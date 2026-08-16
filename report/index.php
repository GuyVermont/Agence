<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';

$langs->loadLangs(array('agence@agence'));
$dashboards = agence_report_available_dashboards();
if (empty($dashboards)) {
	accessforbidden();
}

$start = agence_report_get_date('date_start', date('Y-m-01'));
$end = agence_report_get_date('date_end', date('Y-m-d'));
$action = GETPOST('action', 'alpha');
$dashboardKey = GETPOST('dashboard', 'alpha');
if ($dashboardKey === '' || !isset($dashboards[$dashboardKey])) {
	$dashboardKey = (string) array_key_first($dashboards);
}
$dashboard = $dashboards[$dashboardKey];

if ($action === 'export') {
	if (!$user->hasRight('agence', 'report', 'export')) {
		accessforbidden();
	}
	$requestedDataset = GETPOST('dataset', 'alpha');
	$allowedDatasets = array_column($dashboards, 'dataset');
	if (!in_array($requestedDataset, $allowedDatasets, true)) {
		accessforbidden('Ce tableau de bord ne correspond pas au rôle courant.');
	}
	agence_report_export_csv($requestedDataset, $start, $end);
}

llxHeader('', $langs->trans('ReportsStatistics'), '', '', 0, 0, '', '', '', 'mod-agence page-report_index');
print load_fiche_titre($langs->trans('ReportsStatistics'), '', 'chart');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<div class="fichecenter">';
print '<label>Vue métier <select class="flat minwidth200" name="dashboard">';
foreach ($dashboards as $key => $definition) {
	print '<option value="'.dol_escape_htmltag($key).'"'.($key === $dashboardKey ? ' selected' : '').'>'.dol_escape_htmltag($definition['label']).'</option>';
}
print '</select></label> ';
print '<label>'.$langs->trans('DateStart').' <input type="date" class="flat" name="date_start" value="'.dol_escape_htmltag($start).'"></label> ';
print '<label>'.$langs->trans('DateEnd').' <input type="date" class="flat" name="date_end" value="'.dol_escape_htmltag($end).'"></label> ';
print '<input type="submit" class="button" value="'.$langs->trans('Refresh').'">';
print '</div></form>';

agence_report_print_kpis(agence_report_kpis($start, $end));
agence_report_print_table(
	$dashboard['label'],
	agence_report_dataset($dashboard['dataset'], $start, $end),
	$dashboard['columns'],
	$dashboard['dataset'],
	$start,
	$end
);

llxFooter();
$db->close();
