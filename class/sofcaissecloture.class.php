<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Cash session closing.
 */
class SofCaisseCloture extends SofCommonObject
{
	public $element = 'sof_caisse_cloture';
	public $table_element = 'sof_caisse_cloture';
	public $picto = 'lock';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk', 'notnull' => 1, 'index' => 1),
		'date_cloture' => array('type' => 'datetime', 'label' => 'ClosingDate', 'notnull' => 1),
		'total_cash' => array('type' => 'price', 'label' => 'TotalCash', 'isameasure' => 1),
		'total_card' => array('type' => 'price', 'label' => 'TotalCard', 'isameasure' => 1),
		'total_cheque' => array('type' => 'price', 'label' => 'TotalCheque', 'isameasure' => 1),
		'total_transfer' => array('type' => 'price', 'label' => 'TotalTransfer', 'isameasure' => 1),
		'total_mobile_money' => array('type' => 'price', 'label' => 'TotalMobileMoney', 'isameasure' => 1),
		'total_deferred' => array('type' => 'price', 'label' => 'TotalDeferred', 'isameasure' => 1),
		'total_refund' => array('type' => 'price', 'label' => 'TotalRefund', 'isameasure' => 1),
		'total_credit_note' => array('type' => 'price', 'label' => 'TotalCreditNote', 'isameasure' => 1),
		'theoretical_amount' => array('type' => 'price', 'label' => 'TheoreticalAmount', 'isameasure' => 1),
		'physical_amount' => array('type' => 'price', 'label' => 'PhysicalAmount', 'isameasure' => 1),
		'gap_amount' => array('type' => 'price', 'label' => 'GapAmount', 'isameasure' => 1),
		'cashier_comment' => array('type' => 'text', 'label' => 'CashierComment'),
		'validator_comment' => array('type' => 'text', 'label' => 'ValidatorComment'),
		'fk_user_cashier' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Cashier'),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1),
		'report_ref' => array('type' => 'varchar(255)', 'label' => 'ReportRef'),
	);
}
