<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofagenceservice.class.php
 * \ingroup    agence
 * \brief      Business services for agency scope, audit and workflows.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofauditlog.class.php';

/**
 * Cross-object services for the Agence module.
 */
class SofAgenceService
{
	/**
	 * Return allowed agency ids for a user.
	 *
	 * A null return value means unrestricted access (administrator or an active
	 * enterprise-wide transversal role). An empty array means no agency access.
	 *
	 * @param DoliDB $db   Database handler
	 * @param User   $user User
	 * @return array<int,int>|null
	 */
	public static function allowedAgencyIds(DoliDB $db, User $user)
	{
		if (!empty($user->admin)) {
			return null;
		}
		$nowSql = "'".$db->escape($db->idate(dol_now()))."'";
		$sql = 'SELECT scope_type, scope_value FROM '.$db->prefix().'sof_role_transversal';
		$sql .= ' WHERE entity IN ('.getEntity('sof_role_transversal').') AND fk_user = '.((int) $user->id).' AND status = 1';
		$sql .= ' AND (date_start IS NULL OR date_start <= '.$nowSql.')';
		$sql .= ' AND (date_end IS NULL OR date_end >= '.$nowSql.')';
		$resql = $db->query($sql);
		$ids = array();
		while ($resql && ($obj = $db->fetch_object($resql))) {
			if (in_array(strtolower((string) $obj->scope_type), array('all', 'enterprise', 'global'), true)) {
				return null;
			}
			foreach (preg_split('/[^0-9]+/', (string) $obj->scope_value) as $id) {
				if ((int) $id > 0) {
					$ids[(int) $id] = (int) $id;
				}
			}
		}

		$sql = 'SELECT fk_agence FROM '.$db->prefix().'sof_agence_user';
		$sql .= ' WHERE entity IN ('.getEntity('sof_agence_user').') AND fk_user = '.((int) $user->id).' AND status = 1';
		$sql .= ' AND (date_start IS NULL OR date_start <= '.$nowSql.')';
		$sql .= ' AND (date_end IS NULL OR date_end >= '.$nowSql.')';
		$resql = $db->query($sql);
		while ($resql && ($obj = $db->fetch_object($resql))) {
			if ((int) $obj->fk_agence > 0) {
				$ids[(int) $obj->fk_agence] = (int) $obj->fk_agence;
			}
		}
		return array_values($ids);
	}

