<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence', 'accountancy'));
if (!$user->hasRight('agence', 'compta', 'post')) {
	accessforbidden();
}
$engine = new SofAgenceOperations($db);
$scopeIds = SofAgenceService::allowedAgencyIds($db, $user);

if (GETPOST('action', 'alpha') === 'post') {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$sessionId = GETPOST('id', 'int');
	$sql = 'SELECT fk_agence FROM '.$db->prefix().'sof_caisse_session WHERE entity='.((int) $conf->entity).' AND rowid='.((int) $sessionId);
	$resql = $db->query($sql);
	$sessionRow = $resql ? $db->fetch_object($resql) : null;
	if (!$sessionRow || ($scopeIds !== null && !in_array((int) $sessionRow->fk_agence, $scopeIds, true))) {
		accessforbidden('Session outside user scope');
	}
	$result = $engine->transitionSession($user, $sessionId, 'account');
	setEventMessages($result > 0 ? $langs->trans('AccountingPostingCompleted') : ($engine->error ?: $langs->trans('Error')), $result > 0 ? null : $engine->errors, $result > 0 ? 'mesgs' : 'errors');
	if ($result > 0) {
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

$sql = 'SELECT s.rowid, s.ref, s.date_closing, s.gap_amount, s.accounting_status, a.ref agence_ref, a.label agence_label, c.ref caisse_ref, c.label caisse_label';
$sql .= ' FROM '.$db->prefix().'sof_caisse_session s LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid=s.fk_agence LEFT JOIN '.$db->prefix().'sof_caisse c ON c.rowid=s.fk_caisse';
$sql .= ' WHERE s.entity='.((int) $conf->entity).' AND s.status=7 AND s.accounting_status < 4';
if ($scopeIds !== null) {
	$sql .= empty($scopeIds) ? ' AND 1=0' : ' AND s.fk_agence IN ('.implode(',', array_map('intval', $scopeIds)).')';
}
$sql .= ' ORDER BY s.date_closing, s.rowid'.$db->plimit(500, 0);
$resql = $db->query($sql);

llxHeader('', $langs->trans('AgencyAccountingPosting'));
print load_fiche_titre($langs->trans('AgencyAccountingPosting'), '', 'scale-balanced');
if (!isModEnabled('accounting')) {
	print '<div class="warning">'.$langs->trans('AccountingModuleRequired').'</div>';
}
print '<div class="info">'.$langs->trans('AgencyAccountingPostingHelp').'</div>';
print '<div class="div-table-responsive"><table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Session').'</th><th>'.$langs->trans('Agency').'</th><th>'.$langs->trans('CashDesk').'</th><th>'.$langs->trans('ClosingDate').'</th><th class="right">'.$langs->trans('GapAmount').'</th><th></th></tr>';
$count = 0;
while ($resql && ($row = $db->fetch_object($resql))) {
	$count++;
	print '<tr class="oddeven"><td><a href="'.dol_buildpath('/agence/session/card.php', 1).'?object=session&id='.$row->rowid.'">'.dol_escape_htmltag($row->ref).'</a></td><td>'.dol_escape_htmltag($row->agence_label.' ('.$row->agence_ref.')').'</td><td>'.dol_escape_htmltag($row->caisse_label.' ('.$row->caisse_ref.')').'</td><td>'.dol_print_date($db->jdate($row->date_closing), 'dayhour').'</td><td class="right">'.price($row->gap_amount).'</td>';
	print '<td class="right"><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="post"><input type="hidden" name="id" value="'.$row->rowid.'"><button class="button" type="submit">'.$langs->trans('PostToAccounting').'</button></form></td></tr>';
}
if ($count === 0) {
	print '<tr><td colspan="6" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
print '</table></div>';
llxFooter();
$db->close();
