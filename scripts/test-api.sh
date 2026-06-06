#!/usr/bin/env bash
# access-switch API tests — DDEV, local PHP server, or CI (DDEV_PRIMARY_URL + ACCESS_SWITCH_TOKEN)
set -euo pipefail

BASE="${DDEV_PRIMARY_URL:-https://access-switch.ddev.site}"
TOKEN="${ACCESS_SWITCH_TOKEN:-dev-token-change-me}"

fail() { echo "FAIL: $*" >&2; exit 1; }
ok() { echo "OK: $*"; }

code() {
  curl -sk -o /dev/null -w '%{http_code}' "$@"
}

echo "Base: $BASE"

c=$(code "$BASE/health")
[[ "$c" == "200" ]] || fail "/health → $c (expected 200)"
ok "/health → 200"

c=$(code "$BASE/check")
[[ "$c" == "503" ]] || fail "/check (closed) → $c (expected 503)"
ok "/check closed by default → 503"

c=$(code "$BASE/check/default")
[[ "$c" == "503" ]] || fail "/check/default (closed) → $c (expected 503)"
ok "/check/default closed by default → 503"

c=$(code -X POST "$BASE/admin" -H "Authorization: Bearer wrong" -H "Content-Type: application/json" -d '{"open":true}')
[[ "$c" == "401" ]] || fail "admin with bad token → $c (expected 401)"
ok "admin invalid token → 401"

body=$(curl -sk -X POST "$BASE/admin" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open":true}')
echo "$body" | grep -q '"open":true' || fail "admin open=true: $body"
echo "$body" | grep -q '"service":"default"' || fail "admin open=true missing service: $body"
ok "admin open=true (default service)"

c=$(code "$BASE/check")
[[ "$c" == "200" ]] || fail "/check (open) → $c (expected 200)"
ok "/check open → 200"

c=$(code "$BASE/check/default")
[[ "$c" == "200" ]] || fail "/check/default (open) → $c (expected 200)"
ok "/check/default open → 200"

c=$(code -X POST "$BASE/admin" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open":false}' -o /dev/null -w '%{http_code}')
[[ "$c" == "200" ]] || fail "admin open=false → $c"
ok "admin open=false"

c=$(code "$BASE/check")
[[ "$c" == "503" ]] || fail "/check (closed again) → $c (expected 503)"
ok "/check closed again → 503"

c=$(code "$BASE/check/toto")
[[ "$c" == "503" ]] || fail "/check/toto unauthorized → $c (expected 503)"
ok "/check/toto unauthorized → 503"

echo ""
echo "All tests passed."
