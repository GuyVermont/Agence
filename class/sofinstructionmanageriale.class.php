<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Documented management instruction.
 */
class SofInstructionManageriale extends SofCommonObject
{
	public $element = 'sof_instruction_manageriale';
	public $table_element = 'sof_instruction_manageriale';
	public $picto = 'note';

	const STATUS_PENDING = 0;
	const STATUS_ACCEPTED = 1;
	const STATUS_EXECUTED = 2;
	const STATUS_INVOICED = 3;
	const STATUS_PAID = 4;
	const STATUS_REJECTED = 5;
	const STATUS_CANCELED = 9;

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'instruction_ref' => array('type' => 'varchar(128)', 'label' => 'InstructionRef', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_user_issuer' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Issuer'),
		'issuer_function' => array('type' => 'varchar(128)', 'label' => 'IssuerFunction'),
		'instruction_date' => array('type' => 'datetime', 'label' => 'InstructionDateTime'),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'index' => 1),
		'fk_contact' => array('type' => 'integer:Contact:societe/class/contact.class.php', 'label' => 'Contact'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'fk_commande' => array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order'),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'index' => 1),
		'service_description' => array('type' => 'text', 'label' => 'ServiceDescription'),
		'reason' => array('type' => 'text', 'label' => 'Reason'),
		'estimated_amount' => array('type' => 'price', 'label' => 'EstimatedAmount', 'isameasure' => 1),
		'final_amount' => array('type' => 'price', 'label' => 'FinalAmount', 'isameasure' => 1),
		'urgency_level' => array('type' => 'varchar(64)', 'label' => 'UrgencyLevel'),
		'attachment_ref' => array('type' => 'varchar(255)', 'label' => 'Attachment'),
		'fk_user_final_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'FinalValidator', 'noteditable' => 1),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);
}
