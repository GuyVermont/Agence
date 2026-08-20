<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

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
		$result = $service->saveWebhook($user, array(
			'id'=>GETPOST('id','int'), 'ref'=>GETPOST('ref','alphanohtml'), 'label'=>GETPOST('label','restricthtml'),
			'endpoint_url'=>GETPOST('endpoint_url','restricthtml'), 'event_filter'=>GETPOST('event_filter','restricthtml'),
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
				fputcsv($output, array_keys($export['rows'][0]), ';');
				foreach ($export['rows'] as $row) fputcsv($output, $row, ';');
			}
			fclose($output);
			exit;
		}
	}
	if ($result >= 0) setEventMessages('Opération d’intégration exécutée et tracée.', null, 'mesgs');
	else setEventMessages($service->error ?: 'Opération d’intégration refusée ou en échec.', null, 'errors');
}

$scope = SofAgenceService::allowedAgencyIds($db, $user);
$agencySql = 'SELECT rowid,ref,label FROM '.$db->prefix().'sof_agence WHERE entity='.(int) $conf->entity.' AND status=1';
if ($scope !== null) $agencySql .= $scope ? ' AND rowid IN ('.implode(',', array_map('intval',$scope)).')' : ' AND 1=0';
$agencySql .= ' ORDER BY ref';
$agencyResult = $db->query($agencySql); $agencies = array(); while ($agencyResult && ($row=$db->fetch_object($agencyResult))) $agencies[]=$row;
$bankResult = $db->query('SELECT rowid,ref,label FROM '.$db->prefix().'bank_account WHERE entity='.(int) $conf->entity.' AND clos=0 ORDER BY ref');
$bankAccounts = array(); while ($bankResult && ($row=$db->fetch_object($bankResult))) $bankAccounts[]=$row;

llxHeader('', 'Intégrations PowerERP', '', '', 0, 0, '', '', '', 'mod-agence page-integrations');
print load_fiche_titre('Intégrations PowerERP', '', 'plug');
print '<div class="info">API REST authentifiée : <code>'.dol_escape_htmltag(DOL_URL_ROOT.'/api/index.php/agence').'</code>. Les secrets restent chiffrés sur cette instance et ne sont jamais exportés.</div>';

function agence_integration_agency_options($agencies, $allowGlobal = true)
{
	$html = $allowGlobal ? '<option value="0">Toutes les agences autorisées</option>' : '<option value="0">-- choisir --</option>';
	foreach ($agencies as $agency) $html .= '<option value="'.(int) $agency->rowid.'">'.dol_escape_htmltag($agency->ref.' — '.$agency->label).'</option>';
	return $html;
}

