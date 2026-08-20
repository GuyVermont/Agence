<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/soffacturelink.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofcommandelink.class.php';

$langs->loadLangs(array('agence@agence', 'bills', 'orders', 'companies'));
if (!$user->hasRight('agence', 'agence', 'write') && !$user->hasRight('agence', 'scope', 'write')) accessforbidden();

$nativeType = GETPOST('native_type', 'alpha');
$nativeId = (int) GETPOST('native_id', 'int');
if (!in_array($nativeType, array('invoice', 'order'), true) || $nativeId <= 0) accessforbidden();

$nativeTable = $nativeType === 'invoice' ? 'facture' : 'commande';
$nativeLabel = $nativeType === 'invoice' ? 'Invoice' : 'Order';
$sql = 'SELECT n.rowid,n.ref,n.fk_soc,s.nom thirdparty'.($nativeType === 'invoice' ? ',n.type' : '').' FROM '.$db->prefix().$nativeTable.' n';
$sql .= ' LEFT JOIN '.$db->prefix().'societe s ON s.rowid=n.fk_soc WHERE n.entity='.(int) $conf->entity.' AND n.rowid='.$nativeId;
$resql = $db->query($sql);
$native = $resql ? $db->fetch_object($resql) : null;
if (!$native) accessforbidden($langs->trans('NativeObjectUnavailableForEntity'));

if ($nativeType === 'invoice' && (int) $native->type === 2) {
	header('Location: '.dol_buildpath('/agence/avoir/card.php', 1).'?object=avoir&action=create&fk_facture_avoir='.$nativeId);
	exit;
}

$linkTable = $nativeType === 'invoice' ? 'sof_facture_link' : 'sof_commande_link';
$linkField = $nativeType === 'invoice' ? 'fk_facture' : 'fk_commande';
$existingSql = 'SELECT * FROM '.$db->prefix().$linkTable.' WHERE entity='.(int) $conf->entity.' AND '.$linkField.'='.$nativeId.' ORDER BY rowid DESC LIMIT 1';
$existingResult = $db->query($existingSql);
$existing = $existingResult ? $db->fetch_object($existingResult) : null;

$allowedAgencies = SofAgenceService::allowedAgencyIds($db, $user);
$suggestedAgency = $existing ? (int) $existing->fk_agence : 0;
$suggestedDas = $existing ? (int) $existing->fk_das : 0;
if ($suggestedAgency <= 0) {
	$profileSql = 'SELECT fk_agence_followup FROM '.$db->prefix().'sof_tiers_credit_profile WHERE entity='.(int) $conf->entity.' AND fk_soc='.(int) $native->fk_soc.' AND status=1 ORDER BY rowid DESC LIMIT 1';
	$profileResult = $db->query($profileSql);
	$profile = $profileResult ? $db->fetch_object($profileResult) : null;
	if ($profile) $suggestedAgency = (int) $profile->fk_agence_followup;
}
if ($suggestedAgency <= 0 && is_array($allowedAgencies) && count($allowedAgencies) === 1) $suggestedAgency = (int) reset($allowedAgencies);

if ($suggestedDas <= 0) {
	$detailTable = $nativeType === 'invoice' ? 'facturedet' : 'commandedet';
	$detailForeign = $nativeType === 'invoice' ? 'fk_facture' : 'fk_commande';
	$dasSql = 'SELECT DISTINCT pd.fk_das FROM '.$db->prefix().$detailTable.' det INNER JOIN '.$db->prefix().'sof_product_das pd ON pd.fk_product=det.fk_product AND pd.entity='.(int) $conf->entity.' AND pd.status=1';
	$dasSql .= ' WHERE det.'.$detailForeign.'='.$nativeId.' AND det.fk_product IS NOT NULL';
	if ($suggestedAgency > 0) $dasSql .= ' AND (pd.fk_agence IS NULL OR pd.fk_agence='.$suggestedAgency.')';
	$dasResult = $db->query($dasSql);
	if ($dasResult && $db->num_rows($dasResult) === 1 && ($dasRow = $db->fetch_object($dasResult))) $suggestedDas = (int) $dasRow->fk_das;
}

