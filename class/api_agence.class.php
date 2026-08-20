<?php
/* Copyright (C) 2026 iPowerWorld */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';

/**
 * Secured PowerERP Agence REST API.
 *
 * Authentication is provided by Dolibarr's REST API key layer. Every method
 * additionally reloads the active account, module rights, current entity and
 * agency assignments.
 *
 * @smart-auto-routing false
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class Agence extends DolibarrApi
{
	/** @var SofIntegrationService */
	private $service;

	/** @url GET / */
	public function index()
	{
		$this->requireApiRead();
		return array(
			'name' => 'PowerERP Agence API', 'version' => '2.4.0', 'editor' => 'iPowerWorld',
			'authentication' => 'Dolibarr API key',
			'resources' => array('health','agencies','cashdesks','bi/{dataset}','webhooks','connectors','configuration'),
		);
	}

	public function __construct()
	{
		global $db;
		$this->db = $db;
		$this->service = new SofIntegrationService($db);
	}

	/** @url GET /health */
	public function getHealth()
	{
		$result = $this->service->health($this->apiUser());
		if ($result < 0) throw new RestException(403, $this->service->error);
		return $result;
	}

	/** @url GET /agencies */
	public function getAgencies($limit = 100, $page = 0)
	{
		$user = $this->requireApiRead();
		$scope = SofAgenceService::allowedAgencyIds($this->db, $user);
		$limit = max(1, min(500, (int) $limit)); $page = max(0, (int) $page);
		$sql = 'SELECT rowid,ref,label,code,country_code,currency_code,status,tms FROM '.$this->db->prefix().'sof_agence WHERE entity='.$this->entity();
		if ($scope !== null) $sql .= $scope ? ' AND rowid IN ('.implode(',', array_map('intval', $scope)).')' : ' AND 1=0';
		$sql .= ' ORDER BY ref'.$this->db->plimit($limit, $page * $limit);
		return $this->fetchRows($sql);
	}

	/** @url GET /cashdesks */
	public function getCashdesks($fk_agence = 0, $limit = 100, $page = 0)
	{
		$user = $this->requireApiRead();
		$scope = SofAgenceService::allowedAgencyIds($this->db, $user);
		$fk_agence = (int) $fk_agence; $limit = max(1, min(500, (int) $limit)); $page = max(0, (int) $page);
		if ($fk_agence > 0 && $scope !== null && !in_array($fk_agence, $scope, true)) throw new RestException(403, 'Agency outside the current user scope');
		$sql = 'SELECT c.rowid,c.ref,c.label,c.fk_agence,a.ref agency_ref,c.cash_type,c.status,c.tms FROM '.$this->db->prefix().'sof_caisse c JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=c.fk_agence AND a.entity=c.entity WHERE c.entity='.$this->entity();
		if ($fk_agence > 0) $sql .= ' AND c.fk_agence='.$fk_agence;
		elseif ($scope !== null) $sql .= $scope ? ' AND c.fk_agence IN ('.implode(',', array_map('intval', $scope)).')' : ' AND 1=0';
		$sql .= ' ORDER BY a.ref,c.ref'.$this->db->plimit($limit, $page * $limit);
		return $this->fetchRows($sql);
	}

	/** @url GET /bi/{dataset} */
	public function getBi($dataset, $cursor = '', $limit = 250, $fk_agence = 0)
	{
		$result = $this->service->incrementalExport($this->apiUser(), (string) $dataset, (string) $cursor, (int) $limit, (int) $fk_agence);
		if ($result < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 400, $this->service->error);
		return $result;
	}

	/** @url POST /webhooks */
	public function postWebhook($request_data = null)
	{
		$id = $this->service->saveWebhook($this->apiUser(), (array) $request_data);
		if ($id < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 400, $this->service->error);
		return array('success' => true, 'id' => $id);
	}

	/** @url POST /webhooks/{id}/replay */
	public function postWebhookReplay($id, $request_data = null)
	{
		$result = $this->service->replayWebhook($this->apiUser(), (int) $id);
		if ($result < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 409, $this->service->error);
		return array('success' => true, 'delivery_id' => (int) $id);
	}

	/** @url POST /connectors */
	public function postConnector($request_data = null)
	{
		$id = $this->service->saveConnector($this->apiUser(), (array) $request_data);
		if ($id < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 400, $this->service->error);
		return array('success' => true, 'id' => $id);
	}

	/** @url POST /connectors/{id}/sync */
	public function postConnectorSync($id, $request_data = null)
	{
		$result = $this->service->syncConnector($this->apiUser(), (int) $id);
		if ($result < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 502, $this->service->error);
		return array('success' => true, 'result' => $result);
	}

	/** @url GET /configuration/export */
	public function getConfigurationExport($environment = 'development')
	{
		$result = $this->service->exportConfiguration($this->apiUser(), (string) $environment);
		if ($result < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 400, $this->service->error);
		return $result;
	}

	/** @url POST /configuration/import */
	public function postConfigurationImport($request_data = null)
	{
		$data = (array) $request_data;
		if (!is_array($data['package'] ?? null)) throw new RestException(400, 'package must be an object');
		$result = $this->service->importConfiguration($this->apiUser(), $data['package'], (string) ($data['target_environment'] ?? ''), !isset($data['dry_run']) || !empty($data['dry_run']));
		if ($result < 0) throw new RestException(stripos($this->service->error, 'Permission') !== false ? 403 : 400, $this->service->error);
		return array('success' => true, 'dry_run' => !isset($data['dry_run']) || !empty($data['dry_run']), 'summary' => $result);
	}

	private function requireApiRead()
	{
		$user = $this->apiUser();
		if (!SofAgenceService::isActiveUser($this->db, $user)) throw new RestException(401, 'Inactive user');
		if (!empty($user->admin)) return $user;
		$fresh = new User($this->db);
		if ($fresh->fetch((int) $user->id) <= 0 || empty($fresh->statut)) throw new RestException(401, 'Inactive user');
		$fresh->loadRights('agence', 1);
		if (!$fresh->hasRight('agence', 'api', 'read')) throw new RestException(403, 'No permission to read Agence API');
		return $fresh;
	}

	private function apiUser()
	{
		if (!isset(DolibarrApiAccess::$user) || !(DolibarrApiAccess::$user instanceof User)) throw new RestException(401, 'Authentication required');
		return DolibarrApiAccess::$user;
	}

	private function fetchRows($sql)
	{
		$resql = $this->db->query($sql);
		if (!$resql) throw new RestException(500, 'Database query failed');
		$rows = array(); while ($row = $this->db->fetch_object($resql)) $rows[] = (array) $row;
		return array('entity' => $this->entity(), 'count' => count($rows), 'rows' => $rows);
	}

	private function entity() { global $conf; return (int) $conf->entity; }
}
