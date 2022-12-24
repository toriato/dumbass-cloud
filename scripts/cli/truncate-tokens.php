<?php
require_once join(DIRECTORY_SEPARATOR, [dirname(__FILE__), '../../includes/common.php']);

$sth = $db->prepare('TRUNCATE challenges');
$sth->execute();
