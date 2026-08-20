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
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissetransfert.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissedepotbanque.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisseworkflow.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/softakeposlink.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/triggers/interface_99_modAgence_AgenceTriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$errors = array();
function agence_operational_assert($condition, $label)
{
	global $errors;
	echo ($condition ? '[OK] ' : '[KO] ').$label.PHP_EOL;
	if (!$condition) {
		$errors[] = $label;
	}
}

// CLI bootstrap may not select a user. Load the first administrator explicitly.
if (empty($GLOBALS['user']->id)) {
	$resql = $db->query('SELECT rowid FROM '.$db->prefix().'user WHERE admin = 1 AND statut = 1 ORDER BY rowid LIMIT 1');
	$row = $resql ? $db->fetch_object($resql) : null;
	if ($row) {
		$GLOBALS['user']->fetch((int) $row->rowid);
	}
}
$testUser = $GLOBALS['user'];
$testUser->getrights('', 1);
agence_operational_assert(!empty($testUser->id) && !empty($testUser->admin), 'administrator available for operational test');

$token = date('YmdHis').mt_rand(100, 999);
$db->begin();

$cardAccount = new Account($db);
$cardAccount->ref = 'TB-'.substr(md5($token), 0, 8);
$cardAccount->label = 'Agence operational test card account';
$cardAccount->courant = Account::TYPE_CURRENT;
$cardAccount->type = Account::TYPE_CURRENT;
$cardAccount->country_id = !empty($mysoc->country_id) ? (int) $mysoc->country_id : 1;
$cardAccount->date_solde = dol_now();
$cardAccountId = $cardAccount->create($testUser);
agence_operational_assert($cardAccountId > 0, 'temporary non-cash financial account created'.($cardAccountId > 0 ? '' : ' — '.$cardAccount->error));

$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity;
$agency->ref = 'TEST-AG-'.$token;
$agency->label = 'Agence operational test';
$agency->country_code = 'CM';
$agency->status = SofAgence::STATUS_ACTIVE;
$agencyId = $agency->create($testUser, 1);
agence_operational_assert($agencyId > 0, 'temporary agency created');

$cashDesk = new SofCaisse($db);
$cashDesk->entity = (int) $conf->entity;
$cashDesk->fk_agence = (int) $agencyId;
$cashDesk->ref = 'TEST-CAI-'.$token;
$cashDesk->label = 'Cash desk operational test';
$cashDesk->caisse_type = 'cash';
$cashDesk->currency_code = 'XAF';
$cashDesk->physical_balance_ceiling = 1000000;
$cashDesk->cashin_ceiling = 1000000;
$cashDesk->refund_ceiling = 1000000;
$cashDesk->allow_parallel_sessions = 0;
$cashDesk->status = SofCaisse::STATUS_ACTIVE;
$cashDeskId = $cashDesk->create($testUser, 1);
agence_operational_assert($cashDeskId > 0, 'temporary cash desk created'.($cashDeskId > 0 ? '' : ' — '.($cashDesk->error ?: implode(' | ', $cashDesk->errors))));

$engine = new SofAgenceOperations($db);
$sessionId = $engine->openSession($testUser, $cashDeskId, 50000, 'daily', 0, 'Automated operational check');
agence_operational_assert($sessionId > 0, 'session opens through business engine'.($sessionId > 0 ? '' : ' — '.$engine->error));

$duplicate = $engine->openSession($testUser, $cashDeskId, 1000, 'daily');
agence_operational_assert($duplicate < 0, 'double session is rejected');

$movementId = $engine->createMovement($testUser, array(
	'fk_agence' => $agencyId,
	'fk_caisse' => $cashDeskId,
	'fk_session' => $sessionId,
	'type_operation' => 'manual_cash_in',
	'direction' => 'credit',
	'payment_mode' => 'LIQ',
	'amount' => 5000,
	'label' => 'Automated cash in',
));
agence_operational_assert($movementId > 0, 'cash movement writes to ledger');
$balance = $engine->recalculateSession($sessionId);
agence_operational_assert(abs($balance - 55000) < 0.01, 'cash theoretical balance is recalculated');

