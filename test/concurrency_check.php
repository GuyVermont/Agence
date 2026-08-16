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
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';

function agence_concurrency_admin()
{
	global $db;
	$admin = new User($db);
	$resql = $db->query('SELECT rowid FROM '.$db->prefix().'user WHERE admin = 1 AND statut = 1 ORDER BY rowid LIMIT 1');
	$row = $resql ? $db->fetch_object($resql) : null;
	if ($row) {
		$admin->fetch((int) $row->rowid);
		$admin->getrights('', 1);
	}
	return $admin;
}

if (isset($argv[1]) && $argv[1] === '--worker') {
	$sessionId = isset($argv[2]) ? (int) $argv[2] : 0;
	$barrierToken = isset($argv[3]) ? preg_replace('/[^a-z0-9-]/i', '', (string) $argv[3]) : '';
	$barrier = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'agence-concurrency-'.$barrierToken;
	$worker = isset($argv[4]) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $argv[4]) : '';
	$admin = agence_concurrency_admin();
	$sql = 'SELECT status FROM '.$db->prefix().'sof_caisse_session WHERE entity = '.((int) $conf->entity).' AND rowid = '.$sessionId;
	$resql = $db->query($sql);
	$before = $resql ? $db->fetch_object($resql) : null;
	file_put_contents($barrier.DIRECTORY_SEPARATOR.'ready-'.$worker, '1', LOCK_EX);
	$deadline = microtime(true) + 10;
	while (count(glob($barrier.DIRECTORY_SEPARATOR.'ready-*')) < 2 && microtime(true) < $deadline) {
		usleep(20000);
	}
	$engine = new SofAgenceOperations($db);
	$result = $engine->updateRow('sof_caisse_session', $sessionId, array('status' => 2), $admin, 1);
	echo json_encode(array('worker' => $worker, 'before' => $before ? (int) $before->status : null, 'result' => $result, 'error' => $engine->error)), PHP_EOL;
	exit(0);
}

$admin = agence_concurrency_admin();
$errors = array();
$token = date('YmdHis').mt_rand(1000, 9999);
$barrier = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'agence-concurrency-'.$token;
mkdir($barrier, 0700, true);

$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity;
$agency->ref = 'TEST-CONC-AG-'.$token;
$agency->label = 'Agence concurrency qualification';
$agency->country_code = 'CM';
$agency->status = 1;
$agencyId = $agency->create($admin, 1);

$cashDesk = new SofCaisse($db);
$cashDesk->entity = (int) $conf->entity;
$cashDesk->fk_agence = (int) $agencyId;
$cashDesk->ref = 'TEST-CONC-CAI-'.$token;
$cashDesk->label = 'Cash desk concurrency qualification';
$cashDesk->caisse_type = 'cash';
$cashDesk->currency_code = 'XAF';
$cashDesk->status = 1;
$cashDeskId = $cashDesk->create($admin, 1);

$session = new SofCaisseSession($db);
$session->entity = (int) $conf->entity;
$session->ref = 'TEST-CONC-SES-'.$token;
$session->fk_agence = (int) $agencyId;
$session->fk_caisse = (int) $cashDeskId;
$session->fk_user_cashier = (int) $admin->id;
$session->session_type = 'qualification';
$session->date_opening = dol_now();
$session->opening_amount = 0;
$session->theoretical_amount = 0;
$session->physical_amount = 0;
$session->gap_amount = 0;
$session->accounting_status = 0;
$session->accounting_attempts = 0;
$session->freeze_status = 0;
$session->status = 1;
$sessionId = $session->create($admin, 1);

$processes = array();
foreach (array('a', 'b') as $name) {
	$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$pipes = array();
	$process = proc_open(array(PHP_BINARY, 'custom/agence/test/concurrency_check.php', '--worker', (string) $sessionId, $token, $name), $descriptors, $pipes, DOL_DOCUMENT_ROOT);
	if (is_resource($process)) {
		fclose($pipes[0]);
		$processes[] = array($process, $pipes);
	}
}

$results = array();
foreach ($processes as $entry) {
	list($process, $pipes) = $entry;
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($process);
	$decoded = json_decode(trim($stdout), true);
	if (is_array($decoded)) {
		$results[] = $decoded;
	} else {
		$errors[] = 'Sortie worker invalide: '.$stdout.' '.$stderr;
	}
}

$wins = 0;
$conflicts = 0;
foreach ($results as $result) {
	$wins += (int) $result['result'] > 0 ? 1 : 0;
	$conflicts += (int) $result['result'] < 0 ? 1 : 0;
	if ((int) $result['before'] !== 1) {
		$errors[] = 'Les deux workers n’ont pas lu le même état initial.';
	}
}
$ok = count($processes) === 2 && count($results) === 2 && $wins === 1 && $conflicts === 1 && empty($errors);
echo ($ok ? '[OK] ' : '[KO] '),'deux processus simultanés produisent exactement un gagnant et un conflit optimiste',PHP_EOL;
foreach ($errors as $error) {
	echo '[KO] '.$error.PHP_EOL;
}

$db->begin();
$db->query('DELETE FROM '.$db->prefix().'sof_caisse_session WHERE rowid = '.((int) $sessionId).' AND entity = '.((int) $conf->entity));
$db->query('DELETE FROM '.$db->prefix().'sof_caisse WHERE rowid = '.((int) $cashDeskId).' AND entity = '.((int) $conf->entity));
$db->query('DELETE FROM '.$db->prefix().'sof_agence WHERE rowid = '.((int) $agencyId).' AND entity = '.((int) $conf->entity));
$db->commit();
foreach (glob($barrier.DIRECTORY_SEPARATOR.'ready-*') as $file) {
	unlink($file);
}
rmdir($barrier);

exit($ok ? 0 : 1);
