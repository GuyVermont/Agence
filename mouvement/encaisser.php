<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcaissesession.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$langs->loadLangs(array('agence@agence', 'bills', 'companies', 'banks'));
if (!$user->hasRight('agence', 'mouvement', 'cashin')) {
	accessforbidden();
}

$engine = new SofAgenceOperations($db);
$sessionId = GETPOST('fk_session', 'int');
$invoiceId = GETPOST('fk_facture', 'int');
$action = GETPOST('action', 'alpha');
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

if ($action === 'capture') {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$components = array();
	foreach (array('LIQ', 'CB', 'CHQ', 'VIR', 'OM', 'MM') as $mode) {
		$components[$mode] = GETPOST('pay_'.$mode, 'alpha');
	}
	$result = $engine->captureInvoicePayment($user, $sessionId, $invoiceId, $components, array(
		'amount' => GETPOST('pay_DIFF', 'alpha'),
		'source_type' => GETPOST('deferred_type', 'alpha'),
		'source_id' => GETPOST('source_id', 'int'),
		'due_date' => GETPOST('due_date', 'alpha'),
		'transaction_ref' => GETPOST('transaction_ref', 'alphanohtml'),
	));
	if ($result > 0) {
		setEventMessages($langs->trans('CollectionRecorded'), null, 'mesgs');
		header('Location: '.dol_buildpath('/agence/session/my.php', 1));
		exit;
	}
	setEventMessages($engine->error, $engine->errors, 'errors');
}

$invoice = null;
if ($invoiceId > 0) {
	$invoice = new Facture($db);
	if ($invoice->fetch($invoiceId) <= 0) {
		$invoice = null;
	} elseif ($allowedAgencyIds !== null) {
		$sql = 'SELECT COALESCE(fl.fk_agence,tl.fk_agence) fk_agence FROM '.$db->prefix().'facture f';
		$sql .= ' LEFT JOIN '.$db->prefix().'sof_facture_link fl ON fl.fk_facture=f.rowid AND fl.entity=f.entity';
		$sql .= ' LEFT JOIN '.$db->prefix().'sof_takepos_link tl ON tl.fk_facture=f.rowid AND tl.entity=f.entity AND tl.status=1';
		$sql .= ' WHERE f.rowid='.((int) $invoiceId).' AND f.entity='.((int) $conf->entity).' ORDER BY fl.rowid DESC,tl.rowid DESC LIMIT 1';
		$scopeResult = $db->query($sql);
		$scopeRow = $scopeResult ? $db->fetch_object($scopeResult) : null;
		if ($scopeRow && !empty($scopeRow->fk_agence) && !in_array((int) $scopeRow->fk_agence, $allowedAgencyIds, true)) {
			accessforbidden('Facture hors périmètre agence.');
		}
	}
}

