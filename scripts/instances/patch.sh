#!/usr/bin/env bash
set -o errexit
set -o nounset
set -o pipefail
shopt -s globstar
shopt -s nullglob
[[ "${TRACE-0}" == "1" ]] && set -o xtrace

pwd=$(pwd)
target=${1-public}

# venv 적용하기
source .venv/bin/activate

# 레포지토리 없다면 클론하기
if [[ ! -d "$REPO_DIR/.git" ]]; then
  echo "Missing main repository, cloning..."

  rm -rf "$REPO_DIR"
  git clone https://github.com/AUTOMATIC1111/stable-diffusion-webui.git "$REPO_DIR"
fi

# 레포지토리 업데이트
echo "Updating main repository..."
(cd "repos/$target" && git reset --hard && git pull)

# 확장 기능 업데이트
echo "Updating extension repositories..."
find "repos/$target/extensions" -name .git -type d -prune | while read path; do
  path=$(realpath "$path/../")
  echo "$path"
  (cd "$path" && git reset --hard && git pull)
done

# 파일 패치
if [[ -d "patches/$target" ]]; then
  echo "Patching..."
  pushd patches/$target
  for path in **/*; do
    source_path="$pwd/repos/$target/$path"

    [[ -d "$path" ]] && continue # 디렉터리라면 무시하기
    [[ "$path" == *.patch ]] && continue # 패치 파일이라면 무시하기

    # 패치 파일이 비어 있다면 원본 파일 제거하기
    if [[ ! -s "$path" ]]; then
      echo "remove: $path"
      rm "$source_path"
      continue
    fi

    # 파일에 차이가 존재하면 종료
    if ! diff "$source_path" "$path"; then
      echo "copy: $path"
      cp $path $source_path
    fi
  done
  popd
fi

# 자바스크립트 통합
echo "Merging multiple javascript files into one..."
merged_js_file=$(mktemp)

# order matter?
cat repos/$target/script.js >> $merged_js_file
for p in repos/$target/javascript/**/*.js; do cat $p >> $merged_js_file; rm $p; done
for p in repos/$target/extensions/**/*.js; do cat $p >> $merged_js_file; rm $p; done

echo "Minifying merged javascript..."
python -m rjsmin < $merged_js_file > repos/$target/script.js
rm $merged_js_file

rm -rf "repos/$target/models"
ln -sf /storage/models "repos/$target/models"
