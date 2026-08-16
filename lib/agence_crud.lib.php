<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/lib/agence_crud.lib.php
 * \ingroup    agence
 * \brief      Generic CRUD helpers for SOFITOUL agency objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_operations.lib.php';

/**
 * Return registry for SOFITOUL business objects.
 *
 * @return array<string,array<string,mixed>>
 */
function agence_get_object_registry()
{
	$readAgency = array(array('agence', 'agence', 'read'));
	$writeAgency = array(array('agence', 'agence', 'write'));

	return array(
		'agence' => array('class' => 'SofAgence', 'file' => 'sofagence', 'path' => 'agence', 'label' => 'Agencies', 'singular' => 'Agency', 'picto' => 'building', 'readperms' => $readAgency, 'writeperms' => $writeAgency),
		'das' => array('class' => 'SofDas', 'file' => 'sofdas', 'path' => 'das', 'label' => 'DASList', 'singular' => 'DAS', 'picto' => 'sitemap', 'readperms' => $readAgency, 'writeperms' => $writeAgency),
		'agenceuser' => array('class' => 'SofAgenceUser', 'file' => 'sofagenceuser', 'path' => 'agence', 'label' => 'AgencyUserScopes', 'singular' => 'AgencyUserScope', 'picto' => 'user-tag', 'readperms' => array(array('agence', 'scope', 'write')), 'writeperms' => array(array('agence', 'scope', 'write'))),
		'roletransversal' => array('class' => 'SofRoleTransversal', 'file' => 'sofroletransversal', 'path' => 'agence', 'label' => 'TransversalRoles', 'singular' => 'TransversalRole', 'picto' => 'users-gear', 'readperms' => array(array('agence', 'scope', 'write')), 'writeperms' => array(array('agence', 'scope', 'write'))),
		'tierscredit' => array('class' => 'SofTiersCreditProfile', 'file' => 'softierscreditprofile', 'path' => 'mouvement', 'label' => 'CustomerCreditProfiles', 'singular' => 'CustomerCreditProfile', 'picto' => 'address-card', 'readperms' => array(array('agence', 'paiementdiffere', 'create'), array('agence', 'paiementdiffere', 'validate')), 'writeperms' => array(array('agence', 'paiementdiffere', 'create'))),
		'productdas' => array('class' => 'SofProductDas', 'file' => 'sofproductdas', 'path' => 'das', 'label' => 'ProductDASMapping', 'singular' => 'ProductDASMappingLine', 'picto' => 'boxes-stacked', 'readperms' => $readAgency, 'writeperms' => $writeAgency),

		'caisse' => array('class' => 'SofCaisse', 'file' => 'sofcaisse', 'path' => 'caisse', 'label' => 'CashDesks', 'singular' => 'CashDesk', 'picto' => 'cash-register', 'readperms' => array(array('agence', 'caisse', 'read')), 'writeperms' => array(array('agence', 'caisse', 'write'))),
		'session' => array('class' => 'SofCaisseSession', 'file' => 'sofcaissesession', 'path' => 'session', 'label' => 'CashSessions', 'singular' => 'CashSession', 'picto' => 'clock', 'readperms' => array(array('agence', 'session', 'open'), array('agence', 'session', 'close'), array('agence', 'session', 'validate')), 'writeperms' => array(array('agence', 'session', 'open'), array('agence', 'session', 'close')), 'allowcreate' => false, 'allowedit' => false),
		'cloture' => array('class' => 'SofCaisseCloture', 'file' => 'sofcaissecloture', 'path' => 'session', 'label' => 'CashClosings', 'singular' => 'CashClosing', 'picto' => 'lock', 'readperms' => array(array('agence', 'session', 'close'), array('agence', 'session', 'validate')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'comptage' => array('class' => 'SofCaisseComptage', 'file' => 'sofcaissecomptage', 'path' => 'session', 'label' => 'CashCounts', 'singular' => 'CashCount', 'picto' => 'coins', 'readperms' => array(array('agence', 'session', 'close'), array('agence', 'controle', 'create')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'ecart' => array('class' => 'SofCaisseEcart', 'file' => 'sofcaisseecart', 'path' => 'controle', 'label' => 'CashGaps', 'singular' => 'CashGap', 'picto' => 'triangle-exclamation', 'readperms' => array(array('agence', 'ecart', 'manage'), array('agence', 'controle', 'create')), 'writeperms' => array(array('agence', 'ecart', 'manage')), 'allowcreate' => false),
		'mouvement' => array('class' => 'SofCaisseMouvement', 'file' => 'sofcaissemouvement', 'path' => 'mouvement', 'label' => 'CashMovements', 'singular' => 'CashMovement', 'picto' => 'money-bill-transfer', 'readperms' => array(array('agence', 'mouvement', 'cashin'), array('agence', 'session', 'validate')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),

		'paiementdiffere' => array('class' => 'SofPaiementDiffere', 'file' => 'sofpaiementdiffere', 'path' => 'differe', 'label' => 'DeferredPayments', 'singular' => 'DeferredPayment', 'picto' => 'file-invoice-dollar', 'readperms' => array(array('agence', 'paiementdiffere', 'create'), array('agence', 'paiementdiffere', 'validate')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'boncommande' => array('class' => 'SofBonCommandeClient', 'file' => 'sofboncommandeclient', 'path' => 'differe', 'label' => 'CustomerPurchaseOrders', 'singular' => 'CustomerPurchaseOrder', 'picto' => 'file-signature', 'readperms' => array(array('agence', 'boncommande', 'validate'), array('agence', 'paiementdiffere', 'create')), 'writeperms' => array(array('agence', 'boncommande', 'validate'))),
		'bst' => array('class' => 'SofBST', 'file' => 'sofbst', 'path' => 'differe', 'label' => 'BSTList', 'singular' => 'BST', 'picto' => 'route', 'readperms' => array(array('agence', 'bst', 'validate'), array('agence', 'paiementdiffere', 'create')), 'writeperms' => array(array('agence', 'bst', 'validate'))),
		'instruction' => array('class' => 'SofInstructionManageriale', 'file' => 'sofinstructionmanageriale', 'path' => 'differe', 'label' => 'ManagerInstructions', 'singular' => 'ManagerInstruction', 'picto' => 'clipboard-check', 'readperms' => array(array('agence', 'instruction', 'validate'), array('agence', 'paiementdiffere', 'create')), 'writeperms' => array(array('agence', 'instruction', 'validate'))),

		'avoir' => array('class' => 'SofAvoirTracking', 'file' => 'sofavoirtracking', 'path' => 'avoir', 'label' => 'CreditNoteFollowups', 'singular' => 'CreditNoteFollowup', 'picto' => 'rotate-left', 'readperms' => array(array('agence', 'avoir', 'create'), array('agence', 'avoir', 'validate'), array('agence', 'avoir', 'use')), 'writeperms' => array(array('agence', 'avoir', 'create'))),
		'remboursement' => array('class' => 'SofRemboursement', 'file' => 'sofremboursement', 'path' => 'remboursement', 'label' => 'Refunds', 'singular' => 'Refund', 'picto' => 'hand-holding-dollar', 'readperms' => array(array('agence', 'remboursement', 'request'), array('agence', 'remboursement', 'validate'), array('agence', 'remboursement', 'execute')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'facturelink' => array('class' => 'SofFactureLink', 'file' => 'soffacturelink', 'path' => 'mouvement', 'label' => 'InvoiceAgencyLinks', 'singular' => 'InvoiceAgencyLink', 'picto' => 'file-invoice', 'readperms' => array(array('agence', 'mouvement', 'cashin'), array('agence', 'paiementdiffere', 'create')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'paiementlink' => array('class' => 'SofPaiementLink', 'file' => 'sofpaiementlink', 'path' => 'mouvement', 'label' => 'PaymentAgencyLinks', 'singular' => 'PaymentAgencyLink', 'picto' => 'money-bill-transfer', 'readperms' => array(array('agence', 'mouvement', 'cashin'), array('agence', 'mouvement', 'mixedpayment')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'commandelink' => array('class' => 'SofCommandeLink', 'file' => 'sofcommandelink', 'path' => 'mouvement', 'label' => 'OrderAgencyLinks', 'singular' => 'OrderAgencyLink', 'picto' => 'cart-shopping', 'readperms' => array(array('agence', 'mouvement', 'cashin'), array('agence', 'paiementdiffere', 'create')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'takeposlink' => array('class' => 'SofTakeposLink', 'file' => 'softakeposlink', 'path' => 'mouvement', 'label' => 'TakePOSLinks', 'singular' => 'TakePOSLink', 'picto' => 'cash-register', 'readperms' => array(array('agence', 'mouvement', 'cashin')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),

		'controle' => array('class' => 'SofCaisseControle', 'file' => 'sofcaissecontrole', 'path' => 'controle', 'label' => 'SurpriseControls', 'singular' => 'SurpriseControl', 'picto' => 'shield-halved', 'readperms' => array(array('agence', 'controle', 'create'), array('agence', 'audit', 'read')), 'writeperms' => array(array('agence', 'controle', 'create'))),
		'validation' => array('class' => 'SofCaisseValidation', 'file' => 'sofcaissevalidation', 'path' => 'controle', 'label' => 'Validations', 'singular' => 'Validation', 'picto' => 'check-double', 'readperms' => array(array('agence', 'session', 'validate'), array('agence', 'workflow', 'write')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'workflow' => array('class' => 'SofCaisseWorkflow', 'file' => 'sofcaisseworkflow', 'path' => 'workflow', 'label' => 'WorkflowRules', 'singular' => 'WorkflowRule', 'picto' => 'code-branch', 'readperms' => array(array('agence', 'workflow', 'write')), 'writeperms' => array(array('agence', 'workflow', 'write'))),
		'parametre' => array('class' => 'SofParametre', 'file' => 'sofparametre', 'path' => 'workflow', 'label' => 'AgencyParameters', 'singular' => 'AgencyParameter', 'picto' => 'gear', 'readperms' => array(array('agence', 'parametre', 'write')), 'writeperms' => array(array('agence', 'parametre', 'write'))),

		'transfert' => array('class' => 'SofCaisseTransfert', 'file' => 'sofcaissetransfert', 'path' => 'banque', 'label' => 'VaultTransfers', 'singular' => 'VaultTransfer', 'picto' => 'vault', 'readperms' => array(array('agence', 'transfert', 'create'), array('agence', 'depotbanque', 'create')), 'writeperms' => array(array('agence', 'transfert', 'create'))),
		'depotbanque' => array('class' => 'SofCaisseDepotBanque', 'file' => 'sofcaissedepotbanque', 'path' => 'banque', 'label' => 'BankDeposits', 'singular' => 'BankDeposit', 'picto' => 'building-columns', 'readperms' => array(array('agence', 'depotbanque', 'create'), array('agence', 'depotbanque', 'reconcile')), 'writeperms' => array(array('agence', 'depotbanque', 'create'))),
		'banklink' => array('class' => 'SofBankLink', 'file' => 'sofbanklink', 'path' => 'banque', 'label' => 'BankAgencyLinks', 'singular' => 'BankAgencyLink', 'picto' => 'landmark', 'readperms' => array(array('agence', 'depotbanque', 'reconcile'), array('agence', 'compta', 'post')), 'writeperms' => array(array('agence', 'depotbanque', 'reconcile'))),
		'mappingcomptable' => array('class' => 'SofMappingComptable', 'file' => 'sofmappingcomptable', 'path' => 'banque', 'label' => 'AccountingMappings', 'singular' => 'AccountingMapping', 'picto' => 'scale-balanced', 'readperms' => array(array('agence', 'compta', 'post'), array('agence', 'parametre', 'write')), 'writeperms' => array(array('agence', 'compta', 'post'))),

		'audit' => array('class' => 'SofAuditLog', 'file' => 'sofauditlog', 'path' => 'audit', 'label' => 'AuditTrail', 'singular' => 'AuditLogEntry', 'picto' => 'fingerprint', 'readperms' => array(array('agence', 'audit', 'read')), 'writeperms' => array(), 'allowcreate' => false, 'allowedit' => false),
		'alerte' => array('class' => 'SofAlerte', 'file' => 'sofalerte', 'path' => 'audit', 'label' => 'Alerts', 'singular' => 'Alert', 'picto' => 'bell', 'readperms' => array(array('agence', 'audit', 'read'), array('agence', 'report', 'read')), 'writeperms' => array(array('agence', 'workflow', 'write'))),
	);
}

/**
 * Get a registry entry.
 *
 * @param string $key Object key
 * @return array<string,mixed>
 */
function agence_get_object_config($key)
{
	$registry = agence_get_object_registry();
	if (empty($registry[$key])) {
		accessforbidden('Unknown SOFITOUL object: '.$key);
	}
	return $registry[$key];
}

/**
 * Get selected object key from request.
 *
 * @param string        $default Default key
 * @param array<int,string> $allowed Allowed keys
 * @return string
 */
function agence_get_requested_object_key($default, $allowed = array())
{
	$key = GETPOST('object', 'alpha');
	if (empty($key)) {
		$key = $default;
	}
	if (!empty($allowed) && !in_array($key, $allowed, true)) {
		$key = $default;
	}
	return $key;
}

/**
 * Test if current user has one of the provided permissions.
 *
 * @param array<int,array<int,string>> $perms Permission tuples
 * @return bool
 */
function agence_user_has_one_permission($perms)
{
	global $user;

	if (empty($perms)) {
		return false;
	}
	foreach ($perms as $perm) {
		if (!empty($perm[0]) && !empty($perm[1]) && !empty($perm[2]) && $user->hasRight($perm[0], $perm[1], $perm[2])) {
			return true;
		}
	}
	return false;
}

/**
 * Load and instantiate a SOFITOUL object.
 *
 * @param array<string,mixed> $config Object config
 * @return SofCommonObject
 */
function agence_new_object($config)
{
	global $db;

	require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/'.$config['file'].'.class.php';
	$class = $config['class'];
	return new $class($db);
}

/**
 * Return card URL for an object.
 *
 * @param string $key Object key
 * @param int    $id  Object id
 * @return string
 */
function agence_object_card_url($key, $id = 0)
{
	$config = agence_get_object_config($key);
	$url = dol_buildpath('/agence/'.$config['path'].'/card.php', 1).'?object='.urlencode($key);
	if ($id > 0) {
		$url .= '&id='.((int) $id);
	}
	return $url;
}

/**
 * Return list URL for an object.
 *
 * @param string $key Object key
 * @return string
 */
function agence_object_list_url($key)
{
	$config = agence_get_object_config($key);
	return dol_buildpath('/agence/'.$config['path'].'/list.php', 1).'?object='.urlencode($key);
}

/**
 * Render object tabs for grouped pages.
 *
 * @param string            $current Current key
 * @param array<int,string> $allowed Allowed keys
 * @return void
 */
function agence_print_object_tabs($current, $allowed)
{
	global $langs;

	if (count($allowed) < 2) {
		return;
	}
	print '<div class="tabs">';
	foreach ($allowed as $key) {
		$config = agence_get_object_config($key);
		$active = ($key === $current) ? ' class="tabactive"' : '';
		print '<a'.$active.' href="'.agence_object_list_url($key).'">'.$langs->trans($config['label']).'</a>';
	}
	print '</div>';
}

/**
 * Return fields visible in lists.
 *
 * @param SofCommonObject $object Object
 * @return array<int,string>
 */
function agence_get_list_fields($object)
{
	$fields = array();
	foreach ($object->fields as $key => $field) {
		if ($key === 'rowid' || $key === 'entity') {
			continue;
		}
		if (empty($field['visible']) || $field['visible'] < 1) {
			continue;
		}
		if (!empty($field['type']) && preg_match('/^text/i', $field['type']) && count($fields) > 3) {
			continue;
		}
		$fields[] = $key;
		if (count($fields) >= 9) {
			break;
		}
	}
	if (!in_array('status', $fields, true) && isset($object->fields['status'])) {
		$fields[] = 'status';
	}
	return $fields;
}

/**
 * Return fields editable in forms.
 *
 * @param SofCommonObject $object Object
 * @return array<string,array<string,mixed>>
 */
function agence_get_form_fields($object)
{
	$fields = array();
	foreach ($object->fields as $key => $field) {
		if (in_array($key, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat', 'fk_user_modif', 'import_key'), true)) {
			continue;
		}
		if (!empty($field['noteditable'])) {
			continue;
		}
		if (isset($field['visible']) && $field['visible'] < 0) {
			continue;
		}
		$fields[$key] = $field;
	}
	return $fields;
}

/**
 * Render generic list page.
 *
 * @param string            $defaultKey Default object key
 * @param array<int,string> $allowed    Allowed keys
 * @return void
 */
function agence_render_object_list_page($defaultKey, $allowed = array())
{
	global $db, $langs;

	$langs->loadLangs(array('agence@agence', 'companies', 'users', 'bills', 'orders', 'banks'));

	$key = agence_get_requested_object_key($defaultKey, $allowed);
	$config = agence_get_object_config($key);
	if (!agence_user_has_one_permission($config['readperms'])) {
		accessforbidden();
	}

	$object = agence_new_object($config);
	$fields = agence_get_list_fields($object);
	$canwrite = agence_user_has_one_permission($config['writeperms']);
	$cancreate = $canwrite && (!isset($config['allowcreate']) || $config['allowcreate']);
	$title = $langs->trans($config['label']);
	$searchAll = trim(GETPOST('search_all', 'restricthtml'));
	$searchStatus = GETPOST('search_status', 'alpha');
	$limit = max(20, (int) getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 50));

	llxHeader('', $title);
	print load_fiche_titre($title, '', $config['picto']);
	agence_print_object_tabs($key, $allowed);

	print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="object" value="'.dol_escape_htmltag($key).'">';
	print '<div class="fichecenter">';
	print '<input type="text" class="flat minwidth300" name="search_all" aria-label="'.dol_escape_htmltag($langs->trans('Search')).'" value="'.dol_escape_htmltag($searchAll).'" placeholder="'.$langs->trans('Search').'">';
	if (isset($object->fields['status'])) {
		print ' <input type="text" class="flat width75" name="search_status" aria-label="'.dol_escape_htmltag($langs->trans('Status')).'" value="'.dol_escape_htmltag($searchStatus).'" placeholder="'.$langs->trans('Status').'">';
	}
	print ' <input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Search').'">';
	print '</div>';
	print '</form>';

	if ($cancreate) {
		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.agence_object_card_url($key).'&action=create">'.$langs->trans('NewObject', $langs->trans($config['singular'])).'</a>';
		print '</div>';
	}

	$sql = 'SELECT t.rowid';
	foreach ($fields as $field) {
		$sql .= ', t.'.$field;
	}
	$sql .= ' FROM '.$db->prefix().$object->table_element.' as t';
	$sql .= ' WHERE 1 = 1';
	if (isset($object->fields['entity'])) {
		$sql .= ' AND t.entity IN ('.getEntity($object->element).')';
	}
	$scopeIds = SofAgenceService::allowedAgencyIds($db, $GLOBALS['user']);
	$sql .= agence_object_scope_sql($object, 't', $scopeIds);
	if ($searchAll !== '') {
		$conditions = array();
		foreach ($object->fields as $fieldKey => $field) {
			if ($fieldKey === 'rowid' || empty($field['type']) || !preg_match('/(varchar|text|email|phone)/i', $field['type'])) {
				continue;
			}
			$conditions[] = 't.'.$fieldKey." LIKE '%".$db->escape($searchAll)."%'";
		}
		if (!empty($conditions)) {
			$sql .= ' AND ('.implode(' OR ', $conditions).')';
		}
	}
	if ($searchStatus !== '' && isset($object->fields['status'])) {
		$sql .= ' AND t.status = '.((int) $searchStatus);
	}
	$sql .= ' ORDER BY t.rowid DESC';
	$sql .= $db->plimit($limit + 1, 0);

	$resql = $db->query($sql);
	if (!$resql) {
		print '<div class="warning">'.$langs->trans('AgenceTablesNotReady').'<br>'.dol_escape_htmltag($db->lasterror()).'</div>';
		llxFooter();
		$db->close();
		return;
	}

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	foreach ($fields as $field) {
		print '<th>'.$langs->trans($object->fields[$field]['label']).'</th>';
	}
	print '<th class="center width75">'.$langs->trans('Action').'</th>';
	print '</tr>';

	$num = $db->num_rows($resql);
	if ($num === 0) {
		print '<tr class="oddeven"><td colspan="'.(count($fields) + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	for ($i = 0; $i < min($num, $limit); $i++) {
		$obj = $db->fetch_object($resql);
		print '<tr class="oddeven">';
		foreach ($fields as $field) {
			print '<td>'.agence_format_field_value($field, isset($obj->$field) ? $obj->$field : null, $object->fields[$field], $key).'</td>';
		}
		print '<td class="center"><a class="reposition" href="'.agence_object_card_url($key, (int) $obj->rowid).'">'.img_picto($langs->trans('View'), 'object_generic').'</a></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	if ($num > $limit) {
		print '<div class="opacitymedium">'.$langs->trans('ListLimitedTo', $limit).'</div>';
	}

	llxFooter();
	$db->close();
}

/**
 * Render generic card page.
 *
 * @param string            $defaultKey Default object key
 * @param array<int,string> $allowed    Allowed keys
 * @return void
 */
function agence_render_object_card_page($defaultKey, $allowed = array())
{
	global $db, $langs, $user;

	$langs->loadLangs(array('agence@agence', 'companies', 'users', 'bills', 'orders', 'banks'));

	$key = agence_get_requested_object_key($defaultKey, $allowed);
	$config = agence_get_object_config($key);
	if (!agence_user_has_one_permission($config['readperms'])) {
		accessforbidden();
	}

	$canwrite = agence_user_has_one_permission($config['writeperms']);
	$cancreate = $canwrite && (!isset($config['allowcreate']) || $config['allowcreate']);
	$canedit = $canwrite && (!isset($config['allowedit']) || $config['allowedit']);
	$object = agence_new_object($config);
	$action = GETPOST('action', 'alpha');
	$id = (int) GETPOST('id', 'int');

	if (($action === 'add' && !$cancreate) || ($action === 'update' && !$canedit)) {
		accessforbidden();
	}

	if (($action === 'add' || $action === 'update') && GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	if ($action === 'business' && $id > 0) {
		if ($object->fetch($id) <= 0) {
			accessforbidden('Record not found');
		}
		agence_enforce_object_scope($object);
		agence_process_business_action($key, $id);
	}

	if ($action === 'add') {
		agence_fill_object_from_post($object);
		agence_enforce_object_scope($object);
		$result = $object->create($user);
		if ($result > 0) {
			SofAgenceService::logAudit($db, $user, 'SOF_OBJECT_CREATE', $object, null, agence_object_snapshot($object));
			setEventMessages($langs->trans('RecordCreated'), null, 'mesgs');
			header('Location: '.agence_object_card_url($key, $result));
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'create';
	}

	if ($action === 'update' && $id > 0) {
		$result = $object->fetch($id);
		if ($result <= 0) {
			accessforbidden('Record not found');
		}
		agence_enforce_object_scope($object);
		if (agence_is_object_locked($object)) {
			setEventMessages($langs->trans('AgenceObjectLocked'), null, 'warnings');
			header('Location: '.agence_object_card_url($key, $id));
			exit;
		}
		$object->oldcopy = dol_clone($object, 2);
		agence_fill_object_from_post($object);
		// Re-check the effective target scope after applying submitted fields.
		// Checking only the stored row would let a scoped writer move a record
		// into another agency by changing fk_agence in the same request.
		agence_enforce_object_scope($object);
		$result = $object->update($user);
		if ($result > 0) {
			SofAgenceService::logAudit($db, $user, 'SOF_OBJECT_UPDATE', $object, agence_object_snapshot($object->oldcopy), agence_object_snapshot($object));
			setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
			header('Location: '.agence_object_card_url($key, $id));
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}

	if ($id > 0 && !in_array($action, array('add', 'update'), true)) {
		$result = $object->fetch($id);
		if ($result <= 0) {
			accessforbidden('Record not found');
		}
		agence_enforce_object_scope($object);
	}

	if ($action === 'create' && !$cancreate) {
		accessforbidden();
	}
	if ($action === 'edit' && (!$canedit || agence_is_object_locked($object))) {
		if (agence_is_object_locked($object)) {
			setEventMessages($langs->trans('AgenceObjectLocked'), null, 'warnings');
		}
		$action = '';
	}

	$title = $langs->trans($config['singular']);
	llxHeader('', $title);
	print load_fiche_titre($title, '', $config['picto']);
	agence_print_object_tabs($key, $allowed);

	if ($action === 'create' || $action === 'edit') {
		agence_print_object_form($key, $object, $action, $config);
	} elseif ($id > 0) {
		agence_print_object_view($key, $object, $config, $canedit);
		agence_print_business_actions($key, $object);
	} else {
		print '<div class="warning">'.$langs->trans('ErrorRecordNotFound').'</div>';
	}

	llxFooter();
	$db->close();
}

/**
 * Fill object from request.
 *
 * @param SofCommonObject $object Object
 * @return void
 */
function agence_fill_object_from_post($object)
{
	global $conf;

	foreach (agence_get_form_fields($object) as $key => $field) {
		$type = empty($field['type']) ? '' : $field['type'];
		if (preg_match('/^(int|integer)/i', $type)) {
			$value = GETPOST($key, 'int');
			$object->$key = ($value === '' ? null : (int) $value);
		} elseif (preg_match('/^(double|real|price)/i', $type)) {
			$object->$key = price2num(GETPOST($key, 'alpha'));
		} elseif ($type === 'boolean') {
			$object->$key = GETPOST($key, 'int') ? 1 : 0;
		} else {
			$value = GETPOST($key, 'restricthtml');
			$object->$key = ($value === '' && empty($field['notnull'])) ? null : $value;
		}
	}
	if (isset($object->fields['entity'])) {
		$object->entity = $conf->entity;
	}
}

/**
 * Print edit form.
 *
 * @param string                  $key    Object key
 * @param SofCommonObject         $object Object
 * @param string                  $action create|edit
 * @param array<string,mixed>     $config Config
 * @return void
 */
function agence_print_object_form($key, $object, $action, $config)
{
	global $langs;

	$form = new Form($object->db);
	$id = !empty($object->id) ? (int) $object->id : (int) $object->rowid;
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="object" value="'.dol_escape_htmltag($key).'">';
	print '<input type="hidden" name="action" value="'.($action === 'create' ? 'add' : 'update').'">';
	if ($id > 0) {
		print '<input type="hidden" name="id" value="'.$id.'">';
	}
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	foreach (agence_get_form_fields($object) as $fieldKey => $field) {
		$value = isset($object->$fieldKey) ? $object->$fieldKey : (isset($field['default']) ? $field['default'] : '');
		print '<tr>';
		print '<td class="titlefieldcreate'.(!empty($field['notnull']) ? ' fieldrequired' : '').'"><label for="'.dol_escape_htmltag($fieldKey).'">'.$langs->trans($field['label']).(!empty($field['notnull']) ? ' <span class="required">*</span>' : '').'</label></td>';
		print '<td>'.agence_render_input_field($form, $fieldKey, $value, $field).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '<div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('Save').'">';
	$cancelUrl = $id > 0 ? agence_object_card_url($key, $id) : agence_object_list_url($key);
	print ' <a class="button button-cancel" href="'.$cancelUrl.'">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	print '</form>';
}

/**
 * Print read-only card.
 *
 * @param string              $key      Object key
 * @param SofCommonObject     $object   Object
 * @param array<string,mixed> $config   Config
 * @param bool                $canwrite Can write
 * @return void
 */
function agence_print_object_view($key, $object, $config, $canwrite)
{
	global $langs;

	$id = !empty($object->id) ? (int) $object->id : (int) $object->rowid;
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	foreach ($object->fields as $fieldKey => $field) {
		if (in_array($fieldKey, array('rowid', 'entity'), true)) {
			continue;
		}
		if (isset($field['visible']) && $field['visible'] < -1) {
			continue;
		}
		$value = isset($object->$fieldKey) ? $object->$fieldKey : null;
		print '<tr>';
		print '<td class="titlefield">'.$langs->trans($field['label']).'</td>';
		print '<td>'.agence_format_field_value($fieldKey, $value, $field, $key).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.agence_object_list_url($key).'">'.$langs->trans('BackToList').'</a>';
	if ($canwrite && !agence_is_object_locked($object)) {
		print '<a class="butAction" href="'.agence_object_card_url($key, $id).'&action=edit">'.$langs->trans('Modify').'</a>';
	}
	print '<a class="butAction" href="'.dol_buildpath('/agence/document.php', 1).'?object='.urlencode($key).'&id='.$id.'&action=builddoc&token='.newToken().'">'.$langs->trans('GeneratePDF').'</a>';
	if (agence_is_object_locked($object)) {
		print '<span class="butActionRefused classfortooltip" title="'.$langs->trans('AgenceObjectLocked').'">'.$langs->trans('Modify').'</span>';
	}
	print '</div>';
}

/**
 * Render an input matching a field definition.
 *
 * @param Form                $form     Form helper
 * @param string              $key      Field key
 * @param mixed               $value    Current value
 * @param array<string,mixed> $field    Field definition
 * @return string
 */
function agence_render_input_field($form, $key, $value, $field)
{
	$type = empty($field['type']) ? 'varchar' : $field['type'];
	$id = dol_escape_htmltag($key);
	$required = !empty($field['notnull']) ? ' required' : '';
	if (!empty($field['arrayofkeyval']) && is_array($field['arrayofkeyval'])) {
		return agence_select_array($key, $value, $field['arrayofkeyval'], !empty($field['notnull']));
	}
	if (preg_match('/User:user/i', $type)) {
		return $form->select_dolusers($value, $key, 1, null, 0, '', '', '', 0, 0, '', 0, '', 'minwidth300');
	}
	if (preg_match('/Societe:societe/i', $type) || $key === 'fk_soc') {
		return $form->select_company($value, $key, '', 1, 0, 0, array(), 0, 'minwidth300');
	}
	if (in_array($key, array('fk_agence', 'fk_caisse', 'fk_session', 'fk_das'), true)) {
		return agence_select_internal_object($key, $value, !empty($field['notnull']));
	}
	if (preg_match('/^text/i', $type)) {
		return '<textarea id="'.$id.'" class="flat centpercent" name="'.$id.'" rows="4"'.$required.'>'.dol_escape_htmltag((string) $value).'</textarea>';
	}
	if (preg_match('/datetime/i', $type)) {
		return '<input id="'.$id.'" type="datetime-local" class="flat minwidth200" name="'.$id.'" value="'.dol_escape_htmltag(agence_value_for_datetime_input($value)).'"'.$required.'>';
	}
	if (preg_match('/^date/i', $type)) {
		return '<input id="'.$id.'" type="date" class="flat minwidth150" name="'.$id.'" value="'.dol_escape_htmltag(agence_value_for_date_input($value)).'"'.$required.'>';
	}
	if (preg_match('/^(int|integer)/i', $type)) {
		return '<input id="'.$id.'" type="number" class="flat width100" name="'.$id.'" value="'.dol_escape_htmltag((string) $value).'"'.$required.'>';
	}
	if (preg_match('/^(double|real|price)/i', $type)) {
		return '<input id="'.$id.'" type="text" class="flat width100 right" name="'.$id.'" value="'.dol_escape_htmltag((string) price2num($value)).'"'.$required.'>';
	}
	if ($type === 'email') {
		return '<input id="'.$id.'" type="email" class="flat minwidth300" name="'.$id.'" value="'.dol_escape_htmltag((string) $value).'"'.$required.'>';
	}
	if ($type === 'phone') {
		return '<input id="'.$id.'" type="tel" class="flat minwidth200" name="'.$id.'" value="'.dol_escape_htmltag((string) $value).'"'.$required.'>';
	}
	return '<input id="'.$id.'" type="text" class="flat minwidth300" name="'.$id.'" value="'.dol_escape_htmltag((string) $value).'"'.$required.'>';
}

/**
 * Render a select from an array.
 *
 * @param string                    $name    Input name
 * @param mixed                     $value   Selected value
 * @param array<int|string,string>  $options Options
 * @return string
 */
function agence_select_array($name, $value, $options, $required = false)
{
	$out = '<select id="'.dol_escape_htmltag($name).'" class="flat minwidth200" name="'.dol_escape_htmltag($name).'"'.($required ? ' required' : '').'>';
	$out .= '<option value="">&nbsp;</option>';
	foreach ($options as $key => $label) {
		$selected = ((string) $key === (string) $value) ? ' selected' : '';
		$out .= '<option value="'.dol_escape_htmltag((string) $key).'"'.$selected.'>'.dol_escape_htmltag((string) $label).'</option>';
	}
	$out .= '</select>';
	return $out;
}

/**
 * Render internal object selector.
 *
 * @param string $name  Field name
 * @param mixed  $value Selected value
 * @return string
 */
function agence_select_internal_object($name, $value, $required = false)
{
	global $db, $langs, $user;

	$map = array(
		'fk_agence' => array('table' => 'sof_agence', 'label' => 'Agency'),
		'fk_caisse' => array('table' => 'sof_caisse', 'label' => 'CashDesk'),
		'fk_session' => array('table' => 'sof_caisse_session', 'label' => 'CashSession'),
		'fk_das' => array('table' => 'sof_das', 'label' => 'DAS'),
	);
	if (empty($map[$name])) {
		return '<input id="'.dol_escape_htmltag($name).'" type="number" class="flat width100" name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag((string) $value).'"'.($required ? ' required' : '').'>';
	}
	$table = $map[$name]['table'];
	$sql = 'SELECT rowid, ref, label FROM '.$db->prefix().$table.' WHERE entity IN ('.getEntity($table).')';
	$scopeIds = SofAgenceService::allowedAgencyIds($db, $user);
	if ($scopeIds !== null && in_array($name, array('fk_agence', 'fk_caisse', 'fk_session'), true)) {
		if (empty($scopeIds)) {
			$sql .= ' AND 1 = 0';
		} elseif ($name === 'fk_agence') {
			$sql .= ' AND rowid IN ('.implode(',', array_map('intval', $scopeIds)).')';
		} else {
			$sql .= ' AND fk_agence IN ('.implode(',', array_map('intval', $scopeIds)).')';
		}
	}
	$sql .= ' ORDER BY ref ASC';
	$resql = $db->query($sql);
	if (!$resql) {
		return '<input id="'.dol_escape_htmltag($name).'" type="number" class="flat width100" name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag((string) $value).'"'.($required ? ' required' : '').'> <span class="opacitymedium">'.$langs->trans('AgenceTablesNotReady').'</span>';
	}
	$out = '<select id="'.dol_escape_htmltag($name).'" class="flat minwidth300" name="'.dol_escape_htmltag($name).'"'.($required ? ' required' : '').'>';
	$out .= '<option value="">&nbsp;</option>';
	while ($obj = $db->fetch_object($resql)) {
		$label = trim($obj->ref.' - '.$obj->label);
		$selected = ((int) $value === (int) $obj->rowid) ? ' selected' : '';
		$out .= '<option value="'.((int) $obj->rowid).'"'.$selected.'>'.dol_escape_htmltag($label).'</option>';
	}
	$out .= '</select>';
	return $out;
}

/**
 * Format a value for list/card output.
 *
 * @param string              $fieldKey Field key
 * @param mixed               $value    Value
 * @param array<string,mixed> $field    Field definition
 * @param string              $objectKey Object key
 * @return string
 */
function agence_format_field_value($fieldKey, $value, $field, $objectKey = '')
{
	global $langs, $db;

	if ($value === null || $value === '') {
		return '<span class="opacitymedium">-</span>';
	}
	if (!empty($field['arrayofkeyval']) && is_array($field['arrayofkeyval']) && isset($field['arrayofkeyval'][$value])) {
		return dol_escape_htmltag($field['arrayofkeyval'][$value]);
	}
	$type = empty($field['type']) ? '' : $field['type'];
	if (preg_match('/^(double|real|price)/i', $type)) {
		return price($value);
	}
	if (preg_match('/datetime/i', $type)) {
		$ts = is_numeric($value) ? (int) $value : $db->jdate($value);
		return $ts ? dol_print_date($ts, 'dayhour') : dol_escape_htmltag((string) $value);
	}
	if (preg_match('/^date/i', $type)) {
		$ts = is_numeric($value) ? (int) $value : $db->jdate($value);
		return $ts ? dol_print_date($ts, 'day') : dol_escape_htmltag((string) $value);
	}
	if (preg_match('/^text/i', $type)) {
		return dol_nl2br(dol_escape_htmltag(dol_trunc((string) $value, 180)));
	}
	if (preg_match('/^(int|integer)/i', $type) && strpos($fieldKey, 'fk_') === 0) {
		return agence_format_foreign_value($fieldKey, (int) $value);
	}
	if ($fieldKey === 'status' || preg_match('/_status$/', $fieldKey)) {
		return '<span class="badge badge-status'.((int) $value).'">'.dol_escape_htmltag((string) $value).'</span>';
	}
	return dol_escape_htmltag((string) $value);
}

/**
 * Format known foreign values.
 *
 * @param string $fieldKey Field key
 * @param int    $value    Foreign id
 * @return string
 */
function agence_format_foreign_value($fieldKey, $value)
{
	global $db;

	if ($value <= 0) {
		return '<span class="opacitymedium">-</span>';
	}
	$standardLinks = array(
		'fk_soc' => array('/societe/card.php?socid=', 'ThirdPartyShort'),
		'fk_facture' => array('/compta/facture/card.php?facid=', 'Invoice'),
		'fk_commande' => array('/commande/card.php?id=', 'Order'),
		'fk_paiement' => array('/compta/paiement/card.php?id=', 'Payment'),
		'fk_bank' => array('/compta/bank/card.php?id=', 'Bank'),
		'fk_product' => array('/product/card.php?id=', 'Product'),
	);
	if (isset($standardLinks[$fieldKey])) {
		return '<a href="'.dol_buildpath($standardLinks[$fieldKey][0].$value, 1).'">#'.$value.'</a>';
	}
	$internalLinks = array(
		'fk_agence' => array('agence', 'sof_agence'),
		'fk_caisse' => array('caisse', 'sof_caisse'),
		'fk_session' => array('session', 'sof_caisse_session'),
		'fk_das' => array('das', 'sof_das'),
	);
	if (isset($internalLinks[$fieldKey])) {
		$label = agence_get_internal_object_label($internalLinks[$fieldKey][1], $value);
		return '<a href="'.agence_object_card_url($internalLinks[$fieldKey][0], $value).'">'.dol_escape_htmltag($label).'</a>';
	}
	if (strpos($fieldKey, 'fk_user') === 0) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$tmpuser = new User($db);
		if ($tmpuser->fetch($value) > 0) {
			return $tmpuser->getNomUrl(1);
		}
	}
	return '#'.((int) $value);
}

/**
 * Return display label for an internal object.
 *
 * @param string $tableElement Table element
 * @param int    $id           Row id
 * @return string
 */
function agence_get_internal_object_label($tableElement, $id)
{
	global $db;
	static $cache = array();

	$key = $tableElement.':'.$id;
	if (isset($cache[$key])) {
		return $cache[$key];
	}
	$sql = 'SELECT rowid, ref, label FROM '.$db->prefix().$tableElement.' WHERE rowid = '.((int) $id);
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$label = trim((!empty($obj->ref) ? $obj->ref : '#'.$obj->rowid).' '.(!empty($obj->label) ? '- '.$obj->label : ''));
		$cache[$key] = $label;
		return $label;
	}
	$cache[$key] = '#'.$id;
	return $cache[$key];
}

/**
 * Return date value suitable for an HTML input.
 *
 * @param mixed $value Value
 * @return string
 */
function agence_value_for_date_input($value)
{
	if (empty($value)) {
		return '';
	}
	$ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
	return $ts ? date('Y-m-d', $ts) : '';
}

/**
 * Return datetime value suitable for an HTML input.
 *
 * @param mixed $value Value
 * @return string
 */
function agence_value_for_datetime_input($value)
{
	if (empty($value)) {
		return '';
	}
	$ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
	return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/**
 * Detect objects that must not be modified from generic screens.
 *
 * @param SofCommonObject $object Object
 * @return bool
 */
function agence_is_object_locked($object)
{
	if ($object->table_element === 'sof_caisse_session' && ((int) $object->status >= 6 || (int) $object->accounting_status >= 3)) {
		return true;
	}
	if (isset($object->accounting_status) && (int) $object->accounting_status >= 4) {
		return true;
	}
	return false;
}

/**
 * Return a compact snapshot for audit.
 *
 * @param SofCommonObject $object Object
 * @return array<string,mixed>
 */
function agence_object_snapshot($object)
{
	$data = array();
	foreach ($object->fields as $key => $field) {
		if ($key === 'tms' || $key === 'date_creation') {
			continue;
		}
		if (isset($object->$key)) {
			$data[$key] = $object->$key;
		}
	}
	return $data;
}

/**
 * Resolve the agency owning an object when its scope is direct or derivable.
 *
 * A null return means that the object is global at entity level and its
 * registry permission remains authoritative.
 *
 * @param SofCommonObject $object Object
 * @return int|null
 */
function agence_object_agency_id($object)
{
	global $db, $conf;

	if ($object->table_element === 'sof_agence') {
		return !empty($object->id) ? (int) $object->id : (int) $object->rowid;
	} elseif (isset($object->fields['fk_agence'])) {
		return (int) $object->fk_agence;
	} elseif (isset($object->fields['fk_agence_followup'])) {
		return (int) $object->fk_agence_followup;
	} elseif (isset($object->fields['fk_session']) && !empty($object->fk_session)) {
		$sql = 'SELECT fk_agence FROM '.$db->prefix().'sof_caisse_session';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND rowid = '.((int) $object->fk_session);
		$resql = $db->query($sql);
		$row = $resql ? $db->fetch_object($resql) : null;
		return $row ? (int) $row->fk_agence : 0;
	} elseif ($object->table_element === 'sof_caisse_validation') {
		return SofAgenceService::validationAgencyId($db, (string) $object->object_type, (int) $object->object_id);
	}
	return null;
}

/** Return the SQL fragment that applies an agency perimeter to a list. */
function agence_object_scope_sql($object, $alias, $scopeIds)
{
	global $db;

	if ($scopeIds === null) {
		return '';
	}
	if (empty($scopeIds)) {
		return ' AND 1 = 0';
	}
	$alias = preg_match('/^[a-z][a-z0-9_]*$/i', (string) $alias) ? (string) $alias : 't';
	$ids = implode(',', array_map('intval', $scopeIds));
	if ($object->table_element === 'sof_agence') {
		return ' AND '.$alias.'.rowid IN ('.$ids.')';
	}
	if (isset($object->fields['fk_agence'])) {
		return ' AND '.$alias.'.fk_agence IN ('.$ids.')';
	}
	if (isset($object->fields['fk_agence_followup'])) {
		return ' AND '.$alias.'.fk_agence_followup IN ('.$ids.')';
	}
	if ($object->table_element === 'sof_caisse_validation') {
		return SofAgenceService::validationScopeSql($db, $alias, $scopeIds);
	}
	if (isset($object->fields['fk_session'])) {
		return ' AND EXISTS (SELECT 1 FROM '.$db->prefix().'sof_caisse_session scope_session WHERE scope_session.rowid = '.$alias.'.fk_session AND scope_session.entity = '.$alias.'.entity AND scope_session.fk_agence IN ('.$ids.'))';
	}
	return '';
}

/** Test access to an object against the supplied user's agency perimeter. */
function agence_user_can_access_object($object, $targetUser)
{
	global $db;

	$scopeIds = SofAgenceService::allowedAgencyIds($db, $targetUser);
	if ($scopeIds === null) {
		return true;
	}
	$agencyId = agence_object_agency_id($object);
	if ($agencyId === null) {
		return true;
	}
	return $agencyId > 0 && in_array($agencyId, $scopeIds, true);
}

/** Reject access to an object outside the current user's agency perimeter. */
function agence_enforce_object_scope($object)
{
	global $user;

	if (!agence_user_can_access_object($object, $user)) {
		accessforbidden('Object outside agency scope');
	}
}