	/**
	 * Parse a configured list of positive identifiers.
	 *
	 * CSV, semicolon, pipe, whitespace and JSON-like brackets are accepted so
	 * existing module configurations remain compatible. Any other character
	 * makes the list invalid instead of silently turning it into an unrestricted
	 * configuration.
	 *
	 * @param mixed $raw   Raw list
	 * @param bool  $valid Syntax validity returned by reference
	 * @return array<int,int>
	 */
	public static function parseIdList($raw, &$valid = null)
	{
		$valid = true;
		$value = trim((string) $raw);
		if ($value === '') {
			return array();
		}
		if ($value[0] === '[' || substr($value, -1) === ']') {
			if ($value[0] !== '[' || substr($value, -1) !== ']') {
				$valid = false;
				return array();
			}
			$value = trim(substr($value, 1, -1));
		}
		if ($value === '') {
			return array();
		}
		if (preg_match('/[^0-9,;|\s]/', $value)) {
			$valid = false;
			return array();
		}

		$ids = array();
		foreach (preg_split('/[,;|\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) as $item) {
			if (!ctype_digit($item) || (int) $item <= 0) {
				$valid = false;
				return array();
			}
			$ids[(int) $item] = (int) $item;
		}
		return array_values($ids);
	}

	/**
	 * Validate the agency/cash-desk/DAS tuple against the current entity.
	 *
	 * Empty optional identifiers are accepted. A cash desk always requires an
	 * agency. Non-empty agency and cash-desk DAS lists are restrictive.
	 *
	 * @param DoliDB $db            Database handler
	 * @param int    $fkAgence      Agency id
	 * @param int    $fkCaisse      Cash desk id
	 * @param int    $fkDas         DAS id
	 * @param bool   $requireActive Require active referenced records
	 * @return string Empty on success, validation error otherwise
	 */
	public static function validateAgencyCashDeskDas(DoliDB $db, $fkAgence, $fkCaisse = 0, $fkDas = 0, $requireActive = true)
	{
		global $conf;

		$fkAgence = (int) $fkAgence;
		$fkCaisse = (int) $fkCaisse;
		$fkDas = (int) $fkDas;
		$entity = (int) $conf->entity;
		$agency = null;
		$cashDesk = null;

		if ($fkCaisse > 0 && $fkAgence <= 0) {
			return 'Une caisse doit être rattachée à une agence.';
		}
		if ($fkAgence > 0) {
			$sql = 'SELECT rowid, status, allowed_das FROM '.$db->prefix().'sof_agence';
			$sql .= ' WHERE entity = '.$entity.' AND rowid = '.$fkAgence.' LIMIT 1';
			$resql = $db->query($sql);
			$agency = $resql ? $db->fetch_object($resql) : null;
			if (!$agency) {
				return 'L’agence sélectionnée est introuvable dans l’entité courante.';
			}
			if ($requireActive && (int) $agency->status !== 1) {
				return 'L’agence sélectionnée est inactive.';
			}
		}
		if ($fkCaisse > 0) {
			$sql = 'SELECT rowid, fk_agence, status, allowed_das FROM '.$db->prefix().'sof_caisse';
			$sql .= ' WHERE entity = '.$entity.' AND rowid = '.$fkCaisse.' LIMIT 1';
			$resql = $db->query($sql);
			$cashDesk = $resql ? $db->fetch_object($resql) : null;
			if (!$cashDesk) {
				return 'La caisse sélectionnée est introuvable dans l’entité courante.';
			}
			if ((int) $cashDesk->fk_agence !== $fkAgence) {
				return 'La caisse sélectionnée n’appartient pas à l’agence choisie.';
			}
			if ($requireActive && (int) $cashDesk->status !== 1) {
				return 'La caisse sélectionnée est inactive.';
			}
		}
		if ($fkDas > 0) {
			$sql = 'SELECT rowid, status FROM '.$db->prefix().'sof_das';
			$sql .= ' WHERE entity = '.$entity.' AND rowid = '.$fkDas.' LIMIT 1';
			$resql = $db->query($sql);
			$das = $resql ? $db->fetch_object($resql) : null;
			if (!$das) {
				return 'Le DAS sélectionné est introuvable dans l’entité courante.';
			}
			if ($requireActive && (int) $das->status !== 1) {
				return 'Le DAS sélectionné est inactif.';
			}

			if ($agency) {
				$validList = true;
				$allowedDas = self::parseIdList($agency->allowed_das, $validList);
				if (!$validList) {
					return 'La liste des DAS autorisés de l’agence est invalide.';
				}
				if (!empty($allowedDas) && !in_array($fkDas, $allowedDas, true)) {
					return 'Le DAS sélectionné n’est pas autorisé pour cette agence.';
				}
			}
			if ($cashDesk) {
				$validList = true;
				$allowedDas = self::parseIdList($cashDesk->allowed_das, $validList);
				if (!$validList) {
					return 'La liste des DAS autorisés de la caisse est invalide.';
				}
				if (!empty($allowedDas) && !in_array($fkDas, $allowedDas, true)) {
					return 'Le DAS sélectionné n’est pas autorisé pour cette caisse.';
				}
			}
		}

		return '';
	}

	/**
	 * Validate a DAS allowlist stored on an agency or cash desk.
	 *
	 * @param DoliDB $db            Database handler
	 * @param int    $fkAgence      Parent agency, or 0 for an agency object
	 * @param mixed  $rawAllowedDas Configured list
	 * @param bool   $requireActive Require active DAS records
	 * @return string Empty on success, validation error otherwise
	 */
	public static function validateAllowedDasConfiguration(DoliDB $db, $fkAgence, $rawAllowedDas, $requireActive = true)
	{
		global $conf;

		$validList = true;
		$ids = self::parseIdList($rawAllowedDas, $validList);
		if (!$validList) {
			return 'La liste des DAS autorisés doit contenir uniquement des identifiants positifs séparés par des virgules.';
		}
		if (empty($ids)) {
			return '';
		}

		$sql = 'SELECT rowid, status FROM '.$db->prefix().'sof_das';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND rowid IN ('.implode(',', array_map('intval', $ids)).')';
		$resql = $db->query($sql);
		$found = array();
		while ($resql && ($das = $db->fetch_object($resql))) {
			if (!$requireActive || (int) $das->status === 1) {
				$found[(int) $das->rowid] = (int) $das->rowid;
			}
		}
		if (count($found) !== count($ids)) {
			return $requireActive
				? 'La liste contient un DAS introuvable, inactif ou rattaché à une autre entité.'
				: 'La liste contient un DAS introuvable ou rattaché à une autre entité.';
		}

		$fkAgence = (int) $fkAgence;
		if ($fkAgence > 0) {
			$error = self::validateAgencyCashDeskDas($db, $fkAgence, 0, 0, $requireActive);
			if ($error !== '') {
				return $error;
			}
			$sql = 'SELECT allowed_das FROM '.$db->prefix().'sof_agence';
			$sql .= ' WHERE entity = '.((int) $conf->entity).' AND rowid = '.$fkAgence.' LIMIT 1';
			$resql = $db->query($sql);
			$agency = $resql ? $db->fetch_object($resql) : null;
			$validAgencyList = true;
			$agencyAllowedDas = $agency ? self::parseIdList($agency->allowed_das, $validAgencyList) : array();
			if (!$validAgencyList) {
				return 'La liste des DAS autorisés de l’agence est invalide.';
			}
			if (!empty($agencyAllowedDas) && array_diff($ids, $agencyAllowedDas)) {
				return 'Une caisse ne peut autoriser qu’un DAS autorisé par son agence.';
			}
		}

		return '';
	}

	/**
	 * Write an audit event without breaking the source transaction if audit table is not ready.
	 *
	 * @param DoliDB             $db       Database handler
	 * @param User               $user     Current user
	 * @param string             $action   Action code
	 * @param CommonObject|mixed $object   Source object
	 * @param mixed              $oldValue Previous value
	 * @param mixed              $newValue New value
	 * @param string             $reason   Reason
	 * @return int 1 if written, 0 if skipped, <0 if failed
	 */
	public static function logAudit(DoliDB $db, User $user, $action, $object, $oldValue = null, $newValue = null, $reason = '')
	{
		if (!isModEnabled('agence') || !getDolGlobalInt('AGENCE_ENABLE_AUDIT_TRAIL', 1)) {
			return 0;
		}

		$log = new SofAuditLog($db);
		$log->entity = isset($GLOBALS['conf']->entity) ? (int) $GLOBALS['conf']->entity : 1;
		$log->fk_user = (int) $user->id;
		$log->user_role = self::detectUserRole($user);
		$log->fk_agence = self::readObjectInt($object, 'fk_agence');
		$log->fk_caisse = self::readObjectInt($object, 'fk_caisse');
		$log->fk_session = self::readObjectInt($object, 'fk_session');
		$log->action_code = substr((string) $action, 0, 128);
		$log->object_type = self::detectObjectType($object);
		$log->object_id = self::detectObjectId($object);
		$log->event_date = $db->idate(dol_now());
		$log->ip_address = empty($_SERVER['REMOTE_ADDR']) ? '' : substr($_SERVER['REMOTE_ADDR'], 0, 64);
		$log->terminal = empty($_SERVER['HTTP_USER_AGENT']) ? php_sapi_name() : substr($_SERVER['HTTP_USER_AGENT'], 0, 128);
		$log->old_value = self::encodeValue($oldValue);
		$log->new_value = self::encodeValue($newValue);
		$log->reason = $reason;
		$log->status = 1;
		$log->date_creation = $db->idate(dol_now());

		$result = $log->create($user, 1);
		if ($result < 0) {
			dol_syslog('SofAgenceService::logAudit failed: '.implode(' | ', $log->errors), LOG_WARNING);
			return -1;
		}
		return 1;
	}

	/**
	 * Check whether a user has an agency scope for an operation.
	 *
	 * @param DoliDB $db            Database handler
	 * @param User   $user          User
	 * @param int    $fkAgence      Agency id
	 * @param string $operationType Operation type
	 * @param float  $amount        Amount
	 * @param int    $fkDas         DAS id
	 * @return bool
	 */
	public static function userCanAccessAgency(DoliDB $db, User $user, $fkAgence, $operationType = '', $amount = 0.0, $fkDas = 0)
	{
		if ($fkAgence <= 0 && $fkDas <= 0) {
			return true;
		}

		$nowSql = "'".$db->escape($db->idate(dol_now()))."'";
		$sql = 'SELECT rowid FROM '.$db->prefix().'sof_agence_user';
		$sql .= ' WHERE entity IN ('.getEntity('sof_agence_user').')';
		$sql .= ' AND fk_user = '.((int) $user->id);
		$sql .= ' AND status = 1';
		$sql .= ' AND (date_start IS NULL OR date_start <= '.$nowSql.')';
		$sql .= ' AND (date_end IS NULL OR date_end >= '.$nowSql.')';
		if ($fkAgence > 0) {
			$sql .= ' AND (fk_agence = '.((int) $fkAgence).' OR scope_type IN (\'all\', \'enterprise\'))';
		}
		if ($fkDas > 0) {
			$sql .= ' AND (fk_das IS NULL OR fk_das = '.((int) $fkDas).')';
		}
		if ($amount > 0) {
			$sql .= ' AND (validation_limit IS NULL OR validation_limit = 0 OR validation_limit >= '.price2num($amount).')';
		}
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql) > 0) {
			return true;
		}

