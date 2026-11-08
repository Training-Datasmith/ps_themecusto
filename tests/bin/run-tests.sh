#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DOCKER_BIN="${DOCKER_BIN:-docker}"
FILTER="${FILTER:-}"
EXPECTED_TESTS=61
PHPUNIT_VERSION=4.8.36
PHPUNIT_SHA256=ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e
FINDER_SHA256=5027119ef9af48354574b21470148273a14091bbdb21b6ae32cd3260367b929c
FINDER_VERSION=3.4.47
MARIADB_IMAGE=mariadb@sha256:f1c56f035ed268a0d7c2c2e06dc879f6319b997cb434cf822a4cfd72a7aa184d

while [ "$#" -gt 0 ]; do
    case "$1" in
        --filter)
            FILTER="$2"
            shift 2
            ;;
        *)
            echo "Unknown argument: $1" >&2
            exit 2
            ;;
    esac
done

TMPDIR="$(mktemp -d)"
chmod 0700 "$TMPDIR"
cleanup() {
    docker_cmd rm -f ps-themecusto-mariadb ps-themecusto-phpunit >/dev/null 2>&1 || true
    chmod -R u+w "$TMPDIR" 2>/dev/null || true
    rm -rf "$TMPDIR"
}
trap cleanup EXIT

docker_cmd() {
    if [ "${USE_SUDO_DOCKER:-0}" = "1" ]; then
        sudo "$DOCKER_BIN" "$@"
    else
        "$DOCKER_BIN" "$@"
    fi
}

verify_phpunit() {
    local phar="$TMPDIR/phpunit-${PHPUNIT_VERSION}.phar"
    local asc="$TMPDIR/phpunit-${PHPUNIT_VERSION}.phar.asc"
    curl -fsSL -o "$phar" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"
    curl -fsSL -o "$asc" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar.asc"
    local actual
    actual="$(sha256sum "$phar" | awk '{print $1}')"
    if [ "$actual" != "$PHPUNIT_SHA256" ]; then
        echo "PHPUnit SHA-256 mismatch: $actual" >&2
        exit 1
    fi
    export GNUPGHOME="$(mktemp -d)"
    chmod 0700 "$GNUPGHOME"
    gpg --homedir "$GNUPGHOME" --keyserver hkps://keyserver.ubuntu.com --recv-keys D8406D0D82947747293778314AA394086372C20A
    if ! gpg --homedir "$GNUPGHOME" --batch --status-fd 1 --verify "$asc" "$phar" 2>&1 | grep -q '\[GNUPG:\] VALIDSIG D8406D0D82947747293778314AA394086372C20A'; then
        echo "PHPUnit GPG verification failed" >&2
        exit 1
    fi
}

verify_finder() {
    local tgz="$TMPDIR/finder-${FINDER_VERSION}.tar.gz"
    curl -fsSL -o "$tgz" "https://github.com/symfony/finder/archive/refs/tags/v${FINDER_VERSION}.tar.gz"
    local actual
    actual="$(sha256sum "$tgz" | awk '{print $1}')"
    if [ "$actual" != "$FINDER_SHA256" ]; then
        echo "Finder SHA-256 mismatch" >&2
        exit 1
    fi
    tar -xzf "$tgz" -C "$TMPDIR"
}

write_phpunit_xml() {
    local outfile="$1"
    local shuffle="${2:-0}"
    python3 - "$outfile" "$shuffle" "$REPO_ROOT" <<'PY'
import random
import sys

outfile, shuffle, repo = sys.argv[1], sys.argv[2], sys.argv[3]
files = [
    f"{repo}/tests/Unit/AdminAdvancedChildThemeTest.php",
    f"{repo}/tests/Unit/AdminAdvancedUploadTest.php",
    f"{repo}/tests/Unit/AdminConfigurationActionsTest.php",
    f"{repo}/tests/Unit/AdminConfigurationListsTest.php",
    f"{repo}/tests/Unit/IndexGuardTest.php",
    f"{repo}/tests/Unit/ModuleInstallTest.php",
    f"{repo}/tests/Unit/ThemeCustoRequestsTest.php",
]
files = sorted(files)
if shuffle == "1":
    random.seed(20261108)
    random.shuffle(files)
container_files = [f.replace(repo, "/module") for f in files]
lines = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<phpunit bootstrap="/module/tests/phpunit-bootstrap.php" colors="true">',
    '  <testsuites>',
    '    <testsuite name="module-tests">',
]
lines += [f'      <file>{path}</file>' for path in container_files]
lines += [
    '    </testsuite>',
    '  </testsuites>',
    '</phpunit>',
]
with open(outfile, "w", encoding="utf-8") as handle:
    handle.write("\n".join(lines) + "\n")
