<?php
/* Copyright (C) 2026 iPowerWorld */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnotificationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';

/** Orchestrates scheduled operations, financial reversals, retention and diagnostics. */
class SofAgenceIndustrialService
{
	/** @var DoliDB */
	private $db;
	public $error = '';
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Dolibarr cron entrypoint. */
	public function runScheduledOperations()
	{
		$notifications = new SofNotificationService($this->db);
		$integrations = new SofIntegrationService($this->db);
		$results = array(
			'escalations' => $notifications->runEscalations(),
			'collections' => $notifications->synchronizeCollections(),
			'notifications' => $notifications->processQueue(200),
			'retries' => $notifications->processTechnicalRetries(25),
			'webhooks' => $integrations->processWebhooks(200),
			'connectors' => $integrations->syncDueConnectors(20),
		);
		// Scheduled retention archives evidence but never enables purge by itself.
		$retention = $this->applyRetention(null, false, false);
		if (!is_array($retention)) {
			$results['retention'] = -1;
		} else {
			$results['retention'] = (int) $retention['audits_archived'] + (int) $retention['documents_archived'];
		}
		foreach ($results as $value) {
			if ($value < 0) {
				dol_syslog(__METHOD__.' failed: '.json_encode($results), LOG_ERR);
				return -1;
			}
		}
		dol_syslog(__METHOD__.' completed: '.json_encode($results), LOG_INFO);
		return 1;
	}

