<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/softakeposlink.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence', 'admin', 'cashdesk'));
if (!$user->hasRight('agence', 'caisse', 'write') && !$user->hasRight('agence', 'parametre', 'write')) {
	accessforbidden();
}

$allowedAgencies = SofAgenceService::allowedAgencyIds($db, $user);
$action = GETPOST('action', 'alpha');
$engine = new SofAgenceOperations($db);

if (in_array($action, array('save', 'enable', 'disable'), true)) {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$id = $action === 'save' ? 0 : GETPOST('id', 'int');
	if ($action === 'save') {
		$terminal = trim((string) GETPOST('terminal_ref', 'alphanohtml'));
		$fkAgence = GETPOST('fk_agence', 'int');
		$fkCaisse = GETPOST('fk_caisse', 'int');
		$fkDas = GETPOST('fk_das', 'int');
		if ($terminal === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/', $terminal) || $fkAgence <= 0 || $fkCaisse <= 0) {
			setEventMessages($langs->trans('TerminalMappingInvalid'), null, 'errors');
		} elseif ($allowedAgencies !== null && !in_array($fkAgence, $allowedAgencies, true)) {
			accessforbidden('Agency outside user scope');
		} else {
			$contextError = SofAgenceService::validateAgencyCashDeskDas($db, $fkAgence, $fkCaisse, $fkDas, true);
			if ($contextError !== '') {
				setEventMessages($contextError, null, 'errors');
			} else {
				$db->begin();
				$operationError = '';
				$sql = 'UPDATE '.$db->prefix().'sof_takepos_link SET status = 0, fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP';
				$sql .= ' WHERE entity = '.((int) $conf->entity)." AND terminal_ref = '".$db->escape($terminal)."' AND fk_facture IS NULL";
				if ($id > 0) {
					$sql .= ' AND rowid <> '.((int) $id);
				}
				$disabled = $db->query($sql);
				if ($id > 0) {
					$result = $engine->updateRow('sof_takepos_link', $id, array(
						'terminal_ref' => $terminal, 'fk_agence' => $fkAgence, 'fk_caisse' => $fkCaisse,
						'fk_das' => $fkDas ?: null, 'fk_session' => null, 'fk_facture' => null,
						'place_ref' => '', 'ticket_ref' => '', 'pos_source' => 'takepos_mapping',
						'billing_status' => 0, 'reconcile_status' => 0, 'status' => 1,
					), $user);
				} else {
					$mapping = new SofTakeposLink($db);
					$mapping->entity = (int) $conf->entity;
					$mapping->terminal_ref = $terminal;
					$mapping->fk_agence = $fkAgence;
					$mapping->fk_caisse = $fkCaisse;
					$mapping->fk_das = $fkDas ?: null;
					$mapping->pos_source = 'takepos_mapping';
					$mapping->billing_status = 0;
					$mapping->reconcile_status = 0;
					$mapping->status = 1;
					$result = $mapping->create($user);
					$operationError = $mapping->error;
				}
				if ($disabled && $result > 0) {
					$db->commit();
					setEventMessages($langs->trans('TerminalMappingSaved'), null, 'mesgs');
					header('Location: '.$_SERVER['PHP_SELF']);
					exit;
				}
				$db->rollback();
				setEventMessages($operationError ?: ($engine->error ?: $langs->trans('Error')), $engine->errors, 'errors');
			}
		}
	} elseif ($id > 0) {
		$sql = 'SELECT fk_agence FROM '.$db->prefix().'sof_takepos_link WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $id).' AND fk_facture IS NULL';
		$resql = $db->query($sql);
		$row = $resql ? $db->fetch_object($resql) : null;
		if (!$row || ($allowedAgencies !== null && !in_array((int) $row->fk_agence, $allowedAgencies, true))) {
			accessforbidden('Mapping outside user scope');
		}
		$result = $engine->updateRow('sof_takepos_link', $id, array('status' => $action === 'enable' ? 1 : 0), $user);
		setEventMessages($result > 0 ? $langs->trans('RecordModifiedSuccessfully') : ($engine->error ?: $langs->trans('Error')), $result > 0 ? null : $engine->errors, $result > 0 ? 'mesgs' : 'errors');
	}
}

