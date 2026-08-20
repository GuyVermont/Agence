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
	/** @var array<string,mixed> */
	public $results = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Expose Agence events in Dolibarr's standard configurable Notification UI. */
	public function notifsupported($parameters, &$object, &$action, $hookmanager)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/agence/class/sofintegrationservice.class.php';
		$this->results = array('arrayofnotifsupported' => SofIntegrationService::dolibarrNotificationEvents());
		return 0;
	}

	/** Display Agence enrichment directly on native Dolibarr business cards. */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		$langs->loadLangs(array('agence@agence'));
		if (!isModEnabled('agence') || empty($object->id)) {
			return 0;
		}
		$element = isset($object->element) ? $object->element : '';
		$row = null;
		$this->resprints = '';
		if (in_array($element, array('facture', 'invoice'), true)) {
			$sql = 'SELECT l.rowid link_id,a.ref agence_ref, c.ref caisse_ref, s.ref session_ref, d.ref das_ref';
			$sql .= ' FROM '.$this->db->prefix().'sof_facture_link l';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=l.fk_agence';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse c ON c.rowid=l.fk_caisse';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse_session s ON s.rowid=l.fk_session';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_das d ON d.rowid=l.fk_das';
			$sql .= ' WHERE l.fk_facture='.((int) $object->id).' ORDER BY l.rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
		} elseif (in_array($element, array('paiement', 'payment'), true)) {
			$sql = 'SELECT l.rowid link_id,a.ref agence_ref, c.ref caisse_ref, s.ref session_ref, d.ref das_ref';
			$sql .= ' FROM '.$this->db->prefix().'sof_paiement_link l';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=l.fk_agence';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse c ON c.rowid=l.fk_caisse';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse_session s ON s.rowid=l.fk_session';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_das d ON d.rowid=l.fk_das';
			$sql .= ' WHERE l.fk_paiement='.((int) $object->id).' ORDER BY l.rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
		} elseif (in_array($element, array('commande', 'order'), true)) {
			$sql = 'SELECT l.rowid link_id,a.ref agence_ref,c.ref caisse_ref,s.ref session_ref,d.ref das_ref FROM '.$this->db->prefix().'sof_commande_link l';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=l.fk_agence LEFT JOIN '.$this->db->prefix().'sof_caisse c ON c.rowid=l.fk_caisse';
			$sql .= ' LEFT JOIN '.$this->db->prefix().'sof_caisse_session s ON s.rowid=l.fk_session LEFT JOIN '.$this->db->prefix().'sof_das d ON d.rowid=l.fk_das';
			$sql .= ' WHERE l.fk_commande='.(int) $object->id.' ORDER BY l.rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$row = $resql ? $this->db->fetch_object($resql) : null;
		} elseif (in_array($element, array('societe', 'thirdparty'), true)) {
			$sql = 'SELECT p.rowid,p.credit_limit,p.payment_delay_days,p.risk_status,a.ref agence_ref FROM '.$this->db->prefix().'sof_tiers_credit_profile p LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=p.fk_agence_followup';
			$sql .= ' WHERE p.entity='.(int) $GLOBALS['conf']->entity.' AND p.fk_soc='.(int) $object->id.' AND p.status=1 ORDER BY p.rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$profile = $resql ? $this->db->fetch_object($resql) : null;
			if ($profile) {
				$url = dol_buildpath('/agence/mouvement/card.php', 1).'?object=tierscredit&id='.(int) $profile->rowid;
				$this->resprints .= '<tr><td>'.$langs->trans('AgencyCreditProfile').'</td><td><a href="'.$url.'">'.dol_escape_htmltag($langs->trans('CreditLimit').' '.price($profile->credit_limit).' — '.$langs->trans('PaymentDelayDays').' '.(int) $profile->payment_delay_days.' — '.$langs->trans(ucfirst((string) $profile->risk_status))).'</a></td></tr>';
			}
		} elseif (in_array($element, array('product', 'service'), true)) {
			$sql = 'SELECT pd.rowid,d.ref das_ref,a.ref agence_ref FROM '.$this->db->prefix().'sof_product_das pd INNER JOIN '.$this->db->prefix().'sof_das d ON d.rowid=pd.fk_das LEFT JOIN '.$this->db->prefix().'sof_agence a ON a.rowid=pd.fk_agence';
			$sql .= ' WHERE pd.entity='.(int) $GLOBALS['conf']->entity.' AND pd.fk_product='.(int) $object->id.' AND pd.status=1 ORDER BY a.ref,d.ref';
			$resql = $this->db->query($sql);
			$labels = array();
			while ($resql && ($mapping = $this->db->fetch_object($resql))) $labels[] = trim($mapping->das_ref.(!empty($mapping->agence_ref) ? ' — '.$mapping->agence_ref : ''));
			if (!empty($labels)) $this->resprints .= '<tr><td>'.$langs->trans('AgencyDASMappings').'</td><td>'.dol_escape_htmltag(implode(', ', $labels)).'</td></tr>';
		}
		if ($row) {
			$this->resprints .= '<tr><td>'.$langs->trans('AgencyContext').'</td><td>';
			$this->resprints .= dol_escape_htmltag(trim($row->agence_ref.' / '.$row->caisse_ref.' / '.$row->session_ref.' / '.$row->das_ref, ' /'));
			$this->resprints .= '</td></tr>';
		}
		return 0;
	}

	/** Add contextual shortcuts without recreating native Dolibarr actions. */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;
		$langs->loadLangs(array('agence@agence'));
		if (!isModEnabled('agence') || empty($object->id) || empty($object->element)) {
			return 0;
		}
		$this->resprints = '';
		if ($object->element === 'facture') {
			if ((int) $object->type === 2) {
				$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_avoir_tracking WHERE entity='.(int) $GLOBALS['conf']->entity.' AND fk_facture_avoir='.(int) $object->id.' LIMIT 1';
				$resql = $this->db->query($sql);
				$tracking = $resql ? $this->db->fetch_object($resql) : null;
				if ($tracking) {
					$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/avoir/card.php', 1).'?object=avoir&id='.(int) $tracking->rowid.'">'.$langs->trans('ViewAgencyCreditNoteFollowup').'</a>';
				} elseif ($user->hasRight('agence', 'avoir', 'create')) {
					$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/avoir/card.php', 1).'?object=avoir&action=create&fk_facture_avoir='.(int) $object->id.'">'.$langs->trans('TrackNativeCreditNote').'</a>';
				}
			} else {
				if ($user->hasRight('agence', 'mouvement', 'cashin') && (int) $object->statut === 1 && empty($object->paye)) {
					$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/mouvement/encaisser.php', 1).'?fk_facture='.(int) $object->id.'">'.$langs->trans('CollectViaAgency').'</a>';
				}
				if ($user->hasRight('agence', 'remboursement', 'request') && (int) $object->statut >= 1) {
					$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/remboursement/request.php', 1).'?fk_facture_origin='.(int) $object->id.'">'.$langs->trans('RequestRefundFromInvoice').'</a>';
				}
			}
			if ($user->hasRight('agence', 'agence', 'write') || $user->hasRight('agence', 'scope', 'write')) {
				$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/native/link.php', 1).'?native_type=invoice&native_id='.(int) $object->id.'">'.$langs->trans('ManageAgencyAttachment').'</a>';
			}
		} elseif (in_array($object->element, array('commande', 'order'), true) && ($user->hasRight('agence', 'agence', 'write') || $user->hasRight('agence', 'scope', 'write'))) {
			$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/native/link.php', 1).'?native_type=order&native_id='.(int) $object->id.'">'.$langs->trans('ManageAgencyAttachment').'</a>';
		} elseif (in_array($object->element, array('societe', 'thirdparty'), true) && $user->hasRight('agence', 'paiementdiffere', 'create')) {
			$sql = 'SELECT rowid FROM '.$this->db->prefix().'sof_tiers_credit_profile WHERE entity='.(int) $GLOBALS['conf']->entity.' AND fk_soc='.(int) $object->id.' ORDER BY rowid DESC LIMIT 1';
			$resql = $this->db->query($sql);
			$profile = $resql ? $this->db->fetch_object($resql) : null;
			if ($profile) {
				$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/mouvement/card.php', 1).'?object=tierscredit&id='.(int) $profile->rowid.'">'.$langs->trans('ViewCustomerCreditProfile').'</a>';
			} else {
				$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/mouvement/card.php', 1).'?object=tierscredit&action=create&fk_soc='.(int) $object->id.'">'.$langs->trans('ConfigureCustomerCreditProfile').'</a>';
			}
		} elseif (in_array($object->element, array('product', 'service'), true) && $user->hasRight('agence', 'agence', 'write')) {
			$this->resprints .= '<a class="butAction" href="'.dol_buildpath('/agence/das/card.php', 1).'?object=productdas&action=create&fk_product='.(int) $object->id.'">'.$langs->trans('AssignProductToDAS').'</a>';
		}
		return 0;
	}
}
