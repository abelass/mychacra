<?php
$db='mychacranet';
$host='mysql.mychacra.net';
$login='abelass';
$pwd='malheur12';
$conn=mysql_connect($host, $login,$pwd);
$sql = "SHOW TABLES FROM $db";
$req = mysql_query($sql);
mysql_select_db($db,$conn);
while ($row = mysql_fetch_row($req))
{
  if(stripos($row[0],'piwik_archive_')!==false)
  {
    $trunc=mysql_query('TRUNCATE TABLE '.$db.'.'.$row[0]);
    mysql_free_result($trunc);
    unset($trunc);
  }
  else
  {
    $opt=mysql_query('OPTIMIZE TABLE '.$db.'.'.$row[0]);
    mysql_free_result($opt);
    unset($opt);
  }
}
mysql_free_result($req);
unset($req);
mysql_close($conn);
unset($conn);
?>