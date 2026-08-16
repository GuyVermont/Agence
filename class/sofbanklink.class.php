<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Agency/session enrichment for a Dolibarr bank line.
 */
class SofBankLink extends SofCommonObject
{
	public $element = 'sof_bank_link';
	public $table_element = 'sof_bank_link';
	public $picto = 'bank';

	public $sof_fields = array(
		'fk_bank' => array('type' => 'integer', 'label' => 'BankLine', 'notnull' => 1, 'index' => 1),
		'fk_bank_account' => array('type' => 'integer:Account:compta/bank/class/account.class.php', 'label' => 'BankAccount', 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'fk_depot_banque' => array('type' => 'integer', 'label' => 'BankDeposit', 'index' => 1),
		'operation_type' => array('type' => 'varchar(64)', 'label' => 'OperationType'),
		'reconcile_status' => array('type' => 'integer', 'label' => 'ReconcileStatus'),
		'accounting_status' => array('type' => 'integer', 'label' => 'AccountingStatus'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate'),
	);
}
