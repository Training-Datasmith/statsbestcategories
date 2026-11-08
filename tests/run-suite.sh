#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
DOCKER_BIN="${DOCKER_BIN:-docker}"
if ! command -v "$DOCKER_BIN" >/dev/null 2>&1; then
  echo "docker not found in PATH (set DOCKER_BIN)" >&2
  exit 127
fi

docker_cmd() {
  if [ "$USE_SUDO_DOCKER" = "1" ]; then
  sudo "$DOCKER_BIN" "$@"
  else
  "$DOCKER_BIN" "$@"
  fi
}

PHP_IMAGE_DIGEST="sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
MYSQL_IMAGE="mysql:5.7.44"
MYSQL_IMAGE_DIGEST="${MYSQL_IMAGE_DIGEST:-sha256:4bc6bc963e6d8443453676cae56536f4b8156d78bae03c0145cbe47c2aad73bb}"

EXPECTED_TESTS=36
EXPECTED_FILES=(
  "$REPO_ROOT/tests/DirectAccessTest.php"
  "$REPO_ROOT/tests/StatsBestCategoriesTest.php"
  "$REPO_ROOT/tests/UpgradeModule201Test.php"
)

sorted_expected=()
while IFS= read -r f; do
  sorted_expected+=("$f")
done < <(printf '%s\n' "${EXPECTED_FILES[@]}" | sort)

sorted_on_disk=()
while IFS= read -r f; do
  sorted_on_disk+=("$f")
done < <(find "$REPO_ROOT/tests" -maxdepth 1 -type f -name '*Test.php' | sort)

if [ "${#sorted_on_disk[@]}" -ne "${#sorted_expected[@]}" ]; then
  echo "Test file count drift (disk=${#sorted_on_disk[@]} expected=${#sorted_expected[@]})" >&2
  exit 1
fi
for i in "${!sorted_expected[@]}"; do
  if [ "${sorted_on_disk[$i]}" != "${sorted_expected[$i]}" ]; then
    echo "Test file list drift at index $i: ${sorted_on_disk[$i]} != ${sorted_expected[$i]}" >&2
    exit 1
  fi
done

RUNTIME_DIR="$(mktemp -d)"
chmod 0700 "$RUNTIME_DIR"
GNUPGHOME="$(mktemp -d)"
chmod 0700 "$GNUPGHOME"
export GNUPGHOME
cleanup() {
  docker_cmd rm -f statsbc-mysql >/dev/null 2>&1 || true
  rm -rf "$RUNTIME_DIR" "$GNUPGHOME"
}
trap cleanup EXIT

PHPUNIT_PHAR="$RUNTIME_DIR/phpunit-4.8.36.phar"
curl -fsSL -o "$PHPUNIT_PHAR" https://phar.phpunit.de/phpunit-4.8.36.phar
curl -fsSL -o "$RUNTIME_DIR/phpunit-4.8.36.phar.asc" https://phar.phpunit.de/phpunit-4.8.36.phar.asc
curl -fsSL https://sebastian-bergmann.de/gpg.asc | gpg --import >/dev/null 2>&1
gpg --status-fd 1 --verify "$RUNTIME_DIR/phpunit-4.8.36.phar.asc" "$PHPUNIT_PHAR" 2>&1 | tee "$RUNTIME_DIR/gpg-verify.txt"
grep -q 'VALIDSIG.*D8406D0D82947747293778314AA394086372C20A' "$RUNTIME_DIR/gpg-verify.txt"
EXPECTED_SHA="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
ACTUAL_SHA="$(sha256sum "$PHPUNIT_PHAR" | awk '{print $1}')"
if [ "$ACTUAL_SHA" != "$EXPECTED_SHA" ]; then
  echo "PHPUnit PHAR sha256 mismatch" >&2
  exit 1
fi

PHP_IMAGE_TAG="statsbc-php56-mysqli"
if ! docker_cmd image inspect "$PHP_IMAGE_TAG" >/dev/null 2>&1; then
  docker_cmd build -f "$REPO_ROOT/tests/Dockerfile.php56-mysqli" -t "$PHP_IMAGE_TAG" "$REPO_ROOT/tests"
fi

if [ -n "$MYSQL_IMAGE_DIGEST" ]; then
  MYSQL_REF="${MYSQL_IMAGE}@${MYSQL_IMAGE_DIGEST}"
else
  MYSQL_REF="$MYSQL_IMAGE"
fi

MYSQL_HOST_PORT="${MYSQL_HOST_PORT:-3306}"
docker_cmd rm -f statsbc-mysql >/dev/null 2>&1 || true
docker_cmd run -d --name statsbc-mysql \
  -p "127.0.0.1:${MYSQL_HOST_PORT}:3306" \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=stats_best_categories \
  -e MYSQL_USER=statsbc \
  -e MYSQL_PASSWORD=statsbc \
  "$MYSQL_REF" >/dev/null

