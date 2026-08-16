<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * User agency assignment and local scope.
 */
class SofAgenceUser extends SofCommonObject
{
	public $element = 'sof_agence_user';
	public $table_element = 'sof_agence_user';
	public $picto = 'user';

	public $sof_fields = array(
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'role_code' => array('type' => 'varchar(64)', 'label' => 'Role', 'notnull' => 1, 'index' => 1),
		'scope_type' => array('type' => 'varchar(64)', 'label' => 'ScopeType'),
		'scope_value' => array('type' => 'varchar(255)', 'label' => 'ScopeValue'),
		'validation_limit' => array('type' => 'price', 'label' => 'ValidationLimit', 'isameasure' => 1),
		'is_default' => array('type' => 'integer', 'label' => 'Default', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'is_substitute' => array('type' => 'integer', 'label' => 'Substitute', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'date_start' => array('type' => 'datetime', 'label' => 'DateStart'),
		'date_end' => array('type' => 'datetime', 'label' => 'DateEnd'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
