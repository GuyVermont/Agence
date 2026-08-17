<?php
/* Copyright (C) 2026 iPowerWorld */

/**
 * \file       htdocs/custom/agence/admin/about.php
 * \ingroup    agence
 * \brief      About page for Agence module.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/modules/modAgence.class.php';

$langs->loadLangs(array('admin', 'agence@agence'));

if (!$user->admin && !$user->hasRight('agence', 'parametre', 'write')) {
	accessforbidden();
}

llxHeader('', $langs->trans('About'), '', '', 0, 0, '', '', '', 'mod-agence page-admin_about');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('About'), $linkback, 'title_setup');

$head = agenceAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('ModuleAgenceName'), -1, 'building');
$moduleDescriptor = new modAgence($db);

print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('Module').'</td><td>'.$langs->trans('ModuleAgenceName').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Version').'</td><td>'.dol_escape_htmltag($moduleDescriptor->version).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Publisher').'</td><td><a href="https://ipowerworld.net" rel="noopener noreferrer">iPowerWorld</a></td></tr>';
print '<tr class="oddeven"><td>Support</td><td><a href="mailto:csa@ipowerworld.net">csa@ipowerworld.net</a></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('Purpose').'</td><td>'.$langs->trans('ModuleAgenceDescLong').'</td></tr>';
print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
