<?php
/* Copyright (C) 2026 iPowerWorld */

/**
 * \file       htdocs/custom/agence/admin/setup.php
 * \ingroup    agence
 * \brief      Setup page for Agence module.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';

$langs->loadLangs(array('admin', 'agence@agence'));

if (!$user->admin && !$user->hasRight('agence', 'parametre', 'write')) {
	accessforbidden();
}

$settings = agence_get_settings_definition();
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'restricthtml');
$constname = GETPOST('constname', 'alpha');

if ($action === 'setconst') {
	if (!GETPOST('token') || GETPOST('token') != $_SESSION['newtoken']) {
		accessforbidden('Invalid CSRF token');
	}

	$effectiveValues = array();
	foreach ($settings as $key => $setting) {
		$effectiveValues[$key] = getDolGlobalString($key, $setting['default']);
	}
	$normalizedValue = null;
	$validationError = '';
	if (!agence_validate_setting_update($constname, $value, $effectiveValues, $normalizedValue, $validationError)) {
		setEventMessages($validationError, null, 'errors');
	} else {
		$res = dolibarr_set_const($db, $constname, $normalizedValue, 'chaine', 0, '', $conf->entity);
		if ($res > 0) {
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
}

llxHeader('', $langs->trans('AgenceSetup'), '', '', 0, 0, '', '', '', 'mod-agence page-admin_setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AgenceSetup'), $linkback, 'title_setup');

$head = agenceAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('ModuleAgenceName'), -1, 'building');

print '<span class="opacitymedium">'.$langs->trans('AgenceSetupIntro').'</span><br><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td><td></td></tr>';
foreach ($settings as $key => $setting) {
	$current = getDolGlobalString($key, $setting['default']);
	print '<tr class="oddeven">';
	print '<td>'.(strpos($setting['label'], ' ') === false ? $langs->trans($setting['label']) : dol_escape_htmltag($setting['label'])).'</td>';
	$displayValue = $setting['type'] === 'secret' ? $langs->trans($current !== '' ? 'Configured' : 'NotConfigured') : dol_escape_htmltag($current);
	print '<td>'.($setting['type'] === 'boolean' ? ($current ? $langs->trans('Yes') : $langs->trans('No')) : $displayValue).'</td>';
	print '<td class="right">';
	if ($setting['type'] === 'boolean') {
		print '<form class="inline-block" method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setconst"><input type="hidden" name="constname" value="'.dol_escape_htmltag($key).'"><input type="hidden" name="value" value="'.($current ? '0' : '1').'"><button class="button smallpaddingimp" type="submit">'.img_picto($langs->trans('Switch'), 'switch_on').'</button></form>';
	} elseif (in_array($setting['type'], array('integer', 'decimal'), true)) {
		$step = $setting['type'] === 'integer' ? '1' : '0.01';
		print '<form class="inline-block" method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setconst"><input type="hidden" name="constname" value="'.dol_escape_htmltag($key).'"><input class="flat width100" type="number" min="'.dol_escape_htmltag((string) $setting['min']).'" max="'.dol_escape_htmltag((string) $setting['max']).'" step="'.$step.'" name="value" value="'.dol_escape_htmltag($current).'"><button class="button smallpaddingimp" type="submit">'.$langs->trans('Save').'</button></form>';
	} elseif ($setting['type'] === 'secret') {
		print '<form class="inline-block" method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setconst"><input type="hidden" name="constname" value="'.dol_escape_htmltag($key).'"><input class="flat minwidth400" autocomplete="new-password" type="password" name="value" value="" placeholder="'.dol_escape_htmltag($langs->trans('LeaveEmptyToClear')).'"><button class="button smallpaddingimp" type="submit">'.$langs->trans('Save').'</button></form>';
	} else {
		print '<form class="inline-block" method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setconst"><input type="hidden" name="constname" value="'.dol_escape_htmltag($key).'"><input class="flat minwidth400" type="text" name="value" value="'.dol_escape_htmltag($current).'"><button class="button smallpaddingimp" type="submit">'.$langs->trans('Save').'</button></form>';
	}
	print '</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
