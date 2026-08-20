<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/lib/agence_crud.lib.php
 * \ingroup    agence
 * \brief      Generic CRUD helpers for SOFITOUL agency objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnativeintegrationservice.class.php';
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
	global $db, $user;

	if (empty($perms) || !SofAgenceService::isActiveUser($db, $user)) {
		return false;
	}
	if (!empty($user->admin)) {
		return true;
	}
	$user->loadRights('agence', 1);
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
	$nativeDerived = array();
	if ($object->table_element === 'sof_avoir_tracking') {
		// A credit-note follow-up is an enrichment of the native Dolibarr credit
		// note. These values must never be keyed a second time by the operator.
		$nativeDerived = array('ref', 'fk_facture_origin', 'fk_soc', 'initial_amount');
	}
	foreach ($object->fields as $key => $field) {
		if (in_array($key, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat', 'fk_user_modif', 'import_key'), true)) {
			continue;
		}
		if (!empty($field['noteditable'])) {
			continue;
		}
		if (in_array($key, $nativeDerived, true)) {
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
		$statusOptions = !empty($object->fields['status']['arrayofkeyval']) ? $object->fields['status']['arrayofkeyval'] : agence_business_input_options('status', $key);
		print ' <label>'.$langs->trans('Status').' '.agence_select_array('search_status', $searchStatus, $statusOptions, false).'</label>';
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
		$fillResult = agence_fill_object_from_post($object, $key, true);
		if ($fillResult > 0) {
			agence_enforce_object_scope($object);
			$result = $object->create($user);
		} else {
			$result = -1;
		}
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
		$fillResult = agence_fill_object_from_post($object, $key, false);
		// Re-check the effective target scope after applying submitted fields.
		// Checking only the stored row would let a scoped writer move a record
		// into another agency by changing fk_agence in the same request.
		if ($fillResult > 0) {
			agence_enforce_object_scope($object);
			$result = $object->update($user);
		} else {
			$result = -1;
		}
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
	if ($action === 'create') {
		agence_prefill_object_from_get($object);
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
function agence_fill_object_from_post($object, $objectKey = '', $creating = false)
{
	global $conf;

	foreach (agence_get_form_fields($object) as $key => $field) {
		if (in_array($key, array('allowed_das', 'allowed_cashiers', 'allowed_payment_modes', 'payment_modes_allowed'), true)) {
			$values = GETPOST($key, 'array');
			if (!is_array($values)) {
				$values = array_filter(array_map('trim', explode(',', (string) GETPOST($key, 'restricthtml'))));
			}
			if ($key === 'allowed_das' || $key === 'allowed_cashiers') {
				$values = array_values(array_unique(array_filter(array_map('intval', $values))));
			} else {
				$allowedCodes = array('LIQ', 'CB', 'CHQ', 'VIR', 'OM', 'MM', 'AVOIR', 'DIFF');
				$values = array_values(array_intersect($allowedCodes, array_map('strtoupper', $values)));
			}
			$object->$key = empty($values) ? null : implode(',', $values);
			continue;
		}
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
	$native = new SofNativeIntegrationService($object->db);
	if ($native->synchronize($objectKey, $object, $creating) < 0) {
		$object->error = $native->error;
		$object->errors = $native->errors;
		return -1;
	}
	return 1;
}

/** Apply only declared, integer native references passed by a Dolibarr card link. */
function agence_prefill_object_from_get($object)
{
	$allowed = array(
		'fk_soc', 'fk_soc_payer', 'fk_facture', 'fk_facture_origin', 'fk_facture_avoir',
		'fk_commande', 'fk_product', 'fk_contact', 'fk_agence', 'fk_caisse', 'fk_session', 'fk_das',
	);
	foreach ($allowed as $field) {
		if (isset($object->fields[$field]) && GETPOSTISSET($field)) {
			$object->$field = (int) GETPOST($field, 'int');
		}
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
		print '<td>'.agence_render_input_field($form, $fieldKey, $value, $field, $key).'</td>';
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
function agence_render_input_field($form, $key, $value, $field, $objectKey = '')
{
	$type = empty($field['type']) ? 'varchar' : $field['type'];
	$id = dol_escape_htmltag($key);
	$required = !empty($field['notnull']) ? ' required' : '';
	if (!empty($field['arrayofkeyval']) && is_array($field['arrayofkeyval'])) {
		return agence_select_array($key, $value, $field['arrayofkeyval'], !empty($field['notnull']));
	}
	$businessOptions = agence_business_input_options($key, $objectKey);
	if (!empty($businessOptions)) {
		return agence_select_array($key, $value, $businessOptions, !empty($field['notnull']));
	}
	if (preg_match('/User:user/i', $type)) {
		return $form->select_dolusers($value, $key, 1, null, 0, '', '', '', 0, 0, '', 0, '', 'minwidth300');
	}
	if (preg_match('/Societe:societe/i', $type) || $key === 'fk_soc') {
		return $form->select_company($value, $key, '', 1, 0, 0, array(), 0, 'minwidth300');
	}
	if ($key === 'fk_soc_payer') {
		return $form->select_company($value, $key, '', 1, 0, 0, array(), 0, 'minwidth300');
	}
	if (SofNativeIntegrationService::isNativeField($key)) {
		return agence_select_native_object($key, $value, !empty($field['notnull']));
	}
	if (in_array($key, array('fk_agence', 'fk_agence_followup', 'fk_caisse', 'fk_caisse_source', 'fk_caisse_dest', 'fk_caisse_destination', 'fk_session', 'fk_session_source', 'fk_das', 'fk_cloture', 'fk_controle', 'fk_depot_banque', 'fk_mouvement_origin'), true)) {
		return agence_select_internal_object($key, $value, !empty($field['notnull']));
	}
	if ($key === 'allowed_das') {
		return agence_select_multiple_reference($key, $value, 'das');
	}
	if ($key === 'allowed_cashiers') {
		return agence_select_multiple_reference($key, $value, 'users');
	}
	if (in_array($key, array('allowed_payment_modes', 'payment_modes_allowed'), true)) {
		return agence_select_multiple_reference($key, $value, 'payment_modes');
	}
	if ($key === 'country_code') {
		return $form->select_country($value, $key, $required !== '' ? 'required' : '', 0, 'minwidth300', 'code2', empty($field['notnull']) ? 1 : 0);
	}
	if ($key === 'currency_code') {
		return $form->selectCurrency($value, $key, 0, !empty($field['notnull']) ? 0 : 1);
	}
	if (in_array($key, array('account_debit', 'account_credit', 'accountancy_code'), true)) {
		return agence_select_accounting_reference($key, $value, 'account', !empty($field['notnull']));
	}
	if ($key === 'journal_code') {
		return agence_select_accounting_reference($key, $value, 'journal', !empty($field['notnull']));
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
	global $langs;
	$out = '<select id="'.dol_escape_htmltag($name).'" class="flat minwidth200" name="'.dol_escape_htmltag($name).'"'.($required ? ' required' : '').'>';
	$out .= '<option value="">&nbsp;</option>';
	foreach ($options as $key => $label) {
		$selected = ((string) $key === (string) $value) ? ' selected' : '';
		$out .= '<option value="'.dol_escape_htmltag((string) $key).'"'.$selected.'>'.dol_escape_htmltag($langs->trans((string) $label)).'</option>';
	}
	$out .= '</select>';
	return $out;
}

/**
 * Render a readable, entity-scoped selector for a Dolibarr native object.
 * Numeric identifiers are stored internally but are never used as operator labels.
 */
function agence_select_native_object($name, $value, $required = false)
{
	global $db, $conf, $langs;

	$entity = (int) $conf->entity;
	$sql = '';
	if (in_array($name, array('fk_facture', 'fk_facture_origin', 'fk_facture_avoir'), true)) {
		$sql = 'SELECT f.rowid, f.ref, s.nom thirdparty, f.total_ttc amount, f.datef object_date, f.fk_statut object_status FROM '.$db->prefix().'facture f';
		$sql .= ' LEFT JOIN '.$db->prefix().'societe s ON s.rowid=f.fk_soc WHERE f.entity='.$entity;
		if ($name === 'fk_facture_avoir') {
			$sql .= ' AND f.type=2';
		} elseif ($name === 'fk_facture_origin') {
			$sql .= ' AND f.type IN (0,3)';
		}
		$sql .= ' ORDER BY f.datef DESC, f.ref DESC';
	} elseif ($name === 'fk_commande') {
		$sql = 'SELECT c.rowid,c.ref,s.nom thirdparty,c.total_ttc amount,c.date_commande object_date,c.fk_statut object_status FROM '.$db->prefix().'commande c LEFT JOIN '.$db->prefix().'societe s ON s.rowid=c.fk_soc WHERE c.entity='.$entity.' ORDER BY c.date_commande DESC,c.ref DESC';
	} elseif ($name === 'fk_product') {
		$sql = 'SELECT p.rowid,p.ref,p.label thirdparty,0 amount,NULL object_date,p.tosell object_status FROM '.$db->prefix().'product p WHERE p.entity='.$entity.' ORDER BY p.ref';
	} elseif (strpos($name, 'fk_bank_account') === 0) {
		$sql = 'SELECT ba.rowid,ba.ref,ba.label thirdparty,0 amount,NULL object_date,ba.clos object_status FROM '.$db->prefix().'bank_account ba WHERE ba.entity='.$entity.' AND ba.clos=0 ORDER BY ba.label';
	} elseif (in_array($name, array('fk_contact', 'fk_contact_signatory', 'fk_contact_beneficiary'), true)) {
		$fkSoc = (int) GETPOST('fk_soc', 'int');
		if ($fkSoc <= 0) $fkSoc = (int) GETPOST('fk_soc_payer', 'int');
		$sql = 'SELECT sp.rowid,CONCAT(sp.firstname,\' \',sp.lastname) ref,s.nom thirdparty,0 amount,NULL object_date,sp.statut object_status FROM '.$db->prefix().'socpeople sp LEFT JOIN '.$db->prefix().'societe s ON s.rowid=sp.fk_soc WHERE sp.entity='.$entity;
		if ($fkSoc > 0) $sql .= ' AND sp.fk_soc='.$fkSoc;
		$sql .= ' ORDER BY sp.lastname,sp.firstname';
	} elseif (in_array($name, array('fk_paiement', 'fk_paiement_origin'), true)) {
		$sql = 'SELECT DISTINCT p.rowid,COALESCE(p.ref,CONCAT(\'#\',p.rowid)) ref,s.nom thirdparty,p.amount,p.datep object_date,0 object_status FROM '.$db->prefix().'paiement p INNER JOIN '.$db->prefix().'paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN '.$db->prefix().'facture f ON f.rowid=pf.fk_facture LEFT JOIN '.$db->prefix().'societe s ON s.rowid=f.fk_soc WHERE f.entity='.$entity.' ORDER BY p.datep DESC,p.rowid DESC';
	} elseif ($name === 'fk_payment_various') {
		$sql = 'SELECT pv.rowid,COALESCE(pv.ref,CONCAT(\'#\',pv.rowid)) ref,pv.label thirdparty,pv.amount,pv.datep object_date,0 object_status FROM '.$db->prefix().'payment_various pv WHERE pv.entity='.$entity.' ORDER BY pv.datep DESC,pv.rowid DESC';
	} elseif ($name === 'fk_bank') {
		$sql = 'SELECT b.rowid,COALESCE(b.num_releve,CONCAT(\'#\',b.rowid)) ref,CONCAT(ba.label,\' — \',b.label) thirdparty,b.amount,b.dateo object_date,0 object_status FROM '.$db->prefix().'bank b INNER JOIN '.$db->prefix().'bank_account ba ON ba.rowid=b.fk_account WHERE ba.entity='.$entity.' ORDER BY b.dateo DESC,b.rowid DESC';
	}
	if ($sql === '') {
		return '<span class="warning">'.dol_escape_htmltag($langs->trans('NativeSelectorUnavailable')).'</span>';
	}
	$sql .= $db->plimit(1000, 0);
	$resql = $db->query($sql);
	if (!$resql) {
		return '<span class="warning">'.dol_escape_htmltag($langs->trans('NativeSelectorUnavailable')).'</span>';
	}
	$out = '<select id="'.dol_escape_htmltag($name).'" class="flat minwidth300 maxwidth500" name="'.dol_escape_htmltag($name).'"'.($required ? ' required' : '').'>';
	$out .= '<option value="">'.dol_escape_htmltag($langs->trans('SelectExistingNativeObject')).'</option>';
	while ($row = $db->fetch_object($resql)) {
		$parts = array(trim((string) $row->ref));
		if (!empty($row->thirdparty)) $parts[] = trim((string) $row->thirdparty);
		if (!empty($row->object_date)) $parts[] = dol_print_date($db->jdate($row->object_date), 'day');
		if (abs((float) $row->amount) > 0.00001) $parts[] = price(abs((float) $row->amount));
		$out .= '<option value="'.(int) $row->rowid.'"'.((int) $value === (int) $row->rowid ? ' selected' : '').'>'.dol_escape_htmltag(implode(' — ', $parts)).'</option>';
	}
	$out .= '</select>';
	return $out;
}

/** Render multi-selects for configuration values backed by existing references. */
function agence_select_multiple_reference($name, $value, $kind)
{
	global $db, $conf, $langs;
	$selected = array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
	$options = array();
	if ($kind === 'das') {
		$resql = $db->query('SELECT rowid,ref,label FROM '.$db->prefix().'sof_das WHERE entity='.(int) $conf->entity.' AND status=1 ORDER BY ref');
		while ($resql && ($row = $db->fetch_object($resql))) $options[(string) $row->rowid] = trim($row->ref.' — '.$row->label, ' —');
	} elseif ($kind === 'users') {
		$resql = $db->query('SELECT rowid,login,firstname,lastname FROM '.$db->prefix().'user WHERE entity IN (0,'.(int) $conf->entity.') AND statut=1 ORDER BY lastname,firstname,login');
		while ($resql && ($row = $db->fetch_object($resql))) $options[(string) $row->rowid] = trim($row->firstname.' '.$row->lastname).' ('.$row->login.')';
	} else {
		$options = array('LIQ'=>'Cash', 'CB'=>'BankCard', 'CHQ'=>'Cheque', 'VIR'=>'BankTransfer', 'OM'=>'OrangeMoney', 'MM'=>'MobileMoney', 'AVOIR'=>'CreditNote', 'DIFF'=>'DeferredPayment');
	}
	$out = '<select id="'.dol_escape_htmltag($name).'" class="flat minwidth300" name="'.dol_escape_htmltag($name).'[]" multiple size="'.min(8, max(3, count($options))).'">';
	foreach ($options as $key => $label) {
		$text = $kind === 'payment_modes' ? $langs->trans($label) : $label;
		$out .= '<option value="'.dol_escape_htmltag((string) $key).'"'.(in_array((string) $key, $selected, true) ? ' selected' : '').'>'.dol_escape_htmltag($text).'</option>';
	}
	$out .= '</select><div class="opacitymedium">'.dol_escape_htmltag($langs->trans('MultipleSelectionHelp')).'</div>';
	return $out;
}

/** Select an active native accounting account or journal by business code. */
function agence_select_accounting_reference($name, $value, $kind, $required = false)
{
	global $db, $conf, $langs;
	if ($kind === 'journal') {
		$sql = 'SELECT code ref,label FROM '.$db->prefix().'accounting_journal WHERE entity='.(int) $conf->entity.' AND active=1 ORDER BY code';
	} else {
		$sql = 'SELECT account_number ref,label FROM '.$db->prefix().'accounting_account WHERE entity='.(int) $conf->entity.' AND active=1 ORDER BY account_number';
	}
	$resql = $db->query($sql);
	if (!$resql) return '<span class="warning">'.dol_escape_htmltag($langs->trans('NativeSelectorUnavailable')).'</span>';
	$out = '<select id="'.dol_escape_htmltag($name).'" class="flat minwidth300 maxwidth500" name="'.dol_escape_htmltag($name).'"'.($required ? ' required' : '').'><option value="">'.dol_escape_htmltag($langs->trans('Select')).'</option>';
	$found = false;
	while ($row = $db->fetch_object($resql)) {
		if ((string) $row->ref === (string) $value) $found = true;
		$out .= '<option value="'.dol_escape_htmltag($row->ref).'"'.((string) $row->ref === (string) $value ? ' selected' : '').'>'.dol_escape_htmltag($row->ref.' — '.$row->label).'</option>';
	}
	if ($value !== '' && $value !== null && !$found) $out .= '<option value="'.dol_escape_htmltag((string) $value).'" selected>'.dol_escape_htmltag((string) $value.' — '.$langs->trans('LegacyOrExternalValue')).'</option>';
	$out .= '</select>';
	return $out;
}

/** Return translated choices for stored business codes edited by generic forms. */
function agence_business_input_options($fieldKey, $objectKey = '')
{
	$options = array(
		'caisse_type'=>array('cash'=>'PhysicalCashDesk', 'virtual'=>'VirtualCashDesk', 'takepos'=>'TakePOSCashDesk', 'mobile'=>'MobileCashDesk'),
		'session_type'=>array('daily'=>'DailySession', 'exceptional'=>'ExceptionalSession'),
		'trigger_type'=>array('manual'=>'ManualTrigger', 'planned'=>'PlannedTrigger', 'surprise'=>'SurpriseTrigger', 'automatic'=>'AutomaticTrigger'),
		'comptage_type'=>array('opening'=>'OpeningCashCount', 'closing'=>'ClosingCashCount', 'control'=>'ControlCashCount', 'surprise'=>'SurpriseCashCount'),
		'gap_type'=>array('cash'=>'CashGapType', 'card'=>'CardGapType', 'cheque'=>'ChequeGapType', 'transfer'=>'TransferGapType', 'mobile_money'=>'MobileMoneyGapType', 'total'=>'TotalGapType'),
		'severity'=>array('info'=>'InformationSeverity', 'warning'=>'WarningSeverity', 'major'=>'MajorSeverity', 'critical'=>'CriticalSeverity'),
		'scope_type'=>array('global'=>'GlobalScope', 'agency'=>'AgencyScope', 'cashdesk'=>'CashDeskScope', 'das'=>'DASScope', 'user'=>'UserScope'),
		'risk_level'=>array('low'=>'LowRisk', 'normal'=>'NormalRisk', 'medium'=>'MediumRisk', 'high'=>'HighRisk', 'critical'=>'CriticalRisk'),
		'risk_status'=>array('low'=>'LowRisk', 'normal'=>'NormalRisk', 'medium'=>'MediumRisk', 'high'=>'HighRisk', 'critical'=>'CriticalRisk', 'blocked'=>'Blocked'),
		'urgency_level'=>array('low'=>'LowPriority', 'normal'=>'NormalPriority', 'high'=>'HighPriority', 'critical'=>'CriticalPriority'),
		'validation_mode'=>array('sequential'=>'SequentialValidation', 'parallel'=>'ParallelValidation', 'single'=>'SingleValidation'),
		'transfer_type'=>array('cash'=>'CashTransfer', 'vault'=>'VaultTransfer', 'bank'=>'BankTransfer', 'internal'=>'InternalTransfer'),
		'payment_component_type'=>array('real'=>'ActualPaymentComponent', 'deferred'=>'DeferredPaymentComponent', 'credit_note'=>'CreditNotePaymentComponent'),
		'payment_mode'=>array('LIQ'=>'CashPayment', 'CB'=>'BankCardPayment', 'CHQ'=>'ChequePayment', 'VIR'=>'BankTransferPayment', 'OM'=>'OrangeMoney', 'MM'=>'MobileMoney', 'AVOIR'=>'CreditNoteOnly', 'DIFF'=>'DeferredPayment'),
		'cashier_signature_status'=>array('pending'=>'SignaturePending', 'signed'=>'Signed', 'refused'=>'SignatureRefused', 'not_required'=>'SignatureNotRequired'),
	);
	if ($fieldKey !== 'status') return isset($options[$fieldKey]) ? $options[$fieldKey] : array();
	$statusOptions = array(
		'agence'=>array(1=>'Active', 2=>'Suspended', 3=>'Closed', 4=>'Test', 9=>'Archived'),
		'caisse'=>array(0=>'Draft', 1=>'Active', 2=>'Suspended', 9=>'Archived'),
		'das'=>array(0=>'Disabled', 1=>'Active'),
		'session'=>array(0=>'Draft', 1=>'Opened', 2=>'Operating', 3=>'Paused', 4=>'ControlInProgress', 5=>'ClosingInProgress', 6=>'Closed', 7=>'Validated', 8=>'Accounted', 9=>'Canceled', 10=>'Blocked'),
		'paiementdiffere'=>array(0=>'Draft', 1=>'Validated', 2=>'Invoiced', 3=>'PartiallyPaid', 4=>'Paid', 5=>'Late', 6=>'Disputed', 7=>'Closed', 9=>'Canceled'),
		'boncommande'=>array(0=>'Received', 1=>'Checked', 2=>'Used', 3=>'PartiallyUsed', 4=>'Expired', 5=>'Rejected', 6=>'Invoiced', 7=>'Paid'),
		'bst'=>array(0=>'Issued', 1=>'Validated', 2=>'Consumed', 3=>'Invoiced', 4=>'Paid', 9=>'Canceled', 10=>'Disputed'),
		'instruction'=>array(0=>'PendingValidation', 1=>'Accepted', 2=>'Executed', 3=>'Invoiced', 4=>'Paid', 5=>'Rejected', 9=>'Canceled'),
		'avoir'=>array(0=>'PendingValidation', 1=>'PartiallyUsed', 2=>'Consumed', 9=>'Canceled'),
		'ecart'=>array(0=>'Open', 1=>'UnderReview', 2=>'Approved', 3=>'Processed', 9=>'Canceled'),
		'controle'=>array(0=>'Planned', 1=>'ControlInProgress', 2=>'Completed', 9=>'Canceled'),
		'cloture'=>array(0=>'Draft', 1=>'PendingValidation', 2=>'Validated', 3=>'Accounted', 9=>'Canceled'),
		'transfert'=>array(0=>'Draft', 1=>'Sent', 2=>'Received', 9=>'Canceled'),
		'depotbanque'=>array(0=>'Draft', 1=>'Deposited', 2=>'PendingReconciliation', 3=>'Reconciled', 9=>'Canceled'),
		'alerte'=>array(0=>'Open', 1=>'Read', 2=>'Closed'),
		'validation'=>array(0=>'PendingValidation', 1=>'Approved', 2=>'Rejected'),
		'mouvement'=>array(0=>'Canceled', 1=>'Validated'),
		'remboursement'=>array(0=>'Requested', 1=>'PendingValidation', 2=>'Approved', 3=>'Executed', 4=>'Accounted', 8=>'Rejected', 9=>'Canceled'),
	);
	return isset($statusOptions[$objectKey]) ? $statusOptions[$objectKey] : array(0=>'Disabled', 1=>'Active');
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
		'fk_agence' => array('table' => 'sof_agence', 'label' => 'Agency', 'haslabel' => true),
		'fk_agence_followup' => array('table' => 'sof_agence', 'label' => 'Agency', 'haslabel' => true),
		'fk_caisse' => array('table' => 'sof_caisse', 'label' => 'CashDesk', 'haslabel' => true),
		'fk_caisse_source' => array('table' => 'sof_caisse', 'label' => 'CashDesk', 'haslabel' => true),
		'fk_caisse_dest' => array('table' => 'sof_caisse', 'label' => 'CashDesk', 'haslabel' => true),
		'fk_caisse_destination' => array('table' => 'sof_caisse', 'label' => 'CashDesk', 'haslabel' => true),
		'fk_session' => array('table' => 'sof_caisse_session', 'label' => 'CashSession', 'haslabel' => false),
		'fk_session_source' => array('table' => 'sof_caisse_session', 'label' => 'CashSession', 'haslabel' => false),
		'fk_das' => array('table' => 'sof_das', 'label' => 'DAS', 'haslabel' => true),
		'fk_cloture' => array('table' => 'sof_caisse_cloture', 'label' => 'CashClosing', 'haslabel' => false),
		'fk_controle' => array('table' => 'sof_caisse_controle', 'label' => 'SurpriseControl', 'haslabel' => false),
		'fk_depot_banque' => array('table' => 'sof_caisse_depot_banque', 'label' => 'BankDeposit', 'haslabel' => false),
		'fk_mouvement_origin' => array('table' => 'sof_caisse_mouvement', 'label' => 'CashMovement', 'haslabel' => false),
	);
	if (empty($map[$name])) {
		return '<input id="'.dol_escape_htmltag($name).'" type="number" class="flat width100" name="'.dol_escape_htmltag($name).'" value="'.dol_escape_htmltag((string) $value).'"'.($required ? ' required' : '').'>';
	}
	$table = $map[$name]['table'];
	$sql = 'SELECT rowid, ref'.($map[$name]['haslabel'] ? ', label' : '').' FROM '.$db->prefix().$table.' WHERE entity IN ('.getEntity($table).')';
	$scopeIds = SofAgenceService::allowedAgencyIds($db, $user);
	if ($scopeIds !== null && in_array($name, array('fk_agence', 'fk_agence_followup', 'fk_caisse', 'fk_caisse_source', 'fk_caisse_dest', 'fk_caisse_destination', 'fk_session', 'fk_session_source'), true)) {
		if (empty($scopeIds)) {
			$sql .= ' AND 1 = 0';
		} elseif (in_array($name, array('fk_agence', 'fk_agence_followup'), true)) {
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
		$label = trim($obj->ref.($map[$name]['haslabel'] && !empty($obj->label) ? ' - '.$obj->label : ''));
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
		return dol_escape_htmltag($langs->trans($field['arrayofkeyval'][$value]));
	}
	$businessCategories = array(
		'type_operation'=>'operation_type', 'operation_type'=>'operation_type', 'direction'=>'direction', 'payment_mode'=>'payment_mode',
		'severity'=>'severity', 'session_type'=>'session_type', 'connector_type'=>'connector_type', 'channel'=>'channel',
		'decision'=>'decision', 'object_type'=>'object_type', 'source_type'=>'source_type',
		'role_code'=>'role', 'user_role'=>'role', 'role_required'=>'role', 'scope_type'=>'scope_type',
		'caisse_type'=>'cashdesk_type', 'trigger_type'=>'trigger_type', 'comptage_type'=>'count_type', 'gap_type'=>'gap_type',
		'validation_mode'=>'validation_mode', 'urgency_level'=>'urgency', 'risk_level'=>'risk', 'risk_status'=>'risk',
		'transfer_type'=>'transfer_type', 'payment_component_type'=>'payment_component_type', 'pos_source'=>'pos_source',
		'cashier_signature_status'=>'signature_status',
	);
	if (isset($businessCategories[$fieldKey])) {
		return dol_escape_htmltag(agence_translate_business_code($businessCategories[$fieldKey], $value, $objectKey));
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
	if ($fieldKey === 'status') {
		return '<span class="badge badge-status'.((int) $value).'">'.dol_escape_htmltag(agence_translate_business_code('status', $value, $objectKey)).'</span>';
	}
	if (preg_match('/_status$/', $fieldKey)) {
		if ($fieldKey === 'previous_session_status') {
			return '<span class="badge badge-status'.((int) $value).'">'.dol_escape_htmltag(agence_translate_business_code('status', $value, 'session')).'</span>';
		}
		if (in_array($fieldKey, array('accounting_status', 'reconcile_status', 'billing_status', 'validation_status', 'use_status', 'freeze_status'), true)) {
			return '<span class="badge badge-status'.((int) $value).'">'.dol_escape_htmltag(agence_translate_business_code($fieldKey, $value, $objectKey)).'</span>';
		}
		$statusContext = array('accounting_status'=>'AccountingStatusNumber', 'reconcile_status'=>'ReconcileStatusNumber', 'billing_status'=>'BillingStatusNumber', 'validation_status'=>'ValidationStatusNumber', 'use_status'=>'UseStatusNumber', 'freeze_status'=>'FreezeStatusNumber');
		$key = isset($statusContext[$fieldKey]) ? $statusContext[$fieldKey] : 'StatusNumber';
		return '<span class="badge badge-status'.((int) $value).'">'.dol_escape_htmltag($langs->trans($key, (int) $value)).'</span>';
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
	global $db, $langs;

	if ($value <= 0) {
		return '<span class="opacitymedium">-</span>';
	}
	$standardLinks = array(
		'fk_soc' => array('/societe/card.php?socid=', 'societe', array('nom')),
		'fk_soc_payer' => array('/societe/card.php?socid=', 'societe', array('nom')),
		'fk_facture' => array('/compta/facture/card.php?facid=', 'facture', array('ref')),
		'fk_facture_origin' => array('/compta/facture/card.php?facid=', 'facture', array('ref')),
		'fk_facture_avoir' => array('/compta/facture/card.php?facid=', 'facture', array('ref')),
		'fk_commande' => array('/commande/card.php?id=', 'commande', array('ref')),
		'fk_paiement' => array('/compta/paiement/card.php?id=', 'paiement', array('ref')),
		'fk_paiement_origin' => array('/compta/paiement/card.php?id=', 'paiement', array('ref')),
		'fk_payment_various' => array('/compta/bank/various_payment/card.php?id=', 'payment_various', array('ref', 'label')),
		'fk_bank' => array('/compta/bank/line.php?rowid=', 'bank', array('label')),
		'fk_product' => array('/product/card.php?id=', 'product', array('ref', 'label')),
		'fk_bank_account' => array('/compta/bank/card.php?id=', 'bank_account', array('ref', 'label')),
		'fk_bank_account_card' => array('/compta/bank/card.php?id=', 'bank_account', array('ref', 'label')),
		'fk_bank_account_cheque' => array('/compta/bank/card.php?id=', 'bank_account', array('ref', 'label')),
		'fk_bank_account_mobile' => array('/compta/bank/card.php?id=', 'bank_account', array('ref', 'label')),
		'fk_bank_account_other' => array('/compta/bank/card.php?id=', 'bank_account', array('ref', 'label')),
		'fk_contact' => array('/contact/card.php?id=', 'socpeople', array('firstname', 'lastname')),
		'fk_contact_signatory' => array('/contact/card.php?id=', 'socpeople', array('firstname', 'lastname')),
		'fk_contact_beneficiary' => array('/contact/card.php?id=', 'socpeople', array('firstname', 'lastname')),
	);
	if (isset($standardLinks[$fieldKey])) {
		$meta = $standardLinks[$fieldKey];
		$label = agence_get_standard_object_label($meta[1], $meta[2], $value);
		return '<a href="'.dol_buildpath($meta[0].$value, 1).'">'.dol_escape_htmltag($label).'</a>';
	}
	$internalLinks = array(
		'fk_agence' => array('agence', 'sof_agence', true),
		'fk_agence_followup' => array('agence', 'sof_agence', true),
		'fk_caisse' => array('caisse', 'sof_caisse', true),
		'fk_caisse_source' => array('caisse', 'sof_caisse', true),
		'fk_caisse_dest' => array('caisse', 'sof_caisse', true),
		'fk_caisse_destination' => array('caisse', 'sof_caisse', true),
		'fk_session' => array('session', 'sof_caisse_session', false),
		'fk_session_source' => array('session', 'sof_caisse_session', false),
		'fk_das' => array('das', 'sof_das', true),
		'fk_cloture' => array('cloture', 'sof_caisse_cloture', false),
		'fk_controle' => array('controle', 'sof_caisse_controle', false),
		'fk_depot_banque' => array('depotbanque', 'sof_caisse_depot_banque', false),
		'fk_mouvement_origin' => array('mouvement', 'sof_caisse_mouvement', true),
	);
	if (isset($internalLinks[$fieldKey])) {
		$label = agence_get_internal_object_label($internalLinks[$fieldKey][1], $value, $internalLinks[$fieldKey][2]);
		return '<a href="'.agence_object_card_url($internalLinks[$fieldKey][0], $value).'">'.dol_escape_htmltag($label).'</a>';
	}
	if (strpos($fieldKey, 'fk_user') === 0) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$tmpuser = new User($db);
		if ($tmpuser->fetch($value) > 0) {
			return $tmpuser->getNomUrl(1);
		}
	}
	return dol_escape_htmltag($langs->trans('ReferenceNumber', (int) $value));
}

/**
 * Return a human-readable label for a standard Dolibarr object.
 *
 * @param string            $tableElement Table element
 * @param array<int,string> $columns      Display columns
 * @param int               $id           Row id
 * @return string
 */
function agence_get_standard_object_label($tableElement, $columns, $id)
{
	global $db, $langs;
	static $cache = array();

	$key = $tableElement.':'.$id;
	if (isset($cache[$key])) {
		return $cache[$key];
	}
	$allowedTables = array('societe', 'facture', 'commande', 'paiement', 'payment_various', 'bank', 'product', 'bank_account', 'socpeople');
	$allowedColumns = array('nom', 'ref', 'label', 'firstname', 'lastname');
	if (!in_array($tableElement, $allowedTables, true) || array_diff($columns, $allowedColumns)) {
		return $langs->trans('ReferenceNumber', (int) $id);
	}
	$sql = 'SELECT '.implode(', ', $columns).' FROM '.$db->prefix().$tableElement.' WHERE rowid = '.((int) $id);
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$parts = array();
		foreach ($columns as $column) {
			if (isset($obj->{$column}) && trim((string) $obj->{$column}) !== '') {
				$parts[] = trim((string) $obj->{$column});
			}
		}
		if (!empty($parts)) {
			$cache[$key] = implode(' - ', $parts);
			return $cache[$key];
		}
	}
	$cache[$key] = $langs->trans('ReferenceNumber', (int) $id);
	return $cache[$key];
}

/**
 * Return display label for an internal object.
 *
 * @param string $tableElement Table element
 * @param int    $id           Row id
 * @return string
 */
function agence_get_internal_object_label($tableElement, $id, $hasLabel = true)
{
	global $db, $langs;
	static $cache = array();

	$key = $tableElement.':'.$id;
	if (isset($cache[$key])) {
		return $cache[$key];
	}
	$allowedTables = array('sof_agence', 'sof_caisse', 'sof_caisse_session', 'sof_das', 'sof_caisse_cloture', 'sof_caisse_controle', 'sof_caisse_depot_banque', 'sof_caisse_mouvement');
	if (!in_array($tableElement, $allowedTables, true)) {
		return $langs->trans('ReferenceNumber', (int) $id);
	}
	$sql = 'SELECT rowid, ref'.($hasLabel ? ', label' : '').' FROM '.$db->prefix().$tableElement.' WHERE rowid = '.((int) $id);
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$reference = !empty($obj->ref) ? $obj->ref : $langs->trans('ReferenceNumber', (int) $obj->rowid);
		$label = trim($reference.' '.($hasLabel && !empty($obj->label) ? '- '.$obj->label : ''));
		$cache[$key] = $label;
		return $label;
	}
	$cache[$key] = $langs->trans('ReferenceNumber', (int) $id);
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
