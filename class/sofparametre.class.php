<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Scoped business parameter.
 */
class SofParametre extends SofCommonObject
{
	public $element = 'sof_parametre';
	public $table_element = 'sof_parametre';
	public $picto = 'setup';

	public $sof_fields = array(
		'code' => array('type' => 'varchar(128)', 'label' => 'Code', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'searchall' => 1),
		'value_text' => array('type' => 'text', 'label' => 'TextValue'),
		'value_number' => array('type' => 'price', 'label' => 'NumberValue'),
		'scope_type' => array('type' => 'varchar(64)', 'label' => 'ScopeType'),
		'scope_id' => array('type' => 'integer', 'label' => 'ScopeId'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
