#!/usr/bin/env bash
set -o errexit
set -o nounset
set -o pipefail
shopt -s globstar
shopt -s nullglob
[[ "${TRACE-0}" == "1" ]] && set -o xtrace

REPO_DIR="repos/public"
PATCHE_DIR="patches/public"
LAUNCH_ARGS=(
  --theme dark
  
  # features
  --gradio-img2img-tool color-sketch
  --deepdanbooru
  
  # foolproof
  --freeze-settings
  --hide-ui-dir-config
  --disable-console-progressbars

  # optimize memory usage on attention
  --xformers

  # mixed precision?
  # https://tutorials.pytorch.kr/recipes/recipes/amp_recipe.html#automatic-mixed-precision
  --precision autocast
)

pwd=$(pwd)

# venv 적용하기
source .venv/bin/activate

# 종속 프로그램 설치
apt -qq update
which ffmpeg || apt -qq install -y ffmpeg

# 허깅페이스 모델 캐시 디렉터리 심볼릭 링크 만들기
mkdir -p cache/huggingface ~/.cache
ln -sf "$(pwd)/cache/huggingface" ~/.cache/huggingface

# 레포지토리 없다면 클론하기
if [[ ! -d "$REPO_DIR/.git" ]]; then
  echo "Missing main repository, cloning..."

  rm -rf "$REPO_DIR"
  git clone https://github.com/AUTOMATIC1111/stable-diffusion-webui.git "$REPO_DIR"
fi

# 종속 패키지 설치
(cd "$REPO_DIR" && python launch.py --exit)

# 프로세스 실행
(trap 'kill 0' SIGINT;\
  #
  # reverse ssh tunneling
  (ssh -NT \
    -R48400:127.0.0.1:48400 \
    -R48401:127.0.0.1:48401 \
    -R48402:127.0.0.1:48402 \
    -R48500:127.0.0.1:48500 \
    -R48600:127.0.0.1:48600 \
    -R48601:127.0.0.1:48601 \
    -o ExitOnForwardFailure=yes \
    -o StrictHostKeyChecking=accept-new \
    -i ./tools/ssh/key_ed25519 \
    -p 22491 \
    tunnel@vmm.pw \
  ) & \
  #
  # full-1
  (cd "$REPO_DIR" && \
    python -c 'import webui; webui.webui()' \
      --port 48400 \
      --ui-settings-file "$pwd/configs/public/full-1.json" \
      --ui-config-file "$pwd/configs/public/ui/full-1.json" \
      --ckpt "/storage/models/Stable-diffusion/nai/animefull-final-pruned.ckpt" \
      --vae-path "/storage/models/VAE/nai/animevae.pt" \
      ${LAUNCH_ARGS[@]} \
  ) & \
  # #
  # # full-2
  # (cd "$REPO_DIR" && \
  #   python -c 'import webui; webui.webui()' \
  #     --port 48401 \
  #     --ui-settings-file "$pwd/configs/public/full-2.json" \
  #     --ui-config-file "$pwd/configs/public/ui/full-2.json" \
  #     --ckpt "/storage/models/Stable-diffusion/nai/animefull-final-pruned.ckpt" \
  #     --vae-path "/storage/models/VAE/nai/animevae.pt" \
  #     ${LAUNCH_ARGS[@]} \
  # ) & \
  # #
  # # full-3
  # (cd "$REPO_DIR" && \
  #   python -c 'import webui; webui.webui()' \
  #     --port 48402 \
  #     --ui-settings-file "$pwd/configs/public/full-3.json" \
  #     --ui-config-file "$pwd/configs/public/ui/full-3.json" \
  #     --ckpt "/storage/models/Stable-diffusion/nai/animefull-final-pruned.ckpt" \
  #     --vae-path "/storage/models/VAE/nai/animevae.pt" \
  #     ${LAUNCH_ARGS[@]} \
  # ) & \
  #
  # sfw-1
  (cd "$REPO_DIR" && \
    python -c 'import webui; webui.webui()' \
      --port 48500 \
      --ui-settings-file "$pwd/configs/public/sfw-1.json" \
      --ui-config-file "$pwd/configs/public/ui/sfw-1.json" \
      --ckpt "/storage/models/Stable-diffusion/nai/animesfw-final-pruned.ckpt" \
      --vae-path "/storage/models/VAE/nai/animevae.pt" \
      ${LAUNCH_ARGS[@]} \
  ) & \
  #
  # anything-1
  (cd "$REPO_DIR" && \
    python -c 'import webui; webui.webui()' \
      --port 48600 \
      --ui-settings-file "$pwd/configs/public/anything-1.json" \
      --ui-config-file "$pwd/configs/public/ui/anything-1.json" \
      --ckpt "/storage/models/Stable-diffusion/Anything-3-0-ema-pruned.ckpt" \
      --vae-path "/storage/models/VAE/nai/animevae.pt" \
      ${LAUNCH_ARGS[@]} \
  ) & \
  #
  # anything-2
  (cd "$REPO_DIR" && \
    python -c 'import webui; webui.webui()' \
      --port 48601 \
      --ui-settings-file "$pwd/configs/public/anything-2.json" \
      --ui-config-file "$pwd/configs/public/ui/anything-2.json" \
      --ckpt "/storage/models/Stable-diffusion/Anything-3-0-ema-pruned.ckpt" \
      --vae-path "/storage/models/VAE/nai/animevae.pt" \
      ${LAUNCH_ARGS[@]} \
  ) & \
wait)