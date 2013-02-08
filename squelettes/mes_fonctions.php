<?php
function url_lang ($langues) {
    $texte = '';
	$tab_langues = explode(',', $GLOBALS['meta']['langues_multilingue']);
    while ( list($clef, $valeur) = each($tab_langues) ) 
	if ($valeur == $GLOBALS['spip_lang']) { 
	$texte .= '<li>  | '.traduire_nom_langue($valeur).'</li>'; 
	}
	else { $GLOBALS['delais'] = 0;
	$cible = str_replace('&amp;', '&amp;', parametre_url(self( /* racine */ true), 'lang', ''.$valeur.'','&amp;')) ;
	$texte .= '<li> | <a href="'.parametre_url(generer_url_action('cookie'), 'url', $cible, '&amp;').'">'.traduire_nom_langue($valeur).'</a></li>'; 
	}
    return $texte;
}


?>
