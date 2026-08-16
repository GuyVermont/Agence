<?php
/* Copyright (C) 2026 SOFITOUL */

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

echo 'Schema check: '.count($errors).' error(s), '.count($warnings).' warning(s).'.PHP_EOL;
exit(empty($errors) ? 0 : 1);
