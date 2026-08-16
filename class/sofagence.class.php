<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofagence.class.php
 * \ingroup    agence
 * \brief      Agency business object.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * SOFITOUL agency.
 */
class SofAgence extends SofCommonObject
{
	public $element = 'sof_agence';
	public $table_element = 'sof_agence';
	public $picto = 'building';

	const STATUS_ACTIVE = 1;
	const STATUS_SUSPENDED = 2;
	const STATUS_CLOSED = 3;
	const STATUS_TEST = 4;
	const STATUS_ARCHIVED = 9;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'town' => array('type' => 'varchar(128)', 'label' => 'Town'),
		'country_code' => array('type' => 'varchar(10)', 'label' => 'Country'),
		'address' => array('type' => 'text', 'label' => 'Address'),
		'phone' => array('type' => 'phone', 'label' => 'Phone'),
		'email' => array('type' => 'email', 'label' => 'Email'),
		'fk_user_responsible' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AgencyManager', 'index' => 1),
		'fk_user_deputy' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'DeputyManager'),
		'fk_user_cash_chief' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'CashChief', 'index' => 1),
		'fk_user_accounting_referent' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AccountingReferent'),
		'fk_user_sales_referent' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'SalesReferent'),
		'opening_hours' => array('type' => 'text', 'label' => 'OpeningHours'),
		'allowed_das' => array('type' => 'text', 'label' => 'AllowedDAS'),
		'cash_ceiling' => array('type' => 'price', 'label' => 'CashCeiling', 'isameasure' => 1),
		'cashin_ceiling' => array('type' => 'price', 'label' => 'CashinCeiling', 'isameasure' => 1),
		'refund_ceiling' => array('type' => 'price', 'label' => 'RefundCeiling', 'isameasure' => 1),
		'deferred_payment_ceiling' => array('type' => 'price', 'label' => 'DeferredPaymentCeiling', 'isameasure' => 1),
		'alert_threshold_amount' => array('type' => 'price', 'label' => 'AlertThreshold', 'isameasure' => 1),
		'validation_rules' => array('type' => 'text', 'label' => 'ValidationRules'),
		'closing_rules' => array('type' => 'text', 'label' => 'ClosingRules'),
		'accounting_center' => array('type' => 'varchar(128)', 'label' => 'AccountingCenter'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1, 'arrayofkeyval' => array(1 => 'Active', 2 => 'Suspended', 3 => 'Closed', 4 => 'Test', 9 => 'Archived')),
		'note_public' => array('type' => 'text', 'label' => 'NotePublic'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate'),
	);
}
