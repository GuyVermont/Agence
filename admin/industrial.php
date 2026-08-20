<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnotificationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofimportservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofindustrialservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';

$langs->loadLangs(array('agence@agence', 'admin', 'banks', 'companies'));
$section = GETPOST('section', 'aZ09') ?: 'notifications';
$allowedSections = array('notifications', 'imports', 'collections', 'errors', 'reversals', 'retention');
if (!in_array($section, $allowedSections, true)) $section = 'notifications';
$permissions = array(
	'notifications' => array(array('notification', 'manage')),
	'imports' => array(array('bankimport', 'import'), array('bankimport', 'reconcile'), array('bulkimport', 'run')),
	'collections' => array(array('recouvrement', 'manage')),
	'errors' => array(array('technicalerror', 'manage')),
	'reversals' => array(array('reversal', 'request'), array('reversal', 'approve')),
	'retention' => array(array('archive', 'manage')),
);
$authorized = !empty($user->admin);
foreach ($permissions[$section] as $permission) {
	if ($user->hasRight('agence', $permission[0], $permission[1])) $authorized = true;
}
if (!$authorized || !SofAgenceService::isActiveUser($db, $user)) accessforbidden();

$notifications = new SofNotificationService($db);
$imports = new SofImportService($db);
$industrial = new SofAgenceIndustrialService($db);
$action = GETPOST('action', 'aZ09');
$retentionPreview = null;

function agence_industrial_uploaded_csv($name, &$filename, &$error)
{
	$filename = '';
	$error = '';
	if (empty($_FILES[$name]) || !is_array($_FILES[$name]) || (int) $_FILES[$name]['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES[$name]['tmp_name'])) {
		$error = 'Fichier CSV absent ou transfert incomplet.';
		return false;
	}
	$filename = basename((string) $_FILES[$name]['name']);
	$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	if (!in_array($extension, array('csv', 'txt'), true) || (int) $_FILES[$name]['size'] <= 0 || (int) $_FILES[$name]['size'] > 20 * 1024 * 1024) {
		$error = 'Seuls les fichiers CSV/TXT de 20 Mo maximum sont acceptés.';
		return false;
	}
	$content = file_get_contents($_FILES[$name]['tmp_name']);
	if ($content === false) {
		$error = 'Lecture du fichier importé impossible.';
		return false;
	}
	return $content;
}

/** Restrict a list query to the agencies currently assigned to the user. */
function agence_industrial_agency_scope_sql($field)
{
	global $db, $user;
	$allowed = SofAgenceService::allowedAgencyIds($db, $user);
	if ($allowed === null) return '';
	if (empty($allowed)) return ' AND 1=0';
	return ' AND '.$field.' IN ('.implode(',', array_map('intval', $allowed)).')';
}

/** Render a retention policy code as a readable business label. */
function agence_industrial_policy_label($code)
{
	global $langs;
	if (preg_match('/^(AUDIT|DOCUMENT|TECH_ERROR)_([0-9]+)D$/', (string) $code, $matches)) {
		$objects = array('AUDIT'=>'AuditLogEntry', 'DOCUMENT'=>'Document', 'TECH_ERROR'=>'TechnicalError');
		return $langs->trans('RetentionPolicyForDays', $langs->trans($objects[$matches[1]]), (int) $matches[2]);
	}
	return $langs->trans('CustomRetentionPolicy');
}

/** Return readable agency options restricted to the current user's scope. */
function agence_industrial_agency_options($selected = 0)
{
	global $db, $conf, $user, $langs;
	$allowed = SofAgenceService::allowedAgencyIds($db, $user);
	$sql = 'SELECT rowid, ref, label FROM '.$db->prefix().'sof_agence WHERE entity='.((int) $conf->entity).' AND status IN (1,4)';
	if ($allowed !== null) $sql .= empty($allowed) ? ' AND 1=0' : ' AND rowid IN ('.implode(',', array_map('intval', $allowed)).')';
	$sql .= ' ORDER BY label, ref';
	$resql = $db->query($sql);
	$html = '<option value="0">'.$langs->trans('None').'</option>';
	while ($resql && ($row = $db->fetch_object($resql))) $html .= '<option value="'.((int) $row->rowid).'"'.((int) $selected === (int) $row->rowid ? ' selected' : '').'>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</option>';
	return $html;
}

/** Return readable active Dolibarr bank-account options for the current entity. */
function agence_industrial_bank_account_options($selected = 0)
{
	global $db, $conf, $langs;
	$sql = 'SELECT rowid, ref, label FROM '.$db->prefix().'bank_account WHERE entity='.((int) $conf->entity).' AND clos=0 ORDER BY label, ref';
	$resql = $db->query($sql);
	$html = '<option value="0">'.$langs->trans('NotApplicable').'</option>';
	while ($resql && ($row = $db->fetch_object($resql))) $html .= '<option value="'.((int) $row->rowid).'"'.((int) $selected === (int) $row->rowid ? ' selected' : '').'>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</option>';
	return $html;
}

/** Return reversible cash movements as readable references instead of numeric ids. */
function agence_industrial_movement_options($selected = 0)
{
	global $db, $conf, $user, $langs;
	$allowed = SofAgenceService::allowedAgencyIds($db, $user);
	$sql = 'SELECT rowid, ref, label, amount, transaction_date FROM '.$db->prefix().'sof_caisse_mouvement WHERE entity='.((int) $conf->entity).' AND status=1';
	if ($allowed !== null) $sql .= empty($allowed) ? ' AND 1=0' : ' AND fk_agence IN ('.implode(',', array_map('intval', $allowed)).')';
	$sql .= ' ORDER BY transaction_date DESC, rowid DESC'.$db->plimit(500, 0);
	$resql = $db->query($sql);
	$html = '<option value="">'.$langs->trans('SelectCashMovement').'</option>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		$display = $row->ref.' — '.($row->label ?: $langs->trans('CashMovement')).' — '.price($row->amount);
		$html .= '<option value="'.((int) $row->rowid).'"'.((int) $selected === (int) $row->rowid ? ' selected' : '').'>'.dol_escape_htmltag($display).'</option>';
	}
	return $html;
}

