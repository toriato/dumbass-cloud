<?php
require_once join(DIRECTORY_SEPARATOR, [dirname(__FILE__), '../../includes/common.php']);

$sth = $db->prepare(<<<SQL
  SELECT * 
    FROM challenges 
    ORDER BY id DESC
    LIMIT 10
SQL);
$sth->execute();

foreach ($sth->fetchAll() as $record) {
  printf(
    "[%s] %s by %s\n",
    date('c', $record['created_at']),
    bin2hex($record['token']),
    inet_ntop($record['address'])
  );
}
