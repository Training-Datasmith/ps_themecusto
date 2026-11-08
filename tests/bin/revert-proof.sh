#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TMPDIR="$(mktemp -d)"
chmod 0700 "$TMPDIR"
cleanup() { rm -rf "$TMPDIR"; }
trap cleanup EXIT

archive="$TMPDIR/export.tar"
tar -C "$REPO_ROOT" -cf "$archive" .

prove_mutation() {
    local name="$1"
    local patch="$2"
    local test="$3"
    local work="$TMPDIR/work-$name"
    mkdir "$work"
    tar -xf "$archive" -C "$work"
    (cd "$work" && eval "$patch")
    if (cd "$work" && FILTER="$test" tests/bin/run-tests.sh --filter "$test" >/dev/null 2>&1); then
        echo "PROOF FAIL: $name still passes after mutation" >&2
        exit 1
    fi
    echo "PROOF OK: $name"
}

prove_mutation upload-guards 'perl -0pi -e '"'"'s/if \(!is_array\(\$aChildThemeReturned\)\) \{.*?\n        \}//s'"'"' controllers/admin/AdminPsThemeCustoAdvanced.php' \
    'testUploadRejectsNonArrayAttachmentPayload'

prove_mutation shop-filter 'perl -0pi -e "s/ AND ms\.id_shop = .*;//" classes/ThemeCustoRequests.php' \
    'testDeviceStatusUsesCurrentShop'

prove_mutation php-regex 'perl -pi -e '"'"'s/\(php\)#i/(php)#/'"'"' controllers/admin/AdminPsThemeCustoAdvanced.php' \
    'testPhpExtensionCheckIsCaseInsensitive'

echo "All revert proofs passed"
