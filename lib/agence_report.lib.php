<?php
/* Copyright (C) 2026 SOFITOUL */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';

/**
 * \file       htdocs/custom/agence/lib/agence_report.lib.php
 * \ingroup    agence
 * \brief      Reporting helpers for SOFITOUL agency module.
 */

/**
 * Normalize a request date.
 *
 * @param string $name    Request key
 * @param string $default Default YYYY-MM-DD
 * @return string
 */
function agence_report_get_date($name, $default)
{
	$value = GETPOST($name, 'alpha');
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $default;
}

/**
 * Execute a query and return rows.
 *
 * @param string $sql SQL query
 * @return array<int,object>
 */
function agence_report_rows($sql)
{
	global $db;

	$rows = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('Agence report query failed: '.$db->lasterror().' | '.$sql, LOG_WARNING);
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
	return $rows;
}

/**
 * Return a scalar result.
 *
 * @param string $sql     SQL query
 * @param string $field   Field name
 * @param mixed  $default Default value
 * @return mixed
 */
function agence_report_scalar($sql, $field, $default = 0)
{
	$rows = agence_report_rows($sql);
	if (empty($rows) || !isset($rows[0]->$field)) {
		return $default;
	}
	return $rows[0]->$field;
}

/**
 * SQL condition for a datetime period.
 *
 * @param string $field Date field
 * @param string $start Start date
 * @param string $end   End date
 * @return string
 */
function agence_report_period_condition($field, $start, $end)
{
	global $db;

	return ' AND '.$field." >= '".$db->escape($start)." 00:00:00' AND ".$field." <= '".$db->escape($end)." 23:59:59'";
}

/** Enforce the current user's agency perimeter on reports and exports. */
function agence_report_scope_condition($field = 'fk_agence')
{
	global $db, $user;
	if (!SofAgenceService::isActiveUser($db, $user)) {
		return ' AND 1 = 0';
	}
	if (!empty($user->admin) || $user->hasRight('agence', 'dashboard', 'direction') || $user->hasRight('agence', 'scope', 'write')) {
		return '';
	}
	$ids = SofAgenceService::allowedAgencyIds($db, $user);
	if ($ids === null) {
		return '';
	}
	return empty($ids) ? ' AND 1 = 0' : ' AND '.$field.' IN ('.implode(',', array_map('intval', $ids)).')';
}

/**
 * Return dashboard KPIs.
 *
 * @param string $start Start date
 * @param string $end   End date
 * @return array<int,array<string,mixed>>
 */
