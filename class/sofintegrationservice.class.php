<?php
/* Copyright (C) 2026 iPowerWorld */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommonobject.class.php';

/**
 * Minimal Dolibarr business object used by the native Notification module.
 *
 * Notify::send() expects a CommonObject (not a stdClass): it loads the
 * optional project and builds substitutions/output paths even for custom
 * event codes.
 */
class SofIntegrationNotificationObject extends SofCommonObject
{
	public $element = 'agence';
	public $table_element = 'sof_caisse_auditlog';
	public $project = null;
	public $fk_project = null;
	public $socid = 0;
	public $total_ht = 0;
	public $context = array();
}

/** Secure integration boundary for PowerERP, Dolibarr and external payment systems. */
class SofIntegrationService
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var array<int,string> */
	public $errors = array();

	/** Events which form the public integration contract. */
	public const EVENTS = array(
		'cash_closure.completed' => 'AGENCE_CASH_CLOSURE_COMPLETED',
		'validation.decided' => 'AGENCE_VALIDATION_DECIDED',
		'refund.completed' => 'AGENCE_REFUND_COMPLETED',
		'bank_deposit.completed' => 'AGENCE_BANK_DEPOSIT_COMPLETED',
		'alert.created' => 'AGENCE_ALERT_CREATED',
	);

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/** Return the Dolibarr trigger codes exposed to its Notification module. */
	public static function dolibarrNotificationEvents()
	{
		return array_values(self::EVENTS);
	}

	/** Create or update a signed webhook endpoint. A blank secret preserves the current secret. */
	public function saveWebhook(User $user, array $data)
	{
		if (!$this->hasRight($user, 'webhook', 'manage')) return $this->fail('Permission refusée pour gérer les webhooks.');
		$id = (int) ($data['id'] ?? 0);
		$ref = strtoupper(trim((string) ($data['ref'] ?? '')));
		$label = trim((string) ($data['label'] ?? ''));
		$url = trim((string) ($data['endpoint_url'] ?? ''));
		$events = $this->parseEventFilter($data['event_filter'] ?? '');
		$fkAgence = (int) ($data['fk_agence'] ?? 0);
		$secret = (string) ($data['secret'] ?? '');
		$maxAttempts = max(1, min(20, (int) ($data['max_attempts'] ?? 8)));
		$status = !empty($data['status']) ? 1 : 0;
		if (!preg_match('/^[A-Z0-9][A-Z0-9_.-]{1,63}$/', $ref) || $label === '' || strlen($label) > 255) return $this->fail('Référence ou libellé de webhook invalide.');
		if (!$this->validateExternalUrl($url)) return -1;
		if (!$events) return $this->fail('Au moins un événement webhook pris en charge est obligatoire.');
		if ($fkAgence > 0 && !SofAgenceService::userCanAccessAgency($this->db, $user, $fkAgence, 'webhook_manage')) return $this->fail('Agence webhook hors périmètre.');
		if ($secret !== '' && (strlen($secret) < 32 || strlen($secret) > 512 || preg_match('/[\x00-\x1F\x7F]/', $secret))) return $this->fail('Le secret webhook doit contenir entre 32 et 512 caractères sans caractère de contrôle.');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		if ($id > 0) {
			$sql = 'SELECT rowid, secret_encrypted FROM '.$this->db->prefix().'sof_webhook_endpoint WHERE entity = '.$this->entity().' AND rowid = '.$id;
			$resql = $this->db->query($sql);
			$current = $resql ? $this->db->fetch_object($resql) : null;
			if (!$current) return $this->fail('Webhook introuvable dans cette entité.');
			$encrypted = $secret !== '' ? dolEncrypt($secret) : (string) $current->secret_encrypted;
			$sql = 'UPDATE '.$this->db->prefix().'sof_webhook_endpoint SET ref = '.$this->quote($ref).', label = '.$this->quote($label).', endpoint_url = '.$this->quote($url);
			$sql .= ', event_filter = '.$this->quote(implode(',', $events)).', fk_agence = '.($fkAgence ?: 'NULL').', secret_encrypted = '.$this->quote($encrypted).', max_attempts = '.$maxAttempts.', status = '.$status;
			$sql .= ', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE entity = '.$this->entity().' AND rowid = '.$id;
			if (!$this->db->query($sql)) return $this->fail($this->db->lasterror());
			return $id;
		}
		if ($secret === '') return $this->fail('Un secret webhook est obligatoire lors de la création.');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_webhook_endpoint (entity,ref,label,endpoint_url,event_filter,fk_agence,secret_encrypted,max_attempts,status,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().', '.$this->quote($ref).', '.$this->quote($label).', '.$this->quote($url).', '.$this->quote(implode(',', $events)).', '.($fkAgence ?: 'NULL').', '.$this->quote(dolEncrypt($secret)).', '.$maxAttempts.', '.$status.', CURRENT_TIMESTAMP, '.((int) $user->id).')';
		if (!$this->db->query($sql)) return $this->fail($this->db->lasterror());
		return (int) $this->db->last_insert_id($this->db->prefix().'sof_webhook_endpoint');
	}

	/** Queue an immutable CloudEvents-like business event for every matching endpoint. */
	public function queueBusinessEvent($eventCode, $objectType, $objectId, $fkAgence = 0, array $data = array(), $eventId = '')
	{
		$eventCode = strtolower(trim((string) $eventCode));
		if (!isset(self::EVENTS[$eventCode])) return $this->fail('Code événement d’intégration inconnu.');
		$eventId = $eventId !== '' ? $eventId : $this->makeRef('EVT');
		if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{1,63}$/', $eventId) || !preg_match('/^[a-z][a-z0-9_.-]{0,127}$/i', (string) $objectType) || (int) $objectId < 0) return $this->fail('Identité de l’événement webhook invalide.');
		$event = array(
			'specversion' => '1.0', 'id' => $eventId, 'source' => 'urn:powererp:agence:entity:'.$this->entity(),
			'type' => 'net.ipowerworld.powererp.agence.'.$eventCode, 'time' => gmdate('c'),
			'subject' => trim((string) $objectType).'/'.((int) $objectId),
			'datacontenttype' => 'application/json',
			'data' => array_merge(array('entity' => $this->entity(), 'object_type' => (string) $objectType, 'object_id' => (int) $objectId, 'fk_agence' => (int) $fkAgence), $data),
		);
		$payload = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($payload === false || strlen($payload) > 1024 * 1024) return $this->fail('Charge utile webhook invalide ou trop volumineuse.');
		$sql = 'SELECT rowid,event_filter,fk_agence FROM '.$this->db->prefix().'sof_webhook_endpoint WHERE entity = '.$this->entity().' AND status = 1';
		$sql .= ' AND (fk_agence IS NULL'.($fkAgence > 0 ? ' OR fk_agence = '.((int) $fkAgence) : '').')';
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail($this->db->lasterror());
		$queued = 0;
		while ($endpoint = $this->db->fetch_object($resql)) {
			$filter = array_map('trim', explode(',', strtolower((string) $endpoint->event_filter)));
			if (!in_array($eventCode, $filter, true)) continue;
			$existingSql = 'SELECT rowid FROM '.$this->db->prefix().'sof_webhook_delivery WHERE entity='.$this->entity().' AND fk_endpoint='.((int) $endpoint->rowid).' AND event_id='.$this->quote($eventId);
			$existingResult = $this->db->query($existingSql);
			if ($existingResult && $this->db->num_rows($existingResult) > 0) continue;
			$deliveryRef = $this->makeRef('WH');
			$sqlInsert = (in_array($this->db->type, array('mysql','mysqli'), true) ? 'INSERT IGNORE INTO ' : 'INSERT INTO ').$this->db->prefix().'sof_webhook_delivery (entity,delivery_ref,event_id,event_code,fk_endpoint,fk_agence,object_type,object_id,payload,attempts,next_attempt,status,date_creation) VALUES (';
			$sqlInsert .= $this->entity().', '.$this->quote($deliveryRef).', '.$this->quote($eventId).', '.$this->quote($eventCode).', '.((int) $endpoint->rowid).', '.((int) $fkAgence ?: 'NULL').', '.$this->quote((string) $objectType).', '.((int) $objectId).', '.$this->quote($payload).', 0, CURRENT_TIMESTAMP, 0, CURRENT_TIMESTAMP)';
			if ($this->db->type === 'pgsql') $sqlInsert .= ' ON CONFLICT (entity,fk_endpoint,event_id) DO NOTHING';
			$insertResult = $this->db->query($sqlInsert);
			if ($insertResult && $this->db->affected_rows($insertResult) > 0) $queued++;
			elseif (!$insertResult) return $this->fail($this->db->lasterror());
		}
		return $queued;
	}

	/** Publish to webhooks, the internal notification outbox and Dolibarr Notification. */
	public function emitBusinessEvent($eventCode, $objectType, $objectId, $fkAgence = 0, array $data = array(), User $actor = null)
	{
		$queued = getDolGlobalInt('AGENCE_ENABLE_WEBHOOKS', 1) ? $this->queueBusinessEvent($eventCode, $objectType, $objectId, $fkAgence, $data) : 0;
		if ($queued < 0) {
			dol_syslog(__METHOD__.' webhook queue failed: '.$this->error, LOG_ERR);
			$queued = 0; // An external integration must never roll back a committed financial operation.
		}
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnotificationservice.class.php';
		$notifications = new SofNotificationService($this->db);
		$severity = $eventCode === 'alert.created' ? (string) ($data['severity'] ?? 'warning') : 'info';
		$notifications->queueEvent(str_replace('.', '_', $eventCode), $severity, (string) ($data['subject'] ?? $eventCode), (string) ($data['message'] ?? json_encode($data, JSON_UNESCAPED_UNICODE)), $objectType, (int) $objectId, (int) $fkAgence);
		$this->sendDolibarrNotification($eventCode, $objectType, $objectId, $fkAgence, $data, $actor);
		return $queued;
	}

	/** Deliver queued webhooks with HMAC-SHA256, retry and bounded response capture. */
	public function processWebhooks($limit = 100)
	{
		if (!getDolGlobalInt('AGENCE_ENABLE_WEBHOOKS', 1)) return 0;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		$limit = max(1, min(500, (int) $limit));
		$sql = 'SELECT d.rowid,d.delivery_ref,d.event_code,d.payload,d.attempts,e.endpoint_url,e.secret_encrypted,e.max_attempts';
		$sql .= ' FROM '.$this->db->prefix().'sof_webhook_delivery d JOIN '.$this->db->prefix().'sof_webhook_endpoint e ON e.rowid=d.fk_endpoint AND e.entity=d.entity';
		$sql .= ' WHERE d.entity='.$this->entity().' AND d.status IN (0,2) AND (d.next_attempt IS NULL OR d.next_attempt <= CURRENT_TIMESTAMP) AND e.status=1 ORDER BY d.rowid'.$this->db->plimit($limit, 0);
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail($this->db->lasterror());
		$processed = 0;
		while ($delivery = $this->db->fetch_object($resql)) {
			$claim = 'UPDATE '.$this->db->prefix().'sof_webhook_delivery SET status=1,tms=CURRENT_TIMESTAMP WHERE entity='.$this->entity().' AND rowid='.((int) $delivery->rowid).' AND status IN (0,2)';
			$claimResult = $this->db->query($claim);
			if (!$claimResult || $this->db->affected_rows($claimResult) !== 1) continue;
			$timestamp = (string) time();
			$secret = dolDecrypt((string) $delivery->secret_encrypted);
			if (strlen($secret) < 32) {
				$this->db->query('UPDATE '.$this->db->prefix().'sof_webhook_delivery SET attempts='.((int) $delivery->attempts + 1).",status=3,last_error='Secret webhook absent ou illisible.',next_attempt=NULL,tms=CURRENT_TIMESTAMP WHERE entity=".$this->entity().' AND rowid='.((int) $delivery->rowid));
				$processed++;
				continue;
			}
			$signature = hash_hmac('sha256', $timestamp.'.'.(string) $delivery->payload, $secret);
			$headers = array('Content-Type: application/cloudevents+json', 'User-Agent: PowerERP-Agence/2.4', 'X-PowerERP-Delivery: '.$delivery->delivery_ref, 'X-PowerERP-Event: '.$delivery->event_code, 'X-PowerERP-Timestamp: '.$timestamp, 'X-PowerERP-Signature: sha256='.$signature);
			$response = getURLContent($delivery->endpoint_url, 'POSTALREADYFORMATED', $delivery->payload, 0, $headers, array('https'), 0, 1, 5, max(5, getDolGlobalInt('AGENCE_WEBHOOK_TIMEOUT_SECONDS', 15)));
			$http = (int) ($response['http_code'] ?? 0);
			$attempts = (int) $delivery->attempts + 1;
			$success = $http >= 200 && $http < 300;
			$permanent = !$success && ($attempts >= (int) $delivery->max_attempts || ($http >= 400 && $http < 500 && !in_array($http, array(408, 409, 425, 429), true)));
			$status = $success ? 4 : ($permanent ? 3 : 2);
			$error = $success ? '' : trim('HTTP '.$http.' '.(string) ($response['curl_error_msg'] ?? ''));
			$delay = min(21600, 60 * (2 ** min(8, max(0, $attempts - 1))));
			$next = $success || $permanent ? 'NULL' : $this->quote($this->db->idate(dol_now() + $delay));
			$sqlUpdate = 'UPDATE '.$this->db->prefix().'sof_webhook_delivery SET attempts='.$attempts.',status='.$status.',http_status='.$http.',next_attempt='.$next;
			$sqlUpdate .= ',date_sent='.($success ? 'CURRENT_TIMESTAMP' : 'NULL').',response_excerpt='.$this->quote(substr((string) ($response['content'] ?? ''), 0, 2000)).',last_error='.($error !== '' ? $this->quote(substr($error, 0, 2000)) : 'NULL').',tms=CURRENT_TIMESTAMP';
			$sqlUpdate .= ' WHERE entity='.$this->entity().' AND rowid='.((int) $delivery->rowid);
			$this->db->query($sqlUpdate);
			$processed++;
		}
		return $processed;
	}

	/** Requeue a failed or delivered webhook while retaining its evidence. */
	public function replayWebhook(User $user, $deliveryId)
	{
		if (!$this->hasRight($user, 'webhook', 'replay')) return $this->fail('Permission refusée pour rejouer un webhook.');
		$sql = 'UPDATE '.$this->db->prefix().'sof_webhook_delivery SET status=2,next_attempt=CURRENT_TIMESTAMP,last_error=NULL,tms=CURRENT_TIMESTAMP WHERE entity='.$this->entity().' AND rowid='.((int) $deliveryId).' AND status IN (3,4)';
		$resql = $this->db->query($sql);
		return $resql && $this->db->affected_rows($resql) === 1 ? 1 : $this->fail('Livraison absente ou non rejouable.');
	}

	/** Save a pull connector for a bank, Orange Money or Mobile Money contract. */
	public function saveConnector(User $user, array $data)
	{
		if (!$this->hasRight($user, 'connector', 'manage')) return $this->fail('Permission refusée pour gérer les connecteurs.');
		$id = (int) ($data['id'] ?? 0);
		$ref = strtoupper(trim((string) ($data['ref'] ?? '')));
		$label = trim((string) ($data['label'] ?? ''));
		$type = strtolower(trim((string) ($data['connector_type'] ?? '')));
		$url = trim((string) ($data['endpoint_url'] ?? ''));
		$auth = strtolower(trim((string) ($data['auth_type'] ?? 'bearer')));
		$credential = (string) ($data['credential'] ?? '');
		$fkAgence = (int) ($data['fk_agence'] ?? 0);
		$fkBank = (int) ($data['fk_bank_account'] ?? 0);
		$polling = max(5, min(10080, (int) ($data['polling_minutes'] ?? 15)));
		$status = !empty($data['status']) ? 1 : 0;
		if (!preg_match('/^[A-Z0-9][A-Z0-9_.-]{1,63}$/', $ref) || $label === '' || strlen($label) > 255 || !in_array($type, array('bank','orange_money','mobile_money'), true) || !in_array($auth, array('none','bearer','api_key','basic'), true)) return $this->fail('Configuration de connecteur invalide.');
		if (!$this->validateExternalUrl($url)) return -1;
		if ($fkAgence <= 0 || !SofAgenceService::userCanAccessAgency($this->db, $user, $fkAgence, 'connector_manage')) return $this->fail('Agence connecteur obligatoire et limitée au périmètre utilisateur.');
		if ($type === 'bank' && $fkBank <= 0) return $this->fail('Un compte bancaire est obligatoire pour un connecteur bancaire.');
		if ($fkBank > 0) {
			$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'bank_account WHERE entity='.$this->entity().' AND rowid='.$fkBank.' AND clos=0');
			if (!$resql || $this->db->num_rows($resql) !== 1) return $this->fail('Compte bancaire absent, clôturé ou inter-entité.');
		}
		if ($credential !== '' && (strlen($credential) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $credential))) return $this->fail('Identifiant secret de connecteur trop long ou contenant un caractère de contrôle.');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		if ($id > 0) {
			$resql = $this->db->query('SELECT credential_encrypted FROM '.$this->db->prefix().'sof_integration_connector WHERE entity='.$this->entity().' AND rowid='.$id);
			$current = $resql ? $this->db->fetch_object($resql) : null;
			if (!$current) return $this->fail('Connecteur introuvable.');
			$encrypted = $credential !== '' ? dolEncrypt($credential) : $current->credential_encrypted;
			$sql = 'UPDATE '.$this->db->prefix().'sof_integration_connector SET ref='.$this->quote($ref).',label='.$this->quote($label).',connector_type='.$this->quote($type).',endpoint_url='.$this->quote($url).',auth_type='.$this->quote($auth).',credential_encrypted='.($encrypted !== null ? $this->quote($encrypted) : 'NULL').',fk_agence='.$fkAgence.',fk_bank_account='.($fkBank ?: 'NULL').',polling_minutes='.$polling.',status='.$status.',fk_user_modif='.((int) $user->id).',tms=CURRENT_TIMESTAMP WHERE entity='.$this->entity().' AND rowid='.$id;
			return $this->db->query($sql) ? $id : $this->fail($this->db->lasterror());
		}
		if ($auth !== 'none' && $credential === '') return $this->fail('Un secret est obligatoire pour ce type d’authentification.');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_integration_connector (entity,ref,label,connector_type,endpoint_url,auth_type,credential_encrypted,fk_agence,fk_bank_account,polling_minutes,date_next_sync,status,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().','.$this->quote($ref).','.$this->quote($label).','.$this->quote($type).','.$this->quote($url).','.$this->quote($auth).','.($credential !== '' ? $this->quote(dolEncrypt($credential)) : 'NULL').','.$fkAgence.','.($fkBank ?: 'NULL').','.$polling.',CURRENT_TIMESTAMP,'.$status.',CURRENT_TIMESTAMP,'.((int) $user->id).')';
		if (!$this->db->query($sql)) return $this->fail($this->db->lasterror());
		return (int) $this->db->last_insert_id($this->db->prefix().'sof_integration_connector');
	}

	/** Pull and import one connector response contract: {transactions:[], next_cursor:string}. */
	public function syncConnector(User $user, $connectorId)
	{
		if (!$this->hasRight($user, 'connector', 'sync')) return $this->fail('Permission refusée pour synchroniser les connecteurs.');
		$resql = $this->db->query('SELECT * FROM '.$this->db->prefix().'sof_integration_connector WHERE entity='.$this->entity().' AND rowid='.((int) $connectorId).' AND status=1');
		$connector = $resql ? $this->db->fetch_object($resql) : null;
		if (!$connector || !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $connector->fk_agence, 'connector_sync')) return $this->fail('Connecteur absent ou hors périmètre.');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		$syncRef = $this->makeRef('SYNC');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_integration_sync (entity,ref,fk_connector,direction,remote_cursor_before,date_start,status,date_creation,fk_user_creat) VALUES ('.$this->entity().','.$this->quote($syncRef).','.((int) $connector->rowid).",'pull',".($connector->remote_cursor ? $this->quote($connector->remote_cursor) : 'NULL').',CURRENT_TIMESTAMP,0,CURRENT_TIMESTAMP,'.((int) $user->id).')';
		if (!$this->db->query($sql)) return $this->fail($this->db->lasterror());
		$syncId = (int) $this->db->last_insert_id($this->db->prefix().'sof_integration_sync');
		$url = (string) $connector->endpoint_url;
		if ($connector->remote_cursor !== null && $connector->remote_cursor !== '') $url .= (strpos($url, '?') === false ? '?' : '&').'cursor='.rawurlencode($connector->remote_cursor);
		$headers = array('Accept: application/json', 'User-Agent: PowerERP-Agence/2.4');
		$credential = $connector->credential_encrypted ? dolDecrypt($connector->credential_encrypted) : '';
		if ($connector->auth_type === 'bearer') $headers[] = 'Authorization: Bearer '.$credential;
		elseif ($connector->auth_type === 'api_key') $headers[] = 'X-API-Key: '.$credential;
		elseif ($connector->auth_type === 'basic') $headers[] = 'Authorization: Basic '.base64_encode($credential);
		$response = getURLContent($url, 'GET', '', 0, $headers, array('https'), 0, 1, 5, max(5, getDolGlobalInt('AGENCE_CONNECTOR_TIMEOUT_SECONDS', 30)));
		$http = (int) ($response['http_code'] ?? 0);
		if ($http < 200 || $http >= 300) return $this->finishSyncFailure($syncId, $connector, 'HTTP '.$http.' '.(string) ($response['curl_error_msg'] ?? ''));
		$decoded = json_decode((string) ($response['content'] ?? ''), true);
		if (!is_array($decoded) || !isset($decoded['transactions']) || !is_array($decoded['transactions']) || count($decoded['transactions']) > 5000) return $this->finishSyncFailure($syncId, $connector, 'Réponse invalide : transactions doit être un tableau de 0 à 5000 éléments.');
		$nextCursor = substr((string) ($decoded['next_cursor'] ?? $connector->remote_cursor), 0, 1024);
		$imported = 0;
		if ($decoded['transactions']) {
			$csv = $this->transactionsToCsv($decoded['transactions']);
			if ($csv === false) return $this->finishSyncFailure($syncId, $connector, $this->error);
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofimportservice.class.php';
			$imports = new SofImportService($this->db);
			$importId = $imports->importStatement($user, $connector->connector_type, strtolower($connector->ref).'-'.$syncRef.'.csv', $csv, (int) $connector->fk_bank_account, (int) $connector->fk_agence);
			if ($importId < 0 && stripos($imports->error, 'déjà été importé') === false) return $this->finishSyncFailure($syncId, $connector, $imports->error);
			$imported = $importId > 0 ? count($decoded['transactions']) : 0;
		}
		$checksum = hash('sha256', (string) ($response['content'] ?? ''));
		$sql = 'UPDATE '.$this->db->prefix().'sof_integration_sync SET remote_cursor_after='.$this->quote($nextCursor).',imported_count='.$imported.',response_checksum='.$this->quote($checksum).',date_end=CURRENT_TIMESTAMP,status=1 WHERE entity='.$this->entity().' AND rowid='.$syncId;
		$this->db->query($sql);
		$nextDate = $this->db->idate(dol_now() + ((int) $connector->polling_minutes * 60));
		$sql = 'UPDATE '.$this->db->prefix().'sof_integration_connector SET remote_cursor='.$this->quote($nextCursor).',date_last_sync=CURRENT_TIMESTAMP,date_next_sync='.$this->quote($nextDate).',last_error=NULL,tms=CURRENT_TIMESTAMP WHERE entity='.$this->entity().' AND rowid='.((int) $connector->rowid);
		$this->db->query($sql);
		return array('sync_id' => $syncId, 'imported' => $imported, 'next_cursor' => $nextCursor);
	}

	/** Run all due connectors under an active administrator account. */
	public function syncDueConnectors($limit = 20)
	{
		$actor = $this->cronActor();
		if (!$actor) return $this->fail('Aucun administrateur actif disponible pour la synchronisation.');
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_integration_connector WHERE entity='.$this->entity().' AND status=1 AND (date_next_sync IS NULL OR date_next_sync<=CURRENT_TIMESTAMP) ORDER BY rowid'.$this->db->plimit(max(1, min(100, (int) $limit)), 0);
		$resql = $this->db->query($sql);
		$count = 0;
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$result = $this->syncConnector($actor, (int) $row->rowid);
			if (is_array($result)) $count++;
		}
		return $count;
	}

	/** Incremental, entity- and agency-scoped BI dataset. */
	public function incrementalExport(User $user, $dataset, $cursor = '', $limit = 250, $fkAgence = 0)
	{
		if (!$this->hasRight($user, 'bi', 'export')) return $this->fail('Permission refusée pour l’export BI.');
		$definitions = $this->biDefinitions();
		if (!isset($definitions[$dataset])) return $this->fail('Jeu de données BI non autorisé.');
		$definition = $definitions[$dataset];
		$scope = SofAgenceService::allowedAgencyIds($this->db, $user);
		$fkAgence = (int) $fkAgence;
		if ($fkAgence > 0 && ($scope !== null && !in_array($fkAgence, $scope, true))) return $this->fail('Agence BI hors périmètre.');
		$position = $this->decodeCursor($cursor);
		if ($position === false) return -1;
		$limit = max(1, min(1000, (int) $limit));
		$modified = 'COALESCE(t.tms,t.date_creation)';
		$sql = 'SELECT '.implode(',', array_map(function ($field) { return 't.'.$field; }, $definition['fields'])).', '.$modified.' AS modified_at FROM '.$this->db->prefix().$definition['table'].' t WHERE t.entity='.$this->entity();
		$sql .= ' AND ('.$modified.' > '.$this->quote($position[0]).' OR ('.$modified.' = '.$this->quote($position[0]).' AND t.rowid > '.((int) $position[1]).'))';
		if ($definition['agency']) {
			if ($fkAgence > 0) $sql .= ' AND t.'.$definition['agency'].'='.$fkAgence;
			elseif ($scope !== null) $sql .= $scope ? ' AND t.'.$definition['agency'].' IN ('.implode(',', array_map('intval', $scope)).')' : ' AND 1=0';
		}
		$sql .= ' ORDER BY '.$modified.',t.rowid'.$this->db->plimit($limit + 1, 0);
		$resql = $this->db->query($sql);
		if (!$resql) return $this->fail($this->db->lasterror());
		$rows = array();
		$hasMore = false;
		while ($row = $this->db->fetch_object($resql)) {
			if (count($rows) >= $limit) { $hasMore = true; break; }
			$rows[] = (array) $row;
		}
		$last = $rows ? end($rows) : null;
		$next = $last ? $this->encodeCursor((string) $last['modified_at'], (int) $last['rowid']) : $this->encodeCursor($position[0], $position[1]);
		return array('dataset' => $dataset, 'entity' => $this->entity(), 'rows' => $rows, 'count' => count($rows), 'has_more' => $hasMore, 'next_cursor' => $next, 'generated_at' => gmdate('c'));
	}

	/** Export portable allowlisted configuration. Secrets and database identifiers are excluded. */
	public function exportConfiguration(User $user, $environment)
	{
		if (!$this->hasRight($user, 'configtransfer', 'export')) return $this->fail('Permission refusée pour exporter la configuration.');
		$environment = $this->normalizeEnvironment($environment);
		if ($environment === '') return -1;
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';
		$settings = array();
		foreach (agence_get_settings_definition() as $name => $definition) {
			if (($definition['type'] ?? '') === 'secret') continue;
			$settings[$name] = getDolGlobalString($name, (string) ($definition['default'] ?? ''));
		}
		$payload = array(
			'settings' => $settings,
			'accounting_mappings' => $this->exportResource('sof_mapping_comptable', array('code','operation_type','fk_agence','fk_das','payment_mode','journal_code','account_debit','account_credit','analytic_code','rule_expression','status')),
			'workflows' => $this->exportResource('sof_caisse_workflow', array('code','label','object_type','agency_scope','das_scope','payment_mode_scope','min_amount','max_amount','risk_level','rule_expression','validation_steps','status')),
			'notification_rules' => $this->exportResource('sof_notification_config', array('event_code','severity_min','channel','recipient_type','recipient','escalation_level','status')),
			'webhooks' => $this->exportResource('sof_webhook_endpoint', array('ref','label','endpoint_url','event_filter','fk_agence','max_attempts','status')),
			'connectors' => $this->exportResource('sof_integration_connector', array('ref','label','connector_type','endpoint_url','auth_type','fk_agence','fk_bank_account','polling_minutes','status')),
		);
		$package = array('format' => 'powererp-agence-configuration', 'format_version' => 1, 'module_version' => '2.4.1', 'editor' => 'iPowerWorld', 'source_environment' => $environment, 'generated_at' => gmdate('c'), 'payload' => $payload);
		$package['checksum'] = hash('sha256', json_encode($package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->logConfigTransfer($user, 'export', $environment, '', $package['checksum'], true, array('resources' => array_map('count', array_filter($payload, 'is_array'))), 1);
		return $package;
	}

	/** Validate then import configuration by stable business references; dry-run never writes. */
	public function importConfiguration(User $user, array $package, $targetEnvironment, $dryRun = true)
	{
		if (!$this->hasRight($user, 'configtransfer', 'import')) return $this->fail('Permission refusée pour importer la configuration.');
		$targetEnvironment = $this->normalizeEnvironment($targetEnvironment);
		if ($targetEnvironment === '') return -1;
		$sourceEnvironment = $this->normalizeEnvironment((string) ($package['source_environment'] ?? ''));
		if ($sourceEnvironment === '') return -1;
		$checksum = (string) ($package['checksum'] ?? '');
		$unsigned = $package; unset($unsigned['checksum']);
		$calculated = hash('sha256', json_encode($unsigned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$packageModuleVersion = (string) ($package['module_version'] ?? '');
		if (($package['format'] ?? '') !== 'powererp-agence-configuration' || (int) ($package['format_version'] ?? 0) !== 1
			|| !preg_match('/^2\.[0-9]+\.[0-9]+$/', $packageModuleVersion) || version_compare($packageModuleVersion, '2.3.0', '<')
			|| !hash_equals($calculated, $checksum) || !is_array($package['payload'] ?? null)) return $this->fail('Paquet de configuration invalide, altéré ou incompatible.');
		$summary = array('settings' => 0, 'accounting_mappings' => 0, 'workflows' => 0, 'notification_rules' => 0, 'webhooks' => 0, 'connectors' => 0, 'warnings' => array());
		$payload = $package['payload'];
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';
		$definitions = agence_get_settings_definition();
		$effective = array(); foreach ($definitions as $name => $def) $effective[$name] = getDolGlobalString($name, (string) ($def['default'] ?? ''));
		foreach ((array) ($payload['settings'] ?? array()) as $name => $value) {
			if (!isset($definitions[$name]) || ($definitions[$name]['type'] ?? '') === 'secret') return $this->fail('Paramètre interdit dans le paquet : '.$name);
			$normalized = ''; $validationError = '';
			if (!agence_validate_setting_update($name, $value, array_merge($effective, (array) ($payload['settings'] ?? array())), $normalized, $validationError)) return $this->fail($validationError);
			$effective[$name] = $normalized; $summary['settings']++;
		}
		$specs = $this->configurationImportSpecs();
		foreach ($specs as $resource => $spec) {
			$rows = (array) ($payload[$resource] ?? array());
			if (count($rows) > 10000) return $this->fail('Le paquet dépasse 10 000 lignes pour '.$resource.'.');
			foreach ($rows as $row) if (!is_array($row) || !$this->validateImportRow($row, $spec)) return -1;
			$summary[$resource] = count($rows);
		}
		if ($dryRun) {
			$this->logConfigTransfer($user, 'import', $sourceEnvironment, $targetEnvironment, $checksum, true, $summary, 1);
			return $summary;
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		$this->db->begin();
		foreach ($effective as $name => $value) {
			if (array_key_exists($name, (array) ($payload['settings'] ?? array())) && dolibarr_set_const($this->db, $name, $value, 'chaine', 0, '', $this->entity()) < 0) { $this->db->rollback(); return $this->fail('Échec d’import du paramètre '.$name.'.'); }
		}
		foreach ($specs as $resource => $spec) {
			foreach ((array) ($payload[$resource] ?? array()) as $row) {
				if ($this->upsertConfigurationRow($spec, $row, $user) < 0) { $this->db->rollback(); return -1; }
			}
		}
		$this->db->commit();
		$this->logConfigTransfer($user, 'import', $sourceEnvironment, $targetEnvironment, $checksum, false, $summary, 1);
		return $summary;
	}

	/** Compact operational status for REST health and the administrator diagnostic. */
	public function health(User $user)
	{
		if (!$this->hasRight($user, 'api', 'read') && !$this->hasRight($user, 'diagnostic', 'read')) return $this->fail('Permission refusée pour le diagnostic API.');
		$counts = array();
		foreach (array('webhooks_pending' => "sof_webhook_delivery WHERE status IN (0,2)", 'webhooks_failed' => 'sof_webhook_delivery WHERE status=3', 'connectors_due' => 'sof_integration_connector WHERE status=1 AND (date_next_sync IS NULL OR date_next_sync<=CURRENT_TIMESTAMP)') as $key => $from) {
			$resql = $this->db->query('SELECT COUNT(*) nb FROM '.$this->db->prefix().$from.' AND entity='.$this->entity());
			$row = $resql ? $this->db->fetch_object($resql) : null; $counts[$key] = $row ? (int) $row->nb : -1;
		}
		return array('status' => in_array(-1, $counts, true) ? 'degraded' : 'ok', 'module' => 'agence', 'version' => '2.4.1', 'entity' => $this->entity(), 'time' => gmdate('c'), 'queues' => $counts);
	}

	private function sendDolibarrNotification($eventCode, $objectType, $objectId, $fkAgence, array $data, User $actor = null)
	{
		if (!isModEnabled('notification') || !isset(self::EVENTS[$eventCode])) return 0;
		global $user;
		$oldUser = $user;
		if ($actor) $user = $actor;
		try {
			require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';
			$object = new SofIntegrationNotificationObject($this->db);
			$object->id = (int) $objectId; $object->rowid = (int) $objectId; $object->ref = (string) ($data['ref'] ?? strtoupper($objectType).'-'.$objectId);
			$object->entity = $this->entity(); $object->elementtype = 'agence'; $object->socid = (int) ($data['fk_soc'] ?? 0); $object->fk_agence = (int) $fkAgence;
			$object->label = (string) ($data['subject'] ?? $object->ref);
			$object->total_ht = isset($data['amount']) ? (float) $data['amount'] : 0;
			$object->context = array('agence_event' => $eventCode, 'agence_data' => $data);
			$notify = new Notify($this->db);
			$result = $notify->send(self::EVENTS[$eventCode], $object);
			if ($result < 0) dol_syslog(__METHOD__.' failed: '.$notify->error, LOG_WARNING);
			return $result;
		} catch (Throwable $e) {
			dol_syslog(__METHOD__.' exception: '.$e->getMessage(), LOG_WARNING);
			return -1;
		} finally {
			$user = $oldUser;
		}
	}

	private function configurationImportSpecs()
	{
		return array(
			'accounting_mappings' => array('table'=>'sof_mapping_comptable','key'=>'code','fields'=>array('code','operation_type','fk_agence','fk_das','payment_mode','journal_code','account_debit','account_credit','analytic_code','rule_expression','status')),
			'workflows' => array('table'=>'sof_caisse_workflow','key'=>'code','fields'=>array('code','label','object_type','agency_scope','das_scope','payment_mode_scope','min_amount','max_amount','risk_level','rule_expression','validation_steps','status')),
			'notification_rules' => array('table'=>'sof_notification_config','key'=>array('event_code','channel','recipient_type','recipient','escalation_level'),'fields'=>array('event_code','severity_min','channel','recipient_type','recipient','escalation_level','status')),
			'webhooks' => array('table'=>'sof_webhook_endpoint','key'=>'ref','fields'=>array('ref','label','endpoint_url','event_filter','fk_agence','max_attempts','status'),'preserve'=>array('secret_encrypted')),
			'connectors' => array('table'=>'sof_integration_connector','key'=>'ref','fields'=>array('ref','label','connector_type','endpoint_url','auth_type','fk_agence','fk_bank_account','polling_minutes','status'),'preserve'=>array('credential_encrypted','remote_cursor','date_last_sync','date_next_sync')),
		);
	}

	private function exportResource($table, array $fields)
	{
		$sql = 'SELECT '.implode(',', $fields).' FROM '.$this->db->prefix().$table.' WHERE entity='.$this->entity().' ORDER BY rowid';
		$resql = $this->db->query($sql); $rows = array();
		while ($resql && ($row = $this->db->fetch_object($resql))) {
			$value = (array) $row;
			foreach (array('fk_agence'=>'agency_ref','fk_das'=>'das_ref','fk_bank_account'=>'bank_account_ref') as $idField => $refField) {
				if (!array_key_exists($idField, $value)) continue;
				$value[$refField] = $this->referenceFor($idField, (int) $value[$idField]); unset($value[$idField]);
			}
			if (isset($value['agency_scope'])) { $value['agency_refs'] = $this->scopeReferences('fk_agence', $value['agency_scope']); unset($value['agency_scope']); }
			if (isset($value['das_scope'])) { $value['das_refs'] = $this->scopeReferences('fk_das', $value['das_scope']); unset($value['das_scope']); }
			$rows[] = $value;
		}
		return $rows;
	}

	private function validateImportRow(array &$row, array $spec)
	{
		$aliases = array('agency_ref'=>'fk_agence','das_ref'=>'fk_das','bank_account_ref'=>'fk_bank_account');
		foreach ($aliases as $refField => $idField) if (array_key_exists($refField, $row)) { $row[$idField] = $this->idForReference($idField, (string) $row[$refField]); unset($row[$refField]); if ($row[$idField] < 0) return false; }
		if (array_key_exists('agency_refs', $row)) { $row['agency_scope'] = $this->idsForReferences('fk_agence', $row['agency_refs']); unset($row['agency_refs']); if ($row['agency_scope'] === false) return false; }
		if (array_key_exists('das_refs', $row)) { $row['das_scope'] = $this->idsForReferences('fk_das', $row['das_refs']); unset($row['das_refs']); if ($row['das_scope'] === false) return false; }
		$allowed = array_flip($spec['fields']);
		foreach ($row as $field => $value) if (!isset($allowed[$field])) return (bool) $this->fail('Champ de configuration interdit : '.$field) && false;
		$keys = is_array($spec['key']) ? $spec['key'] : array($spec['key']);
		foreach ($keys as $key) if (!array_key_exists($key, $row) || trim((string) $row[$key]) === '') return (bool) $this->fail('Clé de configuration manquante : '.$key) && false;
		foreach ($row as $field => $value) {
			if (is_array($value) || is_object($value) || strlen((string) $value) > 20000) return (bool) $this->fail('Valeur de configuration invalide : '.$field) && false;
			if (in_array($field, array('endpoint_url'), true) && !$this->validateExternalUrl((string) $value)) return false;
		}
		if ($spec['table'] === 'sof_webhook_endpoint') {
			$events = $this->parseEventFilter((string) ($row['event_filter'] ?? ''));
			if (!$events) return (bool) $this->fail('Filtre d’événements webhook invalide dans le paquet.') && false;
			$row['event_filter'] = implode(',', $events);
		}
		if ($spec['table'] === 'sof_integration_connector') {
			if (!in_array((string) ($row['connector_type'] ?? ''), array('bank','orange_money','mobile_money'), true)
				|| !in_array((string) ($row['auth_type'] ?? ''), array('none','bearer','api_key','basic'), true)
				|| (int) ($row['polling_minutes'] ?? 0) < 5 || (int) ($row['polling_minutes'] ?? 0) > 10080) return (bool) $this->fail('Connecteur invalide dans le paquet.') && false;
		}
		if ($spec['table'] === 'sof_notification_config') {
			if (!in_array((string) ($row['channel'] ?? ''), array('internal','email','sms'), true)
				|| !in_array((string) ($row['recipient_type'] ?? ''), array('address','user','role'), true)) return (bool) $this->fail('Règle de notification invalide dans le paquet.') && false;
		}
		return true;
	}

	private function upsertConfigurationRow(array $spec, array $row, User $user)
	{
		if (!$this->validateImportRow($row, $spec)) return -1;
		$keys = is_array($spec['key']) ? $spec['key'] : array($spec['key']);
		$where = array(); foreach ($keys as $key) $where[] = $key.'='.$this->sqlValue($row[$key]);
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().$spec['table'].' WHERE entity='.$this->entity().' AND '.implode(' AND ', $where));
		$current = $resql ? $this->db->fetch_object($resql) : null;
		if ($current) {
			$sets = array(); foreach ($row as $field => $value) $sets[] = $field.'='.$this->sqlValue($value);
			$sets[] = 'fk_user_modif='.((int) $user->id); $sets[] = 'tms=CURRENT_TIMESTAMP';
			return $this->db->query('UPDATE '.$this->db->prefix().$spec['table'].' SET '.implode(',', $sets).' WHERE entity='.$this->entity().' AND rowid='.((int) $current->rowid)) ? 1 : $this->fail($this->db->lasterror());
		}
		if (!empty($spec['preserve'])) return $this->fail('La ressource '.$spec['table'].' doit être créée sur la cible avec son secret avant import.');
		$fields = array_merge(array('entity'), array_keys($row), array('date_creation','fk_user_creat'));
		$values = array_merge(array($this->entity()), array_map(array($this, 'sqlValue'), array_values($row)), array('CURRENT_TIMESTAMP',(int) $user->id));
		$sql = 'INSERT INTO '.$this->db->prefix().$spec['table'].' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
		return $this->db->query($sql) ? 1 : $this->fail($this->db->lasterror());
	}

	private function biDefinitions()
	{
		return array(
			'movements'=>array('table'=>'sof_caisse_mouvement','agency'=>'fk_agence','fields'=>array('rowid','ref','fk_agence','fk_caisse','fk_session','fk_das','fk_soc','fk_facture','type_operation','direction','payment_mode','amount','transaction_date','transaction_ref','status','accounting_status')),
			'sessions'=>array('table'=>'sof_caisse_session','agency'=>'fk_agence','fields'=>array('rowid','ref','fk_agence','fk_caisse','fk_das','fk_user_cashier','session_type','date_opening','date_closing','date_validation','opening_amount','theoretical_amount','physical_amount','gap_amount','accounting_status','freeze_status','status')),
			'refunds'=>array('table'=>'sof_remboursement','agency'=>'fk_agence','fields'=>array('rowid','ref','fk_soc','fk_agence','fk_das','fk_caisse','fk_session','fk_facture_origin','requested_amount','approved_amount','refunded_amount','payment_mode','request_date','validation_date','execution_date','status','accounting_status')),
			'deposits'=>array('table'=>'sof_caisse_depot_banque','agency'=>'fk_agence','fields'=>array('rowid','ref','fk_agence','fk_caisse_source','fk_session','fk_bank_account','fk_bank','amount','currency_code','date_preparation','date_deposit','date_reconcile','bank_slip_number','reconcile_reference','status')),
			'alerts'=>array('table'=>'sof_caisse_alerte','agency'=>'fk_agence','fields'=>array('rowid','ref','alert_type','severity','fk_agence','fk_caisse','fk_session','object_type','object_id','message','escalation_level','date_alert','date_close','status')),
		);
	}

	private function transactionsToCsv(array $transactions)
	{
		$fields = array('operation_date','value_date','amount','external_ref','currency_code','counterparty','description','payment_mode');
		$stream = fopen('php://temp', 'w+'); if (!$stream) return $this->fail('Impossible de préparer le relevé synchronisé.') && false;
		fputcsv($stream, $fields, ';');
		foreach ($transactions as $transaction) {
			if (!is_array($transaction)) { fclose($stream); $this->fail('Transaction distante invalide.'); return false; }
			$row = array(); foreach ($fields as $field) { $value = $transaction[$field] ?? ''; if (is_array($value) || is_object($value) || strlen((string) $value) > 4000) { fclose($stream); $this->fail('Champ distant invalide : '.$field); return false; } $row[] = (string) $value; }
			fputcsv($stream, $row, ';');
		}
		rewind($stream); $csv = stream_get_contents($stream); fclose($stream); return $csv;
	}

	private function finishSyncFailure($syncId, $connector, $message)
	{
		$message = substr(trim((string) $message), 0, 4000);
		$this->db->query('UPDATE '.$this->db->prefix().'sof_integration_sync SET error_message='.$this->quote($message).',date_end=CURRENT_TIMESTAMP,status=3 WHERE entity='.$this->entity().' AND rowid='.((int) $syncId));
		$nextDate = $this->db->idate(dol_now() + max(300, (int) $connector->polling_minutes * 60));
		$this->db->query('UPDATE '.$this->db->prefix().'sof_integration_connector SET last_error='.$this->quote($message).',date_next_sync='.$this->quote($nextDate).',tms=CURRENT_TIMESTAMP WHERE entity='.$this->entity().' AND rowid='.((int) $connector->rowid));
		return $this->fail($message);
	}

	private function parseEventFilter($raw)
	{
		$values = is_array($raw) ? $raw : preg_split('/[,;\s]+/', strtolower(trim((string) $raw)), -1, PREG_SPLIT_NO_EMPTY);
		$out = array(); foreach ($values as $event) { $event = strtolower(trim((string) $event)); if (isset(self::EVENTS[$event])) $out[$event] = $event; else return array(); }
		return array_values($out);
	}

	private function validateExternalUrl($url)
	{
		if (strlen($url) > 1024 || !filter_var($url, FILTER_VALIDATE_URL)) return (bool) $this->fail('URL HTTPS externe invalide.') && false;
		$parts = parse_url($url);
		if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) return (bool) $this->fail('Seules les URL HTTPS externes sans identifiants ni fragment sont autorisées.') && false;
		return true;
	}

	private function encodeCursor($modified, $rowid)
	{
		return rtrim(strtr(base64_encode(json_encode(array((string) $modified, (int) $rowid))), '+/', '-_'), '=');
	}

	private function decodeCursor($cursor)
	{
		if ($cursor === '') return array('1970-01-01 00:00:00', 0);
		if (strlen($cursor) > 512 || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) return $this->cursorFail();
		$raw = base64_decode(strtr($cursor, '-_', '+/'), true); $value = $raw !== false ? json_decode($raw, true) : null;
		$timestamp = is_array($value) && isset($value[0]) ? (string) $value[0] : '';
		if (!is_array($value) || count($value) !== 2 || strlen($timestamp) > 64 || !preg_match('/^[0-9T:+. Z-]+$/', $timestamp) || strtotime($timestamp) === false || (int) $value[1] < 0) return $this->cursorFail();
		// Preserve database precision (including PostgreSQL microseconds). Reducing
		// the timestamp to seconds would replay the last row on the next page.
		return array($timestamp, (int) $value[1]);
	}

	private function cursorFail() { $this->fail('Curseur BI invalide.'); return false; }

	private function referenceFor($type, $id)
	{
		if ($id <= 0) return '';
		$map = array('fk_agence'=>array('sof_agence','ref'),'fk_das'=>array('sof_das','ref'),'fk_bank_account'=>array('bank_account','ref'));
		if (!isset($map[$type])) return '';
		$resql = $this->db->query('SELECT '.$map[$type][1].' ref FROM '.$this->db->prefix().$map[$type][0].' WHERE entity='.$this->entity().' AND rowid='.$id);
		$row = $resql ? $this->db->fetch_object($resql) : null; return $row ? (string) $row->ref : '';
	}

	private function idForReference($type, $ref)
	{
		if ($ref === '') return 0;
		$map = array('fk_agence'=>array('sof_agence','ref'),'fk_das'=>array('sof_das','ref'),'fk_bank_account'=>array('bank_account','ref'));
		if (!isset($map[$type]) || strlen($ref) > 255) { $this->fail('Référence de configuration invalide.'); return -1; }
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().$map[$type][0].' WHERE entity='.$this->entity().' AND '.$map[$type][1].'='.$this->quote($ref));
		$row = $resql ? $this->db->fetch_object($resql) : null; if (!$row) { $this->fail('Référence cible introuvable : '.$ref); return -1; } return (int) $row->rowid;
	}

	private function scopeReferences($type, $raw)
	{
		$valid = false; $ids = SofAgenceService::parseIdList((string) $raw, $valid); if (!$valid) return array();
		$refs = array(); foreach ($ids as $id) { $ref = $this->referenceFor($type, $id); if ($ref !== '') $refs[] = $ref; } return $refs;
	}

	private function idsForReferences($type, $refs)
	{
		if (!is_array($refs)) { $this->fail('Liste de références invalide.'); return false; }
		$ids = array(); foreach ($refs as $ref) { $id = $this->idForReference($type, (string) $ref); if ($id < 0) return false; if ($id > 0) $ids[] = $id; } return implode(',', $ids);
	}

	private function normalizeEnvironment($environment)
	{
		$environment = strtolower(trim((string) $environment));
		if (!in_array($environment, array('development','staging','production'), true)) { $this->fail('Environnement attendu : development, staging ou production.'); return ''; }
		return $environment;
	}

	private function logConfigTransfer(User $user, $direction, $source, $target, $checksum, $dryRun, array $summary, $status)
	{
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_config_transfer (entity,ref,direction,source_environment,target_environment,package_version,package_checksum,dry_run,summary_json,status,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().','.$this->quote($this->makeRef('CFG')).','.$this->quote($direction).','.$this->quote($source).','.$this->quote($target).",'1',".$this->quote($checksum).','.($dryRun ? 1 : 0).','.$this->quote(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).','.(int) $status.',CURRENT_TIMESTAMP,'.((int) $user->id).')';
		return $this->db->query($sql) ? 1 : -1;
	}

	private function cronActor()
	{
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'user WHERE admin=1 AND statut=1 AND entity IN ('.getEntity('user').') ORDER BY rowid'.$this->db->plimit(1, 0));
		$row = $resql ? $this->db->fetch_object($resql) : null; if (!$row) return null;
		$actor = new User($this->db); if ($actor->fetch((int) $row->rowid) <= 0) return null; $actor->loadRights(); return $actor;
	}

	private function hasRight(User $user, $object, $action)
	{
		if (!SofAgenceService::isActiveUser($this->db, $user)) return false;
		if (!empty($user->admin)) return true;
		$fresh = new User($this->db); if ($fresh->fetch((int) $user->id) <= 0 || empty($fresh->statut)) return false; $fresh->loadRights('agence', 1);
		return (bool) $fresh->hasRight('agence', $object, $action);
	}

	private function entity() { global $conf; return (int) $conf->entity; }
	private function quote($value) { return "'".$this->db->escape((string) $value)."'"; }
	private function sqlValue($value) { if ($value === null || $value === '') return 'NULL'; if (is_bool($value)) return $value ? '1' : '0'; if (is_int($value) || is_float($value)) return (string) $value; return $this->quote($value); }
	private function makeRef($prefix) { return $prefix.'-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 12)); }
	private function fail($message, array $errors = array()) { $this->error = (string) $message; $this->errors = $errors; return -1; }
}
