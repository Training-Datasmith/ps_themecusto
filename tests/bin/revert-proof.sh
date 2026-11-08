#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TMPDIR="$(mktemp -d)"
chmod 0700 "$TMPDIR"
cleanup() { rm -rf "$TMPDIR"; }
trap cleanup EXIT

git -C "$REPO_ROOT" archive HEAD >"$TMPDIR/export.tar"

extract_work() {
    local name="$1"
    local work="$TMPDIR/work-$name"
    rm -rf "$work"
    mkdir -p "$work"
    tar -xf "$TMPDIR/export.tar" -C "$work"
    printf '%s' "$work"
}

prove_mutation() {
    local name="$1"
    local patch="$2"
    local test="$3"
    local work
    work="$(extract_work "$name")"
    (cd "$work" && eval "$patch")
    local log="$TMPDIR/proof-$name.log"
    set +e
    (cd "$work" && FILTER="$test" USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}" tests/bin/run-tests.sh --filter "$test" >"$log" 2>&1)
    local rc=$?
    set -e

    if grep -qiE 'Parse error:|Fatal error:.*bootstrap|No tests executed' "$log"; then
        echo "PROOF FAIL: $name ended with parse/setup failure (not an assertion failure)" >&2
        tail -30 "$log" >&2
        exit 1
    fi

    local phpunit_log
    phpunit_log="$(awk '/^PHPUnit [0-9]/,0' "$log")"
    if ! echo "$phpunit_log" | grep -q 'FAILURES!'; then
        if [ "$rc" -eq 0 ]; then
            echo "PROOF FAIL: $name still passes after mutation" >&2
            tail -30 "$log" >&2
            exit 1
        fi
        echo "PROOF FAIL: $name failed without PHPUnit assertion failures" >&2
        tail -30 "$log" >&2
        exit 1
    fi

    if grep -qiE '(^|[^A-Za-z])(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):' "$log" \
        && ! grep -q 'PHP diagnostic in child output' "$log"; then
        if grep -qi 'Parse error:' "$log"; then
            echo "PROOF FAIL: $name mutation caused a parse error" >&2
            exit 1
        fi
    fi

    echo "PROOF OK: $name"
}

prove_mutation upload-guards-isarray 'perl -0pi -e '"'"'s/if \(!is_array\(\$aChildThemeReturned\)\) \{.*?\n        \}//s'"'"' controllers/admin/AdminPsThemeCustoAdvanced.php' \
    'testUploadWithoutFileReturnsErrorJson'

prove_mutation upload-guards-nonfile 'perl -0pi -e '"'"'s/if \(!is_string\(\$sZipPath\) \|\| !is_file\(\$sZipPath\)\) \{.*?\n        \}//s'"'"' controllers/admin/AdminPsThemeCustoAdvanced.php' \
    'testUploadDeniedWithoutEditRightReturnsPermissionMessage'

prove_mutation shop-filter 'python3 tests/bin/patch-remove-shop-filter.py' \
    'testDeviceStatusUsesCurrentShop'

prove_mutation php-regex 'perl -pi -e '"'"'s/\(php\)#i/(php)#/'"'"' controllers/admin/AdminPsThemeCustoAdvanced.php' \
    'testPhpExtensionCheckIsCaseInsensitive'

echo "All revert proofs passed"
