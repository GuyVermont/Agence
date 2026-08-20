<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';

$langs->loadLangs(array('agence@agence', 'admin', 'banks'));
$canAccess = !empty($user->admin)
	|| $user->hasRight('agence', 'webhook', 'manage') || $user->hasRight('agence', 'webhook', 'replay')
	|| $user->hasRight('agence', 'connector', 'manage') || $user->hasRight('agence', 'connector', 'sync')
	|| $user->hasRight('agence', 'bi', 'export') || $user->hasRight('agence', 'configtransfer', 'export') || $user->hasRight('agence', 'configtransfer', 'import');
if (!$canAccess || !SofAgenceService::isActiveUser($db, $user)) accessforbidden();

$service = new SofIntegrationService($db);
$action = GETPOST('action', 'aZ09');
if ($action !== '') {
	if (!GETPOST('token') || GETPOST('token') !== $_SESSION['newtoken']) accessforbidden('Invalid CSRF token');
	$result = -1;
	if ($action === 'save_webhook') {
		$requestedEvents = GETPOST('events', 'array');
		$requestedEvents = is_array($requestedEvents) ? array_values(array_intersect(array_keys(SofIntegrationService::EVENTS), array_map('strval', $requestedEvents))) : array();
		$result = $service->saveWebhook($user, array(
			'id'=>GETPOST('id','int'), 'ref'=>GETPOST('ref','alphanohtml'), 'label'=>GETPOST('label','restricthtml'),
			'endpoint_url'=>GETPOST('endpoint_url','restricthtml'), 'event_filter'=>implode(',', $requestedEvents),
			'fk_agence'=>GETPOST('fk_agence','int'), 'secret'=>GETPOST('secret','none'), 'max_attempts'=>GETPOST('max_attempts','int'), 'status'=>GETPOST('status','int'),
		));
	} elseif ($action === 'process_webhooks') {
		if (empty($user->admin) && !$user->hasRight('agence', 'webhook', 'replay')) accessforbidden();
		$result = $service->processWebhooks(200);
	} elseif ($action === 'replay_webhook') {
		$result = $service->replayWebhook($user, GETPOST('delivery_id','int'));
	} elseif ($action === 'save_connector') {
		$result = $service->saveConnector($user, array(
			'id'=>GETPOST('id','int'), 'ref'=>GETPOST('ref','alphanohtml'), 'label'=>GETPOST('label','restricthtml'),
			'connector_type'=>GETPOST('connector_type','alpha'), 'endpoint_url'=>GETPOST('endpoint_url','restricthtml'),
			'auth_type'=>GETPOST('auth_type','alpha'), 'credential'=>GETPOST('credential','none'), 'fk_agence'=>GETPOST('fk_agence','int'),
			'fk_bank_account'=>GETPOST('fk_bank_account','int'), 'polling_minutes'=>GETPOST('polling_minutes','int'), 'status'=>GETPOST('status','int'),
		));
	} elseif ($action === 'sync_connector') {
		$sync = $service->syncConnector($user, GETPOST('connector_id','int'));
		$result = is_array($sync) ? 1 : -1;
	} elseif ($action === 'export_config') {
		$package = $service->exportConfiguration($user, GETPOST('environment','alpha'));
		if (is_array($package)) {
			header('Content-Type: application/json; charset=UTF-8');
			header('Content-Disposition: attachment; filename="powererp-agence-configuration-'.date('Ymd-His').'.json"');
			echo json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}
	} elseif ($action === 'import_config') {
		if (empty($_FILES['config_file']) || (int) $_FILES['config_file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['config_file']['tmp_name']) || (int) $_FILES['config_file']['size'] > 5 * 1024 * 1024) {
			$service->error = 'Paquet JSON absent, invalide ou supérieur à 5 Mo.';
		} else {
			$package = json_decode((string) file_get_contents($_FILES['config_file']['tmp_name']), true);
			$summary = is_array($package) ? $service->importConfiguration($user, $package, GETPOST('target_environment','alpha'), GETPOST('dry_run','int') === 1) : -1;
			$result = is_array($summary) ? 1 : -1;
		}
	} elseif ($action === 'bi_export') {
		$export = $service->incrementalExport($user, GETPOST('dataset','alpha'), GETPOST('cursor','alphanohtml'), GETPOST('limit','int'), GETPOST('fk_agence','int'));
		if (is_array($export)) {
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename="agence-bi-'.dol_sanitizeFileName($export['dataset']).'-'.date('Ymd-His').'.csv"');
			$output = fopen('php://output', 'w');
			if (!empty($export['rows'])) {
				fputcsv($output, agence_integration_bi_headers(array_keys($export['rows'][0])), ';');
				foreach ($export['rows'] as $row) fputcsv($output, $row, ';');
			}
			fclose($output);
			exit;
		}
	}
	if ($result >= 0) setEventMessages($langs->trans('IntegrationOperationCompleted'), null, 'mesgs');
	else setEventMessages($service->error ?: $langs->trans('IntegrationOperationFailed'), null, 'errors');
}

$scope = SofAgenceService::allowedAgencyIds($db, $user);
$agencySql = 'SELECT rowid,ref,label FROM '.$db->prefix().'sof_agence WHERE entity='.(int) $conf->entity.' AND status=1';
if ($scope !== null) $agencySql .= $scope ? ' AND rowid IN ('.implode(',', array_map('intval',$scope)).')' : ' AND 1=0';
$agencySql .= ' ORDER BY ref';
$agencyResult = $db->query($agencySql); $agencies = array(); while ($agencyResult && ($row=$db->fetch_object($agencyResult))) $agencies[]=$row;
$bankResult = $db->query('SELECT rowid,ref,label FROM '.$db->prefix().'bank_account WHERE entity='.(int) $conf->entity.' AND clos=0 ORDER BY ref');
$bankAccounts = array(); while ($bankResult && ($row=$db->fetch_object($bankResult))) $bankAccounts[]=$row;

llxHeader('', $langs->trans('PowerERPIntegrations'), '', '', 0, 0, '', '', '', 'mod-agence page-integrations');
print load_fiche_titre($langs->trans('PowerERPIntegrations'), '', 'plug');
print '<div class="info">'.$langs->trans('AuthenticatedRestApi').' : <code>'.dol_escape_htmltag(DOL_URL_ROOT.'/api/index.php/agence').'</code>. '.$langs->trans('IntegrationSecretsProtection').'</div>';

function agence_integration_agency_options($agencies, $allowGlobal = true)
{
	global $langs;
	$html = $allowGlobal ? '<option value="0">'.$langs->trans('AllAuthorizedAgencies').'</option>' : '<option value="0">'.$langs->trans('SelectAgency').'</option>';
	foreach ($agencies as $agency) $html .= '<option value="'.(int) $agency->rowid.'">'.dol_escape_htmltag($agency->ref.' — '.$agency->label).'</option>';
	return $html;
}

function agence_integration_event_labels($filter)
{
	global $langs;
	$labels = array('cash_closure.completed'=>'CashClosureCompletedEvent', 'validation.decided'=>'ValidationDecidedEvent', 'refund.completed'=>'RefundCompletedEvent', 'bank_deposit.completed'=>'BankDepositCompletedEvent', 'alert.created'=>'AlertCreatedEvent');
	$out = array();
	foreach (preg_split('/[,;\s]+/', (string) $filter, -1, PREG_SPLIT_NO_EMPTY) as $code) $out[] = isset($labels[$code]) ? $langs->trans($labels[$code]) : $code;
	return implode(', ', $out);
}

/** Translate the public BI export columns while preserving their order. */
function agence_integration_bi_headers($fields)
{
	global $langs;
	$labels = array(
		'rowid'=>'RecordNumber', 'ref'=>'Ref', 'fk_agence'=>'Agency', 'fk_caisse'=>'CashDesk', 'fk_caisse_source'=>'SourceCashDesk', 'fk_session'=>'CashSession', 'fk_das'=>'DAS', 'fk_soc'=>'ThirdParty', 'fk_facture'=>'Invoice', 'fk_facture_origin'=>'OriginInvoice', 'fk_user_cashier'=>'Cashier', 'fk_bank_account'=>'BankAccount', 'fk_bank'=>'BankLine',
		'type_operation'=>'OperationType', 'direction'=>'Direction', 'payment_mode'=>'PaymentMode', 'amount'=>'Amount', 'transaction_date'=>'TransactionDate', 'transaction_ref'=>'TransactionRef', 'session_type'=>'SessionType', 'date_opening'=>'OpeningDate', 'date_closing'=>'ClosingDate', 'date_validation'=>'ValidationDate', 'opening_amount'=>'OpeningAmount', 'theoretical_amount'=>'TheoreticalAmount', 'physical_amount'=>'PhysicalAmount', 'gap_amount'=>'GapAmount', 'requested_amount'=>'RequestedAmount', 'approved_amount'=>'ApprovedAmount', 'refunded_amount'=>'RefundedAmount', 'request_date'=>'RequestDate', 'validation_date'=>'ValidationDate', 'execution_date'=>'ExecutionDate', 'currency_code'=>'Currency', 'date_preparation'=>'PreparationDate', 'date_deposit'=>'DepositDate', 'date_reconcile'=>'ReconcileDate', 'bank_slip_number'=>'BankSlipNumber', 'reconcile_reference'=>'ReconcileReference', 'alert_type'=>'AlertType', 'severity'=>'Severity', 'object_type'=>'ObjectType', 'object_id'=>'ObjectId', 'message'=>'Message', 'escalation_level'=>'EscalationLevel', 'date_alert'=>'AlertDate', 'date_close'=>'ClosingDate', 'status'=>'Status', 'accounting_status'=>'AccountingStatus', 'freeze_status'=>'FreezeStatus',
	);
	$out = array();
	foreach ($fields as $field) $out[] = isset($labels[$field]) ? $langs->trans($labels[$field]) : $langs->trans('Other');
	return $out;
}

function agence_integration_connector_label($type)
{
	global $langs;
	$labels = array('bank'=>'Bank', 'orange_money'=>'OrangeMoney', 'mobile_money'=>'MobileMoney');
	return isset($labels[$type]) ? $langs->trans($labels[$type]) : (string) $type;
}

if (!empty($user->admin) || $user->hasRight('agence','webhook','manage') || $user->hasRight('agence','webhook','replay')) {
	print load_fiche_titre($langs->trans('SignedWebhooks'), '', 'link');
	if (!empty($user->admin) || $user->hasRight('agence','webhook','manage')) {
		print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_webhook"><table class="border centpercent">';
		print '<tr><td>'.$langs->trans('Ref').'</td><td><input required name="ref" maxlength="64"></td><td>'.$langs->trans('Label').'</td><td><input required class="minwidth200" name="label"></td></tr>';
		print '<tr><td>'.$langs->trans('HttpsUrl').'</td><td colspan="3"><input required class="quatrevingtpercent" type="url" name="endpoint_url" placeholder="https://integration.example/webhooks/agence"></td></tr>';
		print '<tr><td>'.$langs->trans('Events').'</td><td colspan="3"><select required multiple class="quatrevingtpercent" name="events[]" size="5">';
		foreach (array_keys(SofIntegrationService::EVENTS) as $eventCode) print '<option selected value="'.dol_escape_htmltag($eventCode).'">'.dol_escape_htmltag(agence_integration_event_labels($eventCode)).'</option>';
		print '</select><br><span class="opacitymedium">'.$langs->trans('SelectOneOrMoreWebhookEvents').'</span></td></tr>';
		print '<tr><td>'.$langs->trans('Agency').'</td><td><select name="fk_agence">'.agence_integration_agency_options($agencies).'</select></td><td>'.$langs->trans('SecretMinimumLength', 32).'</td><td><input required autocomplete="new-password" type="password" name="secret"></td></tr>';
		print '<tr><td>'.$langs->trans('Attempts').'</td><td><input type="number" min="1" max="20" name="max_attempts" value="8"></td><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="status" value="1" checked></td></tr></table><div class="center"><button class="button">'.$langs->trans('CreateWebhook').'</button></div></form>';
	}
	print '<form method="POST" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="process_webhooks"><button class="button">'.$langs->trans('ProcessQueueNow').'</button></form>';
	$resql=$db->query('SELECT e.ref,e.label,e.endpoint_url,e.event_filter,e.status,COUNT(d.rowid) deliveries,SUM(CASE WHEN d.status=3 THEN 1 ELSE 0 END) failed FROM '.$db->prefix().'sof_webhook_endpoint e LEFT JOIN '.$db->prefix().'sof_webhook_delivery d ON d.fk_endpoint=e.rowid AND d.entity=e.entity WHERE e.entity='.(int)$conf->entity.' GROUP BY e.rowid,e.ref,e.label,e.endpoint_url,e.event_filter,e.status ORDER BY e.ref');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Url').'</th><th>'.$langs->trans('Events').'</th><th>'.$langs->trans('Deliveries').'</th><th>'.$langs->trans('Failures').'</th><th>'.$langs->trans('Status').'</th></tr>';
	while ($resql && ($row=$db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</td><td>'.dol_escape_htmltag($row->endpoint_url).'</td><td>'.dol_escape_htmltag(agence_integration_event_labels($row->event_filter)).'</td><td>'.(int)$row->deliveries.'</td><td>'.(int)$row->failed.'</td><td>'.$langs->trans((int)$row->status?'Active':'Inactive').'</td></tr>';
	print '</table>';
	$resql=$db->query('SELECT d.rowid,d.delivery_ref,d.event_code,d.attempts,d.http_status,d.last_error,d.date_creation FROM '.$db->prefix().'sof_webhook_delivery d WHERE d.entity='.(int)$conf->entity.' AND d.status=3 ORDER BY d.rowid DESC'.$db->plimit(20,0));
	if ($resql && $db->num_rows($resql)>0) { print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('FailedDelivery').'</th><th>'.$langs->trans('Event').'</th><th>HTTP</th><th>'.$langs->trans('Error').'</th><th></th></tr>'; while ($row=$db->fetch_object($resql)) { print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->delivery_ref).'</td><td>'.dol_escape_htmltag(agence_integration_event_labels($row->event_code)).'</td><td>'.(int)$row->http_status.'</td><td>'.dol_escape_htmltag($row->last_error).'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="replay_webhook"><input type="hidden" name="delivery_id" value="'.(int)$row->rowid.'"><button class="button smallpaddingimp">'.$langs->trans('Replay').'</button></form></td></tr>'; } print '</table>'; }
}

if (!empty($user->admin) || $user->hasRight('agence','connector','manage') || $user->hasRight('agence','connector','sync')) {
	print load_fiche_titre($langs->trans('BankAndPaymentConnectors'), '', 'building-columns');
	if (!empty($user->admin) || $user->hasRight('agence','connector','manage')) {
		print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_connector"><table class="border centpercent">';
		print '<tr><td>'.$langs->trans('Ref').'</td><td><input required name="ref"></td><td>'.$langs->trans('Label').'</td><td><input required name="label"></td><td>'.$langs->trans('Type').'</td><td><select name="connector_type"><option value="bank">'.$langs->trans('Bank').'</option><option value="orange_money">'.$langs->trans('OrangeMoney').'</option><option value="mobile_money">'.$langs->trans('MobileMoney').'</option></select></td></tr>';
		print '<tr><td>'.$langs->trans('JsonHttpsUrl').'</td><td colspan="3"><input required class="quatrevingtpercent" type="url" name="endpoint_url"></td><td>'.$langs->trans('Authentication').'</td><td><select name="auth_type"><option value="bearer">'.$langs->trans('BearerTokenAuthentication').'</option><option value="api_key">'.$langs->trans('ApiKeyAuthentication').'</option><option value="basic">'.$langs->trans('BasicAuthentication').'</option><option value="none">'.$langs->trans('None').'</option></select></td></tr>';
		print '<tr><td>'.$langs->trans('Secret').'</td><td><input type="password" autocomplete="new-password" name="credential"></td><td>'.$langs->trans('Agency').'</td><td><select required name="fk_agence">'.agence_integration_agency_options($agencies,false).'</select></td><td>'.$langs->trans('BankAccount').'</td><td><select name="fk_bank_account"><option value="0">'.$langs->trans('NotApplicable').'</option>'; foreach ($bankAccounts as $account) print '<option value="'.(int)$account->rowid.'">'.dol_escape_htmltag($account->ref.' — '.$account->label).'</option>'; print '</select></td></tr>';
		print '<tr><td>'.$langs->trans('PollingFrequencyMinutes').'</td><td><input type="number" min="5" max="10080" name="polling_minutes" value="15"></td><td>'.$langs->trans('Active').'</td><td><input type="checkbox" name="status" value="1" checked></td><td colspan="2"></td></tr></table><div class="center"><button class="button">'.$langs->trans('CreateConnector').'</button></div></form>';
	}
	$resql=$db->query('SELECT c.* ,a.ref agency_ref,ba.ref bank_ref FROM '.$db->prefix().'sof_integration_connector c LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid=c.fk_agence LEFT JOIN '.$db->prefix().'bank_account ba ON ba.rowid=c.fk_bank_account WHERE c.entity='.(int)$conf->entity.' ORDER BY c.ref');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Connector').'</th><th>'.$langs->trans('Type').'</th><th>'.$langs->trans('AgencyAndAccount').'</th><th>'.$langs->trans('LastSynchronization').'</th><th>'.$langs->trans('Cursor').'</th><th>'.$langs->trans('Error').'</th><th></th></tr>';
	while ($resql && ($row=$db->fetch_object($resql))) { print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</td><td>'.dol_escape_htmltag(agence_integration_connector_label($row->connector_type)).'</td><td>'.dol_escape_htmltag($row->agency_ref.' / '.$row->bank_ref).'</td><td>'.dol_escape_htmltag($row->date_last_sync).'</td><td>'.dol_escape_htmltag(substr((string)$row->remote_cursor,0,60)).'</td><td>'.dol_escape_htmltag($row->last_error).'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="sync_connector"><input type="hidden" name="connector_id" value="'.(int)$row->rowid.'"><button class="button smallpaddingimp">'.$langs->trans('Synchronize').'</button></form></td></tr>'; }
	print '</table>';
}

if (!empty($user->admin) || $user->hasRight('agence','bi','export')) {
	print load_fiche_titre($langs->trans('IncrementalBiExport'), '', 'chart-line');
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="bi_export"><table class="border centpercent"><tr><td>'.$langs->trans('Dataset').'</td><td><select name="dataset"><option value="movements">'.$langs->trans('CashMovements').'</option><option value="sessions">'.$langs->trans('CashSessions').'</option><option value="refunds">'.$langs->trans('Refunds').'</option><option value="deposits">'.$langs->trans('BankDeposits').'</option><option value="alerts">'.$langs->trans('Alerts').'</option></select></td><td>'.$langs->trans('Agency').'</td><td><select name="fk_agence">'.agence_integration_agency_options($agencies).'</select></td><td>'.$langs->trans('Limit').'</td><td><input type="number" min="1" max="1000" name="limit" value="250"></td></tr><tr><td>'.$langs->trans('PreviousCursor').'</td><td colspan="5"><input class="quatrevingtpercent" name="cursor" placeholder="'.$langs->trans('EmptyForFirstBatch').'"></td></tr></table><div class="center"><button class="button">'.$langs->trans('DownloadCsvBatch').'</button></div></form>';
}

if (!empty($user->admin) || $user->hasRight('agence','configtransfer','export') || $user->hasRight('agence','configtransfer','import')) {
	print load_fiche_titre($langs->trans('ConfigurationTransport'), '', 'gears');
	if (!empty($user->admin) || $user->hasRight('agence','configtransfer','export')) print '<form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="export_config"><select name="environment"><option value="development">'.$langs->trans('Development').'</option><option value="staging">'.$langs->trans('Staging').'</option><option value="production">'.$langs->trans('Production').'</option></select><button class="button">'.$langs->trans('ExportJsonWithoutSecrets').'</button></form>';
	if (!empty($user->admin) || $user->hasRight('agence','configtransfer','import')) print '<form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import_config"><input required type="file" accept="application/json,.json" name="config_file"><select name="target_environment"><option value="development">'.$langs->trans('Development').'</option><option value="staging">'.$langs->trans('Staging').'</option><option value="production">'.$langs->trans('Production').'</option></select><label><input type="checkbox" name="dry_run" value="1" checked> '.$langs->trans('Simulation').'</label><button class="button">'.$langs->trans('ValidateOrImport').'</button></form>';
}

llxFooter();
$db->close();
