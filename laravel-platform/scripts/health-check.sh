#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://localhost:8080}"

echo "==> Checking OIDC Discovery endpoint"
curl -fsS "${BASE_URL}/.well-known/openid-configuration" | grep -q '"issuer"'

echo "==> Checking JWKS endpoint"
curl -fsS "${BASE_URL}/.well-known/jwks.json" | grep -q '"keys"'

echo "==> Health check passed"
