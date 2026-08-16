<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Deferred payment / receivable follow-up.
 */
class SofPaiementDiffere extends SofCommonObject
{
	public $element = 'sof_paiement_differe';
	public $table_element = 'sof_paiement_differe';
	public $picto = 'file-invoice-dollar';

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_INVOICED = 2;
	const STATUS_PARTIALLY_PAID = 3;
	const STATUS_PAID = 4;
	const STATUS_LATE = 5;
	const STATUS_DISPUTED = 6;
	const STATUS_CLOSED = 7;
	const STATUS_CANCELED = 9;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'source_type' => array('type' => 'varchar(64)', 'label' => 'SourceType', 'notnull' => 1, 'index' => 1),
		'source_id' => array('type' => 'integer', 'label' => 'SourceId', 'index' => 1),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'index' => 1),
		'fk_commande' => array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order'),
		'operation_date' => array('type' => 'datetime', 'label' => 'OperationDate'),
		'service_description' => array('type' => 'text', 'label' => 'ServiceDescription'),
		'expected_amount' => array('type' => 'price', 'label' => 'ExpectedAmount', 'notnull' => 1, 'isameasure' => 1),
		'invoiced_amount' => array('type' => 'price', 'label' => 'InvoicedAmount', 'isameasure' => 1),
		'paid_amount' => array('type' => 'price', 'label' => 'PaidAmount', 'isameasure' => 1, 'noteditable' => 1),
		'remaining_amount' => array('type' => 'price', 'label' => 'RemainingAmount', 'isameasure' => 1, 'noteditable' => 1),
		'expected_payment_date' => array('type' => 'date', 'label' => 'ExpectedPaymentDate'),
		'last_reminder_date' => array('type' => 'date', 'label' => 'LastReminderDate'),
		'dispute_reason' => array('type' => 'text', 'label' => 'DisputeReason'),
		'closure_reason' => array('type' => 'text', 'label' => 'ClosureReason'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);
}
