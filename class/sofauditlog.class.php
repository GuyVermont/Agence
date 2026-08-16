<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/class/sofauditlog.class.php
 * \ingroup    agence
 * \brief      Audit log business object.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * SOFITOUL audit log.
 */
class SofAuditLog extends SofCommonObject
{
	public $element = 'sof_caisse_auditlog';
	public $table_element = 'sof_caisse_auditlog';
	public $picto = 'fingerprint';
	public $isextrafieldmanaged = 0;
	public $sof_use_standard_tracking = 0;

	public $sof_fields = array(
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'index' => 1),
		'user_role' => array('type' => 'varchar(128)', 'label' => 'Role'),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_caisse' => array('type' => 'integer:SofCaisse:custom/agence/class/sofcaisse.class.php', 'label' => 'CashDesk'),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession', 'index' => 1),
		'action_code' => array('type' => 'varchar(128)', 'label' => 'Action', 'notnull' => 1, 'index' => 1),
		'object_type' => array('type' => 'varchar(128)', 'label' => 'ObjectType', 'notnull' => 1, 'index' => 1),
		'object_id' => array('type' => 'integer', 'label' => 'ObjectId', 'index' => 1),
		'event_date' => array('type' => 'datetime', 'label' => 'EventDate', 'notnull' => 1, 'index' => 1),
		'ip_address' => array('type' => 'varchar(64)', 'label' => 'IPAddress'),
		'terminal' => array('type' => 'varchar(128)', 'label' => 'Terminal'),
		'old_value' => array('type' => 'text', 'label' => 'OldValue'),
		'new_value' => array('type' => 'text', 'label' => 'NewValue'),
		'reason' => array('type' => 'text', 'label' => 'Reason'),
		'attachment_ref' => array('type' => 'varchar(255)', 'label' => 'Attachment'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 1, 'index' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'notnull' => 1, 'visible' => -2),
	);
}
