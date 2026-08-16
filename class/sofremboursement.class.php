<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/** Refund request and execution tracking. */
class SofRemboursement extends SofCommonObject
{
	public $element = 'sof_remboursement';
	public $table_element = 'sof_remboursement';
	public $picto = 'hand-holding-dollar';

	const STATUS_REQUESTED = 0;
	const STATUS_PENDING = 1;
	const STATUS_APPROVED = 2;
	const STATUS_EXECUTED = 3;
	const STATUS_ACCOUNTED = 4;
	const STATUS_REJECTED = 8;
	const STATUS_CANCELED = 9;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession'),
		'fk_facture_origin' => array('type' => 'integer', 'label' => 'OriginInvoice', 'notnull' => 1, 'index' => 1),
		'fk_paiement_origin' => array('type' => 'integer', 'label' => 'OriginPayment'),
		'fk_mouvement_origin' => array('type' => 'integer:SofCaisseMouvement:custom/agence/class/sofcaissemouvement.class.php', 'label' => 'OriginMovement'),
		'fk_facture_avoir' => array('type' => 'integer', 'label' => 'CreditNote'),
		'fk_payment_various' => array('type' => 'integer', 'label' => 'VariousPayment'),
		'requested_amount' => array('type' => 'price', 'label' => 'RequestedAmount', 'notnull' => 1, 'isameasure' => 1),
		'approved_amount' => array('type' => 'price', 'label' => 'ApprovedAmount', 'isameasure' => 1),
		'refunded_amount' => array('type' => 'price', 'label' => 'RefundedAmount', 'isameasure' => 1),
		'payment_mode' => array('type' => 'varchar(64)', 'label' => 'PaymentMode'),
		'reason' => array('type' => 'text', 'label' => 'Reason', 'notnull' => 1),
		'request_date' => array('type' => 'datetime', 'label' => 'RequestDate', 'notnull' => 1),
		'validation_date' => array('type' => 'datetime', 'label' => 'ValidationDate'),
		'execution_date' => array('type' => 'datetime', 'label' => 'ExecutionDate'),
		'fk_user_requester' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Requester', 'notnull' => 1),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator'),
		'fk_user_executor' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Executor'),
		'rejection_reason' => array('type' => 'text', 'label' => 'RejectionReason'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'arrayofkeyval' => array(0 => 'Requested', 1 => 'PendingValidation', 2 => 'Approved', 3 => 'Executed', 4 => 'Accounted', 8 => 'Rejected', 9 => 'Canceled')),
		'accounting_status' => array('type' => 'integer', 'label' => 'AccountingStatus', 'notnull' => 1, 'default' => 0),
	);
}
