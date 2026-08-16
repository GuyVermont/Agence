<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';

agence_render_object_card_page('audit', array('audit', 'alerte'));
