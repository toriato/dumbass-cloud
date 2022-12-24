import re

from typing import Callable
from modules.processing import StableDiffusionProcessing

# 프롬프트에 사용할 수 있는 글자
PROMPT_VALID_CHARACTERS = re.compile(r'^(|[a-zA-Z\s\d()\[\]_,:]+)$')

FILTERS = {
    # 프롬프트
    'prompt': lambda p: PROMPT_VALID_CHARACTERS.match(p),

    # 네거티브 프롬프트
    'negative_prompt': lambda p: PROMPT_VALID_CHARACTERS.match(p),

    # 숫자 자료형은 (최소, 최대) 값으로 필터 가능함
    'width': (1, 1024),  # 폭
    'height': (1, 1024),  # 높이
    'n_iter': (1, 1),  # 배치 수
    'batch_size': (1, 1),  # 배치 크기
}

if not hasattr(StableDiffusionProcessing, '__wrapped_init__'):
    setattr(
        StableDiffusionProcessing,
        '__wrapped_init__',
        StableDiffusionProcessing.__init__
    )


def init(cls: StableDiffusionProcessing, **kwargs):
    for key, filter in FILTERS.items():
        if key not in kwargs:
            continue

        value = kwargs[key]

        if isinstance(filter, Callable):
            if not filter(value):
                break

        elif isinstance(value, (int, float, complex)):
            min, max = filter
            if min > value or max < value:
                break

    else:
        return cls.__wrapped_init__(**kwargs)

    raise ValueError(f"'{key}' failed to pass")


setattr(StableDiffusionProcessing, '__init__', init)