llxHeader('', $langs->trans('CollectInvoice'));
print load_fiche_titre($langs->trans('CollectInvoice'), '', 'money-bill-transfer');
if (!$invoice) {
	$sql = 'SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement, s.nom,';
	$sql .= ' COALESCE((SELECT SUM(pf.amount) FROM '.$db->prefix().'paiement_facture pf WHERE pf.fk_facture = f.rowid),0) paid';
	$sql .= ' FROM '.$db->prefix().'facture f LEFT JOIN '.$db->prefix().'societe s ON s.rowid = f.fk_soc';
	$sql .= ' LEFT JOIN '.$db->prefix().'sof_facture_link fl ON fl.fk_facture=f.rowid AND fl.entity=f.entity';
	$sql .= ' LEFT JOIN '.$db->prefix().'sof_takepos_link tl ON tl.fk_facture=f.rowid AND tl.entity=f.entity AND tl.status=1';
	$sql .= ' WHERE f.entity = '.((int) $conf->entity).' AND f.fk_statut = 1 AND f.paye = 0 AND f.type IN (0,3)';
	if ($allowedAgencyIds !== null) {
		$sql .= empty($allowedAgencyIds)
			? ' AND 1=0'
			: ' AND (COALESCE(fl.fk_agence,tl.fk_agence) IS NULL OR COALESCE(fl.fk_agence,tl.fk_agence) IN ('.implode(',', array_map('intval', $allowedAgencyIds)).'))';
	}
	$sql .= ' ORDER BY f.date_lim_reglement, f.rowid DESC'.$db->plimit(500, 0);
	$resql = $db->query($sql);
	print '<form method="GET"><input type="hidden" name="fk_session" value="'.$sessionId.'"><table class="border centpercent tableforfield"><tr><td class="titlefieldcreate"><label for="fk_facture">'.$langs->trans('UnpaidInvoice').'</label></td><td><select id="fk_facture" class="flat minwidth500" name="fk_facture" required><option value="">'.$langs->trans('Select').'</option>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		$remaining = (float) $row->total_ttc - (float) $row->paid;
		if ($remaining > 0.009) {
			print '<option value="'.((int) $row->rowid).'">'.dol_escape_htmltag($row->ref.' - '.$row->nom.' - '.$langs->trans('RemainingToPay').' '.price($remaining)).'</option>';
		}
	}
	print '</select></td></tr></table><div class="center"><button class="button" type="submit">'.$langs->trans('Continue').'</button></div></form>';
} else {
	$sql = 'SELECT COALESCE(SUM(amount),0) paid FROM '.$db->prefix().'paiement_facture WHERE fk_facture = '.((int) $invoice->id);
	$resql = $db->query($sql);
	$row = $resql ? $db->fetch_object($resql) : null;
	$remaining = max(0, (float) $invoice->total_ttc - ($row ? (float) $row->paid : 0));
	print '<div class="info">'.$langs->trans('Invoice').' <strong>'.dol_escape_htmltag($invoice->ref).'</strong> — '.$langs->trans('RemainingToPay').' : <strong>'.price($remaining).'</strong></div>';
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="capture"><input type="hidden" name="fk_session" value="'.$sessionId.'"><input type="hidden" name="fk_facture" value="'.((int) $invoice->id).'">';
	print '<table class="border centpercent tableforfield">';
	foreach (array('LIQ' => 'CashPayment', 'CB' => 'BankCardPayment', 'CHQ' => 'ChequePayment', 'VIR' => 'BankTransferPayment', 'OM' => 'OrangeMoney', 'MM' => 'MobileMoney') as $mode => $label) {
		print '<tr><td class="titlefield"><label for="pay_'.$mode.'">'.$langs->trans($label).'</label></td><td><input id="pay_'.$mode.'" class="flat" type="number" min="0" step="0.01" name="pay_'.$mode.'" value="0"></td></tr>';
	}
	print '<tr><td><label for="transaction_ref">'.$langs->trans('TransactionRef').'</label></td><td><input id="transaction_ref" class="flat minwidth300" name="transaction_ref"></td></tr>';
	print '<tr><td><label for="pay_DIFF">'.$langs->trans('DeferredShare').'</label></td><td><input id="pay_DIFF" class="flat" type="number" min="0" step="0.01" name="pay_DIFF" value="0"></td></tr>';
	print '<tr><td><label for="deferred_type">'.$langs->trans('DeferredSupportingDocument').'</label></td><td><select id="deferred_type" name="deferred_type"><option value="boncommande">'.$langs->trans('CustomerPurchaseOrder').'</option><option value="bst">'.$langs->trans('BST').'</option><option value="instruction">'.$langs->trans('ManagerInstruction').'</option><option value="other">'.$langs->trans('Other').'</option></select> <label for="source_id">'.$langs->trans('SupportingDocumentId').'</label> <input id="source_id" class="flat width75" type="number" name="source_id"></td></tr>';
	print '<tr><td><label for="due_date">'.$langs->trans('PaymentDueDate').'</label></td><td><input id="due_date" class="flat" type="date" name="due_date"></td></tr>';
	print '</table><div class="center"><button class="button button-save" type="submit">'.$langs->trans('ValidateCollection').'</button></div></form>';
}
llxFooter();
$db->close();
