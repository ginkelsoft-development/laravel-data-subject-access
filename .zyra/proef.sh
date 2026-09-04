#!/usr/bin/env bash
#
# Proefscript voor laravel-data-subject-access (Laravel-package, geen eigen
# host-app).
#
# Dit package heeft zelf geen artisan/.env/routes/serve-entrypoint: het
# levert de `retention:export` command en de Exportable-trait voor gebruik
# in een host-app, en steunt op `ginkelsoft/laravel-compliance-core` (hash
# chain, subject hashing). Voor lokaal proefdraaien gebruiken we daarom
# Orchestra Testbench: dat start een minimale Laravel-skeleton-app met deze
# package erin geladen, zodat je zonder aparte host-app kunt rondkijken en
# de testsuite kunt draaien.
#
# Gebruik:
#   PORT=8080 .zyra/proef.sh

set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PORT="${PORT:-8000}"

echo "==> Composer-dependencies installeren"
composer install --no-interaction

echo "==> Testbench-database (sqlite) klaarzetten"
vendor/bin/testbench workbench:create-sqlite-db --no-interaction || true

echo "==> Testsuite draaien (bewijst dat de package zelfstandig werkt)"
vendor/bin/pest

echo "==> Server starten op 0.0.0.0:${PORT}"
echo "    Dit package heeft geen eigen routes; de skeleton-app draait leeg."
echo "    Gebruik dit om te bevestigen dat de package + Testbench-app opstarten."
exec vendor/bin/testbench serve --host=0.0.0.0 --port="${PORT}" --no-interaction
