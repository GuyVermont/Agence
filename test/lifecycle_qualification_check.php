<?php
/* Copyright (C) 2026 SOFITOUL */

if (PHP_SAPI !== 'cli') {
	die("CLI only\n");
}
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);

$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissecontrole.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisseecart.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofpaiementdiffere.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofavoirtracking.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofmappingcomptable.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofalerte.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceuser.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$errors = array();
function agence_lifecycle_assert($condition, $label, $detail = '')
{
	global $errors;
	echo ($condition ? '[OK] ' : '[KO] ').$label.(!$condition && $detail !== '' ? ' — '.$detail : '').PHP_EOL;
	if (!$condition) {
		$errors[] = $label;
	}
}
function agence_lifecycle_row($sql)
{
	global $db;
	$resql = $db->query($sql);
	return $resql ? $db->fetch_object($resql) : null;
}

$adminRow = agence_lifecycle_row('SELECT rowid FROM '.$db->prefix().'user WHERE admin = 1 AND statut = 1 ORDER BY rowid LIMIT 1');
if ($adminRow) {
	$user->fetch((int) $adminRow->rowid);
	$user->getrights('', 1);
}
agence_lifecycle_assert(!empty($user->id) && !empty($user->admin), 'administrateur de qualification disponible');

$token = date('YmdHis').mt_rand(100, 999);
$db->begin();

$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity;
$agency->ref = 'TEST-LIFE-AG-'.$token;
$agency->label = 'Agence lifecycle qualification';
$agency->country_code = 'CM';
$agency->status = 1;
$agencyId = $agency->create($user, 1);

$cashDesk = new SofCaisse($db);
$cashDesk->entity = (int) $conf->entity;
$cashDesk->fk_agence = (int) $agencyId;
$cashDesk->ref = 'TEST-LIFE-CAI-'.$token;
$cashDesk->label = 'Cash desk lifecycle qualification';
$cashDesk->caisse_type = 'cash';
$cashDesk->currency_code = 'XAF';
$cashDesk->physical_balance_ceiling = 1000000;
$cashDesk->cashin_ceiling = 1000000;
$bankAccount = agence_lifecycle_row('SELECT rowid FROM '.$db->prefix().'bank_account WHERE entity = '.((int) $conf->entity).' AND clos = 0 ORDER BY rowid LIMIT 1');
$cashDesk->fk_bank_account = $bankAccount ? (int) $bankAccount->rowid : null;
$cashDesk->status = 1;
$cashDeskId = $cashDesk->create($user, 1);

$engine = new SofAgenceOperations($db);
$sessionId = $engine->openSession($user, $cashDeskId, 1000, 'qualification');
agence_lifecycle_assert($agencyId > 0 && $cashDeskId > 0 && $sessionId > 0, 'socle agence, caisse et session créé', $engine->error);

$thirdParty = agence_lifecycle_row('SELECT rowid FROM '.$db->prefix().'societe WHERE entity = '.((int) $conf->entity).' AND client IN (1,2,3) ORDER BY rowid LIMIT 1');
$invoice = new Facture($db);
$invoice->socid = (int) $thirdParty->rowid;
$invoice->type = Facture::TYPE_STANDARD;
$invoice->date = dol_now();
$invoiceId = $invoice->create($user);
$invoiceLine = $invoiceId > 0 ? $invoice->addline('Deferred lifecycle qualification', 1000, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) : -1;
$invoiceValidation = $invoiceLine > 0 ? $invoice->validate($user) : -1;
agence_lifecycle_assert($invoiceValidation > 0, 'facture native support du paiement différé créée');

