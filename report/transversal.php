<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';

$langs->loadLangs(array('agence@agence'));
if (!$user->hasRight('agence', 'dashboard', 'direction') && !$user->hasRight('agence', 'scope', 'write')) {
	accessforbidden();
}

$start = agence_report_get_date('date_start', date('Y-m-01'));
$end = agence_report_get_date('date_end', date('Y-m-d'));
$action = GETPOST('action', 'alpha');

if ($action === 'export') {
	if (!$user->hasRight('agence', 'report', 'export') && !$user->hasRight('agence', 'scope', 'write')) {
		accessforbidden();
	}
	agence_report_export_csv('transversal', $start, $end);
}

llxHeader('', $langs->trans('TransversalManagement'), '', '', 0, 0, '', '', '', 'mod-agence page-report_transversal');
print load_fiche_titre($langs->trans('TransversalReports'), '', 'sitemap');

agence_report_print_table($langs->trans('AgencyConsolidation'), agence_report_transversal_rows(), array(
	'ref' => $langs->trans('Ref'),
	'label' => $langs->trans('Label'),
	'town' => $langs->trans('Town'),
	'status' => $langs->trans('Status'),
	'nb_cashdesks' => $langs->trans('NbCashDesks'),
	'nb_open_sessions' => $langs->trans('OpenSessions'),
	'deferred_remaining' => $langs->trans('DeferredRemaining'),
	'open_gap_amount' => $langs->trans('OpenCashGaps'),
	'nb_unreconciled_deposits' => $langs->trans('UnreconciledDeposits'),
	'nb_open_alerts' => $langs->trans('OpenAlerts'),
), 'transversal', $start, $end);

llxFooter();
$db->close();
