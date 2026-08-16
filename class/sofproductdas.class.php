<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Product/service to DAS enrichment.
 */
class SofProductDas extends SofCommonObject
{
	public $element = 'sof_product_das';
	public $table_element = 'sof_product_das';
	public $picto = 'product';

	public $sof_fields = array(
		'fk_product' => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'ProductService', 'notnull' => 1, 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'payment_modes_allowed' => array('type' => 'text', 'label' => 'AllowedPaymentModes'),
		'deferred_payment_allowed' => array('type' => 'integer', 'label' => 'DeferredPaymentAllowed', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'refund_rules' => array('type' => 'text', 'label' => 'RefundRules'),
		'credit_note_rules' => array('type' => 'text', 'label' => 'CreditNoteRules'),
		'accountancy_code' => array('type' => 'varchar(64)', 'label' => 'AccountancyCode'),
		'analytic_code' => array('type' => 'varchar(128)', 'label' => 'AnalyticCode'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