if (GETPOST('action', 'alpha') === 'save') {
	if (GETPOST('token') !== $_SESSION['newtoken']) accessforbidden('Invalid token');
	$fkAgence = (int) GETPOST('fk_agence', 'int');
	$fkDas = (int) GETPOST('fk_das', 'int');
	if ($allowedAgencies !== null && !in_array($fkAgence, $allowedAgencies, true)) accessforbidden($langs->trans('AgencyOutOfScope'));
	$relationError = SofAgenceService::validateAgencyCashDeskDas($db, $fkAgence, 0, $fkDas, true);
	if ($fkAgence <= 0 || $relationError !== '') {
		setEventMessages($relationError !== '' ? $relationError : $langs->trans('AgencyRequired'), null, 'errors');
	} else {
		$db->begin();
		if ($existing) {
			$update = 'UPDATE '.$db->prefix().$linkTable.' SET fk_soc='.(int) $native->fk_soc.',fk_agence='.$fkAgence.',fk_das='.($fkDas ?: 'NULL').',fk_user_modif='.(int) $user->id.',tms=CURRENT_TIMESTAMP WHERE entity='.(int) $conf->entity.' AND rowid='.(int) $existing->rowid;
			$result = $db->query($update) ? (int) $existing->rowid : -1;
		} else {
			$link = $nativeType === 'invoice' ? new SofFactureLink($db) : new SofCommandeLink($db);
			$link->entity = (int) $conf->entity;
			$link->$linkField = $nativeId;
			$link->fk_soc = (int) $native->fk_soc;
			$link->fk_agence = $fkAgence;
			$link->fk_das = $fkDas ?: null;
			$link->source_type = 'dolibarr_native';
			$link->source_id = $nativeId;
			if ($nativeType === 'invoice') {
				$link->billing_status = 1;
				$link->deferred_status = 0;
				$link->accounting_status = 0;
			}
			$result = $link->create($user, 1);
		}
		if ($result > 0) {
			SofAgenceService::logAudit($db, $user, 'SOF_NATIVE_ATTACHMENT', $native, $existing ? (array) $existing : null, array('native_type'=>$nativeType, 'native_id'=>$nativeId, 'fk_agence'=>$fkAgence, 'fk_das'=>$fkDas));
			$db->commit();
			setEventMessages($langs->trans('NativeAttachmentSaved'), null, 'mesgs');
			$target = $nativeType === 'invoice' ? '/compta/facture/card.php?facid='.$nativeId : '/commande/card.php?id='.$nativeId;
			header('Location: '.dol_buildpath($target, 1));
			exit;
		}
		$db->rollback();
		setEventMessages(isset($link) ? $link->error : $db->lasterror(), isset($link) ? $link->errors : null, 'errors');
	}
}

llxHeader('', $langs->trans('ManageAgencyAttachment'));
print load_fiche_titre($langs->trans('ManageAgencyAttachment'), '', 'link');
print '<div class="info">'.$langs->trans('NativeAttachmentIntro').'</div>';
print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="native_type" value="'.dol_escape_htmltag($nativeType).'"><input type="hidden" name="native_id" value="'.$nativeId.'">';
print '<table class="border centpercent tableforfield"><tr><td class="titlefield">'.$langs->trans($nativeLabel).'</td><td><strong>'.dol_escape_htmltag($native->ref).'</strong> — '.dol_escape_htmltag($native->thirdparty).'</td></tr>';
print '<tr><td class="fieldrequired">'.$langs->trans('Agency').'</td><td><select class="flat minwidth300" name="fk_agence" required><option value="">'.$langs->trans('Select').'</option>';
$agencySql = 'SELECT rowid,ref,label FROM '.$db->prefix().'sof_agence WHERE entity='.(int) $conf->entity.' AND status IN (1,4)';
if ($allowedAgencies !== null) $agencySql .= empty($allowedAgencies) ? ' AND 1=0' : ' AND rowid IN ('.implode(',', array_map('intval', $allowedAgencies)).')';
$agencySql .= ' ORDER BY ref';
$agencyResult = $db->query($agencySql);
while ($agencyResult && ($agency = $db->fetch_object($agencyResult))) print '<option value="'.(int) $agency->rowid.'"'.($suggestedAgency === (int) $agency->rowid ? ' selected' : '').'>'.dol_escape_htmltag(trim($agency->ref.' — '.$agency->label, ' —')).'</option>';
print '</select></td></tr><tr><td>'.$langs->trans('DAS').'</td><td><select class="flat minwidth300" name="fk_das"><option value="">'.$langs->trans('NotApplicable').'</option>';
$dasResult = $db->query('SELECT rowid,ref,label FROM '.$db->prefix().'sof_das WHERE entity='.(int) $conf->entity.' AND status=1 ORDER BY ref');
while ($dasResult && ($das = $db->fetch_object($dasResult))) print '<option value="'.(int) $das->rowid.'"'.($suggestedDas === (int) $das->rowid ? ' selected' : '').'>'.dol_escape_htmltag(trim($das->ref.' — '.$das->label, ' —')).'</option>';
print '</select></td></tr></table><div class="center"><button class="button button-save" type="submit">'.$langs->trans('Save').'</button></div></form>';
llxFooter();
$db->close();
