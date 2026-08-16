<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Bank deposit follow-up.
 */
class SofCaisseDepotBanque extends SofCommonObject
{
	public $element = 'sof_caisse_depot_banque';
	public $table_element = 'sof_caisse_depot_banque';
	public $picto = 'bank';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse_source' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'SourceCashDesk', 'index' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'noteditable' => 1),
		'fk_bank_account' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'BankAccount', 'index' => 1),
		'fk_bank' => array('type' => 'integer', 'label' => 'BankLine', 'index' => 1, 'noteditable' => 1),
		'amount' => array('type' => 'price', 'label' => 'Amount', 'notnull' => 1, 'isameasure' => 1),
		'currency_code' => array('type' => 'varchar(10)', 'label' => 'Currency'),
		'date_preparation' => array('type' => 'datetime', 'label' => 'PreparationDate'),
		'date_deposit' => array('type' => 'datetime', 'label' => 'DepositDate', 'noteditable' => 1),
		'date_reconcile' => array('type' => 'datetime', 'label' => 'ReconcileDate', 'noteditable' => 1),
		'fk_user_depositor' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Depositor', 'noteditable' => 1),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator', 'noteditable' => 1),
		'bank_slip_number' => array('type' => 'varchar(128)', 'label' => 'BankSlipNumber'),
		'bank_slip_scan_ref' => array('type' => 'varchar(255)', 'label' => 'BankSlipScan'),
		'reconcile_reference' => array('type' => 'varchar(128)', 'label' => 'ReconcileReference', 'noteditable' => 1),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);
}
