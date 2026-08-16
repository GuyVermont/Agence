<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/index.php
 * \ingroup    agence
 * \brief      Agency management dashboard.
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';

$langs->loadLangs(array('agence@agence', 'companies', 'bills', 'banks', 'users'));

if (!$user->hasRight('agence', 'agence', 'read') && !$user->hasRight('agence', 'report', 'read')) {
	accessforbidden();
}

$title = $langs->trans('ModuleAgenceName');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-agence page-index');

print load_fiche_titre($title, '', 'building');

$start = date('Y-m-01');
$end = date('Y-m-d');
agence_report_print_kpis(agence_report_kpis($start, $end));

print '<div class="tabsAction">';
if ($user->hasRight('agence', 'session', 'open') || $user->hasRight('agence', 'mouvement', 'cashin')) {
	print '<a class="butAction" href="'.dol_buildpath('/agence/session/my.php', 1).'">'.$langs->trans('MyCashDesk').'</a>';
}
if ($user->hasRight('agence', 'report', 'read')) {
	print '<a class="butAction" href="'.dol_buildpath('/agence/report/index.php', 1).'">'.$langs->trans('ReportsStatistics').'</a>';
}
print '</div>';

print '<div class="fichecenter">';
print '<div class="fichethirdleft">';
print '<div class="info-box">';
print '<span class="info-box-icon bg-infobox-action"><i class="fa fa-building"></i></span>';
print '<div class="info-box-content"><span class="info-box-text">'.$langs->trans('AgencyScope').'</span><span class="info-box-number">'.$langs->trans('AgencyScopeDesc').'</span></div>';
print '</div>';
print '</div>';

print '<div class="fichetwothirdright">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('OperationalDomains').'</td><td>'.$langs->trans('DolibarrReuse').'</td><td>'.$langs->trans('Status').'</td></tr>';

$domains = agence_get_operational_domains($langs);
foreach ($domains as $domain) {
	print '<tr class="oddeven">';
	print '<td><span class="badge badge-status4 badge-status">'.dol_escape_htmltag($domain['label']).'</span><br><span class="opacitymedium">'.dol_escape_htmltag($domain['description']).'</span></td>';
	print '<td>'.dol_escape_htmltag($domain['reuse']).'</td>';
	print '<td>'.dol_escape_htmltag($domain['status']).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="clearboth"></div><br>';

print '<div class="fichecenter">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('ReportingDashboards').'</td><td>'.$langs->trans('TargetUsers').'</td><td>'.$langs->trans('MainIndicators').'</td></tr>';

$dashboards = agence_get_reporting_dashboards($langs);
foreach ($dashboards as $dashboard) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($dashboard['label']).'</td>';
	print '<td>'.dol_escape_htmltag($dashboard['audience']).'</td>';
	print '<td>'.dol_escape_htmltag($dashboard['indicators']).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';

llxFooter();
$db->close();
