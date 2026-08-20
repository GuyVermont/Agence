<?php
/* Copyright (C) 2026 iPowerWorld */

/** Transactional qualification of REST support, webhooks, BI, connectors and configuration transport. */
if (PHP_SAPI !== 'cli') die("CLI only\n");
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);
$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofalerte.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

$failures = array();
function agence_integration_assert($condition, $label, $detail = '')
{
	global $failures;
	echo ($condition ? '[OK] ' : '[KO] ').$label.(!$condition && $detail !== '' ? ' — '.$detail : '').PHP_EOL;
	if (!$condition) $failures[] = $label;
}
function agence_integration_row($sql)
{
	global $db;
	$resql = $db->query($sql);
	return $resql ? $db->fetch_object($resql) : null;
}

$admin = agence_integration_row('SELECT rowid FROM '.$db->prefix().'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
if ($admin) { $user->fetch((int) $admin->rowid); $user->getrights('', 1); }
agence_integration_assert(!empty($user->id) && !empty($user->admin), 'active administrator available');

$tables = array('sof_webhook_endpoint','sof_webhook_delivery','sof_integration_connector','sof_integration_sync','sof_config_transfer');
foreach ($tables as $table) agence_integration_assert(!empty($db->DDLInfoTable($db->prefix().$table)), 'integration table '.$table.' is installed');

$token = date('YmdHis').mt_rand(1000,9999);
$db->begin();
$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity; $agency->ref = 'INT-AG-'.$token; $agency->label = 'Integration qualification'; $agency->country_code = 'CM'; $agency->status = 1;
$agencyId = $agency->create($user, 1);
agence_integration_assert($agencyId > 0, 'integration agency fixture created', $agency->error);

$service = new SofIntegrationService($db);
$plainWebhookSecret = 'qualification-webhook-secret-'.$token.'-0123456789';
$webhookId = $service->saveWebhook($user, array(
	'ref'=>'INT-WH-'.$token, 'label'=>'Qualification webhook', 'endpoint_url'=>'https://example.invalid/powererp/agence',
	'event_filter'=>'cash_closure.completed,validation.decided,refund.completed,bank_deposit.completed,alert.created',
	'fk_agence'=>$agencyId, 'secret'=>$plainWebhookSecret, 'max_attempts'=>3, 'status'=>1,
));
$webhook = agence_integration_row('SELECT * FROM '.$db->prefix().'sof_webhook_endpoint WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $webhookId);
agence_integration_assert($webhookId > 0 && $webhook && $webhook->secret_encrypted !== $plainWebhookSecret && dolDecrypt($webhook->secret_encrypted) === $plainWebhookSecret, 'webhook secret is encrypted at rest', $service->error);

$eventId = 'EVT-QUAL-'.$token;
$queued = $service->queueBusinessEvent('cash_closure.completed', 'session', 987654, $agencyId, array('ref'=>'SES-'.$token,'gap_amount'=>0), $eventId);
$duplicate = $service->queueBusinessEvent('cash_closure.completed', 'session', 987654, $agencyId, array('ref'=>'SES-'.$token,'gap_amount'=>0), $eventId);
$delivery = agence_integration_row('SELECT * FROM '.$db->prefix().'sof_webhook_delivery WHERE entity='.(int) $conf->entity." AND event_id='".$db->escape($eventId)."'");
$payload = $delivery ? json_decode($delivery->payload, true) : null;
$timestamp = '1750000000';
$expectedSignature = $delivery ? hash_hmac('sha256', $timestamp.'.'.$delivery->payload, $plainWebhookSecret) : '';
agence_integration_assert($queued === 1 && $duplicate === 0 && $delivery && is_array($payload) && $payload['specversion'] === '1.0' && $payload['data']['entity'] === (int) $conf->entity, 'webhook event is immutable, entity-scoped and idempotent', $service->error);
agence_integration_assert(strlen($expectedSignature) === 64 && hash_equals($expectedSignature, hash_hmac('sha256', $timestamp.'.'.$delivery->payload, dolDecrypt($webhook->secret_encrypted))), 'webhook HMAC-SHA256 contract is reproducible');

$connectorSecret = 'qualification-connector-'.$token;
$connectorId = $service->saveConnector($user, array(
	'ref'=>'INT-OM-'.$token, 'label'=>'Orange Money qualification', 'connector_type'=>'orange_money',
	'endpoint_url'=>'https://example.invalid/powererp/transactions', 'auth_type'=>'bearer', 'credential'=>$connectorSecret,
	'fk_agence'=>$agencyId, 'polling_minutes'=>15, 'status'=>1,
));
$connector = agence_integration_row('SELECT * FROM '.$db->prefix().'sof_integration_connector WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $connectorId);
agence_integration_assert($connectorId > 0 && $connector && $connector->credential_encrypted !== $connectorSecret && dolDecrypt($connector->credential_encrypted) === $connectorSecret, 'payment connector credential is encrypted and agency-scoped', $service->error);

for ($i=1; $i<=2; $i++) {
	$alert = new SofAlerte($db);
	$alert->entity=(int)$conf->entity; $alert->ref='INT-ALT-'.$i.'-'.$token; $alert->dedup_key='integration-'.$i.'-'.$token;
	$alert->alert_type='integration_qualification'; $alert->severity='warning'; $alert->fk_agence=$agencyId;
	$alert->object_type='agency'; $alert->object_id=$agencyId; $alert->message='BI integration qualification '.$i;
	$alert->date_alert=dol_now()+$i; $alert->status=0; $alert->create($user,1);
}
$first = $service->incrementalExport($user, 'alerts', '', 1, $agencyId);
$second = is_array($first) ? $service->incrementalExport($user, 'alerts', $first['next_cursor'], 10, $agencyId) : -1;
$firstId = is_array($first) && !empty($first['rows'][0]) ? (int) $first['rows'][0]['rowid'] : 0;
$secondIds = is_array($second) ? array_map(function ($row) { return (int) $row['rowid']; }, $second['rows']) : array();
agence_integration_assert(is_array($first) && $first['count'] === 1 && is_array($second) && !in_array($firstId, $secondIds, true), 'BI cursor paginates without duplicate and preserves agency scope', $service->error);
$forbiddenDataset = $service->incrementalExport($user, 'users', '', 10, 0);
agence_integration_assert($forbiddenDataset < 0, 'BI export rejects non-allowlisted datasets');

$package = $service->exportConfiguration($user, 'development');
$serializedPackage = is_array($package) ? json_encode($package) : '';
agence_integration_assert(is_array($package) && !str_contains($serializedPackage, $plainWebhookSecret) && !str_contains($serializedPackage, $connectorSecret) && !str_contains($serializedPackage, 'secret_encrypted') && !str_contains($serializedPackage, 'credential_encrypted'), 'configuration package excludes all integration secrets', $service->error);
if (is_array($package)) {
	foreach (array('accounting_mappings','workflows','notification_rules','webhooks','connectors') as $resource) $package['payload'][$resource] = array();
	$unsigned = $package; unset($unsigned['checksum']);
	$package['checksum'] = hash('sha256', json_encode($unsigned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
$dryRun = is_array($package) ? $service->importConfiguration($user, $package, 'staging', true) : -1;
$transfer = agence_integration_row('SELECT * FROM '.$db->prefix().'sof_config_transfer WHERE entity='.(int) $conf->entity.' ORDER BY rowid DESC LIMIT 1');
agence_integration_assert(is_array($dryRun) && $transfer && (int) $transfer->dry_run === 1 && $transfer->target_environment === 'staging', 'configuration import dry-run validates checksum, allowlists and target environment', $service->error);

$eventCount = agence_integration_row('SELECT COUNT(*) nb FROM '.$db->prefix()."c_action_trigger WHERE code IN ('AGENCE_CASH_CLOSURE_COMPLETED','AGENCE_VALIDATION_DECIDED','AGENCE_REFUND_COMPLETED','AGENCE_BANK_DEPOSIT_COMPLETED','AGENCE_ALERT_CREATED')");
agence_integration_assert($eventCount && (int) $eventCount->nb === 5, 'five Agence events are registered in Dolibarr Notification');
agence_integration_assert(SofIntegrationService::dolibarrNotificationEvents() === array('AGENCE_CASH_CLOSURE_COMPLETED','AGENCE_VALIDATION_DECIDED','AGENCE_REFUND_COMPLETED','AGENCE_BANK_DEPOSIT_COMPLETED','AGENCE_ALERT_CREATED'), 'Dolibarr notification hook exposes the exact public events');

$health = $service->health($user);
agence_integration_assert(is_array($health) && $health['status'] === 'ok' && $health['version'] === '2.3.0' && $health['entity'] === (int) $conf->entity, 'REST health contract reports module, version, entity and queues', $service->error);

$apiReachable = false; $apiDetail = 'cURL extension unavailable'; $apiLabel = 'REST endpoint is discovered and rejects unauthenticated calls';
if (function_exists('curl_init')) {
	$curl = curl_init('http://localhost/dev/htdocs/api/index.php/agence/health');
	$headers = array('Accept: application/json');
	if (!empty($user->api_key)) $headers[] = 'DOLAPIKEY: '.$user->api_key;
	curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_HTTPHEADER=>$headers));
	$body = curl_exec($curl); $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE); $curlError = curl_error($curl); curl_close($curl);
	$apiJson = is_string($body) ? json_decode($body, true) : null;
	if (!empty($user->api_key)) {
		$apiLabel = 'authenticated Dolibarr REST endpoint /agence/health is callable';
		$apiReachable = $httpCode === 200 && is_array($apiJson) && ($apiJson['version'] ?? '') === '2.3.0' && (int) ($apiJson['entity'] ?? 0) === (int) $conf->entity;
	} else {
		$apiReachable = $httpCode === 401;
	}
	$apiDetail = 'HTTP '.$httpCode.($curlError !== '' ? ' '.$curlError : '');
}
agence_integration_assert($apiReachable, $apiLabel, $apiDetail);

$db->rollback();
echo $failures ? 'Integration ecosystem qualification failed: '.implode(', ', $failures).PHP_EOL : "Integration ecosystem qualification completed successfully.\n";
exit($failures ? 1 : 0);
