<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Agency/DAS enrichment for a Dolibarr customer order.
 */
class SofCommandeLink extends SofCommonObject
{
	public $element = 'sof_commande_link';
	public $table_element = 'sof_commande_link';
	public $picto = 'order';

	public $sof_fields = array(
		'fk_commande' => array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order', 'notnull' => 1, 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession'),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'source_type' => array('type' => 'varchar(64)', 'label' => 'SourceType'),
		'source_id' => array('type' => 'integer', 'label' => 'SourceId'),
		'deferred_payment_status' => array('type' => 'integer', 'label' => 'DeferredPaymentStatus'),
		'invoice_status' => array('type' => 'integer', 'label' => 'InvoiceStatus'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate'),
	);
}
