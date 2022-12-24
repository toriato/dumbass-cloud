## `common.json` 설정하는 방법
```jsonc
{
  "article": "https://arca.live/b/aiart/64272115", // 토큰 댓글 확인할 게시글 주소
  "database": {
    // 도커 내부에서 사용할 데이터베이스 연결 정보
    "internal": {
      "host": "localhost",
      "port": 3389,
      "database": "dumbass",
      "user": "dumbass",
      "password": "dumbass-password"
    },
    // 도커 외부에서 사용할 데이터베이스 연결 정보 
    // 현재는 파이썬 스크립트에서만 사용함
    "external": {
      "dsn": "mysql:host=host.containers.internal;dbname=dumbass",
      "user": "dumbass",
      "password": "dumbass-password"
    }
  },
  // 토큰 만료 시간 (초)
  "token": {
    "expire": 10800
  }
}
```