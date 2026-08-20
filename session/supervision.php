<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';

$langs->loadLangs(array('agence@agence', 'users'));
if (!$user->hasRight('agence', 'dashboard', 'direction') && !$user->hasRight('agence', 'session', 'validate') && !$user->hasRight('agence', 'audit', 'read')) {
	accessforbidden();
}

$autorefresh = GETPOST('autorefresh', 'int');
$fkAgence = GETPOST('fk_agence', 'int');
$statusFilter = GETPOST('status', 'int');
$scopeIds = SofAgenceService::allowedAgencyIds($db, $user);
if ($scopeIds !== null && $fkAgence > 0 && !in_array($fkAgence, $scopeIds, true)) {
	accessforbidden('Agency outside user scope');
}
$scopeCondition = '';
if ($scopeIds !== null) {
	$scopeCondition = empty($scopeIds) ? ' AND 1=0' : ' AND fk_agence IN ('.implode(',', array_map('intval', $scopeIds)).')';
}
if ($fkAgence > 0) {
	$scopeCondition .= ' AND fk_agence = '.((int) $fkAgence);
}

function agence_supervision_scalar($db, $sql, $field)
{
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : null;
	return $row && isset($row->$field) ? $row->$field : 0;
}

$today = dol_print_date(dol_now(), '%Y-%m-%d');
$openSessions = agence_supervision_scalar($db, 'SELECT COUNT(*) nb FROM '.$db->prefix().'sof_caisse_session WHERE entity='.((int) $conf->entity).' AND status IN (1,2,3,4,5)'.$scopeCondition, 'nb');
$todayCollections = agence_supervision_scalar($db, "SELECT COALESCE(SUM(amount),0) total FROM ".$db->prefix()."sof_caisse_mouvement WHERE entity=".((int) $conf->entity)." AND status=1 AND direction='credit' AND type_operation <> 'opening' AND transaction_date >= '".$db->escape($today)." 00:00:00'".$scopeCondition, 'total');
$criticalGaps = agence_supervision_scalar($db, "SELECT COUNT(*) nb FROM ".$db->prefix()."sof_caisse_ecart WHERE entity=".((int) $conf->entity)." AND severity='critical' AND status NOT IN (3,9)".$scopeCondition, 'nb');
$pendingClosings = agence_supervision_scalar($db, 'SELECT COUNT(*) nb FROM '.$db->prefix().'sof_caisse_session WHERE entity='.((int) $conf->entity).' AND status=6'.$scopeCondition, 'nb');

$sql = 'SELECT s.*, c.ref caisse_ref, c.label caisse_label, a.ref agence_ref, a.label agence_label,';
$sql .= ' u.login, u.firstname, u.lastname FROM '.$db->prefix().'sof_caisse_session s';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_caisse c ON c.rowid=s.fk_caisse LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid=s.fk_agence';
$sql .= ' LEFT JOIN '.$db->prefix().'user u ON u.rowid=s.fk_user_cashier WHERE s.entity='.((int) $conf->entity);
$sessionScopeCondition = str_replace('fk_agence', 's.fk_agence', $scopeCondition);
$sql .= $sessionScopeCondition;
if ($statusFilter > 0) {
	$sql .= ' AND s.status = '.((int) $statusFilter);
}
$sql .= ' ORDER BY s.date_opening DESC'.$db->plimit(300, 0);
$resql = $db->query($sql);

llxHeader('', $langs->trans('CashSupervision'));
if ($autorefresh) {
	print '<meta http-equiv="refresh" content="60">';
}
print load_fiche_titre($langs->trans('CashSupervision'), '', 'chart-line');
print '<div class="fichecenter">';
foreach (array(
	array('OpenSessions', $openSessions, 'clock'),
	array('TodayCollections', price($todayCollections), 'coins'),
	array('CriticalCashGaps', $criticalGaps, 'triangle-exclamation'),
	array('ClosingsPendingValidation', $pendingClosings, 'hourglass-half'),
) as $kpi) {
	print '<div class="fichequarterleft"><div class="info-box"><span class="info-box-icon bg-infobox-action">'.img_picto('', $kpi[2]).'</span><div class="info-box-content"><span class="info-box-text">'.$langs->trans($kpi[0]).'</span><span class="info-box-number">'.dol_escape_htmltag((string) $kpi[1]).'</span></div></div></div>';
}
print '</div><div class="clearboth"></div>';