if ($action !== '') {
	if (!GETPOST('token') || GETPOST('token') !== $_SESSION['newtoken']) accessforbidden('Invalid CSRF token');
	$result = -1;
	if ($action === 'save_notification' && ($user->admin || $user->hasRight('agence', 'notification', 'manage'))) {
		$result = $notifications->saveConfiguration($user, array(
			'event_code' => GETPOST('event_code', 'restricthtml'), 'severity_min' => GETPOST('severity_min', 'alpha'),
			'channel' => GETPOST('channel', 'alpha'), 'recipient_type' => GETPOST('recipient_type', 'alpha'),
			'recipient' => GETPOST('recipient', 'restricthtml'), 'escalation_level' => GETPOST('escalation_level', 'int'),
		));
	} elseif ($action === 'disable_notification' && ($user->admin || $user->hasRight('agence', 'notification', 'manage'))) {
		$result = $notifications->disableConfiguration($user, GETPOST('id', 'int'));
	} elseif ($action === 'process_notifications' && ($user->admin || $user->hasRight('agence', 'notification', 'manage'))) {
		$result = $notifications->runEscalations();
		if ($result >= 0) $result = $notifications->synchronizeCollections();
		if ($result >= 0) $result = $notifications->processQueue(200);
	} elseif ($action === 'import_statement' && ($user->admin || $user->hasRight('agence', 'bankimport', 'import'))) {
		$filename = $uploadError = '';
		$content = agence_industrial_uploaded_csv('statement_file', $filename, $uploadError);
		$result = $content === false ? -1 : $imports->importStatement($user, GETPOST('source_type', 'alpha'), $filename, $content, GETPOST('fk_bank_account', 'int'), GETPOST('fk_agence', 'int'));
		if ($content === false) $imports->error = $uploadError;
	} elseif ($action === 'confirm_match' && ($user->admin || $user->hasRight('agence', 'bankimport', 'reconcile'))) {
		$result = $imports->confirmMatch($user, GETPOST('line_id', 'int'), GETPOST('target_id', 'int'));
	} elseif ($action === 'import_master' && ($user->admin || $user->hasRight('agence', 'bulkimport', 'run'))) {
		$filename = $uploadError = '';
		$content = agence_industrial_uploaded_csv('master_file', $filename, $uploadError);
		$result = $content === false ? -1 : $imports->importMasterData($user, GETPOST('object_type', 'alpha'), $filename, $content, GETPOST('import_mode', 'alpha'));
		if ($content === false) $imports->error = $uploadError;
	} elseif ($action === 'sync_collections' && ($user->admin || $user->hasRight('agence', 'recouvrement', 'manage'))) {
		$result = $notifications->synchronizeCollections();
	} elseif ($action === 'collection_action' && ($user->admin || $user->hasRight('agence', 'recouvrement', 'manage'))) {
		$result = $notifications->addCollectionAction($user, GETPOST('case_id', 'int'), GETPOST('action_type', 'alpha'), GETPOST('channel', 'alpha'), GETPOST('outcome', 'alpha'), GETPOST('notes', 'restricthtml'), GETPOST('next_action_date', 'alpha'), GETPOST('promise_date', 'alpha'), GETPOST('promise_amount', 'alphanohtml'));
	} elseif ($action === 'retry_error' && ($user->admin || $user->hasRight('agence', 'technicalerror', 'manage'))) {
		$result = $notifications->retryTechnicalError(GETPOST('error_id', 'int'), $user);
	} elseif ($action === 'request_reversal' && ($user->admin || $user->hasRight('agence', 'reversal', 'request'))) {
		$result = $industrial->requestReversal($user, GETPOST('movement_id', 'int'), GETPOST('reason', 'restricthtml'), GETPOST('evidence_ref', 'restricthtml'));
	} elseif ($action === 'decide_reversal' && ($user->admin || $user->hasRight('agence', 'reversal', 'approve'))) {
		$result = $industrial->decideReversal($user, GETPOST('reversal_id', 'int'), GETPOST('decision', 'alpha') === 'approve', GETPOST('decision_reason', 'restricthtml'));
	} elseif ($action === 'retention_preview' && ($user->admin || $user->hasRight('agence', 'archive', 'manage'))) {
		$retentionPreview = $industrial->applyRetention($user, true, false);
		$result = is_array($retentionPreview) ? 1 : -1;
	} elseif ($action === 'retention_archive' && ($user->admin || $user->hasRight('agence', 'archive', 'manage'))) {
		$retentionPreview = $industrial->applyRetention($user, false, false);
		$result = is_array($retentionPreview) ? 1 : -1;
	} elseif ($action === 'retention_purge' && ($user->admin || $user->hasRight('agence', 'archive', 'manage'))) {
		if (GETPOST('confirm_text', 'alpha') !== 'PURGER' || !getDolGlobalInt('AGENCE_ENABLE_PURGE')) {
			$industrial->error = 'La purge exige le mot PURGER et le paramètre global d’autorisation.';
			$result = -1;
		} else {
			$retentionPreview = $industrial->applyRetention($user, false, true);
			$result = is_array($retentionPreview) ? 1 : -1;
		}
	}
	$serviceError = $notifications->error ?: ($imports->error ?: $industrial->error);
	if ($result >= 0) setEventMessages($langs->trans('OperationCompletedAndLogged'), null, 'mesgs');
	else setEventMessages($serviceError ?: $langs->trans('OperationRejectedOrFailed'), null, 'errors');
}

