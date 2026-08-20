<?php
/* Copyright (C) 2026 iPowerWorld */

/**
 * \file       htdocs/custom/agence/lib/agence.lib.php
 * \ingroup    agence
 * \brief      Shared helpers for the Agence module.
 */

/**
 * Return the exact allowlist and validation schema for module constants.
 *
 * @return array<string,array<string,mixed>>
 */
function agence_get_settings_definition()
{
	return array(
		'AGENCE_ENABLE_TRANSVERSAL_SCOPE' => array('label' => 'EnableTransversalScope', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_ENABLE_AUDIT_TRAIL' => array('label' => 'EnableAuditTrail', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_ENABLE_REPORTING' => array('label' => 'EnableReporting', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_REQUIRE_OPEN_SESSION' => array('label' => 'RequireOpenSession', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_MAX_SESSION_HOURS' => array('label' => 'MaxSessionDurationHours', 'type' => 'integer', 'default' => '12', 'min' => 1, 'max' => 8760),
		'AGENCE_GAP_MAJOR_AMOUNT' => array('label' => 'MajorCashGapThreshold', 'type' => 'decimal', 'default' => '1000', 'min' => 0, 'max' => 1000000000000000),
		'AGENCE_GAP_CRITICAL_AMOUNT' => array('label' => 'CriticalCashGapThreshold', 'type' => 'decimal', 'default' => '10000', 'min' => 0, 'max' => 1000000000000000),
		'AGENCE_TAKEPOS_MAX_DISCOUNT_PCT' => array('label' => 'TakePOSMaxDiscountPercent', 'type' => 'decimal', 'default' => '10', 'min' => 0, 'max' => 100),
		'AGENCE_DEPOSIT_ALERT_DAYS' => array('label' => 'UnreconciledDepositAlertDays', 'type' => 'integer', 'default' => '3', 'min' => 1, 'max' => 3650),
		'AGENCE_CASH_DENOMINATIONS' => array('label' => 'CashDenominations', 'type' => 'denominations', 'default' => '10000,5000,2000,1000,500,100,50,25,10,5'),
		'AGENCE_ALLOW_SELF_APPROVAL' => array('label' => 'AllowSelfApproval', 'type' => 'boolean', 'default' => '0'),
		'AGENCE_ENABLE_NOTIFICATIONS' => array('label' => 'EnableMultichannelNotifications', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_SMS_GATEWAY_URL' => array('label' => 'SmsGatewayHttpsUrl', 'type' => 'url', 'default' => ''),
		'AGENCE_SMS_GATEWAY_TOKEN' => array('label' => 'SmsGatewaySecretToken', 'type' => 'secret', 'default' => '', 'max' => 2048),
		'AGENCE_CRITICAL_ESCALATION_MINUTES' => array('label' => 'CriticalEscalationDelayMinutes', 'type' => 'integer', 'default' => '15', 'min' => 1, 'max' => 10080),
		'AGENCE_VALIDATION_ESCALATION_HOURS' => array('label' => 'ValidationEscalationDelayHours', 'type' => 'integer', 'default' => '24', 'min' => 1, 'max' => 8760),
		'AGENCE_AUDIT_RETENTION_DAYS' => array('label' => 'AuditRetentionDays', 'type' => 'integer', 'default' => '3650', 'min' => 365, 'max' => 36500),
		'AGENCE_DOCUMENT_RETENTION_DAYS' => array('label' => 'DocumentRetentionDays', 'type' => 'integer', 'default' => '3650', 'min' => 365, 'max' => 36500),
		'AGENCE_TECH_ERROR_RETENTION_DAYS' => array('label' => 'TechnicalErrorRetentionDays', 'type' => 'integer', 'default' => '730', 'min' => 90, 'max' => 36500),
		'AGENCE_ENABLE_PURGE' => array('label' => 'EnablePurgeAfterRetention', 'type' => 'boolean', 'default' => '0'),
		'AGENCE_ENABLE_WEBHOOKS' => array('label' => 'EnableSignedWebhooks', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_WEBHOOK_TIMEOUT_SECONDS' => array('label' => 'WebhookTimeoutSeconds', 'type' => 'integer', 'default' => '15', 'min' => 5, 'max' => 120),
		'AGENCE_CONNECTOR_TIMEOUT_SECONDS' => array('label' => 'ConnectorTimeoutSeconds', 'type' => 'integer', 'default' => '30', 'min' => 5, 'max' => 300),
		'AGENCE_DEPLOYMENT_ENVIRONMENT' => array('label' => 'DeploymentEnvironment', 'type' => 'environment', 'default' => 'development'),
	);
}

/**
 * Validate and normalize one module setting before it is persisted.
 *
 * The setting name is checked against the exact module allowlist.  The
 * effective values are used for rules involving more than one setting.
 *
 * @param string              $constname       Constant name supplied by the request
 * @param mixed               $rawValue        Raw submitted value
 * @param array<string,mixed> $effectiveValues Current values of other module settings
 * @param string|null         $normalizedValue Normalized value returned on success
 * @param string              $error           Human-readable validation error
 * @return bool
 */
function agence_validate_setting_update($constname, $rawValue, array $effectiveValues, &$normalizedValue, &$error)
{
	$settings = agence_get_settings_definition();
	$normalizedValue = null;
	$error = '';

	if (!is_string($constname) || !array_key_exists($constname, $settings)) {
		$error = 'Paramètre Agence non autorisé.';
		return false;
	}

	$definition = $settings[$constname];
	$type = $definition['type'];
	$value = trim((string) $rawValue);

	if ($type === 'boolean') {
		if (!in_array($value, array('0', '1'), true)) {
			$error = 'La valeur booléenne doit être 0 ou 1.';
			return false;
		}
		$normalizedValue = $value;
	} elseif ($type === 'integer') {
		if (!preg_match('/^[0-9]+$/', $value)) {
			$error = 'La valeur doit être un entier positif.';
			return false;
		}
		$number = (int) $value;
		if ($number < (int) $definition['min'] || $number > (int) $definition['max']) {
			$error = 'La valeur est hors des limites autorisées.';
			return false;
		}
		$normalizedValue = (string) $number;
	} elseif ($type === 'decimal') {
		if (!preg_match('/^[0-9]+(?:[.,][0-9]+)?$/', $value)) {
			$error = 'La valeur doit être un nombre positif.';
			return false;
		}
		$number = (float) str_replace(',', '.', $value);
		if (!is_finite($number) || $number < (float) $definition['min'] || $number > (float) $definition['max']) {
			$error = 'La valeur est hors des limites autorisées.';
			return false;
		}
		$normalizedValue = rtrim(rtrim(sprintf('%.8F', $number), '0'), '.');
		if ($normalizedValue === '') {
			$normalizedValue = '0';
		}
	} elseif ($type === 'url') {
		if ($value === '') {
			$normalizedValue = '';
		} elseif (strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL) || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
			$error = 'La passerelle doit être une URL HTTPS valide.';
			return false;
		} else {
			$normalizedValue = $value;
		}
	} elseif ($type === 'secret') {
		if (strlen($value) > (int) $definition['max'] || preg_match('/[\x00-\x1F\x7F]/', $value)) {
			$error = 'Le secret contient des caractères interdits ou dépasse la taille maximale.';
			return false;
		}
		$normalizedValue = $value;
	} elseif ($type === 'environment') {
		$value = strtolower($value);
		if (!in_array($value, array('development', 'staging', 'production'), true)) {
			$error = 'L’environnement doit être development, staging ou production.';
			return false;
		}
		$normalizedValue = $value;
	} elseif ($type === 'denominations') {
		if ($value === '') {
			$error = 'Au moins une coupure de caisse est obligatoire.';
			return false;
		}
		if (strlen($value) > 4096) {
			$error = 'La liste des coupures est trop longue.';
			return false;
		}
		$parts = preg_split('/[\s,;|]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
		if (!$parts || count($parts) > 100) {
			$error = 'La liste des coupures est vide ou trop longue.';
			return false;
		}
		$denominations = array();
		foreach ($parts as $part) {
			if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $part)) {
				$error = 'Chaque coupure doit être un nombre strictement positif.';
				return false;
			}
			$number = (float) $part;
			if (!is_finite($number) || $number <= 0 || $number > 1000000000000000) {
				$error = 'Chaque coupure doit être un nombre strictement positif.';
				return false;
			}
			$canonical = rtrim(rtrim(sprintf('%.8F', $number), '0'), '.');
			$denominations[$canonical] = $canonical;
		}
		$normalizedValue = implode(',', array_values($denominations));
	} else {
		$error = 'Type de paramètre Agence non pris en charge.';
		return false;
	}

	if (in_array($constname, array('AGENCE_GAP_MAJOR_AMOUNT', 'AGENCE_GAP_CRITICAL_AMOUNT'), true)) {
		$majorRaw = $constname === 'AGENCE_GAP_MAJOR_AMOUNT'
			? $normalizedValue
			: (isset($effectiveValues['AGENCE_GAP_MAJOR_AMOUNT']) ? $effectiveValues['AGENCE_GAP_MAJOR_AMOUNT'] : $settings['AGENCE_GAP_MAJOR_AMOUNT']['default']);
		$criticalRaw = $constname === 'AGENCE_GAP_CRITICAL_AMOUNT'
			? $normalizedValue
			: (isset($effectiveValues['AGENCE_GAP_CRITICAL_AMOUNT']) ? $effectiveValues['AGENCE_GAP_CRITICAL_AMOUNT'] : $settings['AGENCE_GAP_CRITICAL_AMOUNT']['default']);
		if ((float) str_replace(',', '.', (string) $majorRaw) > (float) str_replace(',', '.', (string) $criticalRaw)) {
			$error = 'Le seuil d’écart majeur ne peut pas dépasser le seuil critique.';
			$normalizedValue = null;
			return false;
		}
	}

	return true;
}

/**
 * Translate a stored business code without exposing its technical value in UI.
 * Unknown values are made readable by replacing separators with spaces.
 *
 * @param string $category Code family
 * @param mixed  $value    Stored value
 * @param string $context  Optional object context
 * @return string
 */
function agence_translate_business_code($category, $value, $context = '')
{
	global $langs;
	$code = strtolower(trim((string) $value));
	$maps = array(
		'direction' => array('credit'=>'CashIn', 'debit'=>'CashOut'),
		'payment_mode' => array('liq'=>'CashPayment', 'cash'=>'CashPayment', 'cb'=>'BankCardPayment', 'card'=>'BankCardPayment', 'chq'=>'ChequePayment', 'cheque'=>'ChequePayment', 'vir'=>'BankTransferPayment', 'transfer'=>'BankTransferPayment', 'om'=>'OrangeMoney', 'orange_money'=>'OrangeMoney', 'mm'=>'MobileMoney', 'mobile_money'=>'MobileMoney', 'avoir'=>'CreditNotePayment', 'diff'=>'DeferredPayment'),
		'operation_type' => array('opening'=>'OpeningFloatOperation', 'manual_cash_in'=>'ManualCashInOperation', 'manual_cash_out'=>'ManualCashOutOperation', 'adjustment'=>'CashAdjustmentOperation', 'invoice_payment'=>'InvoicePaymentOperation', 'native_payment'=>'NativePaymentOperation', 'refund'=>'RefundOperation', 'vault_transfer'=>'VaultTransferOperation', 'vault_transfer_received'=>'VaultTransferReceiptOperation', 'bank_deposit'=>'BankDepositOperation', 'takepos_cancel'=>'TakePOSCancellationOperation', 'collection'=>'CollectionOperation', 'financial_reversal'=>'FinancialReversalOperation'),
		'severity' => array('info'=>'InformationSeverity', 'warning'=>'WarningSeverity', 'major'=>'MajorSeverity', 'critical'=>'CriticalSeverity'),
		'session_type' => array('daily'=>'DailySession', 'exceptional'=>'ExceptionalSession'),
		'connector_type' => array('bank'=>'Bank', 'orange_money'=>'OrangeMoney', 'mobile_money'=>'MobileMoney'),
		'channel' => array('internal'=>'InternalChannel', 'email'=>'EmailChannel', 'sms'=>'SmsChannel', 'phone'=>'PhoneChannel'),
		'decision' => array('approve'=>'Approved', 'approved'=>'Approved', 'reject'=>'Rejected', 'rejected'=>'Rejected', 'pending'=>'PendingValidation'),
		'environment' => array('development'=>'Development', 'staging'=>'Staging', 'production'=>'Production'),
		'object_type' => array('session'=>'CashSession', 'refund'=>'Refund', 'deferred_payment'=>'DeferredPayment', 'paiementdiffere'=>'DeferredPayment', 'credit_note'=>'CreditNote', 'customer_po'=>'CustomerPurchaseOrder', 'boncommande'=>'CustomerPurchaseOrder', 'bst'=>'BST', 'manager_instruction'=>'ManagerInstruction', 'instruction'=>'ManagerInstruction', 'deposit'=>'BankDeposit', 'depotbanque'=>'BankDeposit', 'facture'=>'Invoice', 'ecart'=>'CashGap', 'controle'=>'SurpriseControl', 'mouvement'=>'CashMovement', 'recouvrement'=>'CollectionCase', 'collection'=>'CollectionCase', 'reversal'=>'FinancialReversal', 'notification'=>'Notification', 'technical_error'=>'TechnicalError', 'audit'=>'AuditLogEntry', 'document'=>'Document'),
		'role' => array('admin'=>'Administrator', 'administrator'=>'Administrator', 'manager'=>'Manager', 'agency_manager'=>'AgencyManager', 'cashier'=>'Cashier', 'cash_chief'=>'CashChief', 'accountant'=>'Accountant', 'accounting'=>'Accounting', 'controller'=>'Controller', 'auditor'=>'Auditor', 'sales'=>'Sales', 'sales_manager'=>'SalesManager', 'validator'=>'Validator', 'direction'=>'Management'),
		'scope_type' => array('global'=>'GlobalScope', 'all'=>'GlobalScope', 'agency'=>'AgencyScope', 'agence'=>'AgencyScope', 'cashdesk'=>'CashDeskScope', 'caisse'=>'CashDeskScope', 'das'=>'DASScope', 'user'=>'UserScope'),
		'cashdesk_type' => array('cash'=>'PhysicalCashDesk', 'physical'=>'PhysicalCashDesk', 'virtual'=>'VirtualCashDesk', 'takepos'=>'TakePOSCashDesk', 'mobile'=>'MobileCashDesk'),
		'trigger_type' => array('manual'=>'ManualTrigger', 'planned'=>'PlannedTrigger', 'scheduled'=>'PlannedTrigger', 'surprise'=>'SurpriseTrigger', 'random'=>'SurpriseTrigger', 'automatic'=>'AutomaticTrigger'),
		'count_type' => array('opening'=>'OpeningCashCount', 'closing'=>'ClosingCashCount', 'control'=>'ControlCashCount', 'surprise'=>'SurpriseCashCount'),
		'gap_type' => array('cash'=>'CashGapType', 'card'=>'CardGapType', 'cheque'=>'ChequeGapType', 'transfer'=>'TransferGapType', 'mobile_money'=>'MobileMoneyGapType', 'total'=>'TotalGapType'),
		'validation_mode' => array('sequential'=>'SequentialValidation', 'parallel'=>'ParallelValidation', 'single'=>'SingleValidation'),
		'urgency' => array('low'=>'LowPriority', 'normal'=>'NormalPriority', 'high'=>'HighPriority', 'critical'=>'CriticalPriority'),
		'risk' => array('low'=>'LowRisk', 'normal'=>'NormalRisk', 'medium'=>'MediumRisk', 'high'=>'HighRisk', 'critical'=>'CriticalRisk', 'blocked'=>'Blocked'),
		'transfer_type' => array('cash'=>'CashTransfer', 'vault'=>'VaultTransfer', 'bank'=>'BankTransfer', 'internal'=>'InternalTransfer'),
		'payment_component_type' => array('real'=>'ActualPaymentComponent', 'deferred'=>'DeferredPaymentComponent', 'credit_note'=>'CreditNotePaymentComponent'),
		'pos_source' => array('takepos'=>'TakePOS', 'takepos_mapping'=>'TakePOSTerminalMapping'),
		'source_type' => array('invoice'=>'Invoice', 'facture'=>'Invoice', 'payment'=>'Payment', 'paiement'=>'Payment', 'takepos'=>'TakePOS', 'manual'=>'ManualSource', 'import'=>'ImportedSource', 'refund'=>'Refund', 'credit_note'=>'CreditNote', 'customer_po'=>'CustomerPurchaseOrder', 'bst'=>'BST', 'manager_instruction'=>'ManagerInstruction'),
		'signature_status' => array('pending'=>'SignaturePending', 'signed'=>'Signed', 'refused'=>'SignatureRefused', 'not_required'=>'SignatureNotRequired'),
		'accounting_status' => array('0'=>'PendingPosting', '1'=>'PostingInProgress', '2'=>'PostingFailed', '3'=>'PostingReadyForRetry', '4'=>'Posted'),
		'reconcile_status' => array('0'=>'PendingReconciliation', '1'=>'Reconciled', '2'=>'ReconciliationFailed', '9'=>'Canceled'),
		'billing_status' => array('0'=>'PendingBilling', '1'=>'Invoiced', '2'=>'BillingFailed', '9'=>'Canceled'),
		'validation_status' => array('0'=>'PendingValidation', '1'=>'Validated', '2'=>'Rejected'),
		'use_status' => array('0'=>'Available', '1'=>'PartiallyUsed', '2'=>'Consumed', '9'=>'Canceled'),
		'freeze_status' => array('0'=>'CashDeskAvailable', '1'=>'CashDeskFrozen'),
		'recipient_type' => array('address'=>'AddressOrNumber', 'user'=>'DolibarrUser', 'role'=>'AgencyRole'),
		'event_code' => array('*'=>'AllNotificationEvents', 'critical_alert'=>'CriticalAlertEvent', 'validation_overdue'=>'OverdueValidationEvent', 'collection_reminder1'=>'FirstCollectionReminderEvent', 'collection_reminder2'=>'SecondCollectionReminderEvent', 'collection_formal_notice'=>'FormalNoticeEvent', 'collection_dispute'=>'CollectionDisputeEvent', 'financial_reversal_requested'=>'FinancialReversalRequestedEvent', 'financial_reversal_approved'=>'FinancialReversalApprovedEvent', 'financial_reversal_rejected'=>'FinancialReversalRejectedEvent', 'cash_closure_completed'=>'CashClosureCompletedEvent', 'validation_decided'=>'ValidationDecidedEvent', 'refund_completed'=>'RefundCompletedEvent', 'bank_deposit_completed'=>'BankDepositCompletedEvent', 'alert_created'=>'AlertCreatedEvent'),
		'collection_stage' => array('new'=>'NewCollectionCase', 'reminder1'=>'FirstReminder', 'reminder2'=>'SecondReminder', 'formal_notice'=>'FormalNotice', 'promise'=>'PaymentPromise', 'dispute'=>'Dispute', 'closed'=>'Closed'),
		'notification_status' => array('0'=>'PendingDelivery', '1'=>'Sent', '2'=>'DeliveryRetryScheduled', '3'=>'DeliveryFailedPermanently'),
		'technical_error_status' => array('0'=>'Open', '1'=>'RetryScheduled', '2'=>'Resolved', '3'=>'RetryAbandoned'),
		'reversal_status' => array('0'=>'PendingValidation', '2'=>'Approved', '9'=>'Rejected'),
		'archive_action' => array('archive'=>'Archived', 'purge'=>'Purged'),
		'operation_code' => array('notification_delivery'=>'NotificationDeliveryOperation', 'collection_sync'=>'CollectionSynchronizationOperation', 'accounting_session'=>'SessionPostingOperation'),
	);
	$statusMaps = array(
		'session' => array(0=>'Draft', 1=>'Opened', 2=>'Operating', 3=>'Paused', 4=>'ControlInProgress', 5=>'ClosingInProgress', 6=>'Closed', 7=>'Validated', 8=>'Accounted', 9=>'Canceled', 10=>'Blocked'),
		'paiementdiffere' => array(0=>'Draft', 1=>'Validated', 2=>'Invoiced', 3=>'PartiallyPaid', 4=>'Paid', 5=>'Late', 6=>'Disputed', 7=>'Closed', 9=>'Canceled'),
		'boncommande' => array(0=>'Received', 1=>'Checked', 2=>'Used', 3=>'PartiallyUsed', 4=>'Expired', 5=>'Rejected', 6=>'Invoiced', 7=>'Paid'),
		'bst' => array(0=>'Issued', 1=>'Validated', 2=>'Consumed', 3=>'Invoiced', 4=>'Paid', 9=>'Canceled', 10=>'Disputed'),
		'instruction' => array(0=>'PendingValidation', 1=>'Accepted', 2=>'Executed', 3=>'Invoiced', 4=>'Paid', 5=>'Rejected', 9=>'Canceled'),
		'avoir' => array(0=>'PendingValidation', 1=>'PartiallyUsed', 2=>'Consumed', 9=>'Canceled'),
		'controle' => array(0=>'Planned', 1=>'ControlInProgress', 2=>'Completed', 9=>'Canceled'),
		'ecart' => array(0=>'Open', 1=>'UnderReview', 2=>'Approved', 3=>'Processed', 9=>'Canceled'),
		'cloture' => array(0=>'Draft', 1=>'PendingValidation', 2=>'Validated', 3=>'Accounted', 9=>'Canceled'),
		'transfert' => array(0=>'Draft', 1=>'Sent', 2=>'Received', 9=>'Canceled'),
		'depotbanque' => array(0=>'Draft', 1=>'Deposited', 2=>'PendingReconciliation', 3=>'Reconciled', 9=>'Canceled'),
		'validation' => array(0=>'PendingValidation', 1=>'Approved', 2=>'Rejected'),
		'alerte' => array(0=>'Open', 1=>'Read', 2=>'Closed'),
		'agence' => array(1=>'Active', 2=>'Suspended', 3=>'Closed', 4=>'Test', 9=>'Archived'),
		'caisse' => array(0=>'Draft', 1=>'Active', 2=>'Suspended', 9=>'Archived'),
		'das' => array(0=>'Disabled', 1=>'Active'),
		'mouvement' => array(0=>'Canceled', 1=>'Validated'),
		'remboursement' => array(0=>'Requested', 1=>'PendingValidation', 2=>'Approved', 3=>'Executed', 4=>'Accounted', 8=>'Rejected', 9=>'Canceled'),
	);
	if ($category === 'status') {
		$number = (int) $value;
		if (isset($statusMaps[$context][$number])) return $langs->trans($statusMaps[$context][$number]);
		$defaults = array(0=>'Inactive', 1=>'Active', 2=>'Closed', 3=>'Processed', 4=>'Accounted', 8=>'Rejected', 9=>'Archived');
		return isset($defaults[$number]) ? $langs->trans($defaults[$number]) : $langs->trans('StatusNumber', $number);
	}
	if (isset($maps[$category][$code])) return $langs->trans($maps[$category][$code]);
	$readable = trim(preg_replace('/\s+/', ' ', str_replace(array('_','-'), ' ', (string) $value)));
	return $readable === '' ? '-' : ucfirst($readable);
}

/**
 * Prepare admin tabs.
 *
 * @return array<int,array<int,string>>
 */
function agenceAdminPrepareHead()
{
	global $langs;

	$langs->load('agence@agence');
	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath('/agence/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath('/agence/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';

	return $head;
}

/**
 * Return initial operational domains displayed on the landing dashboard.
 *
 * @param Translate $langs Language handler
 * @return array<int,array<string,string>>
 */
function agence_get_operational_domains($langs)
{
	return array(
		array('label' => $langs->trans('DomainAgencyManagement'), 'description' => $langs->trans('DomainAgencyManagementDesc'), 'reuse' => $langs->trans('ReuseUsersGroupsBankAccounts'), 'status' => $langs->trans('PlannedPhase1')),
		array('label' => $langs->trans('DomainCashDesk'), 'description' => $langs->trans('DomainCashDeskDesc'), 'reuse' => $langs->trans('ReuseBankPaymentsTakePOS'), 'status' => $langs->trans('PlannedPhase2')),
		array('label' => $langs->trans('DomainDeferredPayments'), 'description' => $langs->trans('DomainDeferredPaymentsDesc'), 'reuse' => $langs->trans('ReuseThirdpartiesInvoicesDocuments'), 'status' => $langs->trans('PlannedPhase3')),
		array('label' => $langs->trans('DomainRefundCredit'), 'description' => $langs->trans('DomainRefundCreditDesc'), 'reuse' => $langs->trans('ReuseInvoicesCreditNotesPayments'), 'status' => $langs->trans('PlannedPhase4')),
		array('label' => $langs->trans('DomainControlWorkflow'), 'description' => $langs->trans('DomainControlWorkflowDesc'), 'reuse' => $langs->trans('ReuseUsersNotificationsBlockedLog'), 'status' => $langs->trans('PlannedPhase5')),
		array('label' => $langs->trans('DomainReporting'), 'description' => $langs->trans('DomainReportingDesc'), 'reuse' => $langs->trans('ReuseExportsAccountingReports'), 'status' => $langs->trans('PlannedAllPhases')),
	);
}

/**
 * Return reporting dashboards planned for transversal and local management.
 *
 * @param Translate $langs Language handler
 * @return array<int,array<string,string>>
 */
function agence_get_reporting_dashboards($langs)
{
	return array(
		array('label' => $langs->trans('DashboardAgencyDaily'), 'audience' => $langs->trans('AudienceAgencyManagers'), 'indicators' => $langs->trans('IndicatorsAgencyDaily')),
		array('label' => $langs->trans('DashboardCashier'), 'audience' => $langs->trans('AudienceCashSupervision'), 'indicators' => $langs->trans('IndicatorsCashier')),
		array('label' => $langs->trans('DashboardDeferred'), 'audience' => $langs->trans('AudienceFinanceSales'), 'indicators' => $langs->trans('IndicatorsDeferred')),
		array('label' => $langs->trans('DashboardControlAudit'), 'audience' => $langs->trans('AudienceAuditControl'), 'indicators' => $langs->trans('IndicatorsControlAudit')),
		array('label' => $langs->trans('DashboardDirection'), 'audience' => $langs->trans('AudienceDirection'), 'indicators' => $langs->trans('IndicatorsDirection')),
		array('label' => $langs->trans('DashboardAccounting'), 'audience' => $langs->trans('AudienceAccountingDaf'), 'indicators' => $langs->trans('IndicatorsAccounting')),
	);
}
