<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/document.php
 * \ingroup    agence
 * \brief      Generate generic SOFITOUL agency object PDFs.
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/core/modules/agence/doc/pdf_agence_standard.modules.php';

$langs->loadLangs(array('agence@agence'));

$key = GETPOST('object', 'alpha');
$id = (int) GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

$config = agence_get_object_config($key);
if (!agence_user_has_one_permission($config['readperms'])) {
	accessforbidden();
}
$object = agence_new_object($config);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden('Record not found');
}
agence_enforce_object_scope($object);
if (!empty($object->entity) && (int) $object->entity !== (int) $conf->entity) {
	accessforbidden('Record belongs to another entity');
}

$model = new pdf_agence_standard($db);

if ($action === 'download') {
	$file = $model->getDocumentPath($object);
	$realFile = realpath($file);
	$realDirectory = realpath(dirname($file));
	if ($realFile === false || $realDirectory === false || strpos($realFile, $realDirectory.DIRECTORY_SEPARATOR) !== 0 || !is_file($realFile)) {
		accessforbidden('PDF not generated');
	}
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="'.basename($realFile).'"');
	header('Content-Length: '.filesize($realFile));
	header('Cache-Control: private, no-store, max-age=0');
	header('X-Content-Type-Options: nosniff');
	readfile($realFile);
	exit;
}

if ($action !== 'builddoc' || GETPOST('token') !== $_SESSION['newtoken']) {
	accessforbidden('Invalid action or token');
}

$result = $model->write_file($object, $langs);
if ($result < 0) {
	setEventMessages($langs->trans('ErrorFailedToGeneratePDF'), empty($model->result['error']) ? null : array($model->result['error']), 'errors');
	header('Location: '.agence_object_card_url($key, $id));
	exit;
}

header('Location: '.dol_buildpath('/agence/document.php', 1).'?object='.urlencode($key).'&id='.$id.'&action=download');
exit;
