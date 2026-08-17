<?php
/* Copyright (C) 2026 iPowerWorld */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

/** Multichannel notification, escalation and debt-collection service. */
class SofNotificationService
{
	/** @var DoliDB */
	private $db;
	public $error = '';
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Configure one event/channel destination. */
	public function saveConfiguration(User $user, array $data)
	{
		if (!$this->hasRight($user, 'notification', 'manage')) {
			return $this->fail('Permission refusée pour configurer les notifications.');
		}
		$event = strtolower(trim((string) ($data['event_code'] ?? '')));
		$channel = strtolower(trim((string) ($data['channel'] ?? '')));
		$type = strtolower(trim((string) ($data['recipient_type'] ?? 'address')));
		$recipient = trim((string) ($data['recipient'] ?? ''));
		$severity = strtolower(trim((string) ($data['severity_min'] ?? 'info')));
		$level = max(0, min(3, (int) ($data['escalation_level'] ?? 0)));
		if (!preg_match('/^[a-z0-9_*.-]{1,128}$/', $event) || !in_array($channel, array('internal', 'email', 'sms'), true)
			|| !in_array($type, array('address', 'user', 'role'), true) || $recipient === '' || strlen($recipient) > 255
			|| !in_array($severity, array('info', 'warning', 'critical'), true)) {
			return $this->fail('Configuration de notification invalide.');
		}
		if ($type === 'address' && $channel === 'email' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
			return $this->fail('Adresse e-mail invalide.');
		}
		if ($type === 'address' && $channel === 'sms' && !preg_match('/^\+?[0-9][0-9 .-]{6,24}$/', $recipient)) {
			return $this->fail('Numéro SMS invalide.');
		}
		$entity = $this->entity();
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_notification_config WHERE entity = '.$entity;
		$sql .= " AND event_code = '".$this->db->escape($event)."' AND channel = '".$this->db->escape($channel)."'";
		$sql .= " AND recipient = '".$this->db->escape($recipient)."' AND escalation_level = ".$level;
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		if ($row) {
			$sql = 'UPDATE '.$this->db->prefix()."sof_notification_config SET severity_min = '".$this->db->escape($severity)."', recipient_type = '".$this->db->escape($type)."', status = 1";
			$sql .= ', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $row->rowid).' AND entity = '.$entity;
		} else {
			$sql = 'INSERT INTO '.$this->db->prefix().'sof_notification_config (entity,event_code,severity_min,channel,recipient_type,recipient,escalation_level,status,date_creation,fk_user_creat) VALUES (';
			$sql .= $entity.", '".$this->db->escape($event)."', '".$this->db->escape($severity)."', '".$this->db->escape($channel)."', '".$this->db->escape($type)."', '".$this->db->escape($recipient)."', ".$level.', 1, CURRENT_TIMESTAMP, '.((int) $user->id).')';
		}
		return $this->db->query($sql) ? 1 : $this->fail($this->db->lasterror());
	}

	/** Disable a configuration without destroying its history. */
	public function disableConfiguration(User $user, $id)
	{
		if (!$this->hasRight($user, 'notification', 'manage')) {
			return $this->fail('Permission refusée.');
		}
		$sql = 'UPDATE '.$this->db->prefix().'sof_notification_config SET status = 0, fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP';
		$sql .= ' WHERE rowid = '.((int) $id).' AND entity = '.$this->entity();
		$resql = $this->db->query($sql);
		return $resql && $this->db->affected_rows($resql) > 0 ? 1 : $this->fail('Configuration absente.');
	}

