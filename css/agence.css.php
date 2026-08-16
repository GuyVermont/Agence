<?php
/* Copyright (C) 2026 SOFITOUL */
if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
require '../../../main.inc.php';
header('Content-type: text/css');
?>
.mod-agence .info-box-number {
	font-size: 13px;
	font-weight: normal;
	line-height: 1.4;
}
.mod-agence .badge-status {
	margin-bottom: 4px;
}