function agence_report_kpis($start, $end)
{
	global $db, $langs, $conf;
	$entity = (int) $conf->entity;

	return array(
		array('label' => $langs->trans('ActiveAgencies'), 'value' => agence_report_scalar('SELECT COUNT(*) as total FROM '.$db->prefix().'sof_agence WHERE entity = '.$entity.' AND status = 1'.agence_report_scope_condition('rowid'), 'total', 0), 'picto' => 'building'),
		array('label' => $langs->trans('ActiveCashDesks'), 'value' => agence_report_scalar('SELECT COUNT(*) as total FROM '.$db->prefix().'sof_caisse WHERE entity = '.$entity.' AND status = 1'.agence_report_scope_condition('fk_agence'), 'total', 0), 'picto' => 'cash-register'),
		array('label' => $langs->trans('OpenSessions'), 'value' => agence_report_scalar('SELECT COUNT(*) as total FROM '.$db->prefix().'sof_caisse_session WHERE entity = '.$entity.' AND status IN (1,2,3,4,5)'.agence_report_scope_condition('fk_agence'), 'total', 0), 'picto' => 'clock'),
		array('label' => $langs->trans('PeriodCollections'), 'value' => price(agence_report_scalar("SELECT COALESCE(SUM(amount),0) as total FROM ".$db->prefix()."sof_caisse_mouvement WHERE entity = ".$entity." AND status=1 AND direction='credit' AND type_operation <> 'opening'".agence_report_scope_condition('fk_agence').agence_report_period_condition('transaction_date', $start, $end), 'total', 0)), 'picto' => 'money-bill-transfer'),
		array('label' => $langs->trans('Refunds'), 'value' => price(agence_report_scalar('SELECT COALESCE(SUM(refunded_amount),0) as total FROM '.$db->prefix().'sof_remboursement WHERE entity = '.$entity.' AND status IN (3,4)'.agence_report_scope_condition('fk_agence').agence_report_period_condition('execution_date', $start, $end), 'total', 0)), 'picto' => 'hand-holding-dollar'),
		array('label' => $langs->trans('DeferredRemaining'), 'value' => price(agence_report_scalar('SELECT COALESCE(SUM(remaining_amount),0) as total FROM '.$db->prefix().'sof_paiement_differe WHERE entity = '.$entity.' AND status NOT IN (4,7,9)'.agence_report_scope_condition('fk_agence'), 'total', 0)), 'picto' => 'file-invoice-dollar'),
		array('label' => $langs->trans('UnreconciledDeposits'), 'value' => agence_report_scalar('SELECT COUNT(*) as total FROM '.$db->prefix().'sof_caisse_depot_banque WHERE entity = '.$entity.' AND status NOT IN (3,9)'.agence_report_scope_condition('fk_agence'), 'total', 0), 'picto' => 'building-columns'),
		array('label' => $langs->trans('OpenCashGaps'), 'value' => price(agence_report_scalar('SELECT COALESCE(SUM(ABS(gap_amount)),0) as total FROM '.$db->prefix().'sof_caisse_ecart WHERE entity = '.$entity.' AND status NOT IN (3,9)'.agence_report_scope_condition('fk_agence'), 'total', 0)), 'picto' => 'triangle-exclamation'),
		array('label' => $langs->trans('OpenAlerts'), 'value' => agence_report_scalar('SELECT COUNT(*) as total FROM '.$db->prefix().'sof_caisse_alerte WHERE entity = '.$entity.' AND status = 0'.agence_report_scope_condition('fk_agence'), 'total', 0), 'picto' => 'bell'),
	);
}

/**
 * Return a named report dataset.
 *
 * @param string $dataset Dataset key
 * @param string $start   Start date
 * @param string $end     End date
 * @return array<int,object>
 */
