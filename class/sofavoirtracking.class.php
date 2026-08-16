<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Business tracking for a Dolibarr credit note.
 */
class SofAvoirTracking extends SofCommonObject
{
	public $element = 'sof_avoir_tracking';
	public $table_element = 'sof_avoir_tracking';
	public $picto = 'credit-note';

	public $sof_fields = array(
		'ref' => array('type' => 'varchar(64)', 'label' => 'Ref', 'notnull' => 1, 'index' => 1, 'searchall' => 1),
		'fk_facture_avoir' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'CreditNote', 'notnull' => 1, 'index' => 1),
		'fk_facture_origin' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'OriginInvoice', 'index' => 1),
		'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'notnull' => 1, 'index' => 1),
		'fk_agence' => array('type' => 'integer:SofAgence:custom/agence/class/sofagence.class.php', 'label' => 'Agency', 'index' => 1),
		'fk_session' => array('type' => 'integer:SofCaisseSession:custom/agence/class/sofcaissesession.class.php', 'label' => 'CashSession'),
		'fk_das' => array('type' => 'integer:SofDas:custom/agence/class/sofdas.class.php', 'label' => 'DAS'),
		'initial_amount' => array('type' => 'price', 'label' => 'InitialAmount', 'notnull' => 1, 'isameasure' => 1),
		'used_amount' => array('type' => 'price', 'label' => 'UsedAmount', 'isameasure' => 1, 'noteditable' => 1),
		'remaining_amount' => array('type' => 'price', 'label' => 'RemainingAmount', 'isameasure' => 1, 'noteditable' => 1),
		'reason' => array('type' => 'text', 'label' => 'Reason'),
		'expiration_date' => array('type' => 'date', 'label' => 'ExpirationDate'),
		'validation_status' => array('type' => 'integer', 'label' => 'ValidationStatus', 'noteditable' => 1),
		'date_validation' => array('type' => 'datetime', 'label' => 'ValidationDate', 'noteditable' => 1),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Validator', 'noteditable' => 1),
		'use_status' => array('type' => 'integer', 'label' => 'UseStatus', 'noteditable' => 1),
		'date_last_use' => array('type' => 'datetime', 'label' => 'LastUseDate', 'noteditable' => 1),
		'fk_user_last_use' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'LastUseUser', 'noteditable' => 1),
		'blocked_reason' => array('type' => 'text', 'label' => 'BlockedReason'),
		'status' => array('type' => 'integer', 'label' => 'Status', 'notnull' => 1, 'default' => 0, 'index' => 1, 'noteditable' => 1),
	);

	/** Initialize computed balances for a manually registered native credit note. */
	public function create(User $user, $notrigger = 0)
	{
		if ($this->used_amount === null || $this->used_amount === '') {
			$this->used_amount = 0;
		}
		if ($this->remaining_amount === null || $this->remaining_amount === '') {
			$this->remaining_amount = max(0, (float) $this->initial_amount - (float) $this->used_amount);
		}
		if ($this->validation_status === null || $this->validation_status === '') {
			$this->validation_status = 0;
		}
		if ($this->use_status === null || $this->use_status === '') {
			$this->use_status = 0;
		}
		if ($this->status === null || $this->status === '') {
			$this->status = 0;
		}
		return parent::create($user, $notrigger);
	}
}
