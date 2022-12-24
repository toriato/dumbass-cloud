<?php
require_once '../../includes/common.php';

$challenge = null;

// 토큰 확인하기
if (isset($_SESSION['challenge_id'])) {
  $challenge = getChallenge($_SESSION['challenge_id']);

  if (
    // 삭제된 토큰
    $challenge === null
  ) {
    $_SESSION = [];
  } else if (
    // 만료된 토큰
    $challenge['expire_at'] !== null &&
    $challenge['expire_at'] < time()
  ) {
    $challenge = null;
  } else if (
    // 차단된 상태
    $challenge['reject_reason'] !== null
  ) {
  } else if (
    // 인증 대기 상태
    $challenge['expire_at'] == null
  ) {
  }
}

// 토큰 새로 만들기
if (is_null($challenge)) {
  // 무작위 토큰 만들기
  $challenge = [
    'address' => @$_SERVER['REMOTE_ADDR'],
    'token' => bin2hex(random_bytes(16))
  ];

  if (!$challenge['address']) {
    exitWithStatusCode(500, 'something went wrong, cannot convert address to binary');
  }

  $sth = $db->prepare(<<<SQL
    INSERT INTO challenges
      (token, address, created_at) VALUES
      (UNHEX(:token), INET6_ATON(:address), :created_at)
  SQL);

  $sth->bindValue(':token', $challenge['token'], PDO::PARAM_STR);
  $sth->bindValue(':address', $challenge['address'], PDO::PARAM_LOB);
  $sth->bindValue(':created_at', time(), PDO::PARAM_INT);

  if (!$sth->execute()) {
    exitWithStatusCode(500, 'something went wrong, failed to execute database insert');
  }

  $_SESSION['challenge_id'] = $db->lastInsertId();
}

// API?
if (isset($_GET['validate'])) {
  $sth = $db->prepare(<<<SQL
    SELECT
      COUNT(1) AS total,
      COUNT(DISTINCT CASE WHEN reject_reason IS NULL AND used_by IS NOT NULL THEN used_by ELSE NULL END) AS granted,
      COUNT(DISTINCT CASE WHEN reject_reason IS NOT NULL THEN used_by ELSE NULL END) AS rejected
      FROM challenges
      WHERE
        created_at >= (UNIX_TIMESTAMP() - 60 * 60 * 3)
  SQL);
  $sth->execute();
  $stats = $sth->fetch();

  exitWithStatusCode(
    @$challenge['solved_at'] ? 200 : 401,
    json_encode([
      'article' => CONFIG['article'],
      'challenge' => [
        'token' => $challenge['token'],
        'reject_reason' => @$challenge['reject_reason'],
        'expire_at' => @$challenge['expire_at']
      ],
      'stats' => $stats
    ], JSON_UNESCAPED_UNICODE)
  );
}

if (
  @$challenge['reject_reason'] === null &&
  @$challenge['expire_at'] !== null &&
  @$challenge['expire_at'] >= time()
) {
  redirect(isset($_GET['go']) ? $_GET['go'] : 'https://dumbass.cloud');
}

?>
<!--
  yo stop leaking my precious gpu powers
  it's not mean to be used by (you)
-->
<!Doctype HTML>
<html>

<head>
  <title>Dumbass Checker</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    html,
    body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
    }

    body {
      display: grid;
      background-color: black;
      place-content: center;
      text-align: center;
      color: white;
    }

    header h2 {
      font-size: 1.5em;
    }

    header h3 {
      padding: .25em;
      background-color: white;
      font-family: monospace;
      color: black;
    }

    a[href] {
      color: #fcba03;
    }

    p {
      margin: .25em 0;
    }

    footer {
      margin-top: 1em;
      color: grey;
    }
  </style>
</head>

<body>

  <header>
    <h2></h2>
    <h3></h3>
  </header>
  <content></content>
  <footer></footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js" integrity="sha512-42PE0rd+wZ2hNXftlM78BSehIGzezNeQuzihiBCvUEB3CVxHvsShF86wBWwQORNxNINlBPuq7rG4WWhNiTVHFg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script>
    moment.locale('ko')

    const $title = document.querySelector('header h2')
    const $token = document.querySelector('header h3')
    const $content = document.querySelector('content')
    const $footer = document.querySelector('footer')

    let intervalId

    function validate() {
      fetch(
          `${location.origin}${location.pathname}?validate`, {
            redirect: 'manual'
          })
        .then(res => {
          if (res.status === 429) {
            $content.innerHTML = `
              <p>인증 창을 하나만 열어주세요</p>
            `
            return
          }

          return res.json()
        })
        .then(({
          article,
          challenge,
          stats
        }) => {
          if (stats) {
            $footer.innerHTML = `
              <p>최근 걸러진 바보: ${parseInt(stats.granted, 10) + parseInt(stats.rejected, 10)}명 중 ${stats.rejected}명</p>
            `
          }

          // 토큰이 다르다면 업데이트
          // 계속 수정하면 복사하기 넘 어려움...
          if (challenge.token !== $token.dataset.token) {
            $token.dataset.token = challenge.token
            $token.textContent = challenge.token.toUpperCase()
          }

          // 차단 메세지
          if (challenge.reject_reason) {
            let duration = '영원히 차단됐습니다'
            if (challenge.expire_at) {
              duration = moment.unix(challenge.expire_at).fromNow() + ' 차단이 풀립니다'
            } else {
              // 영구 차단 박혔으면 개추ㅋㅋ
              clearInterval(intervalId)
            }

            $title.innerHTML = `<span style="color:red">${challenge.reject_reason}</span>`
            $content.innerHTML = `
              <p>${duration}</p>
            `
            return
          }

          // 인증 성공
          if (challenge.expire_at !== null) {
            const params = new URLSearchParams(location.search)
            location.href = params.has('go') ? params.get('go') : 'https://dumbass.cloud';
            return
          }

          // 인증 대기
          $title.textContent = 'Am I A Dumb?'
          $content.innerHTML = `
            <p>이 사이트는 <a href="https://arca.live/b/aiart">AI그림 채널</a> 사용자만 사용할 수 있습니다</p>
            <p>위 토큰을 아래 게시글에 댓글로 올리면 인증됩니다</p>
            <p><a target="_blank" href="${article}">${article}</a></p>
          `
        })
    }

    intervalId = setInterval(validate, 1000)

    validate()
  </script>

</html>