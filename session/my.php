<?php
/* Copyright (C) 2026 SOFITOUL */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence', 'banks'));
if (!$user->hasRight('agence', 'session', 'open') && !$user->hasRight('agence', 'mouvement', 'cashin')) {
	accessforbidden();
}

$engine = new SofAgenceOperations($db);
$action = GETPOST('action', 'alpha');
if ($action === 'open') {
	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	$result = $engine->openSession(
		$user,
		GETPOST('fk_caisse', 'int'),
		GETPOST('opening_amount', 'alpha'),
		GETPOST('session_type', 'alpha'),
		GETPOST('fk_das', 'int'),
		GETPOST('note', 'restricthtml')
	);
	if ($result > 0) {
		setEventMessages('Session ouverte avec succès.', null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages($engine->error, $engine->errors, 'errors');
}

$active = $engine->getOpenSessionForUser((int) $user->id);
llxHeader('', 'Ma caisse');
print load_fiche_titre('Ma caisse', '', 'cash-register');

if ($active) {
	$sql = 'SELECT c.ref caisse_ref, c.label caisse_label, a.ref agence_ref FROM '.$db->prefix().'sof_caisse c';
	$sql .= ' LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid = c.fk_agence WHERE c.rowid = '.((int) $active->fk_caisse);
	$resql = $db->query($sql);
	$context = $resql ? $db->fetch_object($resql) : null;
	print '<div class="info">Session active <strong>'.dol_escape_htmltag($active->ref).'</strong> — '.dol_escape_htmltag($context ? $context->caisse_ref.' '.$context->caisse_label : '').'</div>';
	print '<div class="fichecenter"><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">Ouverture</td><td>'.dol_print_date($db->jdate($active->date_opening), 'dayhour').'</td></tr>';
	print '<tr><td>Fonds initial</td><td>'.price($active->opening_amount).'</td></tr>';
	print '<tr><td>Solde espèces théorique</td><td><strong>'.price($active->theoretical_amount).'</strong></td></tr>';
	print '<tr><td>Statut</td><td>'.((int) $active->status).'</td></tr>';
	print '</table></div>';
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.dol_buildpath('/agence/mouvement/encaisser.php', 1).'?fk_session='.(int) $active->rowid.'">Encaisser une facture</a>';
	print '<a class="butAction" href="'.dol_buildpath('/agence/mouvement/acompte.php', 1).'?fk_session='.(int) $active->rowid.'">Enregistrer un acompte</a>';
	print '<a class="butAction" href="'.dol_buildpath('/agence/session/card.php', 1).'?id='.(int) $active->rowid.'">Gérer / clôturer</a>';
	print '<a class="butAction" href="'.dol_buildpath('/agence/remboursement/list.php', 1).'">Remboursements</a>';
	print '</div>';

	$sql = 'SELECT rowid, ref, transaction_date, type_operation, direction, payment_mode, amount, label';
	$sql .= ' FROM '.$db->prefix().'sof_caisse_mouvement WHERE fk_session = '.((int) $active->rowid).' ORDER BY rowid DESC'.$db->plimit(30, 0);
	$resql = $db->query($sql);
	print load_fiche_titre('Derniers mouvements', '', 'money-bill-transfer');
	print '<div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>Référence</th><th>Date</th><th>Type</th><th>Mode</th><th>Sens</th><th class="right">Montant</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td><a href="'.dol_buildpath('/agence/mouvement/card.php', 1).'?object=mouvement&id='.(int) $row->rowid.'">'.dol_escape_htmltag($row->ref).'</a></td>';
		print '<td>'.dol_print_date($db->jdate($row->transaction_date), 'dayhour').'</td><td>'.dol_escape_htmltag($row->type_operation).'</td><td>'.dol_escape_htmltag($row->payment_mode).'</td>';
		print '<td>'.($row->direction === 'credit' ? 'Entrée' : 'Sortie').'</td><td class="right">'.price($row->amount).'</td></tr>';
	}
	print '</table></div>';
} else {
	$scopeIds = SofAgenceService::allowedAgencyIds($db, $user);
	$sql = 'SELECT c.rowid, c.ref, c.label, a.ref agence_ref FROM '.$db->prefix().'sof_caisse c';
	$sql .= ' LEFT JOIN '.$db->prefix().'sof_agence a ON a.rowid = c.fk_agence WHERE c.entity IN ('.getEntity('sof_caisse').') AND c.status = 1';
	if ($scopeIds !== null) {
		$sql .= empty($scopeIds) ? ' AND 1 = 0' : ' AND c.fk_agence IN ('.implode(',', array_map('intval', $scopeIds)).')';
	}
	$sql .= ' ORDER BY a.ref, c.ref';
	$resql = $db->query($sql);
	$dasRows = array();
	$dasResult = $db->query('SELECT rowid, code, label FROM '.$db->prefix().'sof_das WHERE entity = '.((int) $conf->entity).' AND status = 1 ORDER BY label, code');
	while ($dasResult && ($dasRow = $db->fetch_object($dasResult))) {
		$dasRows[] = $dasRow;
	}
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="open">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">Caisse</td><td><select class="flat minwidth300" name="fk_caisse" required><option value="">-- Choisir --</option>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<option value="'.((int) $row->rowid).'">'.dol_escape_htmltag($row->agence_ref.' / '.$row->ref.' - '.$row->label).'</option>';
	}
	print '</select></td></tr>';
	print '<tr><td>Type de session</td><td><select name="session_type"><option value="daily">Journalière</option><option value="exceptional">Exceptionnelle</option></select></td></tr>';
	print '<tr><td>Fonds initial</td><td><input class="flat" type="number" min="0" step="0.01" name="opening_amount" value="0" required></td></tr>';
	print '<tr><td>DAS (optionnel)</td><td><select class="flat minwidth300" name="fk_das"><option value="">-- Aucun --</option>';
	foreach ($dasRows as $dasRow) {
		print '<option value="'.$dasRow->rowid.'">'.dol_escape_htmltag($dasRow->label.' ('.$dasRow->code.')').'</option>';
	}
	print '</select></td></tr>';
	print '<tr><td>Note</td><td><textarea class="flat centpercent" name="note"></textarea></td></tr>';
	print '</table><div class="center"><button class="button button-save" type="submit">Ouvrir la session</button></div></form>';
}

llxFooter();
$db->close();