function agence_report_dataset($dataset, $start, $end)
{
	global $db, $conf, $user;
	$entity = (int) $conf->entity;

	if ($dataset === 'daily_cash') {
		$sql = "SELECT fk_agence, fk_caisse, payment_mode, direction, COUNT(*) as nb_operations, COALESCE(SUM(amount),0) as total_amount";
		$sql .= ' FROM '.$db->prefix().'sof_caisse_mouvement';
		$sql .= ' WHERE entity = '.$entity.' AND status = 1';
		$sql .= agence_report_scope_condition('fk_agence');
		$sql .= agence_report_period_condition('transaction_date', $start, $end);
		$sql .= ' GROUP BY fk_agence, fk_caisse, payment_mode, direction ORDER BY fk_agence, fk_caisse, payment_mode, direction';
		return agence_report_rows($sql);
	}

	if ($dataset === 'refunds') {
		$sql = 'SELECT ref, fk_soc, fk_agence, payment_mode, requested_amount, approved_amount, refunded_amount, status, execution_date';
		$sql .= ' FROM '.$db->prefix().'sof_remboursement WHERE entity = '.$entity;
		$sql .= agence_report_scope_condition('fk_agence');
		$sql .= agence_report_period_condition('request_date', $start, $end);
		$sql .= ' ORDER BY rowid DESC';
		return agence_report_rows($sql);
	}

	if ($dataset === 'deferred') {
		$sql = 'SELECT fk_agence, status, COUNT(*) as nb_records, COALESCE(SUM(expected_amount),0) as expected_amount, COALESCE(SUM(remaining_amount),0) as remaining_amount';
		$sql .= ' FROM '.$db->prefix().'sof_paiement_differe';
		$sql .= ' WHERE entity = '.$entity;
		$sql .= agence_report_scope_condition('fk_agence');
		$sql .= ' GROUP BY fk_agence, status ORDER BY fk_agence, status';
		return agence_report_rows($sql);
	}

	if ($dataset === 'gaps') {
		$sql = 'SELECT ref, fk_agence, fk_caisse, gap_type, severity, gap_amount, status';
		$sql .= ' FROM '.$db->prefix().'sof_caisse_ecart';
		$sql .= ' WHERE entity = '.$entity.' AND status NOT IN (3,9)';
		$sql .= agence_report_scope_condition('fk_agence');
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $db->plimit(100, 0);
		return agence_report_rows($sql);
	}

	if ($dataset === 'deposits') {
		$sql = 'SELECT fk_agence, status, COUNT(*) as nb_records, COALESCE(SUM(amount),0) as total_amount';
		$sql .= ' FROM '.$db->prefix().'sof_caisse_depot_banque';
		$sql .= ' WHERE entity = '.$entity;
		$sql .= agence_report_scope_condition('fk_agence');
		$sql .= ' GROUP BY fk_agence, status ORDER BY fk_agence, status';
		return agence_report_rows($sql);
	}

	if ($dataset === 'transversal') {
		return agence_report_transversal_rows();
	}

	if ($dataset === 'cashier_sessions') {
		$sql = 'SELECT ref, fk_agence, fk_caisse, date_opening, date_closing, theoretical_amount, physical_amount, gap_amount, status';
		$sql .= ' FROM '.$db->prefix().'sof_caisse_session WHERE entity = '.$entity.' AND fk_user_cashier = '.((int) $user->id);
		$sql .= agence_report_period_condition('date_opening', $start, $end).' ORDER BY date_opening DESC';
		return agence_report_rows($sql);
	}

	if ($dataset === 'deferred_cases') {
		$sql = 'SELECT ref, fk_soc, fk_agence, expected_amount, paid_amount, remaining_amount, expected_payment_date, status';
		$sql .= ' FROM '.$db->prefix().'sof_paiement_differe WHERE entity = '.$entity;
		$sql .= agence_report_scope_condition('fk_agence').' ORDER BY expected_payment_date, rowid DESC'.$db->plimit(200, 0);
		return agence_report_rows($sql);
	}

	if ($dataset === 'audit_controls') {
		$sql = 'SELECT ref, fk_agence, fk_caisse, fk_session, fk_user_controller, date_start, date_end, gap_amount, freeze_enabled, status';
		$sql .= ' FROM '.$db->prefix().'sof_caisse_controle WHERE entity = '.$entity;
		$sql .= agence_report_scope_condition('fk_agence').agence_report_period_condition('date_start', $start, $end);
		$sql .= ' ORDER BY date_start DESC'.$db->plimit(200, 0);
		return agence_report_rows($sql);
	}

	if ($dataset === 'accounting_queue') {
		$sql = 'SELECT ref, fk_agence, fk_caisse, date_validation, accounting_status, accounting_attempts, accounting_error, status';
		$sql .= ' FROM '.$db->prefix().'sof_caisse_session WHERE entity = '.$entity.' AND status IN (7,8)';
		$sql .= agence_report_scope_condition('fk_agence').' ORDER BY date_validation DESC'.$db->plimit(200, 0);
		return agence_report_rows($sql);
	}

	return array();
}

