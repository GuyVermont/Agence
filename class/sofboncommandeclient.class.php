<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Customer purchase order support. It is not a real payment.
 */
class SofBonCommandeClient extends SofCommonObject
{
	public $element = 'sof_bon_commande_client';
	public $table_element = 'sof_bon_commande_client';
	public $picto = 'order';

	const STATUS_RECEIVED = 0;
	const STATUS_CHECKED = 1;
	const STATUS_USED = 2;
	const STATUS_PARTIALLY_USED = 3;
	const STATUS_EXPIRED = 4;
	const STATUS_REJECTED = 5;
	const STATUS_INVOICED = 6;
	const STATUS_PAID = 7;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'notnull' => 1, 'index' => 1),
		'fk_contact_signatory' => array('type' => 'integer:Contact:societe/class/contact.class.php', 'label' => 'Signatory'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'fk_commande' => array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order'),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'index' => 1),
		'order_number' => array('type' => 'varchar(128)', 'label' => 'PurchaseOrderNumber', 'notnull' => 1, 'searchall' => 1),
		'order_date' => array('type' => 'date', 'label' => 'Date'),
		'authorized_amount' => array('type' => 'price', 'label' => 'AuthorizedAmount', 'notnull' => 1, 'isameasure' => 1),
		'consumed_amount' => array('type' => 'price', 'label' => 'ConsumedAmount', 'isameasure' => 1, 'noteditable' => 1),
		'remaining_amount' => array('type' => 'price', 'label' => 'RemainingAmount', 'isameasure' => 1, 'noteditable' => 1),
		'object_label' => array('type' => 'varchar(255)', 'label' => 'Object'),
		'service_description' => array('type' => 'text', 'label' => 'ServiceDescription'),
		'validity_start' => array('type' => 'date', 'label' => 'ValidityStart'),
		'validity_end' => array('type' => 'date', 'label' => 'ValidityEnd'),
		'payment_due_date' => array('type' => 'date', 'label' => 'PaymentDueDate'),
		'attachment_ref' => array('type' => 'varchar(255)', 'label' => 'Attachment'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);
}
