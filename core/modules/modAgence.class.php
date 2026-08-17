<?php
/* Copyright (C) 2026 iPowerWorld
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/custom/agence/core/modules/modAgence.class.php
 * \ingroup    agence
 * \brief      Module descriptor for SOFITOUL agency management.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for Agence.
 */
class modAgence extends DolibarrModules
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 510000;
		$this->rights_class = 'agence';
		$this->family = 'financial';
		$this->module_position = '42';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleAgenceDesc';
		$this->descriptionlong = 'ModuleAgenceDescLong';
		$this->editor_name = 'iPowerWorld';
		$this->editor_url = 'https://ipowerworld.net';
		$this->version = '2.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'building';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 1,
			'printing' => 0,
			'theme' => 0,
			'css' => array('/agence/css/agence.css.php'),
			'js' => array('/agence/js/agence_takepos_session_check.js'),
			'hooks' => array(
				'data' => array(
					'thirdpartycard',
					'invoicecard',
					'invoicedao',
					'paiementcard',
					'bankcard',
					'banktransactionlist',
					'takeposfrontend',
					'takeposinvoice',
					'takeposproductsearch',
					'globalcard'
				),
				'entity' => '1'
			),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0
		);

		$this->dirs = array('/agence/temp', '/agence/reports', '/agence/exports');
		$this->config_page_url = array('setup.php@agence');
		$this->hidden = getDolGlobalInt('MODULE_AGENCE_DISABLED');
		$this->depends = array('modSociete', 'modFacture', 'modBanque');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('agence@agence');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(22, 0);
		$this->need_javascript_ajax = 0;
		$this->warnings_activation = array('always' => 'AgenceActivationWarning');
		$this->warnings_activation_ext = array();

		$this->const = array(
			1 => array('AGENCE_ENABLE_TRANSVERSAL_SCOPE', 'chaine', '1', 'Enable transversal perimeter management', 0, 'current', 1),
			2 => array('AGENCE_ENABLE_AUDIT_TRAIL', 'chaine', '1', 'Enable sensitive action audit trail', 0, 'current', 1),
			3 => array('AGENCE_ENABLE_REPORTING', 'chaine', '1', 'Enable agency reporting dashboards', 0, 'current', 1),
			4 => array('AGENCE_REQUIRE_OPEN_SESSION', 'chaine', '1', 'Require open cash session for cash operations', 0, 'current', 1),
			5 => array('AGENCE_MAX_SESSION_HOURS', 'chaine', '12', 'Maximum duration of an open session before alert', 0, 'current', 1),
			6 => array('AGENCE_GAP_MAJOR_AMOUNT', 'chaine', '1000', 'Major cash gap threshold', 0, 'current', 1),
			7 => array('AGENCE_GAP_CRITICAL_AMOUNT', 'chaine', '10000', 'Critical cash gap threshold', 0, 'current', 1),
			8 => array('AGENCE_TAKEPOS_MAX_DISCOUNT_PCT', 'chaine', '10', 'TakePOS maximum discount before alert', 0, 'current', 1),
			9 => array('AGENCE_DEPOSIT_ALERT_DAYS', 'chaine', '3', 'Days before an unreconciled deposit alert', 0, 'current', 1),
			10 => array('AGENCE_CASH_DENOMINATIONS', 'chaine', '10000,5000,2000,1000,500,100,50,25,10,5', 'Cash denominations used for physical counts', 0, 'current', 1),
			11 => array('AGENCE_ALLOW_SELF_APPROVAL', 'chaine', '0', 'Allow requester or cashier to approve their own financial operation', 0, 'current', 1),
			12 => array('AGENCE_ENABLE_NOTIFICATIONS', 'chaine', '1', 'Enable notification outbox processing', 0, 'current', 1),
			13 => array('AGENCE_SMS_GATEWAY_URL', 'chaine', '', 'HTTPS endpoint of the SMS gateway', 0, 'current', 1),
			14 => array('AGENCE_SMS_GATEWAY_TOKEN', 'chaine', '', 'Secret bearer token of the SMS gateway', 0, 'current', 1),
			15 => array('AGENCE_CRITICAL_ESCALATION_MINUTES', 'chaine', '15', 'Delay before escalating a critical alert', 0, 'current', 1),
			16 => array('AGENCE_VALIDATION_ESCALATION_HOURS', 'chaine', '24', 'Delay before escalating an overdue validation', 0, 'current', 1),
			17 => array('AGENCE_AUDIT_RETENTION_DAYS', 'chaine', '3650', 'Audit retention period', 0, 'current', 1),
			18 => array('AGENCE_DOCUMENT_RETENTION_DAYS', 'chaine', '3650', 'Document retention period', 0, 'current', 1),
			19 => array('AGENCE_TECH_ERROR_RETENTION_DAYS', 'chaine', '730', 'Technical error retention period', 0, 'current', 1),
			20 => array('AGENCE_ENABLE_PURGE', 'chaine', '0', 'Enable irreversible purge after retention', 0, 'current', 1)
		);

		if (!isModEnabled('agence')) {
			$conf->agence = new stdClass();
			$conf->agence->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array(
			0 => array(
				'label' => 'DetectAgencyOperationalAlerts',
				'jobtype' => 'method',
				'class' => '/agence/class/sofalerte.class.php',
				'objectname' => 'SofAlerte',
				'method' => 'detectAlerts',
				'parameters' => '',
				'comment' => 'Detect non-closed sessions, overdue deferred payments, unreconciled deposits and critical gaps',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("agence")',
				'priority' => 50,
			),
			1 => array(
				'label' => 'RunAgencyIndustrialOperations',
				'jobtype' => 'method',
				'class' => '/agence/class/sofindustrialservice.class.php',
				'objectname' => 'SofAgenceIndustrialService',
				'method' => 'runScheduledOperations',
				'parameters' => '',
				'comment' => 'Escalate alerts and validations, send queued notifications, synchronize collections and retry controlled failures',
				'frequency' => 15,
				'unitfrequency' => 60,
				'status' => 1,
				'test' => 'isModEnabled("agence")',
				'priority' => 51,
			)
		);

		$this->rights = array();
		$r = 0;
		$this->addRight($r, 1, 'ReadAgencies', 'agence', 'read');
		$this->addRight($r, 2, 'CreateModifyDisableAgencies', 'agence', 'write');
		$this->addRight($r, 3, 'ReadCashDesks', 'caisse', 'read');
		$this->addRight($r, 4, 'CreateModifyDisableCashDesks', 'caisse', 'write');
		$this->addRight($r, 5, 'OpenCashSession', 'session', 'open');
		$this->addRight($r, 6, 'CloseCashSession', 'session', 'close');
		$this->addRight($r, 7, 'ValidateCashClosing', 'session', 'validate');
		$this->addRight($r, 8, 'RecordCashIn', 'mouvement', 'cashin');
		$this->addRight($r, 9, 'RecordMixedPayment', 'mouvement', 'mixedpayment');
		$this->addRight($r, 10, 'RecordDeferredPayment', 'paiementdiffere', 'create');
		$this->addRight($r, 11, 'ValidateDeferredPayment', 'paiementdiffere', 'validate');
		$this->addRight($r, 12, 'CreateValidateCustomerPurchaseOrder', 'boncommande', 'validate');
		$this->addRight($r, 13, 'CreateValidateBST', 'bst', 'validate');
		$this->addRight($r, 14, 'CreateValidateManagerInstruction', 'instruction', 'validate');
		$this->addRight($r, 15, 'RequestRefund', 'remboursement', 'request');
		$this->addRight($r, 16, 'ValidateRefund', 'remboursement', 'validate');
		$this->addRight($r, 17, 'ExecuteRefund', 'remboursement', 'execute');
		$this->addRight($r, 18, 'CreateCreditNoteFollowup', 'avoir', 'create');
		$this->addRight($r, 19, 'ValidateCreditNoteFollowup', 'avoir', 'validate');
		$this->addRight($r, 20, 'UseCreditNoteFollowup', 'avoir', 'use');
		$this->addRight($r, 21, 'ManageCashGap', 'ecart', 'manage');
		$this->addRight($r, 22, 'RunSurpriseControl', 'controle', 'create');
		$this->addRight($r, 23, 'FreezeCashDeskDuringControl', 'controle', 'freeze');
		$this->addRight($r, 24, 'CreateVaultTransfer', 'transfert', 'create');
		$this->addRight($r, 25, 'CreateBankDeposit', 'depotbanque', 'create');
		$this->addRight($r, 26, 'ReconcileBankDeposit', 'depotbanque', 'reconcile');
		$this->addRight($r, 27, 'PostToAccounting', 'compta', 'post');
		$this->addRight($r, 28, 'ReadReports', 'report', 'read');
		$this->addRight($r, 29, 'ExportReports', 'report', 'export');
		$this->addRight($r, 30, 'ReadDirectionDashboards', 'dashboard', 'direction');
		$this->addRight($r, 31, 'ReadAuditControlDashboards', 'dashboard', 'audit');
		$this->addRight($r, 32, 'ManageTransversalScopes', 'scope', 'write');
		$this->addRight($r, 33, 'AdministerAgencySettings', 'parametre', 'write');
		$this->addRight($r, 34, 'ReadAuditTrail', 'audit', 'read');
		$this->addRight($r, 35, 'AdministerWorkflows', 'workflow', 'write');
		$this->addRight($r, 36, 'ManageAgencyNotifications', 'notification', 'manage');
		$this->addRight($r, 37, 'ImportBankStatements', 'bankimport', 'import');
		$this->addRight($r, 38, 'ReconcileImportedPayments', 'bankimport', 'reconcile');
		$this->addRight($r, 39, 'ManageDebtCollection', 'recouvrement', 'manage');
		$this->addRight($r, 40, 'RunAgencyBulkImports', 'bulkimport', 'run');
		$this->addRight($r, 41, 'ManageTechnicalRetries', 'technicalerror', 'manage');
		$this->addRight($r, 42, 'RequestFinancialReversal', 'reversal', 'request');
		$this->addRight($r, 43, 'ApproveFinancialReversal', 'reversal', 'approve');
		$this->addRight($r, 44, 'ManageRetentionAndPurge', 'archive', 'manage');
		$this->addRight($r, 45, 'ReadAgencyDiagnostics', 'diagnostic', 'read');

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'ModuleAgenceName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'agence',
			'leftmenu' => '',
			'url' => '/agence/index.php',
			'langs' => 'agence@agence',
			'position' => 1000,
			'enabled' => 'isModEnabled("agence")',
			'perms' => '$user->admin || $user->hasRight("agence", "agence", "read") || $user->hasRight("agence", "caisse", "read") || $user->hasRight("agence", "session", "open") || $user->hasRight("agence", "session", "validate") || $user->hasRight("agence", "mouvement", "cashin") || $user->hasRight("agence", "paiementdiffere", "create") || $user->hasRight("agence", "remboursement", "request") || $user->hasRight("agence", "controle", "create") || $user->hasRight("agence", "compta", "post") || $user->hasRight("agence", "report", "read") || $user->hasRight("agence", "scope", "write") || $user->hasRight("agence", "parametre", "write") || $user->hasRight("agence", "notification", "manage") || $user->hasRight("agence", "bankimport", "import") || $user->hasRight("agence", "recouvrement", "manage") || $user->hasRight("agence", "technicalerror", "manage") || $user->hasRight("agence", "diagnostic", "read")',
			'target' => '',
			'user' => 0,
		);

		$this->addLeftMenu($r, 'AgenceDashboard', '/agence/index.php', 'agence_home', '$user->hasRight("agence", "agence", "read") || $user->hasRight("agence", "report", "read")', 'fa-chart-line');
		$this->addLeftMenu($r, 'Agencies', '/agence/agence/list.php', 'agence_agence', '$user->hasRight("agence", "agence", "read")', 'fa-building');
		$this->addLeftMenu($r, 'AgencyUserScopes', '/agence/agence/list.php?object=agenceuser', 'agence_scopes', '$user->hasRight("agence", "scope", "write")', 'fa-user-tag');
		$this->addLeftMenu($r, 'DASList', '/agence/das/list.php', 'agence_das', '$user->hasRight("agence", "agence", "read")', 'fa-sitemap');
		$this->addLeftMenu($r, 'CashDesks', '/agence/caisse/list.php', 'agence_caisse', '$user->hasRight("agence", "caisse", "read")', 'fa-cash-register');
		$this->addLeftMenu($r, 'MyCashDesk', '/agence/session/my.php', 'agence_my_cashdesk', '$user->hasRight("agence", "session", "open") || $user->hasRight("agence", "mouvement", "cashin")', 'fa-hand-holding-dollar');
		$this->addLeftMenu($r, 'CashSessions', '/agence/session/list.php', 'agence_session', '$user->hasRight("agence", "session", "open") || $user->hasRight("agence", "session", "close")', 'fa-clock');
		$this->addLeftMenu($r, 'CashSupervision', '/agence/session/supervision.php', 'agence_supervision', '$user->hasRight("agence", "dashboard", "direction") || $user->hasRight("agence", "session", "validate") || $user->hasRight("agence", "audit", "read")', 'fa-chart-line');
		$this->addLeftMenu($r, 'FinancialFlows', '/agence/mouvement/list.php', 'agence_mouvement', '$user->hasRight("agence", "mouvement", "cashin")', 'fa-money-bill-transfer');
		$this->addLeftMenu($r, 'RecordCustomerDeposit', '/agence/mouvement/acompte.php', 'agence_customer_deposit', '$user->hasRight("agence", "mouvement", "cashin")', 'fa-hand-holding-dollar');
		$this->addLeftMenu($r, 'DeferredPayments', '/agence/differe/list.php', 'agence_differe', '$user->hasRight("agence", "paiementdiffere", "create") || $user->hasRight("agence", "paiementdiffere", "validate")', 'fa-file-invoice-dollar');
		$this->addLeftMenu($r, 'Receivables', '/agence/creance/list.php', 'agence_receivables', '$user->hasRight("agence", "paiementdiffere", "create") || $user->hasRight("agence", "paiementdiffere", "validate") || $user->hasRight("agence", "report", "read")', 'fa-calendar-xmark');
		$this->addLeftMenu($r, 'RefundsAndCredits', '/agence/remboursement/list.php', 'agence_refund_credit', '$user->hasRight("agence", "remboursement", "request") || $user->hasRight("agence", "avoir", "create")', 'fa-rotate-left');
		$this->addLeftMenu($r, 'RequestRefund', '/agence/remboursement/request.php', 'agence_refund_request', '$user->hasRight("agence", "remboursement", "request")', 'fa-hand-holding-dollar');
		$this->addLeftMenu($r, 'ControlsAndAudit', '/agence/controle/list.php', 'agence_controle', '$user->hasRight("agence", "controle", "create") || $user->hasRight("agence", "audit", "read")', 'fa-shield-halved');
		$this->addLeftMenu($r, 'BankDeposits', '/agence/banque/list.php', 'agence_banque', '$user->hasRight("agence", "depotbanque", "create") || $user->hasRight("agence", "depotbanque", "reconcile")', 'fa-building-columns');
		$this->addLeftMenu($r, 'Imports et rapprochements', '/agence/admin/industrial.php?section=imports', 'agence_imports', '$user->hasRight("agence", "bankimport", "import") || $user->hasRight("agence", "bankimport", "reconcile") || $user->hasRight("agence", "bulkimport", "run")', 'fa-file-import');
		$this->addLeftMenu($r, 'Recouvrement clients', '/agence/admin/industrial.php?section=collections', 'agence_collections', '$user->hasRight("agence", "recouvrement", "manage")', 'fa-comments-dollar');
		$this->addLeftMenu($r, 'Annulations financières', '/agence/admin/industrial.php?section=reversals', 'agence_reversals', '$user->hasRight("agence", "reversal", "request") || $user->hasRight("agence", "reversal", "approve")', 'fa-arrow-rotate-left');
		$this->addLeftMenu($r, 'WorkflowValidation', '/agence/workflow/list.php', 'agence_workflow', '$user->hasRight("agence", "workflow", "write")', 'fa-code-branch');
		$this->addLeftMenu($r, 'MyPendingValidations', '/agence/workflow/my.php', 'agence_my_validations', '$user->hasRight("agence", "session", "validate") || $user->hasRight("agence", "remboursement", "validate") || $user->hasRight("agence", "boncommande", "validate") || $user->hasRight("agence", "bst", "validate") || $user->hasRight("agence", "instruction", "validate")', 'fa-check-double');
		$this->addLeftMenu($r, 'ReportsStatistics', '/agence/report/index.php', 'agence_report', '$user->hasRight("agence", "report", "read")', 'fa-chart-pie');
		$this->addLeftMenu($r, 'TransversalManagement', '/agence/report/transversal.php', 'agence_transversal', '$user->hasRight("agence", "dashboard", "direction") || $user->hasRight("agence", "scope", "write")', 'fa-sitemap');
		$this->addLeftMenu($r, 'AuditTrail', '/agence/audit/list.php', 'agence_audit', '$user->hasRight("agence", "audit", "read")', 'fa-fingerprint');
		$this->addLeftMenu($r, 'TerminalMappings', '/agence/admin/terminal_mapping.php', 'agence_terminal_mapping', '$user->hasRight("agence", "caisse", "write") || $user->hasRight("agence", "parametre", "write")', 'fa-cash-register');
		$this->addLeftMenu($r, 'AgencyAccountingPosting', '/agence/admin/accounting.php', 'agence_accounting', '$user->hasRight("agence", "compta", "post")', 'fa-scale-balanced');
		$this->addLeftMenu($r, 'Notifications et escalades', '/agence/admin/industrial.php?section=notifications', 'agence_notifications', '$user->hasRight("agence", "notification", "manage")', 'fa-bell');
		$this->addLeftMenu($r, 'Erreurs et reprises', '/agence/admin/industrial.php?section=errors', 'agence_errors', '$user->hasRight("agence", "technicalerror", "manage")', 'fa-triangle-exclamation');
		$this->addLeftMenu($r, 'Archivage et conservation', '/agence/admin/industrial.php?section=retention', 'agence_retention', '$user->hasRight("agence", "archive", "manage")', 'fa-box-archive');
		$this->addLeftMenu($r, 'Diagnostic Agence', '/agence/admin/diagnostic.php', 'agence_diagnostic', '$user->hasRight("agence", "diagnostic", "read") || $user->admin', 'fa-stethoscope');
		$this->addLeftMenu($r, 'Setup', '/agence/admin/setup.php', 'agence_setup', '$user->hasRight("agence", "parametre", "write")', 'fa-gear');
	}

	/**
	 * Register a module permission.
	 *
	 * @param int    $r      Permission array pointer
	 * @param int    $offset Numeric offset
	 * @param string $label  Translation key
	 * @param string $object Permission object
	 * @param string $action Permission action
	 * @return void
	 */
	private function addRight(&$r, $offset, $label, $object, $action)
	{
		$this->rights[$r][0] = $this->numero + $offset;
		$this->rights[$r][1] = $label;
		$this->rights[$r][4] = $object;
		$this->rights[$r][5] = $action;
		$r++;
	}

	/**
	 * Register a left menu entry.
	 *
	 * @param int    $r        Menu array pointer
	 * @param string $title    Translation key
	 * @param string $url      Relative URL
	 * @param string $leftmenu Left menu key
	 * @param string $perms    Permission expression
	 * @param string $picto    FontAwesome class
	 * @return void
	 */
	private function addLeftMenu(&$r, $title, $url, $leftmenu, $perms, $picto)
	{
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=agence',
			'type' => 'left',
			'titre' => $title,
			'prefix' => img_picto('', $picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'agence',
			'leftmenu' => $leftmenu,
			'url' => $url,
			'langs' => 'agence@agence',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("agence")',
			'perms' => $perms,
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Init module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables('/agence/sql/');
		if ($result < 0) {
			return -1;
		}
		if ($this->ensureSchemaUpgrades() < 0) {
			return -1;
		}
		$this->seedPaymentModes();
		// Dolibarr's menu insertion is not idempotent: an existing first menu
		// makes the complete activation transaction roll back. Install all other
		// module components first, then replace only this module's owned menus.
		$moduleMenus = $this->menu;
		$this->menu = array();
		$result = $this->_init($sql, $options);
		$this->menu = $moduleMenus;
		if ($result > 0) {
			if ($this->delete_menus() > 0 || $this->insert_menus() > 0) {
				return -1;
			}
			// _init() may skip newly added cron descriptors when upgrading an
			// already enabled module; this public helper is idempotent by label.
			if ($this->insert_cronjobs() < 0) {
				return -1;
			}
			$cronSql = 'UPDATE '.$this->db->prefix()."cronjob SET status = 1 WHERE module_name = 'agence'";
			$cronSql .= " AND ((objectname = 'SofAlerte' AND methodename = 'detectAlerts') OR (objectname = 'SofAgenceIndustrialService' AND methodename = 'runScheduledOperations'))";
			$this->db->query($cronSql);
		}
		return $result > 0 ? $result : -1;
	}

	/** Apply additive schema upgrades when an existing installation is re-enabled. */
	private function ensureSchemaUpgrades()
	{
		$upgrades = array(
			$this->db->prefix().'sof_caisse' => array(
				'fk_bank_account_card' => array('type' => 'integer'),
				'fk_bank_account_cheque' => array('type' => 'integer'),
				'fk_bank_account_mobile' => array('type' => 'integer'),
				'fk_bank_account_other' => array('type' => 'integer'),
			),
			$this->db->prefix().'sof_caisse_session' => array(
				'accounting_attempts' => array('type' => 'integer', 'default' => 0, 'notnull' => 1),
				'accounting_error' => array('type' => 'text'),
				'date_accounting' => array('type' => 'datetime'),
				'fk_user_accounting' => array('type' => 'integer'),
			),
			$this->db->prefix().'sof_caisse_mouvement' => array(
				'accounting_attempts' => array('type' => 'integer', 'default' => 0, 'notnull' => 1),
				'accounting_error' => array('type' => 'text'),
				'date_accounting_attempt' => array('type' => 'datetime'),
			),
			$this->db->prefix().'sof_paiement_differe' => array(
				'date_validation' => array('type' => 'datetime'),
				'fk_user_validator' => array('type' => 'integer'),
				'date_dispute' => array('type' => 'datetime'),
				'fk_user_dispute' => array('type' => 'integer'),
				'regularization_reason' => array('type' => 'text'),
				'date_regularization' => array('type' => 'datetime'),
				'fk_user_regularization' => array('type' => 'integer'),
				'date_closure' => array('type' => 'datetime'),
				'fk_user_closure' => array('type' => 'integer'),
			),
			$this->db->prefix().'sof_avoir_tracking' => array(
				'date_validation' => array('type' => 'datetime'),
				'fk_user_validator' => array('type' => 'integer'),
				'date_last_use' => array('type' => 'datetime'),
				'fk_user_last_use' => array('type' => 'integer'),
			),
			$this->db->prefix().'sof_caisse_ecart' => array(
				'date_treatment' => array('type' => 'datetime'),
			),
			$this->db->prefix().'sof_caisse_controle' => array(
				'previous_session_status' => array('type' => 'integer'),
			),
			$this->db->prefix().'sof_caisse_alerte' => array(
				'dedup_key' => array('type' => 'varchar', 'value' => '255'),
				'escalation_level' => array('type' => 'integer', 'default' => 0, 'notnull' => 1),
				'date_last_escalation' => array('type' => 'datetime'),
			),
			$this->db->prefix().'sof_caisse_validation' => array(
				'escalation_level' => array('type' => 'integer', 'default' => 0, 'notnull' => 1),
				'date_last_escalation' => array('type' => 'datetime'),
			),
			$this->db->prefix().'sof_caisse_auditlog' => array(
				'archive_status' => array('type' => 'integer', 'default' => 0, 'notnull' => 1),
				'date_archive' => array('type' => 'datetime'),
				'purge_after' => array('type' => 'datetime'),
			),
		);
		foreach ($upgrades as $table => $fields) {
			foreach ($fields as $field => $definition) {
				$description = $this->db->DDLDescTable($table, $field);
				if (!$description || $this->db->num_rows($description) === 0) {
					if ($this->db->DDLAddField($table, $field, $definition) < 0) {
						return -1;
					}
				}
			}
		}

		$instructionTable = $this->db->prefix().'sof_instruction_manageriale';
		$instructionDate = $this->db->DDLDescTable($instructionTable, 'instruction_date');
		if (!$instructionDate || $this->db->num_rows($instructionDate) === 0) {
			$legacyInstructionDate = $this->db->DDLDescTable($instructionTable, 'instruction_timestamp');
			if ($legacyInstructionDate && $this->db->num_rows($legacyInstructionDate) > 0) {
				if ($this->db->type === 'pgsql') {
					$sql = 'ALTER TABLE '.$instructionTable.' RENAME COLUMN instruction_timestamp TO instruction_date';
				} elseif (in_array($this->db->type, array('mysql', 'mysqli'), true)) {
					$sql = 'ALTER TABLE '.$instructionTable.' CHANGE instruction_timestamp instruction_date datetime NULL';
				} else {
					$sql = '';
				}
				if ($sql === '' || !$this->db->query($sql)) {
					return -1;
				}
			} elseif ($this->db->DDLAddField($instructionTable, 'instruction_date', array('type' => 'datetime')) < 0) {
				return -1;
			}
		}

		$paymentLinkTable = $this->db->prefix().'sof_paiement_link';
		$sql = 'SELECT COUNT(*) nb FROM (SELECT fk_paiement, fk_facture FROM '.$paymentLinkTable.' GROUP BY fk_paiement, fk_facture HAVING COUNT(*) > 1) duplicates';
		$resql = $this->db->query($sql);
		$row = $resql ? $this->db->fetch_object($resql) : null;
		if (!$row || (int) $row->nb > 0) {
			dol_syslog(__METHOD__.' cannot create payment/invoice uniqueness index because duplicate rows exist or the check failed', LOG_ERR);
			return -1;
		}
		if ($this->db->type === 'pgsql') {
			$sql = 'CREATE UNIQUE INDEX IF NOT EXISTS uk_sof_paiement_link_payment_invoice ON '.$paymentLinkTable.' (fk_paiement, fk_facture)';
			if (!$this->db->query($sql)) {
				return -1;
			}
		} elseif (in_array($this->db->type, array('mysql', 'mysqli'), true)) {
			$sql = "SELECT COUNT(*) nb FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '".$this->db->escape($paymentLinkTable)."' AND index_name = 'uk_sof_paiement_link_payment_invoice'";
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
			if (!$row) {
				return -1;
			}
			if ((int) $row->nb === 0 && !$this->db->query('CREATE UNIQUE INDEX uk_sof_paiement_link_payment_invoice ON '.$paymentLinkTable.' (fk_paiement, fk_facture)')) {
				return -1;
			}
		}

		$alertTable = $this->db->prefix().'sof_caisse_alerte';
		if ($this->db->type === 'pgsql') {
			if (!$this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS uk_sof_caisse_alerte_dedup ON '.$alertTable.' (entity, dedup_key)')) {
				return -1;
			}
		} elseif (in_array($this->db->type, array('mysql', 'mysqli'), true)) {
			$sql = "SELECT COUNT(*) nb FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '".$this->db->escape($alertTable)."' AND index_name = 'uk_sof_caisse_alerte_dedup'";
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
			if (!$row || ((int) $row->nb === 0 && !$this->db->query('CREATE UNIQUE INDEX uk_sof_caisse_alerte_dedup ON '.$alertTable.' (entity, dedup_key)'))) {
				return -1;
			}
		}

		$importLineTable = $this->db->prefix().'sof_bank_import_line';
		foreach (array('fk_bank' => 'uk_sof_bank_import_line_bank', 'fk_mouvement' => 'uk_sof_bank_import_line_movement') as $field => $indexName) {
			$sql = 'SELECT COUNT(*) nb FROM (SELECT entity, '.$field.' FROM '.$importLineTable.' WHERE '.$field.' IS NOT NULL GROUP BY entity, '.$field.' HAVING COUNT(*) > 1) duplicates';
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
			if (!$row || (int) $row->nb > 0) {
				dol_syslog(__METHOD__.' cannot create '.$indexName.' because duplicate reconciliations exist or the check failed', LOG_ERR);
				return -1;
			}
			if ($this->db->type === 'pgsql') {
				if (!$this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS '.$indexName.' ON '.$importLineTable.' (entity, '.$field.')')) return -1;
			} elseif (in_array($this->db->type, array('mysql', 'mysqli'), true)) {
				$sql = "SELECT COUNT(*) nb FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '".$this->db->escape($importLineTable)."' AND index_name = '".$this->db->escape($indexName)."'";
				$resql = $this->db->query($sql);
				$row = $resql ? $this->db->fetch_object($resql) : null;
				if (!$row || ((int) $row->nb === 0 && !$this->db->query('CREATE UNIQUE INDEX '.$indexName.' ON '.$importLineTable.' (entity, '.$field.')'))) return -1;
			}
		}
		return 1;
	}

	/** Ensure local mobile-money modes exist in Dolibarr's payment dictionary. */
	private function seedPaymentModes()
	{
		global $conf;
		$modes = array(
			array('OM', 'Orange Money', 60),
			array('MM', 'Mobile Money', 61),
		);
		foreach ($modes as $mode) {
			$sql = 'INSERT INTO '.$this->db->prefix().'c_paiement (id, entity, code, libelle, type, active, accountancy_code, module, position)';
			$sql .= ' SELECT (SELECT COALESCE(MAX(id),0)+1 FROM '.$this->db->prefix().'c_paiement), '.((int) $conf->entity).", '".$this->db->escape($mode[0])."', '".$this->db->escape($mode[1])."', 0, 1, '', 'agence', ".((int) $mode[2]);
			$sql .= ' WHERE NOT EXISTS (SELECT 1 FROM '.$this->db->prefix()."c_paiement WHERE code = '".$this->db->escape($mode[0])."' AND entity IN (0,".((int) $conf->entity).'))';
			$this->db->query($sql);
		}
	}

	/**
	 * Remove module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
