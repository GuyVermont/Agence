<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/core/modules/agence/doc/pdf_agence_standard.modules.php
 * \ingroup    agence
 * \brief      Generic PDF model for SOFITOUL agency objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Generic PDF model for SOFITOUL objects.
 */
class pdf_agence_standard
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name = 'agence_standard';

	/**
	 * @var string Description
	 */
	public $description = 'Generic PDF for SOFITOUL agency objects';

	/**
	 * @var string Type
	 */
	public $type = 'pdf';

	/**
	 * @var array<string,string> Result data
	 */
	public $result = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Write object PDF.
	 *
	 * @param CommonObject $object      Object
	 * @param Translate    $outputlangs Language
	 * @param string       $srctemplatepath Source template path
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '')
	{
		global $conf, $mysoc;

		if (!is_object($outputlangs)) {
			$outputlangs = $GLOBALS['langs'];
		}
		$outputlangs->loadLangs(array('main', 'agence@agence'));

		$dir = !empty($conf->agence->dir_output) ? $conf->agence->dir_output : DOL_DATA_ROOT.'/agence';
		$subdir = $dir.'/documents/'.$object->table_element;
		if (!dol_is_dir($subdir)) {
			dol_mkdir($subdir);
		}

		$ref = !empty($object->ref) ? $object->ref : (!empty($object->id) ? (string) $object->id : (string) $object->rowid);
		$file = $subdir.'/'.dol_sanitizeFileName($object->table_element.'_'.$ref).'.pdf';

		$pdf = pdf_getInstance('A4', 'mm', 'P');
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$pdf->SetAutoPageBreak(1, 15);
		$pdf->SetTitle($outputlangs->trans('ModuleAgenceName').' - '.$ref);
		$pdf->SetSubject($outputlangs->trans('AgencyDocument'));
		$pdf->SetCreator('Dolibarr');
		$pdf->Open();
		$pdf->AddPage();
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $defaultFontSize);

		$y = 15;
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 14);
		$pdf->SetXY(15, $y);
		$pdf->MultiCell(180, 8, $outputlangs->trans('ModuleAgenceName'), 0, 'L');
		$y += 10;
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 9);
		$pdf->SetXY(15, $y);
		$pdf->MultiCell(180, 6, $outputlangs->trans('AgencyDocument').' - '.$object->table_element, 0, 'L');
		$y += 8;

		if (is_object($mysoc)) {
			$pdf->SetXY(15, $y);
			$pdf->MultiCell(180, 5, $mysoc->name, 0, 'L');
			$y += 6;
		}

		$pdf->SetDrawColor(190, 190, 190);
		$pdf->Line(15, $y, 195, $y);
		$y += 8;

		foreach ($object->fields as $fieldKey => $field) {
			if (in_array($fieldKey, array('rowid', 'entity', 'tms', 'import_key'), true)) {
				continue;
			}
			if (isset($field['visible']) && $field['visible'] < -1) {
				continue;
			}
			if ($y > 270) {
				$pdf->AddPage();
				$y = 15;
			}
			$label = $outputlangs->trans($field['label']);
			$value = isset($object->$fieldKey) ? $object->$fieldKey : '';
			if ($value === null || $value === '') {
				$value = '-';
			}
			if (!empty($field['type']) && preg_match('/^(double|real|price)/i', $field['type'])) {
				$value = price($value);
			}
			if (!empty($field['type']) && preg_match('/datetime/i', $field['type'])) {
				$ts = is_numeric($value) ? (int) $value : $this->db->jdate($value);
				$value = $ts ? dol_print_date($ts, 'dayhour', false, $outputlangs) : $value;
			} elseif (!empty($field['type']) && preg_match('/^date/i', $field['type'])) {
				$ts = is_numeric($value) ? (int) $value : $this->db->jdate($value);
				$value = $ts ? dol_print_date($ts, 'day', false, $outputlangs) : $value;
			}
			$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 8);
			$pdf->SetXY(15, $y);
			$pdf->MultiCell(55, 5, dol_trunc($label, 35), 0, 'L');
			$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 8);
			$pdf->SetXY(72, $y);
			$pdf->MultiCell(120, 5, dol_trunc((string) $value, 250), 0, 'L');
			$y += 6;
		}

		$pdf->SetY(-15);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 7);
		$pdf->Cell(0, 10, $outputlangs->trans('GeneratedByDolibarr').' - '.dol_print_date(dol_now(), 'dayhour', false, $outputlangs), 0, 0, 'C');

		$pdf->Output($file, 'F');
		if (!empty($pdf->error)) {
			$this->result['error'] = $pdf->error;
			return -1;
		}

		$this->result['fullpath'] = $file;
		$this->result['relativepath'] = 'documents/'.$object->table_element.'/'.basename($file);
		return 1;
	}
}