$deferred = new SofPaiementDiffere($db);
$deferred->entity = (int) $conf->entity;
$deferred->ref = 'TEST-LIFE-DIF-'.$token;
$deferred->fk_soc = (int) $thirdParty->rowid;
$deferred->fk_agence = (int) $agencyId;
$deferred->fk_caisse = (int) $cashDeskId;
$deferred->fk_session = (int) $sessionId;
$deferred->source_type = 'qualification';
$deferred->fk_facture = (int) $invoiceId;
$deferred->operation_date = dol_now();
$deferred->expected_amount = 1000;
$deferred->invoiced_amount = 1000;
$deferred->paid_amount = 0;
$deferred->remaining_amount = 1000;
$deferred->expected_payment_date = dol_print_date(dol_now() + 86400, '%Y-%m-%d');
$deferred->status = 0;
$deferredId = $deferred->create($user, 1);
$deferredValidate = $engine->transitionDeferredPayment($user, $deferredId, 'validate');
$deferredDispute = $engine->transitionDeferredPayment($user, $deferredId, 'dispute', 'Montant contesté pendant la qualification');
$capture = $engine->captureInvoicePayment($user, $sessionId, $invoiceId, array('LIQ' => 1000));
$sync = $engine->synchronizeDeferredPayments($user);
$disputedRow = agence_lifecycle_row('SELECT status FROM '.$db->prefix().'sof_paiement_differe WHERE rowid = '.((int) $deferredId));
$deferredRegularize = $engine->transitionDeferredPayment($user, $deferredId, 'regularize', 'Paiement natif contrôlé et litige levé');
$paidRow = agence_lifecycle_row('SELECT status, remaining_amount, date_regularization FROM '.$db->prefix().'sof_paiement_differe WHERE rowid = '.((int) $deferredId));
$deferredClose = $engine->transitionDeferredPayment($user, $deferredId, 'close', 'Solde nul et dossier archivé');
$closedRow = agence_lifecycle_row('SELECT status, date_closure FROM '.$db->prefix().'sof_paiement_differe WHERE rowid = '.((int) $deferredId));
agence_lifecycle_assert($deferredValidate > 0 && $deferredDispute > 0 && $capture > 0 && $disputedRow && (int) $disputedRow->status === 6, 'la synchronisation automatique préserve un litige actif', 'validate='.$deferredValidate.' dispute='.$deferredDispute.' capture='.$capture.' status='.($disputedRow ? $disputedRow->status : 'null').' error='.$engine->error);
agence_lifecycle_assert($deferredRegularize > 0 && $paidRow && (int) $paidRow->status === 4 && (float) $paidRow->remaining_amount <= 0.01 && !empty($paidRow->date_regularization), 'le litige payé est régularisé et tracé', $engine->error);
agence_lifecycle_assert($deferredClose > 0 && $closedRow && (int) $closedRow->status === 7 && !empty($closedRow->date_closure), 'le paiement différé soldé est clôturé et tracé', $engine->error);

$credit = new Facture($db);
$credit->socid = (int) $thirdParty->rowid;
$credit->type = Facture::TYPE_CREDIT_NOTE;
$credit->fk_facture_source = (int) $invoiceId;
$credit->date = dol_now();
$creditId = $credit->create($user);
$creditLine = $creditId > 0 ? $credit->addline('Credit lifecycle qualification', 1000, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) : -1;
$creditValidation = $creditLine > 0 ? $credit->validate($user) : -1;

$automaticTracking = agence_lifecycle_row('SELECT rowid,validation_status FROM '.$db->prefix().'sof_avoir_tracking WHERE entity='.(int) $conf->entity.' AND fk_facture_avoir='.(int) $creditId);
$trackingId = $automaticTracking ? (int) $automaticTracking->rowid : -1;
$trackingValidation = $automaticTracking && (int) $automaticTracking->validation_status === 1 ? 1 : $engine->validateCreditTracking($user, $trackingId);
$firstUse = $engine->consumeCreditTracking($user, $trackingId, 400);
$secondUse = $engine->consumeCreditTracking($user, $trackingId, 600);
$overUse = $engine->consumeCreditTracking($user, $trackingId, 1);
$trackingRow = agence_lifecycle_row('SELECT validation_status, use_status, status, remaining_amount, date_validation, date_last_use FROM '.$db->prefix().'sof_avoir_tracking WHERE rowid = '.((int) $trackingId));
agence_lifecycle_assert($creditValidation > 0 && $trackingValidation > 0 && $trackingRow && !empty($trackingRow->date_validation), 'l’avoir natif est validé après contrôles croisés', $engine->error);
agence_lifecycle_assert($firstUse > 0 && $secondUse > 0 && $overUse < 0 && $trackingRow && (int) $trackingRow->use_status === 2 && (float) $trackingRow->remaining_amount <= 0.01 && !empty($trackingRow->date_last_use), 'l’avoir est consommé sous verrou sans dépassement');

