<?php
/* Copyright (C) 2026 iPowerWorld */

if (PHP_SAPI !== 'cli') die("CLI only\n");
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);
$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/modules/modAgence.class.php';

$errors = array();
function agence_upgrade_assert($condition, $label, $detail = '')
{
	global $errors;
	echo ($condition ? '[OK] ' : '[KO] ').$label.(!$condition && $detail !== '' ? ' — '.$detail : '').PHP_EOL;
	if (!$condition) $errors[] = $label;
}

$module = new modAgence($db);
$result = $module->init();
agence_upgrade_assert($result > 0, 'module activation applies additive industrial migrations', $module->error);
$tables = array('sof_notification_config','sof_notification_outbox','sof_bank_import','sof_bank_import_line','sof_recouvrement','sof_recouvrement_action','sof_bulk_import','sof_bulk_import_line','sof_technical_error','sof_financial_reversal','sof_archive_log');
foreach ($tables as $table) {
	$resql = $db->DDLDescTable($db->prefix().$table, '');
	agence_upgrade_assert($resql && $db->num_rows($resql) > 0, 'industrial table '.$table.' is installed');
}
foreach (array('escalation_level','date_last_escalation') as $field) {
	$resql = $db->DDLDescTable($db->prefix().'sof_caisse_alerte', $field);
	agence_upgrade_assert($resql && $db->num_rows($resql) > 0, 'alert field '.$field.' is installed');
}
foreach (array('archive_status','date_archive','purge_after') as $field) {
	$resql = $db->DDLDescTable($db->prefix().'sof_caisse_auditlog', $field);
	agence_upgrade_assert($resql && $db->num_rows($resql) > 0, 'audit retention field '.$field.' is installed');
}
$resql = $db->query('SELECT COUNT(*) nb FROM '.$db->prefix()."cronjob WHERE module_name='agence' AND status=1 AND ((objectname='SofAlerte' AND methodename='detectAlerts') OR (objectname='SofAgenceIndustrialService' AND methodename='runScheduledOperations'))");
$row = $resql ? $db->fetch_object($resql) : null;
$cronDetail = array();
if (!$row || (int) $row->nb !== 2) {
	$detailResult = $db->query('SELECT rowid,module_name,objectname,methodename,status FROM '.$db->prefix()."cronjob WHERE module_name='agence' ORDER BY rowid");
	while ($detailResult && ($detail = $db->fetch_object($detailResult))) $cronDetail[] = implode(':', array($detail->rowid,$detail->objectname,$detail->methodename,$detail->status));
}
agence_upgrade_assert($row && (int) $row->nb === 2, 'both Agence scheduled jobs are installed and active', implode(', ', $cronDetail));
$resql = $db->query('SELECT COUNT(*) nb FROM '.$db->prefix()."rights_def WHERE entity=".((int) $conf->entity)." AND module='agence'");
$row = $resql ? $db->fetch_object($resql) : null;
agence_upgrade_assert($row && (int) $row->nb >= 45, 'all Agence permissions are installed in the current entity');
$resql = $db->query('SELECT COUNT(*) nb FROM '.$db->prefix()."menu WHERE module='agence' AND entity IN (0,".((int) $conf->entity).')');
$row = $resql ? $db->fetch_object($resql) : null;
agence_upgrade_assert($row && (int) $row->nb === count($module->menu), 'all Agence menus are refreshed during upgrade', $row ? ((int) $row->nb).'/'.count($module->menu) : 'query failed');

if ($errors) {
	fwrite(STDERR, 'Install/upgrade check failed: '.implode(', ', $errors).PHP_EOL);
	exit(1);
}
echo "Install/upgrade check completed successfully.\n";