	/** Request a documented reversal of an immutable financial movement. */
	public function requestReversal(User $user, $movementId, $reason, $evidenceRef = '')
	{
		if (!$this->hasRight($user, 'reversal', 'request')) {
			return $this->fail('Permission refusée pour demander une contrepassation.');
		}
		$reason = trim((string) $reason);
		$evidenceRef = trim((string) $evidenceRef);
		if (strlen($reason) < 10 || strlen($reason) > 4000 || strlen($evidenceRef) > 255) {
			return $this->fail('Le motif documenté doit contenir au moins 10 caractères et la preuve doit être une référence courte.');
		}
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_mouvement WHERE rowid = '.((int) $movementId).' AND entity = '.$this->entity().' AND status = 1';
		$resql = $this->db->query($sql);
		$movement = $resql ? $this->db->fetch_object($resql) : null;
		if (!$movement || !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $movement->fk_agence, 'reversal_request', (float) $movement->amount, (int) $movement->fk_das)) {
			return $this->fail('Mouvement absent ou hors périmètre.');
		}
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'sof_financial_reversal WHERE entity = '.$this->entity().' AND fk_mouvement_original = '.((int) $movementId));
		if ($resql && $this->db->num_rows($resql) > 0) {
			return $this->fail('Une demande de contrepassation existe déjà pour ce mouvement.');
		}
		$ref = $this->makeRef('REV');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_financial_reversal (entity,ref,fk_mouvement_original,reason,evidence_ref,fk_user_request,date_request,status,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().", '".$ref."', ".((int) $movementId).", '".$this->db->escape($reason)."', ".($evidenceRef !== '' ? "'".$this->db->escape($evidenceRef)."'" : 'NULL').', '.((int) $user->id).', CURRENT_TIMESTAMP, 0, CURRENT_TIMESTAMP, '.((int) $user->id).')';
		if (!$this->db->query($sql)) {
			return $this->fail($this->db->lasterror());
		}
		$id = (int) $this->db->last_insert_id($this->db->prefix().'sof_financial_reversal');
		SofAgenceService::logAudit($this->db, $user, 'SOF_FINANCIAL_REVERSAL_REQUEST', $movement, null, array('reversal_id' => $id, 'evidence_ref' => $evidenceRef), $reason);
		$notifications = new SofNotificationService($this->db);
		$notifications->queueEvent('financial_reversal_requested', 'warning', 'Contrepassation à valider '.$ref, 'Mouvement '.$movement->ref.' — '.$reason, 'reversal', $id, (int) $movement->fk_agence);
		return $id;
	}

	/** Approve/reject a reversal with segregation of duties and an opposite ledger line. */
	public function decideReversal(User $user, $reversalId, $approve, $decisionReason)
	{
		if (!$this->hasRight($user, 'reversal', 'approve')) {
			return $this->fail('Permission refusée pour décider une contrepassation.');
		}
		$decisionReason = trim((string) $decisionReason);
		if (strlen($decisionReason) < 5 || strlen($decisionReason) > 4000) {
			return $this->fail('La décision doit être motivée.');
		}
		$this->db->begin();
		$sql = 'SELECT r.*, m.ref movement_ref, m.fk_agence, m.fk_caisse, m.fk_session, m.fk_das, m.fk_soc, m.fk_facture, m.fk_paiement, m.fk_payment_various, m.fk_bank, m.type_operation, m.direction, m.payment_mode, m.amount, m.transaction_ref, m.label movement_label, m.accounting_status';
		$sql .= ' FROM '.$this->db->prefix().'sof_financial_reversal r JOIN '.$this->db->prefix().'sof_caisse_mouvement m ON m.rowid = r.fk_mouvement_original AND m.entity = r.entity';
		$sql .= ' WHERE r.rowid = '.((int) $reversalId).' AND r.entity = '.$this->entity().' FOR UPDATE';
		$resql = $this->db->query($sql);
		$request = $resql ? $this->db->fetch_object($resql) : null;
		if (!$request || (int) $request->status !== 0 || (!getDolGlobalInt('AGENCE_ALLOW_SELF_APPROVAL') && (int) $request->fk_user_request === (int) $user->id)
			|| !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $request->fk_agence, 'reversal_approval', (float) $request->amount, (int) $request->fk_das)) {
			$this->db->rollback();
			return $this->fail('Demande absente, déjà décidée, auto-approbation interdite ou hors périmètre.');
		}
		$movementId = 0;
		if ($approve) {
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissemouvement.class.php';
			$movement = new SofCaisseMouvement($this->db);
			$movement->entity = $this->entity();
			$movement->ref = $this->makeRef('MVT-REV');
			foreach (array('fk_agence','fk_caisse','fk_session','fk_das','fk_soc','fk_facture','fk_paiement','fk_payment_various','fk_bank','payment_mode') as $field) {
				$movement->$field = $request->$field ?: null;
			}
			$movement->type_operation = 'financial_reversal';
			$movement->direction = $request->direction === 'credit' ? 'debit' : 'credit';
			$movement->amount = abs((float) $request->amount);
			$movement->transaction_date = dol_now();
			$movement->source_type = 'reversal';
			$movement->source_id = (int) $request->fk_mouvement_original;
			$movement->transaction_ref = 'REVERSAL-'.$request->movement_ref;
			$movement->label = 'Contrepassation '.$request->movement_ref.' — '.$request->reason;
			$movement->justification_ref = $request->evidence_ref;
			$movement->status = 1;
			$movement->accounting_status = 0;
			$movementId = $movement->create($user, 1);
			if ($movementId <= 0) {
				$this->db->rollback();
				return $this->fail($movement->error, $movement->errors);
			}
		}
		$status = $approve ? 2 : 9;
		$sql = 'UPDATE '.$this->db->prefix().'sof_financial_reversal SET fk_mouvement_reversal = '.($movementId ?: 'NULL').', fk_user_approval = '.((int) $user->id).', date_decision = CURRENT_TIMESTAMP, decision_reason = \''.$this->db->escape($decisionReason)."', status = ".$status.', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $reversalId).' AND entity = '.$this->entity();
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$this->db->commit();
		$source = (object) array('id' => (int) $reversalId, 'rowid' => (int) $reversalId, 'element' => 'sof_financial_reversal', 'fk_agence' => (int) $request->fk_agence, 'fk_caisse' => (int) $request->fk_caisse, 'fk_session' => (int) $request->fk_session);
		SofAgenceService::logAudit($this->db, $user, $approve ? 'SOF_FINANCIAL_REVERSAL_APPROVE' : 'SOF_FINANCIAL_REVERSAL_REJECT', $source, array('status' => 0), array('status' => $status, 'movement_id' => $movementId), $decisionReason);
		$notifications = new SofNotificationService($this->db);
		$notifications->queueEvent($approve ? 'financial_reversal_approved' : 'financial_reversal_rejected', $approve ? 'warning' : 'info', 'Décision contrepassation '.$request->ref, $decisionReason, 'reversal', (int) $reversalId, (int) $request->fk_agence);
		return 1;
	}

	/** Archive old evidence and optionally purge only after an explicit confirmed policy. */
	public function applyRetention(User $user = null, $dryRun = true, $confirmPurge = false)
	{
		if ($user && !$this->hasRight($user, 'archive', 'manage')) {
			return $this->fail('Permission refusée pour la conservation et la purge.');
		}
		$auditDays = max(365, getDolGlobalInt('AGENCE_AUDIT_RETENTION_DAYS', 3650));
		$documentDays = max(365, getDolGlobalInt('AGENCE_DOCUMENT_RETENTION_DAYS', 3650));
		$errorDays = max(90, getDolGlobalInt('AGENCE_TECH_ERROR_RETENTION_DAYS', 730));
		$auditCutoff = dol_now() - ($auditDays * 86400);
		$purgeCutoff = dol_now() - (($auditDays + 365) * 86400);
		$errorCutoff = dol_now() - ($errorDays * 86400);
		$actor = $user ? (int) $user->id : $this->actorId();
		$result = array('audits_to_archive' => 0, 'audits_archived' => 0, 'audits_purged' => 0, 'errors_purged' => 0, 'documents_to_archive' => 0, 'documents_archived' => 0, 'documents_purged' => 0);
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse_auditlog WHERE entity = '.$this->entity().' AND archive_status = 0 AND event_date < \''.$this->db->escape($this->db->idate($auditCutoff))."'";
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$result['audits_to_archive'] = $row ? (int) $row->nb : 0;
		if (!$dryRun && $result['audits_to_archive'] > 0) {
			$purgeAfter = $auditCutoff + (($auditDays + 365) * 86400);
			$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_auditlog SET archive_status = 1, date_archive = CURRENT_TIMESTAMP, purge_after = \''.$this->db->escape($this->db->idate($purgeAfter))."' WHERE entity = ".$this->entity()." AND archive_status = 0 AND event_date < '".$this->db->escape($this->db->idate($auditCutoff))."'";
			$resql = $this->db->query($sql);
			if ($resql) $result['audits_archived'] = $this->db->affected_rows($resql);
		}

		$documents = $this->retentionDocuments($documentDays, $dryRun, $confirmPurge && getDolGlobalInt('AGENCE_ENABLE_PURGE'), $actor);
		$result = array_merge($result, $documents);

		if (!$dryRun && $confirmPurge && getDolGlobalInt('AGENCE_ENABLE_PURGE')) {
			$sql = 'SELECT rowid, action_code, object_type, object_id, event_date, old_value, new_value FROM '.$this->db->prefix().'sof_caisse_auditlog WHERE entity = '.$this->entity().' AND archive_status = 1 AND purge_after IS NOT NULL AND purge_after < CURRENT_TIMESTAMP AND event_date < \''.$this->db->escape($this->db->idate($purgeCutoff))."' ORDER BY rowid".$this->db->plimit(1000, 0);
			$resql = $this->db->query($sql);
			$ids = array();
			while ($resql && ($audit = $this->db->fetch_object($resql))) {
				$hash = hash('sha256', json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
				$this->logArchive('audit', (int) $audit->rowid, '', '', $hash, 'AUDIT_'.($auditDays).'D', 'purge', 'Conservation échue et purge explicitement confirmée', $actor);
				$ids[] = (int) $audit->rowid;
			}
			if ($ids && $this->db->query('DELETE FROM '.$this->db->prefix().'sof_caisse_auditlog WHERE entity = '.$this->entity().' AND rowid IN ('.implode(',', $ids).')')) {
				$result['audits_purged'] = count($ids);
			}
			$sql = 'SELECT rowid, operation_code, object_type, object_id, error_message, date_creation FROM '.$this->db->prefix().'sof_technical_error WHERE entity = '.$this->entity().' AND status IN (2,3) AND date_creation < \''.$this->db->escape($this->db->idate($errorCutoff))."' ORDER BY rowid".$this->db->plimit(1000, 0);
			$resql = $this->db->query($sql);
			$ids = array();
			while ($resql && ($technical = $this->db->fetch_object($resql))) {
				$this->logArchive('technical_error', (int) $technical->rowid, '', '', hash('sha256', json_encode($technical)), 'TECH_ERROR_'.$errorDays.'D', 'purge', 'Erreur résolue ou abandonnée, conservation échue', $actor);
				$ids[] = (int) $technical->rowid;
			}
			if ($ids && $this->db->query('DELETE FROM '.$this->db->prefix().'sof_technical_error WHERE entity = '.$this->entity().' AND rowid IN ('.implode(',', $ids).')')) {
				$result['errors_purged'] = count($ids);
			}
		}
		return $result;
	}

	/** Return diagnostic checks without changing operational data. */
	public function diagnostics(User $user)
	{
		global $conf, $mysoc;
		if (!$this->hasRight($user, 'diagnostic', 'read') && empty($user->admin)) {
			$this->fail('Permission refusée pour le diagnostic.');
			return array();
		}
		$checks = array();
		$tables = array('sof_notification_config','sof_notification_outbox','sof_bank_import','sof_bank_import_line','sof_recouvrement','sof_recouvrement_action','sof_bulk_import','sof_bulk_import_line','sof_technical_error','sof_financial_reversal','sof_archive_log','sof_webhook_endpoint','sof_webhook_delivery','sof_integration_connector','sof_integration_sync','sof_config_transfer');
		foreach ($tables as $table) {
			$resql = $this->db->DDLDescTable($this->db->prefix().$table, '');
			$checks[] = $this->check('schema', $table, $resql && $this->db->num_rows($resql) > 0 ? 'ok' : 'error', $resql && $this->db->num_rows($resql) > 0 ? 'Table installée' : 'Table absente');
		}
		$sql = 'SELECT label, objectname, methodename, status, lastresult, datelastrun, datenextrun FROM '.$this->db->prefix()."cronjob WHERE module_name = 'agence'";
		$resql = $this->db->query($sql);
		$cronCount = 0;
		while ($resql && ($cron = $this->db->fetch_object($resql))) {
			$cronCount++;
			$status = (int) $cron->status === 1 && (int) $cron->lastresult === 0 ? 'ok' : ((int) $cron->status === 1 ? 'warning' : 'error');
			$checks[] = $this->check('cron', $cron->objectname.'::'.$cron->methodename, $status, 'Actif='.(int) $cron->status.', dernier résultat='.(int) $cron->lastresult.', prochain='.$cron->datenextrun);
		}
		if ($cronCount < 2) $checks[] = $this->check('cron', 'jobs_required', 'error', 'Les deux travaux planifiés Agence ne sont pas installés.');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_mapping_comptable WHERE entity = '.$this->entity().' AND status = 1');
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('accounting', 'active_mappings', $row && (int) $row->nb > 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' mapping(s) actif(s)');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix()."sof_mapping_comptable WHERE entity = ".$this->entity()." AND status = 1 AND (journal_code IS NULL OR journal_code = '' OR account_debit IS NULL OR account_debit = '' OR account_credit IS NULL OR account_credit = '')");
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('accounting', 'mapping_completeness', $row && (int) $row->nb === 0 ? 'ok' : 'error', ($row ? (int) $row->nb : 0).' mapping(s) actif(s) incomplet(s)');
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse c LEFT JOIN '.$this->db->prefix().'bank_account ba ON ba.rowid=c.fk_bank_account AND ba.entity=c.entity AND ba.clos=0 WHERE c.entity = '.$this->entity().' AND c.status = 1 AND ba.rowid IS NULL';
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('accounts', 'cash_accounts', $row && (int) $row->nb === 0 ? 'ok' : 'error', ($row ? (int) $row->nb : 0).' caisse(s) active(s) sans compte espèces valide dans l’entité');
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse WHERE entity = '.$this->entity().' AND status = 1 AND (fk_bank_account_card IS NULL OR fk_bank_account_cheque IS NULL OR fk_bank_account_mobile IS NULL OR fk_bank_account_other IS NULL)';
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('accounts', 'payment_mode_accounts', $row && (int) $row->nb === 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' caisse(s) active(s) avec au moins un compte spécialisé non renseigné');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_notification_config WHERE entity = '.$this->entity().' AND status = 1');
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('integrations', 'notifications', $row && (int) $row->nb > 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' règle(s) de notification active(s)');
		$emailFrom = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		if ($emailFrom === '' && is_object($conf->notification ?? null) && !empty($conf->notification->email_from)) $emailFrom = $conf->notification->email_from;
		if ($emailFrom === '' && is_object($mysoc) && !empty($mysoc->email)) $emailFrom = $mysoc->email;
		$checks[] = $this->check('integrations', 'email_sender', filter_var($emailFrom, FILTER_VALIDATE_EMAIL) ? 'ok' : 'warning', filter_var($emailFrom, FILTER_VALIDATE_EMAIL) ? 'Expéditeur Dolibarr configuré' : 'Expéditeur e-mail Dolibarr invalide ou absent');
		$smsReady = getDolGlobalString('AGENCE_SMS_GATEWAY_URL') !== '' && getDolGlobalString('AGENCE_SMS_GATEWAY_TOKEN') !== '';
		$checks[] = $this->check('integrations', 'sms', $smsReady ? 'ok' : 'warning', $smsReady ? 'Passerelle SMS configurée' : 'Passerelle SMS incomplète');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix()."c_paiement WHERE entity IN (0,".$this->entity().") AND code IN ('OM','MM') AND active = 1");
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('integrations', 'mobile_money', $row && (int) $row->nb >= 2 ? 'ok' : 'error', ($row ? (int) $row->nb : 0).'/2 modes opérateur actifs');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_webhook_endpoint WHERE entity = '.$this->entity().' AND status = 1');
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('integrations', 'webhooks', $row && (int) $row->nb > 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' webhook(s) signé(s) actif(s)');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_integration_connector WHERE entity = '.$this->entity().' AND status = 1');
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('integrations', 'payment_connectors', $row && (int) $row->nb > 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' connecteur(s) banque/opérateur actif(s)');
		$checks[] = $this->check('integrations', 'rest_api', isModEnabled('api') ? 'ok' : 'warning', isModEnabled('api') ? 'API REST Dolibarr active ; endpoint /api/index.php/agence' : 'Module API REST Dolibarr inactif');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_technical_error WHERE entity = '.$this->entity().' AND status IN (0,1)');
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('operations', 'technical_errors', $row && (int) $row->nb === 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' erreur(s) ouverte(s) ou en reprise');
		$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_notification_outbox WHERE entity = '.$this->entity().' AND status = 3');
		$row = $resql ? $this->db->fetch_object($resql) : null;
		$checks[] = $this->check('operations', 'failed_notifications', $row && (int) $row->nb === 0 ? 'ok' : 'warning', ($row ? (int) $row->nb : 0).' notification(s) en échec définitif');
		return $checks;
	}

	private function retentionDocuments($days, $dryRun, $purge, $actor)
	{
		global $conf;
		$result = array('documents_to_archive' => 0, 'documents_archived' => 0, 'documents_purged' => 0);
		$root = !empty($conf->agence->dir_output) ? $conf->agence->dir_output : DOL_DATA_ROOT.'/agence';
		$sourceRoot = $root.'/documents/entity_'.$this->entity();
		$archiveRoot = $root.'/archive/entity_'.$this->entity();
		$cutoff = dol_now() - (max(365, (int) $days) * 86400);
		if (is_dir($sourceRoot)) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->getMTime() >= $cutoff) continue;
				$result['documents_to_archive']++;
				if ($dryRun) continue;
				$source = $file->getPathname();
				$relative = ltrim(str_replace('\\', '/', substr($source, strlen($sourceRoot))), '/');
				$target = $archiveRoot.'/'.$relative;
				if (strpos(str_replace('\\', '/', realpath(dirname($source)) ?: dirname($source)), str_replace('\\', '/', realpath($sourceRoot) ?: $sourceRoot)) !== 0) continue;
				if (!is_dir(dirname($target)) && dol_mkdir(dirname($target)) < 0) continue;
				$hash = hash_file('sha256', $source);
				if (@rename($source, $target)) {
					$this->logArchive('document', 0, $source, $target, $hash, 'DOCUMENT_'.$days.'D', 'archive', 'Durée de conservation active atteinte', $actor);
					$result['documents_archived']++;
				}
			}
		}
		if ($purge && is_dir($archiveRoot)) {
			$purgeCutoff = dol_now() - ((max(365, (int) $days) + 365) * 86400);
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($archiveRoot, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->getMTime() >= $purgeCutoff) continue;
				$path = $file->getPathname();
				$resolvedRoot = str_replace('\\', '/', realpath($archiveRoot) ?: $archiveRoot);
				$resolvedPath = str_replace('\\', '/', realpath($path) ?: $path);
				if (strpos($resolvedPath, $resolvedRoot.'/') !== 0) continue;
				$hash = hash_file('sha256', $path);
				if (@unlink($path)) {
					$this->logArchive('document', 0, '', $path, $hash, 'DOCUMENT_'.$days.'D', 'purge', 'Archive échue et purge explicitement confirmée', $actor);
					$result['documents_purged']++;
				}
			}
		}
		return $result;
	}

	private function logArchive($objectType, $objectId, $source, $target, $hash, $policy, $action, $reason, $actor)
	{
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_archive_log (entity,ref,object_type,object_id,document_path,archive_path,content_hash,policy_code,action_type,reason,action_date,fk_user_action,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().", '".$this->makeRef('ARC')."', '".$this->db->escape($objectType)."', ".((int) $objectId ?: 'NULL').', '.($source !== '' ? "'".$this->db->escape($source)."'" : 'NULL').', '.($target !== '' ? "'".$this->db->escape($target)."'" : 'NULL').', '.($hash !== '' ? "'".$this->db->escape($hash)."'" : 'NULL').", '".$this->db->escape($policy)."', '".$this->db->escape($action)."', '".$this->db->escape($reason)."', CURRENT_TIMESTAMP, ".((int) $actor ?: 'NULL').', CURRENT_TIMESTAMP, '.((int) $actor ?: 1).')';
		return $this->db->query($sql) ? 1 : -1;
	}

	private function check($category, $code, $status, $message)
	{
		return array('category' => $category, 'code' => $code, 'status' => $status, 'message' => $message);
	}

	private function hasRight(User $user, $object, $action)
	{
		if (!SofAgenceService::isActiveUser($this->db, $user)) return false;
		if (!empty($user->admin)) return true;
		$probe = new User($this->db);
		if ($probe->fetch((int) $user->id) <= 0 || empty($probe->statut)) return false;
		$probe->loadRights('agence', 1);
		return (bool) $probe->hasRight('agence', $object, $action);
	}

	private function actorId()
	{
		global $user;
		if ($user instanceof User && !empty($user->id)) return (int) $user->id;
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'user WHERE admin = 1 AND statut = 1 AND entity IN ('.getEntity('user').') ORDER BY rowid'.$this->db->plimit(1, 0));
		$row = $resql ? $this->db->fetch_object($resql) : null;
		return $row ? (int) $row->rowid : 1;
	}

	private function entity()
	{
		global $conf;
		return (int) $conf->entity;
	}

	private function makeRef($prefix)
	{
		return $prefix.'-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
	}

	private function fail($message, array $errors = array())
	{
		$this->error = (string) $message;
		$this->errors = $errors;
		return -1;
	}
}
