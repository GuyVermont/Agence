<?php
/* Copyright (C) 2026 SOFITOUL */

if (PHP_SAPI !== 'cli') {
	die("CLI only\n");
}
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);

$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceuser.class.php';

$action = isset($argv[1]) ? (string) $argv[1] : '';
$login = 'agence_browser_qualif';
$permissions = array(
	array('agence', 'read'), array('report', 'read'), array('report', 'export'),
	array('session', 'open'), array('mouvement', 'cashin'),
);

function agence_browser_grant_permissions($db, $entity, $userId, array $permissions)
{
	foreach ($permissions as $permission) {
		$sql = 'INSERT INTO '.$db->prefix().'user_rights (entity, fk_user, fk_id) SELECT '.((int) $entity).', '.((int) $userId).', r.id';
		$sql .= ' FROM '.$db->prefix()."rights_def r WHERE r.entity = ".((int) $entity)." AND r.module = 'agence'";
		$sql .= " AND r.perms = '".$db->escape($permission[0])."' AND r.subperms = '".$db->escape($permission[1])."'";
		$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.$db->prefix().'user_rights ur WHERE ur.entity = '.((int) $entity).' AND ur.fk_user = '.((int) $userId).' AND ur.fk_id = r.id)';
		if (!$db->query($sql)) {
			return false;
		}
	}
	return true;
}

$admin = new User($db);
$resql = $db->query('SELECT rowid FROM '.$db->prefix().'user WHERE admin = 1 AND statut = 1 ORDER BY rowid LIMIT 1');
$adminRow = $resql ? $db->fetch_object($resql) : null;
if ($adminRow) {
	$admin->fetch((int) $adminRow->rowid);
	$admin->getrights('', 1);
}

$existing = new User($db);
$existing->fetch(0, $login);
if ($action === 'cleanup') {
	$db->begin();
	if (!empty($existing->id)) {
		$db->query('DELETE FROM '.$db->prefix().'sof_agence_user WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $existing->id));
		$db->query('DELETE FROM '.$db->prefix().'user_rights WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $existing->id));
		$db->query('DELETE FROM '.$db->prefix().'user WHERE rowid = '.((int) $existing->id));
	}
	$db->query('DELETE FROM '.$db->prefix()."sof_agence WHERE entity = ".((int) $conf->entity)." AND ref LIKE 'BROWSER-QUALIF-%'");
	$db->commit();
	echo json_encode(array('cleanup' => true)), PHP_EOL;
	exit(0);
}

if ($action === 'revoke' || $action === 'disable' || $action === 'restore') {
	if (empty($existing->id)) {
		exit(2);
	}
	if ($action === 'revoke') {
		$db->query('DELETE FROM '.$db->prefix().'user_rights WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $existing->id));
	} elseif ($action === 'disable') {
		$db->query('UPDATE '.$db->prefix().'user SET statut = 0 WHERE rowid = '.((int) $existing->id));
	} else {
		$db->query('UPDATE '.$db->prefix().'user SET statut = 1 WHERE rowid = '.((int) $existing->id));
		agence_browser_grant_permissions($db, (int) $conf->entity, (int) $existing->id, $permissions);
	}
	echo json_encode(array($action => true, 'user_id' => (int) $existing->id)), PHP_EOL;
	exit(0);
}

if ($action !== 'setup') {
	die("Usage: browser_fixture.php setup|revoke|disable|restore|cleanup\n");
}
if (!empty($existing->id)) {
	die("Fixture already exists; run cleanup first.\n");
}

$token = date('YmdHis').mt_rand(100, 999);
$password = 'Qf!'.bin2hex(random_bytes(8)).'a7';
$browserUser = new User($db);
$browserUser->entity = (int) $conf->entity;
$browserUser->login = $login;
$browserUser->lastname = 'Qualification';
$browserUser->firstname = 'Navigateur';
$browserUser->email = '';
$browserUser->statut = 1;
$browserUser->admin = 0;
$browserUserId = $browserUser->create($admin, 1);
if ($browserUserId <= 0 || $browserUser->setPassword($admin, $password, 0, 1, 0, 0, 1) < 0) {
	die('Unable to create browser user: '.$browserUser->error.PHP_EOL);
}

if (!agence_browser_grant_permissions($db, (int) $conf->entity, (int) $browserUserId, $permissions)) {
	die('Unable to grant browser permissions.'.PHP_EOL);
}

$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity;
$agency->ref = 'BROWSER-QUALIF-'.$token;
$agency->label = 'Agence qualification navigateur iPowerWorld';
$agency->town = 'Douala';
$agency->country_code = 'CM';
$agency->note_public = 'Document PDF de qualification visuelle. '.str_repeat('Texte long contrôlé pour vérifier les retours à la ligne sans chevauchement. ', 8);
$agency->status = 1;
$agencyId = $agency->create($admin, 1);

$scope = new SofAgenceUser($db);
$scope->entity = (int) $conf->entity;
$scope->fk_agence = (int) $agencyId;
$scope->fk_user = (int) $browserUserId;
$scope->role_code = 'cashier';
$scope->scope_type = 'agency';
$scope->status = 1;
$scopeId = $scope->create($admin, 1);
if ($agencyId <= 0 || $scopeId <= 0) {
	die('Unable to create browser fixtures.'.PHP_EOL);
}

echo json_encode(array(
	'login' => $login,
	'password' => $password,
	'user_id' => (int) $browserUserId,
	'agency_id' => (int) $agencyId,
	'card_url' => 'http://dev.test/htdocs/custom/agence/agence/card.php?id='.(int) $agencyId,
	'report_url' => 'http://dev.test/htdocs/custom/agence/report/index.php',
), JSON_UNESCAPED_SLASHES), PHP_EOL;
