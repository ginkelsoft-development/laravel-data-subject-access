#!/usr/bin/env bash
set -euo pipefail

# Dit is een Composer package (een Laravel-trait/service-bibliotheek),
# geen op zichzelf staande app: er is geen artisan, geen bootstrap/app.php
# en dus niets om op $PORT te serveren. Dit script installeert de
# dependencies (inclusief de vereiste ginkelsoft/laravel-compliance-core
# sibling package) en draait de testsuite van het package, zodat een mens
# kan proefdraaien of het package in zijn geheel werkt.

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SIBLING_DIR="../laravel-compliance-core"

if [ ! -d "$SIBLING_DIR" ]; then
  echo "==> Klonen van ginkelsoft/laravel-compliance-core (development) naast dit package..."
  git clone --branch development https://github.com/ginkelsoft-development/laravel-compliance-core.git "$SIBLING_DIR"
fi

echo "==> composer install"
composer install --no-interaction

echo "==> Testsuite (pest)"
vendor/bin/pest --colors=always

echo "==> Statische analyse (phpstan)"
vendor/bin/phpstan analyse --no-progress --memory-limit=1G

echo "==> Codestijl (pint --test)"
vendor/bin/pint --test

echo
echo "Klaar. Dit package heeft geen poort om op te serveren (geen app, alleen een library)."
