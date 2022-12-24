<?php
require_once join(DIRECTORY_SEPARATOR, [dirname(__FILE__), '../includes/common.php']);

if (isset($_SESSION['challenge_id'])) {
  $challenge = getChallenge($_SESSION['challenge_id']);

  if ($challenge === null) {
    exitWithStatusCode(401);
  }

  // 만료된 토큰
  if (
    $challenge['expire_at'] !== null &&
    $challenge['expire_at'] < time()
  ) {
    exitWithStatusCode(401);
  }

  // 정상적으로 인증된 토큰
  if (
    $challenge['reject_reason'] === null &&
    $challenge['expire_at'] !== null &&
    $challenge['expire_at'] >= time()
  ) {
    exitWithStatusCode(200);
  }
}

exitWithStatusCode(401);
