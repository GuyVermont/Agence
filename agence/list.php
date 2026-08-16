<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';

agence_render_object_list_page('agence', array('agence', 'agenceuser', 'roletransversal'));
