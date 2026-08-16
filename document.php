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
if ($action !== 'builddoc' || GETPOST('token') !== $_SESSION['newtoken']) {
	accessforbidden('Invalid action or token');
}

$object = agence_new_object($config);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden('Record not found');
}
agence_enforce_object_scope($object);

$model = new pdf_agence_standard($db);
$result = $model->write_file($object, $langs);
if ($result < 0) {
	setEventMessages($langs->trans('ErrorFailedToGeneratePDF'), empty($model->result['error']) ? null : array($model->result['error']), 'errors');
	header('Location: '.agence_object_card_url($key, $id));
	exit;
}

setEventMessages($langs->trans('PDFGenerated'), null, 'mesgs');
header('Location: '.agence_object_card_url($key, $id));
exit;
