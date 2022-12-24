<?php
require_once join(DIRECTORY_SEPARATOR, [dirname(__FILE__), '../../includes/common.php']);

$sth = $db->prepare('INSERT INTO whitelist (address) VALUES (:address)');
$sth->bindValue(':address', inet_pton('REPLACE_THIS_TO_YOUR_ADDRESS'), PDO::PARAM_LOB);
$sth->execute();