for _ in $(seq 1 90); do
  if docker_cmd exec statsbc-mysql mysqladmin ping -h127.0.0.1 -uroot -proot --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
docker_cmd exec statsbc-mysql mysqladmin ping -h127.0.0.1 -uroot -proot --silent

CONTAINER_ROOT="/work"

write_phpunit_xml() {
  local xml_path="$1"
  local files_list=("${@:2}")
  cat >"$xml_path" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="${CONTAINER_ROOT}/tests/bootstrap.php"
         colors="false"
         backupGlobals="false"
         backupStaticAttributes="false">
  <testsuites>
    <testsuite name="module-tests">
EOF
  local f host_path container_path
  for f in "${files_list[@]}"; do
    container_path="${f/$REPO_ROOT/$CONTAINER_ROOT}"
    echo "      <file>${container_path}</file>" >>"$xml_path"
  done
  cat >>"$xml_path" <<EOF
    </testsuite>
  </testsuites>
</phpunit>
EOF
}

run_phpunit_once() {
  local xml_basename="$1"
  local log_path="$2"
  docker_cmd run --rm --network host \
    -v "$REPO_ROOT:/work:ro" \
    -v "$RUNTIME_DIR:/runtime:ro" \
    -e STATSBC_DB_HOST=127.0.0.1 \
    -e STATSBC_DB_PORT="${MYSQL_HOST_PORT}" \
    -e STATSBC_DB_USER=statsbc \
    -e STATSBC_DB_PASS=statsbc \
    -e STATSBC_DB_NAME=stats_best_categories \
    -e STATSBC_PHP_BIN=php \
    -w /work \
    "$PHP_IMAGE_TAG" \
    php -d error_reporting=-1 -d display_errors=1 -d html_errors=0 \
    /runtime/phpunit-4.8.36.phar --configuration "/runtime/${xml_basename}" >"$log_path" 2>&1

  if grep -E '(FAILURES!|Skipped:|Incomplete:|Risky)' "$log_path" >/dev/null; then
    echo "PHPUnit reported failures/skips/risky" >&2
    exit 1
  fi
  if grep -E '(Notice:|Warning:|Deprecated:|Strict Standards:|Parse error:|Fatal error:)' "$log_path" >/dev/null; then
    echo "PHPUnit output contains PHP diagnostics" >&2
    exit 1
  fi
  OK_LINE="$(grep -E '^OK \([0-9]+ tests, [0-9]+ assertions\)$' "$log_path" | tail -1)"
  if [ -z "$OK_LINE" ]; then
    echo "Missing clean OK summary" >&2
    exit 1
  fi
  echo "$OK_LINE"
}

ORDERED_XML="$RUNTIME_DIR/phpunit-ordered.xml"
write_phpunit_xml "$ORDERED_XML" "${sorted_expected[@]}"
ORDERED_LOG="$RUNTIME_DIR/ordered.log"
ORDERED_OK="$(run_phpunit_once "phpunit-ordered.xml" "$ORDERED_LOG")"
echo "$ORDERED_OK" | grep -q "OK (${EXPECTED_TESTS} tests,"

SHUFFLED_FILES=()
SHUFFLE_PHP="$RUNTIME_DIR/shuffle.php"
cat >"$SHUFFLE_PHP" <<'EOS'
<?php
$files = explode("\n", trim(file_get_contents('php://stdin')));
$count = count($files);
srand(20261108);
for ($i = $count - 1; $i > 0; $i--) {
    $j = rand(0, $i);
    $tmp = $files[$i];
    $files[$i] = $files[$j];
    $files[$j] = $tmp;
}
echo implode("\n", $files), "\n";
EOS

while IFS= read -r f; do
  SHUFFLED_FILES+=("$f")
done < <(printf '%s\n' "${sorted_expected[@]}" | docker_cmd run --rm -i \
  -v "$RUNTIME_DIR:/runtime:ro" \
  "$PHP_IMAGE_TAG" php /runtime/shuffle.php)

SHUFFLE_XML="$RUNTIME_DIR/phpunit-shuffle.xml"
write_phpunit_xml "$SHUFFLE_XML" "${SHUFFLED_FILES[@]}"
SHUFFLE_LOG="$RUNTIME_DIR/shuffle.log"
SHUFFLE_OK="$(run_phpunit_once "phpunit-shuffle.xml" "$SHUFFLE_LOG")"
if [ "$SHUFFLE_OK" != "$ORDERED_OK" ]; then
  echo "Shuffled run summary drift: $SHUFFLE_OK vs $ORDERED_OK" >&2
  exit 1
fi

echo "$ORDERED_OK"
