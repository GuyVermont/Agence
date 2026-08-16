<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Surprise cash control.
 */
class SofCaisseControle extends SofCommonObject
{
	public $element = 'sof_caisse_controle';
	public $table_element = 'sof_caisse_controle';
	public $picto = 'shield';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk', 'index' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'fk_user_cashier' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Cashier'),
		'fk_user_controller' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Controller', 'notnull' => 1, 'index' => 1),
		'trigger_type' => array('type' => 'varchar(64)', 'label' => 'TriggerType', 'notnull' => 1),
		'date_start' => array('type' => 'datetime', 'label' => 'DateStart', 'notnull' => 1),
		'date_end' => array('type' => 'datetime', 'label' => 'DateEnd'),
		'freeze_enabled' => array('type' => 'integer', 'label' => 'FreezeEnabled', 'default' => 0, 'arrayofkeyval' => array(0 => 'No', 1 => 'Yes')),
		'theoretical_amount' => array('type' => 'price', 'label' => 'TheoreticalAmount', 'isameasure' => 1),
		'physical_amount' => array('type' => 'price', 'label' => 'PhysicalAmount', 'isameasure' => 1),
		'gap_amount' => array('type' => 'price', 'label' => 'GapAmount', 'isameasure' => 1),
		'observations' => array('type' => 'text', 'label' => 'Observations'),
		'cashier_signature_status' => array('type' => 'varchar(64)', 'label' => 'CashierSignatureStatus'),
		'controller_signature_ref' => array('type' => 'varchar(255)', 'label' => 'ControllerSignature'),
		'cashier_signature_ref' => array('type' => 'varchar(255)', 'label' => 'CashierSignature'),
		'report_ref' => array('type' => 'varchar(255)', 'label' => 'ReportRef'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1),
	);
}
