<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Credit profile linked to a Dolibarr thirdparty.
 */
class SofTiersCreditProfile extends SofCommonObject
{
	public $element = 'sof_tiers_credit_profile';
	public $table_element = 'sof_tiers_credit_profile';
	public $picto = 'company';

	public $sof_fields = array(
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'notnull' => 1, 'index' => 1),
		'fk_agence_followup' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'FollowupAgency', 'index' => 1),
		'deferred_payment_allowed' => array('type' => 'integer', 'label' => 'DeferredPaymentAllowed', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'credit_limit' => array('type' => 'price', 'label' => 'CreditLimit', 'isameasure' => 1),
		'payment_delay_days' => array('type' => 'integer', 'label' => 'PaymentDelayDays'),
		'risk_status' => array('type' => 'varchar(64)', 'label' => 'RiskStatus', 'index' => 1),
		'blocked_status' => array('type' => 'integer', 'label' => 'BlockedStatus', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'surveillance_status' => array('type' => 'integer', 'label' => 'SurveillanceStatus', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'fk_user_sales_responsible' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'SalesResponsible'),
		'incident_history' => array('type' => 'text', 'label' => 'IncidentHistory'),
		'validation_rules' => array('type' => 'text', 'label' => 'ValidationRules'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