// Native invoice + two real payment modes.
$resql = $db->query('SELECT rowid FROM '.$db->prefix().'societe WHERE entity IN ('.getEntity('societe').') AND client IN (1,2,3) ORDER BY rowid LIMIT 1');
$thirdParty = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert(!empty($thirdParty->rowid), 'customer fixture available');
$engine->updateRow('sof_caisse', $cashDeskId, array('fk_bank_account' => 1, 'fk_bank_account_card' => $cardAccountId), $testUser);
$invoice = new Facture($db);
$invoice->socid = (int) $thirdParty->rowid;
$invoice->type = Facture::TYPE_STANDARD;
$invoice->date = dol_now();
$invoice->note_private = 'Agence automated operational check';
$invoiceId = $invoice->create($testUser);
$lineResult = -1;
$invoiceValidation = -1;
if ($invoiceId > 0) {
	$lineResult = $invoice->addline('Agence operational test', 10000, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
	$invoiceValidation = $invoice->validate($testUser);
}
agence_operational_assert($invoiceId > 0 && $lineResult > 0 && $invoiceValidation > 0 && (int) $invoice->statut === 1, 'native Dolibarr invoice created and validated — create='.$invoiceId.' line='.$lineResult.' validate='.$invoiceValidation.' '.($invoice->error ?: implode(' | ', $invoice->errors)));
$capture = $engine->captureInvoicePayment($testUser, $sessionId, $invoiceId, array('LIQ' => 3000, 'CB' => 2000));
agence_operational_assert($capture > 0, 'mixed collection creates native payments'.($capture > 0 ? '' : ' — '.$engine->error));
$resql = $db->query('SELECT COUNT(DISTINCT fk_paiement) payments, COUNT(*) components FROM '.$db->prefix().'sof_paiement_link WHERE fk_facture = '.((int) $invoiceId));
$paymentCheck = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($paymentCheck && (int) $paymentCheck->payments === 2 && (int) $paymentCheck->components === 2, 'one native payment is created per payment mode');
$secondCapture = $engine->captureInvoicePayment($testUser, $sessionId, $invoiceId, array('LIQ' => 1000));
agence_operational_assert($secondCapture > 0, 'a later collection on the same linked invoice succeeds'.($secondCapture > 0 ? '' : ' — '.$engine->error));
$resql = $db->query('SELECT COUNT(*) links FROM '.$db->prefix().'sof_paiement_link WHERE fk_facture = '.((int) $invoiceId));
$secondPaymentCheck = $resql ? $db->fetch_object($resql) : null;
$resql = $db->query("SELECT COUNT(*) movements FROM ".$db->prefix()."sof_caisse_mouvement WHERE fk_facture = ".((int) $invoiceId)." AND type_operation = 'invoice_payment'");
$secondMovementCheck = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($secondPaymentCheck && (int) $secondPaymentCheck->links === 3 && $secondMovementCheck && (int) $secondMovementCheck->movements === 3, 'later collection is linked and journaled exactly once');
$customerDepositInvoiceId = $engine->captureCustomerDeposit($testUser, $sessionId, (int) $thirdParty->rowid, array('LIQ' => 2000), 'Automated customer deposit');
$customerDepositInvoice = new Facture($db);
$customerDepositFetched = $customerDepositInvoiceId > 0 ? $customerDepositInvoice->fetch($customerDepositInvoiceId) : -1;
agence_operational_assert($customerDepositInvoiceId > 0 && $customerDepositFetched > 0 && (int) $customerDepositInvoice->type === Facture::TYPE_DEPOSIT, 'customer deposit creates and collects a native deposit invoice'.($customerDepositInvoiceId > 0 ? '' : ' — '.$engine->error));

// TakePOS server-side mapping, canonical context, cancellation and idempotence.
$terminalRef = (string) mt_rand(100, 999);
$terminalMapping = new SofTakeposLink($db);
$terminalMapping->entity = (int) $conf->entity;
$terminalMapping->terminal_ref = $terminalRef;
$terminalMapping->fk_agence = (int) $agencyId;
$terminalMapping->fk_caisse = (int) $cashDeskId;
$terminalMapping->status = 1;
$terminalMappingId = $terminalMapping->create($testUser, 1);
agence_operational_assert($terminalMappingId > 0, 'TakePOS terminal mapping created');

$posInvoice = new Facture($db);
$posInvoice->socid = (int) $thirdParty->rowid;
$posInvoice->type = Facture::TYPE_STANDARD;
$posInvoice->date = dol_now();
$posInvoice->module_source = 'takepos';
$posInvoice->pos_source = $terminalRef;
$posInvoiceId = $posInvoice->create($testUser);
$posLineResult = $posInvoiceId > 0 ? $posInvoice->addline('Agence TakePOS operational test', 1000, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) : -1;
$posValidation = $posLineResult > 0 ? $posInvoice->validate($testUser) : -1;
agence_operational_assert($posValidation > 0, 'mapped TakePOS invoice validates with an open session'.($posValidation > 0 ? '' : ' — '.$posInvoice->error));
$resql = $db->query('SELECT COUNT(*) links FROM '.$db->prefix().'sof_takepos_link WHERE fk_facture = '.((int) $posInvoiceId).' AND fk_session = '.((int) $sessionId));
$takeposContext = $resql ? $db->fetch_object($resql) : null;
$resql = $db->query('SELECT COUNT(*) links FROM '.$db->prefix().'sof_facture_link WHERE fk_facture = '.((int) $posInvoiceId).' AND fk_session = '.((int) $sessionId));
$takeposInvoiceContext = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($takeposContext && (int) $takeposContext->links === 1 && $takeposInvoiceContext && (int) $takeposInvoiceContext->links === 1, 'TakePOS ticket and invoice receive one canonical Agence context');
$posCapture = $engine->captureInvoicePayment($testUser, $sessionId, $posInvoiceId, array('LIQ' => 1000));
agence_operational_assert($posCapture > 0, 'TakePOS invoice collection uses the common payment engine'.($posCapture > 0 ? '' : ' — '.$engine->error));

$trigger = new InterfaceAgenceTriggers($db);
$cancelResult = $trigger->runTrigger('BILL_CANCEL', $posInvoice, $testUser, $langs, $conf);
$secondCancelResult = $trigger->runTrigger('BILL_CANCEL', $posInvoice, $testUser, $langs, $conf);
$resql = $db->query('SELECT status, billing_status, reconcile_status FROM '.$db->prefix().'sof_takepos_link WHERE fk_facture = '.((int) $posInvoiceId).' ORDER BY rowid DESC LIMIT 1');
$canceledTakepos = $resql ? $db->fetch_object($resql) : null;
$resql = $db->query("SELECT COUNT(*) reversals FROM ".$db->prefix()."sof_caisse_mouvement WHERE fk_facture = ".((int) $posInvoiceId)." AND source_type = 'reversal'");
$takeposReversals = $resql ? $db->fetch_object($resql) : null;
$resql = $db->query("SELECT COUNT(*) alerts, MIN(fk_agence) fk_agence, MIN(fk_caisse) fk_caisse, MIN(fk_session) fk_session FROM ".$db->prefix()."sof_caisse_alerte WHERE object_type = 'facture' AND object_id = ".((int) $posInvoiceId)." AND alert_type = 'takepos_cancellation'");
$takeposAlerts = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($cancelResult === 0 && $secondCancelResult === 0 && $canceledTakepos && (int) $canceledTakepos->status === 0 && (int) $canceledTakepos->billing_status === 9, 'TakePOS cancellation closes the ticket context');
agence_operational_assert($takeposReversals && (int) $takeposReversals->reversals === 1 && $takeposAlerts && (int) $takeposAlerts->alerts === 1
	&& (int) $takeposAlerts->fk_agence === $agencyId && (int) $takeposAlerts->fk_caisse === $cashDeskId && (int) $takeposAlerts->fk_session === $sessionId,
	'TakePOS cancellation reversal and agency-scoped alert are idempotent');

$unmappedInvoice = new Facture($db);
$unmappedInvoice->socid = (int) $thirdParty->rowid;
$unmappedInvoice->type = Facture::TYPE_STANDARD;
$unmappedInvoice->date = dol_now();
$unmappedInvoice->module_source = 'takepos';
$unmappedInvoice->pos_source = (string) mt_rand(8000, 8999);
$unmappedInvoiceId = $unmappedInvoice->create($testUser);
$unmappedLine = $unmappedInvoiceId > 0 ? $unmappedInvoice->addline('Unmapped TakePOS operational test', 100, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) : -1;
$unmappedValidation = $unmappedLine > 0 ? $unmappedInvoice->validate($testUser) : -1;
agence_operational_assert($unmappedValidation < 0, 'unmapped TakePOS terminal is blocked server-side');

$refundId = $engine->requestRefund($testUser, array(
	'fk_facture_origin' => $invoiceId,
	'requested_amount' => 1000,
	'payment_mode' => 'LIQ',
	'reason' => 'Agence automated refund',
));
agence_operational_assert($refundId > 0, 'refund request checks the paid origin'.($refundId > 0 ? '' : ' — '.$engine->error));
$refundValidation = $refundId > 0 ? $engine->validateRefund($testUser, $refundId, 1000, 'Approved by automated check') : -1;
agence_operational_assert($refundValidation > 0, 'refund reaches approved status'.($refundValidation > 0 ? '' : ' — '.$engine->error));
$refundExecution = $refundValidation > 0 ? $engine->executeRefund($testUser, $refundId) : -1;
agence_operational_assert($refundExecution > 0, 'approved refund creates cash out and credit note'.($refundExecution > 0 ? '' : ' — '.$engine->error.' '.implode(' | ', $engine->errors)));

$balance = $engine->recalculateSession($sessionId);
agence_operational_assert(abs($balance - 60000) < 0.01, 'only cash collection and cash refund update physical-cash balance');

$transfer = new SofCaisseTransfert($db);
$transfer->entity = (int) $conf->entity;
$transfer->ref = 'TEST-TR-'.$token;
$transfer->fk_agence = (int) $agencyId;
$transfer->fk_caisse_source = (int) $cashDeskId;
$transfer->transfer_type = 'vault';
$transfer->amount = 1000;
$transfer->currency_code = 'XAF';
$transfer->transfer_reason = 'Automated vault transfer';
$transfer->date_transfer = dol_now();
$transfer->status = 0;
$transferId = $transfer->create($testUser);
agence_operational_assert($transferId > 0, 'vault transfer draft created');
$transferExecution = $transferId > 0 ? $engine->executeTransfer($testUser, $transferId) : -1;
agence_operational_assert($transferExecution > 0, 'vault transfer debits source session'.($transferExecution > 0 ? '' : ' — '.$engine->error));
$transferReception = $transferExecution > 0 ? $engine->receiveTransfer($testUser, $transferId) : -1;
agence_operational_assert($transferReception > 0, 'vault transfer reception is confirmed'.($transferReception > 0 ? '' : ' — '.$engine->error));

$deposit = new SofCaisseDepotBanque($db);
$deposit->entity = (int) $conf->entity;
$deposit->ref = 'TEST-DEP-'.$token;
$deposit->fk_agence = (int) $agencyId;
$deposit->fk_caisse_source = (int) $cashDeskId;
$deposit->fk_bank_account = (int) $cardAccountId;
$deposit->amount = 1000;
$deposit->currency_code = 'XAF';
$deposit->date_preparation = dol_now();
$deposit->bank_slip_number = 'TEST-SLIP-'.$token;
$deposit->status = 0;
$depositId = $deposit->create($testUser);
agence_operational_assert($depositId > 0, 'bank deposit draft created');
$depositExecution = $depositId > 0 ? $engine->executeDeposit($testUser, $depositId) : -1;
agence_operational_assert($depositExecution > 0, 'bank deposit debits cash and creates native source line'.($depositExecution > 0 ? '' : ' — '.$engine->error));
$destinationBankLineId = $cardAccount->addline(dol_now(), 'LIQ', 'Automated deposit credit', 1000, '', 0, $testUser);
agence_operational_assert($destinationBankLineId > 0, 'native destination bank line available');
$reconcileResult = $destinationBankLineId > 0 ? $engine->reconcileDeposit($testUser, $depositId, $destinationBankLineId, 'AUTO-'.$token) : -1;
agence_operational_assert($reconcileResult > 0, 'bank deposit reconciles against matching destination line'.($reconcileResult > 0 ? '' : ' — '.$engine->error));

$balance = $engine->recalculateSession($sessionId);
agence_operational_assert(abs($balance - 58000) < 0.01, 'vault transfer and bank deposit reduce physical cash exactly once');

$db->query("UPDATE ".$db->prefix()."sof_caisse_workflow SET status=0 WHERE entity=".((int) $conf->entity)." AND object_type='session'");
$workflow = new SofCaisseWorkflow($db);
$workflow->entity = (int) $conf->entity;
$workflow->code = 'TEST-WF-'.$token;
$workflow->label = 'Automated two-step session approval';
$workflow->object_type = 'session';
$workflow->agency_scope = (string) $agencyId;
$workflow->min_amount = 0;
$workflow->max_amount = 0;
$workflow->validation_steps = json_encode(array(
	array('user' => (int) $testUser->id, 'role' => ''),
	array('user' => (int) $testUser->id, 'role' => ''),
));
$workflow->status = 1;
$workflowId = $workflow->create($testUser);
agence_operational_assert($workflowId > 0, 'two-step closing workflow configured');

agence_operational_assert($engine->transitionSession($testUser, $sessionId, 'start_close') > 0, 'closing starts');
$closeResult = $engine->transitionSession($testUser, $sessionId, 'close', array('physical_amount' => 58000, 'comment' => 'Balanced'));
agence_operational_assert($closeResult > 0, 'session closes with physical amount'.($closeResult > 0 ? '' : ' — '.$engine->error.' '.implode(' | ', $engine->errors)));
$firstValidateResult = $engine->transitionSession($testUser, $sessionId, 'validate', array('reason' => 'Automated first-level validation'));
$resql = $db->query('SELECT status FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
$firstValidationState = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($firstValidateResult > 0 && $firstValidationState && (int) $firstValidationState->status === 6, 'first workflow step keeps the session pending');
$validateResult = $engine->transitionSession($testUser, $sessionId, 'validate', array('reason' => 'Automated final validation'));
agence_operational_assert($validateResult > 0, 'final workflow step validates the session'.($validateResult > 0 ? '' : ' — '.$engine->error));

$resql = $db->query('SELECT status, theoretical_amount, physical_amount, gap_amount FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId));
$sessionRow = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($sessionRow && (int) $sessionRow->status === 7, 'session reaches validated state');
agence_operational_assert($sessionRow && abs((float) $sessionRow->gap_amount) < 0.01, 'balanced closing creates no gap');

$db->rollback();

$resql = $db->query("SELECT COUNT(*) count_rows FROM ".$db->prefix()."sof_agence WHERE ref = '".$db->escape($agency->ref)."'");
$row = $resql ? $db->fetch_object($resql) : null;
agence_operational_assert($row && (int) $row->count_rows === 0, 'test transaction rolled back cleanly');

echo empty($errors) ? "Operational check completed successfully.\n" : 'Operational check failed: '.count($errors).' error(s).'.PHP_EOL;
exit(empty($errors) ? 0 : 1);
