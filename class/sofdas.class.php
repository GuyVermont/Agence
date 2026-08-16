<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofdas.class.php
 * \ingroup    agence
 * \brief      DAS business object.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * SOFITOUL DAS.
 */
class SofDas extends SofCommonObject
{
	public $element = 'sof_das';
	public $table_element = 'sof_das';
	public $picto = 'tag';

	const STATUS_ACTIVE = 1;
	const STATUS_DISABLED = 0;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'description' => array('type' => 'text', 'label' => 'Description'),
		'accountancy_code' => array('type' => 'varchar(64)', 'label' => 'AccountancyCode'),
		'analytic_code' => array('type' => 'varchar(128)', 'label' => 'AnalyticCode'),
		'validation_rules' => array('type' => 'text', 'label' => 'ValidationRules'),
		'refund_rules' => array('type' => 'text', 'label' => 'RefundRules'),
		'credit_note_rules' => array('type' => 'text', 'label' => 'CreditNoteRules'),
		'allowed_payment_modes' => array('type' => 'text', 'label' => 'AllowedPaymentModes'),
		'required_documents' => array('type' => 'text', 'label' => 'RequiredDocuments'),
		'dashboard_config' => array('type' => 'text', 'label' => 'DashboardConfig'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'Disabled', 1 => 'Active')),
	);
}
