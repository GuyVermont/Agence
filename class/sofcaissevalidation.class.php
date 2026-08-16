<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Workflow validation event.
 */
class SofCaisseValidation extends SofCommonObject
{
	public $element = 'sof_caisse_validation';
	public $table_element = 'sof_caisse_validation';
	public $picto = 'check';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref'),
		'object_type' => array('type' => 'varchar(128)', 'label' => 'ObjectType', 'notnull' => 1, 'index' => 1),
		'object_id' => array('type' => 'integer', 'label' => 'ObjectId', 'notnull' => 1, 'index' => 1),
		'workflow_code' => array('type' => 'varchar(128)', 'label' => 'WorkflowCode', 'index' => 1),
		'validation_level' => array('type' => 'integer', 'label' => 'ValidationLevel', 'notnull' => 1, 'default' => 1),
		'validation_mode' => array('type' => 'varchar(64)', 'label' => 'ValidationMode'),
		'fk_user_requester' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Requester'),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator', 'index' => 1),
		'role_required' => array('type' => 'varchar(64)', 'label' => 'RequiredRole'),
		'decision' => array('type' => 'varchar(64)', 'label' => 'Decision'),
		'decision_reason' => array('type' => 'text', 'label' => 'DecisionReason'),
		'date_request' => array('type' => 'datetime', 'label' => 'RequestDate', 'notnull' => 1),
		'date_decision' => array('type' => 'datetime', 'label' => 'DecisionDate'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1),
	);
}
