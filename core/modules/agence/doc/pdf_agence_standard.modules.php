<?php
/* Copyright (C) 2026 SOFITOUL */

/**
 * \file       htdocs/custom/agence/core/modules/agence/doc/pdf_agence_standard.modules.php
 * \ingroup    agence
 * \brief      Generic PDF model for SOFITOUL agency objects.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';

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
	public $description = 'AgencyPDFModelDescription';

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

	/** Return the entity-isolated absolute path of an object's generated document. */
	public function getDocumentPath($object)
	{
		global $conf;
		$root = !empty($conf->agence->dir_output) ? $conf->agence->dir_output : DOL_DATA_ROOT.'/agence';
		$entity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
		$ref = !empty($object->ref) ? $object->ref : (!empty($object->id) ? (string) $object->id : (string) $object->rowid);
		$subdir = $root.'/documents/entity_'.$entity.'/'.$object->table_element;
		return $subdir.'/'.dol_sanitizeFileName($object->table_element.'_'.$ref).'.pdf';
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

		$file = $this->getDocumentPath($object);
		$subdir = dirname($file);
		if (!dol_is_dir($subdir)) {
			if (dol_mkdir($subdir) < 0) {
				$this->result['error'] = $outputlangs->transnoentities('UnableToCreateEntityDocumentDirectory');
				return -1;
			}
		}

		$ref = !empty($object->ref) ? $object->ref : (!empty($object->id) ? (string) $object->id : (string) $object->rowid);

		$pdf = pdf_getInstance('A4', 'mm', 'P');
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$pdf->SetAutoPageBreak(1, 15);
		$pdf->SetTitle($outputlangs->transnoentities('ModuleAgenceName').' - '.$ref);
		$pdf->SetSubject($outputlangs->transnoentities('AgencyDocument'));
		$pdf->SetCreator('Dolibarr');
		$pdf->Open();
		$pdf->AddPage();
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $defaultFontSize);

		$y = 15;
		$pdf->SetFillColor(25, 113, 172);
		$pdf->Rect(0, 0, 210, 11, 'F');
		$pdf->SetDrawColor(242, 115, 10);
		$pdf->SetLineWidth(1.2);
		$pdf->Line(15, 12, 195, 12);
		$pdf->SetTextColor(40, 50, 68);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 14);
		$pdf->SetXY(15, $y);
		$pdf->MultiCell(120, 8, $outputlangs->transnoentities('ModuleAgenceName'), 0, 'L');
		$brandLogo = DOL_DOCUMENT_ROOT.'/custom/agence/img/ipowerworld-logo.svg';
		if (is_readable($brandLogo) && method_exists($pdf, 'ImageSVG')) {
			$pdf->ImageSVG($brandLogo, 145, $y - 2, 50, 14, '', '', '', 0, false);
		} else {
			$pdf->SetTextColor(25, 113, 172);
			$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 11);
			$pdf->SetXY(145, $y);
			$pdf->Cell(50, 7, 'iPowerWorld', 0, 0, 'R');
			$pdf->SetTextColor(40, 50, 68);
		}
		$y += 10;
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 9);
		$pdf->SetXY(15, $y);
		$objectKey = '';
		$objectLabel = $outputlangs->transnoentities('AgencyDocument');
		foreach (agence_get_object_registry() as $registryKey => $registryConfig) {
			if ($registryConfig['class'] === get_class($object)) {
				$objectKey = $registryKey;
				$objectLabel = $outputlangs->transnoentities($registryConfig['singular']);
				break;
			}
		}
		$pdf->MultiCell(180, 6, $outputlangs->transnoentities('AgencyDocument').' - '.$objectLabel, 0, 'L');
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
			$label = $outputlangs->transnoentities($field['label']);
			$value = isset($object->$fieldKey) ? $object->$fieldKey : '';
			if ($value === null || $value === '') {
				$value = '-';
			} else {
				$value = html_entity_decode(strip_tags(agence_format_field_value($fieldKey, $value, $field, $objectKey)), ENT_QUOTES, 'UTF-8');
			}
			$label = $outputlangs->convToOutputCharset((string) $label);
			$value = $outputlangs->convToOutputCharset(dol_trunc((string) $value, 500));
			$rowHeight = method_exists($pdf, 'getStringHeight') ? max(5, (float) $pdf->getStringHeight(120, $value)) : 5;
			if ($y + $rowHeight > 277) {
				$pdf->AddPage();
				$y = 15;
			}
			$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 8);
			$pdf->SetXY(15, $y);
			$pdf->MultiCell(55, 5, dol_trunc($label, 35), 0, 'L');
			$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 8);
			$pdf->SetXY(72, $y);
			$pdf->MultiCell(120, 5, $value, 0, 'L');
			$y += $rowHeight + 1;
		}

		// Disable the automatic break before positioning the footer: placing it
		// exactly on the bottom margin would otherwise create a blank last page.
		$pdf->SetAutoPageBreak(false);
		$pdf->SetY(-14);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 7);
		$pdf->SetTextColor(40, 50, 68);
		$pdf->Cell(0, 8, 'iPowerWorld · csa@ipowerworld.net · '.$outputlangs->transnoentities('GeneratedByDolibarr').' - '.dol_print_date(dol_now(), 'dayhour', false, $outputlangs), 0, 0, 'C');

		$pdf->Output($file, 'F');
		if (!empty($pdf->error)) {
			$this->result['error'] = $pdf->error;
			return -1;
		}

		$this->result['fullpath'] = $file;
		$this->result['relativepath'] = 'documents/entity_'.((int) $conf->entity).'/'.$object->table_element.'/'.basename($file);
		return 1;
	}
}