	/** Queue every configured destination for an event. */
	public function queueEvent($eventCode, $severity, $subject, $body, $objectType = '', $objectId = 0, $fkAgence = 0, $level = 0)
	{
		if (!getDolGlobalInt('AGENCE_ENABLE_NOTIFICATIONS', 1)) {
			return 0;
		}
		$eventCode = strtolower(trim((string) $eventCode));
		$severity = strtolower(trim((string) $severity));
		$level = max(0, min(3, (int) $level));
		if (!preg_match('/^[a-z0-9_*.-]{1,128}$/', $eventCode) || !in_array($severity, array('info', 'warning', 'critical'), true)) {
			return $this->fail('Événement de notification invalide.');
		}
		$sql = 'SELECT rowid, channel, recipient_type, recipient, severity_min FROM '.$this->db->prefix().'sof_notification_config';
		$sql .= ' WHERE entity = '.$this->entity()." AND status = 1 AND event_code IN ('*', '".$this->db->escape($eventCode)."')";
		$sql .= ' AND escalation_level = '.$level.' ORDER BY CASE WHEN event_code = \'*\' THEN 1 ELSE 0 END, rowid';
		$resql = $this->db->query($sql);
		$configs = array();
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			if ($this->severityRank($severity) >= $this->severityRank($row->severity_min)) {
				$configs[] = $row;
			}
		}
		if (empty($configs) && $severity === 'critical') {
			$configs[] = (object) array('rowid' => 0, 'channel' => 'internal', 'recipient_type' => 'role', 'recipient' => 'admin', 'severity_min' => 'critical');
		}

