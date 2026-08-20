<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence', 'bills', 'companies'));
if (!$user->hasRight('agence', 'remboursement', 'request')) {
	accessforbidden();
}
$engine = new SofAgenceOperations($db);
$preselectedInvoiceId = (int) GETPOST('fk_facture_origin', 'int');
if (GETPOST('action', 'alpha') === 'request') {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$id = $engine->requestRefund($user, array(
		'fk_facture_origin' => GETPOST('fk_facture_origin', 'int'),
		'requested_amount' => GETPOST('requested_amount', 'alpha'),
		'payment_mode' => GETPOST('payment_mode', 'alpha'),
		'reason' => GETPOST('reason', 'restricthtml'),
	));
	if ($id > 0) {
		setEventMessages($langs->trans('RefundRequestRecorded'), null, 'mesgs');
		header('Location: '.dol_buildpath('/agence/remboursement/card.php', 1).'?object=remboursement&id='.$id);
		exit;
	}
	setEventMessages($engine->error, $engine->errors, 'errors');
}

$sql = 'SELECT f.rowid, f.ref, f.total_ttc, s.nom, COALESCE((SELECT SUM(pf.amount) FROM '.$db->prefix().'paiement_facture pf WHERE pf.fk_facture=f.rowid),0) paid';
$sql .= ' FROM '.$db->prefix().'facture f LEFT JOIN '.$db->prefix().'societe s ON s.rowid=f.fk_soc';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_facture_link fl ON fl.fk_facture=f.rowid AND fl.entity=f.entity';
$sql .= ' LEFT JOIN '.$db->prefix().'sof_takepos_link tl ON tl.fk_facture=f.rowid AND tl.entity=f.entity';
$sql .= ' WHERE f.entity = '.((int) $conf->entity).' AND f.fk_statut >= 1 AND f.type IN (0,3)';
$sql .= ' AND COALESCE(fl.fk_agence,tl.fk_agence) IS NOT NULL';
$scopeIds = SofAgenceService::allowedAgencyIds($db, $user);
if ($scopeIds !== null) {
	$sql .= empty($scopeIds) ? ' AND 1=0' : ' AND COALESCE(fl.fk_agence,tl.fk_agence) IN ('.implode(',', array_map('intval', $scopeIds)).')';
}
$sql .= ' ORDER BY f.rowid DESC'.$db->plimit(500, 0);
$resql = $db->query($sql);
llxHeader('', $langs->trans('RequestRefund'));
print load_fiche_titre($langs->trans('RequestRefund'), '', 'hand-holding-dollar');
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="request"><table class="border centpercent tableforfield">';
print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('CollectedInvoice').'</td><td><select class="flat minwidth500" name="fk_facture_origin" required><option value="">'.$langs->trans('Select').'</option>';
while ($resql && ($row = $db->fetch_object($resql))) {
	if ((float) $row->paid > 0) {
		print '<option value="'.((int) $row->rowid).'"'.($preselectedInvoiceId === (int) $row->rowid ? ' selected' : '').'>'.dol_escape_htmltag($row->ref.' - '.$row->nom.' - '.$langs->trans('CollectedAmount').' '.price($row->paid)).'</option>';
	}
}
print '</select></td></tr><tr><td class="fieldrequired">'.$langs->trans('RequestedAmount').'</td><td><input class="flat" type="number" min="0.01" step="0.01" name="requested_amount" required></td></tr>';
print '<tr><td>'.$langs->trans('PaymentMode').'</td><td><select name="payment_mode"><option value="LIQ">'.$langs->trans('CashPayment').'</option><option value="CB">'.$langs->trans('BankCardPayment').'</option><option value="CHQ">'.$langs->trans('ChequePayment').'</option><option value="VIR">'.$langs->trans('BankTransferPayment').'</option><option value="OM">Orange Money</option><option value="MM">Mobile Money</option><option value="AVOIR">'.$langs->trans('CreditNoteOnly').'</option></select></td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('Reason').'</td><td><textarea class="flat centpercent" name="reason" required></textarea></td></tr></table>';
print '<div class="center"><button class="button button-save" type="submit">'.$langs->trans('CreateRequest').'</button></div></form>';
llxFooter();
$db->close();
