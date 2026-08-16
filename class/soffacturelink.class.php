<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Agency/session enrichment for a Dolibarr invoice.
 */
class SofFactureLink extends SofCommonObject
{
	public $element = 'sof_facture_link';
	public $table_element = 'sof_facture_link';
	public $picto = 'bill';

	public $sof_fields = array(
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'notnull' => 1, 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'source_type' => array('type' => 'varchar(64)', 'label' => 'SourceType'),
		'source_id' => array('type' => 'integer', 'label' => 'SourceId'),
		'billing_status' => array('type' => 'integer', 'label' => 'BillingStatus'),
		'deferred_status' => array('type' => 'integer', 'label' => 'DeferredStatus'),
		'accounting_status' => array('type' => 'integer', 'label' => 'AccountingStatus'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate'),
	);
}
