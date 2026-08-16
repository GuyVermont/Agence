<?php
/* Copyright (C) 2026 SOFITOUL */

/** Hooks adding non-invasive Agence context to native Dolibarr cards. */
class ActionsAgence
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $resprints = '';
	/** @var array<int,string> */
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Display agency/session linkage on native invoice, payment and bank cards. */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		if (!isModEnabled('agence') || empty($object->id)) {
			return 0;
		}
		$element = isset($object->element) ? $object->element : '';
		$row = null;
		if (in_array($element, array('facture', 'invoice'), true)) {
			$sql = 'SELECT a.ref agence_ref, c.ref caisse_ref, s.ref session_ref, d.ref das_ref';
			$sql .= ' FROM '.$this->db->prefix().'sof_facture_link l';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=l.fk_agence';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse c ON c.rowid=l.fk_caisse';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse_session s ON s.rowid=l.fk_session';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_das d ON d.rowid=l.fk_das';
			$sql .= ' WHERE l.fk_facture='.((int) $object->id).' ORDER BY l.rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
		} elseif (in_array($element, array('paiement', 'payment'), true)) {
			$sql = 'SELECT a.ref agence_ref, c.ref caisse_ref, s.ref session_ref, d.ref das_ref';
			$sql .= ' FROM '.$this->db->prefix().'sof_paiement_link l';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=l.fk_agence';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse c ON c.rowid=l.fk_caisse';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse_session s ON s.rowid=l.fk_session';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_das d ON d.rowid=l.fk_das';
			$sql .= ' WHERE l.fk_paiement='.((int) $object->id).' ORDER BY l.rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
		}
		if ($row) {
			$this->resprints = '<tr><td>Contexte Agence</td><td>';
			$this->resprints .= dol_escape_htmltag(trim($row->agence_ref.' / '.$row->caisse_ref.' / '.$row->session_ref.' / '.$row->das_ref, ' /'));
			$this->resprints .= '</td></tr>';
			return 0;
		}
		return 0;
	}

	/** Add a direct collection action on validated customer invoices. */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user;
		if (!isModEnabled('agence') || empty($object->id) || empty($object->element) || $object->element !== 'facture') {
			return 0;
		}
		if ($user->hasRight('agence', 'mouvement', 'cashin') && (int) $object->statut === 1 && empty($object->paye)) {
			$this->resprints = '<a class="butAction" href="'.dol_buildpath('/agence/mouvement/encaisser.php', 1).'?fk_facture='.(int) $object->id.'">Encaisser via Agence</a>';
			return 0;
		}
		return 0;
	}
}
