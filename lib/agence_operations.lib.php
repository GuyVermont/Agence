<?php
/* Copyright (C) 2026 SOFITOUL */

/** Process a POSTed business transition from a generic object card. */
function agence_process_business_action($key, $id)
{
	global $db, $langs, $user;

	if (GETPOST('token') !== $_SESSION['newtoken']) {
		accessforbidden('Invalid token');
	}
	require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
	$engine = new SofAgenceOperations($db);
	$businessAction = GETPOST('business_action', 'alpha');
	$result = -1;

	if ($key === 'session') {
		if ($businessAction === 'close') {
			$denominations = array();
			foreach (agence_cash_denominations() as $denomination) {
				$quantity = GETPOST('denom_'.$denomination, 'int');
				if ($quantity > 0) {
					$denominations[$denomination] = $quantity;
				}
			}
			if (!empty($denominations)) {
				$physical = $engine->saveCashCount($user, $id, $denominations, 'closing');
			} else {
				$physical = price2num(GETPOST('physical_amount', 'alpha'));
			}
			$result = $physical < 0 ? -1 : $engine->transitionSession($user, $id, 'close', array('physical_amount' => $physical, 'comment' => GETPOST('comment', 'restricthtml')));
		} else {
			$result = $engine->transitionSession($user, $id, $businessAction, array('reason' => GETPOST('reason', 'restricthtml')));
		}
	} elseif ($key === 'remboursement') {
		if ($businessAction === 'validate') {
			$result = $engine->validateRefund($user, $id, GETPOST('approved_amount', 'alpha'), GETPOST('reason', 'restricthtml'));
		} elseif ($businessAction === 'reject') {
			$result = $engine->rejectRefund($user, $id, GETPOST('reason', 'restricthtml'));
		} elseif ($businessAction === 'execute') {
			$result = $engine->executeRefund($user, $id);
		}
	} elseif ($key === 'controle') {
		if ($businessAction === 'start') {
			$result = $engine->startControl($user, $id);
		} elseif ($businessAction === 'complete') {
			$result = $engine->completeControl($user, $id, GETPOST('physical_amount', 'alpha'), GETPOST('observations', 'restricthtml'));
		}
	} elseif ($key === 'transfert' && in_array($businessAction, array('execute', 'receive'), true)) {
		$result = $businessAction === 'execute' ? $engine->executeTransfer($user, $id) : $engine->receiveTransfer($user, $id);
	} elseif ($key === 'depotbanque' && in_array($businessAction, array('execute', 'reconcile'), true)) {
		$result = $businessAction === 'execute'
			? $engine->executeDeposit($user, $id)
			: $engine->reconcileDeposit($user, $id, GETPOST('fk_bank', 'int'), GETPOST('reference', 'alphanohtml'));
	} elseif ($key === 'alerte' && in_array($businessAction, array('read', 'close'), true)) {
		if (!empty($user->admin) || $user->hasRight('agence', 'audit', 'read') || $user->hasRight('agence', 'controle', 'create')) {
			$result = $engine->updateRow('sof_caisse_alerte', $id, $businessAction === 'read'
				? array('date_read' => dol_now(), 'fk_user_read' => (int) $user->id, 'status' => 1)
				: array('date_close' => dol_now(), 'fk_user_close' => (int) $user->id, 'dedup_key' => null, 'status' => 2), $user);
		} else {
			$engine->error = 'Permission refusée pour gérer les alertes.';
		}
	} elseif ($key === 'ecart' && $businessAction === 'resolve') {
		$result = $engine->resolveCashGap($user, $id, GETPOST('reason', 'restricthtml'), GETPOST('decision', 'restricthtml'));
	} elseif ($key === 'paiementdiffere' && in_array($businessAction, array('validate', 'dispute', 'regularize', 'close'), true)) {
		$result = $engine->transitionDeferredPayment($user, $id, $businessAction, GETPOST('reason', 'restricthtml'));
	} elseif (in_array($key, array('boncommande', 'bst', 'instruction'), true) && in_array($businessAction, array('validate', 'reject'), true)) {
		if ($businessAction === 'validate') {
			$result = $engine->validateSupportingDocument($user, $key, $id, GETPOST('reason', 'restricthtml'));
		} else {
			$workflowTypes = array('boncommande' => 'customer_po', 'bst' => 'bst', 'instruction' => 'manager_instruction');
			$result = $engine->rejectSupportingDocument($user, $workflowTypes[$key], $id, GETPOST('reason', 'restricthtml'));
		}
	} elseif ($key === 'avoir' && in_array($businessAction, array('validate', 'use'), true)) {
		$result = $businessAction === 'validate'
			? $engine->validateCreditTracking($user, $id)
			: $engine->consumeCreditTracking($user, $id, GETPOST('amount', 'alpha'));
	}

	if ($result > 0 || $result === 0) {
		setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
	} else {
		setEventMessages($engine->error ?: $langs->trans('Error'), $engine->errors, 'errors');
	}
	header('Location: '.agence_object_card_url($key, $id));
	exit;
}

