<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/agence/lib/agence_crud.lib.php';

agence_render_object_list_page('mouvement', array('mouvement', 'paiementlink', 'facturelink', 'commandelink', 'takeposlink', 'agenceuser', 'roletransversal', 'productdas'));
