#!/usr/bin/env python
import sys
import re
import time
import json
import argparse
import requests
import mariadb
import logging
from os import path
from datetime import datetime, timedelta
from systemd.journal import JournalHandler
from lxml.html import fromstring

SCRIPT_DIR = path.dirname(path.realpath(__file__))

parser = argparse.ArgumentParser(prog='token-solver.py')
parser.add_argument('--is-fresh-account')
args = parser.parse_args()

with open(path.join(SCRIPT_DIR, '../includes/common.json'), 'r') as f:
    config = json.loads(f.read())


def get_score_from_profile(user: str) -> int:
    article_id = config['article'].split('/').pop()
    total_score = 0

    res = requests.get(f"https://arca.live/u/@{user.replace('#', '/')}")
    doc = fromstring(res.text)

    for node in doc.cssselect('.user-recent'):
        try:
            link = node.cssselect('a:nth-child(2)')[0].get('href')
            created_at = datetime.strptime(
                # 2022-10-28T17:22:07.000Z
                node.cssselect('time')[0].get('datetime').split('.').pop(0),
                '%Y-%m-%dT%H:%M:%S'
            )
        except IndexError:
            continue

        # 현재 인증 글에 올라온 댓글이라면 무시하기
        if article_id in link:
            continue

        # 시간까지 포함하면 점수에 영향을 너무 크게 줌...
        # 물론 이러면 자정 땡! 하자마자 차단 박힐 가능성 있음
        # 아카라이브에 API가 없는데 나보고 어쩌라고!!!!!!!!!!!!!!!!!!
        # created_at_delta = -(created_at - datetime.now())

        created_at_delta = -(created_at.date() - datetime.now().date())

        # 과거에 작성한 글 또는 댓글은 무시
        score = 1 - created_at_delta / timedelta(weeks=4)

        # 주요 채널 밖이면 점수 낮게 쳐줌
        if '/aiart/' not in link and '/hypernetworks/' not in link:
            score = score * 0.5

        # 아카콘은 점수가 낮음, 의미없는 아카콘 남발하지 말라고~
        if len(node.cssselect('.emoticon')) > 0:
            score = score * 0.3

        if score > 0:
            total_score = total_score + score

    # 갤로그에선 30개만 보여주기 때문에 반고닉이 얻을 수 있는 최대 점수는 30점
    return total_score / 30


def get_score_from_board(user: str) -> int:
    total_score = 0

    res = requests.get(
        f'https://arca.live/b/aiart?target=nickname&keyword={user}')
    doc = fromstring(res.text)

    for node in doc.cssselect('.list-table a:not(.notice)'):
        try:
            created_at = datetime.strptime(
                # 2022-10-28T17:22:07.000Z
                node.cssselect('time')[0].get('datetime').split('.').pop(0),
                '%Y-%m-%dT%H:%M:%S'
            )
        except IndexError:
            continue

        created_at_delta = -(created_at.date() - datetime.now().date())

        # 일주일 전에 작성한 글은 무시
        score = 1 - created_at_delta / timedelta(weeks=1)

        if score > 0:
            total_score = total_score + score

    # 하루에 글 5개 이상 썼으면 인정이지ㅋㅋ
    return total_score / 5


def is_fresh_account(user: str) -> bool:
    # # 반고닉은 검색하는게 의미가 없어서 프로필 기준으로 점수 구해옴
    # if '#' in user or '/' in user:
    #     return get_score_from_profile(user) < 0.4

    # return get_score_from_board(user) < 1

    score = get_score_from_profile(user)

    return score < 0.3


if args.is_fresh_account:
    print(is_fresh_account(args.is_fresh_account))
    quit()

log = logging.getLogger('dumbass-token-solver')
log.setLevel(logging.INFO)
log.addHandler(JournalHandler())

db = mariadb.connect(**config['database']['internal'])


