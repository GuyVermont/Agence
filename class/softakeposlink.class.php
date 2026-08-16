<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * TakePOS terminal/ticket enrichment.
 */
class SofTakeposLink extends SofCommonObject
{
	public $element = 'sof_takepos_link';
	public $table_element = 'sof_takepos_link';
	public $picto = 'cash-register';

	public $sof_fields = array(
		'terminal_ref' => array('type' => 'varchar(64)', 'label' => 'Terminal', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk', 'notnull' => 1, 'index' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'place_ref' => array('type' => 'varchar(64)', 'label' => 'Place'),
		'ticket_ref' => array('type' => 'varchar(128)', 'label' => 'Ticket'),
		'pos_source' => array('type' => 'varchar(64)', 'label' => 'POSSource'),
		'billing_status' => array('type' => 'integer', 'label' => 'BillingStatus'),
		'reconcile_status' => array('type' => 'integer', 'label' => 'ReconcileStatus'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