PY
}

list_test_methods() {
    grep -hE '^\s*public function test' "$REPO_ROOT"/tests/Unit/*Test.php | wc -l
}

verify_phpunit
verify_finder

TEST_METHODS="$(list_test_methods)"
if [ "$TEST_METHODS" != "$EXPECTED_TESTS" ]; then
    echo "Test count drift: expected $EXPECTED_TESTS got $TEST_METHODS" >&2
    exit 1
fi

DB_PASS="$(openssl rand -hex 16)"
DB_PORT="$(python3 - <<'PY'
import socket
s = socket.socket()
s.bind(("127.0.0.1", 0))
print(s.getsockname()[1])
s.close()
PY
)"

docker_cmd build -f "$REPO_ROOT/tests/docker/Dockerfile.php56" -t ps-themecusto-php56 "$REPO_ROOT/tests/docker"
docker_cmd rm -f ps-themecusto-mariadb >/dev/null 2>&1 || true
docker_cmd run -d --name ps-themecusto-mariadb \
    -e MARIADB_ROOT_PASSWORD="$DB_PASS" \
    -e MARIADB_DATABASE=ps_test \
    -e MARIADB_USER=ps_test \
    -e MARIADB_PASSWORD="$DB_PASS" \
    -p "127.0.0.1:${DB_PORT}:3306" \
    "$MARIADB_IMAGE" >/dev/null

for _ in $(seq 1 60); do
    if docker_cmd exec ps-themecusto-mariadb mysqladmin ping -h127.0.0.1 -ups_test -p"$DB_PASS" --silent; then
        break
    fi
    sleep 1
done

RUN_FLAGS=(
    --rm
    --network host
    --user "$(id -u):$(id -g)"
    -v "$REPO_ROOT:/module:ro"
    -v "$TMPDIR:/runtime:rw"
    -e PS_TEST_RUNTIME=/runtime
    -e PS_TEST_DB_HOST=127.0.0.1
    -e PS_TEST_DB_PORT="$DB_PORT"
    -e PS_TEST_DB_USER=ps_test
    -e PS_TEST_DB_PASS="$DB_PASS"
    -e PS_TEST_DB_NAME=ps_test
    -e SYMFONY_FINDER_PATH="/runtime/finder-${FINDER_VERSION}"
)

run_phpunit() {
    local xml="$1"
    local extra=()
    if [ -n "$FILTER" ]; then
        extra=(--filter "$FILTER")
    fi
    docker_cmd run --name ps-themecusto-phpunit "${RUN_FLAGS[@]}" ps-themecusto-php56 \
        php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
        /runtime/phpunit-${PHPUNIT_VERSION}.phar \
        --configuration "$xml" "${extra[@]}"
}

write_phpunit_xml "$TMPDIR/phpunit.xml" 0
run_phpunit /runtime/phpunit.xml
first_code=$?

if [ -z "$FILTER" ]; then
    write_phpunit_xml "$TMPDIR/phpunit-shuffled.xml" 1
    run_phpunit /runtime/phpunit-shuffled.xml
    second_code=$?
    if [ "$first_code" -ne 0 ] || [ "$second_code" -ne 0 ]; then
        exit 1
    fi
    echo "OK: $EXPECTED_TESTS tests in sorted and shuffled order"
else
    if [ "$first_code" -ne 0 ]; then
        exit 1
    fi
    echo "OK: filtered run for $FILTER"
fi