llxHeader('', $langs->trans('AgencyIndustrialOperations'), '', '', 0, 0, '', '', '', 'mod-agence page-industrial');
print load_fiche_titre($langs->trans('AgencyIndustrialOperations'), '', 'gears');
$tabs = array(
	'notifications' => 'NotificationsAndEscalations', 'imports' => 'ImportsAndReconciliations', 'collections' => 'DebtCollection',
	'errors' => 'ErrorsAndRetries', 'reversals' => 'FinancialReversals', 'retention' => 'ArchivingAndPurge',
);
print '<div class="tabs">';
foreach ($tabs as $key => $label) print '<a class="tab'.($section === $key ? ' tabactive' : '').'" href="?section='.$key.'">'.dol_escape_htmltag($langs->trans($label)).'</a>';
print '</div>';

if ($section === 'notifications') {
	print load_fiche_titre($langs->trans('MultichannelRules'), '', 'bell');
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_notification"><input type="hidden" name="section" value="notifications">';
	print '<table class="border centpercent"><tr><td>'.$langs->trans('Event').'</td><td><select required class="flat minwidth300" name="event_code">';
	foreach (array('*','critical_alert','validation_overdue','collection_reminder1','collection_reminder2','collection_formal_notice','collection_dispute','financial_reversal_requested','financial_reversal_approved','financial_reversal_rejected','cash_closure_completed','validation_decided','refund_completed','bank_deposit_completed','alert_created') as $eventCode) print '<option value="'.dol_escape_htmltag($eventCode).'">'.dol_escape_htmltag(agence_translate_business_code('event_code', $eventCode)).'</option>';
	print '</select></td><td>'.$langs->trans('MinimumSeverity').'</td><td><select name="severity_min"><option value="info">'.$langs->trans('InformationSeverity').'</option><option value="warning">'.$langs->trans('WarningSeverity').'</option><option value="critical">'.$langs->trans('CriticalSeverity').'</option></select></td></tr>';
	print '<tr><td>'.$langs->trans('Channel').'</td><td><select name="channel"><option value="internal">'.$langs->trans('InternalChannel').'</option><option value="email">'.$langs->trans('EmailChannel').'</option><option value="sms">'.$langs->trans('SmsChannel').'</option></select></td><td>'.$langs->trans('RecipientType').'</td><td><select name="recipient_type"><option value="address">'.$langs->trans('AddressOrNumber').'</option><option value="user">'.$langs->trans('DolibarrUser').'</option><option value="role">'.$langs->trans('AgencyRole').'</option></select></td></tr>';
	print '<tr><td>'.$langs->trans('Recipient').'</td><td><input required class="flat minwidth300" name="recipient"></td><td>'.$langs->trans('EscalationLevel').'</td><td><input type="number" min="0" max="3" name="escalation_level" value="0"></td></tr></table><div class="center"><button class="button" type="submit">'.$langs->trans('SaveRule').'</button></div></form>';
	print '<form method="POST" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="process_notifications"><input type="hidden" name="section" value="notifications"><button class="button" type="submit">'.$langs->trans('RunNow').'</button></form>';
	$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_notification_config WHERE entity = '.((int) $conf->entity).' ORDER BY status DESC,event_code,channel,recipient');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Event').'</th><th>'.$langs->trans('Channel').'</th><th>'.$langs->trans('Recipient').'</th><th>'.$langs->trans('EscalationLevel').'</th><th>'.$langs->trans('Status').'</th><th></th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(agence_translate_business_code('event_code', $row->event_code)).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('channel', $row->channel)).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('recipient_type', $row->recipient_type).' — '.$row->recipient).'</td><td>'.((int) $row->escalation_level).'</td><td>'.$langs->trans((int) $row->status ? 'Active' : 'Inactive').'</td><td>';
		if ((int) $row->status) print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="notifications"><input type="hidden" name="action" value="disable_notification"><input type="hidden" name="id" value="'.((int) $row->rowid).'"><button class="button smallpaddingimp">'.$langs->trans('Disable').'</button></form>';
		print '</td></tr>';
	}
	print '</table>';
	$resql = $db->query('SELECT ref,event_code,severity,channel,recipient,attempts,last_error,status,date_creation,date_sent FROM '.$db->prefix().'sof_notification_outbox WHERE entity = '.((int) $conf->entity).' ORDER BY rowid DESC'.$db->plimit(100, 0));
	print load_fiche_titre($langs->trans('NotificationQueueAndInternalChannel'), '', 'inbox');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Event').'</th><th>'.$langs->trans('ChannelAndRecipient').'</th><th>'.$langs->trans('Attempts').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Error').'</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('event_code', $row->event_code).' / '.agence_translate_business_code('severity', $row->severity)).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('channel', $row->channel).' / '.$row->recipient).'</td><td>'.((int) $row->attempts).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('notification_status', $row->status)).'</td><td>'.dol_escape_htmltag(dol_trunc($row->last_error, 120)).'</td></tr>';
	print '</table>';
} elseif ($section === 'imports') {
	print load_fiche_titre($langs->trans('ImportFinancialStatement'), '', 'file-import');
	print '<form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="imports"><input type="hidden" name="action" value="import_statement"><table class="border centpercent">';
	print '<tr><td>'.$langs->trans('Source').'</td><td><select name="source_type"><option value="bank">'.$langs->trans('Bank').'</option><option value="orange_money">'.$langs->trans('OrangeMoney').'</option><option value="mobile_money">'.$langs->trans('MobileMoney').'</option></select></td><td>'.$langs->trans('DolibarrBankAccount').'</td><td><select class="flat minwidth300" name="fk_bank_account">'.agence_industrial_bank_account_options(GETPOST('fk_bank_account', 'int')).'</select></td></tr>';
	print '<tr><td>'.$langs->trans('OptionalAgency').'</td><td><select class="flat minwidth300" name="fk_agence">'.agence_industrial_agency_options(GETPOST('fk_agence', 'int')).'</select></td><td>'.$langs->trans('FinancialStatementCsv').'</td><td><input required type="file" accept=".csv,.txt,text/csv" name="statement_file"></td></tr></table><div class="center"><button class="button">'.$langs->trans('ImportAndSuggestMatches').'</button></div></form>';
	print load_fiche_titre($langs->trans('BulkReferenceDataUpdate'), '', 'upload');
	print '<form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="imports"><input type="hidden" name="action" value="import_master"><table class="border centpercent"><tr><td>'.$langs->trans('ReferenceData').'</td><td><select name="object_type"><option value="agency">'.$langs->trans('Agencies').'</option><option value="cashdesk">'.$langs->trans('CashDesks').'</option><option value="das">'.$langs->trans('DAS').'</option><option value="assignment">'.$langs->trans('Assignments').'</option></select></td><td>'.$langs->trans('ImportMode').'</td><td><select name="import_mode"><option value="upsert">'.$langs->trans('CreateOrUpdate').'</option><option value="create">'.$langs->trans('CreateOnly').'</option><option value="update">'.$langs->trans('UpdateOnly').'</option></select></td></tr><tr><td>CSV</td><td colspan="3"><input required type="file" accept=".csv,.txt,text/csv" name="master_file"></td></tr></table><div class="center"><button class="button">'.$langs->trans('RunLoggedImport').'</button></div></form>';
	$resql = $db->query('SELECT l.*,i.ref import_ref,i.source_type FROM '.$db->prefix().'sof_bank_import_line l JOIN '.$db->prefix().'sof_bank_import i ON i.rowid=l.fk_import AND i.entity=l.entity WHERE l.entity='.((int) $conf->entity).' AND l.status IN (0,1)'.agence_industrial_agency_scope_sql('i.fk_agence').' ORDER BY l.rowid DESC'.$db->plimit(200, 0));
	print load_fiche_titre($langs->trans('SuggestedReconciliations'), '', 'link');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Import').'</th><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Amount').'</th><th>'.$langs->trans('MatchScoreAndReason').'</th><th>'.$langs->trans('Target').'</th><th></th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		$target = $row->source_type === 'bank' ? (int) $row->fk_bank : (int) $row->fk_mouvement;
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->import_ref.' / '.agence_translate_business_code('connector_type', $row->source_type)).'</td><td>'.dol_escape_htmltag($row->operation_date).'</td><td>'.dol_escape_htmltag($row->external_ref).'</td><td>'.price($row->amount).'</td><td>'.((int) $row->match_score).'% '.dol_escape_htmltag($row->match_reason).'</td><td>'.($target ?: '-').'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="imports"><input type="hidden" name="action" value="confirm_match"><input type="hidden" name="line_id" value="'.((int) $row->rowid).'"><input class="width75" type="number" min="1" name="target_id" value="'.($target ?: '').'"><button class="button smallpaddingimp">'.$langs->trans('Confirm').'</button></form></td></tr>';
	}
	print '</table>';
} elseif ($section === 'collections') {
	print '<form method="POST" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="collections"><input type="hidden" name="action" value="sync_collections"><button class="button">'.$langs->trans('SynchronizeOverdueReceivables').'</button></form>';
	$resql = $db->query('SELECT r.*,s.nom FROM '.$db->prefix().'sof_recouvrement r JOIN '.$db->prefix().'societe s ON s.rowid=r.fk_soc WHERE r.entity='.((int) $conf->entity).agence_industrial_agency_scope_sql('r.fk_agence').' ORDER BY r.status,r.priority DESC,r.next_action_date'.$db->plimit(500, 0));
	print load_fiche_titre($langs->trans('DebtCollectionWorkflow'), '', 'comments-dollar');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Case').'</th><th>'.$langs->trans('Customer').'</th><th>'.$langs->trans('Stage').'</th><th>'.$langs->trans('Priority').'</th><th>'.$langs->trans('OutstandingBalance').'</th><th>'.$langs->trans('NextAction').'</th><th>'.$langs->trans('DocumentedAction').'</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag($row->nom).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('collection_stage', $row->stage)).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('urgency', $row->priority)).'</td><td>'.price($row->outstanding_amount).'</td><td>'.dol_escape_htmltag($row->next_action_date).'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="collections"><input type="hidden" name="action" value="collection_action"><input type="hidden" name="case_id" value="'.((int) $row->rowid).'"><select name="action_type"><option value="call">'.$langs->trans('PhoneCall').'</option><option value="email">'.$langs->trans('EmailChannel').'</option><option value="sms">'.$langs->trans('SmsChannel').'</option><option value="visit">'.$langs->trans('CustomerVisit').'</option><option value="formal_notice">'.$langs->trans('FormalNotice').'</option><option value="promise">'.$langs->trans('PaymentPromise').'</option><option value="dispute">'.$langs->trans('Dispute').'</option><option value="close">'.$langs->trans('Close').'</option></select><select name="channel"><option value="phone">'.$langs->trans('PhoneChannel').'</option><option value="email">'.$langs->trans('EmailChannel').'</option><option value="sms">'.$langs->trans('SmsChannel').'</option><option value="internal">'.$langs->trans('InternalChannel').'</option></select><input required name="notes" placeholder="'.$langs->trans('ReportAndEvidence').'"><input type="date" name="next_action_date" aria-label="'.$langs->trans('NextActionDate').'"><input type="date" name="promise_date" aria-label="'.$langs->trans('PromiseDate').'"><input class="width75" name="promise_amount" placeholder="'.$langs->trans('PromiseAmount').'"><button class="button smallpaddingimp">'.$langs->trans('LogAction').'</button></form></td></tr>';
	}
	print '</table>';
} elseif ($section === 'errors') {
	$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_technical_error WHERE entity='.((int) $conf->entity).' ORDER BY status,rowid DESC'.$db->plimit(500, 0));
	print load_fiche_titre($langs->trans('TechnicalErrorLog'), '', 'triangle-exclamation');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('OperationType').'</th><th>'.$langs->trans('Object').'</th><th>'.$langs->trans('Error').'</th><th>'.$langs->trans('Attempts').'</th><th>'.$langs->trans('Status').'</th><th></th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('operation_code', $row->operation_code)).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('object_type', $row->object_type).' — '.$langs->trans('ReferenceNumber', (int) $row->object_id)).'</td><td>'.dol_escape_htmltag(dol_trunc($row->error_message, 180)).'</td><td>'.((int) $row->attempts).'/'.((int) $row->max_attempts).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('technical_error_status', $row->status)).'</td><td>';
		if (in_array((int) $row->status, array(0,1), true) && (int) $row->attempts < (int) $row->max_attempts) print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="errors"><input type="hidden" name="action" value="retry_error"><input type="hidden" name="error_id" value="'.((int) $row->rowid).'"><button class="button smallpaddingimp">'.$langs->trans('Retry').'</button></form>';
		print '</td></tr>';
	}
	print '</table>';
} elseif ($section === 'reversals') {
	print load_fiche_titre($langs->trans('RequestFinancialReversal'), '', 'rotate-left');
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="reversals"><input type="hidden" name="action" value="request_reversal"><table class="border centpercent"><tr><td>'.$langs->trans('CashMovement').'</td><td><select required class="flat minwidth500" name="movement_id">'.agence_industrial_movement_options(GETPOST('movement_id', 'int')).'</select></td><td>'.$langs->trans('EvidenceReference').'</td><td><input class="minwidth200" name="evidence_ref"></td></tr><tr><td>'.$langs->trans('DetailedReason').'</td><td colspan="3"><textarea required class="quatrevingtpercent" name="reason"></textarea></td></tr></table><div class="center"><button class="button">'.$langs->trans('SubmitForApproval').'</button></div></form>';
	$resql = $db->query('SELECT r.*,m.ref movement_ref,m.amount,m.payment_mode FROM '.$db->prefix().'sof_financial_reversal r JOIN '.$db->prefix().'sof_caisse_mouvement m ON m.rowid=r.fk_mouvement_original AND m.entity=r.entity WHERE r.entity='.((int) $conf->entity).agence_industrial_agency_scope_sql('m.fk_agence').' ORDER BY r.rowid DESC'.$db->plimit(500, 0));
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Request').'</th><th>'.$langs->trans('CashMovement').'</th><th>'.$langs->trans('Amount').'</th><th>'.$langs->trans('Reason').'</th><th>'.$langs->trans('Evidence').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Decision').'</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag($row->movement_ref).'</td><td>'.price($row->amount).' '.dol_escape_htmltag(agence_translate_business_code('payment_mode', $row->payment_mode)).'</td><td>'.dol_escape_htmltag($row->reason).'</td><td>'.dol_escape_htmltag($row->evidence_ref).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('reversal_status', $row->status)).'</td><td>';
		if ((int) $row->status === 0 && ($user->admin || $user->hasRight('agence', 'reversal', 'approve'))) print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="reversals"><input type="hidden" name="action" value="decide_reversal"><input type="hidden" name="reversal_id" value="'.((int) $row->rowid).'"><select name="decision"><option value="approve">'.$langs->trans('Approve').'</option><option value="reject">'.$langs->trans('Reject').'</option></select><input required name="decision_reason" placeholder="'.$langs->trans('DecisionReason').'"><button class="button smallpaddingimp">'.$langs->trans('Decide').'</button></form>';
		print '</td></tr>';
	}
	print '</table>';
} else {
	print load_fiche_titre($langs->trans('RetentionPolicy'), '', 'box-archive');
	print '<div class="info">'.$langs->trans('RetentionSummary', getDolGlobalInt('AGENCE_AUDIT_RETENTION_DAYS',3650), getDolGlobalInt('AGENCE_DOCUMENT_RETENTION_DAYS',3650), getDolGlobalInt('AGENCE_TECH_ERROR_RETENTION_DAYS',730), $langs->trans(getDolGlobalInt('AGENCE_ENABLE_PURGE') ? 'Authorized' : 'Disabled')).'</div>';
	foreach (array('retention_preview'=>'Preview','retention_archive'=>'ArchiveExpiredItems') as $retentionAction => $label) print '<form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="retention"><input type="hidden" name="action" value="'.$retentionAction.'"><button class="button">'.$langs->trans($label).'</button></form> ';
	print '<form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="retention"><input type="hidden" name="action" value="retention_purge"><input name="confirm_text" placeholder="'.$langs->trans('EnterPurgeConfirmation').'"><button class="buttonDelete">'.$langs->trans('PurgeExpiredArchivesPermanently').'</button></form>';
	if (is_array($retentionPreview)) {
		print '<table class="border centpercent"><tr class="liste_titre"><th>'.$langs->trans('Indicator').'</th><th>'.$langs->trans('Number').'</th></tr>';
		$retentionLabels = array('audits_to_archive'=>'AuditsToArchive', 'audits_archived'=>'AuditsArchived', 'audits_purged'=>'AuditsPurged', 'errors_purged'=>'TechnicalErrorsPurged', 'documents_to_archive'=>'DocumentsToArchive', 'documents_archived'=>'DocumentsArchived', 'documents_purged'=>'DocumentsPurged');
		foreach ($retentionPreview as $key => $value) print '<tr><td>'.dol_escape_htmltag(isset($retentionLabels[$key]) ? $langs->trans($retentionLabels[$key]) : $langs->trans('Other')).'</td><td>'.((int) $value).'</td></tr>';
		print '</table>';
	}
	$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_archive_log WHERE entity='.((int) $conf->entity).' ORDER BY rowid DESC'.$db->plimit(200, 0));
	print load_fiche_titre($langs->trans('RetentionLog'), '', 'history');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Object').'</th><th>'.$langs->trans('Policy').'</th><th>'.$langs->trans('Action').'</th><th>'.$langs->trans('Fingerprint').'</th><th>'.$langs->trans('Reason').'</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->action_date).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('object_type', $row->object_type).' — '.$langs->trans('ReferenceNumber', (int) $row->object_id)).'</td><td>'.dol_escape_htmltag(agence_industrial_policy_label($row->policy_code)).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('archive_action', $row->action_type)).'</td><td>'.dol_escape_htmltag(substr($row->content_hash,0,16)).'…</td><td>'.dol_escape_htmltag($row->reason).'</td></tr>';
	print '</table>';
}

llxFooter();
$db->close();
