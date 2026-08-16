<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Cash gap.
 */
class SofCaisseEcart extends SofCommonObject
{
	public $element = 'sof_caisse_ecart';
	public $table_element = 'sof_caisse_ecart';
	public $picto = 'warning';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'notnull' => 1, 'index' => 1),
		'fk_cloture' => array('type' => 'integer:SofCaisseCloture:custom/agence/class/sofcaissecloture.class.php', 'label' => 'Closing'),
		'fk_controle' => array('type' => 'integer:SofCaisseControle:custom/agence/class/sofcaissecontrole.class.php', 'label' => 'Control'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk', 'notnull' => 1, 'index' => 1),
		'gap_type' => array('type' => 'varchar(64)', 'label' => 'GapType', 'notnull' => 1),
		'theoretical_amount' => array('type' => 'price', 'label' => 'TheoreticalAmount', 'isameasure' => 1),
		'physical_amount' => array('type' => 'price', 'label' => 'PhysicalAmount', 'isameasure' => 1),
		'gap_amount' => array('type' => 'price', 'label' => 'GapAmount', 'isameasure' => 1),
		'severity' => array('type' => 'varchar(64)', 'label' => 'Severity'),
		'reason' => array('type' => 'text', 'label' => 'Reason'),
		'treatment_decision' => array('type' => 'text', 'label' => 'TreatmentDecision'),
		'date_treatment' => array('type' => 'datetime', 'label' => 'TreatmentDate'),
		'fk_user_cashier' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Cashier'),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1),
	);
}
