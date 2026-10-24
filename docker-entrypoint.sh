#!/bin/sh

set -eu

php vendor/bin/phinx migrate -e default
php vendor/bin/phinx seed:run -s 'App\Database\Seeds\CatalogSeeder' -e default

exec php -S "0.0.0.0:${PORT:-8080}" -t public public/index.php
