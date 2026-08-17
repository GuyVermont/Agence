<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofalerte.class.php
 * \ingroup    agence
 * \brief      Alert detector skeleton.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Alert service for agency operations.
 */
class SofAlerte extends SofCommonObject
{
	public $element = 'sof_caisse_alerte';
	public $table_element = 'sof_caisse_alerte';
	public $picto = 'bell';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'index' => 1),
		'dedup_key' => array('type' => 'varchar(255)', 'label' => 'DeduplicationKey'),
		'alert_type' => array('type' => 'varchar(128)', 'label' => 'AlertType', 'notnull' => 1, 'index' => 1),
		'severity' => array('type' => 'varchar(64)', 'label' => 'Severity'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession'),
		'object_type' => array('type' => 'varchar(128)', 'label' => 'ObjectType'),
		'object_id' => array('type' => 'integer', 'label' => 'ObjectId'),
		'message' => array('type' => 'text', 'label' => 'Message', 'notnull' => 1),
		'target_roles' => array('type' => 'text', 'label' => 'TargetRoles'),
		'escalation_level' => array('type' => 'integer', 'label' => 'EscalationLevel', 'notnull' => 1, 'default' => 0),
		'date_last_escalation' => array('type' => 'datetime', 'label' => 'LastEscalationDate'),
		'date_alert' => array('type' => 'datetime', 'label' => 'AlertDate', 'notnull' => 1),
		'date_read' => array('type' => 'datetime', 'label' => 'ReadDate'),
		'date_close' => array('type' => 'datetime', 'label' => 'CloseDate'),
		'fk_user_read' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'ReadBy'),
		'fk_user_close' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'ClosedBy'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1),
	);

	/**
	 * Detect operational alerts. The operation is idempotent: an unresolved alert
	 * with the same type and source object is never duplicated.
	 *
	 * @return int
	 */
	public function detectAlerts()
	{
		global $conf, $user;

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
		$engine = new SofAgenceOperations($this->db);
		if ($user instanceof User) {
			$engine->synchronizeDeferredPayments($user);
		}

		$created = 0;
		$maxHours = max(1, (int) getDolGlobalString('AGENCE_MAX_SESSION_HOURS', '12'));
		$cutoff = dol_now() - ($maxHours * 3600);
		$sql = 'SELECT rowid, ref, fk_agence, fk_caisse FROM '.$this->db->prefix().'sof_caisse_session';
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND status IN (1,2,3,4,5) AND date_opening < '".$this->db->escape($this->db->idate($cutoff))."'";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$created += $this->createIfMissing('session_too_long', 'warning', 'session', (int) $obj->rowid,
				'Session '.$obj->ref.' ouverte depuis plus de '.$maxHours.' heures.', (int) $obj->fk_agence, (int) $obj->fk_caisse, (int) $obj->rowid);
		}

		$sql = 'SELECT rowid, ref, fk_agence, fk_caisse, fk_session, remaining_amount, expected_payment_date';
		$sql .= ' FROM '.$this->db->prefix().'sof_paiement_differe WHERE entity = '.((int) $conf->entity);
		$sql .= " AND status IN (1,2,3,5,6) AND remaining_amount > 0 AND expected_payment_date < '".$this->db->escape($this->db->idate(dol_now()))."'";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$created += $this->createIfMissing('deferred_overdue', 'warning', 'paiementdiffere', (int) $obj->rowid,
				'Paiement différé '.$obj->ref.' échu, reste '.price($obj->remaining_amount).'.', (int) $obj->fk_agence, (int) $obj->fk_caisse, (int) $obj->fk_session);
		}

		$days = max(1, (int) getDolGlobalString('AGENCE_DEPOSIT_ALERT_DAYS', '3'));
		$depositCutoff = dol_now() - ($days * 86400);
		$sql = 'SELECT rowid, ref, fk_agence, fk_caisse_source, fk_session, amount FROM '.$this->db->prefix().'sof_caisse_depot_banque';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND status NOT IN (3,9)';
		$sql .= " AND COALESCE(date_deposit,date_preparation,date_creation) < '".$this->db->escape($this->db->idate($depositCutoff))."'";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$created += $this->createIfMissing('deposit_unreconciled', 'warning', 'depotbanque', (int) $obj->rowid,
				'Dépôt '.$obj->ref.' de '.price($obj->amount).' non rapproché depuis plus de '.$days.' jours.', (int) $obj->fk_agence, (int) $obj->fk_caisse_source, (int) $obj->fk_session);
		}

		$sql = 'SELECT rowid, ref, fk_agence, fk_caisse, fk_session, gap_amount FROM '.$this->db->prefix().'sof_caisse_ecart';
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND status NOT IN (3,9) AND severity = 'critical'";
		$resql = $this->db->query($sql);
		while ($resql && ($obj = $this->db->fetch_object($resql))) {
			$created += $this->createIfMissing('critical_cash_gap', 'critical', 'ecart', (int) $obj->rowid,
				'Écart critique '.$obj->ref.' de '.price($obj->gap_amount).'.', (int) $obj->fk_agence, (int) $obj->fk_caisse, (int) $obj->fk_session);
		}

		dol_syslog(__METHOD__.' created '.$created.' alert(s)', LOG_INFO);
		return $created;
	}

	/** Create one open alert if the same source/type does not already have one. */
	private function createIfMissing($type, $severity, $objectType, $objectId, $message, $fkAgence = 0, $fkCaisse = 0, $fkSession = 0)
	{
		global $conf, $user;
		$dedupKey = substr(strtolower((string) $type).':'.strtolower((string) $objectType).':'.((int) $objectId), 0, 255);

		$sql = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element;
		$sql .= ' WHERE entity = '.((int) $conf->entity)." AND alert_type = '".$this->db->escape($type)."'";
		$sql .= " AND object_type = '".$this->db->escape($objectType)."' AND object_id = ".((int) $objectId).' AND status < 2 LIMIT 1';
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return 0;
		}
		$this->entity = (int) $conf->entity;
		$this->ref = 'ALT-'.date('Ymd-His').'-'.((int) $objectId);
		$this->dedup_key = $dedupKey;
		$this->alert_type = $type;
		$this->severity = $severity;
		$this->fk_agence = $fkAgence ?: null;
		$this->fk_caisse = $fkCaisse ?: null;
		$this->fk_session = $fkSession ?: null;
		$this->object_type = $objectType;
		$this->object_id = (int) $objectId;
		$this->message = $message;
		$this->target_roles = $severity === 'critical' ? 'direction,audit,cash_chief' : 'agency_manager,cash_chief';
		$this->date_alert = dol_now();
		$this->status = 0;
		$actor = $user instanceof User ? $user : $GLOBALS['user'];
		$result = $this->create($actor, 1);
		if ($result > 0) {
			return 1;
		}
		// A concurrent detector may have inserted the same open alert first.
		$sql = 'SELECT rowid FROM '.$this->db->prefix().$this->table_element.' WHERE entity = '.((int) $conf->entity);
		$sql .= " AND dedup_key = '".$this->db->escape($dedupKey)."' AND status < 2 LIMIT 1";
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0 ? 0 : -1;
	}
}
