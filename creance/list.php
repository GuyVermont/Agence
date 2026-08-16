<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence', 'bills', 'companies'));
if (!$user->hasRight('agence', 'paiementdiffere', 'create')
	&& !$user->hasRight('agence', 'paiementdiffere', 'validate')
	&& !$user->hasRight('agence', 'report', 'read')) {
	accessforbidden();
}

$search = trim((string) GETPOST('search', 'restricthtml'));
$onlyOverdue = GETPOST('overdue', 'int');
$fkAgence = GETPOST('fk_agence', 'int');
$allowedAgencies = SofAgenceService::allowedAgencyIds($db, $user);
if ($allowedAgencies !== null && $fkAgence > 0 && !in_array($fkAgence, $allowedAgencies, true)) {
	accessforbidden('Agency outside user scope');
}

$scopeSql = '';
if ($allowedAgencies !== null) {
	$scopeSql = empty($allowedAgencies) ? ' AND 1 = 0' : ' AND COALESCE(fl.fk_agence,tl.fk_agence) IN ('.implode(',', array_map('intval', $allowedAgencies)).')';
}
if ($fkAgence > 0) {
	$scopeSql .= ' AND COALESCE(fl.fk_agence,tl.fk_agence) = '.((int) $fkAgence);
}

$sql = 'SELECT f.rowid, f.ref, f.datef, f.date_lim_reglement, f.total_ttc, f.fk_soc, s.nom,';
$sql .= ' COALESCE(fl.fk_agence,tl.fk_agence) fk_agence, a.ref agence_ref, a.label agence_label,';
$sql .= ' COALESCE((SELECT SUM(pf.amount) FROM '.$db->prefix().'paiement_facture pf WHERE pf.fk_facture = f.rowid),0) paid';
$sql .= ' FROM '.$db->prefix().'facture f';
$sql .= ' JOIN '.$db->prefix().'societe s ON s.rowid = f.fk_soc';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_facture_link fl ON fl.fk_facture = f.rowid AND fl.entity = '.((int) $conf->entity);
$sql .= ' LEFT JOIN '.$db->prefix().'sof_takepos_link tl ON tl.fk_facture = f.rowid AND tl.entity = '.((int) $conf->entity);
$sql .= ' LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid = COALESCE(fl.fk_agence,tl.fk_agence)';
$sql .= ' WHERE f.entity = '.((int) $conf->entity).' AND f.fk_statut = 1 AND f.paye = 0 AND f.type IN (0,3)';
$sql .= $scopeSql;
if ($search !== '') {
	$escaped = $db->escape(function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search));
	$sql .= " AND (LOWER(f.ref) LIKE '%".$escaped."%' OR LOWER(s.nom) LIKE '%".$escaped."%')";
}
if ($onlyOverdue) {
	$sql .= ' AND f.date_lim_reglement IS NOT NULL AND f.date_lim_reglement < CURRENT_DATE';
}
$sql .= ' ORDER BY CASE WHEN f.date_lim_reglement IS NULL THEN 1 ELSE 0 END, f.date_lim_reglement ASC, f.rowid DESC'.$db->plimit(1000, 0);
$resql = $db->query($sql);

$rows = array();
$totalDue = 0.0;
$totalOverdue = 0.0;
while ($resql && ($row = $db->fetch_object($resql))) {
	$row->remaining = max(0, price2num((float) $row->total_ttc - (float) $row->paid));
	if ($row->remaining <= 0.009) {
		continue;
	}
	$row->is_overdue = !empty($row->date_lim_reglement) && $db->jdate($row->date_lim_reglement) < dol_get_first_hour(dol_now());
	$totalDue += $row->remaining;
	if ($row->is_overdue) {
		$totalOverdue += $row->remaining;
	}
	$rows[] = $row;
}

llxHeader('', $langs->trans('Receivables'), '', '', 0, 0, '', '', '', 'mod-agence page-creance-list');
print load_fiche_titre($langs->trans('Receivables'), '', 'file-invoice-dollar');

print '<div class="fichecenter"><div class="fichehalfleft"><div class="info-box"><strong>'.count($rows).'</strong> '.$langs->trans('OpenInvoices').'<br><strong>'.price($totalDue).'</strong> '.$conf->currency.'</div></div>';
print '<div class="fichehalfright"><div class="warning"><strong>'.price($totalOverdue).'</strong> '.$conf->currency.' '.$langs->trans('OverdueAmount').'</div></div></div><div class="clearboth"></div>';

print '<form method="GET" class="marginbottomonly"><input class="flat minwidth200" name="search" value="'.dol_escape_htmltag($search).'" placeholder="'.$langs->trans('Ref').' / '.$langs->trans('ThirdParty').'"> ';
print '<label><input type="checkbox" name="overdue" value="1"'.($onlyOverdue ? ' checked' : '').'> '.$langs->trans('OnlyOverdue').'</label> ';
print '<input class="button" type="submit" value="'.$langs->trans('Search').'">';
print '</form>';

print '<div class="div-table-responsive"><table class="liste centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Invoice').'</th><th>'.$langs->trans('ThirdParty').'</th><th>'.$langs->trans('Agency').'</th><th>'.$langs->trans('DateDue').'</th><th class="right">'.$langs->trans('Amount').'</th><th class="right">'.$langs->trans('AlreadyPaid').'</th><th class="right">'.$langs->trans('RemainingAmount').'</th><th>'.$langs->trans('Status').'</th><th></th></tr>';
foreach ($rows as $row) {
	$invoiceUrl = DOL_URL_ROOT.'/compta/facture/card.php?facid='.((int) $row->rowid);
	$captureUrl = dol_buildpath('/agence/mouvement/encaisser.php', 1).'?fk_facture='.((int) $row->rowid);
	print '<tr class="oddeven">';
	print '<td><a href="'.$invoiceUrl.'">'.dol_escape_htmltag($row->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag($row->nom).'</td>';
	print '<td>'.dol_escape_htmltag($row->agence_label ?: $row->agence_ref ?: '-').'</td>';
	print '<td>'.(!empty($row->date_lim_reglement) ? dol_print_date($db->jdate($row->date_lim_reglement), 'day') : '-').'</td>';
	print '<td class="right">'.price($row->total_ttc).'</td><td class="right">'.price($row->paid).'</td><td class="right"><strong>'.price($row->remaining).'</strong></td>';
	print '<td>'.($row->is_overdue ? '<span class="badge badge-status8">'.$langs->trans('Late').'</span>' : '<span class="badge badge-status4">'.$langs->trans('Pending').'</span>').'</td>';
	print '<td class="right"><a class="butAction smallpaddingimp" href="'.$captureUrl.'">'.$langs->trans('Collect').'</a></td>';
	print '</tr>';
}
if (empty($rows)) {
	print '<tr><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';

print '<p class="opacitymedium">'.$langs->trans('ReceivablesHelp').'</p>';
llxFooter();
$db->close();
