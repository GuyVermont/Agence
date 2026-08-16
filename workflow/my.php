<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence'));
$canValidate = !empty($user->admin)
	|| $user->hasRight('agence', 'session', 'validate')
	|| $user->hasRight('agence', 'remboursement', 'validate')
	|| $user->hasRight('agence', 'boncommande', 'validate')
	|| $user->hasRight('agence', 'bst', 'validate')
	|| $user->hasRight('agence', 'instruction', 'validate');
if (!$canValidate) {
	accessforbidden();
}

$engine = new SofAgenceOperations($db);
if (GETPOST('action', 'alpha') === 'decide') {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$result = $engine->decideValidationStep(
		$user,
		GETPOST('step_id', 'int'),
		GETPOST('decision', 'alpha'),
		GETPOST('reason', 'restricthtml')
	);
	setEventMessages($result > 0 ? $langs->trans('ValidationDecisionSaved') : ($engine->error ?: $langs->trans('Error')), $result > 0 ? null : $engine->errors, $result > 0 ? 'mesgs' : 'errors');
	if ($result > 0) {
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

$sql = 'SELECT v.* FROM '.$db->prefix().'sof_caisse_validation v WHERE v.entity = '.((int) $conf->entity).' AND v.status = 0';
$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.$db->prefix().'sof_caisse_validation p WHERE p.entity=v.entity';
$sql .= ' AND p.object_type=v.object_type AND p.object_id=v.object_id AND p.status=0 AND p.validation_level < v.validation_level)';
$sql .= SofAgenceService::validationScopeSql($db, 'v', SofAgenceService::allowedAgencyIds($db, $user));
if (empty($user->admin)) {
	$sql .= ' AND (v.fk_user_validator = '.((int) $user->id).' OR (v.fk_user_validator IS NULL AND (';
	$sql .= "v.role_required IS NULL OR v.role_required = ''";
	$sql .= ' OR EXISTS (SELECT 1 FROM '.$db->prefix().'sof_agence_user au WHERE au.entity=v.entity AND au.fk_user='.((int) $user->id).' AND au.role_code=v.role_required AND au.status=1 AND (au.date_start IS NULL OR au.date_start <= CURRENT_TIMESTAMP) AND (au.date_end IS NULL OR au.date_end >= CURRENT_TIMESTAMP))';
	$sql .= ' OR EXISTS (SELECT 1 FROM '.$db->prefix().'sof_role_transversal rt WHERE rt.entity=v.entity AND rt.fk_user='.((int) $user->id).' AND rt.role_code=v.role_required AND rt.status=1 AND (rt.date_start IS NULL OR rt.date_start <= CURRENT_TIMESTAMP) AND (rt.date_end IS NULL OR rt.date_end >= CURRENT_TIMESTAMP))';
	$sql .= ')))';
}
$sql .= ' ORDER BY v.date_request, v.validation_level, v.rowid'.$db->plimit(500, 0);
$resql = $db->query($sql);

function agence_validation_object_url($type, $id)
{
	$map = array(
		'session' => '/agence/session/card.php?object=session&id=',
		'refund' => '/agence/remboursement/card.php?object=remboursement&id=',
		'customer_po' => '/agence/differe/card.php?object=boncommande&id=',
		'bst' => '/agence/differe/card.php?object=bst&id=',
		'manager_instruction' => '/agence/differe/card.php?object=instruction&id=',
	);
	return isset($map[$type]) ? dol_buildpath(strtok($map[$type], '?'), 1).'?'.substr(strstr($map[$type], '?'), 1).((int) $id) : '';
}

llxHeader('', $langs->trans('MyPendingValidations'));
print load_fiche_titre($langs->trans('MyPendingValidations'), '', 'check-double');
print '<div class="div-table-responsive"><table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('RequestDate').'</th><th>'.$langs->trans('Object').'</th><th>'.$langs->trans('Workflow').'</th><th>'.$langs->trans('ValidationLevel').'</th><th>'.$langs->trans('Role').'</th><th>'.$langs->trans('Decision').'</th></tr>';
$count = 0;
while ($resql && ($row = $db->fetch_object($resql))) {
	$count++;
	$url = agence_validation_object_url($row->object_type, (int) $row->object_id);
	print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($row->date_request), 'dayhour').'</td>';
	print '<td>'.($url ? '<a href="'.$url.'">' : '').dol_escape_htmltag($row->object_type).' #'.((int) $row->object_id).($url ? '</a>' : '').'</td>';
	print '<td>'.dol_escape_htmltag($row->workflow_code).'</td><td>'.((int) $row->validation_level).'</td><td>'.dol_escape_htmltag($row->role_required ?: '-').'</td>';
	print '<td><form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="decide"><input type="hidden" name="step_id" value="'.$row->rowid.'">';
	print '<input class="flat minwidth200" name="reason" placeholder="'.$langs->trans('Comment').'"> ';
	print '<button class="button" name="decision" value="approve" type="submit">'.$langs->trans('Approve').'</button> <button class="button button-cancel" name="decision" value="reject" type="submit">'.$langs->trans('Reject').'</button></form></td></tr>';
}
if ($count === 0) {
	print '<tr><td colspan="6" class="center opacitymedium">'.$langs->trans('NoPendingValidation').'</td></tr>';
}
print '</table></div>';
llxFooter();
$db->close();
