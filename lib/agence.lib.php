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
		'AGENCE_MAX_SESSION_HOURS' => array('label' => 'Durée maximale d’une session (heures)', 'type' => 'integer', 'default' => '12', 'min' => 1, 'max' => 8760),
		'AGENCE_GAP_MAJOR_AMOUNT' => array('label' => 'Seuil d’écart majeur', 'type' => 'decimal', 'default' => '1000', 'min' => 0, 'max' => 1000000000000000),
		'AGENCE_GAP_CRITICAL_AMOUNT' => array('label' => 'Seuil d’écart critique', 'type' => 'decimal', 'default' => '10000', 'min' => 0, 'max' => 1000000000000000),
		'AGENCE_TAKEPOS_MAX_DISCOUNT_PCT' => array('label' => 'Remise TakePOS maximale (%)', 'type' => 'decimal', 'default' => '10', 'min' => 0, 'max' => 100),
		'AGENCE_DEPOSIT_ALERT_DAYS' => array('label' => 'Délai d’alerte dépôt non rapproché (jours)', 'type' => 'integer', 'default' => '3', 'min' => 1, 'max' => 3650),
		'AGENCE_CASH_DENOMINATIONS' => array('label' => 'CashDenominations', 'type' => 'denominations', 'default' => '10000,5000,2000,1000,500,100,50,25,10,5'),
		'AGENCE_ALLOW_SELF_APPROVAL' => array('label' => 'AllowSelfApproval', 'type' => 'boolean', 'default' => '0'),
		'AGENCE_ENABLE_NOTIFICATIONS' => array('label' => 'Activer les notifications multicanales', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_SMS_GATEWAY_URL' => array('label' => 'Passerelle SMS HTTPS', 'type' => 'url', 'default' => ''),
		'AGENCE_SMS_GATEWAY_TOKEN' => array('label' => 'Jeton secret de la passerelle SMS', 'type' => 'secret', 'default' => '', 'max' => 2048),
		'AGENCE_CRITICAL_ESCALATION_MINUTES' => array('label' => 'Délai d’escalade critique (minutes)', 'type' => 'integer', 'default' => '15', 'min' => 1, 'max' => 10080),
		'AGENCE_VALIDATION_ESCALATION_HOURS' => array('label' => 'Délai d’escalade des validations (heures)', 'type' => 'integer', 'default' => '24', 'min' => 1, 'max' => 8760),
		'AGENCE_AUDIT_RETENTION_DAYS' => array('label' => 'Conservation des audits (jours)', 'type' => 'integer', 'default' => '3650', 'min' => 365, 'max' => 36500),
		'AGENCE_DOCUMENT_RETENTION_DAYS' => array('label' => 'Conservation des documents (jours)', 'type' => 'integer', 'default' => '3650', 'min' => 365, 'max' => 36500),
		'AGENCE_TECH_ERROR_RETENTION_DAYS' => array('label' => 'Conservation des erreurs techniques (jours)', 'type' => 'integer', 'default' => '730', 'min' => 90, 'max' => 36500),
		'AGENCE_ENABLE_PURGE' => array('label' => 'Autoriser la purge après conservation', 'type' => 'boolean', 'default' => '0'),
		'AGENCE_ENABLE_WEBHOOKS' => array('label' => 'Activer les webhooks signés', 'type' => 'boolean', 'default' => '1'),
		'AGENCE_WEBHOOK_TIMEOUT_SECONDS' => array('label' => 'Délai maximal webhook (secondes)', 'type' => 'integer', 'default' => '15', 'min' => 5, 'max' => 120),
		'AGENCE_CONNECTOR_TIMEOUT_SECONDS' => array('label' => 'Délai maximal connecteur (secondes)', 'type' => 'integer', 'default' => '30', 'min' => 5, 'max' => 300),
		'AGENCE_DEPLOYMENT_ENVIRONMENT' => array('label' => 'Environnement de déploiement', 'type' => 'environment', 'default' => 'development'),
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