print '<form method="GET" class="marginbottomonly"><label>'.$langs->trans('Agency').' <select class="flat" name="fk_agence"><option value="">'.$langs->trans('All').'</option>';
$agencySql = 'SELECT rowid, ref, label FROM '.$db->prefix().'sof_agence WHERE entity='.((int) $conf->entity);
if ($scopeIds !== null) {
	$agencySql .= empty($scopeIds) ? ' AND 1=0' : ' AND rowid IN ('.implode(',', array_map('intval', $scopeIds)).')';
}
$agencySql .= ' ORDER BY label, ref';
$agencyResult = $db->query($agencySql);
while ($agencyResult && ($agency = $db->fetch_object($agencyResult))) {
	print '<option value="'.$agency->rowid.'"'.($fkAgence === (int) $agency->rowid ? ' selected' : '').'>'.dol_escape_htmltag($agency->label.' ('.$agency->ref.')').'</option>';
}
print '</select></label> <label>'.$langs->trans('Status').' <select class="flat" name="status"><option value="">'.$langs->trans('All').'</option>';
foreach (array(1 => 'Opened', 2 => 'Operating', 3 => 'Paused', 4 => 'ControlInProgress', 5 => 'ClosingInProgress', 6 => 'Closed', 7 => 'Validated', 8 => 'Accounted', 9 => 'Canceled', 10 => 'Blocked') as $code => $label) {
	print '<option value="'.$code.'"'.($statusFilter === $code ? ' selected' : '').'>'.$langs->trans($label).'</option>';
}
print '</select></label> <label><input type="checkbox" name="autorefresh" value="1"'.($autorefresh ? ' checked' : '').'> '.$langs->trans('RefreshEveryMinute').'</label> <button class="button" type="submit">'.$langs->trans('Filter').'</button></form>';

print '<div class="div-table-responsive"><table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Agency').'</th><th>'.$langs->trans('CashDesk').'</th><th>'.$langs->trans('Cashier').'</th><th>'.$langs->trans('OpeningDate').'</th><th class="right">'.$langs->trans('TheoreticalAmount').'</th><th class="right">'.$langs->trans('PhysicalAmount').'</th><th class="right">'.$langs->trans('GapAmount').'</th><th>'.$langs->trans('Status').'</th></tr>';
$count = 0;
while ($resql && ($row = $db->fetch_object($resql))) {
	$count++;
	print '<tr class="oddeven"><td><a href="'.dol_buildpath('/agence/session/card.php', 1).'?object=session&id='.$row->rowid.'">'.dol_escape_htmltag($row->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag($row->agence_label.' ('.$row->agence_ref.')').'</td><td>'.dol_escape_htmltag($row->caisse_label.' ('.$row->caisse_ref.')').'</td>';
	print '<td>'.dol_escape_htmltag(trim($row->firstname.' '.$row->lastname) ?: $row->login).'</td><td>'.dol_print_date($db->jdate($row->date_opening), 'dayhour').'</td>';
	print '<td class="right">'.price($row->theoretical_amount).'</td><td class="right">'.price($row->physical_amount).'</td><td class="right">'.price($row->gap_amount).'</td><td>'.dol_escape_htmltag(agence_translate_business_code('status', $row->status, 'session')).'</td></tr>';
}
if ($count === 0) {
	print '<tr><td colspan="9" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
print '</table></div>';
llxFooter();
$db->close();
