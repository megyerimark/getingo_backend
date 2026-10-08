#!/usr/bin/env bash
set -Eeuo pipefail

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

if command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
elif [[ ! -f vendor/autoload.php ]]; then
  echo "HIBA: Composer nem érhető el és a vendor/autoload.php is hiányzik." >&2
  exit 1
else
  echo "Composer nem érhető el; a meglévő vendor mappát használom."
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan app:doctor
"$PHP_BIN" artisan optimize
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan route:list --path=api/code --except-vendor

echo "Getingo API frissítés kész. Ellenőrzés: /api/health és /api/code/capabilities"
