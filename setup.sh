#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")"
command -v php >/dev/null 2>&1 || { echo 'PHP belum ada di PATH.'; exit 1; }
[ -f .env ] || cp .env.example .env
[ -f vendor/autoload.php ] || { echo 'Jalankan composer install terlebih dahulu.'; exit 1; }
[ -f database/database.sqlite ] || touch database/database.sqlite
if ! grep -Eq '^APP_KEY=.+$' .env; then php artisan key:generate; fi
php artisan config:clear
php artisan migrate --seed
printf '\nSiap. Jalankan: php artisan serve\nBaca README.md untuk akun demo dan batasan.\n'
