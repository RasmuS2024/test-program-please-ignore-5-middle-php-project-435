#!/bin/sh

set -eu

# Step 5 will apply migrations and seed the database here before starting
# the server. Step 4 intentionally does not connect to PostgreSQL yet.
exec php -S "0.0.0.0:${PORT:-8080}" -t public public/index.php
