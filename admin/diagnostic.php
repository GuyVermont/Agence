<?php
/* Copyright (C) 2026 iPowerWorld */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofindustrialservice.class.php';

$langs->loadLangs(array('agence@agence', 'admin'));

if (!$user->admin && !$user->hasRight('agence', 'diagnostic', 'read')) accessforbidden();
$service = new SofAgenceIndustrialService($db);
$checks = $service->diagnostics($user);
llxHeader('', $langs->trans('AgencyAdministratorDiagnostic'), '', '', 0, 0, '', '', '', 'mod-agence page-diagnostic');
print load_fiche_titre($langs->trans('AgencyAdministratorDiagnostic'), '', 'stethoscope');
print '<p class="opacitymedium">'.$langs->trans('AgencyDiagnosticHelp').'</p>';
$counts = array('ok'=>0,'warning'=>0,'error'=>0);
foreach ($checks as $check) $counts[$check['status']]++;
print '<div class="fichecenter"><div class="info-box">'.$langs->trans('SuccessfulChecks').' : <strong>'.$counts['ok'].'</strong> &nbsp; '.$langs->trans('Warnings').' : <strong>'.$counts['warning'].'</strong> &nbsp; '.$langs->trans('Errors').' : <strong>'.$counts['error'].'</strong></div></div>';
print '<table class="liste centpercent"><tr class="liste_titre"><th>'.$langs->trans('Category').'</th><th>'.$langs->trans('Check').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Diagnostic').'</th></tr>';
$categoryLabels = array('schema'=>'DiagnosticCategorySchema', 'cron'=>'DiagnosticCategoryScheduledTasks', 'accounting'=>'DiagnosticCategoryAccounting', 'accounts'=>'DiagnosticCategoryFinancialAccounts', 'integrations'=>'DiagnosticCategoryIntegrations', 'operations'=>'DiagnosticCategoryOperations');
$checkLabels = array('jobs_required'=>'RequiredScheduledTasks', 'active_mappings'=>'ActiveAccountingMappings', 'mapping_completeness'=>'AccountingMappingCompleteness', 'cash_accounts'=>'CashDeskCashAccounts', 'payment_mode_accounts'=>'PaymentMethodAccounts', 'notifications'=>'ActiveNotificationRules', 'email_sender'=>'DolibarrEmailSender', 'sms'=>'SmsGateway', 'mobile_money'=>'MobileMoneyPaymentMethods', 'webhooks'=>'ActiveSignedWebhooks', 'payment_connectors'=>'BankAndPaymentConnectors', 'rest_api'=>'DolibarrRestApi', 'technical_errors'=>'OpenTechnicalErrors', 'failed_notifications'=>'FailedNotifications');
$tableLabels = array('sof_notification_config'=>'NotificationRules', 'sof_notification_outbox'=>'NotificationQueue', 'sof_bank_import'=>'BankStatementImports', 'sof_bank_import_line'=>'BankStatementLines', 'sof_recouvrement'=>'CollectionCases', 'sof_recouvrement_action'=>'CollectionActions', 'sof_bulk_import'=>'BulkImports', 'sof_bulk_import_line'=>'BulkImportLines', 'sof_technical_error'=>'TechnicalErrorLog', 'sof_financial_reversal'=>'FinancialReversals', 'sof_archive_log'=>'RetentionLog', 'sof_webhook_endpoint'=>'WebhookEndpoints', 'sof_webhook_delivery'=>'WebhookDeliveries', 'sof_integration_connector'=>'IntegrationConnectors', 'sof_integration_sync'=>'IntegrationSynchronizations', 'sof_config_transfer'=>'ConfigurationTransfers');
foreach ($checks as $check) {
	$badge = $check['status'] === 'ok' ? 'badge-status4' : ($check['status'] === 'warning' ? 'badge-status1' : 'badge-status8');
	$category = isset($categoryLabels[$check['category']]) ? $langs->trans($categoryLabels[$check['category']]) : ucfirst(str_replace('_', ' ', $check['category']));
	$control = isset($checkLabels[$check['code']]) ? $langs->trans($checkLabels[$check['code']]) : (isset($tableLabels[$check['code']]) ? $langs->trans($tableLabels[$check['code']]) : ucfirst(str_replace(array('_','::'), ' ', $check['code'])));
	$statusLabel = $check['status'] === 'ok' ? $langs->trans('CheckSuccessful') : ($check['status'] === 'warning' ? $langs->trans('Warning') : $langs->trans('Error'));
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($category).'</td><td>'.dol_escape_htmltag($control).'</td><td><span class="badge '.$badge.'">'.dol_escape_htmltag($statusLabel).'</span></td><td>'.dol_escape_htmltag($check['message']).'</td></tr>';
}
print '</table>';
llxFooter();
$db->close();
