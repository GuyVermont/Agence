<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofcaisse.class.php
 * \ingroup    agence
 * \brief      Cash desk business object.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * SOFITOUL cash desk.
 */
class SofCaisse extends SofCommonObject
{
	public $element = 'sof_caisse';
	public $table_element = 'sof_caisse';
	public $picto = 'cash-register';

	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_SUSPENDED = 2;
	const STATUS_ARCHIVED = 9;

	public $sof_fields = array(
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'caisse_type' => array('type' => 'varchar(64)', 'label' => 'CashDeskType', 'notnull' => 1),
		'currency_code' => array('type' => 'varchar(10)', 'label' => 'Currency'),
		'fk_bank_account' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'CashBankAccount', 'index' => 1),
		'fk_bank_account_card' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'CardBankAccount', 'index' => 1),
		'fk_bank_account_cheque' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'ChequeBankAccount', 'index' => 1),
		'fk_bank_account_mobile' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'MobileMoneyBankAccount', 'index' => 1),
		'fk_bank_account_other' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'OtherBankAccount', 'index' => 1),
		'accountancy_code' => array('type' => 'varchar(64)', 'label' => 'AccountancyCode'),
		'analytic_code' => array('type' => 'varchar(128)', 'label' => 'AnalyticCode'),
		'fk_user_main_cashier' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'MainCashier', 'index' => 1),
		'fk_user_cash_chief' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'CashChief'),
		'fk_user_responsible' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Responsible'),
		'allowed_cashiers' => array('type' => 'text', 'label' => 'AllowedCashiers'),
		'allowed_das' => array('type' => 'text', 'label' => 'AllowedDAS'),
		'allowed_payment_modes' => array('type' => 'text', 'label' => 'AllowedPaymentModes'),
		'cashin_ceiling' => array('type' => 'price', 'label' => 'CashinCeiling', 'isameasure' => 1),
		'physical_balance_ceiling' => array('type' => 'price', 'label' => 'PhysicalBalanceCeiling', 'isameasure' => 1),
		'refund_ceiling' => array('type' => 'price', 'label' => 'RefundCeiling', 'isameasure' => 1),
		'allow_multi_cashiers' => array('type' => 'integer', 'label' => 'AllowMultiCashiers', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'allow_parallel_sessions' => array('type' => 'integer', 'label' => 'AllowParallelSessions', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'Draft', 1 => 'Active', 2 => 'Suspended', 9 => 'Archived')),
		'date_activation' => array('type' => 'datetime', 'label' => 'DateActivation'),
		'date_desactivation' => array('type' => 'datetime', 'label' => 'DateDesactivation'),
	);
}
