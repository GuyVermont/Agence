<?php
/* Copyright (C) 2026 iPowerWorld */

require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceservice.class.php';

/** Bank/mobile reconciliation and master-data bulk-import service. */
class SofImportService
{
	/** @var DoliDB */
	private $db;
	public $error = '';
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Import a normalized CSV statement and calculate reconciliation suggestions. */
	public function importStatement(User $user, $sourceType, $filename, $content, $bankAccountId = 0, $fkAgence = 0)
	{
		if (!$this->hasRight($user, 'bankimport', 'import')) {
			return $this->fail('Permission refusée pour importer un relevé.');
		}
		$sourceType = strtolower(trim((string) $sourceType));
		if (!in_array($sourceType, array('bank', 'orange_money', 'mobile_money'), true)) {
			return $this->fail('Source de relevé non prise en charge.');
		}
		if ($sourceType === 'bank' && (int) $bankAccountId <= 0) {
			return $this->fail('Le compte bancaire Dolibarr est obligatoire.');
		}
		if ($sourceType === 'bank') {
			$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'bank_account WHERE rowid = '.((int) $bankAccountId).' AND entity = '.$this->entity().' AND clos = 0');
			if (!$resql || $this->db->num_rows($resql) !== 1) {
				return $this->fail('Le compte bancaire est absent, clôturé ou appartient à une autre entité.');
			}
		}
		$allowedAgencies = SofAgenceService::allowedAgencyIds($this->db, $user);
		if ($allowedAgencies !== null && (int) $fkAgence <= 0) {
			return $this->fail('Une agence de votre périmètre est obligatoire pour cet import.');
		}
		if ((int) $fkAgence > 0 && !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $fkAgence, 'statement_import')) {
			return $this->fail('Agence hors périmètre utilisateur.');
		}
		$content = (string) $content;
		if ($content === '' || strlen($content) > 20 * 1024 * 1024) {
			return $this->fail('Le fichier CSV est vide ou dépasse 20 Mo.');
		}
		$checksum = hash('sha256', $content);
		$sql = 'SELECT rowid FROM '.$this->db->prefix()."sof_bank_import WHERE entity = ".$this->entity()." AND source_type = '".$this->db->escape($sourceType)."' AND file_checksum = '".$this->db->escape($checksum)."'";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return $this->fail('Ce relevé a déjà été importé pour cette source.');
		}
		$parsed = $this->parseCsv($content);
		if ($parsed === false) {
			return -1;
		}
		$ref = $this->makeRef('STM');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_bank_import (entity,ref,source_type,fk_bank_account,fk_agence,original_filename,file_checksum,currency_code,status,date_import,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().", '".$this->db->escape($ref)."', '".$this->db->escape($sourceType)."', ".((int) $bankAccountId ?: 'NULL').', '.((int) $fkAgence ?: 'NULL').", '".$this->db->escape(substr(basename((string) $filename), 0, 255))."', '".$checksum."', NULL, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ".((int) $user->id).')';
		if (!$this->db->query($sql)) {
			return $this->fail($this->db->lasterror());
		}
		$importId = (int) $this->db->last_insert_id($this->db->prefix().'sof_bank_import');
		$lineCount = 0;
		$errorCount = 0;
		$total = 0.0;
		$start = '';
		$end = '';
		$currency = '';
		foreach ($parsed as $index => $row) {
			$lineNo = $index + 2;
			$date = $this->normalizeDate($row['operation_date'] ?? '');
			$valueDate = $this->normalizeDate($row['value_date'] ?? '') ?: $date;
			$amount = $this->normalizeAmount($row['amount'] ?? '');
			if ($date === '' || $amount === null || abs($amount) < 0.00000001) {
				$errorCount++;
				continue;
			}
			$paymentMode = $sourceType === 'orange_money' ? 'OM' : ($sourceType === 'mobile_money' ? 'MM' : strtoupper(substr(trim((string) ($row['payment_mode'] ?? '')), 0, 32)));
			$currencyLine = strtoupper(substr(trim((string) ($row['currency_code'] ?? '')), 0, 10));
			$currency = $currency ?: $currencyLine;
			$start = $start === '' || $date < $start ? $date : $start;
			$end = $end === '' || $date > $end ? $date : $end;
			$total += $amount;
			$sql = 'INSERT INTO '.$this->db->prefix().'sof_bank_import_line (entity,fk_import,line_number,external_ref,operation_date,value_date,amount,currency_code,counterparty,description,payment_mode,status,date_creation,fk_user_creat) VALUES (';
			$sql .= $this->entity().', '.$importId.', '.$lineNo.', '.($this->nullableString($row['external_ref'] ?? '')).", '".$date."', '".$valueDate."', ".price2num($amount).', '.$this->nullableString($currencyLine).', '.$this->nullableString($row['counterparty'] ?? '').', '.$this->nullableString($row['description'] ?? '').', '.$this->nullableString($paymentMode).', 0, CURRENT_TIMESTAMP, '.((int) $user->id).')';
			if (!$this->db->query($sql)) {
				$errorCount++;
				continue;
			}
			$lineId = (int) $this->db->last_insert_id($this->db->prefix().'sof_bank_import_line');
			$this->suggestMatch($lineId);
			$lineCount++;
		}
		$stats = $this->importStats($importId);
		$status = $errorCount > 0 ? 2 : 1;
		$sql = 'UPDATE '.$this->db->prefix().'sof_bank_import SET statement_start = '.($start !== '' ? "'".$start."'" : 'NULL').', statement_end = '.($end !== '' ? "'".$end."'" : 'NULL');
		$sql .= ', currency_code = '.$this->nullableString($currency).', total_amount = '.price2num($total).', line_count = '.$lineCount.', matched_count = '.$stats['matched'].', suggested_count = '.$stats['suggested'].', error_count = '.$errorCount.', status = '.$status.', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.$importId.' AND entity = '.$this->entity();
		$this->db->query($sql);
		return $importId;
	}

	/** Recalculate the best match for one imported line. */
	public function suggestMatch($lineId)
	{
		$sql = 'SELECT l.*, i.source_type, i.fk_bank_account, i.fk_agence FROM '.$this->db->prefix().'sof_bank_import_line l JOIN '.$this->db->prefix().'sof_bank_import i ON i.rowid = l.fk_import AND i.entity = l.entity';
		$sql .= ' WHERE l.rowid = '.((int) $lineId).' AND l.entity = '.$this->entity();
		$resql = $this->db->query($sql);
		$line = $resql ? $this->db->fetch_object($resql) : null;
		if (!$line || (int) $line->status === 2) {
			return $this->fail('Ligne importée absente ou déjà rapprochée.');
		}
		$score = 0;
		$reason = array();
		$fkBank = 0;
		$fkDeposit = 0;
		$fkMovement = 0;
		if ($line->source_type === 'bank') {
			$sql = 'SELECT rowid, amount, dateo, datev, label, num_chq, num_releve FROM '.$this->db->prefix().'bank WHERE fk_account = '.((int) $line->fk_bank_account);
			$sql .= ' AND ABS(ABS(amount) - '.price2num(abs((float) $line->amount)).') <= 0.01';
			$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.$this->db->prefix().'sof_bank_import_line il WHERE il.entity = '.$this->entity().' AND il.fk_bank = '.$this->db->prefix().'bank.rowid AND il.status = 2)';
			$sql .= " AND COALESCE(datev,dateo) BETWEEN '".$this->db->escape(date('Y-m-d', strtotime($line->operation_date.' -5 days')))."' AND '".$this->db->escape(date('Y-m-d', strtotime($line->operation_date.' +5 days')))."' ORDER BY COALESCE(datev,dateo), rowid".$this->db->plimit(10, 0);
			$resql = $this->db->query($sql);
			$best = null;
			$bestScore = 0;
			while ($resql && ($candidate = $this->db->fetch_object($resql))) {
				$candidateScore = 60;
				$candidateDate = substr((string) ($candidate->datev ?: $candidate->dateo), 0, 10);
				if ($candidateDate === substr((string) $line->operation_date, 0, 10)) {
					$candidateScore += 20;
				}
				$haystack = strtolower($candidate->label.' '.$candidate->num_chq.' '.$candidate->num_releve);
				if ($line->external_ref && strpos($haystack, strtolower($line->external_ref)) !== false) {
					$candidateScore += 20;
				}
				if ($candidateScore > $bestScore) {
					$best = $candidate;
					$bestScore = $candidateScore;
				}
			}
			if ($best) {
				$fkBank = (int) $best->rowid;
				$score = $bestScore;
				$reason[] = 'Montant bancaire exact';
			}
			$sql = 'SELECT rowid, bank_slip_number FROM '.$this->db->prefix().'sof_caisse_depot_banque WHERE entity = '.$this->entity().' AND status IN (1,2)';
			$sql .= ' AND fk_bank_account = '.((int) $line->fk_bank_account).' AND ABS(amount - '.price2num(abs((float) $line->amount)).') <= 0.01';
			if (!empty($line->fk_agence)) {
				$sql .= ' AND fk_agence = '.((int) $line->fk_agence);
			}
			$sql .= ' ORDER BY rowid'.$this->db->plimit(2, 0);
			$resql = $this->db->query($sql);
			$deposit = $resql ? $this->db->fetch_object($resql) : null;
			if ($deposit) {
				$fkDeposit = (int) $deposit->rowid;
				$score = min(100, $score + 10);
				$reason[] = 'Dépôt Agence compatible';
			}
		} else {
			$mode = $line->source_type === 'orange_money' ? 'OM' : 'MM';
			$sql = 'SELECT m.rowid, m.transaction_date, m.transaction_ref, m.ref FROM '.$this->db->prefix().'sof_caisse_mouvement m';
			$sql .= ' WHERE m.entity = '.$this->entity()." AND m.status = 1 AND m.payment_mode IN ('".$mode."','MOMO','MOBILE')";
			$sql .= ' AND ABS(m.amount - '.price2num(abs((float) $line->amount)).') <= 0.01';
			$sql .= " AND m.transaction_date BETWEEN '".$this->db->escape($line->operation_date)." 00:00:00' AND '".$this->db->escape(date('Y-m-d', strtotime($line->operation_date.' +3 days')))." 23:59:59'";
			if (!empty($line->fk_agence)) {
				$sql .= ' AND m.fk_agence = '.((int) $line->fk_agence);
			}
			$sql .= ' AND NOT EXISTS (SELECT 1 FROM '.$this->db->prefix().'sof_bank_import_line il WHERE il.entity = m.entity AND il.fk_mouvement = m.rowid AND il.status = 2) ORDER BY m.transaction_date'.$this->db->plimit(10, 0);
			$resql = $this->db->query($sql);
			$best = null;
			$bestScore = 0;
			while ($resql && ($candidate = $this->db->fetch_object($resql))) {
				$candidateScore = 70;
				if (substr($candidate->transaction_date, 0, 10) === substr($line->operation_date, 0, 10)) {
					$candidateScore += 20;
				}
				$haystack = strtolower($candidate->transaction_ref.' '.$candidate->ref);
				if ($line->external_ref && strpos($haystack, strtolower($line->external_ref)) !== false) {
					$candidateScore += 10;
				}
				if ($candidateScore > $bestScore) {
					$best = $candidate;
					$bestScore = $candidateScore;
				}
			}
			if ($best) {
				$fkMovement = (int) $best->rowid;
				$score = $bestScore;
				$reason[] = 'Règlement '.$mode.' compatible';
			}
		}
		$status = $score >= 60 ? 1 : 0;
		$sql = 'UPDATE '.$this->db->prefix().'sof_bank_import_line SET fk_bank = '.($fkBank ?: 'NULL').', fk_depot_banque = '.($fkDeposit ?: 'NULL').', fk_mouvement = '.($fkMovement ?: 'NULL').', match_score = '.$score.", match_reason = '".$this->db->escape(implode('; ', $reason))."', status = ".$status.', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $lineId).' AND entity = '.$this->entity();
		return $this->db->query($sql) ? $status : $this->fail($this->db->lasterror());
	}

	/** Confirm a bank deposit/native line match or a mobile settlement/movement match. */
	public function confirmMatch(User $user, $lineId, $targetId = 0)
	{
		if (!$this->hasRight($user, 'bankimport', 'reconcile')) {
			return $this->fail('Permission refusée pour confirmer un rapprochement.');
		}
		$this->db->begin();
		$sql = 'SELECT l.*, i.source_type, i.fk_bank_account, i.fk_agence FROM '.$this->db->prefix().'sof_bank_import_line l JOIN '.$this->db->prefix().'sof_bank_import i ON i.rowid = l.fk_import AND i.entity = l.entity';
		$sql .= ' WHERE l.rowid = '.((int) $lineId).' AND l.entity = '.$this->entity().' FOR UPDATE';
		$resql = $this->db->query($sql);
		$line = $resql ? $this->db->fetch_object($resql) : null;
		if (!$line || (int) $line->status === 2 || ((int) $line->fk_agence > 0 && !SofAgenceService::userCanAccessAgency($this->db, $user, (int) $line->fk_agence, 'statement_reconcile', abs((float) $line->amount)))) {
			$this->db->rollback();
			return $this->fail('Ligne absente, déjà rapprochée ou hors périmètre.');
		}
		if ($line->source_type === 'bank') {
			$bankLineId = $targetId > 0 ? (int) $targetId : (int) $line->fk_bank;
			if ($bankLineId <= 0) {
				$this->db->rollback();
				return $this->fail('Une ligne bancaire Dolibarr doit être sélectionnée.');
			}
			$resql = $this->db->query('SELECT b.rowid, b.amount, b.fk_account FROM '.$this->db->prefix().'bank b JOIN '.$this->db->prefix().'bank_account ba ON ba.rowid = b.fk_account WHERE b.rowid = '.$bankLineId.' AND ba.entity = '.$this->entity());
			$bank = $resql ? $this->db->fetch_object($resql) : null;
			if (!$bank || (int) $bank->fk_account !== (int) $line->fk_bank_account || abs(abs((float) $bank->amount) - abs((float) $line->amount)) > 0.01) {
				$this->db->rollback();
				return $this->fail('La ligne bancaire choisie ne correspond pas au compte et au montant importés.');
			}
			$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'sof_bank_import_line WHERE entity = '.$this->entity().' AND fk_bank = '.$bankLineId.' AND status = 2 AND rowid <> '.((int) $lineId));
			if ($resql && $this->db->num_rows($resql) > 0) {
				$this->db->rollback();
				return $this->fail('Cette ligne bancaire Dolibarr est déjà rapprochée par un autre relevé.');
			}
			if (!empty($line->fk_depot_banque)) {
				require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceoperations.class.php';
				$engine = new SofAgenceOperations($this->db);
				if ($engine->reconcileDeposit($user, (int) $line->fk_depot_banque, $bankLineId, 'Import '.$line->external_ref) <= 0) {
					$this->db->rollback();
					return $this->fail($engine->error, $engine->errors);
				}
			}
			$updates = 'fk_bank = '.$bankLineId;
		} else {
			$movementId = $targetId > 0 ? (int) $targetId : (int) $line->fk_mouvement;
			$mode = $line->source_type === 'orange_money' ? 'OM' : 'MM';
			$resql = $this->db->query('SELECT rowid, fk_agence, amount, payment_mode FROM '.$this->db->prefix().'sof_caisse_mouvement WHERE rowid = '.$movementId.' AND entity = '.$this->entity().' AND status = 1');
			$movement = $resql ? $this->db->fetch_object($resql) : null;
			if (!$movement || (!empty($line->fk_agence) && (int) $movement->fk_agence !== (int) $line->fk_agence) || !in_array($movement->payment_mode, array($mode, 'MOMO', 'MOBILE'), true) || abs((float) $movement->amount - abs((float) $line->amount)) > 0.01) {
				$this->db->rollback();
				return $this->fail('Le règlement mobile choisi ne correspond pas à la ligne opérateur.');
			}
			$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().'sof_bank_import_line WHERE entity = '.$this->entity().' AND fk_mouvement = '.$movementId.' AND status = 2 AND rowid <> '.((int) $lineId));
			if ($resql && $this->db->num_rows($resql) > 0) {
				$this->db->rollback();
				return $this->fail('Ce règlement mobile est déjà rapproché.');
			}
			$updates = 'fk_mouvement = '.$movementId;
		}
		$sql = 'UPDATE '.$this->db->prefix().'sof_bank_import_line SET '.$updates.', status = 2, match_score = 100, date_reconcile = CURRENT_TIMESTAMP, fk_user_reconcile = '.((int) $user->id).', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $lineId).' AND entity = '.$this->entity();
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return $this->fail($this->db->lasterror());
		}
		$this->db->commit();
		$this->refreshImportStats((int) $line->fk_import, $user);
		return 1;
	}

	/** Upsert agencies, DAS, cash desks or user assignments from CSV. */
	public function importMasterData(User $user, $objectType, $filename, $content, $mode = 'upsert')
	{
		if (!$this->hasRight($user, 'bulkimport', 'run')) {
			return $this->fail('Permission refusée pour les imports de masse.');
		}
		$objectType = strtolower(trim((string) $objectType));
		if (!in_array($objectType, array('agency', 'das', 'cashdesk', 'assignment'), true) || !in_array($mode, array('create', 'update', 'upsert'), true)) {
			return $this->fail('Type ou mode d’import non autorisé.');
		}
		if ((string) $content === '' || strlen((string) $content) > 20 * 1024 * 1024) {
			return $this->fail('Le fichier CSV est vide ou dépasse 20 Mo.');
		}
		$checksum = hash('sha256', (string) $content);
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix()."sof_bulk_import WHERE entity = ".$this->entity()." AND object_type = '".$this->db->escape($objectType)."' AND file_checksum = '".$checksum."'");
		if ($resql && $this->db->num_rows($resql) > 0) {
			return $this->fail('Ce fichier a déjà été importé pour ce référentiel.');
		}
		$rows = $this->parseCsv((string) $content);
		if ($rows === false) {
			return -1;
		}
		$ref = $this->makeRef('IMP');
		$sql = 'INSERT INTO '.$this->db->prefix().'sof_bulk_import (entity,ref,object_type,import_mode,original_filename,file_checksum,status,date_start,date_creation,fk_user_creat) VALUES (';
		$sql .= $this->entity().", '".$ref."', '".$objectType."', '".$mode."', '".$this->db->escape(substr(basename((string) $filename), 0, 255))."', '".$checksum."', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ".((int) $user->id).')';
		if (!$this->db->query($sql)) {
			return $this->fail($this->db->lasterror());
		}
		$importId = (int) $this->db->last_insert_id($this->db->prefix().'sof_bulk_import');
		$created = 0;
		$updated = 0;
		$errors = 0;
		foreach ($rows as $index => $row) {
			$lineNo = $index + 2;
			$result = $this->upsertMasterRow($user, $objectType, $mode, $row);
			$ok = $result['status'] > 0;
			if ($result['action'] === 'created') $created++;
			if ($result['action'] === 'updated') $updated++;
			if (!$ok) $errors++;
			$sql = 'INSERT INTO '.$this->db->prefix().'sof_bulk_import_line (entity,fk_import,line_number,external_key,payload,target_object_id,action_taken,error_message,status,date_creation,fk_user_creat) VALUES (';
			$sql .= $this->entity().', '.$importId.', '.$lineNo.', '.$this->nullableString($result['key']).", '".$this->db->escape(json_encode($this->sanitizeRow($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))."', ".((int) $result['id'] ?: 'NULL').', '.$this->nullableString($result['action']).', '.$this->nullableString($result['error']).', '.($ok ? 1 : 2).', CURRENT_TIMESTAMP, '.((int) $user->id).')';
			$this->db->query($sql);
		}
		$status = $errors === 0 ? 1 : ($created + $updated > 0 ? 2 : 3);
		$sql = 'UPDATE '.$this->db->prefix().'sof_bulk_import SET line_count = '.count($rows).', created_count = '.$created.', updated_count = '.$updated.', error_count = '.$errors.', status = '.$status.', date_end = CURRENT_TIMESTAMP, fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.$importId.' AND entity = '.$this->entity();
		$this->db->query($sql);
		return $importId;
	}

	private function upsertMasterRow(User $user, $type, $mode, array $row)
	{
		try {
			if ($type === 'assignment') {
				return $this->upsertAssignment($user, $mode, $row);
			}
			$map = array(
				'agency' => array('class' => 'SofAgence', 'file' => 'sofagence', 'table' => 'sof_agence', 'fields' => array('ref','label','town','country_code','address','phone','email','opening_hours','cash_ceiling','cashin_ceiling','refund_ceiling','deferred_payment_ceiling','alert_threshold_amount','status')),
				'das' => array('class' => 'SofDas', 'file' => 'sofdas', 'table' => 'sof_das', 'fields' => array('ref','label','description','status')),
				'cashdesk' => array('class' => 'SofCaisse', 'file' => 'sofcaisse', 'table' => 'sof_caisse', 'fields' => array('ref','label','caisse_type','currency_code','allow_parallel_sessions','cashin_ceiling','physical_balance_ceiling','refund_ceiling','status')),
			);
			$config = $map[$type];
			$ref = trim((string) ($row['ref'] ?? ''));
			if ($ref === '' || !preg_match('/^[A-Za-z0-9_.\/-]{1,64}$/', $ref)) {
				throw new Exception('Référence obligatoire ou invalide.');
			}
			require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/'.$config['file'].'.class.php';
			$class = $config['class'];
			$object = new $class($this->db);
			$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().$config['table']." WHERE entity = ".$this->entity()." AND ref = '".$this->db->escape($ref)."'");
			$existing = $resql ? $this->db->fetch_object($resql) : null;
			if ($existing && $mode === 'create') throw new Exception('La référence existe déjà.');
			if (!$existing && $mode === 'update') throw new Exception('La référence à mettre à jour est absente.');
			if ($existing && $object->fetch((int) $existing->rowid) <= 0) throw new Exception($object->error ?: 'Lecture impossible.');
			$object->entity = $this->entity();
			foreach ($config['fields'] as $field) {
				if (array_key_exists($field, $row) && $row[$field] !== '') {
					$object->$field = $this->normalizeMasterValue($field, $row[$field]);
				}
			}
			$object->ref = $ref;
			if ($type === 'cashdesk') {
				if (empty($object->caisse_type)) $object->caisse_type = 'cash';
				if (empty($object->currency_code)) $object->currency_code = 'XAF';
				$agencyRef = trim((string) ($row['agency_ref'] ?? ''));
				if ($agencyRef !== '') $object->fk_agence = $this->resolveByRef('sof_agence', $agencyRef);
				if ($object->fk_agence <= 0) throw new Exception('Agence de la caisse introuvable.');
				if (!empty($row['allowed_das_refs'])) $object->allowed_das = $this->resolveDasRefs($row['allowed_das_refs']);
			}
			if ($type === 'agency' && !empty($row['allowed_das_refs'])) $object->allowed_das = $this->resolveDasRefs($row['allowed_das_refs']);
			$result = $existing ? $object->update($user, 1) : $object->create($user, 1);
			if ($result <= 0) throw new Exception($object->error ?: implode(' | ', $object->errors));
			return array('status' => 1, 'action' => $existing ? 'updated' : 'created', 'id' => $existing ? (int) $existing->rowid : (int) $result, 'key' => $ref, 'error' => '');
		} catch (Throwable $e) {
			return array('status' => -1, 'action' => 'error', 'id' => 0, 'key' => (string) ($row['ref'] ?? $row['user_login'] ?? ''), 'error' => substr($e->getMessage(), 0, 4000));
		}
	}

	private function upsertAssignment(User $user, $mode, array $row)
	{
		$login = trim((string) ($row['user_login'] ?? ''));
		$agencyRef = trim((string) ($row['agency_ref'] ?? ''));
		$role = strtolower(trim((string) ($row['role_code'] ?? '')));
		if ($login === '' || $agencyRef === '' || !preg_match('/^[a-z0-9_.-]{1,64}$/i', $role)) throw new Exception('Utilisateur, agence et rôle sont obligatoires.');
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix()."user WHERE login = '".$this->db->escape($login)."' AND statut = 1 AND entity IN (".getEntity('user').')');
		$userRow = $resql ? $this->db->fetch_object($resql) : null;
		$agencyId = $this->resolveByRef('sof_agence', $agencyRef);
		if (!$userRow || $agencyId <= 0) throw new Exception('Utilisateur actif ou agence introuvable.');
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_agence_user WHERE entity = '.$this->entity().' AND fk_user = '.((int) $userRow->rowid).' AND fk_agence = '.$agencyId;
		$resql = $this->db->query($sql);
		$existing = $resql ? $this->db->fetch_object($resql) : null;
		if ($existing && $mode === 'create') throw new Exception('Cette affectation existe déjà.');
		if (!$existing && $mode === 'update') throw new Exception('Cette affectation est absente.');
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofagenceuser.class.php';
		$assignment = new SofAgenceUser($this->db);
		if ($existing) $assignment->fetch((int) $existing->rowid);
		$assignment->entity = $this->entity();
		$assignment->fk_agence = $agencyId;
		$assignment->fk_user = (int) $userRow->rowid;
		$assignment->role_code = $role;
		$assignment->scope_type = trim((string) ($row['scope_type'] ?? 'agency')) ?: 'agency';
		$assignment->scope_value = trim((string) ($row['scope_value'] ?? ''));
		$assignment->validation_limit = $this->normalizeAmount($row['validation_limit'] ?? '0') ?: 0;
		$assignment->is_default = (int) ($row['is_default'] ?? 0) ? 1 : 0;
		$assignment->is_substitute = (int) ($row['is_substitute'] ?? 0) ? 1 : 0;
		$assignment->status = isset($row['status']) && $row['status'] !== '' ? (int) $row['status'] : 1;
		$result = $existing ? $assignment->update($user, 1) : $assignment->create($user, 1);
		if ($result <= 0) throw new Exception($assignment->error ?: 'Échec de l’affectation.');
		return array('status' => 1, 'action' => $existing ? 'updated' : 'created', 'id' => $existing ? (int) $existing->rowid : (int) $result, 'key' => $login.'@'.$agencyRef, 'error' => '');
	}

	private function parseCsv($content)
	{
		$content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
		$stream = fopen('php://temp', 'w+');
		fwrite($stream, $content);
		rewind($stream);
		$first = fgets($stream);
		if ($first === false) {
			fclose($stream);
			return $this->fail('CSV vide.') && false;
		}
		$counts = array(';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t"));
		arsort($counts);
		$delimiter = key($counts);
		rewind($stream);
		$headersRaw = fgetcsv($stream, 0, $delimiter);
		$headers = array_map(array($this, 'normalizeHeader'), $headersRaw ?: array());
		if (empty($headers) || count(array_filter($headers)) !== count($headers)) {
			fclose($stream);
			$this->fail('En-têtes CSV vides ou invalides.');
			return false;
		}
		$aliases = array('date' => 'operation_date', 'date_operation' => 'operation_date', 'date_valeur' => 'value_date', 'montant' => 'amount', 'devise' => 'currency_code', 'reference' => 'external_ref', 'ref_externe' => 'external_ref', 'libelle' => 'description', 'tiers' => 'counterparty', 'mode_paiement' => 'payment_mode', 'agence' => 'agency_ref', 'utilisateur' => 'user_login');
		foreach ($headers as &$header) {
			if (isset($aliases[$header])) $header = $aliases[$header];
		}
		unset($header);
		$rows = array();
		while (($values = fgetcsv($stream, 0, $delimiter)) !== false) {
			if (count($values) === 1 && trim((string) $values[0]) === '') continue;
			$values = array_pad($values, count($headers), '');
			$rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
			if (count($rows) > 100000) {
				fclose($stream);
				$this->fail('Le CSV dépasse 100 000 lignes.');
				return false;
			}
		}
		fclose($stream);
		if (empty($rows)) {
			$this->fail('Le CSV ne contient aucune ligne exploitable.');
			return false;
		}
		return $rows;
	}

	private function refreshImportStats($importId, User $user)
	{
		$stats = $this->importStats($importId);
		$sql = 'UPDATE '.$this->db->prefix().'sof_bank_import SET matched_count = '.$stats['matched'].', suggested_count = '.$stats['suggested'].', fk_user_modif = '.((int) $user->id).', tms = CURRENT_TIMESTAMP WHERE rowid = '.((int) $importId).' AND entity = '.$this->entity();
		$this->db->query($sql);
	}

	private function importStats($importId)
	{
		$resql = $this->db->query('SELECT SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) matched, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) suggested FROM '.$this->db->prefix().'sof_bank_import_line WHERE entity = '.$this->entity().' AND fk_import = '.((int) $importId));
		$row = $resql ? $this->db->fetch_object($resql) : null;
		return array('matched' => $row ? (int) $row->matched : 0, 'suggested' => $row ? (int) $row->suggested : 0);
	}

	private function normalizeHeader($header)
	{
		$value = strtolower(trim((string) $header));
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = $converted !== false ? $converted : $value;
		return trim(preg_replace('/[^a-z0-9]+/', '_', $value), '_');
	}

	private function normalizeDate($value)
	{
		$value = trim((string) $value);
		foreach (array('Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d') as $format) {
			$date = DateTime::createFromFormat('!'.$format, $value);
			if ($date && $date->format($format) === $value) return $date->format('Y-m-d');
		}
		return '';
	}

	private function normalizeAmount($value)
	{
		$value = trim(str_replace(array("\xC2\xA0", ' '), '', (string) $value));
		if ($value === '') return null;
		if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) $value = str_replace(',', '.', $value);
		else $value = str_replace(',', '', $value);
		return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
	}

	private function normalizeMasterValue($field, $value)
	{
		if (preg_match('/ceiling|percent|limit/', $field)) return $this->normalizeAmount($value) ?: 0;
		if (in_array($field, array('status', 'allow_parallel_sessions'), true)) return (int) $value;
		return trim((string) $value);
	}

	private function resolveByRef($table, $ref)
	{
		if (!in_array($table, array('sof_agence', 'sof_das'), true) || trim((string) $ref) === '') return 0;
		$resql = $this->db->query('SELECT rowid FROM '.$this->db->prefix().$table." WHERE entity = ".$this->entity()." AND ref = '".$this->db->escape(trim($ref))."' AND status = 1");
		$row = $resql ? $this->db->fetch_object($resql) : null;
		return $row ? (int) $row->rowid : 0;
	}

	private function resolveDasRefs($list)
	{
		$ids = array();
		foreach (preg_split('/[,;|]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY) as $ref) {
			$id = $this->resolveByRef('sof_das', trim($ref));
			if ($id <= 0) throw new Exception('DAS introuvable : '.trim($ref));
			$ids[] = $id;
		}
		return implode(',', array_unique($ids));
	}

	private function sanitizeRow(array $row)
	{
		$result = array();
		foreach ($row as $key => $value) {
			$result[$key] = preg_match('/password|secret|token|key/i', (string) $key) ? '[REDACTED]' : substr((string) $value, 0, 2000);
		}
		return $result;
	}

	private function nullableString($value)
	{
		$value = trim((string) $value);
		return $value === '' ? 'NULL' : "'".$this->db->escape($value)."'";
	}

	private function hasRight(User $user, $object, $action)
	{
		if (!SofAgenceService::isActiveUser($this->db, $user)) return false;
		if (!empty($user->admin)) return true;
		$probe = new User($this->db);
		if ($probe->fetch((int) $user->id) <= 0 || empty($probe->statut)) return false;
		$probe->loadRights('agence', 1);
		return (bool) $probe->hasRight('agence', $object, $action);
	}

	private function entity()
	{
		global $conf;
		return (int) $conf->entity;
	}

	private function makeRef($prefix)
	{
		return $prefix.'-'.date('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
	}

	private function fail($message, array $errors = array())
	{
		$this->error = (string) $message;
		$this->errors = $errors;
		return -1;
	}
}
