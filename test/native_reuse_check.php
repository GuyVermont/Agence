<?php
/* Copyright (C) 2026 iPowerWorld */

if (PHP_SAPI !== 'cli') die("CLI only\n");
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);

$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/soffacturelink.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofavoirtracking.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofboncommandeclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnativeintegrationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

$errors = array();
function agence_native_assert($condition, $label)
{
	global $errors;
	echo ($condition ? '[OK] ' : '[KO] ').$label.PHP_EOL;
	if (!$condition) $errors[] = $label;
}

if (empty($GLOBALS['user']->id)) {
	$resql = $db->query('SELECT rowid FROM '.$db->prefix().'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
	$row = $resql ? $db->fetch_object($resql) : null;
	if ($row) $GLOBALS['user']->fetch((int) $row->rowid);
}
$testUser = $GLOBALS['user'];
$testUser->getrights('', 1);
agence_native_assert(!empty($testUser->id) && !empty($testUser->admin), 'administrator available for native reuse fixtures');

$editableCreditFields = array_keys(agence_get_form_fields(new SofAvoirTracking($db)));
agence_native_assert(!in_array('ref', $editableCreditFields, true) && !in_array('fk_soc', $editableCreditFields, true)
	&& !in_array('initial_amount', $editableCreditFields, true) && in_array('fk_facture_avoir', $editableCreditFields, true),
	'credit-note form asks for the native credit note and not duplicated canonical facts');
agence_native_assert(SofNativeIntegrationService::isNativeField('fk_facture_avoir') && SofNativeIntegrationService::isNativeField('fk_commande')
	&& SofNativeIntegrationService::isNativeField('fk_product') && SofNativeIntegrationService::isNativeField('fk_bank_account'),
	'native invoice, order, product and bank references use shared selectors');

$movementPage = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/agence/mouvement/encaisser.php');
$hookSource = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/agence/class/actions_agence.class.php');
agence_native_assert(strpos($movementPage, 'name="source_id"') === false && strpos($movementPage, 'name="deferred_source"') !== false,
	'deferred collection selects a readable existing support document instead of a technical id');
agence_native_assert(strpos($hookSource, 'TrackNativeCreditNote') !== false && strpos($hookSource, 'ManageAgencyAttachment') !== false
	&& strpos($hookSource, 'ConfigureCustomerCreditProfile') !== false && strpos($hookSource, 'AssignProductToDAS') !== false,
	'native invoice, credit note, order, customer and product cards expose Agence shortcuts');

$db->begin();
$token = date('YmdHis').mt_rand(100, 999);
$resql = $db->query('SELECT rowid FROM '.$db->prefix().'societe WHERE entity='.(int) $conf->entity.' AND client IN (1,2,3) ORDER BY rowid LIMIT 1');
$thirdparty = $resql ? $db->fetch_object($resql) : null;

$agency = new SofAgence($db);
$agency->entity = (int) $conf->entity;
$agency->ref = 'NATIVE-'.$token;
$agency->label = 'Native reuse check';
$agency->status = SofAgence::STATUS_ACTIVE;
$agencyId = $agency->create($testUser, 1);

$source = new Facture($db);
$source->socid = (int) $thirdparty->rowid;
$source->type = Facture::TYPE_STANDARD;
$source->date = dol_now();
$sourceId = $source->create($testUser);
$sourceLine = $sourceId > 0 ? $source->addline('Native reuse source', 2500, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) : -1;
$sourceValidation = $sourceLine > 0 ? $source->validate($testUser) : -1;
$link = new SofFactureLink($db);
$link->entity = (int) $conf->entity;
$link->fk_facture = (int) $sourceId;
$link->fk_soc = (int) $thirdparty->rowid;
$link->fk_agence = (int) $agencyId;
$link->source_type = 'native_reuse_check';
$link->source_id = (int) $sourceId;
$link->billing_status = 1;
$link->deferred_status = 0;
$link->accounting_status = 0;
$linkId = $link->create($testUser, 1);
agence_native_assert($agencyId > 0 && $sourceValidation > 0 && $linkId > 0, 'native source invoice has one explicit Agence context');

$order = new Commande($db);
$order->socid = (int) $thirdparty->rowid;
$order->date = dol_now();
$orderId = $order->create($testUser);
$orderLine = $orderId > 0 ? $order->addline('Native order reuse', 1750, 1, 0) : -1;
if ($orderId > 0) $order->fetch($orderId);
$businessOrder = new SofBonCommandeClient($db);
$businessOrder->entity = (int) $conf->entity;
$businessOrder->fk_commande = (int) $orderId;
$orderReuse = new SofNativeIntegrationService($db);
$orderReuseResult = $orderReuse->synchronize('boncommande', $businessOrder, true);
agence_native_assert($orderLine > 0 && $orderReuseResult > 0 && (int) $businessOrder->fk_soc === (int) $thirdparty->rowid
	&& $businessOrder->order_number === $order->ref && abs((float) $businessOrder->authorized_amount - abs((float) $order->total_ttc)) < 0.01,
	'native customer order reuses customer, reference, date and total without re-entry');

$credit = new Facture($db);
$credit->socid = (int) $thirdparty->rowid;
$credit->type = Facture::TYPE_CREDIT_NOTE;
$credit->fk_facture_source = (int) $sourceId;
$credit->date = dol_now();
$credit->note_private = 'Native credit-note reuse check';
$creditId = $credit->create($testUser);
$creditLine = $creditId > 0 ? $credit->addline('Native credit note', 500, 1, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1) : -1;
$creditValidation = $creditLine > 0 ? $credit->validate($testUser) : -1;
$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_avoir_tracking WHERE entity='.(int) $conf->entity.' AND fk_facture_avoir='.(int) $creditId);
$nativeTracking = $resql ? $db->fetch_object($resql) : null;
agence_native_assert($creditValidation > 0 && $nativeTracking && (int) $nativeTracking->fk_facture_origin === (int) $sourceId
	&& (int) $nativeTracking->fk_soc === (int) $thirdparty->rowid && (int) $nativeTracking->fk_agence === (int) $agencyId
	&& abs((float) $nativeTracking->initial_amount - abs((float) $credit->total_ttc)) < 0.01,
	'validated native credit note automatically reuses origin, customer, agency and amount');

$duplicate = new SofAvoirTracking($db);
$duplicate->entity = (int) $conf->entity;
$duplicate->fk_facture_avoir = (int) $creditId;
$nativeService = new SofNativeIntegrationService($db);
$duplicateResult = $nativeService->synchronize('avoir', $duplicate, true);
agence_native_assert($duplicateResult < 0 && $nativeService->error === $langs->trans('CreditNoteAlreadyTracked'),
	'duplicate Agence tracking of one native credit note is rejected before write');

$selector = agence_select_native_object('fk_facture_avoir', $creditId, true);
agence_native_assert(strpos($selector, '<select') !== false && strpos($selector, (string) $credit->ref) !== false
	&& strpos($selector, 'type="number"') === false, 'native credit-note selector displays business references rather than a numeric field');

$db->rollback();
if (!empty($errors)) {
	fwrite(STDERR, 'Native reuse check failed: '.count($errors).' error(s).'.PHP_EOL);
	exit(1);
}
echo "Native PowerERP reuse check completed successfully.\n";
