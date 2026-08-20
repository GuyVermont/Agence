<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofcommonobject.class.php
 * \ingroup    agence
 * \brief      Shared base class for SOFITOUL business objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Shared base class for SOFITOUL objects.
 */
abstract class SofCommonObject extends CommonObject
{
	/**
	 * @var string Module name
	 */
	public $module = 'agence';

	/**
	 * @var int<0,1> Is extrafield managed
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var int<0,1> Is multientity managed
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var array<string,array<string,mixed>> Dolibarr field definitions
	 */
	public $fields = array();

	/**
	 * @var array<string,string|array<string,mixed>> SOFITOUL compact field definitions
	 */
	public $sof_fields = array();

	/**
	 * @var int<0,1> Add entity field
	 */
	public $sof_use_entity = 1;

	/**
	 * @var int<0,1> Add date/user tracking fields
	 */
	public $sof_use_standard_tracking = 1;

	/**
	 * @var int<0,1> Add import_key field
	 */
	public $sof_use_import_key = 1;

	/**
	 * @var array<string,mixed> Dynamic storage for SQL fields not declared as PHP properties.
	 */
	protected $sof_data = array();

	public $rowid;
	public $ref;
	public $label;
	public $entity;
	public $status;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;
		$this->fields = $this->buildFields();

