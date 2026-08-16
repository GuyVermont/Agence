<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/core/triggers/interface_99_modAgence_AgenceTriggers.class.php
 * \ingroup    agence
 * \brief      Trigger file for Agence module.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

/**
 * Trigger class for Agence.
 */
class InterfaceAgenceTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'financial';
		$this->description = 'Agence module triggers for audit and integration with Dolibarr objects.';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'building';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string       $action Event action code
	 * @param CommonObject $object Object
	 * @param User         $user   User
	 * @param Translate    $langs  Langs
	 * @param Conf         $conf   Conf
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('agence')) {
			return 0;
		}

		// TakePOS is enforced server-side. The browser banner is only guidance: a
		// ticket cannot be validated if its terminal has no mapped open session.
		if ($action === 'BILL_VALIDATE' && $this->isTakeposInvoice($object)) {
			$result = $this->linkTakeposInvoice($object, $user);
			if ($result < 0 && getDolGlobalInt('AGENCE_REQUIRE_OPEN_SESSION', 1)) {
				$this->errors[] = $this->error;
				return -1;
			}
			$this->checkTakeposDiscount($object, $user);
		}
		if ($action === 'PAYMENT_CUSTOMER_CREATE' && empty($object->context['agence_capture'])) {
			if ($this->linkNativePayment($object, $user) < 0) {
				$this->errors[] = $this->error;
				return -1;
			}
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
			$engine = new SofAgenceOperations($this->db);
			if ($engine->synchronizeDeferredPayments($user) < 0) {
				$this->error = $engine->error ?: 'Échec de synchronisation des paiements différés.';
				$this->errors[] = $this->error;
				return -1;
			}
		}
		if ($action === 'BILL_CANCEL' || $action === 'BILL_DELETE') {
			$this->reverseTakeposInvoice($object, $user, $action);
		}

		$sensitiveActions = array(
			'BILL_CREATE',
			'BILL_VALIDATE',
			'BILL_PAYED',
			'BILL_CANCEL',
			'PAYMENT_CUSTOMER_CREATE',
			'BANKACCOUNT_CREATE',
			'BANKACCOUNT_MODIFY',
			'USER_MODIFY',
			'USER_NEW'
		);

		if (!in_array($action, $sensitiveActions, true)) {
			return 0;
		}

		SofAgenceService::logAudit($this->db, $user, $action, $object, null, array(
			'class' => is_object($object) ? get_class($object) : '',
			'element' => (is_object($object) && !empty($object->element)) ? $object->element : '',
			'id' => (is_object($object) && !empty($object->id)) ? (int) $object->id : 0,
		));

		dol_syslog("Agence trigger audited sensitive action ".$action." on object ".get_class($object), LOG_DEBUG);
		return 0;
	}

	private function isTakeposInvoice($object)
	{
		return is_object($object) && (!empty($object->id))
			&& ((!empty($object->module_source) && strtolower((string) $object->module_source) === 'takepos') || !empty($object->pos_source));
	}

	/** Link a validated POS invoice to its mapped open session. */
	private function linkTakeposInvoice($invoice, User $user)
	{
		$terminal = !empty($invoice->pos_source) ? (string) $invoice->pos_source : (!empty($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : '');
		if ($terminal === '') {
			$this->error = 'Terminal TakePOS indéterminé : validation bloquée.';
			return -1;
		}
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_takepos_link';
		$sql .= " WHERE entity = ".((int) $invoice->entity)." AND terminal_ref = '".$this->db->escape($terminal)."'";
		$sql .= ' AND fk_facture IS NULL AND status = 1 ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$mapping = $resql ? $this->db->fetch_object($resql) : null;
		if (!$mapping) {
			$this->error = 'Terminal TakePOS '.$terminal.' non rattaché à une caisse Agence.';
			return -1;
		}
		if (empty($user->admin) && !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $mapping->fk_agence, 'takepos_validate', !empty($invoice->total_ttc) ? (float) $invoice->total_ttc : 0, (int) $mapping->fk_das)) {
			$this->error = 'Le terminal TakePOS est hors du périmètre agence ou DAS de l’utilisateur.';
			return -1;
		}
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $invoice->entity);
		$sql .= ' AND fk_caisse = '.((int) $mapping->fk_caisse).' AND status IN (1,2) AND freeze_status = 0 ORDER BY date_opening DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$session = $resql ? $this->db->fetch_object($resql) : null;
		if (!$session) {
			$this->error = 'Aucune session Agence ouverte et non gelée pour le terminal TakePOS '.$terminal.'.';
			return -1;
		}
		if (empty($user->admin) && (int) $session->fk_user_cashier !== (int) $user->id && !$user->hasRight('agence', 'session', 'validate')) {
			$this->error = 'La session TakePOS ouverte n’appartient pas au caissier connecté.';
			return -1;
		}
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_takepos_link WHERE entity = '.((int) $invoice->entity).' AND fk_facture = '.((int) $invoice->id).' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) === 0) {
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/softakeposlink.class.php';
			$link = new SofTakeposLink($this->db);
			$link->entity = (int) $invoice->entity;
			$link->terminal_ref = $terminal;
			$link->fk_agence = (int) $mapping->fk_agence;
			$link->fk_caisse = (int) $mapping->fk_caisse;
			$link->fk_session = (int) $session->rowid;
			$link->fk_facture = (int) $invoice->id;
			$link->fk_das = (int) $mapping->fk_das ?: ((int) $session->fk_das ?: null);
			$link->place_ref = substr((string) GETPOST('place', 'alphanohtml'), 0, 64);
			$link->ticket_ref = !empty($invoice->ref) ? $invoice->ref : '';
			$link->pos_source = 'takepos';
			$link->billing_status = 1;
			$link->reconcile_status = 0;
			$link->status = 1;
			if ($link->create($user, 1) <= 0) {
				$this->error = $link->error ?: 'Échec du rattachement TakePOS.';
				return -1;
			}
		}
		if ($this->upsertTakeposInvoiceContext($invoice, $mapping, $session, $user) < 0) {
			return -1;
		}
		return 1;
	}

	/** Keep TakePOS invoices in the same canonical agency context as all other invoices. */
	private function upsertTakeposInvoiceContext($invoice, $mapping, $session, User $user)
	{
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_facture_link WHERE entity = '.((int) $invoice->entity);
		$sql .= ' AND fk_facture = '.((int) $invoice->id).' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$existing = $resql ? $this->db->fetch_object($resql) : null;
		if ($existing) {
			$sql = 'UPDATE '.$this->db->prefix().'sof_facture_link SET fk_soc = '.((int) $invoice->socid);
			$sql .= ', fk_agence = '.((int) $mapping->fk_agence).', fk_caisse = '.((int) $mapping->fk_caisse);
			$sql .= ', fk_session = '.((int) $session->rowid).', fk_das = '.((int) $mapping->fk_das ?: ((int) $session->fk_das ?: 'NULL'));
			$sql .= ", source_type = 'takepos', source_id = ".((int) $invoice->id).', billing_status = 1, tms = CURRENT_TIMESTAMP';
			$sql .= ', fk_user_modif = '.((int) $user->id).' WHERE rowid = '.((int) $existing->rowid);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			return 1;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/soffacturelink.class.php';
		$link = new SofFactureLink($this->db);
		$link->entity = (int) $invoice->entity;
		$link->fk_facture = (int) $invoice->id;
		$link->fk_soc = (int) $invoice->socid;
		$link->fk_agence = (int) $mapping->fk_agence;
		$link->fk_caisse = (int) $mapping->fk_caisse;
		$link->fk_session = (int) $session->rowid;
		$link->fk_das = (int) $mapping->fk_das ?: ((int) $session->fk_das ?: null);
		$link->source_type = 'takepos';
		$link->source_id = (int) $invoice->id;
		$link->billing_status = 1;
		$link->deferred_status = 0;
		$link->accounting_status = 0;
		if ($link->create($user, 1) <= 0) {
			$this->error = $link->error ?: 'Échec du rattachement de la facture TakePOS.';
			return -1;
		}
		return 1;
	}

	/** Link a real native customer payment to Agence context and ledger. */
	private function linkNativePayment($payment, User $user)
	{
		global $conf;
		if (!is_object($payment) || empty($payment->id)) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofpaiementlink.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
		$engine = new SofAgenceOperations($this->db);
		$sql = 'SELECT pf.fk_facture, pf.amount, COALESCE(fl.fk_agence,tl.fk_agence) fk_agence,';
		$sql .= ' COALESCE(fl.fk_caisse,tl.fk_caisse) fk_caisse, COALESCE(fl.fk_session,tl.fk_session) fk_session,';
		$sql .= ' COALESCE(fl.fk_das,tl.fk_das) fk_das, f.fk_soc,';
		$sql .= ' cp.code payment_mode FROM '.$this->db->prefix().'paiement_facture pf';
		$sql .= ' INNER JOIN '.$this->db->prefix().'facture f ON f.rowid = pf.fk_facture';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_facture_link fl ON fl.fk_facture = pf.fk_facture AND fl.entity = f.entity';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_takepos_link tl ON tl.fk_facture = pf.fk_facture AND tl.entity = f.entity AND tl.status = 1';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'paiement p ON p.rowid = pf.fk_paiement';
		$sql .= ' LEFT JOIN '.$this->db->prefix().'c_paiement cp ON cp.id = p.fk_paiement';
		$sql .= ' WHERE pf.fk_paiement = '.((int) $payment->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$linked = 0;
		while ($row = $this->db->fetch_object($resql)) {
			if (empty($row->fk_session)) {
				continue;
			}
			$sqlCheck = 'SELECT rowid FROM '.$this->db->prefix().'sof_paiement_link WHERE entity = '.((int) $conf->entity);
			$sqlCheck .= ' AND fk_paiement = '.((int) $payment->id).' AND fk_facture = '.((int) $row->fk_facture).' LIMIT 1';
			$rCheck = $this->db->query($sqlCheck);
			if ($rCheck && $this->db->num_rows($rCheck) > 0) {
				continue;
			}
			$link = new SofPaiementLink($this->db);
			$link->entity = (int) $conf->entity;
			$link->fk_paiement = (int) $payment->id;
			$link->fk_facture = (int) $row->fk_facture;
			$link->fk_soc = (int) $row->fk_soc;
			$link->fk_agence = (int) $row->fk_agence;
			$link->fk_caisse = (int) $row->fk_caisse;
			$link->fk_session = (int) $row->fk_session;
			$link->fk_das = (int) $row->fk_das ?: null;
			$link->payment_component_type = 'real';
			$link->payment_mode = $row->payment_mode;
			$link->amount = (float) $row->amount;
			$link->transaction_date = dol_now();
			$link->status = 1;
			if ($link->create($user, 1) <= 0) {
				$this->error = $link->error ?: 'Échec du rattachement du paiement natif à Agence.';
				return -1;
			}
			if ($engine->createMovement($user, array(
				'fk_agence' => (int) $row->fk_agence, 'fk_caisse' => (int) $row->fk_caisse,
				'fk_session' => (int) $row->fk_session, 'fk_das' => (int) $row->fk_das,
				'fk_soc' => (int) $row->fk_soc, 'fk_facture' => (int) $row->fk_facture,
				'fk_paiement' => (int) $payment->id, 'type_operation' => 'native_payment',
				'direction' => 'credit', 'payment_mode' => $row->payment_mode, 'amount' => (float) $row->amount,
				'source_type' => 'paiement', 'source_id' => (int) $payment->id,
				'label' => 'Paiement Dolibarr #'.$payment->id,
			)) <= 0) {
				$this->error = $engine->error ?: 'Échec de journalisation du paiement natif.';
				return -1;
			}
			$linked++;
		}
		return $linked;
	}

	/** Warn on excessive POS discount without silently blocking an already-approved sale. */
	private function checkTakeposDiscount($invoice, User $user)
	{
		$threshold = (float) getDolGlobalString('AGENCE_TAKEPOS_MAX_DISCOUNT_PCT', '10');
		$sql = 'SELECT MAX(remise_percent) max_discount FROM '.$this->db->prefix().'facturedet WHERE fk_facture = '.((int) $invoice->id);
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		if ($row && (float) $row->max_discount > $threshold) {
			$this->createAlert($user, 'takepos_discount', 'warning', 'facture', (int) $invoice->id,
				'Remise TakePOS de '.$row->max_discount.'% supérieure au seuil de '.$threshold.'%.');
		}
	}

	/** Create a reversal for an already-linked POS invoice cancellation. */
	private function reverseTakeposInvoice($invoice, User $user, $action)
	{
		if (!is_object($invoice) || empty($invoice->id)) {
			return;
		}
		$sqlLink = 'SELECT * FROM '.$this->db->prefix().'sof_takepos_link WHERE entity = '.((int) $invoice->entity);
		$sqlLink .= ' AND fk_facture = '.((int) $invoice->id).' ORDER BY rowid DESC LIMIT 1';
		$rLink = $this->db->query($sqlLink);
		$takeposLink = $rLink ? $this->db->fetch_object($rLink) : null;
		if (!$takeposLink) {
			return;
		}
		$this->db->query('UPDATE '.$this->db->prefix().'sof_takepos_link SET billing_status = 9, reconcile_status = 9,'
			.' status = 0, fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $takeposLink->rowid));
		$this->db->query('UPDATE '.$this->db->prefix().'sof_facture_link SET billing_status = 9,'
			.' fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE entity = '.((int) $invoice->entity).' AND fk_facture = '.((int) $invoice->id));
		$sql = 'SELECT m.* FROM '.$this->db->prefix().'sof_caisse_mouvement m';
		$sql .= ' WHERE m.fk_facture = '.((int) $invoice->id)." AND m.direction = 'credit' AND m.status = 1";
		$resql = $this->db->query($sql);
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
		$engine = new SofAgenceOperations($this->db);
		while ($resql && ($movement = $this->db->fetch_object($resql))) {
			$sqlCheck = 'SELECT rowid FROM '.$this->db->prefix()."sof_caisse_mouvement WHERE source_type = 'reversal' AND source_id = ".((int) $movement->rowid).' LIMIT 1';
			$rCheck = $this->db->query($sqlCheck);
			if ($rCheck && $this->db->num_rows($rCheck) > 0) {
				continue;
			}
			$sqlSession = 'SELECT status FROM '.$this->db->prefix().'sof_caisse_session WHERE rowid = '.((int) $movement->fk_session);
			$rSession = $this->db->query($sqlSession);
			$s = $rSession ? $this->db->fetch_object($rSession) : null;
			if ($s && in_array((int) $s->status, array(1,2), true)) {
				$engine->createMovement($user, array(
					'fk_agence' => (int) $movement->fk_agence, 'fk_caisse' => (int) $movement->fk_caisse,
					'fk_session' => (int) $movement->fk_session, 'fk_das' => (int) $movement->fk_das,
					'fk_soc' => (int) $movement->fk_soc, 'fk_facture' => (int) $invoice->id,
					'type_operation' => 'takepos_cancel', 'direction' => 'debit', 'payment_mode' => $movement->payment_mode,
					'amount' => (float) $movement->amount, 'source_type' => 'reversal', 'source_id' => (int) $movement->rowid,
					'label' => 'Annulation TakePOS '.$action,
				));
			} else {
				$this->createAlert($user, 'takepos_closed_session_cancel', 'critical', 'facture', (int) $invoice->id,
					'Annulation TakePOS après fermeture de la session : régularisation manuelle obligatoire.');
			}
		}
		$this->createAlert($user, 'takepos_cancellation', 'warning', 'facture', (int) $invoice->id,
			'Annulation du ticket TakePOS '.$action.' : lien désactivé et mouvements de caisse corrigés.');
	}

	private function createAlert(User $user, $type, $severity, $objectType, $objectId, $message)
	{
		global $conf;
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_caisse_alerte WHERE entity = '.((int) $conf->entity);
		$sql .= " AND alert_type = '".$this->db->escape($type)."' AND object_type = '".$this->db->escape($objectType)."'";
		$sql .= ' AND object_id = '.((int) $objectId).' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return;
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofalerte.class.php';
		$alert = new SofAlerte($this->db);
		$alert->entity = (int) $conf->entity;
		$alert->ref = 'ALT-'.date('Ymd-His').'-'.$objectId;
		$alert->alert_type = $type;
		$alert->severity = $severity;
		$alert->object_type = $objectType;
		$alert->object_id = $objectId;
		$alert->message = $message;
		$alert->target_roles = 'cash_chief,audit,direction';
		$alert->date_alert = dol_now();
		$alert->status = 0;
		$alert->create($user, 1);
	}
}
