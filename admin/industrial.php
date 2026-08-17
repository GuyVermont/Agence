<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofnotificationservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofimportservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofindustrialservice.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

$langs->loadLangs(array('agence@agence', 'admin', 'banks', 'companies'));
$section = GETPOST('section', 'aZ09') ?: 'notifications';
$allowedSections = array('notifications', 'imports', 'collections', 'errors', 'reversals', 'retention');
if (!in_array($section, $allowedSections, true)) $section = 'notifications';
$permissions = array(
	'notifications' => array(array('notification', 'manage')),
	'imports' => array(array('bankimport', 'import'), array('bankimport', 'reconcile'), array('bulkimport', 'run')),
	'collections' => array(array('recouvrement', 'manage')),
	'errors' => array(array('technicalerror', 'manage')),
	'reversals' => array(array('reversal', 'request'), array('reversal', 'approve')),
	'retention' => array(array('archive', 'manage')),
);
$authorized = !empty($user->admin);
foreach ($permissions[$section] as $permission) {
	if ($user->hasRight('agence', $permission[0], $permission[1])) $authorized = true;
}
if (!$authorized || !SofAgenceService::isActiveUser($db, $user)) accessforbidden();

$notifications = new SofNotificationService($db);
$imports = new SofImportService($db);
$industrial = new SofAgenceIndustrialService($db);
$action = GETPOST('action', 'aZ09');
$retentionPreview = null;