		if (!getDolGlobalInt('MAIN_SHOW_TECHNICAL_ID') && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (!isModEnabled('multicompany') && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Magic getter for dynamic SQL fields.
	 *
	 * @param string $name Property name
	 * @return mixed
	 */
	public function __get($name)
	{
		return array_key_exists($name, $this->sof_data) ? $this->sof_data[$name] : null;
	}

	/**
	 * Magic setter for dynamic SQL fields.
	 *
	 * @param string $name  Property name
	 * @param mixed  $value Property value
	 * @return void
	 */
	public function __set($name, $value)
	{
		$this->sof_data[$name] = $value;
	}

	/**
	 * Magic isset for dynamic SQL fields.
	 *
	 * @param string $name Property name
	 * @return bool
	 */
	public function __isset($name)
	{
		return isset($this->sof_data[$name]);
	}

	/**
	 * Build CommonObject fields from compact SOFITOUL definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function buildFields()
	{
		$fields = array(
			'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		);

		if ($this->sof_use_entity) {
			$fields['entity'] = array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2, 'noteditable' => 1, 'default' => 1, 'index' => 1);
		}

		$position = 20;
		foreach ($this->sof_fields as $key => $definition) {
			$fields[$key] = $this->normalizeField($key, $definition, $position);
			$position += 10;
		}

		if ($this->sof_use_standard_tracking) {
			$fields['date_creation'] = array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2);
			$fields['tms'] = array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2);
			$fields['fk_user_creat'] = array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'index' => 1);
			$fields['fk_user_modif'] = array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'index' => 1);
		}

		if ($this->sof_use_import_key) {
			$fields['import_key'] = array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2);
		}

		return $fields;
	}

	/**
	 * Normalize a compact field definition.
	 *
	 * @param string                     $key        Field key
	 * @param string|array<string,mixed> $definition Compact definition
	 * @param int                        $position   Position
	 * @return array<string,mixed>
	 */
	protected function normalizeField($key, $definition, $position)
	{
		if (is_string($definition)) {
			$definition = array('type' => $definition);
		}

		$field = array(
			'type' => isset($definition['type']) ? $definition['type'] : 'varchar(255)',
			'label' => isset($definition['label']) ? $definition['label'] : $key,
			'enabled' => isset($definition['enabled']) ? $definition['enabled'] : 1,
			'position' => isset($definition['position']) ? $definition['position'] : $position,
			'notnull' => isset($definition['notnull']) ? $definition['notnull'] : 0,
			'visible' => isset($definition['visible']) ? $definition['visible'] : $this->defaultVisibility($key),
			'lang' => 'agence@agence',
			'validate' => 1,
		);

		foreach ($definition as $param => $value) {
			$field[$param] = $value;
		}

		if (!empty($field['index'])) {
			$field['index'] = 1;
		}

		return $field;
	}

	/**
	 * Default visibility for a field.
	 *
	 * @param string $key Field key
	 * @return int
	 */
	protected function defaultVisibility($key)
	{
		if (preg_match('/^(note_|validation_rules|closing_rules|rule_expression|validation_steps|incident_history|old_value|new_value|reason|message|target_roles|allowed_|required_documents|dashboard_config)/', $key)) {
			return 0;
		}
		if (preg_match('/^(date_creation|tms|fk_user_creat|fk_user_modif|import_key)$/', $key)) {
			return -2;
		}
		return 1;
	}

	/**
	 * Create object.
	 *
	 * @param User     $user      User
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;
		if (!empty($this->entity) && (int) $this->entity !== (int) $conf->entity) {
			$this->error = 'Création inter-entité interdite.';
			return -1;
		}
		$this->entity = (int) $conf->entity;
		if ($this->validateAndReuseNativeObjects(true) < 0) {
			return -1;
		}
		if ($this->validateAgencyCashDeskDasRelations() < 0) {
			return -1;
		}
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Fetch object.
	 *
	 * @param int         $id            Object id
	 * @param string|null $ref           Ref
	 * @param string      $morewhere     More where
	 * @param int<0,1>    $noextrafields Do not fetch extrafields
	 * @return int
	 */
	public function fetch($id, $ref = null, $morewhere = '', $noextrafields = 0)
	{
		global $conf;
		$morewhere .= ' AND t.entity = '.((int) $conf->entity);
		return $this->fetchCommon($id, $ref, $morewhere, $noextrafields);
	}

	/**
	 * Update object.
	 *
	 * @param User     $user      User
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		global $conf;
		if ((int) $this->entity !== (int) $conf->entity) {
			$this->error = 'Mise à jour inter-entité interdite.';
			return -1;
		}
		if ($this->validateAndReuseNativeObjects(false) < 0) {
			return -1;
		}
		if ($this->validateAgencyCashDeskDasRelations() < 0) {
			return -1;
		}
		return $this->updateCommon($user, $notrigger);
	}

	/** Validate native Dolibarr references for every write entry point, including API/imports. */
	protected function validateAndReuseNativeObjects($creating)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnativeintegrationservice.class.php';
		$keys = array(
			'sof_avoir_tracking'=>'avoir', 'sof_bon_commande_client'=>'boncommande',
			'sof_bst'=>'bst', 'sof_instruction_manageriale'=>'instruction',
			'sof_product_das'=>'productdas', 'sof_tiers_credit_profile'=>'tierscredit',
		);
		$service = new SofNativeIntegrationService($this->db);
		if ($service->synchronize(isset($keys[$this->table_element]) ? $keys[$this->table_element] : '', $this, (bool) $creating) < 0) {
			$this->error = $service->error;
			$this->errors = $service->errors;
			return -1;
		}
		return 1;
	}

	/**
	 * Enforce logical foreign keys shared by module objects.
	 *
	 * The database schema intentionally has few physical foreign keys to remain
	 * compatible with Dolibarr installation and migration tooling. This guard is
	 * therefore applied at the common object boundary for every create/update.
	 *
	 * @return int 1 if valid, -1 otherwise
	 */
	protected function validateAgencyCashDeskDasRelations()
	{
		$hasAgency = array_key_exists('fk_agence', $this->fields);
		$hasCashDesk = array_key_exists('fk_caisse', $this->fields);
		$hasDas = array_key_exists('fk_das', $this->fields);
		$hasAllowedDas = array_key_exists('allowed_das', $this->fields);
		if (!$hasAgency && !$hasCashDesk && !$hasDas && !$hasAllowedDas) {
			return 1;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		$fkAgence = $hasAgency ? (int) $this->fk_agence : 0;
		$fkCaisse = $hasCashDesk ? (int) $this->fk_caisse : 0;
		$fkDas = $hasDas ? (int) $this->fk_das : 0;
		$error = SofAgenceService::validateAgencyCashDeskDas($this->db, $fkAgence, $fkCaisse, $fkDas, true);
		if ($error === '' && $hasAllowedDas) {
			$parentAgency = $this->table_element === 'sof_caisse' ? $fkAgence : 0;
			$error = SofAgenceService::validateAllowedDasConfiguration($this->db, $parentAgency, $this->allowed_das, true);
		}
		if ($error !== '') {
			$this->error = $error;
			if (!is_array($this->errors)) {
				$this->errors = array();
			}
			$this->errors[] = $error;
			return -1;
		}

		return 1;
	}

	/**
	 * Delete object.
	 *
	 * @param User     $user      User
	 * @param int<0,1> $notrigger Disable triggers
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $conf;
		if ((int) $this->entity !== (int) $conf->entity) {
			$this->error = 'Suppression inter-entité interdite.';
			return -1;
		}
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Return object label.
	 *
	 * @param int    $withpicto Include picto
	 * @param string $option    Option
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		$label = !empty($this->ref) ? $this->ref : (!empty($this->label) ? $this->label : (string) $this->id);
		$result = dol_escape_htmltag($label);
		if ($withpicto) {
			$result = img_object('', $this->picto).' '.$result;
		}
		return $result;
	}
}
