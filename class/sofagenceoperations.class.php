<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * Transactional business engine for the Agence module.
 *
 * Dolibarr remains the source of truth for invoices, payments, credit notes and
 * bank entries.  This service enforces agency/session rules and writes the
 * immutable operational ledger used for closing and audit.
 */
class SofAgenceOperations
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/** Return active session status values. */
	public static function activeSessionStatuses()
	{
		return array(1, 2, 3, 4, 5);
	}

	/** Generate a collision-resistant, readable reference. */
	public function generateRef($prefix, $table)
	{
		$prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $prefix));
		try {
			$entropy = strtoupper(bin2hex(random_bytes(4)));
		} catch (Exception $e) {
			$entropy = strtoupper(sprintf('%08x', random_int(0, 0x7fffffff)));
		}
		return ($prefix ?: 'SOF').'-'.date('Ymd-His').'-'.$entropy;
	}

	/** Open a cash session after enforcing desk, scope and anti-duplication rules. */
	public function openSession(User $user, $fkCaisse, $openingAmount, $sessionType = 'daily', $fkDas = 0, $note = '')
	{
		global $conf;

		if (!$this->hasRight($user, 'session', 'open')) {
			return $this->fail('Permission refusée pour ouvrir une session.');
		}
		$openingAmount = price2num($openingAmount);
		if ($openingAmount < 0) {
			return $this->fail('Le fonds initial ne peut pas être négatif.');
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

		$caisse = new SofCaisse($this->db);
		if ($caisse->fetch((int) $fkCaisse) <= 0 || (int) $caisse->status !== SofCaisse::STATUS_ACTIVE) {
			return $this->fail('La caisse est introuvable ou inactive.');
		}
		$contextError = SofAgenceService::validateAgencyCashDeskDas($this->db, (int) $caisse->fk_agence, (int) $fkCaisse, (int) $fkDas, true);
		if ($contextError !== '') {
			return $this->fail($contextError);
		}
		if (empty($user->admin) && !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $caisse->fk_agence, 'session_open', $openingAmount, (int) $fkDas)) {
			return $this->fail("L'utilisateur n'est pas autorisé sur cette agence ou ce DAS.");
		}
		$allowedCashiers = $this->parseNumericList($caisse->allowed_cashiers);
		if (empty($user->admin) && !empty($allowedCashiers) && !in_array((int) $user->id, $allowedCashiers, true)) {
			return $this->fail("L'utilisateur n'est pas autorisé sur cette caisse.");
		}
		if ((float) $caisse->physical_balance_ceiling > 0 && $openingAmount > (float) $caisse->physical_balance_ceiling) {
			return $this->fail('Le fonds initial dépasse le plafond physique de la caisse.');
		}

		$this->db->begin();
		$lockSql = 'SELECT rowid FROM '.$this->db->prefix().'sof_caisse WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $fkCaisse).' FOR UPDATE';
		$userLockSql = 'SELECT rowid FROM '.$this->db->prefix().'user WHERE entity IN (0,'.((int) $conf->entity).') AND rowid = '.((int) $user->id).' FOR UPDATE';
		if (!$this->db->query($lockSql) || !$this->db->query($userLockSql)) {
			$this->db->rollback();
			return $this->fail('Impossible de verrouiller la caisse pour une ouverture concurrente.');
		}

		$active = implode(',', self::activeSessionStatuses());
		$sql = 'SELECT rowid, ref FROM '.$this->db->prefix().'sof_caisse_session';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_caisse = '.((int) $fkCaisse).' AND status IN ('.$active.') LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		if (empty($caisse->allow_parallel_sessions) && $resql && ($occupied = $this->db->fetch_object($resql))) {
			$this->db->rollback();
			return $this->fail('La caisse possède déjà une session active : '.$occupied->ref.'.');
		}
		$sql = 'SELECT rowid, ref FROM '.$this->db->prefix().'sof_caisse_session';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_user_cashier = '.((int) $user->id).' AND status IN ('.$active.') LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		if ($resql && ($occupied = $this->db->fetch_object($resql))) {
			$this->db->rollback();
			return $this->fail('Vous exploitez déjà la session '.$occupied->ref.'.');
		}

		$session = new SofCaisseSession($this->db);
		$session->entity = (int) $conf->entity;
		$session->ref = $this->generateRef('SES', 'sof_caisse_session');
		$session->fk_agence = (int) $caisse->fk_agence;
		$session->fk_caisse = (int) $caisse->id;
		$session->fk_das = $fkDas > 0 ? (int) $fkDas : null;
		$session->fk_user_cashier = (int) $user->id;
		$session->session_type = $sessionType ?: 'daily';
		$session->date_opening = dol_now();
		$session->opening_amount = $openingAmount;
		$session->theoretical_amount = $openingAmount;
		$session->physical_amount = 0;
		$session->gap_amount = 0;
		$session->accounting_status = 0;
		$session->freeze_status = 0;
		$session->status = SofCaisseSession::STATUS_OPEN;
		$session->note_private = trim((string) $note);

		$id = $session->create($user);
		if ($id <= 0) {
			$this->db->rollback();
			return $this->fail($session->error ?: 'Impossible de créer la session.', $session->errors);
		}
		if ($openingAmount > 0) {
			$movement = array(
				'fk_agence' => (int) $caisse->fk_agence,
				'fk_caisse' => (int) $caisse->id,
				'fk_session' => (int) $id,
				'fk_das' => $fkDas,
				'type_operation' => 'opening',
				'direction' => 'credit',
				'payment_mode' => 'LIQ',
				'amount' => $openingAmount,
				'label' => "Fonds initial d'ouverture",
				'source_type' => 'session',
				'source_id' => (int) $id,
			);
			if ($this->createMovement($user, $movement, false) <= 0) {
				$this->db->rollback();
				return -1;
			}
		}
		SofAgenceService::logAudit($this->db, $user, 'SOF_SESSION_OPEN', $session, null, array('opening_amount' => $openingAmount));
		$this->db->commit();
		return (int) $id;
	}

	/** Find the active session of a user, optionally restricted to a desk. */
	public function getOpenSessionForUser($fkUser, $fkCaisse = 0)
	{
		global $conf;

		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_session';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_user_cashier = '.((int) $fkUser);
		$sql .= ' AND status IN (1,2) AND freeze_status = 0';
		if ($fkCaisse > 0) {
			$sql .= ' AND fk_caisse = '.((int) $fkCaisse);
		}
		$sql .= ' ORDER BY date_opening DESC LIMIT 1';
		$resql = $this->db->query($sql);
		return $resql ? $this->db->fetch_object($resql) : null;
	}

	/** Create one immutable ledger line and refresh the session cash balance. */
	public function createMovement(User $user, array $data, $recalculate = true)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissemouvement.class.php';
		$amount = price2num(isset($data['amount']) ? $data['amount'] : 0);
		$direction = isset($data['direction']) ? strtolower($data['direction']) : '';
		if ($amount <= 0 || !in_array($direction, array('credit', 'debit'), true)) {
			return $this->fail('Montant ou sens du mouvement invalide.');
		}
		if (empty($data['fk_session']) || empty($data['fk_caisse']) || empty($data['fk_agence'])) {
			return $this->fail('Le mouvement doit être rattaché à une agence, une caisse et une session.');
		}
		if (!$this->ensureAgencyScope($user, (int) $data['fk_agence'], 'cash_movement', $amount, !empty($data['fk_das']) ? (int) $data['fk_das'] : 0)) {
			return -1;
		}
		$ownTransaction = empty($this->db->transaction_opened);
		if ($ownTransaction) {
			$this->db->begin();
		}
		$sql = 'SELECT fk_agence, fk_caisse, fk_das, status, freeze_status FROM '.$this->db->prefix().'sof_caisse_session';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $data['fk_session']).' FOR UPDATE';
		$resql = $this->db->query($sql);
		$session = $resql ? $this->db->fetch_object($resql) : null;
		if (!$session || (int) $session->fk_agence !== (int) $data['fk_agence'] || (int) $session->fk_caisse !== (int) $data['fk_caisse']
			|| ((!empty($data['fk_das']) || !empty($session->fk_das)) && (int) $session->fk_das !== (int) $data['fk_das'])) {
			if ($ownTransaction) {
				$this->db->rollback();
			}
			return $this->fail('Le mouvement ne correspond pas au contexte agence, caisse et DAS de la session.');
		}
		if (!in_array((int) $session->status, array(1, 2), true) || !empty($session->freeze_status)) {
			if ($ownTransaction) {
				$this->db->rollback();
			}
			return $this->fail('Aucun mouvement n’est autorisé sur une session gelée, en pause ou en clôture.');
		}

		$movement = new SofCaisseMouvement($this->db);
		$movement->entity = (int) $conf->entity;
		$movement->ref = $this->generateRef('MVT', 'sof_caisse_mouvement');
		foreach (array('fk_agence', 'fk_caisse', 'fk_session', 'fk_das', 'fk_soc', 'fk_facture', 'fk_paiement', 'fk_payment_various', 'fk_bank', 'source_id') as $field) {
			$movement->$field = empty($data[$field]) ? null : (int) $data[$field];
		}
		$movement->type_operation = substr((string) $data['type_operation'], 0, 64);
		$movement->direction = $direction;
		$movement->payment_mode = substr((string) (isset($data['payment_mode']) ? $data['payment_mode'] : ''), 0, 64);
		$movement->amount = $amount;
		$movement->transaction_date = !empty($data['transaction_date']) ? $data['transaction_date'] : dol_now();
		$movement->source_type = substr((string) (isset($data['source_type']) ? $data['source_type'] : ''), 0, 64);
		$movement->transaction_ref = substr((string) (isset($data['transaction_ref']) ? $data['transaction_ref'] : ''), 0, 128);
		$movement->label = substr((string) (isset($data['label']) ? $data['label'] : ''), 0, 255);
		$movement->justification_ref = substr((string) (isset($data['justification_ref']) ? $data['justification_ref'] : ''), 0, 255);
		$movement->status = SofCaisseMouvement::STATUS_VALIDATED;
		$movement->accounting_status = 0;
		$id = $movement->create($user);
		if ($id <= 0) {
			if ($ownTransaction) {
				$this->db->rollback();
			}
			return $this->fail($movement->error ?: 'Impossible de créer le mouvement.', $movement->errors);
		}
		if ($recalculate && $this->recalculateSession((int) $data['fk_session']) < 0) {
			if ($ownTransaction) {
				$this->db->rollback();
			}
			return -1;
		}
		if ($ownTransaction) {
			$this->db->commit();
		}
		return (int) $id;
	}

	/** Recalculate the physical-cash theoretical balance from the ledger. */
	public function recalculateSession($fkSession)
	{
		$cashModes = "('LIQ','CASH','ESP','ESPECES')";
		$sql = 'SELECT COALESCE(SUM(CASE WHEN direction = \'credit\' THEN amount ELSE -amount END),0) total';
		$sql .= ' FROM '.$this->db->prefix().'sof_caisse_mouvement';
		$sql .= ' WHERE fk_session = '.((int) $fkSession).' AND status = 1';
		$sql .= " AND (type_operation = 'opening' OR UPPER(COALESCE(payment_mode,'')) IN ".$cashModes.')';
		$resql = $this->db->query($sql);
		if (!$resql || !($obj = $this->db->fetch_object($resql))) {
			return $this->fail($this->db->lasterror());
		}
		$total = price2num($obj->total);
		$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_session SET theoretical_amount = '.$total.', tms = CURRENT_TIMESTAMP';
		$sql .= ' WHERE rowid = '.((int) $fkSession);
		if (!$this->db->query($sql)) {
			return $this->fail($this->db->lasterror());
		}
		return $total;
	}

	/** Return validated session totals grouped by payment mode and direction. */
	public function sessionTotals($fkSession)
	{
		$totals = array();
		$sql = 'SELECT UPPER(COALESCE(payment_mode,\'OTHER\')) payment_mode, direction, COALESCE(SUM(amount),0) total';
		$sql .= ' FROM '.$this->db->prefix().'sof_caisse_mouvement';
		$sql .= ' WHERE fk_session = '.((int) $fkSession).' AND status = 1 GROUP BY payment_mode, direction';
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$key = $obj->payment_mode;
				if (!isset($totals[$key])) {
					$totals[$key] = 0;
				}
				$totals[$key] += ($obj->direction === 'debit' ? -1 : 1) * (float) $obj->total;
			}
		}
		return $totals;
	}

	/** Replace a count batch for a session and return its physical total. */
	public function saveCashCount(User $user, $fkSession, array $denominations, $type = 'closing', $fkControl = 0)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissecomptage.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
		$type = in_array($type, array('opening', 'closing', 'control'), true) ? $type : 'closing';
		$requiredRight = $type === 'control' ? array('controle', 'create') : array('session', $type === 'opening' ? 'open' : 'close');
		if (!$this->hasRight($user, $requiredRight[0], $requiredRight[1])) {
			return $this->fail('Permission refusée pour enregistrer ce comptage.');
		}
		$session = new SofCaisseSession($this->db);
		if ($session->fetch((int) $fkSession) <= 0) {
			return $this->fail('Session de comptage introuvable.');
		}
		if (!$this->ensureAgencyScope($user, (int) $session->fk_agence, 'cash_count_'.$type, 0, (int) $session->fk_das)) {
			return -1;
		}
		$this->db->begin();
		$lockSql = 'SELECT rowid FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $fkSession).' FOR UPDATE';
		if (!$this->db->query($lockSql)) {
			$this->db->rollback();
			return $this->fail('Impossible de verrouiller la session de comptage.');
		}
		$sql = 'DELETE FROM '.$this->db->prefix().'sof_caisse_comptage WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_session = '.((int) $fkSession)." AND comptage_type = '".$this->db->escape($type)."'";
		if ($fkControl > 0) {
			$sql .= ' AND fk_controle = '.((int) $fkControl);
		}
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$total = 0.0;
		foreach ($denominations as $denomination => $quantity) {
			$denomination = price2num($denomination);
			$quantity = (int) $quantity;
			if ($denomination <= 0 || $quantity < 0 || $quantity === 0) {
				continue;
			}
			$count = new SofCaisseComptage($this->db);
			$count->entity = (int) $conf->entity;
			$count->fk_session = (int) $fkSession;
			$count->fk_controle = $fkControl > 0 ? (int) $fkControl : null;
			$count->currency_code = getDolGlobalString('MAIN_MONNAIE', 'XAF');
			$count->denomination_value = $denomination;
			$count->quantity = $quantity;
			$count->amount = $denomination * $quantity;
			$count->comptage_type = $type;
			$count->status = 1;
			if ($count->create($user) <= 0) {
				$this->db->rollback();
				return $this->fail($count->error ?: 'Impossible de sauvegarder le comptage.', $count->errors);
			}
			$total += $count->amount;
		}
		$this->db->commit();
		return $total;
	}

	/** Apply a controlled session state transition. */
	public function transitionSession(User $user, $fkSession, $action, array $params = array())
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

		$session = new SofCaisseSession($this->db);
		if ($session->fetch((int) $fkSession) <= 0) {
			return $this->fail('Session introuvable.');
		}
		if (empty($user->admin) && !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $session->fk_agence, 'session_'.$action, 0, (int) $session->fk_das)) {
			return $this->fail("Cette session est hors de votre périmètre.");
		}

		$rights = array(
			'operate' => array('session', 'open'), 'pause' => array('session', 'open'), 'resume' => array('session', 'open'),
			'start_close' => array('session', 'close'), 'close' => array('session', 'close'),
			'validate' => array('session', 'validate'), 'account' => array('compta', 'post'),
			'freeze' => array('controle', 'freeze'), 'unfreeze' => array('controle', 'freeze'),
			'block' => array('session', 'validate'), 'cancel' => array('session', 'validate'), 'reopen' => array('session', 'validate'),
		);
		if (isset($rights[$action]) && !$this->hasRight($user, $rights[$action][0], $rights[$action][1])) {
			return $this->fail('Permission insuffisante pour cette transition.');
		}
		if (in_array($action, array('operate', 'pause', 'resume', 'start_close', 'close'), true)
			&& empty($user->admin) && (int) $session->fk_user_cashier !== (int) $user->id
			&& !$this->hasRight($user, 'session', 'validate')) {
			return $this->fail('Seul le caissier de la session ou un superviseur peut effectuer cette action.');
		}

		$old = $this->snapshot($session);
		$status = (int) $session->status;
		$managedTransaction = false;
		$updates = array();
		if ($action === 'operate' && in_array($status, array(0, 1, 3, 4), true)) {
			$updates = array('status' => SofCaisseSession::STATUS_OPERATING, 'freeze_status' => 0);
		} elseif ($action === 'pause' && in_array($status, array(1, 2), true)) {
			$updates = array('status' => SofCaisseSession::STATUS_PAUSED);
		} elseif ($action === 'resume' && $status === SofCaisseSession::STATUS_PAUSED) {
			$updates = array('status' => SofCaisseSession::STATUS_OPERATING);
		} elseif ($action === 'freeze' && in_array($status, array(1, 2, 3), true)) {
			$updates = array('status' => SofCaisseSession::STATUS_CONTROL, 'freeze_status' => 1);
		} elseif ($action === 'unfreeze' && $status === SofCaisseSession::STATUS_CONTROL) {
			$updates = array('status' => SofCaisseSession::STATUS_OPERATING, 'freeze_status' => 0);
		} elseif ($action === 'start_close' && in_array($status, array(1, 2, 3, 4), true)) {
			$updates = array('status' => SofCaisseSession::STATUS_CLOSING, 'freeze_status' => 1);
		} elseif ($action === 'close' && $status === SofCaisseSession::STATUS_CLOSING) {
			return $this->finalizeClosure($user, $session, $params);
		} elseif ($action === 'validate' && $status === SofCaisseSession::STATUS_CLOSED) {
			if (empty($user->admin) && !getDolGlobalInt('AGENCE_ALLOW_SELF_APPROVAL') && (int) $session->fk_user_cashier === (int) $user->id) {
				return $this->fail('Le caissier ne peut pas valider sa propre clôture.');
			}
			$this->db->begin();
			$managedTransaction = true;
			$validation = $this->approveObject($user, 'session', (int) $session->id, 'approve', isset($params['reason']) ? $params['reason'] : '');
			if ($validation < 0) {
				$this->db->rollback();
				return -1;
			}
			$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse_validation WHERE entity = '.((int) $conf->entity);
			$sql .= " AND object_type = 'session' AND object_id = ".((int) $session->id).' AND status = 0';
			$resql = $this->db->query($sql);
			if ($resql && ($pendingRow = $this->db->fetch_object($resql)) && (int) $pendingRow->nb > 0) {
				$this->db->commit();
				$this->emitIntegrationEvent('validation.decided', 'session', (int) $session->id, (int) $session->fk_agence, array('ref' => $session->ref, 'decision' => 'approve', 'final' => false, 'subject' => 'Validation de clôture '.$session->ref), $user);
				return 1;
			}
			$updates = array('status' => SofCaisseSession::STATUS_VALIDATED, 'date_validation' => dol_now(), 'fk_user_validator' => (int) $user->id);
		} elseif ($action === 'account' && $status === SofCaisseSession::STATUS_VALIDATED) {
			$this->db->begin();
			$managedTransaction = true;
			$lockSql = 'SELECT status FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $session->id).' FOR UPDATE';
			$lockResult = $this->db->query($lockSql);
			$lockedSession = $lockResult ? $this->db->fetch_object($lockResult) : null;
			if (!$lockedSession || (int) $lockedSession->status !== SofCaisseSession::STATUS_VALIDATED) {
				$this->db->rollback();
				return $this->fail('La session a été modifiée avant le déversement comptable.');
			}
			if ($this->postSessionToAccounting($user, $session, false) < 0) {
				$accountingError = $this->error ?: 'Échec du déversement comptable.';
				$this->db->rollback();
				$this->markAccountingFailure($user, $session, $accountingError);
				return -1;
			}
			$updates = array(
				'status' => SofCaisseSession::STATUS_ACCOUNTED, 'accounting_status' => 4,
				'accounting_attempts' => (int) $session->accounting_attempts + 1, 'accounting_error' => null,
				'date_accounting' => dol_now(), 'fk_user_accounting' => (int) $user->id,
			);
		} elseif ($action === 'block' && $status < SofCaisseSession::STATUS_CLOSED) {
			$reason = trim((string) (isset($params['reason']) ? $params['reason'] : ''));
			if ($reason === '') {
				return $this->fail('Un motif de blocage est obligatoire.');
			}
			$updates = array('status' => SofCaisseSession::STATUS_BLOCKED, 'freeze_status' => 1, 'note_private' => trim($session->note_private."\n[BLOCAGE] ".$reason));
		} elseif ($action === 'cancel' && in_array($status, array(0, 1), true)) {
			$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse_mouvement WHERE fk_session = '.((int) $session->id)." AND type_operation <> 'opening' AND status = 1";
			$resql = $this->db->query($sql);
			$obj = $resql ? $this->db->fetch_object($resql) : null;
			if ($obj && (int) $obj->nb > 0) {
				return $this->fail('Une session contenant des opérations ne peut pas être annulée.');
			}
			$updates = array('status' => SofCaisseSession::STATUS_CANCELED, 'freeze_status' => 1, 'date_closing' => dol_now());
		} elseif ($action === 'reopen' && $status === SofCaisseSession::STATUS_CLOSED && (int) $session->accounting_status < 3) {
			$reason = trim((string) (isset($params['reason']) ? $params['reason'] : ''));
			if ($reason === '') {
				return $this->fail('Le motif de réouverture est obligatoire.');
			}
			$updates = array('status' => SofCaisseSession::STATUS_OPERATING, 'freeze_status' => 0, 'date_closing' => null, 'reopening_reason' => $reason);
		} else {
			return $this->fail('Transition interdite depuis le statut actuel.');
		}

		if ($this->updateRow('sof_caisse_session', (int) $session->id, $updates, $user, $status) < 0) {
			if ($managedTransaction) {
				$this->db->rollback();
			}
			return -1;
		}
		$session->fetch((int) $session->id);
		SofAgenceService::logAudit($this->db, $user, 'SOF_SESSION_'.strtoupper($action), $session, $old, $this->snapshot($session), isset($params['reason']) ? $params['reason'] : '');
		if ($managedTransaction) {
			$this->db->commit();
		}
		if ($action === 'validate') {
			$this->emitIntegrationEvent('validation.decided', 'session', (int) $session->id, (int) $session->fk_agence, array('ref' => $session->ref, 'decision' => 'approve', 'final' => true, 'subject' => 'Clôture validée '.$session->ref), $user);
		}
		return 1;
	}

	/** Finalize closing, persist totals and create a gap and validation chain. */
	private function finalizeClosure(User $user, SofCaisseSession $session, array $params)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissecloture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisseecart.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

		$physical = isset($params['physical_amount']) ? price2num($params['physical_amount']) : null;
		if ($physical === null || $physical < 0) {
			return $this->fail('Le montant physique de clôture est obligatoire.');
		}
		$this->db->begin();
		$lockSql = 'SELECT status FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $session->id).' FOR UPDATE';
		$lockResult = $this->db->query($lockSql);
		$lockedSession = $lockResult ? $this->db->fetch_object($lockResult) : null;
		if (!$lockedSession || (int) $lockedSession->status !== SofCaisseSession::STATUS_CLOSING) {
			$this->db->rollback();
			return $this->fail('La session a été modifiée par une autre opération.');
		}
		$theoretical = $this->recalculateSession((int) $session->id);
		if ($theoretical < 0) {
			$this->db->rollback();
			return -1;
		}
		$gap = price2num($physical - $theoretical);
		$totals = $this->sessionTotals((int) $session->id);

		$closing = new SofCaisseCloture($this->db);
		$closing->entity = (int) $conf->entity;
		$closing->ref = $this->generateRef('CLO', 'sof_caisse_cloture');
		$closing->fk_session = (int) $session->id;
		$closing->fk_agence = (int) $session->fk_agence;
		$closing->fk_caisse = (int) $session->fk_caisse;
		$closing->date_cloture = dol_now();
		$closing->total_cash = isset($totals['LIQ']) ? $totals['LIQ'] : 0;
		$closing->total_card = isset($totals['CB']) ? $totals['CB'] : 0;
		$closing->total_cheque = isset($totals['CHQ']) ? $totals['CHQ'] : 0;
		$closing->total_transfer = isset($totals['VIR']) ? $totals['VIR'] : 0;
		$closing->total_mobile_money = (isset($totals['OM']) ? $totals['OM'] : 0) + (isset($totals['MM']) ? $totals['MM'] : 0);
		$closing->total_deferred = isset($totals['DIFF']) ? $totals['DIFF'] : 0;
		$closing->total_refund = $this->movementTotal((int) $session->id, 'refund', 'debit');
		$closing->total_credit_note = 0;
		$closing->theoretical_amount = $theoretical;
		$closing->physical_amount = $physical;
		$closing->gap_amount = $gap;
		$closing->cashier_comment = trim((string) (isset($params['comment']) ? $params['comment'] : ''));
		$closing->fk_user_cashier = (int) $user->id;
		$closing->status = 1;
		$closingId = $closing->create($user);
		if ($closingId <= 0) {
			$this->db->rollback();
			return $this->fail($closing->error ?: 'Impossible de créer la clôture.', $closing->errors);
		}

		if (abs($gap) >= 0.01) {
			$gapObject = new SofCaisseEcart($this->db);
			$gapObject->entity = (int) $conf->entity;
			$gapObject->ref = $this->generateRef('ECA', 'sof_caisse_ecart');
			$gapObject->fk_session = (int) $session->id;
			$gapObject->fk_cloture = (int) $closingId;
			$gapObject->fk_agence = (int) $session->fk_agence;
			$gapObject->fk_caisse = (int) $session->fk_caisse;
			$gapObject->gap_type = $gap > 0 ? 'surplus' : 'shortage';
			$gapObject->theoretical_amount = $theoretical;
			$gapObject->physical_amount = $physical;
			$gapObject->gap_amount = $gap;
			$gapObject->severity = $this->gapSeverity(abs($gap));
			$gapObject->fk_user_cashier = (int) $session->fk_user_cashier;
			$gapObject->status = 0;
			if ($gapObject->create($user) <= 0) {
				$this->db->rollback();
				return $this->fail($gapObject->error ?: "Impossible d'enregistrer l'écart.", $gapObject->errors);
			}
		}

		$updates = array(
			'status' => SofCaisseSession::STATUS_CLOSED,
			'freeze_status' => 1,
			'date_closing' => dol_now(),
			'theoretical_amount' => $theoretical,
			'physical_amount' => $physical,
			'gap_amount' => $gap,
		);
		if ($this->updateRow('sof_caisse_session', (int) $session->id, $updates, $user, SofCaisseSession::STATUS_CLOSING) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->createValidationChain($user, 'session', (int) $session->id, abs($gap), (int) $session->fk_agence, (int) $session->fk_das, '');
		SofAgenceService::logAudit($this->db, $user, 'SOF_SESSION_CLOSE', $session, null, array('closing' => $closingId, 'gap' => $gap));
		$this->db->commit();
		$this->emitIntegrationEvent('cash_closure.completed', 'session', (int) $session->id, (int) $session->fk_agence, array(
			'ref' => $session->ref, 'closing_id' => (int) $closingId, 'fk_caisse' => (int) $session->fk_caisse,
			'theoretical_amount' => (float) $theoretical, 'physical_amount' => (float) $physical, 'gap_amount' => (float) $gap,
			'subject' => 'Clôture de caisse '.$session->ref,
		), $user);
		return 1;
	}

	/** Capture one invoice with one native Dolibarr payment per real component. */
	public function captureInvoicePayment(User $user, $fkSession, $fkFacture, array $components, array $deferred = array())
	{
		global $conf;

		if (!$this->hasRight($user, 'mouvement', 'cashin')) {
			return $this->fail("Permission refusée pour l'encaissement.");
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofpaiementlink.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/soffacturelink.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofpaiementdiffere.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';

		$session = new SofCaisseSession($this->db);
		if ($session->fetch((int) $fkSession) <= 0 || !in_array((int) $session->status, array(1, 2), true) || (int) $session->freeze_status !== 0) {
			return $this->fail('La session est introuvable, fermée ou gelée.');
		}
		if (empty($user->admin) && (int) $session->fk_user_cashier !== (int) $user->id && !$this->hasRight($user, 'session', 'validate')) {
			return $this->fail("Cette session n'appartient pas au caissier connecté.");
		}
		if (!$this->ensureAgencyScope($user, (int) $session->fk_agence, 'invoice_payment', 0, (int) $session->fk_das)) {
			return -1;
		}
		$caisse = new SofCaisse($this->db);
		if ($caisse->fetch((int) $session->fk_caisse) <= 0 || empty($caisse->fk_bank_account)) {
			return $this->fail('La caisse doit être reliée à un compte banque/caisse Dolibarr.');
		}
		$facture = new Facture($this->db);
		if ($facture->fetch((int) $fkFacture) <= 0 || (int) $facture->statut !== Facture::STATUS_VALIDATED || !in_array((int) $facture->type, array(Facture::TYPE_STANDARD, Facture::TYPE_DEPOSIT), true)) {
			return $this->fail('La facture doit être une facture client validée et encaissable.');
		}
		$existingContext = $this->invoiceAgencyContext((int) $facture->id);
		if (!empty($existingContext['fk_agence']) && (int) $existingContext['fk_agence'] !== (int) $session->fk_agence
			&& empty($user->admin) && !$user->hasRight('agence', 'dashboard', 'direction') && !$user->hasRight('agence', 'scope', 'write')) {
			return $this->fail('Cette facture est déjà rattachée à une autre agence.');
		}

		$cleanComponents = array();
		$totalReal = 0.0;
		foreach ($components as $mode => $amount) {
			$amount = price2num($amount);
			if ($amount <= 0) {
				continue;
			}
			$mode = strtoupper(substr((string) $mode, 0, 64));
			$cleanComponents[$mode] = $amount;
			$totalReal += $amount;
		}
		$deferredAmount = price2num(isset($deferred['amount']) ? $deferred['amount'] : 0);
		if ($deferredAmount > 0) {
			$sourceError = $this->validateDeferredSource(
				(string) (isset($deferred['source_type']) ? $deferred['source_type'] : 'other'),
				!empty($deferred['source_id']) ? (int) $deferred['source_id'] : 0,
				(int) $facture->socid,
				(int) $session->fk_agence,
				$deferredAmount
			);
			if ($sourceError !== '') {
				return $this->fail($sourceError);
			}
		}
		$total = $totalReal + $deferredAmount;
		if (count($cleanComponents) > 1 && !$this->hasRight($user, 'mouvement', 'mixedpayment')) {
			return $this->fail("Permission refusée pour l'encaissement mixte.");
		}
		if ($deferredAmount > 0 && !$this->hasRight($user, 'paiementdiffere', 'create')) {
			return $this->fail('Permission refusée pour le paiement différé.');
		}
		$allowedModes = $this->parseAllowedPaymentModes($caisse->allowed_payment_modes);
		$policyError = $this->validateInvoicePaymentPolicy((int) $facture->id, (int) $session->fk_agence, (int) $session->fk_das, array_keys($cleanComponents), $deferredAmount > 0);
		if ($policyError !== '') {
			return $this->fail($policyError);
		}
		$paymentConfiguration = array();
		foreach ($cleanComponents as $mode => $amount) {
			if (!empty($allowedModes) && !in_array($mode, $allowedModes, true)) {
				return $this->fail('Le mode de paiement '.$mode.' est interdit sur cette caisse.');
			}
			$paymentModeId = $this->paymentModeId($mode);
			if ($paymentModeId <= 0) {
				return $this->fail('Le mode de paiement Dolibarr '.$mode.' est absent ou inactif.');
			}
			$bankAccountId = $this->resolvePaymentBankAccount($caisse, $mode);
			if ($bankAccountId <= 0) {
				return -1;
			}
			$paymentConfiguration[$mode] = array('mode_id' => $paymentModeId, 'bank_account_id' => $bankAccountId);
		}
		$this->db->begin();
		$lockSql = 'SELECT rowid FROM '.$this->db->prefix().'facture WHERE entity IN ('.getEntity('invoice').') AND rowid = '.((int) $facture->id).' FOR UPDATE';
		if (!$this->db->query($lockSql)) {
			$this->db->rollback();
			return $this->fail('Impossible de verrouiller la facture pour encaissement.');
		}
		$remaining = $this->invoiceRemaining((int) $facture->id, (float) $facture->total_ttc);
		if ($total <= 0 || $total > $remaining + 0.01) {
			$this->db->rollback();
			return $this->fail('Le total ventilé est nul ou dépasse le reste à payer de '.price($remaining).'.');
		}
		if ((float) $caisse->cashin_ceiling > 0 && $totalReal > (float) $caisse->cashin_ceiling) {
			$this->db->rollback();
			return $this->fail("L'encaissement dépasse le plafond de la caisse.");
		}

		foreach ($cleanComponents as $mode => $amount) {
			$paymentModeId = (int) $paymentConfiguration[$mode]['mode_id'];
			$bankAccountId = (int) $paymentConfiguration[$mode]['bank_account_id'];
			$payment = new Paiement($this->db);
			$payment->context['agence_capture'] = true;
			$payment->datepaye = dol_now();
			$payment->amounts = array((int) $facture->id => $amount);
			$payment->paiementid = $paymentModeId;
			$payment->num_payment = substr((string) (isset($deferred['transaction_ref']) ? $deferred['transaction_ref'] : ''), 0, 128);
			$paymentId = $payment->create($user, 1);
			if ($paymentId <= 0) {
				$this->db->rollback();
				return $this->fail($payment->error ?: 'Échec de création du paiement Dolibarr.', $payment->errors);
			}
			$bankId = $payment->addPaymentToBank($user, 'payment', 'Encaissement Agence '.$session->ref, $bankAccountId, '', '');
			if ($bankId < 0) {
				$this->db->rollback();
				return $this->fail($payment->error ?: 'Échec de création de la ligne bancaire.', $payment->errors);
			}

			$link = new SofPaiementLink($this->db);
			$link->entity = (int) $conf->entity;
			$link->fk_paiement = (int) $paymentId;
			$link->fk_facture = (int) $facture->id;
			$link->fk_bank = $bankId > 0 ? (int) $bankId : null;
			$link->fk_soc = (int) $facture->socid;
			$link->fk_agence = (int) $session->fk_agence;
			$link->fk_caisse = (int) $session->fk_caisse;
			$link->fk_session = (int) $session->id;
			$link->fk_das = (int) $session->fk_das ?: null;
			$link->payment_component_type = 'real';
			$link->payment_mode = $mode;
			$link->amount = $amount;
			$link->transaction_ref = $payment->num_payment;
			$link->transaction_date = dol_now();
			$link->operator_name = $user->getFullName($GLOBALS['langs']);
			$link->reconcile_status = $bankId > 0 ? 1 : 0;
			$link->status = 1;
			if ($link->create($user) <= 0) {
				$this->db->rollback();
				return $this->fail($link->error ?: 'Échec du rattachement du paiement.', $link->errors);
			}

			$movementId = $this->createMovement($user, array(
				'fk_agence' => (int) $session->fk_agence, 'fk_caisse' => (int) $session->fk_caisse,
				'fk_session' => (int) $session->id, 'fk_das' => (int) $session->fk_das,
				'fk_soc' => (int) $facture->socid, 'fk_facture' => (int) $facture->id,
				'fk_paiement' => (int) $paymentId, 'fk_bank' => $bankId,
				'type_operation' => 'invoice_payment', 'direction' => 'credit', 'payment_mode' => $mode,
				'amount' => $amount, 'source_type' => 'facture', 'source_id' => (int) $facture->id,
				'transaction_ref' => $payment->num_payment, 'label' => 'Encaissement '.$facture->ref.' - '.$mode,
			), false);
			if ($movementId <= 0) {
				$this->db->rollback();
				return -1;
			}
		}

		if ($deferredAmount > 0) {
			$profileError = $this->validateDeferredCredit((int) $facture->socid, (int) $session->fk_agence, $deferredAmount);
			if ($profileError !== '') {
				$this->db->rollback();
				return $this->fail($profileError);
			}
			$deferredObject = new SofPaiementDiffere($this->db);
			$deferredObject->entity = (int) $conf->entity;
			$deferredObject->ref = $this->generateRef('DIF', 'sof_paiement_differe');
			$deferredObject->fk_soc = (int) $facture->socid;
			$deferredObject->fk_agence = (int) $session->fk_agence;
			$deferredObject->fk_caisse = (int) $session->fk_caisse;
			$deferredObject->fk_session = (int) $session->id;
			$deferredObject->fk_das = (int) $session->fk_das ?: null;
			$deferredObject->source_type = substr((string) (isset($deferred['source_type']) ? $deferred['source_type'] : 'other'), 0, 64);
			$deferredObject->source_id = !empty($deferred['source_id']) ? (int) $deferred['source_id'] : null;
			$deferredObject->fk_facture = (int) $facture->id;
			$deferredObject->operation_date = dol_now();
			$deferredObject->service_description = 'Part différée de '.$facture->ref;
			$deferredObject->expected_amount = $deferredAmount;
			$deferredObject->invoiced_amount = $deferredAmount;
			$deferredObject->paid_amount = 0;
			$deferredObject->remaining_amount = $deferredAmount;
			$deferredObject->expected_payment_date = !empty($deferred['due_date']) ? $deferred['due_date'] : dol_print_date(dol_time_plus_duree(dol_now(), 30, 'd'), '%Y-%m-%d');
			$deferredObject->status = SofPaiementDiffere::STATUS_VALIDATED;
			if ($deferredObject->create($user) <= 0) {
				$this->db->rollback();
				return $this->fail($deferredObject->error ?: 'Échec de création du paiement différé.', $deferredObject->errors);
			}
		}

		if ($this->upsertInvoiceLink($user, $facture, $session, $deferredAmount > 0 ? 1 : 0) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->recalculateSession((int) $session->id);
		$this->updateRow('sof_caisse_session', (int) $session->id, array('status' => 2), $user);
		$this->db->commit();
		$this->emitIntegrationEvent('payment.completed', 'facture', (int) $facture->id, (int) $session->fk_agence, array(
			'ref' => $facture->ref, 'fk_soc' => (int) $facture->socid, 'amount' => (float) $total,
			'deferred_amount' => (float) $deferredAmount, 'subject' => 'Encaissement facture '.$facture->ref,
		), $user);
		return 1;
	}

	/** Validate a selected deferred-payment support document against the invoice context. */
	private function validateDeferredSource($sourceType, $sourceId, $fkSoc, $fkAgence, $amount)
	{
		global $conf;
		$sourceType = strtolower(trim((string) $sourceType));
		if ($sourceType === 'other' && $sourceId <= 0) {
			return '';
		}
		$map = array(
			'boncommande' => array('sof_bon_commande_client', 'fk_soc', 'status IN (1,3)', 'remaining_amount'),
			'bst' => array('sof_bst', 'fk_soc_payer', 'status=1', 'estimated_amount'),
			'instruction' => array('sof_instruction_manageriale', 'fk_soc', 'status IN (1,2)', 'estimated_amount'),
		);
		if ($sourceId <= 0 || empty($map[$sourceType])) {
			return 'Le justificatif du paiement différé est invalide.';
		}
		$meta = $map[$sourceType];
		$sql = 'SELECT rowid,'.$meta[3].' available_amount FROM '.$this->db->prefix().$meta[0];
		$sql .= ' WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $sourceId.' AND '.$meta[1].'='.(int) $fkSoc.' AND '.$meta[2];
		$sql .= ' AND (fk_agence IS NULL OR fk_agence='.(int) $fkAgence.')';
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		if (!$row) {
			return 'Le justificatif sélectionné ne correspond pas au client, à l’agence ou à un statut utilisable.';
		}
		if ((float) $row->available_amount > 0 && (float) $amount > (float) $row->available_amount + 0.01) {
			return 'La part différée dépasse le montant disponible sur le justificatif sélectionné.';
		}
		return '';
	}

	/** Create and immediately collect a native Dolibarr customer deposit invoice. */
	public function captureCustomerDeposit(User $user, $fkSession, $fkSoc, array $components, $label = '', $fkDas = 0, $transactionRef = '')
	{
		if (!$this->hasRight($user, 'mouvement', 'cashin')) {
			return $this->fail("Permission refusée pour enregistrer un acompte.");
		}
		if (!$user->hasRight('facture', 'creer')) {
			return $this->fail('Le droit Dolibarr de création/validation des factures est requis pour créer un acompte.');
		}
		$total = 0.0;
		foreach ($components as $amount) {
			$amount = price2num($amount);
			if ($amount > 0) {
				$total += $amount;
			}
		}
		if ((int) $fkSoc <= 0 || $total <= 0) {
			return $this->fail('Le client et un montant positif sont obligatoires.');
		}
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'societe WHERE rowid = '.((int) $fkSoc).' AND entity IN ('.getEntity('societe').') AND client IN (1,2,3)';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->num_rows($resql) === 0) {
			return $this->fail('Le tiers sélectionné n’est pas un client actif de cette entité.');
		}
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$invoice = new Facture($this->db);
		$invoice->socid = (int) $fkSoc;
		$invoice->type = Facture::TYPE_DEPOSIT;
		$invoice->date = dol_now();
		$invoice->module_source = 'agence';
		$invoice->note_private = trim((string) $label) ?: 'Acompte client encaissé par le module Agence';
		$this->db->begin();
		$invoiceId = $invoice->create($user);
		if ($invoiceId <= 0) {
			$this->db->rollback();
			return $this->fail($invoice->error ?: "Échec de création de la facture d'acompte.", $invoice->errors);
		}
		$lineResult = $invoice->addline(trim((string) $label) ?: 'Acompte client', $total, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
		if ($lineResult <= 0 || $invoice->validate($user) <= 0) {
			$this->db->rollback();
			return $this->fail($invoice->error ?: "Échec de validation de la facture d'acompte.", $invoice->errors);
		}
		$result = $this->captureInvoicePayment($user, (int) $fkSession, (int) $invoiceId, $components, array(
			'transaction_ref' => trim((string) $transactionRef),
		));
		if ($result <= 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return (int) $invoiceId;
	}

	/** Create workflow validation rows from matching active rules. */
	public function createValidationChain(User $user, $objectType, $objectId, $amount, $fkAgence = 0, $fkDas = 0, $paymentMode = '')
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissevalidation.class.php';
		$rules = SofAgenceService::findWorkflowRules($this->db, $objectType, $amount, $fkAgence, $fkDas, $paymentMode);
		$created = 0;
		foreach ($rules as $rule) {
			$steps = $this->parseValidationSteps($rule->validation_steps);
			if (empty($steps)) {
				$steps = array(array('role' => '', 'user' => 0));
			}
			$level = 1;
			foreach ($steps as $step) {
				$validation = new SofCaisseValidation($this->db);
				$validation->entity = (int) $conf->entity;
				$validation->ref = $this->generateRef('VAL', 'sof_caisse_validation');
				$validation->object_type = $objectType;
				$validation->object_id = (int) $objectId;
				$validation->workflow_code = $rule->code;
				$validation->validation_level = $level++;
				$validation->validation_mode = 'sequential';
				$validation->fk_user_requester = (int) $user->id;
				$validation->fk_user_validator = !empty($step['user']) ? (int) $step['user'] : null;
				$validation->role_required = !empty($step['role']) ? substr($step['role'], 0, 64) : null;
				$validation->decision = '';
				$validation->date_request = dol_now();
				$validation->status = 0;
				if ($validation->create($user) > 0) {
					$created++;
				}
			}
			break; // Highest-priority matching rule only.
		}
		return $created;
	}

	/** Approve or reject the next pending workflow step for an object. */
	public function approveObject(User $user, $objectType, $objectId, $decision = 'approve', $reason = '')
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

		$decision = $decision === 'reject' ? 'reject' : 'approve';
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_validation';
		$sql .= ' WHERE entity = '.((int) $conf->entity)." AND object_type = '".$this->db->escape($objectType)."'";
		$sql .= ' AND object_id = '.((int) $objectId).' AND status = 0 ORDER BY validation_level ASC, rowid ASC LIMIT 1 FOR UPDATE';
		$resql = $this->db->query($sql);
		$step = $resql ? $this->db->fetch_object($resql) : null;
		if (!$step) {
			return 0; // No configured chain: the dedicated module permission is authoritative.
		}
		if (empty($user->admin) && !SofAgenceService::userCanAccessValidation($this->db, $user, $objectType, $objectId)) {
			return $this->fail("Cette validation n'appartient pas au périmètre agence de l'utilisateur.");
		}
		if (empty($user->admin) && !empty($step->fk_user_validator) && (int) $step->fk_user_validator !== (int) $user->id) {
			return $this->fail("Cette validation est affectée à un autre utilisateur.");
		}
		if (empty($user->admin) && empty($step->fk_user_validator) && !empty($step->role_required) && !$this->userHasOperationalRole($user, $step->role_required)) {
			return $this->fail("Le rôle requis pour cette validation est ".$step->role_required.'.');
		}
		$updates = array(
			'fk_user_validator' => (int) $user->id,
			'decision' => $decision,
			'decision_reason' => trim((string) $reason),
			'date_decision' => dol_now(),
			'status' => $decision === 'approve' ? 1 : 2,
		);
		return $this->updateRow('sof_caisse_validation', (int) $step->rowid, $updates, $user, 0);
	}

	/** Post non-native operational lines as balanced bookkeeping entries. */
	public function postSessionToAccounting(User $user, SofCaisseSession $session, $manageTransaction = true)
	{
		global $conf;
		if (!$this->hasRight($user, 'compta', 'post')) {
			return $this->fail('Permission refusée pour le déversement comptable.');
		}
		if (!isModEnabled('accounting')) {
			return $this->fail('Le module Comptabilité en partie double doit être activé avant le déversement.');
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
		if ($manageTransaction) {
			$this->db->begin();
		}
		$sql = 'SELECT rowid, status FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND rowid = '.((int) $session->id).' FOR UPDATE';
		$sessionResult = $this->db->query($sql);
		$currentSession = $sessionResult ? $this->db->fetch_object($sessionResult) : null;
		if (!$currentSession || (int) $currentSession->status !== SofCaisseSession::STATUS_VALIDATED) {
			if ($manageTransaction) {
				$this->db->rollback();
			}
			return $this->fail('Seule une session validée de l’entité courante peut être déversée.');
		}
		$sql = 'SELECT m.* FROM '.$this->db->prefix().'sof_caisse_mouvement m';
		$sql .= ' WHERE m.entity = '.((int) $conf->entity).' AND m.fk_session = '.((int) $session->id).' AND m.status = 1 AND m.accounting_status IN (0,2)';
		$sql .= " AND m.type_operation IN ('opening','manual_cash_in','manual_cash_out','adjustment') ORDER BY m.rowid FOR UPDATE";
		$resql = $this->db->query($sql);
		if (!$resql) {
			if ($manageTransaction) {
				$this->db->rollback();
			}
			return $this->fail($this->db->lasterror());
		}
		while ($movement = $this->db->fetch_object($resql)) {
			$mapping = $this->findAccountingMapping($movement);
			if (!$mapping) {
				if ($manageTransaction) {
					$this->db->rollback();
				}
				return $this->fail('Mapping comptable absent pour '.$movement->type_operation.' / '.$movement->payment_mode.'.');
			}
			if (trim((string) $mapping->journal_code) === '' || trim((string) $mapping->account_debit) === '' || trim((string) $mapping->account_credit) === '') {
				if ($manageTransaction) {
					$this->db->rollback();
				}
				return $this->fail('Mapping comptable incomplet pour '.$movement->type_operation.' / '.$movement->payment_mode.'.');
			}
			$debitAccount = $movement->direction === 'credit' ? $mapping->account_debit : $mapping->account_credit;
			$creditAccount = $movement->direction === 'credit' ? $mapping->account_credit : $mapping->account_debit;
			if ($this->createBookkeepingLine($user, $movement, $mapping, $debitAccount, (float) $movement->amount, 0) < 0
				|| $this->createBookkeepingLine($user, $movement, $mapping, $creditAccount, 0, (float) $movement->amount) < 0) {
				if ($manageTransaction) {
					$this->db->rollback();
				}
				return -1;
			}
			if ($this->updateRow('sof_caisse_mouvement', (int) $movement->rowid, array(
				'accounting_status' => 4, 'accounting_attempts' => (int) $movement->accounting_attempts + 1,
				'accounting_error' => null, 'date_accounting_attempt' => dol_now(),
			), $user) < 0) {
				if ($manageTransaction) {
					$this->db->rollback();
				}
				return -1;
			}
		}
		if ($manageTransaction) {
			if ($this->updateRow('sof_caisse_session', (int) $session->id, array(
				'status' => SofCaisseSession::STATUS_ACCOUNTED, 'accounting_status' => 4,
				'accounting_attempts' => (int) $session->accounting_attempts + 1, 'accounting_error' => null,
				'date_accounting' => dol_now(), 'fk_user_accounting' => (int) $user->id,
			), $user, SofCaisseSession::STATUS_VALIDATED) < 0) {
				$this->db->rollback();
				return -1;
			}
			$this->db->commit();
		}
		return 1;
	}

	/** Persist a rejected accounting attempt so the same validated session can be retried. */
	private function markAccountingFailure(User $user, SofCaisseSession $session, $message)
	{
		global $conf;
		$message = substr(trim((string) $message), 0, 4000);
		$this->db->begin();
		$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_session SET accounting_status = 2, accounting_attempts = COALESCE(accounting_attempts,0) + 1,';
		$sql .= " accounting_error = '".$this->db->escape($message)."', fk_user_accounting = ".((int) $user->id).', tms = CURRENT_TIMESTAMP';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $session->id).' AND status = '.SofCaisseSession::STATUS_VALIDATED;
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_mouvement SET accounting_status = 2, accounting_attempts = COALESCE(accounting_attempts,0) + 1,';
		$sql .= " accounting_error = '".$this->db->escape($message)."', date_accounting_attempt = CURRENT_TIMESTAMP, fk_user_modif = ".((int) $user->id).', tms = CURRENT_TIMESTAMP';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_session = '.((int) $session->id).' AND status = 1 AND accounting_status IN (0,2)';
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		SofAgenceService::logAudit($this->db, $user, 'SOF_ACCOUNTING_REJECT', $session, null, array('error' => $message), $message);
		$this->db->commit();
		return 1;
	}

	/** Utility: update a module row using typed SQL values and audit metadata. */
	public function updateRow($table, $id, array $updates, User $user, $expectedStatuses = null)
	{
		global $conf;
		if (!preg_match('/^sof_[a-z0-9_]+$/', (string) $table) || (int) $id <= 0) {
			return $this->fail('Table ou identifiant de mise à jour invalide.');
		}
		$relationFields = array('fk_agence', 'fk_caisse', 'fk_das', 'allowed_das');
		if (array_intersect(array_keys($updates), $relationFields)) {
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
			$sql = 'SELECT * FROM '.$this->db->prefix().$table.' WHERE rowid = '.((int) $id).' AND entity = '.((int) $conf->entity).' LIMIT 1';
			$resql = $this->db->query($sql);
			$current = $resql ? $this->db->fetch_object($resql) : null;
			if (!$current) {
				return $this->fail('Ligne de module introuvable dans l’entité courante.');
			}
			$fkAgence = array_key_exists('fk_agence', $updates) ? (int) $updates['fk_agence'] : (isset($current->fk_agence) ? (int) $current->fk_agence : 0);
			$fkCaisse = array_key_exists('fk_caisse', $updates) ? (int) $updates['fk_caisse'] : (isset($current->fk_caisse) ? (int) $current->fk_caisse : 0);
			$fkDas = array_key_exists('fk_das', $updates) ? (int) $updates['fk_das'] : (isset($current->fk_das) ? (int) $current->fk_das : 0);
			$contextError = SofAgenceService::validateAgencyCashDeskDas($this->db, $fkAgence, $fkCaisse, $fkDas, true);
			if ($contextError === '' && array_key_exists('allowed_das', $updates)) {
				$parentAgency = $table === 'sof_caisse' ? $fkAgence : 0;
				$contextError = SofAgenceService::validateAllowedDasConfiguration($this->db, $parentAgency, $updates['allowed_das'], true);
			}
			if ($contextError !== '') {
				return $this->fail($contextError);
			}
		}
		$parts = array();
		foreach ($updates as $field => $value) {
			if (!preg_match('/^[a-z][a-z0-9_]*$/', $field)) {
				continue;
			}
			$parts[] = $field.' = '.$this->sqlValue($value, $field);
		}
		$parts[] = 'fk_user_modif = '.((int) $user->id);
		$parts[] = 'tms = CURRENT_TIMESTAMP';
		$sql = 'UPDATE '.$this->db->prefix().$table.' SET '.implode(', ', $parts).' WHERE rowid = '.((int) $id);
		$sql .= ' AND entity = '.((int) $conf->entity);
		if ($expectedStatuses !== null) {
			$expectedStatuses = is_array($expectedStatuses) ? $expectedStatuses : array($expectedStatuses);
			$sql .= ' AND status IN ('.implode(',', array_map('intval', $expectedStatuses)).')';
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->fail($this->db->lasterror());
		}
		if ($expectedStatuses !== null && $this->db->affected_rows($resql) !== 1) {
			return $this->fail('La ligne a été modifiée par une opération concurrente.');
		}
		return 1;
	}

	private function upsertInvoiceLink(User $user, $facture, $session, $deferredStatus)
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/soffacturelink.class.php';
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_facture_link WHERE entity = '.((int) $conf->entity).' AND fk_facture = '.((int) $facture->id).' LIMIT 1';
		$resql = $this->db->query($sql);
		$existing = $resql ? $this->db->fetch_object($resql) : null;
		if ($existing) {
			return $this->updateRow('sof_facture_link', (int) $existing->rowid, array(
				'fk_soc' => (int) $facture->socid, 'fk_agence' => (int) $session->fk_agence,
				'fk_caisse' => (int) $session->fk_caisse, 'fk_session' => (int) $session->id,
				'fk_das' => (int) $session->fk_das ?: null, 'billing_status' => 1, 'deferred_status' => (int) $deferredStatus,
			), $user);
		}
		$link = new SofFactureLink($this->db);
		$link->entity = (int) $conf->entity;
		$link->fk_facture = (int) $facture->id;
		$link->fk_soc = (int) $facture->socid;
		$link->fk_agence = (int) $session->fk_agence;
		$link->fk_caisse = (int) $session->fk_caisse;
		$link->fk_session = (int) $session->id;
		$link->fk_das = (int) $session->fk_das ?: null;
		$link->source_type = 'cash_session';
		$link->source_id = (int) $session->id;
		$link->billing_status = 1;
		$link->deferred_status = (int) $deferredStatus;
		$link->accounting_status = 0;
		return $link->create($user) > 0 ? 1 : $this->fail($link->error ?: 'Échec du rattachement facture.', $link->errors);
	}

	private function invoiceRemaining($fkFacture, $invoiceTotal)
	{
		$sql = 'SELECT COALESCE(SUM(amount),0) paid FROM '.$this->db->prefix().'paiement_facture WHERE fk_facture = '.((int) $fkFacture);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		return max(0, price2num($invoiceTotal - ($obj ? (float) $obj->paid : 0)));
	}

	private function validateDeferredCredit($fkSoc, $fkAgence, $amount)
	{
		global $conf;
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_tiers_credit_profile WHERE entity = '.((int) $conf->entity).' AND fk_soc = '.((int) $fkSoc);
		$sql .= ' AND status = 1 AND (fk_agence_followup IS NULL OR fk_agence_followup = '.((int) $fkAgence).') ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$profile = $resql ? $this->db->fetch_object($resql) : null;
		if (!$profile) {
			return 'Un profil de crédit client actif est obligatoire pour un paiement différé.';
		}
		if (empty($profile->deferred_payment_allowed) || !empty($profile->blocked_status)) {
			return 'Le paiement différé est interdit ou bloqué pour ce client.';
		}
		$sql = 'SELECT COALESCE(SUM(remaining_amount),0) exposure FROM '.$this->db->prefix().'sof_paiement_differe';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_soc = '.((int) $fkSoc).' AND status NOT IN (4,7,9)';
		$resql = $this->db->query($sql);
		$exposure = $resql && ($obj = $this->db->fetch_object($resql)) ? (float) $obj->exposure : 0;
		if ((float) $profile->credit_limit > 0 && $exposure + $amount > (float) $profile->credit_limit + 0.01) {
			return 'Le plafond de crédit client serait dépassé.';
		}
		return '';
	}

	/** Enforce DAS and product/service payment policies on an invoice. */
	private function validateInvoicePaymentPolicy($invoiceId, $fkAgence, $fkDas, array $realModes, $hasDeferred)
	{
		global $conf;
		if ($fkDas > 0) {
			$sql = 'SELECT allowed_payment_modes FROM '.$this->db->prefix().'sof_das WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $fkDas).' AND status = 1';
			$resql = $this->db->query($sql);
			$das = $resql ? $this->db->fetch_object($resql) : null;
			if (!$das) {
				return 'Le DAS de la session est introuvable ou inactif.';
			}
			$allowed = $this->parseAllowedPaymentModes($das->allowed_payment_modes);
			foreach ($realModes as $mode) {
				if (!empty($allowed) && !in_array(strtoupper($mode), $allowed, true)) {
					return 'Le mode '.$mode." est interdit par la configuration du DAS.";
				}
			}
			if ($hasDeferred && !empty($allowed) && !in_array('DIFF', $allowed, true) && !in_array('DEFERRED', $allowed, true)) {
				return 'Le paiement différé est interdit par la configuration du DAS.';
			}
		}

		$sql = 'SELECT fd.fk_product, pd.fk_das, pd.fk_agence, pd.payment_modes_allowed, pd.deferred_payment_allowed';
		$sql .= ' FROM '.$this->db->prefix().'facturedet fd JOIN '.$this->db->prefix().'sof_product_das pd ON pd.fk_product=fd.fk_product';
		$sql .= ' WHERE fd.fk_facture='.((int) $invoiceId).' AND fd.fk_product IS NOT NULL AND pd.entity='.((int) $conf->entity).' AND pd.status=1';
		$sql .= ' AND (pd.fk_agence IS NULL OR pd.fk_agence='.((int) $fkAgence).')';
		$sql .= ' ORDER BY fd.fk_product, CASE WHEN pd.fk_agence IS NULL THEN 1 ELSE 0 END, pd.rowid DESC';
		$resql = $this->db->query($sql);
		$seenProducts = array();
		while ($resql && ($policy = $this->db->fetch_object($resql))) {
			$productId = (int) $policy->fk_product;
			if (isset($seenProducts[$productId])) {
				continue;
			}
			$seenProducts[$productId] = true;
			if ($fkDas <= 0) {
				return 'La session doit préciser un DAS pour encaisser un produit ou service soumis à une politique DAS.';
			}
			if ((int) $policy->fk_das !== (int) $fkDas) {
				return 'Un produit ou service de la facture appartient à un autre DAS.';
			}
			$allowed = $this->parseAllowedPaymentModes($policy->payment_modes_allowed);
			foreach ($realModes as $mode) {
				if (!empty($allowed) && !in_array(strtoupper($mode), $allowed, true)) {
					return 'Le mode '.$mode.' est interdit pour un produit ou service de la facture.';
				}
			}
			if ($hasDeferred && empty($policy->deferred_payment_allowed)) {
				return 'Le paiement différé est interdit pour un produit ou service de la facture.';
			}
		}
		return '';
	}

	private function paymentModeId($code)
	{
		global $conf;
		$sql = 'SELECT id FROM '.$this->db->prefix()."c_paiement WHERE code = '".$this->db->escape($code)."'";
		$sql .= ' AND active = 1 AND entity IN (0,'.((int) $conf->entity).') ORDER BY entity DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		return $obj ? (int) $obj->id : 0;
	}

	/** Return normalized payment modes configured on a cash desk. */
	private function parseAllowedPaymentModes($raw)
	{
		if (trim((string) $raw) === '') {
			return array();
		}
		$decoded = json_decode((string) $raw, true);
		$values = is_array($decoded) ? $decoded : preg_split('/[,;|\s]+/', (string) $raw);
		$modes = array();
		foreach ($values as $value) {
			$mode = strtoupper(trim((string) $value));
			if ($mode !== '') {
				$modes[$mode] = $mode;
			}
		}
		return array_values($modes);
	}

	/** Parse a JSON or delimited list of positive identifiers. */
	private function parseNumericList($raw)
	{
		if (trim((string) $raw) === '') {
			return array();
		}
		$decoded = json_decode((string) $raw, true);
		$values = is_array($decoded) ? $decoded : preg_split('/[^0-9]+/', (string) $raw);
		$ids = array();
		foreach ($values as $value) {
			if ((int) $value > 0) {
				$ids[(int) $value] = (int) $value;
			}
		}
		return array_values($ids);
	}

	/** Resolve and validate the native Dolibarr financial account for a payment mode. */
	private function resolvePaymentBankAccount($caisse, $mode)
	{
		$mode = strtoupper((string) $mode);
		$cashModes = array('LIQ', 'CASH', 'ESP', 'ESPECES');
		$cardModes = array('CB', 'CARD', 'VAD', 'SUMUP');
		$chequeModes = array('CHQ', 'CHEQUE');
		$mobileModes = array('OM', 'MM', 'MOMO', 'MOBILE');
		$isCash = in_array($mode, $cashModes, true);

		$field = 'fk_bank_account_other';
		$globalKeys = array();
		if ($isCash) {
			$field = 'fk_bank_account';
			$globalKeys = array('CASHDESK_ID_BANKACCOUNT_CASH1', 'CASHDESK_ID_BANKACCOUNT_CASH');
		} elseif (in_array($mode, $cardModes, true)) {
			$field = 'fk_bank_account_card';
			$globalKeys = array('CASHDESK_ID_BANKACCOUNT_CB1', 'CASHDESK_ID_BANKACCOUNT_CB');
		} elseif (in_array($mode, $chequeModes, true)) {
			$field = 'fk_bank_account_cheque';
			$globalKeys = array('CASHDESK_ID_BANKACCOUNT_CHEQUE1', 'CASHDESK_ID_BANKACCOUNT_CHEQUE');
		} elseif (in_array($mode, $mobileModes, true)) {
			$field = 'fk_bank_account_mobile';
		}

		$accountId = !empty($caisse->$field) ? (int) $caisse->$field : 0;
		if ($accountId <= 0 && !$isCash && !empty($caisse->fk_bank_account_other)) {
			$accountId = (int) $caisse->fk_bank_account_other;
		}
		foreach ($globalKeys as $key) {
			if ($accountId <= 0) {
				$accountId = getDolGlobalInt($key);
			}
		}
		if ($accountId <= 0 && !empty($caisse->fk_bank_account)) {
			$accountId = (int) $caisse->fk_bank_account;
		}
		if ($accountId <= 0) {
			return $this->fail('Aucun compte financier n’est configuré pour le mode '.$mode.'.');
		}

		$sql = 'SELECT rowid, courant, clos FROM '.$this->db->prefix().'bank_account WHERE rowid = '.$accountId;
		$resql = $this->db->query($sql);
		$account = $resql ? $this->db->fetch_object($resql) : null;
		if (!$account || !empty($account->clos)) {
			return $this->fail('Le compte financier configuré pour '.$mode.' est absent ou clôturé.');
		}
		if (!$isCash && (int) $account->courant === 2) {
			return $this->fail('Le mode '.$mode.' doit être relié à un compte bancaire non espèces sur la fiche caisse.');
		}
		return $accountId;
	}

	private function movementTotal($fkSession, $type, $direction)
	{
		$sql = 'SELECT COALESCE(SUM(amount),0) total FROM '.$this->db->prefix().'sof_caisse_mouvement WHERE fk_session = '.((int) $fkSession);
		$sql .= " AND type_operation = '".$this->db->escape($type)."' AND direction = '".$this->db->escape($direction)."' AND status = 1";
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		return $obj ? (float) $obj->total : 0;
	}

	private function gapSeverity($amount)
	{
		$critical = (float) getDolGlobalString('AGENCE_GAP_CRITICAL_AMOUNT', '10000');
		$major = (float) getDolGlobalString('AGENCE_GAP_MAJOR_AMOUNT', '1000');
		return $amount >= $critical ? 'critical' : ($amount >= $major ? 'major' : 'minor');
	}

	private function parseValidationSteps($raw)
	{
		if (empty($raw)) {
			return array();
		}
		$decoded = json_decode($raw, true);
		if (is_array($decoded)) {
			$steps = array();
			foreach ($decoded as $step) {
				if (is_string($step)) {
					$steps[] = array('role' => $step, 'user' => 0);
				} elseif (is_array($step)) {
					$steps[] = array('role' => isset($step['role']) ? $step['role'] : '', 'user' => isset($step['user']) ? (int) $step['user'] : 0);
				}
			}
			return $steps;
		}
		$steps = array();
		foreach (preg_split('/[,;]+/', $raw) as $role) {
			if (trim($role) !== '') {
				$steps[] = array('role' => trim($role), 'user' => 0);
			}
		}
		return $steps;
	}

	private function userHasOperationalRole(User $user, $role)
	{
		global $conf;
		$role = $this->db->escape($role);
		$nowSql = "'".$this->db->escape($this->db->idate(dol_now()))."'";
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_agence_user WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $user->id);
		$sql .= " AND role_code = '".$role."' AND status = 1";
		$sql .= ' AND (date_start IS NULL OR date_start <= '.$nowSql.') AND (date_end IS NULL OR date_end >= '.$nowSql.') LIMIT 1';
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return true;
		}
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_role_transversal WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $user->id);
		$sql .= " AND role_code = '".$role."' AND status = 1";
		$sql .= ' AND (date_start IS NULL OR date_start <= '.$nowSql.') AND (date_end IS NULL OR date_end >= '.$nowSql.') LIMIT 1';
		$resql = $this->db->query($sql);
		return $resql && $this->db->num_rows($resql) > 0;
	}

	private function findAccountingMapping($movement)
	{
		global $conf;
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_mapping_comptable WHERE entity = '.((int) $conf->entity).' AND status = 1';
		$sql .= " AND operation_type = '".$this->db->escape($movement->type_operation)."'";
		$sql .= ' AND (fk_agence IS NULL OR fk_agence = '.((int) $movement->fk_agence).')';
		$sql .= ' AND (fk_das IS NULL OR fk_das = '.((int) $movement->fk_das).')';
		$sql .= " AND (payment_mode IS NULL OR payment_mode = '' OR payment_mode = '".$this->db->escape($movement->payment_mode)."')";
		$sql .= ' ORDER BY CASE WHEN fk_agence IS NULL THEN 1 ELSE 0 END, CASE WHEN fk_das IS NULL THEN 1 ELSE 0 END, rowid LIMIT 1';
		$resql = $this->db->query($sql);
		return $resql ? $this->db->fetch_object($resql) : null;
	}

	private function createBookkeepingLine(User $user, $movement, $mapping, $account, $debit, $credit)
	{
		$line = new BookKeeping($this->db);
		$line->doc_date = $this->db->jdate($movement->transaction_date);
		$line->doc_type = 'agence_cash';
		$line->doc_ref = $movement->ref;
		$line->fk_doc = (int) $movement->rowid;
		$line->fk_docdet = 0;
		$line->numero_compte = $account;
		$line->label_compte = $account;
		$line->label_operation = $movement->label ?: $movement->type_operation;
		$line->debit = $debit;
		$line->credit = $credit;
		$line->code_journal = $mapping->journal_code;
		$line->journal_label = $mapping->journal_code;
		$line->fk_user_author = (int) $user->id;
		$result = $line->create($user, 1);
		// Dolibarr BookKeeping::create returns 0 on success and a negative value on failure.
		if ($result < 0) {
			return $this->fail($line->error ?: 'Échec de création de la ligne comptable.', $line->errors);
		}
		return !empty($line->id) ? (int) $line->id : 1;
	}

	/** Create a refund request after checking the paid origin and cumulative cap. */
	public function requestRefund(User $user, array $data)
	{
		global $conf;

		if (!$this->hasRight($user, 'remboursement', 'request')) {
			return $this->fail('Permission refusée pour demander un remboursement.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofremboursement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$invoiceId = !empty($data['fk_facture_origin']) ? (int) $data['fk_facture_origin'] : 0;
		$amount = price2num(isset($data['requested_amount']) ? $data['requested_amount'] : 0);
		$reason = trim((string) (isset($data['reason']) ? $data['reason'] : ''));
		if ($invoiceId <= 0 || $amount <= 0 || $reason === '') {
			return $this->fail("La facture d'origine, le montant et le motif sont obligatoires.");
		}
		$invoice = new Facture($this->db);
		if ($invoice->fetch($invoiceId) <= 0 || !in_array((int) $invoice->type, array(Facture::TYPE_STANDARD, Facture::TYPE_DEPOSIT), true)) {
			return $this->fail("La facture d'origine est introuvable ou incompatible.");
		}
		$paid = $this->invoicePaid($invoiceId);
		$sql = 'SELECT COALESCE(SUM(refunded_amount),0) total FROM '.$this->db->prefix().'sof_remboursement';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_facture_origin = '.$invoiceId.' AND status IN (3,4)';
		$resql = $this->db->query($sql);
		$alreadyRefunded = $resql && ($obj = $this->db->fetch_object($resql)) ? (float) $obj->total : 0;
		if ($amount + $alreadyRefunded > $paid + 0.01) {
			return $this->fail('Le cumul remboursé dépasserait le montant réellement encaissé ('.price($paid).').');
		}

		$context = $this->invoiceAgencyContext($invoiceId);
		if (empty($context['fk_agence'])) {
			return $this->fail("La facture n'est rattachée à aucune opération Agence.");
		}
		if (!empty($data['fk_agence']) && (int) $data['fk_agence'] !== (int) $context['fk_agence']) {
			return $this->fail("L'agence demandée ne correspond pas au rattachement de la facture.");
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		$allowedAgencies = SofAgenceService::allowedAgencyIds($this->db, $user);
		if ($allowedAgencies !== null && !in_array((int) $context['fk_agence'], $allowedAgencies, true)) {
			return $this->fail('La facture appartient à une agence hors de votre périmètre.');
		}
		$refund = new SofRemboursement($this->db);
		$refund->entity = (int) $conf->entity;
		$refund->ref = $this->generateRef('REM', 'sof_remboursement');
		$refund->fk_soc = (int) $invoice->socid;
		$refund->fk_agence = !empty($data['fk_agence']) ? (int) $data['fk_agence'] : (int) $context['fk_agence'];
		$refund->fk_das = !empty($data['fk_das']) ? (int) $data['fk_das'] : ((int) $context['fk_das'] ?: null);
		$refund->fk_caisse = (int) $context['fk_caisse'] ?: null;
		$refund->fk_session = (int) $context['fk_session'] ?: null;
		$refund->fk_facture_origin = $invoiceId;
		$refund->fk_paiement_origin = !empty($data['fk_paiement_origin']) ? (int) $data['fk_paiement_origin'] : null;
		$refund->fk_mouvement_origin = !empty($data['fk_mouvement_origin']) ? (int) $data['fk_mouvement_origin'] : null;
		$refund->requested_amount = $amount;
		$refund->approved_amount = 0;
		$refund->refunded_amount = 0;
		$refund->payment_mode = strtoupper(substr((string) (isset($data['payment_mode']) ? $data['payment_mode'] : 'LIQ'), 0, 64));
		$refund->reason = $reason;
		$refund->request_date = dol_now();
		$refund->fk_user_requester = (int) $user->id;
		$refund->status = SofRemboursement::STATUS_PENDING;
		$refund->accounting_status = 0;

		$this->db->begin();
		$lockSql = 'SELECT rowid FROM '.$this->db->prefix().'facture WHERE entity IN ('.getEntity('invoice').') AND rowid = '.$invoiceId.' FOR UPDATE';
		if (!$this->db->query($lockSql)) {
			$this->db->rollback();
			return $this->fail('Impossible de verrouiller la facture pour la demande de remboursement.');
		}
		$sql = 'SELECT COALESCE(SUM(refunded_amount),0) total FROM '.$this->db->prefix().'sof_remboursement';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_facture_origin = '.$invoiceId.' AND status IN (3,4)';
		$resql = $this->db->query($sql);
		$lockedAlreadyRefunded = $resql && ($obj = $this->db->fetch_object($resql)) ? (float) $obj->total : 0;
		if (!$resql || $amount + $lockedAlreadyRefunded > $paid + 0.01) {
			$this->db->rollback();
			return $this->fail('Le cumul remboursé dépasserait le montant réellement encaissé.');
		}
		$id = $refund->create($user);
		if ($id <= 0) {
			$this->db->rollback();
			return $this->fail($refund->error ?: 'Impossible de créer la demande de remboursement.', $refund->errors);
		}
		$this->createValidationChain($user, 'refund', (int) $id, $amount, (int) $refund->fk_agence, (int) $refund->fk_das, $refund->payment_mode);
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		SofAgenceService::logAudit($this->db, $user, 'SOF_REFUND_REQUEST', $refund, null, $this->snapshot($refund), $reason);
		$this->db->commit();
		return (int) $id;
	}

	/** Approve the next refund step and mark it approved when the chain is complete. */
	public function validateRefund(User $user, $refundId, $approvedAmount = 0, $reason = '')
	{
		global $conf;

		if (!$this->hasRight($user, 'remboursement', 'validate')) {
			return $this->fail('Permission refusée pour valider un remboursement.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofremboursement.class.php';
		$refund = new SofRemboursement($this->db);
		if ($refund->fetch((int) $refundId) <= 0 || !in_array((int) $refund->status, array(0, 1), true)) {
			return $this->fail("Le remboursement n'est pas en attente de validation.");
		}
		if (!$this->ensureAgencyScope($user, (int) $refund->fk_agence, 'refund_validate', (float) $refund->requested_amount, (int) $refund->fk_das)) {
			return -1;
		}
		if (empty($user->admin) && !getDolGlobalInt('AGENCE_ALLOW_SELF_APPROVAL') && (int) $refund->fk_user_requester === (int) $user->id) {
			return $this->fail('Le demandeur ne peut pas approuver son propre remboursement.');
		}
		$approvedAmount = price2num($approvedAmount);
		if ($approvedAmount <= 0) {
			$approvedAmount = (float) $refund->requested_amount;
		}
		if ($approvedAmount > (float) $refund->requested_amount + 0.01) {
			return $this->fail('Le montant approuvé ne peut pas dépasser le montant demandé.');
		}
		$this->db->begin();
		if ($this->approveObject($user, 'refund', (int) $refund->id, 'approve', $reason) < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse_validation WHERE entity = '.((int) $conf->entity);
		$sql .= " AND object_type = 'refund' AND object_id = ".((int) $refund->id).' AND status = 0';
		$resql = $this->db->query($sql);
		$pending = $resql && ($obj = $this->db->fetch_object($resql)) ? (int) $obj->nb : 0;
		if ($pending === 0) {
			if ($this->updateRow('sof_remboursement', (int) $refund->id, array(
				'approved_amount' => $approvedAmount,
				'validation_date' => dol_now(),
				'fk_user_validator' => (int) $user->id,
				'status' => SofRemboursement::STATUS_APPROVED,
			), $user, (int) $refund->status) < 0) {
				$this->db->rollback();
				return -1;
			}
		}
		$this->db->commit();
		return 1;
	}

	/** Reject a pending refund and close all of its validation steps. */
	public function rejectRefund(User $user, $refundId, $reason)
	{
		global $conf;
		if (!$this->hasRight($user, 'remboursement', 'validate') || trim((string) $reason) === '') {
			return $this->fail('Permission refusée ou motif de rejet absent.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofremboursement.class.php';
		$refund = new SofRemboursement($this->db);
		if ($refund->fetch((int) $refundId) <= 0 || !in_array((int) $refund->status, array(0, 1), true)) {
			return $this->fail("Le remboursement n'est pas en attente de validation.");
		}
		if (!$this->ensureAgencyScope($user, (int) $refund->fk_agence, 'refund_reject', (float) $refund->requested_amount, (int) $refund->fk_das)) {
			return -1;
		}
		$this->db->begin();
		if ($this->approveObject($user, 'refund', (int) $refundId, 'reject', $reason) < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_validation SET status = 2, decision = \'canceled\', date_decision = CURRENT_TIMESTAMP';
		$sql .= ' WHERE entity = '.((int) $conf->entity)." AND object_type = 'refund' AND object_id = ".((int) $refundId).' AND status = 0';
		$this->db->query($sql);
		$result = $this->updateRow('sof_remboursement', (int) $refundId, array(
			'rejection_reason' => trim((string) $reason), 'status' => 8,
		), $user, (int) $refund->status);
		$result < 0 ? $this->db->rollback() : $this->db->commit();
		if ($result > 0) {
			$this->emitIntegrationEvent('validation.decided', 'refund', (int) $refund->id, (int) $refund->fk_agence, array('ref' => $refund->ref, 'decision' => 'reject', 'reason' => trim((string) $reason), 'final' => true, 'subject' => 'Remboursement rejeté '.$refund->ref), $user);
		}
		return $result;
	}

	/** Execute an approved refund from the executor's open cash session. */
	public function executeRefund(User $user, $refundId)
	{
		global $conf;

		if (!$this->hasRight($user, 'remboursement', 'execute')) {
			return $this->fail('Permission refusée pour exécuter un remboursement.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofremboursement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/paymentvarious.class.php';
		$refund = new SofRemboursement($this->db);
		if ($refund->fetch((int) $refundId) <= 0 || (int) $refund->status !== SofRemboursement::STATUS_APPROVED) {
			return $this->fail('Le remboursement doit être approuvé avant exécution.');
		}
		if (!$this->ensureAgencyScope($user, (int) $refund->fk_agence, 'refund_execute', (float) $refund->approved_amount, (int) $refund->fk_das)) {
			return -1;
		}
		$amount = price2num($refund->approved_amount);
		if ($amount <= 0) {
			return $this->fail('Le montant approuvé est invalide.');
		}
		$session = $this->getOpenSessionForUser((int) $user->id);
		if (!$session) {
			return $this->fail("L'exécutant doit posséder une session de caisse ouverte et non gelée.");
		}
		if ((int) $session->fk_agence !== (int) $refund->fk_agence) {
			return $this->fail("La session d'exécution doit appartenir à l'agence du remboursement.");
		}
		if (!empty($refund->fk_das) && !empty($session->fk_das) && (int) $session->fk_das !== (int) $refund->fk_das) {
			return $this->fail("Le DAS de la session ne correspond pas au DAS du remboursement.");
		}
		if (empty($user->admin) && !getDolGlobalInt('AGENCE_ALLOW_SELF_APPROVAL')
			&& ((int) $refund->fk_user_requester === (int) $user->id || (int) $refund->fk_user_validator === (int) $user->id)) {
			return $this->fail("Le demandeur ou validateur ne peut pas exécuter lui-même le remboursement.");
		}
		$caisse = new SofCaisse($this->db);
		if ($caisse->fetch((int) $session->fk_caisse) <= 0) {
			return $this->fail('La caisse de remboursement est introuvable.');
		}
		if ((float) $caisse->refund_ceiling > 0 && $amount > (float) $caisse->refund_ceiling) {
			return $this->fail('Le montant dépasse le plafond de remboursement de la caisse.');
		}
		$paid = $this->invoicePaid((int) $refund->fk_facture_origin);
		$sql = 'SELECT COALESCE(SUM(refunded_amount),0) total FROM '.$this->db->prefix().'sof_remboursement';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_facture_origin = '.((int) $refund->fk_facture_origin);
		$sql .= ' AND rowid <> '.((int) $refund->id).' AND status IN (3,4)';
		$resql = $this->db->query($sql);
		$already = $resql && ($obj = $this->db->fetch_object($resql)) ? (float) $obj->total : 0;
		if ($amount + $already > $paid + 0.01) {
			return $this->fail('Contrôle anti-surremboursement déclenché.');
		}
		$refundMode = strtoupper((string) $refund->payment_mode);
		$allowedModes = $this->parseAllowedPaymentModes($caisse->allowed_payment_modes);
		if ($refundMode !== 'AVOIR' && !empty($allowedModes) && !in_array($refundMode, $allowedModes, true)) {
			return $this->fail('Le mode de remboursement '.$refundMode.' est interdit sur cette caisse.');
		}
		$refundBankAccountId = 0;
		if ($refundMode !== 'AVOIR') {
			$refundBankAccountId = $this->resolvePaymentBankAccount($caisse, $refundMode);
			if ($refundBankAccountId <= 0) {
				return -1;
			}
		}
		if (!$user->hasRight('facture', 'creer')) {
			return $this->fail("Le droit Dolibarr de création/validation des factures est requis pour générer l'avoir.");
		}

		$this->db->begin();
		$invoiceLockSql = 'SELECT rowid FROM '.$this->db->prefix().'facture WHERE entity IN ('.getEntity('invoice').') AND rowid = '.((int) $refund->fk_facture_origin).' FOR UPDATE';
		$refundLockSql = 'SELECT status FROM '.$this->db->prefix().'sof_remboursement WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $refund->id).' FOR UPDATE';
		$invoiceLock = $this->db->query($invoiceLockSql);
		$refundLock = $this->db->query($refundLockSql);
		$lockedRefund = $refundLock ? $this->db->fetch_object($refundLock) : null;
		if (!$invoiceLock || !$lockedRefund || (int) $lockedRefund->status !== SofRemboursement::STATUS_APPROVED) {
			$this->db->rollback();
			return $this->fail('Le remboursement a été modifié par une autre opération.');
		}
		$sql = 'SELECT COALESCE(SUM(refunded_amount),0) total FROM '.$this->db->prefix().'sof_remboursement';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_facture_origin = '.((int) $refund->fk_facture_origin);
		$sql .= ' AND rowid <> '.((int) $refund->id).' AND status IN (3,4)';
		$resql = $this->db->query($sql);
		$lockedAlready = $resql && ($obj = $this->db->fetch_object($resql)) ? (float) $obj->total : 0;
		if (!$resql || $amount + $lockedAlready > $paid + 0.01) {
			$this->db->rollback();
			return $this->fail('Contrôle anti-surremboursement concurrent déclenché.');
		}
		$creditNoteId = $this->createCreditNote($user, $refund, $amount);
		if ($creditNoteId < 0) {
			$this->db->rollback();
			return -1;
		}
		$paymentVariousId = 0;
		$bankLineId = 0;
		if ($refundMode !== 'AVOIR') {
			$modeId = $this->paymentModeId($refundMode);
			if ($modeId <= 0) {
				$this->db->rollback();
				return $this->fail('Mode de remboursement Dolibarr absent ou inactif.');
			}
			$payment = new PaymentVarious($this->db);
			$payment->datep = dol_now();
			$payment->datev = dol_now();
			$payment->amount = $amount;
			$payment->sens = 0;
			$payment->fk_account = $refundBankAccountId;
			$payment->type_payment = $modeId;
			$payment->label = 'Remboursement '.$refund->ref;
			$payment->entity = (int) $conf->entity;
			if (property_exists($payment, 'socid')) {
				$payment->socid = (int) $refund->fk_soc;
			}
			$paymentVariousId = $payment->create($user);
			if ($paymentVariousId <= 0) {
				$this->db->rollback();
				return $this->fail($payment->error ?: 'Échec du décaissement Dolibarr.', $payment->errors);
			}
			$movementId = $this->createMovement($user, array(
				'fk_agence' => (int) $session->fk_agence, 'fk_caisse' => (int) $session->fk_caisse,
				'fk_session' => (int) $session->rowid, 'fk_das' => (int) $refund->fk_das,
				'fk_soc' => (int) $refund->fk_soc, 'fk_facture' => (int) $refund->fk_facture_origin,
				'fk_payment_various' => (int) $paymentVariousId,
				'type_operation' => 'refund', 'direction' => 'debit', 'payment_mode' => $refundMode,
				'amount' => $amount, 'source_type' => 'refund', 'source_id' => (int) $refund->id,
				'label' => 'Remboursement '.$refund->ref,
			), false);
			if ($movementId <= 0) {
				$this->db->rollback();
				return -1;
			}
		}
		if ($this->createCreditTracking($user, $refund, $creditNoteId, $amount) < 0) {
			$this->db->rollback();
			return -1;
		}
		$result = $this->updateRow('sof_remboursement', (int) $refund->id, array(
			'fk_agence' => (int) $session->fk_agence, 'fk_caisse' => (int) $session->fk_caisse,
			'fk_session' => (int) $session->rowid, 'fk_facture_avoir' => $creditNoteId,
			'fk_payment_various' => $paymentVariousId ?: null, 'refunded_amount' => $amount,
			'execution_date' => dol_now(), 'fk_user_executor' => (int) $user->id,
			'status' => SofRemboursement::STATUS_EXECUTED,
		), $user, SofRemboursement::STATUS_APPROVED);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->recalculateSession((int) $session->rowid);
		$this->db->commit();
		$this->emitIntegrationEvent('refund.completed', 'refund', (int) $refund->id, (int) $refund->fk_agence, array(
			'ref' => $refund->ref, 'fk_soc' => (int) $refund->fk_soc, 'fk_facture_origin' => (int) $refund->fk_facture_origin,
			'fk_facture_avoir' => (int) $creditNoteId, 'amount' => (float) $amount, 'payment_mode' => $refundMode,
			'subject' => 'Remboursement exécuté '.$refund->ref,
		), $user);
		return 1;
	}

	/** Start a surprise control and really freeze the related open session. */
	public function startControl(User $user, $controlId)
	{
		global $conf;
		if (!$this->hasRight($user, 'controle', 'create') || !$this->hasRight($user, 'controle', 'freeze')) {
			return $this->fail('Les droits de contrôle et de gel sont requis pour démarrer un contrôle.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissecontrole.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		$control = new SofCaisseControle($this->db);
		if ($control->fetch((int) $controlId) <= 0 || (int) $control->status !== 0) {
			return $this->fail('Le contrôle est introuvable ou déjà démarré.');
		}
		if (!$this->ensureAgencyScope($user, (int) $control->fk_agence, 'control_start', 0, (int) $control->fk_das)) {
			return -1;
		}
		$this->db->begin();
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_controle WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $controlId).' AND status = 0 FOR UPDATE';
		$resql = $this->db->query($sql);
		$lockedControl = $resql ? $this->db->fetch_object($resql) : null;
		if (!$lockedControl) {
			$this->db->rollback();
			return $this->fail('Le contrôle a déjà été démarré par une autre requête.');
		}
		$sessionId = (int) $lockedControl->fk_session;
		if ($sessionId <= 0 && (int) $lockedControl->fk_caisse > 0) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity);
			$sql .= ' AND fk_caisse = '.((int) $lockedControl->fk_caisse).' AND status IN (1,2,3) ORDER BY date_opening DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$sessionId = $resql && ($obj = $this->db->fetch_object($resql)) ? (int) $obj->rowid : 0;
		}
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity).' AND rowid = '.$sessionId.' AND status IN (1,2,3) FOR UPDATE';
		$resql = $sessionId > 0 ? $this->db->query($sql) : false;
		$session = $resql ? $this->db->fetch_object($resql) : null;
		if (!$session) {
			$this->db->rollback();
			return $this->fail('Aucune session active à contrôler.');
		}
		if ((int) $session->fk_agence !== (int) $lockedControl->fk_agence
			|| (!empty($lockedControl->fk_caisse) && (int) $session->fk_caisse !== (int) $lockedControl->fk_caisse)
			|| (!empty($lockedControl->fk_das) && (int) $session->fk_das !== (int) $lockedControl->fk_das)) {
			$this->db->rollback();
			return $this->fail('Le contrôle, la session, la caisse et le DAS ne partagent pas le même contexte.');
		}
		$contextError = SofAgenceService::validateAgencyCashDeskDas($this->db, (int) $session->fk_agence, (int) $session->fk_caisse, (int) $session->fk_das, true);
		if ($contextError !== '') {
			$this->db->rollback();
			return $this->fail($contextError);
		}
		$theoretical = $this->recalculateSession($sessionId);
		if ($theoretical < 0) {
			$this->db->rollback();
			return -1;
		}
		if ($this->transitionSession($user, $sessionId, 'freeze') < 0
			|| $this->updateRow('sof_caisse_controle', (int) $control->id, array(
				'fk_session' => $sessionId, 'fk_user_controller' => (int) $user->id,
				'previous_session_status' => (int) $session->status,
				'date_start' => dol_now(), 'freeze_enabled' => 1,
				'theoretical_amount' => $theoretical, 'status' => 1,
			), $user, 0) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/** Complete a surprise control, create its gap and unfreeze the session. */
	public function completeControl(User $user, $controlId, $physicalAmount, $observations = '')
	{
		global $conf;
		if (!$this->hasRight($user, 'controle', 'create') || !$this->hasRight($user, 'controle', 'freeze')) {
			return $this->fail('Les droits de contrôle et de gel sont requis pour terminer un contrôle.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissecontrole.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisseecart.class.php';
		$control = new SofCaisseControle($this->db);
		if ($control->fetch((int) $controlId) <= 0 || (int) $control->status !== 1 || empty($control->fk_session)) {
			return $this->fail('Le contrôle doit être en cours.');
		}
		if (!$this->ensureAgencyScope($user, (int) $control->fk_agence, 'control_complete', 0, (int) $control->fk_das)) {
			return -1;
		}
		$physicalAmount = price2num($physicalAmount);
		if ($physicalAmount < 0) {
			return $this->fail('Le montant physique est invalide.');
		}
		$this->db->begin();
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_controle WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $controlId).' AND status = 1 FOR UPDATE';
		$resql = $this->db->query($sql);
		$lockedControl = $resql ? $this->db->fetch_object($resql) : null;
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $control->fk_session).' FOR UPDATE';
		$resql = $lockedControl ? $this->db->query($sql) : false;
		$session = $resql ? $this->db->fetch_object($resql) : null;
		if (!$lockedControl || !$session || (int) $session->status !== 4 || empty($session->freeze_status)) {
			$this->db->rollback();
			return $this->fail('Le contrôle ou le gel de caisse a été modifié par une autre requête.');
		}
		$theoretical = $this->recalculateSession((int) $lockedControl->fk_session);
		if ($theoretical < 0) {
			$this->db->rollback();
			return -1;
		}
		$gap = price2num($physicalAmount - $theoretical);
		if (abs($gap) >= 0.01) {
			$gapObject = new SofCaisseEcart($this->db);
			$gapObject->entity = (int) $conf->entity;
			$gapObject->ref = $this->generateRef('ECA', 'sof_caisse_ecart');
			$gapObject->fk_session = (int) $lockedControl->fk_session;
			$gapObject->fk_controle = (int) $lockedControl->rowid;
			$gapObject->fk_agence = (int) $lockedControl->fk_agence;
			$gapObject->fk_caisse = (int) $lockedControl->fk_caisse;
			$gapObject->gap_type = $gap > 0 ? 'surplus' : 'shortage';
			$gapObject->theoretical_amount = $theoretical;
			$gapObject->physical_amount = $physicalAmount;
			$gapObject->gap_amount = $gap;
			$gapObject->severity = $this->gapSeverity(abs($gap));
			$gapObject->fk_user_cashier = (int) $lockedControl->fk_user_cashier ?: null;
			$gapObject->status = 0;
			if ($gapObject->create($user) <= 0) {
				$this->db->rollback();
				return $this->fail($gapObject->error ?: "Échec d'enregistrement de l'écart.", $gapObject->errors);
			}
		}
		$restoreStatus = in_array((int) $lockedControl->previous_session_status, array(1, 2, 3), true) ? (int) $lockedControl->previous_session_status : 2;
		if ($this->updateRow('sof_caisse_controle', (int) $control->id, array(
			'date_end' => dol_now(), 'theoretical_amount' => $theoretical,
			'physical_amount' => $physicalAmount, 'gap_amount' => $gap,
			'observations' => trim((string) $observations), 'freeze_enabled' => 0, 'status' => 2,
		), $user, 1) < 0 || $this->updateRow('sof_caisse_session', (int) $control->fk_session, array('status' => $restoreStatus, 'freeze_status' => 0), $user, 4) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/** Resolve a cash gap with severity-aware segregation of duties and traceability. */
	public function resolveCashGap(User $user, $gapId, $reason, $decision)
	{
		global $conf;
		if (!$this->hasRight($user, 'ecart', 'manage')) {
			return $this->fail('Permission refusée pour traiter cet écart.');
		}
		$reason = trim((string) $reason);
		$decision = trim((string) $decision);
		if ($reason === '' || $decision === '') {
			return $this->fail('La justification et la décision de traitement sont obligatoires.');
		}

		$this->db->begin();
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_caisse_ecart WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $gapId).' FOR UPDATE';
		$resql = $this->db->query($sql);
		$gap = $resql ? $this->db->fetch_object($resql) : null;
		if (!$gap || (int) $gap->status >= 3) {
			$this->db->rollback();
			return $this->fail('Écart introuvable ou déjà traité.');
		}
		if (!$this->ensureAgencyScope($user, (int) $gap->fk_agence, 'cash_gap_resolve', abs((float) $gap->gap_amount))) {
			$this->db->rollback();
			return -1;
		}
		$severity = strtolower((string) $gap->severity);
		if (in_array($severity, array('major', 'critical'), true) && empty($user->admin)
			&& !getDolGlobalInt('AGENCE_ALLOW_SELF_APPROVAL') && (int) $gap->fk_user_cashier === (int) $user->id) {
			$this->db->rollback();
			return $this->fail('Un écart majeur ou critique doit être traité par un autre utilisateur que le caissier concerné.');
		}
		if ($severity === 'critical' && empty($user->admin)
			&& !$this->hasRight($user, 'session', 'validate') && !$this->hasRight($user, 'audit', 'read')) {
			$this->db->rollback();
			return $this->fail('Un écart critique exige également un droit de supervision ou d’audit.');
		}

		$updates = array(
			'reason' => $reason, 'treatment_decision' => $decision,
			'date_treatment' => dol_now(), 'fk_user_validator' => (int) $user->id, 'status' => 3,
		);
		if ($this->updateRow('sof_caisse_ecart', (int) $gapId, $updates, $user, (int) $gap->status) < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_alerte SET status = 2, date_close = CURRENT_TIMESTAMP,';
		$sql .= ' fk_user_close = '.((int) $user->id).', dedup_key = NULL WHERE entity = '.((int) $conf->entity);
		$sql .= " AND object_type = 'ecart' AND object_id = ".((int) $gapId).' AND status < 2';
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		SofAgenceService::logAudit($this->db, $user, 'SOF_CASH_GAP_RESOLVE', $gap, $this->snapshot($gap), $updates, $reason);
		$this->db->commit();
		return 1;
	}

	/** Execute a vault transfer as a debit from the source session. */
	public function executeTransfer(User $user, $transferId)
	{
		if (!$this->hasRight($user, 'transfert', 'create')) {
			return $this->fail('Permission refusée pour effectuer un transfert.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissetransfert.class.php';
		$transfer = new SofCaisseTransfert($this->db);
		if ($transfer->fetch((int) $transferId) <= 0 || (int) $transfer->status !== 0 || (float) $transfer->amount <= 0) {
			return $this->fail('Le transfert est invalide ou déjà exécuté.');
		}
		$session = $this->getOpenSessionForUser((int) $user->id, (int) $transfer->fk_caisse_source);
		if (!$session || (!empty($transfer->fk_session_source) && (int) $transfer->fk_session_source !== (int) $session->rowid)) {
			return $this->fail('Une session ouverte sur la caisse source est obligatoire.');
		}
		if (!empty($transfer->fk_caisse_dest) && (int) $transfer->fk_caisse_dest === (int) $transfer->fk_caisse_source) {
			return $this->fail('Les caisses source et destination doivent être différentes.');
		}
		if ($this->recalculateSession((int) $session->rowid) + 0.01 < (float) $transfer->amount) {
			return $this->fail('Le solde physique théorique est insuffisant pour ce transfert.');
		}
		$this->db->begin();
		if ($this->createMovement($user, array(
			'fk_agence' => (int) $session->fk_agence, 'fk_caisse' => (int) $session->fk_caisse,
			'fk_session' => (int) $session->rowid, 'fk_das' => (int) $session->fk_das,
			'type_operation' => 'vault_transfer', 'direction' => 'debit', 'payment_mode' => 'LIQ',
			'amount' => (float) $transfer->amount, 'source_type' => 'transfer', 'source_id' => (int) $transfer->id,
			'label' => 'Transfert '.$transfer->ref,
		), false) <= 0 || $this->updateRow('sof_caisse_transfert', (int) $transfer->id, array(
			'fk_session_source' => (int) $session->rowid, 'fk_user_sender' => (int) $user->id,
			'date_transfer' => dol_now(), 'status' => 1,
		), $user, 0) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->recalculateSession((int) $session->rowid);
		$this->db->commit();
		$this->emitIntegrationEvent('cash_transfer.sent', 'transfer', (int) $transfer->id, (int) $transfer->fk_agence, array(
			'ref' => $transfer->ref, 'stage' => 'sent', 'amount' => (float) $transfer->amount,
			'fk_caisse_source' => (int) $transfer->fk_caisse_source, 'fk_caisse_dest' => (int) $transfer->fk_caisse_dest,
			'subject' => 'Transfert de caisse expédié '.$transfer->ref,
		), $user);
		return 1;
	}

	/** Receive a sent vault transfer and credit the destination session when one is configured. */
	public function receiveTransfer(User $user, $transferId)
	{
		if (!$this->hasRight($user, 'transfert', 'create')) {
			return $this->fail('Permission refusée pour réceptionner un transfert.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissetransfert.class.php';
		$transfer = new SofCaisseTransfert($this->db);
		if ($transfer->fetch((int) $transferId) <= 0 || (int) $transfer->status !== 1) {
			return $this->fail('Le transfert doit avoir été expédié avant sa réception.');
		}
		$destinationSession = null;
		if (!empty($transfer->fk_caisse_dest)) {
			$destinationSession = $this->getOpenSessionForUser((int) $user->id, (int) $transfer->fk_caisse_dest);
			if (!$destinationSession) {
				return $this->fail('Une session ouverte sur la caisse destination est obligatoire.');
			}
		}

		$this->db->begin();
		if ($destinationSession && $this->createMovement($user, array(
			'fk_agence' => (int) $destinationSession->fk_agence, 'fk_caisse' => (int) $destinationSession->fk_caisse,
			'fk_session' => (int) $destinationSession->rowid, 'fk_das' => (int) $destinationSession->fk_das,
			'type_operation' => 'vault_transfer_received', 'direction' => 'credit', 'payment_mode' => 'LIQ',
			'amount' => (float) $transfer->amount, 'source_type' => 'transfer', 'source_id' => (int) $transfer->id,
			'label' => 'Réception transfert '.$transfer->ref,
		), false) <= 0) {
			$this->db->rollback();
			return -1;
		}
		if ($this->updateRow('sof_caisse_transfert', (int) $transfer->id, array(
			'fk_user_receiver' => (int) $user->id, 'date_receive' => dol_now(), 'status' => 2,
		), $user, 1) < 0) {
			$this->db->rollback();
			return -1;
		}
		if ($destinationSession) {
			$this->recalculateSession((int) $destinationSession->rowid);
		}
		$this->db->commit();
		$this->emitIntegrationEvent('cash_transfer.received', 'transfer', (int) $transfer->id, (int) $transfer->fk_agence, array(
			'ref' => $transfer->ref, 'stage' => 'received', 'amount' => (float) $transfer->amount,
			'fk_caisse_source' => (int) $transfer->fk_caisse_source, 'fk_caisse_dest' => (int) $transfer->fk_caisse_dest,
			'subject' => 'Transfert de caisse réceptionné '.$transfer->ref,
		), $user);
		return 1;
	}

	/** Execute a prepared cash deposit and debit both the operational and native cash ledgers. */
	public function executeDeposit(User $user, $depositId)
	{
		global $conf;
		if (!$this->hasRight($user, 'depotbanque', 'create')) {
			return $this->fail('Permission refusée pour exécuter un dépôt bancaire.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissedepotbanque.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofbanklink.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$deposit = new SofCaisseDepotBanque($this->db);
		if ($deposit->fetch((int) $depositId) <= 0 || (int) $deposit->status !== 0 || (float) $deposit->amount <= 0) {
			return $this->fail('Le dépôt est invalide ou a déjà été exécuté.');
		}
		if (!$this->ensureAgencyScope($user, (int) $deposit->fk_agence, 'deposit_execute', (float) $deposit->amount)) {
			return -1;
		}
		if (empty($deposit->fk_caisse_source) || empty($deposit->fk_bank_account)) {
			return $this->fail('La caisse source et le compte bancaire destinataire sont obligatoires.');
		}
		$session = $this->getOpenSessionForUser((int) $user->id, (int) $deposit->fk_caisse_source);
		if (!$session || (!empty($deposit->fk_session) && (int) $deposit->fk_session !== (int) $session->rowid)) {
			return $this->fail('Une session ouverte sur la caisse source est obligatoire.');
		}
		$caisse = new SofCaisse($this->db);
		if ($caisse->fetch((int) $deposit->fk_caisse_source) <= 0 || empty($caisse->fk_bank_account)) {
			return $this->fail('La caisse source doit être reliée à son compte espèces Dolibarr.');
		}
		if ((int) $caisse->fk_bank_account === (int) $deposit->fk_bank_account) {
			return $this->fail('Le compte destinataire doit être différent du compte espèces source.');
		}
		if ($this->recalculateSession((int) $session->rowid) + 0.01 < (float) $deposit->amount) {
			return $this->fail('Le solde physique théorique est insuffisant pour ce dépôt.');
		}
		$sourceAccount = new Account($this->db);
		$destinationAccount = new Account($this->db);
		if ($sourceAccount->fetch((int) $caisse->fk_bank_account) <= 0 || $destinationAccount->fetch((int) $deposit->fk_bank_account) <= 0 || !empty($destinationAccount->clos)) {
			return $this->fail('Un des comptes financiers du dépôt est absent ou clôturé.');
		}

		$this->db->begin();
		$sourceBankLineId = $sourceAccount->addline(dol_now(), 'LIQ', 'Dépôt bancaire '.$deposit->ref, -abs((float) $deposit->amount), '', 0, $user);
		if ($sourceBankLineId <= 0) {
			$this->db->rollback();
			return $this->fail($sourceAccount->error ?: 'Échec de la sortie du compte espèces.', $sourceAccount->errors);
		}
		$bankLink = new SofBankLink($this->db);
		$bankLink->entity = (int) $conf->entity;
		$bankLink->fk_bank = (int) $sourceBankLineId;
		$bankLink->fk_bank_account = (int) $caisse->fk_bank_account;
		$bankLink->fk_agence = (int) $session->fk_agence;
		$bankLink->fk_caisse = (int) $session->fk_caisse;
		$bankLink->fk_session = (int) $session->rowid;
		$bankLink->fk_depot_banque = (int) $deposit->id;
		$bankLink->operation_type = 'deposit_source';
		$bankLink->reconcile_status = 0;
		$bankLink->accounting_status = 0;
		if ($bankLink->create($user) <= 0 || $this->createMovement($user, array(
			'fk_agence' => (int) $session->fk_agence, 'fk_caisse' => (int) $session->fk_caisse,
			'fk_session' => (int) $session->rowid, 'fk_das' => (int) $session->fk_das,
			'fk_bank' => (int) $sourceBankLineId, 'type_operation' => 'bank_deposit',
			'direction' => 'debit', 'payment_mode' => 'LIQ', 'amount' => (float) $deposit->amount,
			'source_type' => 'bank_deposit', 'source_id' => (int) $deposit->id, 'label' => 'Dépôt bancaire '.$deposit->ref,
		), false) <= 0 || $this->updateRow('sof_caisse_depot_banque', (int) $deposit->id, array(
			'fk_session' => (int) $session->rowid, 'date_deposit' => dol_now(),
			'fk_user_depositor' => (int) $user->id, 'status' => 1,
		), $user, 0) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->recalculateSession((int) $session->rowid);
		$this->db->commit();
		return 1;
	}

	/** Reconcile a prepared bank deposit against a real Dolibarr bank line. */
	public function reconcileDeposit(User $user, $depositId, $bankLineId, $reference = '')
	{
		if (!$this->hasRight($user, 'depotbanque', 'reconcile')) {
			return $this->fail('Permission refusée pour rapprocher un dépôt.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissedepotbanque.class.php';
		$deposit = new SofCaisseDepotBanque($this->db);
		if ($deposit->fetch((int) $depositId) <= 0 || !in_array((int) $deposit->status, array(1, 2), true)) {
			return $this->fail('Le dépôt doit avoir été exécuté avant son rapprochement.');
		}
		if (!$this->ensureAgencyScope($user, (int) $deposit->fk_agence, 'deposit_reconcile', (float) $deposit->amount)) {
			return -1;
		}
		$sql = 'SELECT rowid, amount, fk_account FROM '.$this->db->prefix().'bank WHERE rowid = '.((int) $bankLineId);
		$resql = $this->db->query($sql);
		$bankLine = $resql ? $this->db->fetch_object($resql) : null;
		if (!$bankLine || (float) $bankLine->amount <= 0 || abs((float) $bankLine->amount - abs((float) $deposit->amount)) > 0.01) {
			return $this->fail('La ligne bancaire est absente ou son montant ne correspond pas au dépôt.');
		}
		if (!empty($deposit->fk_bank_account) && (int) $deposit->fk_bank_account !== (int) $bankLine->fk_account) {
			return $this->fail('La ligne bancaire appartient à un autre compte.');
		}
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_bank_link WHERE fk_bank = '.((int) $bankLineId).' AND fk_depot_banque <> '.((int) $deposit->id);
		$resql = $this->db->query($sql);
		if ($resql && ($duplicate = $this->db->fetch_object($resql)) && (int) $duplicate->nb > 0) {
			return $this->fail('Cette ligne bancaire est déjà rattachée à un autre dépôt.');
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofbanklink.class.php';
		global $conf;
		$link = new SofBankLink($this->db);
		$link->entity = (int) $conf->entity;
		$link->fk_bank = (int) $bankLineId;
		$link->fk_bank_account = (int) $bankLine->fk_account;
		$link->fk_agence = (int) $deposit->fk_agence;
		$link->fk_caisse = (int) $deposit->fk_caisse_source;
		$link->fk_session = (int) $deposit->fk_session;
		$link->fk_depot_banque = (int) $deposit->id;
		$link->operation_type = 'deposit_destination';
		$link->reconcile_status = 1;
		$link->accounting_status = 0;
		$this->db->begin();
		$linkResult = $link->create($user);
		$sql = 'UPDATE '.$this->db->prefix().'sof_bank_link SET reconcile_status = 1, fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP';
		$sql .= ' WHERE fk_depot_banque = '.((int) $deposit->id)." AND operation_type = 'deposit_source'";
		$sourceLinkResult = $this->db->query($sql);
		if ($linkResult <= 0 || !$sourceLinkResult || $this->updateRow('sof_caisse_depot_banque', (int) $deposit->id, array(
			'fk_bank' => (int) $bankLineId, 'date_reconcile' => dol_now(),
			'fk_user_validator' => (int) $user->id, 'reconcile_reference' => trim((string) $reference), 'status' => 3,
		), $user, (int) $deposit->status) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		$this->emitIntegrationEvent('bank_deposit.completed', 'bank_deposit', (int) $deposit->id, (int) $deposit->fk_agence, array(
			'ref' => $deposit->ref, 'stage' => 'reconciled', 'amount' => (float) $deposit->amount,
			'fk_caisse_source' => (int) $deposit->fk_caisse_source, 'fk_bank_account' => (int) $bankLine->fk_account,
			'fk_bank' => (int) $bankLineId, 'reconcile_reference' => trim((string) $reference),
			'subject' => 'Dépôt bancaire rapproché '.$deposit->ref,
		), $user);
		return 1;
	}

	/** Validate one operational source document through its configured approval chain. */
	public function validateSupportingDocument(User $user, $objectType, $objectId, $reason = '')
	{
		global $conf;
		$map = array(
			'boncommande' => array('right' => 'boncommande', 'table' => 'sof_bon_commande_client', 'workflow' => 'customer_po'),
			'bst' => array('right' => 'bst', 'table' => 'sof_bst', 'workflow' => 'bst'),
			'instruction' => array('right' => 'instruction', 'table' => 'sof_instruction_manageriale', 'workflow' => 'manager_instruction'),
		);
		if (empty($map[$objectType]) || !$this->hasRight($user, $map[$objectType]['right'], 'validate')) {
			return $this->fail('Permission refusée pour cette validation.');
		}
		$config = $map[$objectType];
		$sql = 'SELECT * FROM '.$this->db->prefix().$config['table'].' WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $objectId);
		$resql = $this->db->query($sql);
		$record = $resql ? $this->db->fetch_object($resql) : null;
		if (!$record || (int) $record->status !== 0) {
			return $this->fail('Le document doit être au statut brouillon ou en attente.');
		}
		if (!$this->ensureAgencyScope($user, (int) $record->fk_agence, $config['workflow'].'_validate', 0, !empty($record->fk_das) ? (int) $record->fk_das : 0)) {
			return -1;
		}
		if (empty($user->admin) && !getDolGlobalInt('AGENCE_ALLOW_SELF_APPROVAL') && (int) $record->fk_user_creat === (int) $user->id) {
			return $this->fail('Le créateur ne peut pas valider son propre document.');
		}
		$amount = 0;
		foreach (array('amount', 'total_amount', 'authorized_amount', 'final_amount', 'estimated_amount') as $amountField) {
			if (isset($record->$amountField) && (float) $record->$amountField > 0) {
				$amount = (float) $record->$amountField;
				break;
			}
		}
		$this->db->begin();
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse_validation WHERE entity = '.((int) $conf->entity);
		$sql .= " AND object_type = '".$this->db->escape($config['workflow'])."' AND object_id = ".((int) $objectId);
		$check = $this->db->query($sql);
		$validationCount = $check && ($countRow = $this->db->fetch_object($check)) ? (int) $countRow->nb : 0;
		if ($validationCount === 0) {
			$this->createValidationChain($user, $config['workflow'], (int) $objectId, $amount, (int) $record->fk_agence, !empty($record->fk_das) ? (int) $record->fk_das : 0, '');
		}
		if ($this->approveObject($user, $config['workflow'], (int) $objectId, 'approve', $reason) < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'SELECT COUNT(*) nb FROM '.$this->db->prefix().'sof_caisse_validation WHERE entity = '.((int) $conf->entity);
		$sql .= " AND object_type = '".$this->db->escape($config['workflow'])."' AND object_id = ".((int) $objectId).' AND status = 0';
		$pendingResult = $this->db->query($sql);
		$pending = $pendingResult && ($pendingRow = $this->db->fetch_object($pendingResult)) ? (int) $pendingRow->nb : 0;
		$updates = array('status' => 1);
		if ($objectType === 'instruction') {
			$updates['fk_user_final_validator'] = (int) $user->id;
		}
		$result = $pending === 0 ? $this->updateRow($config['table'], (int) $objectId, $updates, $user, 0) : 1;
		$result < 0 ? $this->db->rollback() : $this->db->commit();
		if ($result > 0) {
			$this->emitIntegrationEvent('validation.decided', $config['workflow'], (int) $objectId, (int) $record->fk_agence, array(
				'ref' => !empty($record->ref) ? $record->ref : $config['workflow'].'-'.$objectId, 'decision' => 'approve', 'final' => $pending === 0,
				'subject' => 'Validation '.$config['workflow'].' #'.$objectId,
			), $user);
		}
		return $result;
	}

	/** Reject an operational source document and cancel its remaining approval steps. */
	public function rejectSupportingDocument(User $user, $objectType, $objectId, $reason)
	{
		global $conf;
		$map = array(
			'customer_po' => array('right' => 'boncommande', 'table' => 'sof_bon_commande_client', 'status' => 5),
			'bst' => array('right' => 'bst', 'table' => 'sof_bst', 'status' => 9),
			'manager_instruction' => array('right' => 'instruction', 'table' => 'sof_instruction_manageriale', 'status' => 5),
		);
		$reason = trim((string) $reason);
		if (empty($map[$objectType]) || !$this->hasRight($user, $map[$objectType]['right'], 'validate') || $reason === '') {
			return $this->fail('Permission refusée ou motif de rejet absent.');
		}
		$config = $map[$objectType];
		$sql = 'SELECT ref, status, fk_agence, fk_das FROM '.$this->db->prefix().$config['table'].' WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $objectId);
		$resql = $this->db->query($sql);
		$record = $resql ? $this->db->fetch_object($resql) : null;
		if (!$record || (int) $record->status !== 0) {
			return $this->fail('Le document ne peut plus être rejeté depuis son statut actuel.');
		}
		if (!$this->ensureAgencyScope($user, (int) $record->fk_agence, $objectType.'_reject', 0, !empty($record->fk_das) ? (int) $record->fk_das : 0)) {
			return -1;
		}
		$this->db->begin();
		if ($this->approveObject($user, $objectType, (int) $objectId, 'reject', $reason) < 0) {
			$this->db->rollback();
			return -1;
		}
		$sql = 'UPDATE '.$this->db->prefix().'sof_caisse_validation SET status = 2, decision = \'canceled\', decision_reason = \''.$this->db->escape($reason).'\', date_decision = CURRENT_TIMESTAMP';
		$sql .= ' WHERE entity = '.((int) $conf->entity)." AND object_type = '".$this->db->escape($objectType)."' AND object_id = ".((int) $objectId).' AND status = 0';
		$cancelResult = $this->db->query($sql);
		$result = $cancelResult ? $this->updateRow($config['table'], (int) $objectId, array('status' => (int) $config['status']), $user, 0) : -1;
		$result < 0 ? $this->db->rollback() : $this->db->commit();
		if ($result > 0) {
			$this->emitIntegrationEvent('validation.decided', $objectType, (int) $objectId, (int) $record->fk_agence, array(
				'ref' => $record->ref, 'decision' => 'reject', 'reason' => $reason, 'final' => true,
				'subject' => 'Validation rejetée '.$record->ref,
			), $user);
		}
		return $result;
	}

	/** Resolve a visible pending validation step through the correct business workflow. */
	public function decideValidationStep(User $user, $stepId, $decision, $reason = '')
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		$decision = strtolower((string) $decision) === 'reject' ? 'reject' : 'approve';
		$reason = trim((string) $reason);
		$sql = 'SELECT v.* FROM '.$this->db->prefix().'sof_caisse_validation v WHERE v.entity = '.((int) $conf->entity);
		$sql .= ' AND v.rowid = '.((int) $stepId).' AND v.status = 0';
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.$this->db->prefix().'sof_caisse_validation p WHERE p.entity=v.entity';
		$sql .= ' AND p.object_type=v.object_type AND p.object_id=v.object_id AND p.status=0 AND p.validation_level < v.validation_level)';
		$resql = $this->db->query($sql);
		$step = $resql ? $this->db->fetch_object($resql) : null;
		if (!$step) {
			return $this->fail('Cette étape n’est plus disponible ou une étape antérieure reste en attente.');
		}
		if (empty($user->admin) && !SofAgenceService::userCanAccessValidation($this->db, $user, $step->object_type, (int) $step->object_id)) {
			return $this->fail("Cette validation n'appartient pas au périmètre agence de l'utilisateur.");
		}
		if ($decision === 'reject' && $reason === '') {
			return $this->fail('Un motif est obligatoire pour rejeter.');
		}
		if ($step->object_type === 'refund') {
			return $decision === 'approve'
				? $this->validateRefund($user, (int) $step->object_id, 0, $reason)
				: $this->rejectRefund($user, (int) $step->object_id, $reason);
		}
		if ($step->object_type === 'session') {
			if ($decision === 'approve') {
				return $this->transitionSession($user, (int) $step->object_id, 'validate', array('reason' => $reason));
			}
			$this->db->begin();
			if ($this->approveObject($user, 'session', (int) $step->object_id, 'reject', $reason) < 0) {
				$this->db->rollback();
				return -1;
			}
			$sql = 'UPDATE '.$this->db->prefix()."sof_caisse_validation SET status=2, decision='canceled', date_decision=CURRENT_TIMESTAMP";
			$sql .= ' WHERE entity='.((int) $conf->entity)." AND object_type='session' AND object_id=".((int) $step->object_id).' AND status=0';
			if (!$this->db->query($sql) || $this->transitionSession($user, (int) $step->object_id, 'reopen', array('reason' => $reason)) < 0) {
				$this->db->rollback();
				return -1;
			}
			$this->db->commit();
			$sessionAgency = SofAgenceService::validationAgencyId($this->db, 'session', (int) $step->object_id);
			$this->emitIntegrationEvent('validation.decided', 'session', (int) $step->object_id, (int) $sessionAgency, array(
				'ref' => 'SESSION-'.((int) $step->object_id), 'decision' => 'reject', 'reason' => $reason, 'final' => true,
				'subject' => 'Clôture rejetée #'.((int) $step->object_id),
			), $user);
			return 1;
		}
		$supportingMap = array('customer_po' => 'boncommande', 'bst' => 'bst', 'manager_instruction' => 'instruction');
		if (isset($supportingMap[$step->object_type])) {
			return $decision === 'approve'
				? $this->validateSupportingDocument($user, $supportingMap[$step->object_type], (int) $step->object_id, $reason)
				: $this->rejectSupportingDocument($user, $step->object_type, (int) $step->object_id, $reason);
		}
		return $this->fail('Type de validation non pris en charge.');
	}

	/** Apply controlled state changes to a deferred receivable. */
	public function transitionDeferredPayment(User $user, $recordId, $action, $reason = '')
	{
		global $conf;
		if (!$this->hasRight($user, 'paiementdiffere', 'validate')) {
			return $this->fail('Permission refusée pour gérer le paiement différé.');
		}
		$action = strtolower((string) $action);
		$reason = trim((string) $reason);
		$this->db->begin();
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_paiement_differe WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $recordId).' FOR UPDATE';
		$resql = $this->db->query($sql);
		$record = $resql ? $this->db->fetch_object($resql) : null;
		if (!$record) {
			$this->db->rollback();
			return $this->fail('Paiement différé introuvable.');
		}
		if (!$this->ensureAgencyScope($user, (int) $record->fk_agence, 'deferred_payment_'.$action, (float) $record->remaining_amount, (int) $record->fk_das)) {
			$this->db->rollback();
			return -1;
		}
		$old = clone $record;
		$updates = array();
		if ($action === 'validate' && (int) $record->status === 0) {
			if ((float) $record->expected_amount <= 0) {
				$this->db->rollback();
				return $this->fail('Le montant attendu doit être strictement positif.');
			}
			$updates = array('status' => 1, 'date_validation' => dol_now(), 'fk_user_validator' => (int) $user->id);
		} elseif ($action === 'dispute' && in_array((int) $record->status, array(1, 2, 3, 5), true) && $reason !== '') {
			$updates = array('dispute_reason' => $reason, 'date_dispute' => dol_now(), 'fk_user_dispute' => (int) $user->id, 'status' => 6);
		} elseif ($action === 'regularize' && (int) $record->status === 6 && $reason !== '') {
			$balance = $this->deferredPaymentBalance($record);
			$updates = array(
				'paid_amount' => $balance['paid'], 'remaining_amount' => $balance['remaining'], 'status' => $balance['status'],
				'regularization_reason' => $reason, 'date_regularization' => dol_now(), 'fk_user_regularization' => (int) $user->id,
			);
		} elseif ($action === 'close' && (int) $record->status === 4 && $reason !== '') {
			$balance = $this->deferredPaymentBalance($record);
			if ($balance['remaining'] > 0.01) {
				$this->db->rollback();
				return $this->fail('Le paiement différé ne peut être clôturé tant que son solde n’est pas nul.');
			}
			$updates = array(
				'paid_amount' => $balance['paid'], 'remaining_amount' => 0,
				'closure_reason' => $reason, 'date_closure' => dol_now(), 'fk_user_closure' => (int) $user->id, 'status' => 7,
			);
		} else {
			$this->db->rollback();
			return $this->fail('Transition interdite depuis le statut actuel ou motif obligatoire absent.');
		}

		$result = $this->updateRow('sof_paiement_differe', (int) $recordId, $updates, $user, (int) $record->status);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		$record->rowid = (int) $recordId;
		foreach ($updates as $field => $value) {
			$record->$field = $value;
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		SofAgenceService::logAudit($this->db, $user, 'SOF_DEFERRED_'.strtoupper($action), $record, $this->snapshot($old), $this->snapshot($record), $reason);
		$this->db->commit();
		if ($action === 'validate') {
			$this->emitIntegrationEvent('validation.decided', 'deferred_payment', (int) $recordId, (int) $record->fk_agence, array(
				'ref' => $record->ref, 'decision' => 'approve', 'final' => true, 'amount' => (float) $record->expected_amount,
				'subject' => 'Paiement différé validé '.$record->ref,
			), $user);
		}
		return 1;
	}

	/** Compute the authoritative deferred balance without mutating a disputed row. */
	private function deferredPaymentBalance($record)
	{
		global $conf;
		$remaining = max(0, (float) $record->expected_amount);
		if (!empty($record->fk_facture)) {
			$sql = 'SELECT total_ttc FROM '.$this->db->prefix().'facture WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $record->fk_facture);
			$resql = $this->db->query($sql);
			$invoice = $resql ? $this->db->fetch_object($resql) : null;
			if ($invoice) {
				$remaining = min($remaining, max(0, $this->invoiceRemaining((int) $record->fk_facture, (float) $invoice->total_ttc)));
			}
		}
		$paid = max(0, price2num((float) $record->expected_amount - $remaining));
		$status = $remaining <= 0.01 ? 4 : ($paid > 0 ? 3 : 1);
		if ($status !== 4 && !empty($record->expected_payment_date) && $this->db->jdate($record->expected_payment_date) < dol_now()) {
			$status = 5;
		}
		return array('paid' => $paid, 'remaining' => price2num($remaining), 'status' => $status);
	}

	/** Validate a credit-note balance tracked by Agence. */
	public function validateCreditTracking(User $user, $trackingId)
	{
		global $conf;
		if (!$this->hasRight($user, 'avoir', 'validate')) {
			return $this->fail("Permission refusée pour valider l'avoir.");
		}
		$this->db->begin();
		$sql = 'SELECT a.*, f.type facture_type, f.fk_statut facture_status, f.total_ttc facture_total, f.fk_soc facture_soc FROM '.$this->db->prefix().'sof_avoir_tracking a';
		$sql .= ' JOIN '.$this->db->prefix().'facture f ON f.rowid = a.fk_facture_avoir';
		$sql .= ' WHERE a.entity = '.((int) $conf->entity).' AND f.entity = '.((int) $conf->entity).' AND a.rowid = '.((int) $trackingId).' FOR UPDATE';
		$resql = $this->db->query($sql);
		$tracking = $resql ? $this->db->fetch_object($resql) : null;
		if (!$tracking || !empty($tracking->validation_status) || (int) $tracking->facture_type !== 2
			|| !in_array((int) $tracking->facture_status, array(1, 2), true) || (float) $tracking->initial_amount <= 0
			|| (int) $tracking->fk_soc !== (int) $tracking->facture_soc
			|| (float) $tracking->initial_amount > abs((float) $tracking->facture_total) + 0.01) {
			$this->db->rollback();
			return $this->fail("Le suivi doit référencer un avoir Dolibarr valide et non encore validé.");
		}
		if (!$this->ensureAgencyScope($user, (int) $tracking->fk_agence, 'credit_validate', (float) $tracking->initial_amount, (int) $tracking->fk_das)) {
			$this->db->rollback();
			return -1;
		}
		$used = max(0, (float) $tracking->used_amount);
		if ($used > (float) $tracking->initial_amount + 0.01) {
			$this->db->rollback();
			return $this->fail("Le montant déjà consommé dépasse le montant initial de l'avoir.");
		}
		$updates = array(
			'used_amount' => $used, 'remaining_amount' => (float) $tracking->initial_amount - $used,
			'validation_status' => 1, 'date_validation' => dol_now(), 'fk_user_validator' => (int) $user->id,
			'use_status' => $used > 0 ? 1 : 0, 'status' => 1,
		);
		$result = $this->updateRow('sof_avoir_tracking', (int) $trackingId, $updates, $user, (int) $tracking->status);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		SofAgenceService::logAudit($this->db, $user, 'SOF_CREDIT_VALIDATE', $tracking, $this->snapshot($tracking), $updates);
		$this->db->commit();
		$this->emitIntegrationEvent('validation.decided', 'credit_note', (int) $trackingId, (int) $tracking->fk_agence, array(
			'ref' => $tracking->ref, 'decision' => 'approve', 'final' => true, 'amount' => (float) $tracking->initial_amount,
			'subject' => 'Avoir validé '.$tracking->ref,
		), $user);
		return 1;
	}

	/** Consume an incremental amount from a validated credit-note balance. */
	public function consumeCreditTracking(User $user, $trackingId, $amount)
	{
		global $conf;
		if (!$this->hasRight($user, 'avoir', 'use')) {
			return $this->fail("Permission refusée pour utiliser l'avoir.");
		}
		$amount = price2num($amount);
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_avoir_tracking WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $trackingId).' FOR UPDATE';
		$this->db->begin();
		$resql = $this->db->query($sql);
		$tracking = $resql ? $this->db->fetch_object($resql) : null;
		if (!$tracking || empty($tracking->validation_status) || (int) $tracking->status !== 1 || $amount <= 0 || $amount > (float) $tracking->remaining_amount + 0.01) {
			$this->db->rollback();
			return $this->fail("Montant invalide ou avoir indisponible.");
		}
		if (!$this->ensureAgencyScope($user, (int) $tracking->fk_agence, 'credit_use', $amount, (int) $tracking->fk_das)) {
			$this->db->rollback();
			return -1;
		}
		if (!empty($tracking->expiration_date) && $tracking->expiration_date < dol_print_date(dol_now(), '%Y-%m-%d')) {
			$this->db->rollback();
			return $this->fail("L'avoir est expiré.");
		}
		$newUsed = price2num((float) $tracking->used_amount + $amount);
		$newRemaining = max(0, price2num((float) $tracking->initial_amount - $newUsed));
		$result = $this->updateRow('sof_avoir_tracking', (int) $trackingId, array(
			'used_amount' => $newUsed, 'remaining_amount' => $newRemaining,
			'date_last_use' => dol_now(), 'fk_user_last_use' => (int) $user->id,
			'use_status' => $newRemaining <= 0.01 ? 2 : 1, 'status' => $newRemaining <= 0.01 ? 2 : 1,
		), $user, (int) $tracking->status);
		if ($result > 0) {
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
			SofAgenceService::logAudit($this->db, $user, 'SOF_CREDIT_USE', $tracking, $this->snapshot($tracking), array('amount' => $amount, 'used_amount' => $newUsed, 'remaining_amount' => $newRemaining));
		}
		$result < 0 ? $this->db->rollback() : $this->db->commit();
		return $result;
	}

	/** Synchronize deferred-payment balances from native invoice payments. */
	public function synchronizeDeferredPayments(User $user = null)
	{
		global $conf;
		$sql = 'SELECT * FROM '.$this->db->prefix().'sof_paiement_differe WHERE entity = '.((int) $conf->entity).' AND status IN (1,2,3,5)';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->fail($this->db->lasterror());
		}
		$count = 0;
		while ($record = $this->db->fetch_object($resql)) {
			$balance = $this->deferredPaymentBalance($record);
			$actor = $user instanceof User ? $user : $GLOBALS['user'];
			if ($this->updateRow('sof_paiement_differe', (int) $record->rowid, array(
				'paid_amount' => $balance['paid'], 'remaining_amount' => $balance['remaining'], 'status' => $balance['status'],
			), $actor) > 0) {
				$count++;
			}
		}
		return $count;
	}

	private function invoicePaid($invoiceId)
	{
		$sql = 'SELECT COALESCE(SUM(amount),0) paid FROM '.$this->db->prefix().'paiement_facture WHERE fk_facture = '.((int) $invoiceId);
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		return $obj ? (float) $obj->paid : 0;
	}

	private function invoiceAgencyContext($invoiceId)
	{
		$context = array('fk_agence' => 0, 'fk_caisse' => 0, 'fk_session' => 0, 'fk_das' => 0);
		$sql = 'SELECT fk_agence, fk_caisse, fk_session, fk_das FROM '.$this->db->prefix().'sof_facture_link WHERE fk_facture = '.((int) $invoiceId).' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		$obj = $resql ? $this->db->fetch_object($resql) : null;
		if (!$obj) {
			$sql = 'SELECT fk_agence, fk_caisse, fk_session, fk_das FROM '.$this->db->prefix().'sof_takepos_link';
			$sql .= ' WHERE fk_facture = '.((int) $invoiceId).' ORDER BY rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$obj = $resql ? $this->db->fetch_object($resql) : null;
		}
		if ($obj) {
			foreach ($context as $key => $value) {
				$context[$key] = (int) $obj->$key;
			}
		}
		return $context;
	}

	private function createCreditNote(User $user, $refund, $amount)
	{
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$source = new Facture($this->db);
		if ($source->fetch((int) $refund->fk_facture_origin) <= 0) {
			return $this->fail("Facture d'origine introuvable.");
		}
		$credit = new Facture($this->db);
		$credit->socid = (int) $refund->fk_soc;
		$credit->type = Facture::TYPE_CREDIT_NOTE;
		$credit->fk_facture_source = (int) $source->id;
		$credit->date = dol_now();
		$credit->cond_reglement_id = (int) $source->cond_reglement_id;
		$credit->mode_reglement_id = (int) $source->mode_reglement_id;
		$credit->module_source = 'agence';
		$credit->note_private = 'Avoir généré par le remboursement '.$refund->ref.' - '.$refund->reason;
		$id = $credit->create($user);
		if ($id <= 0) {
			return $this->fail($credit->error ?: "Échec de création de l'avoir Dolibarr.", $credit->errors);
		}
		$tva = 0.0;
		if (!empty($source->lines[0])) {
			$tva = (float) $source->lines[0]->tva_tx;
		}
		$unitHt = $tva > 0 ? price2num($amount / (1 + $tva / 100)) : $amount;
		if ($credit->addline('Remboursement '.$refund->ref.' - '.$refund->reason, $unitHt, 1, $tva, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) <= 0) {
			return $this->fail($credit->error ?: "Échec de création de la ligne d'avoir.", $credit->errors);
		}
		$result = method_exists($credit, 'validate') ? $credit->validate($user) : $credit->setValidated($user);
		if ($result <= 0) {
			return $this->fail($credit->error ?: "Échec de validation de l'avoir.", $credit->errors);
		}
		return (int) $id;
	}

	private function createCreditTracking(User $user, $refund, $creditNoteId, $amount)
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofavoirtracking.class.php';
		$tracking = new SofAvoirTracking($this->db);
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_avoir_tracking WHERE entity='.(int) $conf->entity.' AND fk_facture_avoir='.(int) $creditNoteId.' LIMIT 1';
		$resql = $this->db->query($sql);
		$existing = $resql ? $this->db->fetch_object($resql) : null;
		if ($existing && $tracking->fetch((int) $existing->rowid) <= 0) {
			return $this->fail("Échec de lecture du suivi d'avoir automatiquement créé.");
		}
		$tracking->entity = (int) $conf->entity;
		if (!$existing) {
			$tracking->ref = $this->generateRef('AVO', 'sof_avoir_tracking');
		}
		$tracking->fk_facture_avoir = (int) $creditNoteId;
		$tracking->fk_facture_origin = (int) $refund->fk_facture_origin;
		$tracking->fk_soc = (int) $refund->fk_soc;
		$tracking->fk_agence = (int) $refund->fk_agence ?: null;
		$tracking->fk_session = (int) $refund->fk_session ?: null;
		$tracking->fk_das = (int) $refund->fk_das ?: null;
		$tracking->initial_amount = $amount;
		$tracking->used_amount = strtoupper((string) $refund->payment_mode) === 'AVOIR' ? 0 : $amount;
		$tracking->remaining_amount = strtoupper((string) $refund->payment_mode) === 'AVOIR' ? $amount : 0;
		$tracking->reason = $refund->reason;
		$tracking->validation_status = 1;
		$tracking->date_validation = dol_now();
		$tracking->fk_user_validator = (int) $user->id;
		$tracking->use_status = strtoupper((string) $refund->payment_mode) === 'AVOIR' ? 0 : 2;
		$tracking->date_last_use = strtoupper((string) $refund->payment_mode) === 'AVOIR' ? null : dol_now();
		$tracking->fk_user_last_use = strtoupper((string) $refund->payment_mode) === 'AVOIR' ? null : (int) $user->id;
		$tracking->status = strtoupper((string) $refund->payment_mode) === 'AVOIR' ? 1 : 2;
		$id = $existing ? $tracking->update($user, 1) : $tracking->create($user);
		if ($existing && $id > 0) $id = (int) $existing->rowid;
		return $id > 0 ? $id : $this->fail($tracking->error ?: "Échec du suivi d'avoir.", $tracking->errors);
	}

	private function snapshot($object)
	{
		$data = array();
		if (!is_object($object)) {
			return $data;
		}
		$fields = !empty($object->fields) && is_array($object->fields) ? array_keys($object->fields) : array_keys(get_object_vars($object));
		foreach ($fields as $field) {
			if (isset($object->$field) && (is_scalar($object->$field) || $object->$field === null)) {
				$data[$field] = $object->$field;
			}
		}
		return $data;
	}

	private function hasRight(User $user, $object, $action)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		if (!SofAgenceService::isActiveUser($this->db, $user)) {
			return false;
		}
		if (!empty($user->admin)) {
			return true;
		}
		// Always authorize against a fresh database view so a revoked permission
		// takes effect on the next request, without clearing rights from other
		// Dolibarr modules on the caller's session user object.
		$authorizationUser = new User($this->db);
		if ($authorizationUser->fetch((int) $user->id) <= 0 || empty($authorizationUser->statut)) {
			return false;
		}
		$authorizationUser->loadRights('agence', 1);
		return (bool) $authorizationUser->hasRight('agence', $object, $action);
	}

	/** Enforce the agency/DAS perimeter at the business-service boundary. */
	private function ensureAgencyScope(User $user, $fkAgence, $operationType = '', $amount = 0.0, $fkDas = 0)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
		if (!SofAgenceService::isActiveUser($this->db, $user)) {
			$this->fail("Le compte utilisateur est désactivé ou n'est plus valide.");
			return false;
		}
		if (!empty($user->admin)) {
			return true;
		}
		if (!SofAgenceService::userCanAccessAgency($this->db, $user, (int) $fkAgence, (string) $operationType, (float) $amount, (int) $fkDas)) {
			$this->fail("L'opération demandée est hors du périmètre agence ou DAS de l'utilisateur.");
			return false;
		}
		return true;
	}

	private function sqlValue($value, $field = '')
	{
		if ($value === null || $value === '') {
			return 'NULL';
		}
		if (preg_match('/^date_|_date$/', $field) && is_numeric($value)) {
			return "'".$this->db->idate((int) $value)."'";
		}
		if (is_bool($value) || is_int($value) || is_float($value)) {
			return is_float($value) ? price2num($value) : (string) ((int) $value);
		}
		return "'".$this->db->escape((string) $value)."'";
	}

	/** Emit only after the financial transaction has committed; delivery remains asynchronous. */
	private function emitIntegrationEvent($eventCode, $objectType, $objectId, $fkAgence, array $data, User $actor)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';
		$service = new SofIntegrationService($this->db);
		$result = $service->emitBusinessEvent($eventCode, $objectType, (int) $objectId, (int) $fkAgence, $data, $actor);
		if ($result < 0) {
			dol_syslog(__METHOD__.' '.$service->error, LOG_WARNING);
		}
		return $result;
	}

	private function fail($message, $errors = array())
	{
		$this->error = (string) $message;
		$this->errors = is_array($errors) ? $errors : array((string) $errors);
		dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
		return -1;
	}
}