$control = new SofCaisseControle($db);
$control->entity = (int) $conf->entity;
$control->ref = 'TEST-LIFE-CTL-'.$token;
$control->fk_agence = (int) $agencyId;
$control->fk_caisse = (int) $cashDeskId;
$control->fk_session = (int) $sessionId;
$control->fk_user_cashier = (int) $user->id;
$control->fk_user_controller = (int) $user->id;
$control->trigger_type = 'surprise';
$control->date_start = dol_now();
$control->freeze_enabled = 0;
$control->status = 0;
$controlId = $control->create($user, 1);
$beforeControlRow = agence_lifecycle_row('SELECT status FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
$controlStart = $engine->startControl($user, $controlId);
$frozenRow = agence_lifecycle_row('SELECT status, freeze_status FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
$frozenMovement = $engine->createMovement($user, array(
	'fk_agence' => $agencyId, 'fk_caisse' => $cashDeskId, 'fk_session' => $sessionId,
	'type_operation' => 'manual_cash_in', 'direction' => 'credit', 'payment_mode' => 'LIQ', 'amount' => 1, 'label' => 'Must be rejected while frozen',
));
$theoretical = $engine->recalculateSession($sessionId);
$controlComplete = $engine->completeControl($user, $controlId, $theoretical, 'Comptage concordant');
$resumedRow = agence_lifecycle_row('SELECT status, freeze_status FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
agence_lifecycle_assert($controlStart > 0 && $frozenRow && (int) $frozenRow->status === 4 && (int) $frozenRow->freeze_status === 1 && $frozenMovement < 0, 'le contrôle inopiné gèle réellement toute écriture');
agence_lifecycle_assert($controlComplete > 0 && $beforeControlRow && $resumedRow && (int) $resumedRow->status === (int) $beforeControlRow->status && (int) $resumedRow->freeze_status === 0, 'la fin du contrôle restaure exactement l’état antérieur de la caisse', 'complete='.$controlComplete.' before='.($beforeControlRow ? $beforeControlRow->status : 'null').' status='.($resumedRow ? $resumedRow->status : 'null').' freeze='.($resumedRow ? $resumedRow->freeze_status : 'null').' error='.$engine->error);

$gap = new SofCaisseEcart($db);
$gap->entity = (int) $conf->entity;
$gap->ref = 'TEST-LIFE-ECA-'.$token;
$gap->fk_session = (int) $sessionId;
$gap->fk_agence = (int) $agencyId;
$gap->fk_caisse = (int) $cashDeskId;
$gap->gap_type = 'shortage';
$gap->theoretical_amount = 20000;
$gap->physical_amount = 0;
$gap->gap_amount = -20000;
$gap->severity = 'critical';
$gap->fk_user_cashier = (int) $user->id;
$gap->status = 0;
$gapId = $gap->create($user, 1);
$alert = new SofAlerte($db);
$alert->entity = (int) $conf->entity;
$alert->ref = 'TEST-LIFE-ALT-'.$token;
$alert->dedup_key = 'critical_cash_gap:ecart:'.$gapId;
$alert->alert_type = 'critical_cash_gap';
$alert->severity = 'critical';
$alert->fk_agence = (int) $agencyId;
$alert->fk_caisse = (int) $cashDeskId;
$alert->fk_session = (int) $sessionId;
$alert->object_type = 'ecart';
$alert->object_id = (int) $gapId;
$alert->message = 'Critical qualification gap';
$alert->date_alert = dol_now();
$alert->status = 0;
$alertId = $alert->create($user, 1);
$gapResolution = $engine->resolveCashGap($user, $gapId, 'Erreur de comptage documentée', 'Régularisation approuvée par la direction');
$gapRow = agence_lifecycle_row('SELECT status, date_treatment FROM '.$db->prefix().'sof_caisse_ecart WHERE rowid = '.((int) $gapId));
$alertRow = agence_lifecycle_row('SELECT status, dedup_key FROM '.$db->prefix().'sof_caisse_alerte WHERE rowid = '.((int) $alertId));
agence_lifecycle_assert($gapResolution > 0 && $gapRow && (int) $gapRow->status === 3 && !empty($gapRow->date_treatment) && $alertRow && (int) $alertRow->status === 2 && $alertRow->dedup_key === null, 'un écart critique est traité, audité et son alerte fermée');

$db->query('UPDATE '.$db->prefix().'sof_caisse_session SET status = 7, accounting_status = 0 WHERE rowid = '.((int) $sessionId));
$accountReject = $engine->transitionSession($user, $sessionId, 'account');
$rejectedAccounting = agence_lifecycle_row('SELECT status, accounting_status, accounting_attempts, accounting_error FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
agence_lifecycle_assert($accountReject < 0 && $rejectedAccounting && (int) $rejectedAccounting->status === 7 && (int) $rejectedAccounting->accounting_status === 2 && (int) $rejectedAccounting->accounting_attempts === 1 && $rejectedAccounting->accounting_error !== '', 'un mapping absent produit un rejet comptable persistant et relançable');

$accounts = agence_report_rows('SELECT account_number FROM '.$db->prefix().'accounting_account WHERE entity = '.((int) $conf->entity).' AND active = 1 ORDER BY LENGTH(account_number) DESC'.$db->plimit(2, 0));
$mapping = new SofMappingComptable($db);
$mapping->entity = (int) $conf->entity;
$mapping->code = 'TEST-LIFE-MAP-'.$token;
$mapping->operation_type = 'opening';
$mapping->fk_agence = (int) $agencyId;
$mapping->payment_mode = 'LIQ';
$mapping->journal_code = 'OD';
$mapping->account_debit = !empty($accounts[0]->account_number) ? $accounts[0]->account_number : '571';
$mapping->account_credit = !empty($accounts[1]->account_number) ? $accounts[1]->account_number : '471';
$mapping->status = 1;
$mappingId = $mapping->create($user, 1);
$db->query('INSERT INTO '.$db->prefix()."accounting_fiscalyear (label, date_start, date_end, statut, entity, datec, fk_user_author) VALUES ('Qualification ".$db->escape($token)."', '".date('Y-01-01')."', '".date('Y-12-31')."', 0, ".((int) $conf->entity).', CURRENT_TIMESTAMP, '.((int) $user->id).')');
unset($conf->cache['active_fiscal_period_cached']);
$accountRetry = $mappingId > 0 ? $engine->transitionSession($user, $sessionId, 'account') : -1;
$postedAccounting = agence_lifecycle_row('SELECT status, accounting_status, accounting_attempts, accounting_error, date_accounting FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
$bookLines = agence_lifecycle_row('SELECT COUNT(*) nb FROM '.$db->prefix()."accounting_bookkeeping WHERE doc_type = 'agence_cash' AND doc_ref LIKE 'MVT-%'");
agence_lifecycle_assert($accountRetry > 0 && $postedAccounting && (int) $postedAccounting->status === 8 && (int) $postedAccounting->accounting_status === 4 && (int) $postedAccounting->accounting_attempts === 2 && empty($postedAccounting->accounting_error) && !empty($postedAccounting->date_accounting), 'le rejet corrigé est repris puis déversé nominalement', $engine->error.' '.implode(' | ', $engine->errors));

$oldSession = new SofCaisseSession($db);
$oldSession->entity = (int) $conf->entity;
$oldSession->ref = 'TEST-LIFE-OLD-'.$token;
$oldSession->fk_agence = (int) $agencyId;
$oldSession->fk_caisse = (int) $cashDeskId;
$oldSession->fk_user_cashier = (int) $user->id;
$oldSession->session_type = 'qualification';
$oldSession->date_opening = dol_now() - 86400;
$oldSession->opening_amount = 0;
$oldSession->theoretical_amount = 0;
$oldSession->physical_amount = 0;
$oldSession->gap_amount = 0;
$oldSession->accounting_status = 0;
$oldSession->accounting_attempts = 0;
$oldSession->freeze_status = 0;
$oldSession->status = 1;
$oldSessionId = $oldSession->create($user, 1);
$detector = new SofAlerte($db);
$firstDetection = $detector->detectAlerts();
$secondDetection = $detector->detectAlerts();
$dedupCount = agence_lifecycle_row('SELECT COUNT(*) nb FROM '.$db->prefix()."sof_caisse_alerte WHERE entity = ".((int) $conf->entity)." AND alert_type = 'session_too_long' AND object_id = ".((int) $oldSessionId).' AND status < 2');
agence_lifecycle_assert($firstDetection >= 1 && $secondDetection === 0 && $dedupCount && (int) $dedupCount->nb === 1, 'le détecteur d’alertes est exécutable et idempotent');

$cashier = clone $user;
$cashier->admin = 0;
$cashier->rights = new stdClass();
$cashier->rights->agence = new stdClass();
$cashier->rights->agence->session = new stdClass();
$cashier->rights->agence->session->open = 1;
$savedUser = $GLOBALS['user'];
$GLOBALS['user'] = $cashier;
$cashierDashboards = agence_report_available_dashboards();
$GLOBALS['user'] = $savedUser;
agence_lifecycle_assert(array_keys($cashierDashboards) === array('cashier'), 'un caissier ne reçoit que son tableau de bord métier');
$adminDashboards = agence_report_available_dashboards();
agence_lifecycle_assert(count($adminDashboards) === 6, 'la direction dispose des six vues métier consolidées');

$temporaryUser = new User($db);
$temporaryUser->entity = (int) $conf->entity;
$temporaryUser->login = 'qualif_'.$token;
$temporaryUser->lastname = 'Qualification';
$temporaryUser->firstname = 'Revocation';
$temporaryUser->statut = 1;
$temporaryUser->admin = 0;
$temporaryUserId = $temporaryUser->create($user, 1);
$right = agence_lifecycle_row('SELECT id FROM '.$db->prefix()."rights_def WHERE entity = ".((int) $conf->entity)." AND module = 'agence' AND perms = 'paiementdiffere' AND subperms = 'validate' LIMIT 1");
if ($temporaryUserId > 0 && $right) {
	$db->query('INSERT INTO '.$db->prefix().'user_rights (entity, fk_user, fk_id) VALUES ('.((int) $conf->entity).', '.((int) $temporaryUserId).', '.((int) $right->id).')');
}
$scope = new SofAgenceUser($db);
$scope->entity = (int) $conf->entity;
$scope->fk_agence = (int) $agencyId;
$scope->fk_user = (int) $temporaryUserId;
$scope->role_code = 'qualification';
$scope->scope_type = 'agency';
$scope->status = 1;
$scopeId = $temporaryUserId > 0 ? $scope->create($user, 1) : -1;
$temporaryUser->getrights('', 1);
$draftOne = clone $deferred;
$draftOne->id = 0;
$draftOne->rowid = 0;
$draftOne->ref = 'TEST-LIFE-RGT1-'.$token;
$draftOne->fk_facture = null;
$draftOne->expected_amount = 10;
$draftOne->remaining_amount = 10;
$draftOne->status = 0;
$draftOneId = $draftOne->create($user, 1);
$beforeRevocation = $engine->transitionDeferredPayment($temporaryUser, $draftOneId, 'validate');
$db->query('DELETE FROM '.$db->prefix().'user_rights WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $temporaryUserId).' AND fk_id = '.((int) $right->id));
$draftTwo = clone $draftOne;
$draftTwo->id = 0;
$draftTwo->rowid = 0;
$draftTwo->ref = 'TEST-LIFE-RGT2-'.$token;
$draftTwo->status = 0;
$draftTwoId = $draftTwo->create($user, 1);
$afterRevocation = $engine->transitionDeferredPayment($temporaryUser, $draftTwoId, 'validate');
$db->query('UPDATE '.$db->prefix().'user SET statut = 0 WHERE rowid = '.((int) $temporaryUserId));
$afterDisable = $engine->transitionDeferredPayment($temporaryUser, $draftTwoId, 'validate');
agence_lifecycle_assert($temporaryUserId > 0 && $scopeId > 0 && $beforeRevocation > 0 && $afterRevocation < 0, 'un retrait de droit prend effet dans la session utilisateur déjà chargée', 'user='.$temporaryUserId.' scope='.$scopeId.' before='.$beforeRevocation.' after='.$afterRevocation.' error='.$engine->error);
agence_lifecycle_assert($afterDisable < 0 && (int) $temporaryUser->statut === 0, 'un utilisateur désactivé est refusé sans attendre une nouvelle connexion');

$db->rollback();
$GLOBALS['user'] = $savedUser;
$exists = agence_lifecycle_row('SELECT COUNT(*) nb FROM '.$db->prefix()."sof_agence WHERE ref = '".$db->escape($agency->ref)."'");
agence_lifecycle_assert($exists && (int) $exists->nb === 0, 'toutes les données de qualification sont annulées proprement');

echo empty($errors) ? "Lifecycle qualification completed successfully.\n" : 'Lifecycle qualification failed: '.count($errors).' error(s).'.PHP_EOL;
exit(empty($errors) ? 0 : 1);