/** Return only dashboards that match the current user's operational role and rights. */
function agence_report_available_dashboards()
{
	global $user, $langs;
	$dashboards = array();
	if (!empty($user->admin) || $user->hasRight('agence', 'session', 'open') || $user->hasRight('agence', 'mouvement', 'cashin')) {
		$dashboards['cashier'] = array('label' => $langs->trans('DashboardCashier'), 'dataset' => 'cashier_sessions', 'columns' => array(
			'ref' => $langs->trans('Ref'), 'fk_agence' => $langs->trans('Agency'), 'fk_caisse' => $langs->trans('CashDesk'), 'date_opening' => $langs->trans('OpeningDate'),
			'date_closing' => $langs->trans('ClosingDate'), 'theoretical_amount' => $langs->trans('TheoreticalAmount'), 'physical_amount' => $langs->trans('PhysicalAmount'), 'gap_amount' => $langs->trans('GapAmount'), 'status' => $langs->trans('Status'),
		));
	}
	if (!empty($user->admin) || $user->hasRight('agence', 'report', 'read') || $user->hasRight('agence', 'session', 'validate')) {
		$dashboards['agency'] = array('label' => $langs->trans('DashboardAgencyDaily'), 'dataset' => 'daily_cash', 'columns' => array(
			'fk_agence' => $langs->trans('Agency'), 'fk_caisse' => $langs->trans('CashDesk'), 'payment_mode' => $langs->trans('PaymentMode'), 'direction' => $langs->trans('Direction'), 'nb_operations' => $langs->trans('NumberOfOperations'), 'total_amount' => $langs->trans('Amount'),
		));
	}
	if (!empty($user->admin) || $user->hasRight('agence', 'paiementdiffere', 'create') || $user->hasRight('agence', 'paiementdiffere', 'validate')) {
		$dashboards['deferred'] = array('label' => $langs->trans('DashboardDeferred'), 'dataset' => 'deferred_cases', 'columns' => array(
			'ref' => $langs->trans('Ref'), 'fk_soc' => $langs->trans('ThirdParty'), 'fk_agence' => $langs->trans('Agency'), 'expected_amount' => $langs->trans('ExpectedAmount'), 'paid_amount' => $langs->trans('PaidAmount'),
			'remaining_amount' => $langs->trans('RemainingAmount'), 'expected_payment_date' => $langs->trans('PaymentDueDate'), 'status' => $langs->trans('Status'),
		));
	}
	if (!empty($user->admin) || $user->hasRight('agence', 'dashboard', 'audit') || $user->hasRight('agence', 'controle', 'create') || $user->hasRight('agence', 'audit', 'read')) {
		$dashboards['audit'] = array('label' => $langs->trans('DashboardControlAudit'), 'dataset' => 'audit_controls', 'columns' => array(
			'ref' => $langs->trans('Ref'), 'fk_agence' => $langs->trans('Agency'), 'fk_caisse' => $langs->trans('CashDesk'), 'fk_session' => $langs->trans('CashSession'), 'fk_user_controller' => $langs->trans('Controller'),
			'date_start' => $langs->trans('DateStart'), 'date_end' => $langs->trans('DateEnd'), 'gap_amount' => $langs->trans('GapAmount'), 'freeze_enabled' => $langs->trans('FreezeEnabled'), 'status' => $langs->trans('Status'),
		));
	}
	if (!empty($user->admin) || $user->hasRight('agence', 'compta', 'post')) {
		$dashboards['accounting'] = array('label' => $langs->trans('DashboardAccounting'), 'dataset' => 'accounting_queue', 'columns' => array(
			'ref' => $langs->trans('Ref'), 'fk_agence' => $langs->trans('Agency'), 'fk_caisse' => $langs->trans('CashDesk'), 'date_validation' => $langs->trans('ValidationDate'),
			'accounting_status' => $langs->trans('AccountingStatus'), 'accounting_attempts' => $langs->trans('AccountingAttempts'), 'accounting_error' => $langs->trans('AccountingError'), 'status' => $langs->trans('Status'),
		));
	}
	if (!empty($user->admin) || $user->hasRight('agence', 'dashboard', 'direction') || $user->hasRight('agence', 'scope', 'write')) {
		$dashboards['direction'] = array('label' => $langs->trans('DashboardDirection'), 'dataset' => 'transversal', 'columns' => array(
			'ref' => $langs->trans('Ref'), 'label' => $langs->trans('Agency'), 'town' => $langs->trans('Town'), 'status' => $langs->trans('Status'), 'nb_cashdesks' => $langs->trans('NbCashDesks'),
			'nb_open_sessions' => $langs->trans('OpenSessions'), 'deferred_remaining' => $langs->trans('DeferredRemaining'), 'open_gap_amount' => $langs->trans('OpenCashGaps'),
			'nb_unreconciled_deposits' => $langs->trans('UnreconciledDeposits'), 'nb_open_alerts' => $langs->trans('OpenAlerts'),
		));
	}
	return $dashboards;
}

