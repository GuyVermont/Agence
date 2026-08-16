<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * CLI quick check for the Agence module.
 *
 * Usage from Laragon/Dolibarr root:
 * php htdocs/custom/agence/test/quick_check.php
 */

if (PHP_SAPI !== 'cli') {
	die("This check is CLI only.\n");
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);

$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/modules/modAgence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/modules/agence/doc/pdf_agence_standard.modules.php';

$errors = 0;

function agence_check_line($ok, $label)
{
	global $errors;
	echo ($ok ? '[OK] ' : '[KO] ').$label.PHP_EOL;
	if (!$ok) {
		$errors++;
	}
}

$module = new modAgence($GLOBALS['db']);
agence_check_line($module->name === 'Agence', 'module descriptor loaded');
agence_check_line(version_compare($module->version, '2.0.0', '>='), 'operational module version loaded');
agence_check_line(count($module->rights) >= 35, 'module permissions declared');
agence_check_line(count($module->menu) >= 16, 'module menus declared');

$registry = agence_get_object_registry();
agence_check_line(count($registry) >= 32, 'CRUD object registry loaded');
foreach ($registry as $key => $config) {
	$object = agence_new_object($config);
	agence_check_line(!empty($object->table_element) && count($object->fields) > 0, 'object '.$key.' maps to '.$object->table_element);
}

$pdf = new pdf_agence_standard($GLOBALS['db']);
agence_check_line($pdf->type === 'pdf', 'PDF model loaded');
agence_check_line(function_exists('agence_report_dataset'), 'reporting helpers loaded');

$sqlFiles = glob(DOL_DOCUMENT_ROOT.'/custom/agence/sql/*.sql');
agence_check_line(count($sqlFiles) >= 8, 'SQL install files present');

$requiredTables = array('sof_agence', 'sof_das', 'sof_caisse', 'sof_caisse_session', 'sof_caisse_mouvement', 'sof_paiement_differe', 'sof_remboursement', 'sof_caisse_auditlog');
foreach ($requiredTables as $table) {
	$info = $GLOBALS['db']->DDLInfoTable($GLOBALS['db']->prefix().$table);
	echo (!empty($info) ? '[OK] ' : '[WARN] ').'database table '.$GLOBALS['db']->prefix().$table.(!empty($info) ? ' exists' : ' not found yet, activate/reload module SQL').PHP_EOL;
}

if (isModEnabled('agence')) {
	agence_check_line(true, 'Agence module is enabled');
} else {
	echo "[WARN] Agence module is not enabled in the current entity.\n";
}
echo isModEnabled('sofops') ? "[WARN] SofOps is still enabled; disable it before production.\n" : "[OK] SofOps module is disabled\n";

$caisseTable = $GLOBALS['db']->prefix().'sof_caisse';
$accountFields = array('fk_bank_account', 'fk_bank_account_card', 'fk_bank_account_cheque', 'fk_bank_account_mobile', 'fk_bank_account_other');
foreach ($accountFields as $field) {
	$description = $GLOBALS['db']->DDLDescTable($caisseTable, $field);
	agence_check_line($description && $GLOBALS['db']->num_rows($description) > 0, 'cash desk account field '.$field.' installed');
}

$sql = 'SELECT COUNT(*) AS nb FROM '.$GLOBALS['db']->prefix()."menu WHERE module = 'agence' AND entity IN (0, ".((int) $GLOBALS['conf']->entity).')';
$resql = $GLOBALS['db']->query($sql);
$installedMenus = $resql ? (int) $GLOBALS['db']->fetch_object($resql)->nb : 0;
agence_check_line($installedMenus >= 16, 'Agence menus installed in database');

echo $errors ? "Quick check failed with ".$errors." blocking error(s).\n" : "Quick check completed without blocking errors.\n";
exit($errors ? 1 : 0);
