<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * Security regression checks for agency-scope enforcement.
 *
 * The database fixtures are created inside a transaction and always rolled
 * back.  No production credential or persistent account is required.
 */

if (PHP_SAPI !== 'cli') {
	die("CLI only\n");
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOSESSION', 1);

$htdocs = dirname(__DIR__, 3);
chdir($htdocs);
require $htdocs.'/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagence.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceuser.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaisse.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissevalidation.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofdas.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/softakeposlink.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';

$errors = array();
function agence_security_assert($condition, $label)
{
	global $errors;
	echo ($condition ? '[OK] ' : '[KO] ').$label.PHP_EOL;
	if (!$condition) {
		$errors[] = $label;
	}
}

// CLI bootstrap may not select a user. Load the first administrator for
// fixture creation, then clone it as a non-admin authorization subject.
if (empty($GLOBALS['user']->id)) {
	$resql = $db->query('SELECT rowid FROM '.$db->prefix().'user WHERE admin = 1 AND statut = 1 ORDER BY rowid LIMIT 1');
	$row = $resql ? $db->fetch_object($resql) : null;
	if ($row) {
		$GLOBALS['user']->fetch((int) $row->rowid);
	}
}
$fixtureAdmin = $GLOBALS['user'];
$fixtureAdmin->getrights('', 1);
agence_security_assert(!empty($fixtureAdmin->id) && !empty($fixtureAdmin->admin), 'administrator available for isolated fixtures');

$expectedSettingKeys = array(
	'AGENCE_ENABLE_TRANSVERSAL_SCOPE',
	'AGENCE_ENABLE_AUDIT_TRAIL',
	'AGENCE_ENABLE_REPORTING',
	'AGENCE_REQUIRE_OPEN_SESSION',
	'AGENCE_MAX_SESSION_HOURS',
	'AGENCE_GAP_MAJOR_AMOUNT',
	'AGENCE_GAP_CRITICAL_AMOUNT',
	'AGENCE_TAKEPOS_MAX_DISCOUNT_PCT',
	'AGENCE_DEPOSIT_ALERT_DAYS',
	'AGENCE_CASH_DENOMINATIONS',
	'AGENCE_ALLOW_SELF_APPROVAL',
);
$settingDefinitions = function_exists('agence_get_settings_definition') ? agence_get_settings_definition() : array();
agence_security_assert(array_keys($settingDefinitions) === $expectedSettingKeys, 'setup exposes an exact allowlist of Agence constants');
$setupSource = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/agence/admin/setup.php');
$setupDefinitionPos = strpos($setupSource, '$settings = agence_get_settings_definition();');
$setupValidationPos = strpos($setupSource, 'agence_validate_setting_update(');
$setupWritePos = strpos($setupSource, 'dolibarr_set_const(');
agence_security_assert(
	$setupDefinitionPos !== false && $setupValidationPos !== false && $setupWritePos !== false && $setupDefinitionPos < $setupValidationPos && $setupValidationPos < $setupWritePos,
	'setup validates the allowlisted value before the Dolibarr constant write sink'
);
$normalizedSetting = null;
$settingError = '';
$unknownSettingRejected = function_exists('agence_validate_setting_update')
	? !agence_validate_setting_update('MAIN_FEATURES_LEVEL', '2', array(), $normalizedSetting, $settingError)
	: false;
agence_security_assert($unknownSettingRejected, 'setup validator rejects a Dolibarr constant outside the Agence allowlist');
$settingError = '';
$invalidDiscountRejected = function_exists('agence_validate_setting_update')
	? !agence_validate_setting_update('AGENCE_TAKEPOS_MAX_DISCOUNT_PCT', '101', array(), $normalizedSetting, $settingError)
	: false;
agence_security_assert($invalidDiscountRejected, 'setup validator enforces the discount percentage bounds');
$settingError = '';
$inconsistentThresholdRejected = function_exists('agence_validate_setting_update')
	? !agence_validate_setting_update('AGENCE_GAP_MAJOR_AMOUNT', '2000', array('AGENCE_GAP_CRITICAL_AMOUNT' => '1000'), $normalizedSetting, $settingError)
	: false;
agence_security_assert($inconsistentThresholdRejected, 'setup validator rejects a major gap threshold above the critical threshold');
$settingError = '';
$validDenominationsAccepted = function_exists('agence_validate_setting_update')
	? agence_validate_setting_update('AGENCE_CASH_DENOMINATIONS', '10000, 5000, 1000', array(), $normalizedSetting, $settingError)
	: false;
agence_security_assert($validDenominationsAccepted && $normalizedSetting === '10000,5000,1000', 'setup validator canonicalizes valid cash denominations');

$source = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php');
$updateStart = strpos($source, "if (\$action === 'update' && \$id > 0)");
$fillAfterFetch = $updateStart === false ? false : strpos($source, 'agence_fill_object_from_post($object);', $updateStart);
$enforceAfterFill = $fillAfterFetch === false ? false : strpos($source, 'agence_enforce_object_scope($object);', $fillAfterFetch);
$updateCall = $fillAfterFetch === false ? false : strpos($source, '$object->update($user)', $fillAfterFetch);
agence_security_assert(
	$fillAfterFetch !== false && $enforceAfterFill !== false && $updateCall !== false && $enforceAfterFill < $updateCall,
	'generic update revalidates scope after applying posted agency fields'
);

$viewFetchStart = strpos($source, "if (\$id > 0 && !in_array(\$action, array('add', 'update'), true))");
$nextScopeGuard = $viewFetchStart === false ? false : strpos($source, 'agence_enforce_object_scope($object);', $viewFetchStart);
$viewRendering = $viewFetchStart === false ? false : strpos($source, "if (\$action === 'create'", $viewFetchStart);
agence_security_assert(
	$nextScopeGuard !== false && $viewRendering !== false && $nextScopeGuard < $viewRendering,
	'generic object view enforces scope for agency records and derived scopes'
);

$documentSource = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/agence/document.php');
agence_security_assert(
	strpos($documentSource, 'agence_enforce_object_scope($object);') !== false,
	'PDF generation uses the canonical object-scope guard'
);

if (!function_exists('agence_user_can_access_object')) {
	agence_security_assert(false, 'canonical object-scope predicate is available');
	echo 'Security regression check failed: '.count($errors).' error(s).'.PHP_EOL;
	exit(1);
}

$token = date('YmdHis').mt_rand(100, 999);
$db->begin();

$agencyA = new SofAgence($db);
$agencyA->entity = (int) $conf->entity;
$agencyA->ref = 'TEST-SCOPE-A-'.$token;
$agencyA->label = 'Scope regression agency A';
$agencyA->country_code = 'CM';
$agencyA->status = SofAgence::STATUS_ACTIVE;
$agencyAId = $agencyA->create($fixtureAdmin, 1);

$agencyB = new SofAgence($db);
$agencyB->entity = (int) $conf->entity;
$agencyB->ref = 'TEST-SCOPE-B-'.$token;
$agencyB->label = 'Scope regression agency B';
$agencyB->country_code = 'CM';
$agencyB->status = SofAgence::STATUS_ACTIVE;
$agencyBId = $agencyB->create($fixtureAdmin, 1);

$dasAllowed = new SofDas($db);
$dasAllowed->entity = (int) $conf->entity;
$dasAllowed->ref = 'TEST-SCOPE-DAS-A-'.$token;
$dasAllowed->label = 'Allowed scope regression DAS';
$dasAllowed->status = SofDas::STATUS_ACTIVE;
$dasAllowedId = $dasAllowed->create($fixtureAdmin, 1);

$dasDenied = new SofDas($db);
$dasDenied->entity = (int) $conf->entity;
$dasDenied->ref = 'TEST-SCOPE-DAS-B-'.$token;
$dasDenied->label = 'Disallowed scope regression DAS';
$dasDenied->status = SofDas::STATUS_ACTIVE;
$dasDeniedId = $dasDenied->create($fixtureAdmin, 1);

$scopedUser = clone $fixtureAdmin;
$scopedUser->admin = 0;
$db->query('DELETE FROM '.$db->prefix().'sof_role_transversal WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $scopedUser->id));
$db->query('DELETE FROM '.$db->prefix().'sof_agence_user WHERE entity = '.((int) $conf->entity).' AND fk_user = '.((int) $scopedUser->id));

$scope = new SofAgenceUser($db);
$scope->entity = (int) $conf->entity;
$scope->fk_agence = (int) $agencyAId;
$scope->fk_user = (int) $scopedUser->id;
$scope->role_code = 'scope_regression';
$scope->scope_type = 'agency';
$scope->status = 1;
$scopeId = $scope->create($fixtureAdmin, 1);

$cashDesk = new SofCaisse($db);
$cashDesk->entity = (int) $conf->entity;
$cashDesk->fk_agence = (int) $agencyAId;
$cashDesk->ref = 'TEST-SCOPE-CAI-'.$token;
$cashDesk->label = 'Scope regression cash desk';
$cashDesk->caisse_type = 'cash';
$cashDesk->currency_code = 'XAF';
$cashDesk->allowed_das = (string) $dasAllowedId;
$cashDesk->status = SofCaisse::STATUS_ACTIVE;
$cashDeskId = $cashDesk->create($fixtureAdmin, 1);

$cashDeskB = new SofCaisse($db);
$cashDeskB->entity = (int) $conf->entity;
$cashDeskB->fk_agence = (int) $agencyBId;
$cashDeskB->ref = 'TEST-SCOPE-CBI-'.$token;
$cashDeskB->label = 'Out-of-scope regression cash desk';
$cashDeskB->caisse_type = 'cash';
$cashDeskB->currency_code = 'XAF';
$cashDeskB->status = SofCaisse::STATUS_ACTIVE;
$cashDeskBId = $cashDeskB->create($fixtureAdmin, 1);

$sessionB = new SofCaisseSession($db);
$sessionB->entity = (int) $conf->entity;
$sessionB->ref = 'TEST-SCOPE-SES-'.$token;
$sessionB->fk_agence = (int) $agencyBId;
$sessionB->fk_caisse = (int) $cashDeskBId;
$sessionB->fk_user_cashier = (int) $fixtureAdmin->id;
$sessionB->session_type = 'daily';
$sessionB->date_opening = dol_now();
$sessionB->opening_amount = 0;
$sessionB->theoretical_amount = 0;
$sessionB->physical_amount = 0;
$sessionB->gap_amount = 0;
$sessionB->accounting_status = 0;
$sessionB->freeze_status = 0;
$sessionB->status = SofCaisseSession::STATUS_CLOSED;
$sessionBId = $sessionB->create($fixtureAdmin, 1);

$validationB = new SofCaisseValidation($db);
$validationB->entity = (int) $conf->entity;
$validationB->ref = 'TEST-SCOPE-VAL-'.$token;
$validationB->object_type = 'session';
$validationB->object_id = (int) $sessionBId;
$validationB->workflow_code = 'TEST-SCOPE';
$validationB->validation_level = 1;
$validationB->validation_mode = 'sequential';
$validationB->fk_user_requester = (int) $fixtureAdmin->id;
$validationB->role_required = 'scope_regression';
$validationB->date_request = dol_now();
$validationB->status = 0;
$validationBId = $validationB->create($fixtureAdmin, 1);

agence_security_assert($agencyAId > 0 && $agencyBId > 0 && $dasAllowedId > 0 && $dasDeniedId > 0 && $scopeId > 0 && $cashDeskId > 0 && $cashDeskBId > 0 && $sessionBId > 0 && $validationBId > 0, 'isolated scope fixtures created');

$crossAgencyMapping = new SofTakeposLink($db);
$crossAgencyMapping->entity = (int) $conf->entity;
$crossAgencyMapping->terminal_ref = 'TEST-CROSS-AGENCY-'.$token;
$crossAgencyMapping->fk_agence = (int) $agencyAId;
$crossAgencyMapping->fk_caisse = (int) $cashDeskBId;
$crossAgencyMapping->pos_source = 'security_regression';
$crossAgencyMapping->status = 1;
agence_security_assert($crossAgencyMapping->create($fixtureAdmin, 1) < 0, 'object boundary rejects a cash desk belonging to another agency');

$disallowedDasMapping = new SofTakeposLink($db);
$disallowedDasMapping->entity = (int) $conf->entity;
$disallowedDasMapping->terminal_ref = 'TEST-DENIED-DAS-'.$token;
$disallowedDasMapping->fk_agence = (int) $agencyAId;
$disallowedDasMapping->fk_caisse = (int) $cashDeskId;
$disallowedDasMapping->fk_das = (int) $dasDeniedId;
$disallowedDasMapping->pos_source = 'security_regression';
$disallowedDasMapping->status = 1;
agence_security_assert($disallowedDasMapping->create($fixtureAdmin, 1) < 0, 'object boundary rejects a DAS outside the cash desk allowlist');

$validMapping = new SofTakeposLink($db);
$validMapping->entity = (int) $conf->entity;
$validMapping->terminal_ref = 'TEST-VALID-CONTEXT-'.$token;
$validMapping->fk_agence = (int) $agencyAId;
$validMapping->fk_caisse = (int) $cashDeskId;
$validMapping->fk_das = (int) $dasAllowedId;
$validMapping->pos_source = 'security_regression';
$validMapping->status = 1;
$validMappingId = $validMapping->create($fixtureAdmin, 1);
agence_security_assert($validMappingId > 0, 'object boundary preserves a valid agency, cash desk and DAS context');
$allowedAgencyIds = SofAgenceService::allowedAgencyIds($db, $scopedUser);
agence_security_assert(
	is_array($allowedAgencyIds) && in_array((int) $agencyAId, $allowedAgencyIds, true),
	'assigned agency is resolved from the active scope fixture'
);
agence_security_assert(agence_user_can_access_object($agencyA, $scopedUser), 'scoped user can read the assigned agency');
agence_security_assert(!agence_user_can_access_object($agencyB, $scopedUser), 'scoped user cannot read another agency by direct id');
agence_security_assert(agence_user_can_access_object($cashDesk, $scopedUser), 'scoped user can read an object in the assigned agency');
$cashDesk->fk_agence = (int) $agencyBId;
agence_security_assert(!agence_user_can_access_object($cashDesk, $scopedUser), 'posted agency reassignment is rejected before update');
agence_security_assert(
	!SofAgenceService::userCanAccessValidation($db, $scopedUser, 'session', $sessionBId),
	'cross-agency workflow decision is rejected'
);
agence_security_assert(!agence_user_can_access_object($validationB, $scopedUser), 'validation card derives and enforces its target agency');
$validationScopeSql = 'SELECT t.rowid FROM '.$db->prefix().'sof_caisse_validation t WHERE t.rowid = '.((int) $validationBId);
$validationScopeSql .= agence_object_scope_sql($validationB, 't', $allowedAgencyIds);
$validationScopeResult = $db->query($validationScopeSql);
agence_security_assert($validationScopeResult && $db->num_rows($validationScopeResult) === 0, 'validation list excludes another agency target');

$operations = new SofAgenceOperations($db);
$crossRelationUpdate = $operations->updateRow('sof_takepos_link', (int) $validMappingId, array('fk_caisse' => (int) $cashDeskBId), $fixtureAdmin);
agence_security_assert($crossRelationUpdate < 0, 'typed update helper rejects a cross-agency cash desk reassignment');
$countResult = $operations->saveCashCount($scopedUser, (int) $sessionBId, array(1000 => 1), 'closing');
agence_security_assert(
	$countResult < 0 && strpos($operations->error, 'périmètre agence') !== false,
	'business-service boundary rejects a direct cross-agency cash-count call'
);

$takePosAjaxSource = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/agence/ajax/check_takepos_session.php');
agence_security_assert(
	strpos($takePosAjaxSource, 'SofAgenceService::allowedAgencyIds($db, $user)') !== false,
	'TakePOS session probe enforces the current user agency perimeter'
);

$refs = array();
for ($i = 0; $i < 1000; $i++) {
	$refs[] = $operations->generateRef('TST', 'sof_caisse_session');
}
agence_security_assert(count(array_unique($refs)) === count($refs), 'high-entropy references remain unique in a same-second burst');

$firstStateChange = $operations->updateRow('sof_caisse', (int) $cashDeskId, array('status' => 0), $fixtureAdmin, SofCaisse::STATUS_ACTIVE);
$staleStateChange = $operations->updateRow('sof_caisse', (int) $cashDeskId, array('status' => SofCaisse::STATUS_ACTIVE), $fixtureAdmin, SofCaisse::STATUS_ACTIVE);
agence_security_assert($firstStateChange > 0 && $staleStateChange < 0, 'optimistic status guard rejects a stale concurrent state change');
agence_security_assert($operations->updateRow('user', (int) $fixtureAdmin->id, array('admin' => 0), $fixtureAdmin) < 0, 'generic update helper rejects non-module tables');

$db->rollback();

echo empty($errors) ? "Security regression check completed successfully.\n" : 'Security regression check failed: '.count($errors).' error(s).'.PHP_EOL;
exit(empty($errors) ? 0 : 1);