/**
 * Return multi-agency consolidated rows.
 *
 * @return array<int,object>
 */
function agence_report_transversal_rows()
{
	global $db, $conf;
	$entity = (int) $conf->entity;

	$sql = 'SELECT a.rowid, a.ref, a.label, a.town, a.status,';
	$sql .= ' (SELECT COUNT(*) FROM '.$db->prefix().'sof_caisse c WHERE c.entity = a.entity AND c.fk_agence = a.rowid) as nb_cashdesks,';
	$sql .= ' (SELECT COUNT(*) FROM '.$db->prefix().'sof_caisse_session s WHERE s.entity = a.entity AND s.fk_agence = a.rowid AND s.status IN (1,2,3,4,5)) as nb_open_sessions,';
	$sql .= ' (SELECT COALESCE(SUM(d.remaining_amount),0) FROM '.$db->prefix().'sof_paiement_differe d WHERE d.entity = a.entity AND d.fk_agence = a.rowid AND d.status NOT IN (4,7,9)) as deferred_remaining,';
	$sql .= ' (SELECT COALESCE(SUM(ABS(e.gap_amount)),0) FROM '.$db->prefix().'sof_caisse_ecart e WHERE e.entity = a.entity AND e.fk_agence = a.rowid AND e.status NOT IN (3,9)) as open_gap_amount,';
	$sql .= ' (SELECT COUNT(*) FROM '.$db->prefix().'sof_caisse_depot_banque b WHERE b.entity = a.entity AND b.fk_agence = a.rowid AND b.status NOT IN (3,9)) as nb_unreconciled_deposits,';
	$sql .= ' (SELECT COUNT(*) FROM '.$db->prefix().'sof_caisse_alerte al WHERE al.entity = a.entity AND al.fk_agence = a.rowid AND al.status = 0) as nb_open_alerts';
	$sql .= ' FROM '.$db->prefix().'sof_agence a';
	$sql .= ' WHERE a.entity = '.$entity;
	$sql .= agence_report_scope_condition('a.rowid');
	$sql .= ' ORDER BY a.ref ASC';
	return agence_report_rows($sql);
}

/**
 * Print KPI cards.
 *
 * @param array<int,array<string,mixed>> $kpis KPIs
 * @return void
 */
function agence_report_print_kpis($kpis)
{
	print '<div class="fichecenter">';
	foreach ($kpis as $kpi) {
		print '<div class="fichethirdleft">';
		print '<div class="info-box">';
		print '<span class="info-box-icon bg-infobox-action">'.img_picto('', $kpi['picto']).'</span>';
		print '<div class="info-box-content"><span class="info-box-text">'.dol_escape_htmltag($kpi['label']).'</span><span class="info-box-number">'.dol_escape_htmltag((string) $kpi['value']).'</span></div>';
		print '</div>';
		print '</div>';
	}
	print '</div><div class="clearboth"></div>';
}

/**
 * Print a report table from objects.
 *
 * @param string            $title   Title
 * @param array<int,object> $rows    Rows
 * @param array<string,string> $columns Columns
 * @param string            $dataset Dataset key
 * @param string            $start   Start date
 * @param string            $end     End date
 * @return void
 */
