<?php
/* Copyright (C) 2026 iPowerWorld */

/** Read-only schema and data-integrity qualification for the Agence module. */
if (PHP_SAPI !== 'cli') {
	die("CLI only\n");
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);

$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';

$errors = array();
$warnings = array();
function agence_schema_result($ok, $label, $warning = false)
{
	global $errors, $warnings;
	echo ($ok ? '[OK] ' : ($warning ? '[WARN] ' : '[KO] ')).$label.PHP_EOL;
	if (!$ok) {
		if ($warning) {
			$warnings[] = $label;
		} else {
			$errors[] = $label;
		}
	}
}

function agence_schema_scalar($sql)
{
	global $db;
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : null;
	return $row && isset($row->nb) ? (int) $row->nb : -1;
}

function agence_schema_has_index($table, $index)
{
	global $db;
	if ($db->type === 'pgsql') {
		$sql = "SELECT COUNT(*) nb FROM pg_indexes WHERE schemaname = current_schema() AND tablename = '".$db->escape($table)."' AND indexname = '".$db->escape($index)."'";
	} elseif (in_array($db->type, array('mysql', 'mysqli'), true)) {
		$sql = "SELECT COUNT(*) nb FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '".$db->escape($table)."' AND index_name = '".$db->escape($index)."'";
	} else {
		return null;
	}
	return agence_schema_scalar($sql) > 0;
}

$registry = agence_get_object_registry();
$seenTables = array();
foreach ($registry as $key => $config) {
	$object = agence_new_object($config);
	$table = $db->prefix().$object->table_element;
	if (isset($seenTables[$table])) {
		continue;
	}
	$seenTables[$table] = true;
	$exists = !empty($db->DDLInfoTable($table));
	if (!$exists) {
		agence_schema_result(false, $table.' exists for object '.$key);
		continue;
	}
	$missing = array();
	foreach (array_keys($object->fields) as $field) {
		$description = $db->DDLDescTable($table, $field);
		if (!$description || $db->num_rows($description) === 0) {
			$missing[] = $field;
		}
	}
	foreach (array('rowid', 'entity') as $standardField) {
		$description = $db->DDLDescTable($table, $standardField);
		if ((!$description || $db->num_rows($description) === 0) && !in_array($standardField, $missing, true)) {
			$missing[] = $standardField;
		}
	}
	agence_schema_result(empty($missing), $table.' matches declared fields'.(empty($missing) ? '' : ': '.implode(', ', $missing)));

	if (isset($object->fields['ref'])) {
		$sql = 'SELECT COUNT(*) nb FROM (SELECT entity, ref FROM '.$table." WHERE ref IS NOT NULL AND ref <> '' GROUP BY entity, ref HAVING COUNT(*) > 1) duplicates";
		agence_schema_result(agence_schema_scalar($sql) === 0, $table.' has no duplicate entity/ref values');
	}
	if (isset($object->fields['fk_agence'])) {
		$sql = 'SELECT COUNT(*) nb FROM '.$table.' t LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid=t.fk_agence AND a.entity=t.entity';
		$sql .= ' WHERE t.fk_agence IS NOT NULL AND t.fk_agence > 0 AND a.rowid IS NULL';
		agence_schema_result(agence_schema_scalar($sql) === 0, $table.' has no orphan agency reference');
	}
}

$industrialStructures = array(
	'sof_notification_config' => array('event_code','severity_min','channel','recipient_type','recipient','escalation_level','status'),
	'sof_notification_outbox' => array('dedup_key','event_code','channel','recipient','attempts','max_attempts','next_attempt','status'),
	'sof_bank_import' => array('source_type','fk_bank_account','file_checksum','line_count','matched_count','status'),
	'sof_bank_import_line' => array('fk_import','operation_date','amount','fk_bank','fk_mouvement','match_score','status'),
	'sof_recouvrement' => array('fk_paiement_differe','fk_soc','fk_agence','stage','outstanding_amount','status'),
	'sof_recouvrement_action' => array('fk_recouvrement','action_type','notes','date_action'),
	'sof_bulk_import' => array('object_type','import_mode','file_checksum','created_count','updated_count','error_count','status'),
	'sof_bulk_import_line' => array('fk_import','line_number','payload','action_taken','status'),
	'sof_technical_error' => array('operation_code','retry_handler','payload','attempts','max_attempts','next_retry','status'),
	'sof_financial_reversal' => array('fk_mouvement_original','fk_mouvement_reversal','reason','evidence_ref','status'),
	'sof_archive_log' => array('object_type','object_id','policy_code','action_type','content_hash','action_date'),
	'sof_webhook_endpoint' => array('ref','endpoint_url','event_filter','secret_encrypted','max_attempts','status'),
	'sof_webhook_delivery' => array('delivery_ref','event_id','event_code','fk_endpoint','payload','attempts','next_attempt','http_status','status'),
	'sof_integration_connector' => array('ref','connector_type','endpoint_url','auth_type','credential_encrypted','remote_cursor','date_next_sync','status'),
	'sof_integration_sync' => array('ref','fk_connector','direction','imported_count','response_checksum','date_start','status'),
	'sof_config_transfer' => array('ref','direction','source_environment','target_environment','package_checksum','dry_run','status'),
);
foreach ($industrialStructures as $tableName => $fields) {
	$table = $db->prefix().$tableName;
	$missing = array();
	foreach (array_merge(array('rowid','entity'), $fields) as $field) {
		$description = $db->DDLDescTable($table, $field);
		if (!$description || $db->num_rows($description) === 0) $missing[] = $field;
	}
	agence_schema_result(empty($missing), $table.' exposes the industrial contract'.(empty($missing) ? '' : ': '.implode(', ', $missing)));
}

