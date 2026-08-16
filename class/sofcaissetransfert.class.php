<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Cash transfer between desk and vault.
 */
class SofCaisseTransfert extends SofCommonObject
{
	public $element = 'sof_caisse_transfert';
	public $table_element = 'sof_caisse_transfert';
	public $picto = 'transfer';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'notnull' => 1, 'index' => 1),
		'fk_caisse_source' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'SourceCashDesk', 'notnull' => 1, 'index' => 1),
		'fk_caisse_dest' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'DestinationCashDesk'),
		'fk_session_source' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'SourceSession', 'noteditable' => 1),
		'transfer_type' => array('type' => 'varchar(64)', 'label' => 'TransferType'),
		'amount' => array('type' => 'price', 'label' => 'Amount', 'notnull' => 1, 'isameasure' => 1),
		'currency_code' => array('type' => 'varchar(10)', 'label' => 'Currency'),
		'detail_coupures' => array('type' => 'text', 'label' => 'DenominationDetail'),
		'fk_user_sender' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Sender', 'noteditable' => 1),
		'fk_user_receiver' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Receiver', 'noteditable' => 1),
		'transfer_reason' => array('type' => 'text', 'label' => 'Reason'),
		'attachment_ref' => array('type' => 'varchar(255)', 'label' => 'Attachment'),
		'signature_ref' => array('type' => 'varchar(255)', 'label' => 'Signature'),
		'date_transfer' => array('type' => 'datetime', 'label' => 'TransferDate', 'notnull' => 1),
		'date_receive' => array('type' => 'datetime', 'label' => 'ReceiveDate', 'noteditable' => 1),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);
}