function agence_report_print_table($title, $rows, $columns, $dataset, $start, $end)
{
	global $langs, $user;

	print '<br>';
	print load_fiche_titre($title, '', 'chart-bar');
	if ($user->hasRight('agence', 'report', 'export')) {
		$url = $_SERVER['PHP_SELF'].'?action=export&dataset='.urlencode($dataset).'&date_start='.urlencode($start).'&date_end='.urlencode($end);
		print '<div class="tabsAction"><a class="butAction" href="'.dol_escape_htmltag($url).'">'.$langs->trans('ExportCSV').'</a></div>';
	}
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	foreach ($columns as $label) {
		print '<th>'.dol_escape_htmltag($label).'</th>';
	}
	print '</tr>';
	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="'.count($columns).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	foreach ($rows as $row) {
		print '<tr class="oddeven">';
		foreach ($columns as $field => $label) {
			$value = isset($row->$field) ? $row->$field : '';
			print '<td>'.agence_report_format_value($field, $value, $dataset, true).'</td>';
		}
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

/** Format a report value for screen or CSV without exposing stored codes. */
function agence_report_format_value($field, $value, $dataset, $html = false)
{
	global $db, $langs;
	$contexts = array('cashier_sessions'=>'session', 'deferred_cases'=>'paiementdiffere', 'audit_controls'=>'controle', 'accounting_queue'=>'session', 'transversal'=>'agence');
	if (strpos($field, 'fk_') === 0) {
		$formatted = agence_format_foreign_value($field, (int) $value);
		return $html ? $formatted : trim(html_entity_decode(strip_tags($formatted), ENT_QUOTES, 'UTF-8'));
	}
	if ($field === 'status') {
		$formatted = agence_translate_business_code('status', $value, isset($contexts[$dataset]) ? $contexts[$dataset] : '');
	} elseif (in_array($field, array('accounting_status', 'reconcile_status', 'billing_status', 'validation_status', 'use_status', 'freeze_status'), true)) {
		$formatted = agence_translate_business_code($field, $value);
	} elseif ($field === 'payment_mode') {
		$formatted = agence_translate_business_code('payment_mode', $value);
	} elseif ($field === 'direction') {
		$formatted = agence_translate_business_code('direction', $value);
	} elseif ($field === 'freeze_enabled') {
		$formatted = $langs->trans((int) $value ? 'Yes' : 'No');
	} elseif (preg_match('/(^date_|_date$)/', $field) && $value !== '') {
		$timestamp = is_numeric($value) ? (int) $value : $db->jdate($value);
		$formatted = $timestamp ? dol_print_date($timestamp, 'dayhour') : (string) $value;
	} elseif (preg_match('/amount|total|remaining/i', $field)) {
		$formatted = price($value);
	} else {
		$formatted = (string) $value;
	}
	return $html ? dol_escape_htmltag($formatted) : $formatted;
}

/**
 * Export a dataset as CSV.
 *
 * @param string $dataset Dataset key
 * @param string $start   Start date
 * @param string $end     End date
 * @return void
 */
function agence_report_export_csv($dataset, $start, $end)
{
	$rows = agence_report_dataset($dataset, $start, $end);
	$filename = 'agence_'.$dataset.'_'.$start.'_'.$end.'.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Cache-Control: private, no-store, max-age=0');
	header('X-Content-Type-Options: nosniff');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	$headers = array();
	$columnLabels = array();
	foreach (agence_report_available_dashboards() as $dashboard) {
		if ($dashboard['dataset'] === $dataset) {
			$headers = array_keys($dashboard['columns']);
			$columnLabels = array_values($dashboard['columns']);
			break;
		}
	}
	if (empty($headers) && !empty($rows)) {
		$headers = array_keys(get_object_vars($rows[0]));
		$columnLabels = array_map(function ($field) { return ucfirst(str_replace('_', ' ', $field)); }, $headers);
	}
	if (!empty($headers)) {
		fputcsv($out, $columnLabels, ';');
		foreach ($rows as $row) {
			$line = array();
			foreach ($headers as $header) {
				$value = agence_report_format_value($header, isset($row->$header) ? $row->$header : '', $dataset, false);
				$line[] = is_string($value) && preg_match('/^[=+\-@]/', ltrim($value)) ? "'".$value : $value;
			}
			fputcsv($out, $line, ';');
		}
	}
	fclose($out);
	exit;
}
