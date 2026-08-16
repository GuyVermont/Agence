<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofcaissesession.class.php
 * \ingroup    agence
 * \brief      Cash session business object.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * SOFITOUL cash session.
 */
class SofCaisseSession extends SofCommonObject
{
	public $element = 'sof_caisse_session';
	public $table_element = 'sof_caisse_session';
	public $picto = 'clock';

	const STATUS_DRAFT = 0;
	const STATUS_OPEN = 1;
	const STATUS_OPERATING = 2;
	const STATUS_PAUSED = 3;
	const STATUS_CONTROL = 4;
	const STATUS_CLOSING = 5;
	const STATUS_CLOSED = 6;
	const STATUS_VALIDATED = 7;
	const STATUS_ACCOUNTED = 8;
	const STATUS_BLOCKED = 10;
	const STATUS_CANCELED = 9;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk', 'notnull' => 1, 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS', 'index' => 1),
		'fk_user_cashier' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Cashier', 'notnull' => 1, 'index' => 1),
		'session_type' => array('type' => 'varchar(64)', 'label' => 'SessionType', 'notnull' => 1),
		'date_opening' => array('type' => 'datetime', 'label' => 'OpeningDate', 'notnull' => 1),
		'date_closing' => array('type' => 'datetime', 'label' => 'ClosingDate'),
		'date_validation' => array('type' => 'datetime', 'label' => 'ValidationDate'),
		'opening_amount' => array('type' => 'price', 'label' => 'OpeningAmount', 'isameasure' => 1),
		'theoretical_amount' => array('type' => 'price', 'label' => 'TheoreticalAmount', 'isameasure' => 1),
		'physical_amount' => array('type' => 'price', 'label' => 'PhysicalAmount', 'isameasure' => 1),
		'gap_amount' => array('type' => 'price', 'label' => 'GapAmount', 'isameasure' => 1),
		'accounting_status' => array('type' => 'integer', 'label' => 'AccountingStatus', 'notnull' => 1, 'default' => 0, 'index' => 1),
		'accounting_attempts' => array('type' => 'integer', 'label' => 'AccountingAttempts', 'notnull' => 1, 'default' => 0),
		'accounting_error' => array('type' => 'text', 'label' => 'AccountingError'),
		'date_accounting' => array('type' => 'datetime', 'label' => 'AccountingDate'),
		'fk_user_accounting' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AccountingUser'),
		'freeze_status' => array('type' => 'integer', 'label' => 'FreezeStatus', 'notnull' => 1, 'default' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator'),
		'report_ref' => array('type' => 'varchar(255)', 'label' => 'ReportRef'),
		'reopening_reason' => array('type' => 'text', 'label' => 'ReopeningReason'),
		'note_public' => array('type' => 'text', 'label' => 'NotePublic'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate'),
	);
}
