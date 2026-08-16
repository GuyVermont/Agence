<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($user->id)) {
	http_response_code(403);
	echo json_encode(array('ok' => false, 'warning' => 'Authentification requise.'));
	exit;
}

$terminal = !empty($_SESSION['takeposterminal']) ? (string) $_SESSION['takeposterminal'] : GETPOST('terminal_id', 'alphanohtml');
$output = array('ok' => false, 'terminal_id' => $terminal);
if ($terminal === '') {
	$output['warning'] = 'Aucun terminal TakePOS actif.';
	echo json_encode($output);
	exit;
}
$sql = 'SELECT fk_agence, fk_caisse FROM '.$db->prefix().'sof_takepos_link';
$sql .= " WHERE entity = ".((int) $conf->entity)." AND terminal_ref = '".$db->escape($terminal)."'";
$sql .= ' AND fk_facture IS NULL AND status = 1 ORDER BY rowid DESC LIMIT 1';
$resql = $db->query($sql);
$mapping = $resql ? $db->fetch_object($resql) : null;
if (!$mapping) {
	$output['warning'] = 'Terminal '.$terminal.' non configuré dans Agence.';
	echo json_encode($output);
	exit;
}
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
$allowedAgencyIds = SofAgenceService::allowedAgencyIds($db, $user);
if ($allowedAgencyIds !== null && !in_array((int) $mapping->fk_agence, $allowedAgencyIds, true)) {
	http_response_code(403);
	echo json_encode(array('ok' => false, 'warning' => 'Terminal non disponible dans votre périmètre.'));
	exit;
}
$sql = 'SELECT s.rowid, s.ref, c.ref caisse_ref, c.label caisse_label FROM '.$db->prefix().'sof_caisse_session s';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_caisse c ON c.rowid=s.fk_caisse';
$sql .= ' WHERE s.entity = '.((int) $conf->entity).' AND s.fk_caisse = '.((int) $mapping->fk_caisse);
$sql .= ' AND s.status IN (1,2) AND s.freeze_status = 0 ORDER BY s.date_opening DESC LIMIT 1';
$resql = $db->query($sql);
$session = $resql ? $db->fetch_object($resql) : null;
if ($session) {
	$output['ok'] = true;
	$output['session_ref'] = $session->ref;
	$output['caisse'] = trim($session->caisse_ref.' '.$session->caisse_label);
} else {
	$output['warning'] = 'Aucune session ouverte et non gelée sur la caisse du terminal '.$terminal.'.';
}
echo json_encode($output);
