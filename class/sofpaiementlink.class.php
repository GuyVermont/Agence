<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Agency/session enrichment for a real Dolibarr payment.
 */
class SofPaiementLink extends SofCommonObject
{
	public $element = 'sof_paiement_link';
	public $table_element = 'sof_paiement_link';
	public $picto = 'payment';

	public $sof_fields = array(
		'fk_paiement' => array('type' => 'integer', 'label' => 'Payment', 'notnull' => 1, 'index' => 1),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'index' => 1),
		'fk_bank' => array('type' => 'integer', 'label' => 'BankLine', 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'payment_component_type' => array('type' => 'varchar(64)', 'label' => 'PaymentComponentType'),
		'payment_mode' => array('type' => 'varchar(64)', 'label' => 'PaymentMode'),
		'amount' => array('type' => 'price', 'label' => 'Amount', 'notnull' => 1, 'isameasure' => 1),
		'transaction_ref' => array('type' => 'varchar(128)', 'label' => 'TransactionRef'),
		'transaction_date' => array('type' => 'datetime', 'label' => 'TransactionDate'),
		'payer_name' => array('type' => 'varchar(255)', 'label' => 'Payer'),
		'operator_name' => array('type' => 'varchar(255)', 'label' => 'Operator'),
		'justification_ref' => array('type' => 'varchar(255)', 'label' => 'Justification'),
		'reconcile_status' => array('type' => 'integer', 'label' => 'ReconcileStatus'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