def get_challenge_by_token(token: str) -> object:
    cursor = db.cursor(dictionary=True)
    cursor.execute(f'''
        SELECT * 
            FROM challenges
            WHERE
                token = UNHEX(?)
    ''', (
        token,
    ))

    record = cursor.fetchone()
    cursor.close()
    return record


def get_challenge_by_user(user: str) -> object:
    cursor = db.cursor(dictionary=True)
    cursor.execute(f'''
        SELECT * 
            FROM challenges
            WHERE
                used_by = ? AND
                (
                    expire_at IS NULL OR
                    expire_at > UNIX_TIMESTAMP()
                )
            ORDER BY
                expire_at IS NULL DESC,
                expire_at DESC,
                reject_reason IS NOT NULL DESC
            LIMIT 1
    ''', (
        user,
    ))

    record = cursor.fetchone()
    cursor.close()
    return record


latest_comment_id = 0

# 댓글로부터 토큰 가져오기
while True:
    with open(path.join(SCRIPT_DIR, '../includes/common.json'), 'r') as f:
        config = json.loads(f.read())

    res = requests.get(config['article'])
    doc = fromstring(res.text)

    for node in doc.cssselect('.comment-item'):
        comment_id = int(node.get('id').lstrip('c_'))

        # 마지막으로 확인한 댓글이거나 그 이전 댓글이라면 넘어가기
        if comment_id <= latest_comment_id:
            continue

        latest_comment_id = comment_id

        # 댓글 요소 가져오기 (없을 시 오류를 반환하고 넘어감)
        try:
            user_anchor = node.cssselect('.user-info a').pop()
            user = user_anchor.get('data-filter')
            text_node = node.cssselect('.text').pop()
        except IndexError:
            continue

        # 댓글 내용으로부터 토큰 포맷 가져오기
        match = re.match('([0-9a-fA-F]{32})', text_node.text_content())
        if not match:
            continue

        token = match[1]
        token_challenge = get_challenge_by_token(token)

        if token_challenge is not None:
            # 이미 사용된 토큰이면 무시하기
            if token_challenge['used_by'] is not None:
                log.info(f"{comment_id}: {token}({user}) -> 이미 사용된 토큰")
                continue

        user_challenge = get_challenge_by_user(user)
        check_fresh_account = True

        if user_challenge is not None:
            # 차단된 계정이면 무시하기
            if user_challenge['reject_reason'] is not None:
                log.info(
                    f"{comment_id}: {token}({user}) -> 차단 ({user_challenge['reject_reason']})")
                continue

            # 영구적으로 검증된 계정이라면 깡통계 확인하지 않기
            elif user_challenge['expire_at'] is None:
                check_fresh_account = False
                log.info(
                    f"{comment_id}: {token}({user}) -> 영구적으로 검증된 계정")

        cursor = db.cursor()

        # 깡통계라면 차단하기
        if check_fresh_account and is_fresh_account(user):
            cursor.execute(f'''
                UPDATE challenges
                    SET
                        used_by = ?,
                        used_at = UNIX_TIMESTAMP(),
                        reject_reason = "사용할 수 없는 계정",
                        expire_at = UNIX_TIMESTAMP() + 60 * 30
                    WHERE
                        token = UNHEX(?) AND
                        used_at IS NULL
            ''', (
                user,
                token
            ))

            # 봇 차단 방지를 위해 일정 시간 대기하기
            time.sleep(1)

        else:
            cursor.execute(f'''
                UPDATE challenges
                    SET
                        used_by = ?,
                        used_at = UNIX_TIMESTAMP(),
                        expire_at = UNIX_TIMESTAMP() + ?
                    WHERE
                        token = UNHEX(?) AND
                        used_at IS NULL
            ''', (
                user,
                config['token']['expire'],
                token
            ))

        if cursor.rowcount > 0:
            log.info(f'{comment_id}: {token}({user}) -> OK')

        # clean up
        cursor.close()

    db.commit()

    time.sleep(3)