$agencyWhere = ' WHERE entity = '.((int) $conf->entity).' AND status = 1';
if ($allowedAgencies !== null) {
	$agencyWhere .= empty($allowedAgencies) ? ' AND 1 = 0' : ' AND rowid IN ('.implode(',', array_map('intval', $allowedAgencies)).')';
}
$agencies = array();
$resql = $db->query('SELECT rowid, ref, label FROM '.$db->prefix().'sof_agence'.$agencyWhere.' ORDER BY label, ref');
while ($resql && ($row = $db->fetch_object($resql))) {
	$agencies[(int) $row->rowid] = $row;
}
$cashDesks = array();
$sql = 'SELECT c.rowid, c.ref, c.label, c.fk_agence FROM '.$db->prefix().'sof_caisse c WHERE c.entity = '.((int) $conf->entity).' AND c.status = 1';
if ($allowedAgencies !== null) {
	$sql .= empty($allowedAgencies) ? ' AND 1 = 0' : ' AND c.fk_agence IN ('.implode(',', array_map('intval', $allowedAgencies)).')';
}
$sql .= ' ORDER BY c.label, c.ref';
$resql = $db->query($sql);
while ($resql && ($row = $db->fetch_object($resql))) {
	$cashDesks[(int) $row->rowid] = $row;
}
$dasList = array();
$resql = $db->query('SELECT rowid, code, label FROM '.$db->prefix().'sof_das WHERE entity = '.((int) $conf->entity).' AND status = 1 ORDER BY label, code');
while ($resql && ($row = $db->fetch_object($resql))) {
	$dasList[(int) $row->rowid] = $row;
}

llxHeader('', $langs->trans('TerminalMappings'));
print load_fiche_titre($langs->trans('TerminalMappings'), '', 'cash-register');
print '<div class="info">'.$langs->trans('TerminalMappingsHelp').'</div>';
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Terminal').'</td><td><input class="flat minwidth200" required name="terminal_ref" placeholder="1"></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('Agency').'</td><td><select class="flat minwidth300" required name="fk_agence"><option value="">--</option>';
foreach ($agencies as $agency) {
	print '<option value="'.$agency->rowid.'">'.dol_escape_htmltag($agency->label.' ('.$agency->ref.')').'</option>';
}
print '</select></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('CashDesk').'</td><td><select class="flat minwidth300" required name="fk_caisse"><option value="">--</option>';
foreach ($cashDesks as $cashDesk) {
	print '<option value="'.$cashDesk->rowid.'">'.dol_escape_htmltag($cashDesk->label.' ('.$cashDesk->ref.')').'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('DAS').'</td><td><select class="flat minwidth300" name="fk_das"><option value="">--</option>';
foreach ($dasList as $das) {
	print '<option value="'.$das->rowid.'">'.dol_escape_htmltag($das->label.' ('.$das->code.')').'</option>';
}
print '</select></td></tr></table><div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button></div></form>';

$sql = 'SELECT t.*, a.label agence_label, c.label caisse_label, d.label das_label FROM '.$db->prefix().'sof_takepos_link t';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid = t.fk_agence LEFT JOIN '.$db->prefix().'sof_caisse c ON c.rowid = t.fk_caisse';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_das d ON d.rowid = t.fk_das WHERE t.entity = '.((int) $conf->entity).' AND t.fk_facture IS NULL';
if ($allowedAgencies !== null) {
	$sql .= empty($allowedAgencies) ? ' AND 1 = 0' : ' AND t.fk_agence IN ('.implode(',', array_map('intval', $allowedAgencies)).')';
}
$sql .= ' ORDER BY t.terminal_ref, t.rowid DESC';
$resql = $db->query($sql);
print '<div class="div-table-responsive"><table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Terminal').'</th><th>'.$langs->trans('Agency').'</th><th>'.$langs->trans('CashDesk').'</th><th>'.$langs->trans('DAS').'</th><th>'.$langs->trans('Status').'</th><th></th></tr>';
while ($resql && ($row = $db->fetch_object($resql))) {
	$toggle = (int) $row->status === 1 ? 'disable' : 'enable';
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->terminal_ref).'</td><td>'.dol_escape_htmltag($row->agence_label).'</td><td>'.dol_escape_htmltag($row->caisse_label).'</td><td>'.dol_escape_htmltag($row->das_label ?: '-').'</td>';
	print '<td>'.((int) $row->status === 1 ? '<span class="badge badge-status4">'.$langs->trans('Active').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Disabled').'</span>').'</td>';
	print '<td class="right"><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.$toggle.'"><input type="hidden" name="id" value="'.$row->rowid.'"><button class="button smallpaddingimp" type="submit">'.$langs->trans(ucfirst($toggle)).'</button></form></td></tr>';
}
print '</table></div>';
llxFooter();
$db->close();
