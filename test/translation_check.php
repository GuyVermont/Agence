<?php
/* Copyright (C) 2026 iPowerWorld */

/** Static translation coverage check for every visible Agence field label. */

if (PHP_SAPI !== 'cli') {
	die("CLI only\n");
}

$root = dirname(__DIR__);
$errors = array();

function agence_translation_read($path, &$errors)
{
	$translations = array();
	foreach (file($path, FILE_IGNORE_NEW_LINES) ?: array() as $number => $line) {
		if ($line === '' || $line[0] === '#') continue;
		$separator = strpos($line, '=');
		if ($separator === false) continue;
		$key = trim(substr($line, 0, $separator));
		$value = trim(substr($line, $separator + 1));
		if ($key === '' || $value === '') $errors[] = basename($path).' line '.($number + 1).' has an empty key or value';
		if (isset($translations[$key])) $errors[] = basename($path).' defines '.$key.' more than once';
		$translations[$key] = $value;
	}
	return $translations;
}

$fr = agence_translation_read($root.'/langs/fr_FR/agence.lang', $errors);
$en = agence_translation_read($root.'/langs/en_US/agence.lang', $errors);
$required = array();

foreach (glob($root.'/class/sof*.class.php') ?: array() as $path) {
	$source = file_get_contents($path);
	preg_match_all("/'label'\\s*=>\\s*'([A-Za-z][A-Za-z0-9_]*)'/", $source, $matches);
	foreach ($matches[1] as $key) $required[$key] = 'field label';
	preg_match_all("/'arrayofkeyval'\\s*=>\\s*array\\(([^\\)]*)\\)/", $source, $enumMatches);
	foreach ($enumMatches[1] as $enumSource) {
		preg_match_all("/=>\\s*'([A-Za-z][A-Za-z0-9_]*)'/", $enumSource, $valueMatches);
		foreach ($valueMatches[1] as $key) $required[$key] = 'enumerated value';
	}
}

$settingsSource = file_get_contents($root.'/lib/agence.lib.php');
$settingsBoundary = strpos($settingsSource, 'function agence_validate_setting_update');
$settingsPart = $settingsBoundary === false ? '' : substr($settingsSource, 0, $settingsBoundary);
preg_match_all("/'label'\\s*=>\\s*'([A-Za-z][A-Za-z0-9_]*)'/", $settingsPart, $settingMatches);
foreach ($settingMatches[1] as $key) $required[$key] = 'setting label';

$businessStart = strpos($settingsSource, 'function agence_translate_business_code');
$businessEnd = $businessStart === false ? false : strpos($settingsSource, 'function agenceAdminPrepareHead', $businessStart);
$businessPart = ($businessStart === false || $businessEnd === false) ? '' : substr($settingsSource, $businessStart, $businessEnd - $businessStart);
preg_match_all("/=>\s*'([A-Z][A-Za-z0-9_]*)'/", $businessPart, $businessMatches);
foreach ($businessMatches[1] as $key) $required[$key] = 'business value';

$moduleSource = file_get_contents($root.'/core/modules/modAgence.class.php');
preg_match_all("/add(?:LeftMenu|Right)\([^\n]*?['\"]([A-Z][A-Za-z0-9_]*)['\"]/", $moduleSource, $menuMatches);
foreach ($menuMatches[1] as $key) $required[$key] = 'menu or permission label';

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
	$path = $file->getPathname();
	if ($file->getExtension() !== 'php' || strpos(str_replace('\\', '/', $path), '/test/') !== false) continue;
	$source = file_get_contents($path);
	preg_match_all("/->trans(?:noentities|noentitiesnoconv)?\\(\\s*['\"]([A-Za-z][A-Za-z0-9_]*)['\"]/", $source, $matches);
	foreach ($matches[1] as $key) $required[$key] = 'explicit UI translation';
}

ksort($required);
foreach ($required as $key => $usage) {
	if (!isset($fr[$key])) $errors[] = 'French translation missing for '.$key.' ('.$usage.')';
	if (!isset($en[$key])) $errors[] = 'English translation missing for '.$key.' ('.$usage.')';
}

if ($errors) {
	foreach ($errors as $error) echo '[KO] '.$error.PHP_EOL;
	exit(1);
}

echo '[OK] '.count($required).' visible translation keys are complete in French and English'.PHP_EOL;
echo '[OK] language files contain no duplicate or empty entries'.PHP_EOL;
echo "Translation coverage check completed successfully.\n";
