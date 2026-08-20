<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence.lib.php';

$langs->loadLangs(array('agence@agence', 'bills', 'companies', 'banks'));
if (!$user->hasRight('agence', 'mouvement', 'cashin')) {
	accessforbidden();
}
$engine = new SofAgenceOperations($db);
$sessionId = GETPOST('fk_session', 'int');
if ($sessionId <= 0) {
	$session = $engine->getOpenSessionForUser((int) $user->id);
	$sessionId = $session ? (int) $session->rowid : 0;
}
if ($sessionId <= 0) {
	accessforbidden('Aucune session de caisse ouverte.');
}
$cashSession = new SofCaisseSession($db);
$allowedAgencyIds = SofAgenceService::allowedAgencyIds($db, $user);
if ($cashSession->fetch((int) $sessionId) <= 0 || !in_array((int) $cashSession->status, array(1, 2), true) || (int) $cashSession->freeze_status !== 0
	|| ($allowedAgencyIds !== null && !in_array((int) $cashSession->fk_agence, $allowedAgencyIds, true))
	|| (empty($user->admin) && (int) $cashSession->fk_user_cashier !== (int) $user->id && !$user->hasRight('agence', 'session', 'validate'))) {
	accessforbidden('Session de caisse indisponible ou hors périmètre.');
}

if (GETPOST('action', 'alpha') === 'capture') {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$components = array();
	foreach (array('LIQ', 'CB', 'CHQ', 'VIR', 'OM', 'MM') as $mode) {
		$components[$mode] = GETPOST('pay_'.$mode, 'alpha');
	}
	$invoiceId = $engine->captureCustomerDeposit(
		$user,
		$sessionId,
		GETPOST('fk_soc', 'int'),
		$components,
		GETPOST('label', 'restricthtml'),
		0,
		GETPOST('transaction_ref', 'alphanohtml')
	);
	if ($invoiceId > 0) {
		setEventMessages($langs->trans('CustomerDepositRecorded'), null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invoiceId);
		exit;
	}
	setEventMessages($engine->error, $engine->errors, 'errors');
}

$form = new Form($db);
llxHeader('', $langs->trans('RecordCustomerDeposit'));
print load_fiche_titre($langs->trans('RecordCustomerDeposit'), '', 'hand-holding-dollar');
print '<div class="info">'.$langs->trans('CustomerDepositHelp').'</div>';
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="capture"><input type="hidden" name="fk_session" value="'.$sessionId.'">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Customer').'</td><td>';
print $form->select_company(GETPOST('fk_soc', 'int'), 'fk_soc', '(s.client:in:1,2,3)', 'SelectThirdParty', 0, 0, array(), 0, 'minwidth300');
print '</td></tr>';
print '<tr><td>'.$langs->trans('Label').'</td><td><input class="flat minwidth400" name="label" value="'.dol_escape_htmltag(GETPOST('label', 'restricthtml')).'" placeholder="'.$langs->trans('CustomerDepositDefaultLabel').'"></td></tr>';
foreach (array('LIQ', 'CB', 'CHQ', 'VIR', 'OM', 'MM') as $mode) {
	print '<tr><td>'.dol_escape_htmltag(agence_translate_business_code('payment_mode', $mode)).'</td><td><input class="flat" type="number" min="0" step="0.01" name="pay_'.$mode.'" value="'.dol_escape_htmltag(GETPOST('pay_'.$mode, 'alpha') ?: '0').'"></td></tr>';
}
print '<tr><td>'.$langs->trans('TransactionRef').'</td><td><input class="flat minwidth300" name="transaction_ref" value="'.dol_escape_htmltag(GETPOST('transaction_ref', 'alphanohtml')).'"></td></tr>';
print '</table><div class="center"><a class="button button-cancel" href="'.dol_buildpath('/agence/session/my.php', 1).'">'.$langs->trans('Cancel').'</a> <button class="button button-save" type="submit">'.$langs->trans('RecordCustomerDeposit').'</button></div></form>';
llxFooter();
$db->close();