/** Render contextual actions for operational objects. */
function agence_print_business_actions($key, $object)
{
	global $langs, $user;

	$id = !empty($object->id) ? (int) $object->id : (int) $object->rowid;
	if ($id <= 0) {
		return;
	}
	$forms = array();
	if ($key === 'session') {
		$status = (int) $object->status;
		if (in_array($status, array(0, 1, 3, 4), true) && $user->hasRight('agence', 'session', 'open')) {
			$forms[] = agence_business_form('operate', 'SwitchToOperating');
		}
		if (in_array($status, array(1, 2), true) && $user->hasRight('agence', 'session', 'open')) {
			$forms[] = agence_business_form('pause', 'PauseSession', array('reason' => 'Reason'));
		}
		if ($status === 3 && $user->hasRight('agence', 'session', 'open')) {
			$forms[] = agence_business_form('resume', 'ResumeSession');
		}
		if (in_array($status, array(1, 2, 3, 4), true) && $user->hasRight('agence', 'session', 'close')) {
			$forms[] = agence_business_form('start_close', 'StartClosing');
		}
		if ($status === 5 && $user->hasRight('agence', 'session', 'close')) {
			$forms[] = agence_business_form('close', 'FinalizeClosing', array('physical_amount' => 'PhysicalAmount', 'comment' => 'Comment'));
			$forms[] = agence_business_cash_count_form();
		}
		if ($status === 6 && $user->hasRight('agence', 'session', 'validate')) {
			$forms[] = agence_business_form('validate', 'ValidateCashClosing', array('reason' => 'Comment'));
			$forms[] = agence_business_form('reopen', 'ReopenSession', array('reason' => 'MandatoryReason'));
		}
		if ($status === 7 && $user->hasRight('agence', 'compta', 'post')) {
			$forms[] = agence_business_form('account', 'PostToAccounting');
		}
		if (in_array($status, array(1, 2, 3), true) && $user->hasRight('agence', 'controle', 'freeze')) {
			$forms[] = agence_business_form('freeze', 'FreezeSession');
		}
		if ($status === 4 && $user->hasRight('agence', 'controle', 'freeze')) {
			$forms[] = agence_business_form('unfreeze', 'UnfreezeSession');
		}
	} elseif ($key === 'remboursement') {
		if (in_array((int) $object->status, array(0, 1), true) && $user->hasRight('agence', 'remboursement', 'validate')) {
			$forms[] = agence_business_form('validate', 'Approve', array('approved_amount' => 'ApprovedAmount', 'reason' => 'Comment'));
			$forms[] = agence_business_form('reject', 'Reject', array('reason' => 'MandatoryReason'));
		}
		if ((int) $object->status === 2 && $user->hasRight('agence', 'remboursement', 'execute')) {
			$forms[] = agence_business_form('execute', 'ExecuteRefund');
		}
	} elseif ($key === 'controle') {
		if ((int) $object->status === 0 && $user->hasRight('agence', 'controle', 'create') && $user->hasRight('agence', 'controle', 'freeze')) {
			$forms[] = agence_business_form('start', 'StartControlAndFreezeCashDesk');
		}
		if ((int) $object->status === 1 && $user->hasRight('agence', 'controle', 'create') && $user->hasRight('agence', 'controle', 'freeze')) {
			$forms[] = agence_business_form('complete', 'CompleteControl', array('physical_amount' => 'PhysicalAmount', 'observations' => 'Observations'));
		}
	} elseif ($key === 'transfert' && $user->hasRight('agence', 'transfert', 'create')) {
		if ((int) $object->status === 0) {
			$forms[] = agence_business_form('execute', 'ExecuteTransfer');
		} elseif ((int) $object->status === 1) {
			$forms[] = agence_business_form('receive', 'ConfirmReceipt');
		}
	} elseif ($key === 'depotbanque') {
		if ((int) $object->status === 0 && $user->hasRight('agence', 'depotbanque', 'create')) {
			$forms[] = agence_business_form('execute', 'ExecuteDeposit');
		}
		if (in_array((int) $object->status, array(1, 2), true) && $user->hasRight('agence', 'depotbanque', 'reconcile')) {
			$forms[] = agence_business_form('reconcile', 'Reconcile', array('fk_bank' => 'CreditBankLineId', 'reference' => 'Ref'));
		}
	} elseif ($key === 'alerte') {
		if ((int) $object->status === 0) {
			$forms[] = agence_business_form('read', 'MarkAsRead');
		}
		if ((int) $object->status < 2) {
			$forms[] = agence_business_form('close', 'CloseAlert');
		}
	} elseif ($key === 'ecart' && (int) $object->status < 3 && $user->hasRight('agence', 'ecart', 'manage')) {
		$forms[] = agence_business_form('resolve', 'ProcessCashGap', array('reason' => 'Justification', 'decision' => 'Decision'));
	} elseif ($key === 'paiementdiffere') {
		if ((int) $object->status === 0 && $user->hasRight('agence', 'paiementdiffere', 'validate')) {
			$forms[] = agence_business_form('validate', 'Validate');
		}
		if (in_array((int) $object->status, array(1, 2, 3, 5), true) && $user->hasRight('agence', 'paiementdiffere', 'validate')) {
			$forms[] = agence_business_form('dispute', 'OpenDispute', array('reason' => 'MandatoryReason'));
		}
		if ((int) $object->status === 6 && $user->hasRight('agence', 'paiementdiffere', 'validate')) {
			$forms[] = agence_business_form('regularize', 'RegularizeDispute', array('reason' => 'RegularizationMeasure'));
		}
		if ((int) $object->status === 4 && $user->hasRight('agence', 'paiementdiffere', 'validate')) {
			$forms[] = agence_business_form('close', 'ClosePermanently', array('reason' => 'ClosureReason'));
		}
	} elseif (in_array($key, array('boncommande', 'bst', 'instruction'), true) && (int) $object->status === 0) {
		$forms[] = agence_business_form('validate', 'Validate');
		$forms[] = agence_business_form('reject', 'Reject', array('reason' => 'MandatoryReason'));
	} elseif ($key === 'avoir') {
		if (empty($object->validation_status) && $user->hasRight('agence', 'avoir', 'validate')) {
			$forms[] = agence_business_form('validate', 'ValidateCreditNote');
		}
		if (!empty($object->validation_status) && (float) $object->remaining_amount > 0 && $user->hasRight('agence', 'avoir', 'use')) {
			$forms[] = agence_business_form('use', 'ConsumeCreditNote', array('amount' => 'AmountToConsume'));
		}
	}

	if (empty($forms)) {
		return;
	}
	print '<div class="fichecenter"><div class="underbanner clearboth"></div><h3>'.$langs->trans('BusinessActions').'</h3>';
	foreach ($forms as $form) {
		print $form;
	}
	print '</div>';
}