if (!empty($user->admin) || $user->hasRight('agence','webhook','manage') || $user->hasRight('agence','webhook','replay')) {
	print load_fiche_titre('Webhooks HMAC-SHA256', '', 'link');
	if (!empty($user->admin) || $user->hasRight('agence','webhook','manage')) {
		print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_webhook"><table class="border centpercent">';
		print '<tr><td>Référence</td><td><input required name="ref" maxlength="64"></td><td>Libellé</td><td><input required class="minwidth200" name="label"></td></tr>';
		print '<tr><td>URL HTTPS</td><td colspan="3"><input required class="quatrevingtpercent" type="url" name="endpoint_url" placeholder="https://integration.example/webhooks/agence"></td></tr>';
		print '<tr><td>Événements</td><td colspan="3"><input required class="quatrevingtpercent" name="event_filter" value="cash_closure.completed,validation.decided,refund.completed,bank_deposit.completed,alert.created"></td></tr>';
		print '<tr><td>Agence</td><td><select name="fk_agence">'.agence_integration_agency_options($agencies).'</select></td><td>Secret (32 caractères min.)</td><td><input required autocomplete="new-password" type="password" name="secret"></td></tr>';
		print '<tr><td>Tentatives</td><td><input type="number" min="1" max="20" name="max_attempts" value="8"></td><td>Actif</td><td><input type="checkbox" name="status" value="1" checked></td></tr></table><div class="center"><button class="button">Créer le webhook</button></div></form>';
	}
	print '<form method="POST" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="process_webhooks"><button class="button">Traiter la file maintenant</button></form>';
	$resql=$db->query('SELECT e.ref,e.label,e.endpoint_url,e.event_filter,e.status,COUNT(d.rowid) deliveries,SUM(CASE WHEN d.status=3 THEN 1 ELSE 0 END) failed FROM '.$db->prefix().'sof_webhook_endpoint e LEFT JOIN '.$db->prefix().'sof_webhook_delivery d ON d.fk_endpoint=e.rowid AND d.entity=e.entity WHERE e.entity='.(int)$conf->entity.' GROUP BY e.rowid,e.ref,e.label,e.endpoint_url,e.event_filter,e.status ORDER BY e.ref');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Référence</th><th>URL</th><th>Événements</th><th>Livraisons</th><th>Échecs</th><th>État</th></tr>';
	while ($resql && ($row=$db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</td><td>'.dol_escape_htmltag($row->endpoint_url).'</td><td>'.dol_escape_htmltag($row->event_filter).'</td><td>'.(int)$row->deliveries.'</td><td>'.(int)$row->failed.'</td><td>'.((int)$row->status?'Actif':'Inactif').'</td></tr>';
	print '</table>';
	$resql=$db->query('SELECT d.rowid,d.delivery_ref,d.event_code,d.attempts,d.http_status,d.last_error,d.date_creation FROM '.$db->prefix().'sof_webhook_delivery d WHERE d.entity='.(int)$conf->entity.' AND d.status=3 ORDER BY d.rowid DESC'.$db->plimit(20,0));
	if ($resql && $db->num_rows($resql)>0) { print '<table class="liste centpercent"><tr class="liste_titre"><th>Livraison en échec</th><th>Événement</th><th>HTTP</th><th>Erreur</th><th></th></tr>'; while ($row=$db->fetch_object($resql)) { print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->delivery_ref).'</td><td>'.dol_escape_htmltag($row->event_code).'</td><td>'.(int)$row->http_status.'</td><td>'.dol_escape_htmltag($row->last_error).'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="replay_webhook"><input type="hidden" name="delivery_id" value="'.(int)$row->rowid.'"><button class="button smallpaddingimp">Rejouer</button></form></td></tr>'; } print '</table>'; }
}

if (!empty($user->admin) || $user->hasRight('agence','connector','manage') || $user->hasRight('agence','connector','sync')) {
	print load_fiche_titre('Connecteurs banques et opérateurs de paiement', '', 'building-columns');
	if (!empty($user->admin) || $user->hasRight('agence','connector','manage')) {
		print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_connector"><table class="border centpercent">';
		print '<tr><td>Référence</td><td><input required name="ref"></td><td>Libellé</td><td><input required name="label"></td><td>Type</td><td><select name="connector_type"><option value="bank">Banque</option><option value="orange_money">Orange Money</option><option value="mobile_money">Mobile Money</option></select></td></tr>';
		print '<tr><td>URL JSON HTTPS</td><td colspan="3"><input required class="quatrevingtpercent" type="url" name="endpoint_url"></td><td>Authentification</td><td><select name="auth_type"><option value="bearer">Bearer</option><option value="api_key">X-API-Key</option><option value="basic">Basic user:password</option><option value="none">Aucune</option></select></td></tr>';
		print '<tr><td>Secret</td><td><input type="password" autocomplete="new-password" name="credential"></td><td>Agence</td><td><select required name="fk_agence">'.agence_integration_agency_options($agencies,false).'</select></td><td>Compte bancaire</td><td><select name="fk_bank_account"><option value="0">Non applicable</option>'; foreach ($bankAccounts as $account) print '<option value="'.(int)$account->rowid.'">'.dol_escape_htmltag($account->ref.' — '.$account->label).'</option>'; print '</select></td></tr>';
		print '<tr><td>Fréquence (minutes)</td><td><input type="number" min="5" max="10080" name="polling_minutes" value="15"></td><td>Actif</td><td><input type="checkbox" name="status" value="1" checked></td><td colspan="2"></td></tr></table><div class="center"><button class="button">Créer le connecteur</button></div></form>';
	}
	$resql=$db->query('SELECT c.* ,a.ref agency_ref,ba.ref bank_ref FROM '.$db->prefix().'sof_integration_connector c LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid=c.fk_agence LEFT JOIN '.$db->prefix().'bank_account ba ON ba.rowid=c.fk_bank_account WHERE c.entity='.(int)$conf->entity.' ORDER BY c.ref');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Connecteur</th><th>Type</th><th>Agence / compte</th><th>Dernière synchro.</th><th>Curseur</th><th>Erreur</th><th></th></tr>';
	while ($resql && ($row=$db->fetch_object($resql))) { print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</td><td>'.dol_escape_htmltag($row->connector_type).'</td><td>'.dol_escape_htmltag($row->agency_ref.' / '.$row->bank_ref).'</td><td>'.dol_escape_htmltag($row->date_last_sync).'</td><td>'.dol_escape_htmltag(substr((string)$row->remote_cursor,0,60)).'</td><td>'.dol_escape_htmltag($row->last_error).'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="sync_connector"><input type="hidden" name="connector_id" value="'.(int)$row->rowid.'"><button class="button smallpaddingimp">Synchroniser</button></form></td></tr>'; }
	print '</table>';
}

if (!empty($user->admin) || $user->hasRight('agence','bi','export')) {
	print load_fiche_titre('Export BI incrémental', '', 'chart-line');
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="bi_export"><table class="border centpercent"><tr><td>Jeu de données</td><td><select name="dataset"><option value="movements">Mouvements</option><option value="sessions">Sessions</option><option value="refunds">Remboursements</option><option value="deposits">Dépôts</option><option value="alerts">Alertes</option></select></td><td>Agence</td><td><select name="fk_agence">'.agence_integration_agency_options($agencies).'</select></td><td>Limite</td><td><input type="number" min="1" max="1000" name="limit" value="250"></td></tr><tr><td>Curseur précédent</td><td colspan="5"><input class="quatrevingtpercent" name="cursor" placeholder="Vide pour le premier lot"></td></tr></table><div class="center"><button class="button">Télécharger le lot CSV</button></div></form>';
}

if (!empty($user->admin) || $user->hasRight('agence','configtransfer','export') || $user->hasRight('agence','configtransfer','import')) {
	print load_fiche_titre('Transport de configuration dev / recette / production', '', 'gears');
	if (!empty($user->admin) || $user->hasRight('agence','configtransfer','export')) print '<form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="export_config"><select name="environment"><option value="development">Développement</option><option value="staging">Recette</option><option value="production">Production</option></select><button class="button">Exporter JSON sans secrets</button></form>';
	if (!empty($user->admin) || $user->hasRight('agence','configtransfer','import')) print '<form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="import_config"><input required type="file" accept="application/json,.json" name="config_file"><select name="target_environment"><option value="development">Développement</option><option value="staging">Recette</option><option value="production">Production</option></select><label><input type="checkbox" name="dry_run" value="1" checked> Simulation</label><button class="button">Valider / importer</button></form>';
}

llxFooter();
$db->close();