function agence_industrial_uploaded_csv($name, &$filename, &$error)
{
	$filename = '';
	$error = '';
	if (empty($_FILES[$name]) || !is_array($_FILES[$name]) || (int) $_FILES[$name]['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES[$name]['tmp_name'])) {
		$error = 'Fichier CSV absent ou transfert incomplet.';
		return false;
	}
	$filename = basename((string) $_FILES[$name]['name']);
	$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	if (!in_array($extension, array('csv', 'txt'), true) || (int) $_FILES[$name]['size'] <= 0 || (int) $_FILES[$name]['size'] > 20 * 1024 * 1024) {
		$error = 'Seuls les fichiers CSV/TXT de 20 Mo maximum sont acceptés.';
		return false;
	}
	$content = file_get_contents($_FILES[$name]['tmp_name']);
	if ($content === false) {
		$error = 'Lecture du fichier importé impossible.';
		return false;
	}
	return $content;
}

/** Restrict a list query to the agencies currently assigned to the user. */
function agence_industrial_agency_scope_sql($field)
{
	global $db, $user;
	$allowed = SofAgenceService::allowedAgencyIds($db, $user);
	if ($allowed === null) return '';
	if (empty($allowed)) return ' AND 1=0';
	return ' AND '.$field.' IN ('.implode(',', array_map('intval', $allowed)).')';
}

if ($action !== '') {
	if (!GETPOST('token') || GETPOST('token') !== $_SESSION['newtoken']) accessforbidden('Invalid CSRF token');
	$result = -1;
	if ($action === 'save_notification' && ($user->admin || $user->hasRight('agence', 'notification', 'manage'))) {
		$result = $notifications->saveConfiguration($user, array(
			'event_code' => GETPOST('event_code', 'restricthtml'), 'severity_min' => GETPOST('severity_min', 'alpha'),
			'channel' => GETPOST('channel', 'alpha'), 'recipient_type' => GETPOST('recipient_type', 'alpha'),
			'recipient' => GETPOST('recipient', 'restricthtml'), 'escalation_level' => GETPOST('escalation_level', 'int'),
		));
	} elseif ($action === 'disable_notification' && ($user->admin || $user->hasRight('agence', 'notification', 'manage'))) {
		$result = $notifications->disableConfiguration($user, GETPOST('id', 'int'));
	} elseif ($action === 'process_notifications' && ($user->admin || $user->hasRight('agence', 'notification', 'manage'))) {
		$result = $notifications->runEscalations();
		if ($result >= 0) $result = $notifications->synchronizeCollections();
		if ($result >= 0) $result = $notifications->processQueue(200);
	} elseif ($action === 'import_statement' && ($user->admin || $user->hasRight('agence', 'bankimport', 'import'))) {
		$filename = $uploadError = '';
		$content = agence_industrial_uploaded_csv('statement_file', $filename, $uploadError);
		$result = $content === false ? -1 : $imports->importStatement($user, GETPOST('source_type', 'alpha'), $filename, $content, GETPOST('fk_bank_account', 'int'), GETPOST('fk_agence', 'int'));
		if ($content === false) $imports->error = $uploadError;
	} elseif ($action === 'confirm_match' && ($user->admin || $user->hasRight('agence', 'bankimport', 'reconcile'))) {
		$result = $imports->confirmMatch($user, GETPOST('line_id', 'int'), GETPOST('target_id', 'int'));
	} elseif ($action === 'import_master' && ($user->admin || $user->hasRight('agence', 'bulkimport', 'run'))) {
		$filename = $uploadError = '';
		$content = agence_industrial_uploaded_csv('master_file', $filename, $uploadError);
		$result = $content === false ? -1 : $imports->importMasterData($user, GETPOST('object_type', 'alpha'), $filename, $content, GETPOST('import_mode', 'alpha'));
		if ($content === false) $imports->error = $uploadError;
	} elseif ($action === 'sync_collections' && ($user->admin || $user->hasRight('agence', 'recouvrement', 'manage'))) {
		$result = $notifications->synchronizeCollections();
	} elseif ($action === 'collection_action' && ($user->admin || $user->hasRight('agence', 'recouvrement', 'manage'))) {
		$result = $notifications->addCollectionAction($user, GETPOST('case_id', 'int'), GETPOST('action_type', 'alpha'), GETPOST('channel', 'alpha'), GETPOST('outcome', 'alpha'), GETPOST('notes', 'restricthtml'), GETPOST('next_action_date', 'alpha'), GETPOST('promise_date', 'alpha'), GETPOST('promise_amount', 'alphanohtml'));
	} elseif ($action === 'retry_error' && ($user->admin || $user->hasRight('agence', 'technicalerror', 'manage'))) {
		$result = $notifications->retryTechnicalError(GETPOST('error_id', 'int'), $user);
	} elseif ($action === 'request_reversal' && ($user->admin || $user->hasRight('agence', 'reversal', 'request'))) {
		$result = $industrial->requestReversal($user, GETPOST('movement_id', 'int'), GETPOST('reason', 'restricthtml'), GETPOST('evidence_ref', 'restricthtml'));
	} elseif ($action === 'decide_reversal' && ($user->admin || $user->hasRight('agence', 'reversal', 'approve'))) {
		$result = $industrial->decideReversal($user, GETPOST('reversal_id', 'int'), GETPOST('decision', 'alpha') === 'approve', GETPOST('decision_reason', 'restricthtml'));
	} elseif ($action === 'retention_preview' && ($user->admin || $user->hasRight('agence', 'archive', 'manage'))) {
		$retentionPreview = $industrial->applyRetention($user, true, false);
		$result = is_array($retentionPreview) ? 1 : -1;
	} elseif ($action === 'retention_archive' && ($user->admin || $user->hasRight('agence', 'archive', 'manage'))) {
		$retentionPreview = $industrial->applyRetention($user, false, false);
		$result = is_array($retentionPreview) ? 1 : -1;
	} elseif ($action === 'retention_purge' && ($user->admin || $user->hasRight('agence', 'archive', 'manage'))) {
		if (GETPOST('confirm_text', 'alpha') !== 'PURGER' || !getDolGlobalInt('AGENCE_ENABLE_PURGE')) {
			$industrial->error = 'La purge exige le mot PURGER et le paramètre global d’autorisation.';
			$result = -1;
		} else {
			$retentionPreview = $industrial->applyRetention($user, false, true);
			$result = is_array($retentionPreview) ? 1 : -1;
		}
	}
	$serviceError = $notifications->error ?: ($imports->error ?: $industrial->error);
	if ($result >= 0) setEventMessages('Opération exécutée et tracée.', null, 'mesgs');
	else setEventMessages($serviceError ?: 'Opération refusée ou en échec.', null, 'errors');
}

llxHeader('', 'Opérations industrielles Agence', '', '', 0, 0, '', '', '', 'mod-agence page-industrial');
print load_fiche_titre('Opérations industrielles Agence', '', 'gears');
$tabs = array(
	'notifications' => 'Notifications et escalades', 'imports' => 'Imports et rapprochements', 'collections' => 'Recouvrement',
	'errors' => 'Erreurs et reprises', 'reversals' => 'Contrepassations', 'retention' => 'Archivage et purge',
);
print '<div class="tabs">';
foreach ($tabs as $key => $label) print '<a class="tab'.($section === $key ? ' tabactive' : '').'" href="?section='.$key.'">'.dol_escape_htmltag($label).'</a>';
print '</div>';

if ($section === 'notifications') {
	print load_fiche_titre('Règles multicanales', '', 'bell');
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_notification"><input type="hidden" name="section" value="notifications">';
	print '<table class="border centpercent"><tr><td>Événement</td><td><input required class="flat minwidth200" name="event_code" placeholder="critical_alert, validation_overdue, *"></td><td>Sévérité minimale</td><td><select name="severity_min"><option>info</option><option>warning</option><option>critical</option></select></td></tr>';
	print '<tr><td>Canal</td><td><select name="channel"><option value="internal">Interne</option><option value="email">E-mail</option><option value="sms">SMS</option></select></td><td>Type destinataire</td><td><select name="recipient_type"><option value="address">Adresse/numéro</option><option value="user">ID utilisateur</option><option value="role">Rôle agence</option></select></td></tr>';
	print '<tr><td>Destinataire</td><td><input required class="flat minwidth300" name="recipient"></td><td>Niveau d’escalade</td><td><input type="number" min="0" max="3" name="escalation_level" value="0"></td></tr></table><div class="center"><button class="button" type="submit">Enregistrer la règle</button></div></form>';
	print '<form method="POST" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="process_notifications"><input type="hidden" name="section" value="notifications"><button class="button" type="submit">Exécuter maintenant</button></form>';
	$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_notification_config WHERE entity = '.((int) $conf->entity).' ORDER BY status DESC,event_code,channel,recipient');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Événement</th><th>Canal</th><th>Destinataire</th><th>Niveau</th><th>État</th><th></th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->event_code).'</td><td>'.dol_escape_htmltag($row->channel).'</td><td>'.dol_escape_htmltag($row->recipient_type.':'.$row->recipient).'</td><td>'.((int) $row->escalation_level).'</td><td>'.((int) $row->status ? 'Actif' : 'Inactif').'</td><td>';
		if ((int) $row->status) print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="notifications"><input type="hidden" name="action" value="disable_notification"><input type="hidden" name="id" value="'.((int) $row->rowid).'"><button class="button smallpaddingimp">Désactiver</button></form>';
		print '</td></tr>';
	}
	print '</table>';
	$resql = $db->query('SELECT ref,event_code,severity,channel,recipient,attempts,last_error,status,date_creation,date_sent FROM '.$db->prefix().'sof_notification_outbox WHERE entity = '.((int) $conf->entity).' ORDER BY rowid DESC'.$db->plimit(100, 0));
	print load_fiche_titre('File et canal interne', '', 'inbox');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Référence</th><th>Événement</th><th>Canal / destinataire</th><th>Tentatives</th><th>État</th><th>Erreur</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag($row->event_code.' / '.$row->severity).'</td><td>'.dol_escape_htmltag($row->channel.' / '.$row->recipient).'</td><td>'.((int) $row->attempts).'</td><td>'.((int) $row->status).'</td><td>'.dol_escape_htmltag(dol_trunc($row->last_error, 120)).'</td></tr>';
	print '</table>';
} elseif ($section === 'imports') {
	print load_fiche_titre('Importer un relevé bancaire ou opérateur', '', 'file-import');
	print '<form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="imports"><input type="hidden" name="action" value="import_statement"><table class="border centpercent">';
	print '<tr><td>Source</td><td><select name="source_type"><option value="bank">Banque</option><option value="orange_money">Orange Money</option><option value="mobile_money">Mobile Money</option></select></td><td>Compte bancaire Dolibarr</td><td><input type="number" min="0" name="fk_bank_account"></td></tr>';
	print '<tr><td>Agence (optionnelle)</td><td><input type="number" min="0" name="fk_agence"></td><td>CSV (date, montant, référence…)</td><td><input required type="file" accept=".csv,.txt,text/csv" name="statement_file"></td></tr></table><div class="center"><button class="button">Importer et proposer les rapprochements</button></div></form>';
	print load_fiche_titre('Mise à jour en masse des référentiels', '', 'upload');
	print '<form method="POST" enctype="multipart/form-data"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="imports"><input type="hidden" name="action" value="import_master"><table class="border centpercent"><tr><td>Référentiel</td><td><select name="object_type"><option value="agency">Agences</option><option value="cashdesk">Caisses</option><option value="das">DAS</option><option value="assignment">Affectations</option></select></td><td>Mode</td><td><select name="import_mode"><option value="upsert">Créer ou mettre à jour</option><option value="create">Créer seulement</option><option value="update">Mettre à jour seulement</option></select></td></tr><tr><td>CSV</td><td colspan="3"><input required type="file" accept=".csv,.txt,text/csv" name="master_file"></td></tr></table><div class="center"><button class="button">Exécuter l’import tracé</button></div></form>';
	$resql = $db->query('SELECT l.*,i.ref import_ref,i.source_type FROM '.$db->prefix().'sof_bank_import_line l JOIN '.$db->prefix().'sof_bank_import i ON i.rowid=l.fk_import AND i.entity=l.entity WHERE l.entity='.((int) $conf->entity).' AND l.status IN (0,1)'.agence_industrial_agency_scope_sql('i.fk_agence').' ORDER BY l.rowid DESC'.$db->plimit(200, 0));
	print load_fiche_titre('Rapprochements proposés', '', 'link');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Import</th><th>Date</th><th>Référence</th><th>Montant</th><th>Score / raison</th><th>Cible</th><th></th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		$target = $row->source_type === 'bank' ? (int) $row->fk_bank : (int) $row->fk_mouvement;
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->import_ref.' / '.$row->source_type).'</td><td>'.dol_escape_htmltag($row->operation_date).'</td><td>'.dol_escape_htmltag($row->external_ref).'</td><td>'.price($row->amount).'</td><td>'.((int) $row->match_score).'% '.dol_escape_htmltag($row->match_reason).'</td><td>'.($target ?: '-').'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="imports"><input type="hidden" name="action" value="confirm_match"><input type="hidden" name="line_id" value="'.((int) $row->rowid).'"><input class="width75" type="number" min="1" name="target_id" value="'.($target ?: '').'"><button class="button smallpaddingimp">Confirmer</button></form></td></tr>';
	}
	print '</table>';
} elseif ($section === 'collections') {
	print '<form method="POST" class="right"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="collections"><input type="hidden" name="action" value="sync_collections"><button class="button">Synchroniser les créances échues</button></form>';
	$resql = $db->query('SELECT r.*,s.nom FROM '.$db->prefix().'sof_recouvrement r JOIN '.$db->prefix().'societe s ON s.rowid=r.fk_soc WHERE r.entity='.((int) $conf->entity).agence_industrial_agency_scope_sql('r.fk_agence').' ORDER BY r.status,r.priority DESC,r.next_action_date'.$db->plimit(500, 0));
	print load_fiche_titre('Workflow de recouvrement', '', 'comments-dollar');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Dossier</th><th>Client</th><th>Étape</th><th>Priorité</th><th>Solde</th><th>Prochaine action</th><th>Action documentée</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag($row->nom).'</td><td>'.dol_escape_htmltag($row->stage).'</td><td>'.dol_escape_htmltag($row->priority).'</td><td>'.price($row->outstanding_amount).'</td><td>'.dol_escape_htmltag($row->next_action_date).'</td><td><form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="collections"><input type="hidden" name="action" value="collection_action"><input type="hidden" name="case_id" value="'.((int) $row->rowid).'"><select name="action_type"><option value="call">Appel</option><option value="email">E-mail</option><option value="sms">SMS</option><option value="visit">Visite</option><option value="formal_notice">Mise en demeure</option><option value="promise">Promesse</option><option value="dispute">Litige</option><option value="close">Clôturer</option></select><select name="channel"><option value="phone">Téléphone</option><option value="email">E-mail</option><option value="sms">SMS</option><option value="internal">Interne</option></select><input required name="notes" placeholder="Compte rendu et preuve"><input type="date" name="next_action_date"><input type="date" name="promise_date"><input class="width75" name="promise_amount" placeholder="Montant"><button class="button smallpaddingimp">Tracer</button></form></td></tr>';
	}
	print '</table>';
} elseif ($section === 'errors') {
	$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_technical_error WHERE entity='.((int) $conf->entity).' ORDER BY status,rowid DESC'.$db->plimit(500, 0));
	print load_fiche_titre('Journal des erreurs techniques', '', 'triangle-exclamation');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Référence</th><th>Opération</th><th>Objet</th><th>Erreur</th><th>Tentatives</th><th>État</th><th></th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag($row->operation_code.' / '.$row->retry_handler).'</td><td>'.dol_escape_htmltag($row->object_type.' #'.$row->object_id).'</td><td>'.dol_escape_htmltag(dol_trunc($row->error_message, 180)).'</td><td>'.((int) $row->attempts).'/'.((int) $row->max_attempts).'</td><td>'.((int) $row->status).'</td><td>';
		if (in_array((int) $row->status, array(0,1), true) && (int) $row->attempts < (int) $row->max_attempts) print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="errors"><input type="hidden" name="action" value="retry_error"><input type="hidden" name="error_id" value="'.((int) $row->rowid).'"><button class="button smallpaddingimp">Reprendre</button></form>';
		print '</td></tr>';
	}
	print '</table>';
} elseif ($section === 'reversals') {
	print load_fiche_titre('Demander une contrepassation', '', 'rotate-left');
	print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="reversals"><input type="hidden" name="action" value="request_reversal"><table class="border centpercent"><tr><td>ID mouvement</td><td><input required type="number" min="1" name="movement_id"></td><td>Preuve / pièce</td><td><input class="minwidth200" name="evidence_ref"></td></tr><tr><td>Motif détaillé</td><td colspan="3"><textarea required class="quatrevingtpercent" name="reason"></textarea></td></tr></table><div class="center"><button class="button">Soumettre à validation</button></div></form>';
	$resql = $db->query('SELECT r.*,m.ref movement_ref,m.amount,m.payment_mode FROM '.$db->prefix().'sof_financial_reversal r JOIN '.$db->prefix().'sof_caisse_mouvement m ON m.rowid=r.fk_mouvement_original AND m.entity=r.entity WHERE r.entity='.((int) $conf->entity).agence_industrial_agency_scope_sql('m.fk_agence').' ORDER BY r.rowid DESC'.$db->plimit(500, 0));
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Demande</th><th>Mouvement</th><th>Montant</th><th>Motif</th><th>Preuve</th><th>État</th><th>Décision</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->ref).'</td><td>'.dol_escape_htmltag($row->movement_ref).'</td><td>'.price($row->amount).' '.dol_escape_htmltag($row->payment_mode).'</td><td>'.dol_escape_htmltag($row->reason).'</td><td>'.dol_escape_htmltag($row->evidence_ref).'</td><td>'.((int) $row->status).'</td><td>';
		if ((int) $row->status === 0 && ($user->admin || $user->hasRight('agence', 'reversal', 'approve'))) print '<form method="POST"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="reversals"><input type="hidden" name="action" value="decide_reversal"><input type="hidden" name="reversal_id" value="'.((int) $row->rowid).'"><select name="decision"><option value="approve">Approuver</option><option value="reject">Rejeter</option></select><input required name="decision_reason" placeholder="Motif de décision"><button class="button smallpaddingimp">Décider</button></form>';
		print '</td></tr>';
	}
	print '</table>';
} else {
	print load_fiche_titre('Politique de conservation', '', 'box-archive');
	print '<div class="info">Audits : '.getDolGlobalInt('AGENCE_AUDIT_RETENTION_DAYS',3650).' jours ; documents : '.getDolGlobalInt('AGENCE_DOCUMENT_RETENTION_DAYS',3650).' jours ; erreurs : '.getDolGlobalInt('AGENCE_TECH_ERROR_RETENTION_DAYS',730).' jours. La purge est '.(getDolGlobalInt('AGENCE_ENABLE_PURGE') ? '<strong>autorisée</strong>' : '<strong>désactivée</strong>').'.</div>';
	foreach (array('retention_preview'=>'Prévisualiser','retention_archive'=>'Archiver les éléments échus') as $retentionAction => $label) print '<form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="retention"><input type="hidden" name="action" value="'.$retentionAction.'"><button class="button">'.$label.'</button></form> ';
	print '<form method="POST" class="inline-block"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="section" value="retention"><input type="hidden" name="action" value="retention_purge"><input name="confirm_text" placeholder="Saisir PURGER"><button class="buttonDelete">Purger définitivement les archives échues</button></form>';
	if (is_array($retentionPreview)) {
		print '<table class="border centpercent"><tr class="liste_titre"><th>Indicateur</th><th>Nombre</th></tr>';
		foreach ($retentionPreview as $key => $value) print '<tr><td>'.dol_escape_htmltag($key).'</td><td>'.((int) $value).'</td></tr>';
		print '</table>';
	}
	$resql = $db->query('SELECT * FROM '.$db->prefix().'sof_archive_log WHERE entity='.((int) $conf->entity).' ORDER BY rowid DESC'.$db->plimit(200, 0));
	print load_fiche_titre('Journal de conservation', '', 'history');
	print '<table class="liste centpercent"><tr class="liste_titre"><th>Date</th><th>Objet</th><th>Politique</th><th>Action</th><th>Empreinte</th><th>Motif</th></tr>';
	while ($resql && ($row = $db->fetch_object($resql))) print '<tr class="oddeven"><td>'.dol_escape_htmltag($row->action_date).'</td><td>'.dol_escape_htmltag($row->object_type.' #'.$row->object_id).'</td><td>'.dol_escape_htmltag($row->policy_code).'</td><td>'.dol_escape_htmltag($row->action_type).'</td><td>'.dol_escape_htmltag(substr($row->content_hash,0,16)).'…</td><td>'.dol_escape_htmltag($row->reason).'</td></tr>';
	print '</table>';
}

llxFooter();
$db->close();
