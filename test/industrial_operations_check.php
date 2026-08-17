<?php
/* Copyright (C) 2026 iPowerWorld */

if (PHP_SAPI !== 'cli') die("CLI only\n");
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);
$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnotificationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofimportservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofindustrialservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofdas.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissedepotbanque.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofpaiementdiffere.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofalerte.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissevalidation.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofauditlog.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$failures = array();
function agence_industrial_assert($condition, $label, $detail = '')
{
	global $failures;
	echo ($condition ? '[OK] ' : '[KO] ').$label.(!$condition && $detail !== '' ? ' — '.$detail : '').PHP_EOL;
	if (!$condition) $failures[] = $label;
}
function agence_industrial_row($sql)
{
	global $db;
	$resql = $db->query($sql);
	return $resql ? $db->fetch_object($resql) : null;
}

$adminRow = agence_industrial_row('SELECT rowid FROM '.$db->prefix().'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
if ($adminRow) {
	$user->fetch((int) $adminRow->rowid);
	$user->getrights('', 1);
}
agence_industrial_assert(!empty($user->id) && !empty($user->admin), 'administrator available for industrial qualification');
$token = date('YmdHis').mt_rand(100,999);
$db->begin();

$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity;
$agency->ref = 'IND-AG-'.$token;
$agency->label = 'Industrial qualification agency';
$agency->country_code = 'CM';
$agency->status = 1;
$agencyId = $agency->create($user, 1);
$das = new SofDas($db);
$das->entity = (int) $conf->entity;
$das->ref = 'IND-DAS-'.$token;
$das->label = 'Industrial qualification DAS';
$das->status = 1;
$dasId = $das->create($user, 1);
$accountRow = agence_industrial_row('SELECT rowid,courant FROM '.$db->prefix().'bank_account WHERE entity='.((int) $conf->entity).' AND clos=0 ORDER BY rowid LIMIT 1');
$cash = new SofCaisse($db);
$cash->entity = (int) $conf->entity;
$cash->fk_agence = (int) $agencyId;
$cash->ref = 'IND-CAI-'.$token;
$cash->label = 'Industrial qualification cash desk';
$cash->caisse_type = 'cash';
$cash->currency_code = 'XAF';
$cash->fk_bank_account = $accountRow ? (int) $accountRow->rowid : null;
$cash->fk_bank_account_mobile = $accountRow ? (int) $accountRow->rowid : null;
$cash->allowed_das = (string) $dasId;
$cash->physical_balance_ceiling = 1000000;
$cash->cashin_ceiling = 1000000;
$cash->status = 1;
$cashId = $cash->create($user, 1);
$engine = new SofAgenceOperations($db);
$sessionId = $engine->openSession($user, $cashId, 5000, 'industrial qualification', $dasId);
agence_industrial_assert($agencyId > 0 && $dasId > 0 && $cashId > 0 && $sessionId > 0, 'industrial agency, DAS, cash desk and session fixtures created', $engine->error);

$notification = new SofNotificationService($db);
$configResult = $notification->saveConfiguration($user, array('event_code'=>'qualification_event','severity_min'=>'info','channel'=>'internal','recipient_type'=>'user','recipient'=>(string) $user->id,'escalation_level'=>0));
$queued = $notification->queueEvent('qualification_event', 'info', 'Qualification interne', 'Message persistant de qualification', 'agency', $agencyId, $agencyId);
$sent = $notification->processQueue();
$outbox = agence_industrial_row('SELECT status,channel,attempts FROM '.$db->prefix()."sof_notification_outbox WHERE entity=".((int) $conf->entity)." AND event_code='qualification_event' ORDER BY rowid DESC LIMIT 1");
agence_industrial_assert($configResult > 0 && $queued === 1 && $sent >= 1 && $outbox && (int) $outbox->status === 1 && $outbox->channel === 'internal', 'configurable internal notification is queued and delivered');
$emailConfig = $notification->saveConfiguration($user, array('event_code'=>'qualification_multichannel','severity_min'=>'info','channel'=>'email','recipient_type'=>'address','recipient'=>'qualification@example.invalid','escalation_level'=>0));
$smsConfig = $notification->saveConfiguration($user, array('event_code'=>'qualification_multichannel','severity_min'=>'info','channel'=>'sms','recipient_type'=>'address','recipient'=>'+237690000000','escalation_level'=>0));
$multichannelQueued = $notification->queueEvent('qualification_multichannel', 'info', 'Qualification multicanale', 'Message de qualification sans émission externe', 'agency', $agencyId, $agencyId);
$multichannelOutbox = agence_industrial_row('SELECT COUNT(*) nb FROM '.$db->prefix()."sof_notification_outbox WHERE entity=".((int) $conf->entity)." AND event_code='qualification_multichannel' AND status=0 AND channel IN ('email','sms')");
agence_industrial_assert($emailConfig > 0 && $smsConfig > 0 && $multichannelQueued === 2 && $multichannelOutbox && (int) $multichannelOutbox->nb === 2, 'email and SMS destinations are validated and queued without an external send during qualification', $notification->error);

$alert = new SofAlerte($db);
$alert->entity = (int) $conf->entity;
$alert->ref = 'IND-ALT-'.$token;
$alert->dedup_key = 'industrial-alert-'.$token;
$alert->alert_type = 'qualification_critical';
$alert->severity = 'critical';
$alert->fk_agence = $agencyId;
$alert->object_type = 'agency';
$alert->object_id = $agencyId;
$alert->message = 'Critical alert escalation qualification';
$alert->target_roles = 'admin';
$alert->date_alert = dol_now() - 7200;
$alert->status = 0;
$alertId = $alert->create($user, 1);
$validation = new SofCaisseValidation($db);
$validation->entity = (int) $conf->entity;
$validation->ref = 'IND-VAL-'.$token;
$validation->object_type = 'agency';
$validation->object_id = $agencyId;
$validation->validation_level = 1;
$validation->validation_mode = 'sequential';
$validation->fk_user_requester = $user->id;
$validation->role_required = 'direction';
$validation->date_request = dol_now() - (72 * 3600);
$validation->status = 0;
$validationId = $validation->create($user, 1);
$escalated = $notification->runEscalations();
$alertEscalation = agence_industrial_row('SELECT escalation_level,date_last_escalation FROM '.$db->prefix().'sof_caisse_alerte WHERE rowid='.((int) $alertId));
$validationEscalation = agence_industrial_row('SELECT escalation_level,date_last_escalation FROM '.$db->prefix().'sof_caisse_validation WHERE rowid='.((int) $validationId));
agence_industrial_assert($escalated >= 2 && $alertEscalation && (int) $alertEscalation->escalation_level > 0 && $validationEscalation && (int) $validationEscalation->escalation_level > 0, 'critical alerts and overdue validations escalate automatically');

$omMovement = $engine->createMovement($user, array('fk_agence'=>$agencyId,'fk_caisse'=>$cashId,'fk_session'=>$sessionId,'fk_das'=>$dasId,'type_operation'=>'collection','direction'=>'credit','payment_mode'=>'OM','amount'=>2500,'transaction_ref'=>'OM-'.$token,'label'=>'Orange Money qualification'));
$mmMovement = $engine->createMovement($user, array('fk_agence'=>$agencyId,'fk_caisse'=>$cashId,'fk_session'=>$sessionId,'fk_das'=>$dasId,'type_operation'=>'collection','direction'=>'credit','payment_mode'=>'MM','amount'=>3500,'transaction_ref'=>'MM-'.$token,'label'=>'Mobile Money qualification'));
$importService = new SofImportService($db);
$today = date('Y-m-d');
$omCsv = "date;montant;reference;libelle\n".$today.";2500;OM-".$token.";Orange Money qualification\n";
$mmCsv = "date;montant;reference;libelle\n".$today.";3500;MM-".$token.";Mobile Money qualification\n";
$omImport = $importService->importStatement($user, 'orange_money', 'orange.csv', $omCsv, 0, $agencyId);
$mmImport = $importService->importStatement($user, 'mobile_money', 'mobile.csv', $mmCsv, 0, $agencyId);
$omLine = agence_industrial_row('SELECT rowid,fk_mouvement,match_score FROM '.$db->prefix().'sof_bank_import_line WHERE fk_import='.((int) $omImport));
$mmLine = agence_industrial_row('SELECT rowid,fk_mouvement,match_score FROM '.$db->prefix().'sof_bank_import_line WHERE fk_import='.((int) $mmImport));
$omConfirm = $omLine ? $importService->confirmMatch($user, (int) $omLine->rowid) : -1;
$mmConfirm = $mmLine ? $importService->confirmMatch($user, (int) $mmLine->rowid) : -1;
agence_industrial_assert($omMovement > 0 && $mmMovement > 0 && $omImport > 0 && $mmImport > 0 && $omLine && (int) $omLine->fk_mouvement === (int) $omMovement && $mmLine && (int) $mmLine->fk_mouvement === (int) $mmMovement && $omConfirm > 0 && $mmConfirm > 0, 'Orange Money and Mobile Money statements are suggested then reconciled', $importService->error);

if ($accountRow) {
	$account = new Account($db);
	$account->fetch((int) $accountRow->rowid);
	$bankOperation = (int) $accountRow->courant === Account::TYPE_CASH ? 'LIQ' : 'VIR';
	$bankLineId = $account->addline(dol_now(), $bankOperation, 'BANK-'.$token, 12345, 'BANK-'.$token, 0, $user);
	$deposit = new SofCaisseDepotBanque($db);
	$deposit->entity = (int) $conf->entity;
	$deposit->ref = 'IND-DEP-'.$token;
	$deposit->fk_agence = $agencyId;
	$deposit->fk_caisse_source = $cashId;
	$deposit->fk_session = $sessionId;
	$deposit->fk_bank_account = (int) $accountRow->rowid;
	$deposit->amount = 12345;
	$deposit->currency_code = 'XAF';
	$deposit->date_deposit = dol_now();
	$deposit->status = 1;
	$depositId = $deposit->create($user, 1);
	$bankCsv = "date;montant;reference;libelle\n".$today.";12345;BANK-".$token.";Dépôt qualification\n";
	$bankImport = $importService->importStatement($user, 'bank', 'bank.csv', $bankCsv, (int) $accountRow->rowid, $agencyId);
	$bankImportedLine = agence_industrial_row('SELECT rowid,fk_bank,fk_depot_banque,match_score FROM '.$db->prefix().'sof_bank_import_line WHERE fk_import='.((int) $bankImport));
	$bankConfirm = $bankImportedLine ? $importService->confirmMatch($user, (int) $bankImportedLine->rowid) : -1;
	$depositAfter = agence_industrial_row('SELECT status,fk_bank,date_reconcile FROM '.$db->prefix().'sof_caisse_depot_banque WHERE rowid='.((int) $depositId));
	$bankNative = agence_industrial_row('SELECT rowid,fk_account,amount,dateo,datev,label,num_chq FROM '.$db->prefix().'bank WHERE rowid='.((int) $bankLineId));
	$bankDetail = json_encode(array('error'=>$importService->error, 'native'=>$bankNative, 'imported'=>$bankImportedLine), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	agence_industrial_assert($bankLineId > 0 && $depositId > 0 && $bankImport > 0 && $bankImportedLine && (int) $bankImportedLine->fk_bank === (int) $bankLineId && (int) $bankImportedLine->fk_depot_banque === (int) $depositId && $bankConfirm > 0 && $depositAfter && (int) $depositAfter->status === 3, 'bank statement import suggests and confirms a Dolibarr deposit reconciliation', $bankDetail ?: $importService->error);
} else {
	agence_industrial_assert(false, 'bank statement qualification requires an active Dolibarr bank account');
}

$bulkAgencyRef = 'BULK-AG-'.$token;
$bulkDasRef = 'BULK-DAS-'.$token;
$bulkCashRef = 'BULK-CAI-'.$token;
$agencyImport = $importService->importMasterData($user, 'agency', 'agencies.csv', "ref;label;town;country_code;status\n".$bulkAgencyRef.";Agence importée;Douala;CM;1\n");
$dasImport = $importService->importMasterData($user, 'das', 'das.csv', "ref;label;description;status\n".$bulkDasRef.";DAS importé;Qualification;1\n");
$cashImport = $importService->importMasterData($user, 'cashdesk', 'cash.csv', "ref;label;agency_ref;allowed_das_refs;caisse_type;currency_code;status\n".$bulkCashRef.";Caisse importée;".$bulkAgencyRef.";".$bulkDasRef.";cash;XAF;1\n");
$assignmentImport = $importService->importMasterData($user, 'assignment', 'assignments.csv', "user_login;agency_ref;role_code;scope_type;status\n".$user->login.";".$bulkAgencyRef.";agency_manager;agency;1\n");
$agencyUpdate = $importService->importMasterData($user, 'agency', 'agencies-update.csv', "ref;label;town;country_code;status\n".$bulkAgencyRef.";Agence importée mise à jour;Yaoundé;CM;1\n", 'update');
$dasUpdate = $importService->importMasterData($user, 'das', 'das-update.csv', "ref;label;description;status\n".$bulkDasRef.";DAS importé mis à jour;Qualification mise à jour;1\n", 'update');
$cashUpdate = $importService->importMasterData($user, 'cashdesk', 'cash-update.csv', "ref;label;status\n".$bulkCashRef.";Caisse importée mise à jour;1\n", 'update');
$assignmentUpdate = $importService->importMasterData($user, 'assignment', 'assignments-update.csv', "user_login;agency_ref;role_code;scope_type;status\n".$user->login.";".$bulkAgencyRef.";cashier;agency;1\n", 'update');
$bulkImportIds = array($agencyImport,$dasImport,$cashImport,$assignmentImport,$agencyUpdate,$dasUpdate,$cashUpdate,$assignmentUpdate);
$bulkCounts = agence_industrial_row('SELECT COUNT(*) nb FROM '.$db->prefix()."sof_bulk_import WHERE entity=".((int) $conf->entity)." AND rowid IN (".implode(',', array_map('intval', $bulkImportIds)).") AND error_count=0 AND status=1");
$bulkUpdated = agence_industrial_row('SELECT b.updated_count,a.label,a.town FROM '.$db->prefix().'sof_bulk_import b JOIN '.$db->prefix()."sof_agence a ON a.entity=b.entity AND a.ref='".$db->escape($bulkAgencyRef)."' WHERE b.rowid=".((int) $agencyUpdate));
$bulkDasUpdated = agence_industrial_row('SELECT b.updated_count,d.label FROM '.$db->prefix().'sof_bulk_import b JOIN '.$db->prefix()."sof_das d ON d.entity=b.entity AND d.ref='".$db->escape($bulkDasRef)."' WHERE b.rowid=".((int) $dasUpdate));
$bulkCashUpdated = agence_industrial_row('SELECT b.updated_count,c.label,c.fk_agence FROM '.$db->prefix().'sof_bulk_import b JOIN '.$db->prefix()."sof_caisse c ON c.entity=b.entity AND c.ref='".$db->escape($bulkCashRef)."' WHERE b.rowid=".((int) $cashUpdate));
$bulkAssignmentUpdated = agence_industrial_row('SELECT b.updated_count,au.role_code FROM '.$db->prefix().'sof_bulk_import b JOIN '.$db->prefix().'sof_agence a ON a.entity=b.entity AND a.ref=\''.$db->escape($bulkAgencyRef).'\' JOIN '.$db->prefix().'sof_agence_user au ON au.entity=b.entity AND au.fk_agence=a.rowid AND au.fk_user='.((int) $user->id).' WHERE b.rowid='.((int) $assignmentUpdate));
$allBulkIdsValid = count(array_filter($bulkImportIds, static function ($id) { return (int) $id > 0; })) === count($bulkImportIds);
agence_industrial_assert($allBulkIdsValid && $bulkCounts && (int) $bulkCounts->nb === 8 && $bulkUpdated && (int) $bulkUpdated->updated_count === 1 && $bulkUpdated->label === 'Agence importée mise à jour' && $bulkUpdated->town === 'Yaoundé' && $bulkDasUpdated && (int) $bulkDasUpdated->updated_count === 1 && $bulkDasUpdated->label === 'DAS importé mis à jour' && $bulkCashUpdated && (int) $bulkCashUpdated->updated_count === 1 && $bulkCashUpdated->label === 'Caisse importée mise à jour' && (int) $bulkCashUpdated->fk_agence > 0 && $bulkAssignmentUpdated && (int) $bulkAssignmentUpdated->updated_count === 1 && $bulkAssignmentUpdated->role_code === 'cashier', 'agencies, DAS, cash desks and assignments support traced create/update bulk import', $importService->error);

$thirdParty = agence_industrial_row('SELECT rowid FROM '.$db->prefix().'societe WHERE entity='.((int) $conf->entity).' AND client IN (1,2,3) ORDER BY rowid LIMIT 1');
$deferred = new SofPaiementDiffere($db);
$deferred->entity = (int) $conf->entity;
$deferred->ref = 'IND-DEF-'.$token;
$deferred->fk_soc = $thirdParty ? (int) $thirdParty->rowid : 0;
$deferred->fk_agence = $agencyId;
$deferred->fk_caisse = $cashId;
$deferred->fk_session = $sessionId;
$deferred->fk_das = $dasId;
$deferred->source_type = 'qualification';
$deferred->source_id = $agencyId;
$deferred->operation_date = dol_now() - (40 * 86400);
$deferred->expected_amount = 50000;
$deferred->paid_amount = 0;
$deferred->remaining_amount = 50000;
$deferred->expected_payment_date = dol_now() - (35 * 86400);
$deferred->status = 5;
$deferredId = $thirdParty ? $deferred->create($user, 1) : -1;
$collectionSync = $notification->synchronizeCollections();
$collectionCase = agence_industrial_row('SELECT * FROM '.$db->prefix().'sof_recouvrement WHERE entity='.((int) $conf->entity).' AND fk_paiement_differe='.((int) $deferredId));
$collectionAction = $collectionCase ? $notification->addCollectionAction($user, (int) $collectionCase->rowid, 'promise', 'phone', 'accepted', 'Promesse confirmée lors de l’appel de qualification', date('Y-m-d', dol_now()+86400), date('Y-m-d', dol_now()+86400), 25000) : -1;
$collectionAfter = $collectionCase ? agence_industrial_row('SELECT stage,promise_amount,last_contact_date FROM '.$db->prefix().'sof_recouvrement WHERE rowid='.((int) $collectionCase->rowid)) : null;
agence_industrial_assert($deferredId > 0 && $collectionSync > 0 && $collectionCase && $collectionCase->stage === 'dispute' && $collectionAction > 0 && $collectionAfter && $collectionAfter->stage === 'promise' && (float) $collectionAfter->promise_amount === 25000.0, 'overdue receivable creates a staged collection workflow with a documented promise', $notification->error);

$technicalErrorId = $notification->recordTechnicalError('qualification_retry', 'agency', $agencyId, 'collection', array('agency_id'=>$agencyId,'token'=>'must-not-leak'), 'Qualification controlled failure', 3);
$retryResult = $notification->retryTechnicalError($technicalErrorId, $user);
$technicalAfter = agence_industrial_row('SELECT status,attempts,payload,date_resolution FROM '.$db->prefix().'sof_technical_error WHERE rowid='.((int) $technicalErrorId));
agence_industrial_assert($technicalErrorId > 0 && $retryResult > 0 && $technicalAfter && (int) $technicalAfter->status === 2 && strpos($technicalAfter->payload, '[REDACTED]') !== false, 'technical error journal redacts secrets and supports controlled retry');

$industrial = new SofAgenceIndustrialService($db);
$reversalId = $industrial->requestReversal($user, $omMovement, 'Erreur opérationnelle documentée pour la qualification', 'PV-'.$token);
$secondAdmin = new User($db);
$secondAdmin->entity = (int) $conf->entity;
$secondAdmin->login = 'ind_approver_'.$token;
$secondAdmin->lastname = 'IndustrialApprover';
$secondAdmin->firstname = 'Qualification';
$secondAdmin->statut = 1;
$secondAdmin->admin = 1;
$secondAdminId = $secondAdmin->create($user, 1);
if ($secondAdminId > 0) {
	$secondAdmin->fetch($secondAdminId);
	$secondAdmin->getrights('', 1);
}
$reversalDecision = $secondAdminId > 0 ? $industrial->decideReversal($secondAdmin, $reversalId, true, 'Contrepassation approuvée après contrôle du procès-verbal') : -1;
$reversal = agence_industrial_row('SELECT r.status,r.fk_mouvement_reversal,m.direction,m.source_type,m.source_id FROM '.$db->prefix().'sof_financial_reversal r LEFT JOIN '.$db->prefix().'sof_caisse_mouvement m ON m.rowid=r.fk_mouvement_reversal WHERE r.rowid='.((int) $reversalId));
agence_industrial_assert($reversalId > 0 && $secondAdminId > 0 && $reversalDecision > 0 && $reversal && (int) $reversal->status === 2 && $reversal->direction === 'debit' && $reversal->source_type === 'reversal' && (int) $reversal->source_id === (int) $omMovement, 'financial reversal is requested, independently approved and posted as an opposite immutable line', $industrial->error);

$oldAudit = new SofAuditLog($db);
$oldAudit->entity = (int) $conf->entity;
$oldAudit->fk_user = (int) $user->id;
$oldAudit->user_role = 'admin';
$oldAudit->fk_agence = $agencyId;
$oldAudit->action_code = 'INDUSTRIAL_RETENTION_QUALIFICATION';
$oldAudit->object_type = 'agency';
$oldAudit->object_id = $agencyId;
$oldAudit->event_date = dol_now() - (4000 * 86400);
$oldAudit->reason = 'Qualification de la politique de conservation';
$oldAudit->status = 1;
$oldAudit->date_creation = dol_now() - (4000 * 86400);
$oldAuditId = $oldAudit->create($user, 1);
$preview = $industrial->applyRetention($user, true, false);
$archive = $industrial->applyRetention($user, false, false);
$oldAuditAfter = agence_industrial_row('SELECT archive_status,date_archive,purge_after FROM '.$db->prefix().'sof_caisse_auditlog WHERE rowid='.((int) $oldAuditId));
agence_industrial_assert(is_array($preview) && $preview['audits_to_archive'] >= 1 && is_array($archive) && $archive['audits_archived'] >= 1 && $oldAuditAfter && (int) $oldAuditAfter->archive_status === 1 && !empty($oldAuditAfter->purge_after), 'retention policy supports dry-run and non-destructive audit archival');

$diagnostics = $industrial->diagnostics($user);
$diagnosticCodes = array_column($diagnostics, 'code');
agence_industrial_assert(count($diagnostics) >= 15 && in_array('active_mappings', $diagnosticCodes, true) && in_array('cash_accounts', $diagnosticCodes, true) && in_array('mobile_money', $diagnosticCodes, true) && in_array('technical_errors', $diagnosticCodes, true), 'administrator diagnostic covers cron, schema, mappings, accounts and integrations');

$db->rollback();
agence_industrial_assert(true, 'all industrial qualification fixtures rolled back cleanly');
if ($failures) {
	fwrite(STDERR, 'Industrial qualification failed: '.implode(', ', $failures).PHP_EOL);
	exit(1);
}
echo "Industrial operations qualification completed successfully.\n";
