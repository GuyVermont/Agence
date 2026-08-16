<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/** Immutable operational cash-ledger line. */
class SofCaisseMouvement extends SofCommonObject
{
	public $element = 'sof_caisse_mouvement';
	public $table_element = 'sof_caisse_mouvement';
	public $picto = 'money-bill-transfer';

	const STATUS_CANCELED = 0;
	const STATUS_VALIDATED = 1;
	const DIRECTION_CREDIT = 'credit';
	const DIRECTION_DEBIT = 'debit';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk', 'notnull' => 1, 'index' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'notnull' => 1, 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS', 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'index' => 1),
		'fk_facture' => array('type' => 'integer', 'label' => 'Invoice', 'index' => 1),
		'fk_paiement' => array('type' => 'integer', 'label' => 'Payment', 'index' => 1),
		'fk_payment_various' => array('type' => 'integer', 'label' => 'VariousPayment'),
		'fk_bank' => array('type' => 'integer', 'label' => 'BankLine'),
		'type_operation' => array('type' => 'varchar(64)', 'label' => 'OperationType', 'notnull' => 1, 'index' => 1),
		'direction' => array('type' => 'varchar(16)', 'label' => 'Direction', 'notnull' => 1, 'arrayofkeyval' => array('credit' => 'CashIn', 'debit' => 'CashOut')),
		'payment_mode' => array('type' => 'varchar(64)', 'label' => 'PaymentMode', 'index' => 1),
		'amount' => array('type' => 'price', 'label' => 'Amount', 'notnull' => 1, 'isameasure' => 1),
		'transaction_date' => array('type' => 'datetime', 'label' => 'TransactionDate', 'notnull' => 1, 'index' => 1),
		'source_type' => array('type' => 'varchar(64)', 'label' => 'SourceType'),
		'source_id' => array('type' => 'integer', 'label' => 'SourceId'),
		'transaction_ref' => array('type' => 'varchar(128)', 'label' => 'TransactionRef'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label'),
		'justification_ref' => array('type' => 'varchar(255)', 'label' => 'Justification'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'arrayofkeyval' => array(0 => 'Canceled', 1 => 'Validated')),
		'accounting_status' => array('type' => 'integer', 'label' => 'AccountingStatus', 'notnull' => 1, 'default' => 0),
		'accounting_attempts' => array('type' => 'integer', 'label' => 'AccountingAttempts', 'notnull' => 1, 'default' => 0),
		'accounting_error' => array('type' => 'text', 'label' => 'AccountingError'),
		'date_accounting_attempt' => array('type' => 'datetime', 'label' => 'AccountingAttemptDate'),
	);
}
