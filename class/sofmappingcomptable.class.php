<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Accounting mapping rule.
 */
class SofMappingComptable extends SofCommonObject
{
	public $element = 'sof_mapping_comptable';
	public $table_element = 'sof_mapping_comptable';
	public $picto = 'accountancy';

	public $sof_fields = array(
		'code' => array('type' => 'varchar(128)', 'label' => 'Code', 'notnull' => 1, 'index' => 1),
		'operation_type' => array('type' => 'varchar(128)', 'label' => 'OperationType', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS', 'index' => 1),
		'payment_mode' => array('type' => 'varchar(64)', 'label' => 'PaymentMode'),
		'journal_code' => array('type' => 'varchar(32)', 'label' => 'JournalCode'),
		'account_debit' => array('type' => 'varchar(64)', 'label' => 'DebitAccount'),
		'account_credit' => array('type' => 'varchar(64)', 'label' => 'CreditAccount'),
		'analytic_code' => array('type' => 'varchar(128)', 'label' => 'AnalyticCode'),
		'rule_expression' => array('type' => 'text', 'label' => 'RuleExpression'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
