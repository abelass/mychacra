<?php 
$to = $_REQUEST['sendto'] ; 
$from = $_REQUEST['email'] ; 
$name = $_REQUEST['Name'] ; 
$headers = "From: $from"; 
$subject = "Offre site"; 
$location= $_REQUEST['url']."&confirmer=oui";


$message = "";
$message .= "Nom : " .$_REQUEST['Name']."\n";
$message .= "Email : ". $_REQUEST['Email']."\n";
$str_fonctions = join(',', $_REQUEST['Fonctions']);
$message .= "Fonctionnalités: ".$str_fonctions."\n";
$message .= "Autres : ". $_REQUEST['Autres']."\n";
$message .= "Commentaire : ". $_REQUEST['Commentaire']."\n";



$headers2 = "From: websolutions@mychacra.net"; 
$subject2 = "Offer request"; 
$autoreply = "Thanks for your interest. \n We will send you a concrete offer";
function EmailValidation($email) { 
    $email = htmlspecialchars(stripslashes(strip_tags($email))); //parse unnecessary characters to prevent exploits
    
    if ( eregi ( '[a-z||0-9]@[a-z||0-9].[a-z]', $email ) ) { //checks to make sure the email address is in a valid format
    $domain = explode( "@", $email ); //get the domain name
        
        if ( @fsockopen ($domain[1],80,$errno,$errstr,3)) {
            //if the connection can be established, the email address is probabley valid
            return true;
            /*
            
            GENERATE A VERIFICATION EMAIL
            
            */
            
        } else {
            return false; //if a connection cannot be established return false
        }
    
    } else {
        return false; //if email address is an invalid format return false
    }
} 
 function verif()
{
	$erreur ="";

if(EmailValidation($_POST['email'])) {}
	 else {
            $erreur .= "<li>Email</li>";
        } 

if($_REQUEST['Name'] == '') $erreur .=  "<li>Name</li>"; 

if (is_array ($_REQUEST['Fonctions'])==false || count ($_REQUEST['Fonctions'])>=2)
		$erreur .= "<li>Features (at least one)</li>";

if (!empty($erreur)){
	print("<b>Missing Fields</b><ul>".$erreur."</ul><a href='javascript:javascript:history.go(-1)'>Click here to go back to previous page</a>");
	return false;
	}
	return true;
}

if (verif()==false) exit(0);
$send = mail($to, $subject, $message, $headers); 
$send2 = mail($from, $subject2, $autoreply, $headers2); 
if($send) 
{header('location:'.$location);} 
else 
{print "Hemos encontrado un error, porfavo contacte a websolutions@mychacra.net"; } 





?>