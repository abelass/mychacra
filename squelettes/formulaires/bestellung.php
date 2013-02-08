<?php
function formulaires_bestellung_charger_dist(){
     	$valeurs = array('email'=>_request('email'),'bemerkungen'=>_request('bemerkungen'),'name'=>_request('name'),'vorname'=>_request('vorname'),'telefon'=>_request('telefon'));
	reset($_POST);
	
	while (list($key, $val) = each($_POST)) { 
		if(preg_match('/portionen/', $key)){
			 $valeurs[$key]=$val;
				}
			}   

	return $valeurs;
}

function formulaires_bestellung_verifier_dist(){
	$erreurs = array();
	// verifier que les champs obligatoires sont bien la :
	foreach(array('email','name') as $obligatoire)
		if (!_request($obligatoire)) $erreurs[$obligatoire] = 'Dieses Feld ist obligatorisch';
		
	reset($_POST); 
	while (list($key, $val) = each($_POST)) { 
		if(preg_match('/portionen/', $key) AND $val){
			$commande='oui'; 					
				}				
			}
						
	// Test Anti Robot
	if (_request('test'))$erreurs['robot'] = 'Sind sie ein Mensch? Dann lassen Sie dieses feld frei';
	
	// verifier que si un email a été saisi, il est bien valide :
	include_spip('inc/filtres');
	
	if (_request('email') AND !email_valide(_request('email')))$erreurs['email'] = 'Email ungültig';
	
	if (!$commande)$erreurs['commande'] = 'Sie haben noch keine Bestellung aufgegeben';

	if (count($erreurs))
		$erreurs['message_erreur'] = 'Fehlerhafte Eingabe !';
	return $erreurs;
}

function formulaires_bestellung_traiter_dist(){
include_spip('inc/mail');
	$date=date('d-m-Y');
	$email_bestellung = _request('email');
	if(_request('telefon')) $telefon='<div> telefon:'._request('telefon').'</div>';
	if(_request('bemerkungen')) $bemerkung='<p> Bemerkung:<div>'._request('bemerkungen').'</div></p>';
	$header ="Content-Type: text/html; charset=UTF-8\n"
		."Content-Transfer-Encoding: 8bit\n" 
		."MIME-Version: 1.0\n";	
	reset($_POST); 
	while (list($key, $val) = each($_POST)) { 
		if(preg_match('/portionen/', $key) AND $val){
			$id_article=str_replace('portionen_','',$key); 
			$result = sql_select("*", "spip_articles", "id_article=".$id_article);
			while($data = spip_fetch_array($result)) {
			$bestellung .= 	'<h2>'.$data['titre'].': </h2>
					<div>'.$val.' Portion(en)</div>';											
				}				
			}
		}	
	$email = $GLOBALS['meta']['email_webmaster'];
	$from = $email_bestellung;
	$sujet = 'Bestellung';
	$message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<title>Bestellung</title>
			</head>
				<body>
				<div>Hallo</div>
				<div>'
				._request('vorname').'&nbsp;'. _request('name').'</div>'
				.$telefon.'
				<div>email: '.$email_bestellung.'</div>
				<div>Hat folgende Bestellung aufgegeben:</div><div>'.$bestellung.
				'</div>'
				.$bemerkung.'
				</body>
			</html>';	
	if (envoyer_mail($email,$sujet,$message,$from,$header)){
		spip_log("Email visiteur $email\n$sujet\n$message\n",'email_bestellung');
		$message = 	'<div>Vielen Dank '._request('vorname').'&nbsp;'. _request('name').'!</div>
			<div>Si haben folgendes bestellt:</div>'
			.$bestellung;
			}
	else	{
		spip_log("Email visiteur echec $email\n$sujet\n$message\n",'email_bestellung');
		$message = 	'<div>Problem!</div>
			<div>Kontaktieren Sie</div>';
		}
		
		return array('message_ok'=>$message);
}
?>