<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Transversal role and scope.
 */
class SofRoleTransversal extends SofCommonObject
{
	public $element = 'sof_role_transversal';
	public $table_element = 'sof_role_transversal';
	public $picto = 'sitemap';

	public $sof_fields = array(
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'notnull' => 1, 'index' => 1),
		'role_code' => array('type' => 'varchar(64)', 'label' => 'Role', 'notnull' => 1, 'index' => 1),
		'scope_type' => array('type' => 'varchar(64)', 'label' => 'ScopeType', 'notnull' => 1),
		'scope_value' => array('type' => 'text', 'label' => 'ScopeValue'),
		'allowed_operation_types' => array('type' => 'text', 'label' => 'AllowedOperationTypes'),
		'financial_threshold' => array('type' => 'price', 'label' => 'FinancialThreshold', 'isameasure' => 1),
		'date_start' => array('type' => 'datetime', 'label' => 'DateStart'),
		'date_end' => array('type' => 'datetime', 'label' => 'DateEnd'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
