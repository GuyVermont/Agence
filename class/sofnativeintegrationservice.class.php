<?php
/* Copyright (C) 2026 iPowerWorld */

/**
 * Reuse and validate Dolibarr business objects instead of duplicating them in Agence.
 */
class SofNativeIntegrationService
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Return true when a field represents a native Dolibarr business object. */
	public static function isNativeField($field)
	{
		return in_array($field, array(
			'fk_facture', 'fk_facture_origin', 'fk_facture_avoir', 'fk_commande',
			'fk_paiement', 'fk_paiement_origin', 'fk_payment_various', 'fk_bank',
			'fk_bank_account', 'fk_bank_account_card', 'fk_bank_account_cheque',
			'fk_bank_account_mobile', 'fk_bank_account_other', 'fk_product',
			'fk_contact', 'fk_contact_signatory', 'fk_contact_beneficiary',
		), true);
	}

	/**
	 * Validate every selected native object, then copy canonical data into editable
	 * Agence records. This is intentionally called server-side: selectors are a UX
	 * improvement, not a security boundary.
	 *
	 * @param string          $objectKey Registry key
	 * @param SofCommonObject $object    Object being created/updated
	 * @param bool            $creating  Creation context
	 * @return int 1 on success, -1 on failure
	 */
	public function synchronize($objectKey, $object, $creating = false)
	{
		global $conf, $langs;

		$this->error = '';
		$this->errors = array();
		foreach ($object->fields as $field => $definition) {
			if (!self::isNativeField($field) || empty($object->$field)) {
				continue;
			}
			if (!$this->nativeExists($field, (int) $object->$field)) {
				return $this->fail($langs->trans('NativeObjectUnavailableForEntity'));
			}
		}

		if ($objectKey === 'avoir' && $this->synchronizeCreditNote($object, $creating) < 0) {
			return -1;
		}
		if ($objectKey === 'boncommande' && $this->synchronizeCustomerOrder($object, $creating) < 0) {
			return -1;
		}
		if (in_array($objectKey, array('bst', 'instruction'), true) && $this->synchronizeSupportingDocument($object) < 0) {
			return -1;
		}
		if ($objectKey === 'productdas' && $this->synchronizeProductMapping($object, $creating) < 0) {
			return -1;
		}
		if ($objectKey === 'tierscredit' && $creating && !empty($object->fk_soc)) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_tiers_credit_profile WHERE entity='.(int) $conf->entity.' AND fk_soc='.(int) $object->fk_soc.' LIMIT 1';
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				return $this->fail($langs->trans('CustomerCreditProfileAlreadyExists'));
			}
		}

		if ($this->validateThirdpartyConsistency($object) < 0) {
			return -1;
		}
		return 1;
	}

	/** Copy native credit-note facts and its original Agence context. */
	private function synchronizeCreditNote($object, $creating)
	{
		global $conf, $langs;
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$credit = new Facture($this->db);
		if (empty($object->fk_facture_avoir) || $credit->fetch((int) $object->fk_facture_avoir) <= 0
			|| (int) $credit->entity !== (int) $conf->entity || (int) $credit->type !== Facture::TYPE_CREDIT_NOTE) {
			return $this->fail($langs->trans('SelectedDocumentMustBeCreditNote'));
		}
		if ($creating) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_avoir_tracking WHERE entity='.(int) $conf->entity.' AND fk_facture_avoir='.(int) $credit->id.' LIMIT 1';
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				return $this->fail($langs->trans('CreditNoteAlreadyTracked'));
			}
		}

		$object->fk_soc = (int) $credit->socid;
		if (!empty($credit->fk_facture_source)) {
			$object->fk_facture_origin = (int) $credit->fk_facture_source;
		}
		$amount = abs((float) $credit->total_ttc);
		if ($creating || (float) $object->initial_amount <= 0) {
			$object->initial_amount = $amount;
			$object->used_amount = 0;
			$object->remaining_amount = $amount;
		}
		if ($creating || empty($object->ref)) {
			$object->ref = substr('AVO-'.$credit->ref, 0, 64);
		}
		if (empty($object->reason)) {
			$object->reason = !empty($credit->note_private) ? $credit->note_private : $credit->note_public;
		}
		if ($creating) {
			// Native document validation and authorization to consume the credit are
			// separate controls. The Agence workflow remains explicitly pending.
			$object->validation_status = 0;
			$object->date_validation = null;
			$object->fk_user_validator = null;
			$object->use_status = 0;
			$object->status = 0;
		}
		$context = $this->invoiceContext(!empty($object->fk_facture_origin) ? (int) $object->fk_facture_origin : (int) $credit->id);
		foreach (array('fk_agence', 'fk_session', 'fk_das') as $field) {
			if (empty($object->$field) && !empty($context[$field])) {
				$object->$field = (int) $context[$field];
			}
		}
		return 1;
	}

	/** Copy the native customer order reference, customer, date and amount. */
	private function synchronizeCustomerOrder($object, $creating)
	{
		global $conf, $langs;
		if (empty($object->fk_commande)) {
			return 1;
		}
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$order = new Commande($this->db);
		if ($order->fetch((int) $object->fk_commande) <= 0 || (int) $order->entity !== (int) $conf->entity) {
			return $this->fail($langs->trans('NativeObjectUnavailableForEntity'));
		}
		$object->fk_soc = (int) $order->socid;
		$object->order_number = (string) $order->ref;
		$object->order_date = !empty($order->date) ? $order->date : $order->date_commande;
		$object->authorized_amount = abs((float) $order->total_ttc);
		if ($creating) {
			$object->consumed_amount = 0;
			$object->remaining_amount = abs((float) $order->total_ttc);
			if (empty($object->ref)) {
				$object->ref = substr('BC-'.$order->ref, 0, 64);
			}
		}
		$context = $this->orderContext((int) $order->id);
		foreach (array('fk_agence', 'fk_das') as $field) {
			if (empty($object->$field) && !empty($context[$field])) {
				$object->$field = (int) $context[$field];
			}
		}
		return 1;
	}

	/** Infer customer, amount and context when a support document points to native documents. */
	private function synchronizeSupportingDocument($object)
	{
		global $conf, $langs;
		$thirdparty = !empty($object->fk_soc) ? (int) $object->fk_soc : (!empty($object->fk_soc_payer) ? (int) $object->fk_soc_payer : 0);
		if (!empty($object->fk_facture)) {
			require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
			$invoice = new Facture($this->db);
			if ($invoice->fetch((int) $object->fk_facture) <= 0 || (int) $invoice->entity !== (int) $conf->entity) {
				return $this->fail($langs->trans('NativeObjectUnavailableForEntity'));
			}
			$thirdparty = (int) $invoice->socid;
			if (isset($object->fields['final_amount']) && (float) $object->final_amount <= 0) {
				$object->final_amount = abs((float) $invoice->total_ttc);
			}
			$context = $this->invoiceContext((int) $invoice->id);
		} elseif (!empty($object->fk_commande)) {
			require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
			$order = new Commande($this->db);
			if ($order->fetch((int) $object->fk_commande) <= 0 || (int) $order->entity !== (int) $conf->entity) {
				return $this->fail($langs->trans('NativeObjectUnavailableForEntity'));
			}
			$thirdparty = (int) $order->socid;
			if (isset($object->fields['estimated_amount']) && (float) $object->estimated_amount <= 0) {
				$object->estimated_amount = abs((float) $order->total_ttc);
			}
			$context = $this->orderContext((int) $order->id);
		} else {
			$context = array();
		}
		if (isset($object->fields['fk_soc'])) {
			$object->fk_soc = $thirdparty ?: null;
		} elseif (isset($object->fields['fk_soc_payer'])) {
			$object->fk_soc_payer = $thirdparty ?: null;
		}
		foreach (array('fk_agence', 'fk_das') as $field) {
			if (isset($object->fields[$field]) && empty($object->$field) && !empty($context[$field])) {
				$object->$field = (int) $context[$field];
			}
		}
		return 1;
	}

	/** Reuse the product sales account and prevent duplicate mappings. */
	private function synchronizeProductMapping($object, $creating)
	{
		global $conf, $langs;
		if (empty($object->fk_product)) {
			return 1;
		}
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$product = new Product($this->db);
		if ($product->fetch((int) $object->fk_product) <= 0 || (int) $product->entity !== (int) $conf->entity) {
			return $this->fail($langs->trans('NativeObjectUnavailableForEntity'));
		}
		if (empty($object->accountancy_code) && !empty($product->accountancy_code_sell)) {
			$object->accountancy_code = $product->accountancy_code_sell;
		}
		if ($creating) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_product_das WHERE entity='.(int) $conf->entity;
			$sql .= ' AND fk_product='.(int) $object->fk_product.' AND fk_das='.(int) $object->fk_das;
			$sql .= ' AND '.(empty($object->fk_agence) ? 'fk_agence IS NULL' : 'fk_agence='.(int) $object->fk_agence).' LIMIT 1';
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				return $this->fail($langs->trans('ProductDASMappingAlreadyExists'));
			}
		}
		return 1;
	}

	/** Ensure invoice, order and contact selections all belong to the selected third party. */
	private function validateThirdpartyConsistency($object)
	{
		global $conf, $langs;
		$fkSoc = !empty($object->fk_soc) ? (int) $object->fk_soc : (!empty($object->fk_soc_payer) ? (int) $object->fk_soc_payer : 0);
		if ($fkSoc <= 0) {
			return 1;
		}
		foreach (array('fk_facture', 'fk_facture_origin', 'fk_facture_avoir') as $field) {
			if (!empty($object->$field)) {
				$sql = 'SELECT rowid FROM '.$this->db->prefix().'facture WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $object->$field.' AND fk_soc='.$fkSoc;
				$resql = $this->db->query($sql);
				if (!$resql || $this->db->num_rows($resql) === 0) {
					return $this->fail($langs->trans('NativeDocumentThirdPartyMismatch'));
				}
			}
		}
		if (!empty($object->fk_commande)) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'commande WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $object->fk_commande.' AND fk_soc='.$fkSoc;
			$resql = $this->db->query($sql);
			if (!$resql || $this->db->num_rows($resql) === 0) {
				return $this->fail($langs->trans('NativeDocumentThirdPartyMismatch'));
			}
		}
		foreach (array('fk_contact', 'fk_contact_signatory', 'fk_contact_beneficiary') as $field) {
			if (!empty($object->$field)) {
				$sql = 'SELECT rowid FROM '.$this->db->prefix().'socpeople WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $object->$field.' AND fk_soc='.$fkSoc;
				$resql = $this->db->query($sql);
				if (!$resql || $this->db->num_rows($resql) === 0) {
					return $this->fail($langs->trans('SelectedContactThirdPartyMismatch'));
				}
			}
		}
		return 1;
	}

	/** Check existence and entity ownership of a native record. */
	private function nativeExists($field, $id)
	{
		global $conf;
		if ($id <= 0) {
			return false;
		}
		if (in_array($field, array('fk_facture', 'fk_facture_origin', 'fk_facture_avoir'), true)) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'facture WHERE entity='.(int) $conf->entity.' AND rowid='.$id;
		} elseif ($field === 'fk_commande') {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'commande WHERE entity='.(int) $conf->entity.' AND rowid='.$id;
		} elseif ($field === 'fk_product') {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'product WHERE entity='.(int) $conf->entity.' AND rowid='.$id;
		} elseif (strpos($field, 'fk_bank_account') === 0) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'bank_account WHERE entity='.(int) $conf->entity.' AND rowid='.$id.' AND clos=0';
		} elseif ($field === 'fk_bank') {
			$sql = 'SELECT b.rowid FROM '.$this->db->prefix().'bank b INNER JOIN '.$this->db->prefix().'bank_account ba ON ba.rowid=b.fk_account WHERE ba.entity='.(int) $conf->entity.' AND b.rowid='.$id;
		} elseif (in_array($field, array('fk_contact', 'fk_contact_signatory', 'fk_contact_beneficiary'), true)) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'socpeople WHERE entity='.(int) $conf->entity.' AND rowid='.$id;
		} elseif (in_array($field, array('fk_paiement', 'fk_paiement_origin'), true)) {
			$sql = 'SELECT pf.fk_paiement FROM '.$this->db->prefix().'paiement_facture pf INNER JOIN '.$this->db->prefix().'facture f ON f.rowid=pf.fk_facture WHERE f.entity='.(int) $conf->entity.' AND pf.fk_paiement='.$id.' LIMIT 1';
		} elseif ($field === 'fk_payment_various') {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'payment_various WHERE entity='.(int) $conf->entity.' AND rowid='.$id;
		} else {
			return true;
		}
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}

	private function invoiceContext($invoiceId)
	{
		global $conf;
		$context = array('fk_agence'=>0, 'fk_caisse'=>0, 'fk_session'=>0, 'fk_das'=>0);
		$sql = 'SELECT fk_agence,fk_caisse,fk_session,fk_das FROM '.$this->db->prefix().'sof_facture_link WHERE entity='.(int) $conf->entity.' AND fk_facture='.(int) $invoiceId.' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		if ($row) {
			foreach ($context as $field => $unused) $context[$field] = (int) $row->$field;
		}
		return $context;
	}

	private function orderContext($orderId)
	{
		global $conf;
		$context = array('fk_agence'=>0, 'fk_das'=>0);
		$sql = 'SELECT fk_agence,fk_das FROM '.$this->db->prefix().'sof_commande_link WHERE entity='.(int) $conf->entity.' AND fk_commande='.(int) $orderId.' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		if ($row) {
			$context['fk_agence'] = (int) $row->fk_agence;
			$context['fk_das'] = (int) $row->fk_das;
		}
		return $context;
	}

	private function fail($message)
	{
		$this->error = (string) $message;
		$this->errors[] = $this->error;
		return -1;
	}
}
