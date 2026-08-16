<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Special Transport Voucher. It is not a real payment.
 */
class SofBst extends SofCommonObject
{
	public $element = 'sof_bst';
	public $table_element = 'sof_bst';
	public $picto = 'trip';

	const STATUS_ISSUED = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CONSUMED = 2;
	const STATUS_INVOICED = 3;
	const STATUS_PAID = 4;
	const STATUS_CANCELED = 9;
	const STATUS_DISPUTED = 10;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'bst_number' => array('type' => 'varchar(128)', 'label' => 'BSTNumber', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'bst_date' => array('type' => 'date', 'label' => 'Date'),
		'issuer' => array('type' => 'varchar(255)', 'label' => 'Issuer'),
		'beneficiary' => array('type' => 'varchar(255)', 'label' => 'Beneficiary'),
		'fk_soc_payer' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'PayerThirdParty', 'notnull' => 1, 'index' => 1),
		'fk_contact_beneficiary' => array('type' => 'integer:Contact:societe/class/contact.class.php', 'label' => 'BeneficiaryContact'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'fk_user_agent' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Agent'),
		'fk_commande' => array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order'),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'index' => 1),
		'route_or_service' => array('type' => 'text', 'label' => 'RouteOrService'),
		'estimated_amount' => array('type' => 'price', 'label' => 'EstimatedAmount', 'isameasure' => 1),
		'final_amount' => array('type' => 'price', 'label' => 'FinalAmount', 'isameasure' => 1),
		'billing_conditions' => array('type' => 'text', 'label' => 'BillingConditions'),
		'attachment_ref' => array('type' => 'varchar(255)', 'label' => 'Attachment'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);
}
