<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';

$langs->loadLangs(array('agence@agence'));
if (!$user->hasRight('agence', 'report', 'read')) {
	accessforbidden();
}

$start = agence_report_get_date('date_start', date('Y-m-01'));
$end = agence_report_get_date('date_end', date('Y-m-d'));
$action = GETPOST('action', 'alpha');
$dataset = GETPOST('dataset', 'alpha');

if ($action === 'export') {
	if (!$user->hasRight('agence', 'report', 'export')) {
		accessforbidden();
	}
	agence_report_export_csv($dataset, $start, $end);
}

llxHeader('', $langs->trans('ReportsStatistics'), '', '', 0, 0, '', '', '', 'mod-agence page-report_index');
print load_fiche_titre($langs->trans('ReportsStatistics'), '', 'chart');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<div class="fichecenter">';
print '<label>'.$langs->trans('DateStart').' <input type="date" class="flat" name="date_start" value="'.dol_escape_htmltag($start).'"></label> ';
print '<label>'.$langs->trans('DateEnd').' <input type="date" class="flat" name="date_end" value="'.dol_escape_htmltag($end).'"></label> ';
print '<input type="submit" class="button" value="'.$langs->trans('Refresh').'">';
print '</div>';
print '</form>';

agence_report_print_kpis(agence_report_kpis($start, $end));

agence_report_print_table($langs->trans('ReportDailyCash'), agence_report_dataset('daily_cash', $start, $end), array(
	'fk_agence' => $langs->trans('Agency'),
	'fk_caisse' => $langs->trans('CashDesk'),
	'payment_mode' => $langs->trans('PaymentMode'),
	'direction' => $langs->trans('Direction'),
	'nb_operations' => $langs->trans('NbOperations'),
	'total_amount' => $langs->trans('Amount'),
), 'daily_cash', $start, $end);

agence_report_print_table($langs->trans('ReportDeferredPayments'), agence_report_dataset('deferred', $start, $end), array(
	'fk_agence' => $langs->trans('Agency'),
	'status' => $langs->trans('Status'),
	'nb_records' => $langs->trans('NbRecords'),
	'expected_amount' => $langs->trans('ExpectedAmount'),
	'remaining_amount' => $langs->trans('RemainingAmount'),
), 'deferred', $start, $end);

agence_report_print_table($langs->trans('ReportCashGaps'), agence_report_dataset('gaps', $start, $end), array(
	'ref' => $langs->trans('Ref'),
	'fk_agence' => $langs->trans('Agency'),
	'fk_caisse' => $langs->trans('CashDesk'),
	'gap_type' => $langs->trans('GapType'),
	'severity' => $langs->trans('Severity'),
	'gap_amount' => $langs->trans('GapAmount'),
	'status' => $langs->trans('Status'),
), 'gaps', $start, $end);

agence_report_print_table($langs->trans('ReportBankDeposits'), agence_report_dataset('deposits', $start, $end), array(
	'fk_agence' => $langs->trans('Agency'),
	'status' => $langs->trans('Status'),
	'nb_records' => $langs->trans('NbRecords'),
	'total_amount' => $langs->trans('Amount'),
), 'deposits', $start, $end);

agence_report_print_table($langs->trans('Refunds'), agence_report_dataset('refunds', $start, $end), array(
	'ref' => $langs->trans('Ref'),
	'fk_soc' => $langs->trans('ThirdParty'),
	'fk_agence' => $langs->trans('Agency'),
	'payment_mode' => $langs->trans('PaymentMode'),
	'requested_amount' => $langs->trans('RequestedAmount'),
	'approved_amount' => $langs->trans('ApprovedAmount'),
	'refunded_amount' => $langs->trans('RefundedAmount'),
	'status' => $langs->trans('Status'),
	'execution_date' => $langs->trans('ExecutionDate'),
), 'refunds', $start, $end);

llxFooter();
$db->close();