		$queued = 0;
		foreach ($configs as $config) {
			$destinations = $this->expandRecipients($config->channel, $config->recipient_type, $config->recipient, (int) $fkAgence);
			foreach ($destinations as $destination) {
				$dedup = hash('sha256', implode('|', array($eventCode, $objectType, (int) $objectId, (int) $config->rowid, $destination, $level)));
				$queued += max(0, $this->queueDirect($eventCode, $severity, $config->channel, $destination, $subject, $body, $objectType, $objectId, $fkAgence, $level, $dedup));
			}
		}
		return $queued;
	}

	/** Queue a known destination, used for customer reminders. */
	public function queueDirect($eventCode, $severity, $channel, $recipient, $subject, $body, $objectType = '', $objectId = 0, $fkAgence = 0, $level = 0, $dedupKey = '')
	{
		$channel = strtolower((string) $channel);
		$recipient = trim((string) $recipient);
		if (!in_array($channel, array('internal', 'email', 'sms'), true) || $recipient === '' || strlen($recipient) > 255) {
			return $this->fail('Destination de notification invalide.');
		}
		if ($dedupKey === '') {
			$dedupKey = hash('sha256', implode('|', array($eventCode, $channel, $recipient, $objectType, (int) $objectId, (int) $level)));
		}
		$actor = $this->actorId();
		$ref = $this->makeRef('NTF');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_notification_outbox (entity,ref,dedup_key,event_code,severity,channel,recipient,subject,body,object_type,object_id,fk_agence,escalation_level,attempts,max_attempts,next_attempt,status,date_creation,fk_user_creat)';
		$sql .= ' SELECT '.$this->entity().", '".$this->db->escape($ref)."', '".$this->db->escape(substr($dedupKey, 0, 255))."', '".$this->db->escape($eventCode)."', '".$this->db->escape($severity)."', '".$this->db->escape($channel)."', '".$this->db->escape($recipient)."', '".$this->db->escape(substr((string) $subject, 0, 255))."', '".$this->db->escape((string) $body)."', ";
		$sql .= ($objectType !== '' ? "'".$this->db->escape($objectType)."'" : 'NULL').', '.((int) $objectId ?: 'NULL').', '.((int) $fkAgence ?: 'NULL').', '.((int) $level).', 0, 5, CURRENT_TIMESTAMP, 0, CURRENT_TIMESTAMP, '.$actor;
		$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix()."sof_notification_outbox WHERE entity = ".$this->entity()." AND dedup_key = '".$this->db->escape(substr($dedupKey, 0, 255))."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			// The unique index also protects against concurrent queue workers.
			$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix()."sof_notification_outbox WHERE entity = ".$this->entity()." AND dedup_key = '".$this->db->escape(substr($dedupKey, 0, 255))."'");
			return $resql && $this->db->num_rows($resql) > 0 ? 0 : $this->fail($this->db->lasterror());
		}
		return $this->db->affected_rows($resql) > 0 ? 1 : 0;
	}

	/** Deliver pending messages with exponential retry. */
	public function processQueue($limit = 100)
	{
		$limit = max(1, min(500, (int) $limit));
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_notification_outbox WHERE entity = '.$this->entity();
		$sql .= ' AND status IN (0,2) AND attempts < max_attempts AND (next_attempt IS NULL OR next_attempt <= CURRENT_TIMESTAMP) ORDER BY rowid';
		$sql .= $this->db->plimit($limit, 0);
		$resql = $this->db->query($sql);
		$ids = array();
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$ids[] = (int) $row->rowid;
		}
		$sent = 0;
		foreach ($ids as $id) {
			$this->db->begin();
			$sql = 'SELECT * FROM '.$this->db->prefix().'sof_notification_outbox WHERE rowid = '.$id.' AND entity = '.$this->entity().' FOR UPDATE';
			$resql = $this->db->query($sql);
			$item = $resql ? $this->db->fetch_object($resql) : null;
			if (!$item || !in_array((int) $item->status, array(0, 2), true) || (int) $item->attempts >= (int) $item->max_attempts) {
				$this->db->rollback();
				continue;
			}
			$deliveryError = '';
			$ok = $this->deliver($item, $deliveryError);
			$attempts = (int) $item->attempts + 1;
			if ($ok) {
				$sql = 'UPDATE '.$this->db->prefix().'sof_notification_outbox SET status = 1, attempts = '.$attempts.', date_sent = CURRENT_TIMESTAMP, last_error = NULL, tms = CURRENT_TIMESTAMP WHERE rowid = '.$id;
				$sent++;
			} else {
				$minutes = min(1440, (int) pow(2, min(10, $attempts)) * 5);
				$next = dol_now() + ($minutes * 60);
				$status = $attempts >= (int) $item->max_attempts ? 3 : 2;
				$sql = 'UPDATE '.$this->db->prefix().'sof_notification_outbox SET status = '.$status.', attempts = '.$attempts.", next_attempt = '".$this->db->escape($this->db->idate($next))."', last_error = '".$this->db->escape(substr($deliveryError, 0, 4000))."', tms = CURRENT_TIMESTAMP WHERE rowid = ".$id;
			}
			if (!$this->db->query($sql)) {
				$this->db->rollback();
				continue;
			}
			$this->db->commit();
			if (!$ok && $attempts >= (int) $item->max_attempts) {
				$this->recordTechnicalError('notification_delivery', 'notification', $id, 'notification', array('outbox_id' => $id), $deliveryError, 1);
			}
		}
		return $sent;
	}

	/** Escalate critical alerts and overdue approval tasks. */
	public function runEscalations()
	{
		$created = 0;
		$criticalMinutes = max(1, getDolGlobalInt('AGENCE_CRITICAL_ESCALATION_MINUTES', 15));
		$sql = 'SELECT rowid, ref, alert_type, message, object_type, object_id, fk_agence, escalation_level, date_alert FROM '.$this->db->prefix().'sof_caisse_alerte';
		$sql .= " WHERE entity = ".$this->entity()." AND status = 0 AND severity = 'critical'";
		$resql = $this->db->query($sql);
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$age = max(0, dol_now() - (int) $this->db->jdate($row->date_alert));
			$level = min(3, (int) floor($age / ($criticalMinutes * 60)));
			if ($level > (int) $row->escalation_level && $level > 0) {
				$created += max(0, $this->queueEvent('critical_alert', 'critical', 'Escalade alerte critique '.$row->ref, $row->message, $row->object_type, (int) $row->object_id, (int) $row->fk_agence, $level));
				$this->db->query('UPDATE '.$this->db->prefix().'sof_caisse_alerte SET escalation_level = '.$level.', date_last_escalation = CURRENT_TIMESTAMP, tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $row->rowid).' AND entity = '.$this->entity());
			}
		}

		$validationHours = max(1, getDolGlobalInt('AGENCE_VALIDATION_ESCALATION_HOURS', 24));
		$sql = 'SELECT rowid, ref, object_type, object_id, role_required, escalation_level, date_request FROM '.$this->db->prefix().'sof_caisse_validation';
		$sql .= ' WHERE entity = '.$this->entity().' AND status = 0';
		$resql = $this->db->query($sql);
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$age = max(0, dol_now() - (int) $this->db->jdate($row->date_request));
			$level = min(3, (int) floor($age / ($validationHours * 3600)));
			if ($level > (int) $row->escalation_level && $level > 0) {
				$body = 'La validation '.$row->ref.' ('.$row->object_type.' #'.((int) $row->object_id).') est en retard. Rôle attendu : '.$row->role_required.'.';
				$fkAgence = SofAgenceService::validationAgencyId($this->db, $row->object_type, (int) $row->object_id);
				$created += max(0, $this->queueEvent('validation_overdue', $level >= 2 ? 'critical' : 'warning', 'Validation en retard '.$row->ref, $body, 'validation', (int) $row->rowid, $fkAgence, $level));
				$this->db->query('UPDATE '.$this->db->prefix().'sof_caisse_validation SET escalation_level = '.$level.', date_last_escalation = CURRENT_TIMESTAMP, tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $row->rowid).' AND entity = '.$this->entity());
			}
		}
		return $created;
	}

	/** Create/update collection cases and queue staged customer reminders. */
	public function synchronizeCollections()
	{
		$sql = 'SELECT d.rowid, d.ref, d.fk_facture, d.fk_soc, d.fk_agence, d.remaining_amount, d.expected_payment_date, s.email, s.nom';
		$sql .= ' FROM '.$this->db->prefix().'sof_paiement_differe d LEFT JOIN '.$this->db->prefix().'societe s ON s.rowid = d.fk_soc';
		$sql .= ' WHERE d.entity = '.$this->entity().' AND d.status NOT IN (4,7,9) AND d.remaining_amount > 0 AND d.expected_payment_date < CURRENT_DATE';
		$resql = $this->db->query($sql);
		$count = 0;
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$due = $this->db->jdate($row->expected_payment_date);
			$days = max(1, (int) floor((dol_now() - $due) / 86400));
			$stage = $days >= 30 ? 'dispute' : ($days >= 15 ? 'formal_notice' : ($days >= 7 ? 'reminder2' : 'reminder1'));
			$priority = $days >= 30 ? 'critical' : ($days >= 15 ? 'high' : 'normal');
			$sqlCase = 'SELECT rowid, stage, status FROM '.$this->db->prefix().'sof_recouvrement WHERE entity = '.$this->entity().' AND fk_paiement_differe = '.((int) $row->rowid);
			$resultCase = $this->db->query($sqlCase);
			$case = $resultCase ? $this->db->fetch_object($resultCase) : null;
			$previousStage = $case ? $case->stage : '';
			if (!$case) {
				$caseRef = $this->makeRef('RCV');
				$sqlInsert = 'INSERT INTO '.$this->db->prefix().'sof_recouvrement (entity,ref,fk_paiement_differe,fk_facture,fk_soc,fk_agence,stage,priority,outstanding_amount,next_action_date,status,date_open,date_creation,fk_user_creat) VALUES (';
				$sqlInsert .= $this->entity().", '".$this->db->escape($caseRef)."', ".((int) $row->rowid).', '.((int) $row->fk_facture ?: 'NULL').', '.((int) $row->fk_soc).', '.((int) $row->fk_agence ?: 'NULL').", '".$stage."', '".$priority."', ".price2num($row->remaining_amount).", CURRENT_DATE, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ".$this->actorId().')';
				if (!$this->db->query($sqlInsert)) {
					$this->recordTechnicalError('collection_sync', 'paiementdiffere', (int) $row->rowid, 'collection', array('deferred_id' => (int) $row->rowid), $this->db->lasterror(), 3);
					continue;
				}
				$caseId = (int) $this->db->last_insert_id($this->db->prefix().'sof_recouvrement');
			} else {
				$caseId = (int) $case->rowid;
				$sqlUpdate = 'UPDATE '.$this->db->prefix()."sof_recouvrement SET stage = '".$stage."', priority = '".$priority."', outstanding_amount = ".price2num($row->remaining_amount).', next_action_date = CURRENT_DATE, status = 0, tms = CURRENT_TIMESTAMP WHERE rowid = '.$caseId.' AND entity = '.$this->entity();
				$this->db->query($sqlUpdate);
			}
			if ($previousStage !== $stage) {
				$subject = 'Relance '.$row->ref.' — échéance dépassée de '.$days.' jour(s)';
				$body = 'Bonjour, le règlement '.$row->ref.' présente un solde de '.price($row->remaining_amount).' arrivé à échéance. Merci de régulariser ou de contacter votre agence.';
				if (!empty($row->email) && filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
					$this->queueDirect('collection_'.$stage, $priority === 'critical' ? 'critical' : 'warning', 'email', $row->email, $subject, $body, 'recouvrement', $caseId, (int) $row->fk_agence, 0, hash('sha256', 'collection|'.$caseId.'|'.$stage.'|'.$row->email));
				} else {
					$this->queueDirect('collection_'.$stage, 'warning', 'internal', 'role:agency_manager', 'Relance sans e-mail '.$row->ref, $body, 'recouvrement', $caseId, (int) $row->fk_agence, 0, hash('sha256', 'collection-internal|'.$caseId.'|'.$stage));
				}
				$this->queueEvent('collection_'.$stage, $priority === 'critical' ? 'critical' : 'warning', $subject, $body, 'recouvrement', $caseId, (int) $row->fk_agence, 0);
			}
			$count++;
		}
		// Close cases whose deferred payment is settled or closed.
		$sql = 'UPDATE '.$this->db->prefix().'sof_recouvrement SET status = 2, stage = \'closed\', date_close = CURRENT_TIMESTAMP, closure_reason = \'Solde régularisé\', tms = CURRENT_TIMESTAMP';
		$sql .= ' WHERE entity = '.$this->entity().' AND status < 2 AND fk_paiement_differe IN (SELECT rowid FROM '.$this->db->prefix().'sof_paiement_differe WHERE entity = '.$this->entity().' AND (remaining_amount <= 0 OR status IN (4,7,9)))';
		$this->db->query($sql);
		return $count;
	}

	/** Add a controlled collection action or promise. */
	public function addCollectionAction(User $user, $caseId, $actionType, $channel, $outcome, $notes, $nextActionDate = '', $promiseDate = '', $promiseAmount = 0)
	{
		if (!$this->hasRight($user, 'recouvrement', 'manage')) {
			return $this->fail('Permission refusée pour le recouvrement.');
		}
		$allowedActions = array('call', 'email', 'sms', 'visit', 'formal_notice', 'promise', 'dispute', 'close');
		$allowedChannels = array('', 'internal', 'email', 'sms', 'phone', 'visit');
		if (!in_array($actionType, $allowedActions, true) || !in_array($channel, $allowedChannels, true) || trim($notes) === '') {
			return $this->fail('Action de recouvrement invalide ou non documentée.');
		}
		if ($nextActionDate !== '' && !$this->validDate($nextActionDate)) {
			return $this->fail('Date de prochaine action invalide.');
		}
		if ($promiseDate !== '' && (!$this->validDate($promiseDate) || (float) $promiseAmount <= 0)) {
			return $this->fail('Promesse de paiement invalide.');
		}
		$this->db->begin();
		$resql = $this->db->query('SELECT * FROM '.$this->db->prefix().'sof_recouvrement WHERE rowid = '.((int) $caseId).' AND entity = '.$this->entity().' FOR UPDATE');
		$case = $resql ? $this->db->fetch_object($resql) : null;
		if (!$case || (int) $case->status >= 2 || (!empty($case->fk_agence) && !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $case->fk_agence, 'collection', (float) $case->outstanding_amount))) {
			$this->db->rollback();
			return $this->fail('Dossier de recouvrement absent, fermé ou hors périmètre.');
		}
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_recouvrement_action (entity,fk_recouvrement,action_type,channel,outcome,notes,next_action_date,date_action,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().', '.((int) $caseId).", '".$this->db->escape($actionType)."', ".($channel !== '' ? "'".$this->db->escape($channel)."'" : 'NULL').', '.($outcome !== '' ? "'".$this->db->escape($outcome)."'" : 'NULL').", '".$this->db->escape($notes)."', ".($nextActionDate !== '' ? "'".$this->db->escape($nextActionDate)."'" : 'NULL').', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '.((int) $user->id).')';
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$stage = $actionType === 'promise' ? 'promise' : ($actionType === 'dispute' ? 'dispute' : ($actionType === 'close' ? 'closed' : $case->stage));
		$status = $actionType === 'close' ? 2 : 1;
		$sql = 'UPDATE '.$this->db->prefix()."sof_recouvrement SET stage = '".$this->db->escape($stage)."', status = ".$status.', last_contact_date = CURRENT_TIMESTAMP, next_action_date = '.($nextActionDate !== '' ? "'".$this->db->escape($nextActionDate)."'" : 'NULL');
		$sql .= ', promise_date = '.($promiseDate !== '' ? "'".$this->db->escape($promiseDate)."'" : 'NULL').', promise_amount = '.($promiseDate !== '' ? price2num($promiseAmount) : 'NULL');
		$sql .= ', date_close = '.($status === 2 ? 'CURRENT_TIMESTAMP' : 'NULL').', closure_reason = '.($status === 2 ? "'".$this->db->escape($notes)."'" : 'NULL').', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $caseId).' AND entity = '.$this->entity();
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$this->db->commit();
		return 1;
	}

	/** Persist a technical failure without secret-bearing exception traces. */
	public function recordTechnicalError($operationCode, $objectType, $objectId, $retryHandler, array $payload, $message, $maxAttempts = 3)
	{
		$allowedHandlers = array('notification', 'bank_match', 'collection', 'accounting_session', 'none');
		if (!in_array($retryHandler, $allowedHandlers, true)) {
			$retryHandler = 'none';
		}
		$payload = $this->sanitizePayload($payload);
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_technical_error (entity,ref,operation_code,retry_handler,object_type,object_id,payload,error_class,error_message,attempts,max_attempts,next_retry,status,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().", '".$this->db->escape($this->makeRef('ERR'))."', '".$this->db->escape(substr((string) $operationCode, 0, 128))."', '".$this->db->escape($retryHandler)."', ".($objectType !== '' ? "'".$this->db->escape(substr($objectType, 0, 128))."'" : 'NULL').', '.((int) $objectId ?: 'NULL').", '".$this->db->escape(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."', NULL, '".$this->db->escape(substr((string) $message, 0, 4000))."', 0, ".max(1, min(20, (int) $maxAttempts)).', CURRENT_TIMESTAMP, 0, CURRENT_TIMESTAMP, '.$this->actorId().')';
		return $this->db->query($sql) ? (int) $this->db->last_insert_id($this->db->prefix().'sof_technical_error') : -1;
	}

	/** Retry an allowlisted failed operation, manually or from cron. */
	public function retryTechnicalError($errorId, User $user = null)
	{
		if ($user && !$this->hasRight($user, 'technicalerror', 'manage')) {
			return $this->fail('Permission refusée pour relancer une opération.');
		}
		$this->db->begin();
		$resql = $this->db->query('SELECT * FROM '.$this->db->prefix().'sof_technical_error WHERE rowid = '.((int) $errorId).' AND entity = '.$this->entity().' FOR UPDATE');
		$item = $resql ? $this->db->fetch_object($resql) : null;
		if (!$item || !in_array((int) $item->status, array(0, 1), true) || (int) $item->attempts >= (int) $item->max_attempts) {
			$this->db->rollback();
			return $this->fail('Erreur absente, résolue ou nombre de tentatives épuisé.');
		}
		$payload = json_decode((string) $item->payload, true);
		$payload = is_array($payload) ? $payload : array();
		$ok = false;
		if ($item->retry_handler === 'notification' && !empty($payload['outbox_id'])) {
			$sql = 'UPDATE '.$this->db->prefix().'sof_notification_outbox SET status = 0, next_attempt = CURRENT_TIMESTAMP, tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $payload['outbox_id']).' AND entity = '.$this->entity().' AND status IN (2,3)';
			$ok = (bool) $this->db->query($sql);
		} elseif ($item->retry_handler === 'collection') {
			$ok = $this->synchronizeCollections() >= 0;
		} elseif ($item->retry_handler === 'accounting_session' && $user && !empty($payload['session_id'])) {
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
			$engine = new SofAgenceOperations($this->db);
			$ok = $engine->transitionSession($user, (int) $payload['session_id'], 'account') > 0;
			if (!$ok) {
				$this->error = $engine->error;
			}
		}
		$attempts = (int) $item->attempts + 1;
		if ($ok) {
			$sql = 'UPDATE '.$this->db->prefix().'sof_technical_error SET status = 2, attempts = '.$attempts.', date_last_attempt = CURRENT_TIMESTAMP, date_resolution = CURRENT_TIMESTAMP, resolution_note = \'Reprise contrôlée réussie\', fk_user_resolution = '.($user ? (int) $user->id : 'NULL').', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $errorId);
		} else {
			$status = $attempts >= (int) $item->max_attempts ? 3 : 1;
			$next = dol_now() + min(86400, (int) pow(2, min(10, $attempts)) * 300);
			$sql = 'UPDATE '.$this->db->prefix().'sof_technical_error SET status = '.$status.', attempts = '.$attempts.', date_last_attempt = CURRENT_TIMESTAMP, next_retry = \''.$this->db->escape($this->db->idate($next))."', resolution_note = '".$this->db->escape(substr($this->error ?: 'Reprise non aboutie', 0, 4000))."', tms = CURRENT_TIMESTAMP WHERE rowid = ".((int) $errorId);
		}
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$this->db->commit();
		return $ok ? 1 : -1;
	}

	/** Retry safe automated handlers whose backoff has elapsed. */
	public function processTechnicalRetries($limit = 25)
	{
		$sql = 'SELECT rowid FROM '.$this->db->prefix()."sof_technical_error WHERE entity = ".$this->entity()." AND status IN (0,1) AND retry_handler IN ('notification','collection')";
		$sql .= ' AND attempts < max_attempts AND (next_retry IS NULL OR next_retry <= CURRENT_TIMESTAMP) ORDER BY rowid'.$this->db->plimit(max(1, min(100, (int) $limit)), 0);
		$resql = $this->db->query($sql);
		$ids = array();
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$ids[] = (int) $row->rowid;
		}
		$resolved = 0;
		foreach ($ids as $id) {
			$resolved += $this->retryTechnicalError($id) > 0 ? 1 : 0;
		}
		return $resolved;
	}

	private function deliver($item, &$error)
	{
		$error = '';
		if ($item->channel === 'internal') {
			return true; // The sent outbox row is the persistent internal inbox item.
		}
		if ($item->channel === 'email') {
			if (!filter_var($item->recipient, FILTER_VALIDATE_EMAIL)) {
				$error = 'Adresse e-mail invalide.';
				return false;
			}
			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			global $conf, $mysoc;
			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
			if ($from === '' && is_object($conf->notification ?? null) && !empty($conf->notification->email_from)) {
				$from = $conf->notification->email_from;
			}
			if ($from === '' && is_object($mysoc) && !empty($mysoc->email)) {
				$from = $mysoc->email;
			}
			if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
				$error = 'Expéditeur e-mail Dolibarr non configuré.';
				return false;
			}
			$mail = new CMailFile($item->subject, $item->recipient, $from, nl2br(dol_escape_htmltag($item->body)), array(), array(), array(), '', '', 0, 1, '', '', '', '', 'notification');
			if (!$mail->sendfile()) {
				$error = $mail->error ?: 'Échec de l’envoi e-mail.';
				return false;
			}
			return true;
		}
		if ($item->channel === 'sms') {
			$url = getDolGlobalString('AGENCE_SMS_GATEWAY_URL');
			$token = getDolGlobalString('AGENCE_SMS_GATEWAY_TOKEN');
			if ($url === '' || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' || $token === '') {
				$error = 'Passerelle SMS HTTPS ou jeton non configuré.';
				return false;
			}
			require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
			$payload = json_encode(array('to' => $item->recipient, 'message' => $item->body, 'reference' => $item->ref), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$response = getURLContent($url, 'POSTALREADYFORMATED', $payload, 0, array('Content-Type: application/json', 'Authorization: Bearer '.$token), array('https'), 0, 1, 5, 15);
			if (empty($response['http_code']) || (int) $response['http_code'] < 200 || (int) $response['http_code'] >= 300) {
				$error = 'Passerelle SMS HTTP '.((int) ($response['http_code'] ?? 0)).' '.substr((string) ($response['curl_error_msg'] ?? ''), 0, 500);
				return false;
			}
			return true;
		}
		$error = 'Canal inconnu.';
		return false;
	}

	private function expandRecipients($channel, $type, $recipient, $fkAgence)
	{
		if ($type === 'address') {
			return array($recipient);
		}
		$sql = 'SELECT DISTINCT u.rowid, u.email, u.office_phone, u.user_mobile, u.personal_mobile FROM '.$this->db->prefix().'user u';
		$userEntities = getEntity('user');
		if ($type === 'user') {
			$sql .= ' WHERE u.rowid = '.((int) $recipient).' AND u.statut = 1 AND u.entity IN ('.$userEntities.')';
		} else {
			if ($recipient === 'admin') {
				$sql .= ' WHERE u.admin = 1 AND u.statut = 1 AND u.entity IN ('.$userEntities.')';
			} else {
				$sql .= ' JOIN '.$this->db->prefix().'sof_agence_user au ON au.fk_user = u.rowid AND au.entity = '.$this->entity().' AND au.status = 1';
				$sql .= " WHERE u.statut = 1 AND u.entity IN (".$userEntities.") AND au.role_code = '".$this->db->escape($recipient)."'";
				if ($fkAgence > 0) {
					$sql .= ' AND au.fk_agence = '.((int) $fkAgence);
				}
			}
		}
		$resql = $this->db->query($sql);
		$result = array();
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			if ($channel === 'email' && filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
				$result[] = $row->email;
			} elseif ($channel === 'sms') {
				$phone = $row->user_mobile ?: ($row->personal_mobile ?: $row->office_phone);
				if ($phone) {
					$result[] = $phone;
				}
			} elseif ($channel === 'internal') {
				$result[] = 'user:'.((int) $row->rowid);
			}
		}
		if ($channel === 'internal' && empty($result)) {
			$result[] = 'role:'.$recipient;
		}
		return array_values(array_unique($result));
	}

	private function sanitizePayload(array $payload)
	{
		$result = array();
		foreach ($payload as $key => $value) {
			if (preg_match('/password|secret|token|authorization|cookie|api[_-]?key/i', (string) $key)) {
				$result[$key] = '[REDACTED]';
			} elseif (is_array($value)) {
				$result[$key] = $this->sanitizePayload($value);
			} elseif (is_scalar($value) || $value === null) {
				$result[$key] = is_string($value) ? substr($value, 0, 4000) : $value;
			}
		}
		return $result;
	}

	private function hasRight(User $user, $object, $action)
	{
		if (!SofAgenceService::isActiveUser($this->db, $user)) {
			return false;
		}
		if (!empty($user->admin)) {
			return true;
		}
		$probe = new User($this->db);
		if ($probe->fetch((int) $user->id) <= 0 || empty($probe->statut)) {
			return false;
		}
		$probe->loadRights('agence', 1);
		return (bool) $probe->hasRight('agence', $object, $action);
	}

	private function entity()
	{
		global $conf;
		return (int) $conf->entity;
	}

	private function actorId()
	{
		global $user;
		if ($user instanceof User && !empty($user->id)) {
			return (int) $user->id;
		}
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'user WHERE admin = 1 AND statut = 1 AND entity IN ('.getEntity('user').') ORDER BY rowid'.$this->db->plimit(1, 0));
		$row = $resql ? $this->db->fetch_object($resql) : null;
		return $row ? (int) $row->rowid : 1;
	}

	private function makeRef($prefix)
	{
		return $prefix.'-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
	}

	private function severityRank($severity)
	{
		return array_search(strtolower((string) $severity), array('info', 'warning', 'critical'), true) ?: 0;
	}

	private function validDate($date)
	{
		$parsed = DateTime::createFromFormat('Y-m-d', (string) $date);
		return $parsed && $parsed->format('Y-m-d') === $date;
	}

	private function fail($message, array $errors = array())
	{
		$this->error = (string) $message;
		$this->errors = $errors;
		return -1;
	}
}
