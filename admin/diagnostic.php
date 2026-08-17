<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofindustrialservice.class.php';

if (!$user->admin && !$user->hasRight('agence', 'diagnostic', 'read')) accessforbidden();
$service = new SofAgenceIndustrialService($db);
$checks = $service->diagnostics($user);
llxHeader('', 'Diagnostic administrateur Agence', '', '', 0, 0, '', '', '', 'mod-agence page-diagnostic');
print load_fiche_titre('Diagnostic administrateur Agence', '', 'stethoscope');
print '<p class="opacitymedium">Vue sans effet de bord sur les tâches planifiées, le schéma, les mappings, les comptes financiers et les intégrations.</p>';
$counts = array('ok'=>0,'warning'=>0,'error'=>0);
foreach ($checks as $check) $counts[$check['status']]++;
print '<div class="fichecenter"><div class="info-box">OK : <strong>'.$counts['ok'].'</strong> &nbsp; Avertissements : <strong>'.$counts['warning'].'</strong> &nbsp; Erreurs : <strong>'.$counts['error'].'</strong></div></div>';
print '<table class="liste centpercent"><tr class="liste_titre"><th>Catégorie</th><th>Contrôle</th><th>État</th><th>Diagnostic</th></tr>';
foreach ($checks as $check) {
	$badge = $check['status'] === 'ok' ? 'badge-status4' : ($check['status'] === 'warning' ? 'badge-status1' : 'badge-status8');
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($check['category']).'</td><td>'.dol_escape_htmltag($check['code']).'</td><td><span class="badge '.$badge.'">'.dol_escape_htmltag(strtoupper($check['status'])).'</span></td><td>'.dol_escape_htmltag($check['message']).'</td></tr>';
}
print '</table>';
llxFooter();
$db->close();
