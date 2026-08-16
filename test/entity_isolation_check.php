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
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_report.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/modules/agence/doc/pdf_agence_standard.modules.php';

$errors = array();
function agence_entity_assert($condition, $label)
{
	global $errors;
	echo ($condition ? '[OK] ' : '[KO] ').$label.PHP_EOL;
	if (!$condition) {
		$errors[] = $label;
	}
}

$resql = $db->query('SELECT rowid FROM '.$db->prefix().'user WHERE admin = 1 AND statut = 1 ORDER BY rowid LIMIT 1');
$row = $resql ? $db->fetch_object($resql) : null;
if ($row) {
	$user->fetch((int) $row->rowid);
	$user->getrights('', 1);
}
$currentEntity = (int) $conf->entity;
$otherEntity = $currentEntity + 100000;
$token = date('YmdHis').mt_rand(100, 999);
$ref = 'TEST-ENTITY-'.$token;

$db->begin();
$sql = 'INSERT INTO '.$db->prefix().'sof_agence (entity, ref, label, country_code, status, date_creation, fk_user_creat) VALUES (';
$sql .= $otherEntity.", '".$db->escape($ref)."', 'Other entity fixture', 'CM', 1, CURRENT_TIMESTAMP, ".((int) $user->id).')';
$insert = $db->query($sql);
$otherAgencyId = $insert ? (int) $db->last_insert_id($db->prefix().'sof_agence') : 0;
agence_entity_assert($otherAgencyId > 0, 'fixture created in a second logical Dolibarr entity');

$otherAgency = new SofAgence($db);
agence_entity_assert($otherAgency->fetch($otherAgencyId) <= 0, 'CommonObject fetch cannot read the other entity by direct id');
agence_entity_assert(
	strpos(SofAgenceService::validateAgencyCashDeskDas($db, $otherAgencyId, 0, 0, true), 'entité courante') !== false,
	'cross-entity agency validation is rejected'
);

$rows = agence_report_transversal_rows();
$found = false;
foreach ($rows as $reportRow) {
	if ((string) $reportRow->ref === $ref) {
		$found = true;
		break;
	}
}
agence_entity_assert(!$found, 'role dashboards and CSV datasets exclude the other entity');

$engine = new SofAgenceOperations($db);
$update = $engine->updateRow('sof_agence', $otherAgencyId, array('status' => 9), $user, 1);
agence_entity_assert($update < 0, 'typed business updates cannot mutate the other entity');

$pdfModel = new pdf_agence_standard($db);
$first = (object) array('entity' => $currentEntity, 'table_element' => 'sof_agence', 'ref' => $ref, 'id' => 1, 'rowid' => 1);
$second = clone $first;
$second->entity = $otherEntity;
agence_entity_assert($pdfModel->getDocumentPath($first) !== $pdfModel->getDocumentPath($second), 'PDF storage paths are physically isolated by entity');

$db->rollback();
$conf->entity = $currentEntity;
echo empty($errors) ? "Entity isolation check completed successfully.\n" : 'Entity isolation check failed: '.count($errors).' error(s).'.PHP_EOL;
exit(empty($errors) ? 0 : 1);