foreach (array('archive_status','date_archive','purge_after') as $field) {
	$description = $db->DDLDescTable($db->prefix().'sof_caisse_auditlog', $field);
	agence_schema_result($description && $db->num_rows($description) > 0, 'audit retention field '.$field.' is installed');
}

$sessionMismatch = agence_schema_scalar('SELECT COUNT(*) nb FROM '.$db->prefix().'sof_caisse_session s LEFT JOIN '.$db->prefix().'sof_caisse c ON c.rowid=s.fk_caisse AND c.entity=s.entity WHERE c.rowid IS NULL OR c.fk_agence <> s.fk_agence');
agence_schema_result($sessionMismatch === 0, 'cash sessions match an existing cash desk in the same agency');

$movementMismatch = agence_schema_scalar('SELECT COUNT(*) nb FROM '.$db->prefix().'sof_caisse_mouvement m LEFT JOIN '.$db->prefix().'sof_caisse_session s ON s.rowid=m.fk_session AND s.entity=m.entity WHERE s.rowid IS NULL OR s.fk_agence <> m.fk_agence OR s.fk_caisse <> m.fk_caisse');
agence_schema_result($movementMismatch === 0, 'ledger movements match their session, agency and cash desk');

$parallelCashDesk = agence_schema_scalar('SELECT COUNT(*) nb FROM (SELECT s.entity, s.fk_caisse FROM '.$db->prefix().'sof_caisse_session s JOIN '.$db->prefix().'sof_caisse c ON c.rowid=s.fk_caisse AND c.entity=s.entity WHERE COALESCE(c.allow_parallel_sessions,0)=0 AND s.status IN (1,2,3,4,5) GROUP BY s.entity,s.fk_caisse HAVING COUNT(*) > 1) duplicates');
agence_schema_result($parallelCashDesk === 0, 'non-parallel cash desks have at most one active session');

$parallelCashier = agence_schema_scalar('SELECT COUNT(*) nb FROM (SELECT entity, fk_user_cashier FROM '.$db->prefix().'sof_caisse_session WHERE status IN (1,2,3,4,5) GROUP BY entity,fk_user_cashier HAVING COUNT(*) > 1) duplicates');
agence_schema_result($parallelCashier === 0, 'cashiers have at most one active session');

$duplicatePaymentLinks = agence_schema_scalar('SELECT COUNT(*) nb FROM (SELECT fk_paiement,fk_facture FROM '.$db->prefix().'sof_paiement_link GROUP BY fk_paiement,fk_facture HAVING COUNT(*) > 1) duplicates');
agence_schema_result($duplicatePaymentLinks === 0, 'payment/invoice links contain no duplicate pair');

$indexState = agence_schema_has_index($db->prefix().'sof_paiement_link', 'uk_sof_paiement_link_payment_invoice');
agence_schema_result($indexState === true, 'unique payment/invoice index is installed', $indexState === false || $indexState === null);

$alertIndexState = agence_schema_has_index($db->prefix().'sof_caisse_alerte', 'uk_sof_caisse_alerte_dedup');
agence_schema_result($alertIndexState === true, 'unique open-alert deduplication index is installed', $alertIndexState === false || $alertIndexState === null);

foreach (array(
	array('sof_notification_outbox','uk_sof_notif_outbox_dedup'),
	array('sof_bank_import','uk_sof_bank_import_checksum'),
	array('sof_bank_import_line','uk_sof_bank_import_line_bank'),
	array('sof_bank_import_line','uk_sof_bank_import_line_movement'),
	array('sof_recouvrement','uk_sof_recouvrement_deferred'),
	array('sof_bulk_import','uk_sof_bulk_import_checksum'),
	array('sof_financial_reversal','uk_sof_financial_reversal_original'),
	array('sof_webhook_endpoint','uk_sof_webhook_endpoint_ref'),
	array('sof_webhook_delivery','uk_sof_webhook_delivery_endpoint_event'),
	array('sof_integration_connector','uk_sof_integration_connector_ref'),
	array('sof_integration_sync','uk_sof_integration_sync_ref'),
	array('sof_config_transfer','uk_sof_config_transfer_ref'),
) as $indexCheck) {
	$state = agence_schema_has_index($db->prefix().$indexCheck[0], $indexCheck[1]);
	agence_schema_result($state === true, 'unique industrial index '.$indexCheck[1].' is installed', $state === false || $state === null);
}

$orphanImportLines = agence_schema_scalar('SELECT COUNT(*) nb FROM '.$db->prefix().'sof_bank_import_line l LEFT JOIN '.$db->prefix().'sof_bank_import i ON i.rowid=l.fk_import AND i.entity=l.entity WHERE i.rowid IS NULL');
agence_schema_result($orphanImportLines === 0, 'bank statement lines belong to an import in the same entity');
$orphanCollectionActions = agence_schema_scalar('SELECT COUNT(*) nb FROM '.$db->prefix().'sof_recouvrement_action a LEFT JOIN '.$db->prefix().'sof_recouvrement r ON r.rowid=a.fk_recouvrement AND r.entity=a.entity WHERE r.rowid IS NULL');
agence_schema_result($orphanCollectionActions === 0, 'collection actions belong to a case in the same entity');
$invalidApprovedReversals = agence_schema_scalar('SELECT COUNT(*) nb FROM '.$db->prefix().'sof_financial_reversal WHERE status=2 AND (fk_mouvement_reversal IS NULL OR date_decision IS NULL OR fk_user_approval IS NULL)');
agence_schema_result($invalidApprovedReversals === 0, 'approved reversals reference their opposite movement and decision');

echo 'Schema check: '.count($errors).' error(s), '.count($warnings).' warning(s).'.PHP_EOL;
exit(empty($errors) ? 0 : 1);
