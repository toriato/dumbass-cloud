<?php
define(
  'CONFIG',
  json_decode(
    file_get_contents(
      join(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'common.json'])
    ),
    true
  )
);

// https://gist.github.com/henriquemoody/6580488
define('HTTP_STATUS_MESSAGES', [
  100 => 'Continue',
  101 => 'Switching Protocols',
  102 => 'Processing', // WebDAV; RFC 2518
  103 => 'Early Hints', // RFC 8297
  200 => 'OK',
  201 => 'Created',
  202 => 'Accepted',
  203 => 'Non-Authoritative Information', // since HTTP/1.1
  204 => 'No Content',
  205 => 'Reset Content',
  206 => 'Partial Content', // RFC 7233
  207 => 'Multi-Status', // WebDAV; RFC 4918
  208 => 'Already Reported', // WebDAV; RFC 5842
  226 => 'IM Used', // RFC 3229
  300 => 'Multiple Choices',
  301 => 'Moved Permanently',
  302 => 'Found', // Previously "Moved temporarily"
  303 => 'See Other', // since HTTP/1.1
  304 => 'Not Modified', // RFC 7232
  305 => 'Use Proxy', // since HTTP/1.1
  306 => 'Switch Proxy',
  307 => 'Temporary Redirect', // since HTTP/1.1
  308 => 'Permanent Redirect', // RFC 7538
  400 => 'Bad Request',
  401 => 'Unauthorized', // RFC 7235
  402 => 'Payment Required',
  403 => 'Forbidden',
  404 => 'Not Found',
  405 => 'Method Not Allowed',
  406 => 'Not Acceptable',
  407 => 'Proxy Authentication Required', // RFC 7235
  408 => 'Request Timeout',
  409 => 'Conflict',
  410 => 'Gone',
  411 => 'Length Required',
  412 => 'Precondition Failed', // RFC 7232
  413 => 'Payload Too Large', // RFC 7231
  414 => 'URI Too Long', // RFC 7231
  415 => 'Unsupported Media Type', // RFC 7231
  416 => 'Range Not Satisfiable', // RFC 7233
  417 => 'Expectation Failed',
  418 => 'I\'m a teapot', // RFC 2324, RFC 7168
  421 => 'Misdirected Request', // RFC 7540
  422 => 'Unprocessable Entity', // WebDAV; RFC 4918
  423 => 'Locked', // WebDAV; RFC 4918
  424 => 'Failed Dependency', // WebDAV; RFC 4918
  425 => 'Too Early', // RFC 8470
  426 => 'Upgrade Required',
  428 => 'Precondition Required', // RFC 6585
  429 => 'Too Many Requests', // RFC 6585
  431 => 'Request Header Fields Too Large', // RFC 6585
  451 => 'Unavailable For Legal Reasons', // RFC 7725
  500 => 'Internal Server Error',
  501 => 'Not Implemented',
  502 => 'Bad Gateway',
  503 => 'Service Unavailable',
  504 => 'Gateway Timeout',
  505 => 'HTTP Version Not Supported',
  506 => 'Variant Also Negotiates', // RFC 2295
  507 => 'Insufficient Storage', // WebDAV; RFC 4918
  508 => 'Loop Detected', // WebDAV; RFC 5842
  510 => 'Not Extended', // RFC 2774
  511 => 'Network Authentication Required', // RFC 6585
]);

function exitWithStatusCode(int $statusCode, string $message = null): never
{
  // use default status message if exists
  if (is_null($message)) {
    $message = @HTTP_STATUS_MESSAGES[$statusCode];
  }

  http_response_code($statusCode);
  die($message);
}

session_start([
  'cookie_domain' => '.dumbass.cloud',
  'cookie_secure' => true,
  'cookie_httponly' => true,
]);

$db = new PDO(
  CONFIG['database']['external']['dsn'],
  CONFIG['database']['external']['user'],
  CONFIG['database']['external']['password'],
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]
);

function redirect(string $url): never
{
  header(sprintf('Location: %s', $url));
  exitWithStatusCode(302);
}

function getChallenge(int $id)
{
  global $db;

  $sth = $db->prepare(<<<SQL
    SELECT * 
      FROM challenges 
      WHERE
        -- 접속한 아이피가 일치하는 레코드 중 유효한 거부 레코드가 있다면
        (
          address = INET6_ATON(:address) AND
          reject_reason IS NOT NULL
        )
        OR 
        -- 아이디가 일치하고 유효한 토큰일 경우
        (
          id = :id AND
          (
            expire_at IS NULL OR
            expire_at >= UNIX_TIMESTAMP()
          )
        )
      ORDER BY id DESC
  SQL);

  $sth->bindValue(':address', $_SERVER['REMOTE_ADDR'], PDO::PARAM_LOB);
  $sth->bindValue(':id', $id, PDO::PARAM_INT);
  $sth->execute();

  if ($sth->rowCount() > 0) {
    $record = $sth->fetch();
    $record['token'] = bin2hex($record['token']);
    $record['address'] = inet_ntop($record['address']);
    return $record;
  }

  return null;
}
