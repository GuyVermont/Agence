<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Denomination count for opening, closing or control.
 */
class SofCaisseComptage extends SofCommonObject
{
	public $element = 'sof_caisse_comptage';
	public $table_element = 'sof_caisse_comptage';
	public $picto = 'calculator';

	public $sof_fields = array(
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'notnull' => 1, 'index' => 1),
		'fk_cloture' => array('type' => 'integer:SofCaisseCloture:custom/agence/class/sofcaissecloture.class.php', 'label' => 'Closing'),
		'fk_controle' => array('type' => 'integer:SofCaisseControle:custom/agence/class/sofcaissecontrole.class.php', 'label' => 'Control'),
		'currency_code' => array('type' => 'varchar(10)', 'label' => 'Currency'),
		'denomination_value' => array('type' => 'price', 'label' => 'Denomination', 'notnull' => 1),
		'quantity' => array('type' => 'integer', 'label' => 'Qty', 'notnull' => 1),
		'amount' => array('type' => 'price', 'label' => 'Amount', 'notnull' => 1, 'isameasure' => 1),
		'comptage_type' => array('type' => 'varchar(64)', 'label' => 'CountType'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
	);
}