/** Build a compact POST form for a business transition. */
function agence_business_form($action, $label, array $fields = array())
{
	global $langs;
	$html = '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="inline-block valignmiddle marginrightonly marginbottomonly">';
	$html .= '<input type="hidden" name="token" value="'.newToken().'">';
	$html .= '<input type="hidden" name="action" value="business">';
	$html .= '<input type="hidden" name="business_action" value="'.dol_escape_htmltag($action).'">';
	$html .= '<input type="hidden" name="id" value="'.((int) GETPOST('id', 'int')).'">';
	$html .= '<input type="hidden" name="object" value="'.dol_escape_htmltag(GETPOST('object', 'alpha')).'">';
	foreach ($fields as $name => $placeholderKey) {
		$type = preg_match('/amount|fk_bank/', $name) ? 'number' : 'text';
		$step = $type === 'number' ? ' step="0.01"' : '';
		$placeholder = $langs->trans($placeholderKey);
		$html .= '<input class="flat minwidth150" type="'.$type.'"'.$step.' name="'.dol_escape_htmltag($name).'" aria-label="'.dol_escape_htmltag($placeholder).'" placeholder="'.dol_escape_htmltag($placeholder).'"> ';
	}
	$html .= '<button class="butAction" type="submit">'.dol_escape_htmltag($langs->trans($label)).'</button></form>';
	return $html;
}

/** Return configured, unique cash denominations in descending order. */
function agence_cash_denominations()
{
	$raw = getDolGlobalString('AGENCE_CASH_DENOMINATIONS', '10000,5000,2000,1000,500,100,50,25,10,5');
	$values = array();
	foreach (preg_split('/[,;|\s]+/', $raw) as $value) {
		$number = price2num($value);
		if ($number > 0) {
			$values[(string) $number] = $number;
		}
	}
	rsort($values, SORT_NUMERIC);
	return $values;
}

/** Build the detailed physical cash count form used for closing. */
function agence_business_cash_count_form()
{
	global $langs;
	$html = '<details class="agence-cash-count marginbottomonly"><summary class="butAction">'.$langs->trans('DetailedCashCount').'</summary>';
	$html .= '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="padding">';
	$html .= '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="business"><input type="hidden" name="business_action" value="close">';
	$html .= '<input type="hidden" name="id" value="'.((int) GETPOST('id', 'int')).'"><input type="hidden" name="object" value="'.dol_escape_htmltag(GETPOST('object', 'alpha')).'">';
	$html .= '<table class="noborder"><tr class="liste_titre"><th>'.$langs->trans('Denomination').'</th><th>'.$langs->trans('Qty').'</th></tr>';
	foreach (agence_cash_denominations() as $denomination) {
		$html .= '<tr><td class="right">'.price($denomination).'</td><td><input class="flat width75" type="number" min="0" step="1" name="denom_'.dol_escape_htmltag((string) $denomination).'" aria-label="'.dol_escape_htmltag($langs->trans('QuantityForDenomination', price($denomination))).'" value="0"></td></tr>';
	}
	$html .= '</table><label>'.$langs->trans('Comment').' <input class="flat minwidth300" name="comment"></label> <button class="button button-save" type="submit">'.$langs->trans('CloseWithCashCount').'</button></form></details>';
	return $html;
}
