[CmdletBinding()]
param(
	[string] $PhpExecutable = 'php',
	[string] $NodeExecutable = 'node'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$moduleRoot = Split-Path -Parent $PSScriptRoot
$phpCommand = Get-Command -Name $PhpExecutable -ErrorAction Stop
$nodeCommand = Get-Command -Name $NodeExecutable -ErrorAction Stop
$lintFailures = [System.Collections.Generic.List[string]]::new()
$phpFiles = Get-ChildItem -LiteralPath $moduleRoot -Recurse -File -Filter '*.php'

foreach ($file in $phpFiles) {
	& $phpCommand.Source -l $file.FullName *> $null
	if ($LASTEXITCODE -ne 0) {
		$lintFailures.Add($file.FullName)
	}
}

if ($lintFailures.Count -gt 0) {
	Write-Error ('PHP lint failed for: ' + ($lintFailures -join ', '))
}
Write-Output ('PHP lint: PASS ({0} files)' -f $phpFiles.Count)

$javascriptFile = Join-Path $moduleRoot 'js\agence_takepos_session_check.js'
& $nodeCommand.Source --check $javascriptFile
if ($LASTEXITCODE -ne 0) {
	throw 'JavaScript syntax check failed.'
}
Write-Output 'JavaScript syntax: PASS'

$tests = @(
	'install_upgrade_check.php',
	'quick_check.php',
	'operational_check.php',
	'lifecycle_qualification_check.php',
	'industrial_operations_check.php',
	'integration_ecosystem_check.php',
	'concurrency_check.php',
	'entity_isolation_check.php',
	'security_regression_check.php',
	'schema_check.php'
)

Push-Location -LiteralPath $PSScriptRoot
try {
	foreach ($test in $tests) {
		Write-Output ('Running {0}' -f $test)
		& $phpCommand.Source $test
		if ($LASTEXITCODE -ne 0) {
			throw ('Quality gate failed in {0}.' -f $test)
		}
	}
} finally {
	Pop-Location
}

Write-Output 'Agence local quality gate: PASS'