		$sql = 'SELECT rowid FROM '.$db->prefix().'sof_role_transversal';
		$sql .= ' WHERE entity IN ('.getEntity('sof_role_transversal').')';
		$sql .= ' AND fk_user = '.((int) $user->id);
		$sql .= ' AND status = 1';
		$sql .= ' AND (date_start IS NULL OR date_start <= '.$nowSql.')';
		$sql .= ' AND (date_end IS NULL OR date_end >= '.$nowSql.')';
		if ($amount > 0) {
			$sql .= ' AND (financial_threshold IS NULL OR financial_threshold = 0 OR financial_threshold >= '.price2num($amount).')';
		}
		if ($operationType !== '') {
			$sql .= " AND (allowed_operation_types IS NULL OR allowed_operation_types = '' OR allowed_operation_types LIKE '%".$db->escape($operationType)."%')";
		}
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		return ($resql && $db->num_rows($resql) > 0);
	}

	/**
	 * Return an open session for a cash desk.
	 *
	 * @param DoliDB $db       Database handler
	 * @param int    $fkCaisse Cash desk id
	 * @param int    $fkUser   Optional cashier id
	 * @return int Session id, 0 if none, <0 on error
	 */
	public static function getOpenSession(DoliDB $db, $fkCaisse, $fkUser = 0)
	{
		if ($fkCaisse <= 0) {
			return 0;
		}

		$sql = 'SELECT rowid FROM '.$db->prefix().'sof_caisse_session';
		$sql .= ' WHERE entity IN ('.getEntity('sof_caisse_session').')';
		$sql .= ' AND fk_caisse = '.((int) $fkCaisse);
		$sql .= ' AND status IN (1,2,3,4,5)';
		if ($fkUser > 0) {
			$sql .= ' AND fk_user_cashier = '.((int) $fkUser);
		}
		$sql .= ' ORDER BY date_opening DESC';
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		if (!$resql) {
			return -1;
		}
		$obj = $db->fetch_object($resql);
		return $obj ? (int) $obj->rowid : 0;
	}

	/** Return the agency owning a workflow validation target. */
	public static function validationAgencyId(DoliDB $db, $objectType, $objectId)
	{
		global $conf;

		$map = array(
			'session' => 'sof_caisse_session',
			'refund' => 'sof_remboursement',
			'customer_po' => 'sof_bon_commande_client',
			'bst' => 'sof_bst',
			'manager_instruction' => 'sof_instruction_manageriale',
		);
		$objectType = (string) $objectType;
		if (empty($map[$objectType]) || (int) $objectId <= 0) {
			return 0;
		}
		$sql = 'SELECT fk_agence FROM '.$db->prefix().$map[$objectType];
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $objectId);
		$resql = $db->query($sql);
		$row = $resql ? $db->fetch_object($resql) : null;
		return $row ? (int) $row->fk_agence : 0;
	}

	/** Check whether a user may act on a workflow validation target. */
	public static function userCanAccessValidation(DoliDB $db, User $user, $objectType, $objectId)
	{
		$scopeIds = self::allowedAgencyIds($db, $user);
		if ($scopeIds === null) {
			return true;
		}
		$agencyId = self::validationAgencyId($db, $objectType, $objectId);
		return $agencyId > 0 && in_array($agencyId, $scopeIds, true);
	}

	/** Build a safe SQL filter for validation rows limited to agency ids. */
	public static function validationScopeSql(DoliDB $db, $alias, $scopeIds)
	{
		global $conf;

		if ($scopeIds === null) {
			return '';
		}
		if (empty($scopeIds)) {
			return ' AND 1 = 0';
		}
		$alias = preg_match('/^[a-z][a-z0-9_]*$/i', (string) $alias) ? (string) $alias : 'v';
		$ids = implode(',', array_map('intval', $scopeIds));
		$map = array(
			'session' => 'sof_caisse_session',
			'refund' => 'sof_remboursement',
			'customer_po' => 'sof_bon_commande_client',
			'bst' => 'sof_bst',
			'manager_instruction' => 'sof_instruction_manageriale',
		);
		$conditions = array();
		foreach ($map as $type => $table) {
			$conditions[] = '(' . $alias . ".object_type = '".$db->escape($type)."' AND EXISTS (SELECT 1 FROM ".$db->prefix().$table.' scope_target';
			$conditions[count($conditions) - 1] .= ' WHERE scope_target.entity = '.((int) $conf->entity).' AND scope_target.rowid = '.$alias.'.object_id AND scope_target.fk_agence IN ('.$ids.')))';
		}
		return ' AND ('.implode(' OR ', $conditions).')';
	}

	/**
	 * Find workflow rules matching an operation.
	 *
	 * @param DoliDB $db          Database handler
	 * @param string $objectType  Object type
	 * @param float  $amount      Amount
	 * @param int    $fkAgence    Agency id
	 * @param int    $fkDas       DAS id
	 * @param string $paymentMode Payment mode
	 * @return array<int,object>
	 */
	public static function findWorkflowRules(DoliDB $db, $objectType, $amount = 0.0, $fkAgence = 0, $fkDas = 0, $paymentMode = '')
	{
		$rules = array();
		$sql = 'SELECT * FROM '.$db->prefix().'sof_caisse_workflow';
		$sql .= ' WHERE entity IN ('.getEntity('sof_caisse_workflow').')';
		$sql .= " AND object_type = '".$db->escape($objectType)."'";
		$sql .= ' AND status = 1';
		$sql .= ' AND (min_amount IS NULL OR min_amount <= '.price2num($amount).')';
		$sql .= ' AND (max_amount IS NULL OR max_amount = 0 OR max_amount >= '.price2num($amount).')';
		if ($fkAgence > 0) {
			$sql .= " AND (agency_scope IS NULL OR agency_scope = '' OR agency_scope LIKE '%".$db->escape((string) $fkAgence)."%')";
		}
		if ($fkDas > 0) {
			$sql .= " AND (das_scope IS NULL OR das_scope = '' OR das_scope LIKE '%".$db->escape((string) $fkDas)."%')";
		}
		if ($paymentMode !== '') {
			$sql .= " AND (payment_mode_scope IS NULL OR payment_mode_scope = '' OR payment_mode_scope LIKE '%".$db->escape($paymentMode)."%')";
		}
		$sql .= ' ORDER BY min_amount DESC, rowid ASC';

		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$rules[] = $obj;
			}
		}
		return $rules;
	}

	/**
	 * Return an integer property from an object.
	 *
	 * @param mixed  $object Object
	 * @param string $field  Field
	 * @return int
	 */
	private static function readObjectInt($object, $field)
	{
		return (is_object($object) && isset($object->$field)) ? (int) $object->$field : 0;
	}

	/**
	 * Detect source object type.
	 *
	 * @param mixed $object Object
	 * @return string
	 */
	private static function detectObjectType($object)
	{
		if (is_object($object)) {
			if (!empty($object->element)) {
				return substr($object->element, 0, 128);
			}
			if (!empty($object->table_element)) {
				return substr($object->table_element, 0, 128);
			}
			return substr(get_class($object), 0, 128);
		}
		return 'unknown';
	}

	/**
	 * Detect source object id.
	 *
	 * @param mixed $object Object
	 * @return int
	 */
	private static function detectObjectId($object)
	{
		if (!is_object($object)) {
			return 0;
		}
		if (!empty($object->id)) {
			return (int) $object->id;
		}
		if (!empty($object->rowid)) {
			return (int) $object->rowid;
		}
		return 0;
	}

	/**
	 * Encode audit value.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private static function encodeValue($value)
	{
		if ($value === null) {
			return '';
		}
		if (is_scalar($value)) {
			return (string) $value;
		}
		$json = json_encode($value);
		return $json === false ? '' : $json;
	}

	/**
	 * Return a compact user role hint for audit logs.
	 *
	 * @param User $user User
	 * @return string
	 */
	private static function detectUserRole(User $user)
	{
		if (!empty($user->admin)) {
			return 'admin';
		}
		return '';
	}
}
