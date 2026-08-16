<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Configurable workflow rule.
 */
class SofCaisseWorkflow extends SofCommonObject
{
	public $element = 'sof_caisse_workflow';
	public $table_element = 'sof_caisse_workflow';
	public $picto = 'workflow';

	public $sof_fields = array(
		'code' => array('type' => 'varchar(128)', 'label' => 'Code', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'notnull' => 1, 'searchall' => 1),
		'object_type' => array('type' => 'varchar(128)', 'label' => 'ObjectType', 'notnull' => 1, 'index' => 1),
		'agency_scope' => array('type' => 'varchar(255)', 'label' => 'AgencyScope'),
		'das_scope' => array('type' => 'varchar(255)', 'label' => 'DASScope'),
		'payment_mode_scope' => array('type' => 'varchar(255)', 'label' => 'PaymentModeScope'),
		'min_amount' => array('type' => 'price', 'label' => 'MinAmount', 'isameasure' => 1),
		'max_amount' => array('type' => 'price', 'label' => 'MaxAmount', 'isameasure' => 1),
		'risk_level' => array('type' => 'varchar(64)', 'label' => 'RiskLevel'),
		'rule_expression' => array('type' => 'text', 'label' => 'RuleExpression'),
		'validation_steps' => array('type' => 'text', 'label' => 'ValidationSteps'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
