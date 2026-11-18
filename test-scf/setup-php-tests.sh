#!/usr/bin/env bash
# One-time (per container) setup for the PHP suite.
#
# Installs composer dev tooling (PHPUnit, WPCS, WordPress core, the WP test
# library) inside the wp-env container and stages ACF Free 6.8.10 so the suite
# runs against a real host plugin. Idempotent: skips what is already there.
#
# Needs network access to packagist and to wp.org (for ACF Free).
set -euo pipefail

CLI=wp-env-test-scf-e54e6b03-cli-1
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEV=/tmp/sdc-dev
ACF_VERSION=6.8.10

docker exec "$CLI" sh -lc "
	set -e
	mkdir -p $DEV
	# The test database is dropped and rebuilt on every run; it must never be
	# the site database.
	mariadb -hmysql -uroot -p\"\${WORDPRESS_DB_PASSWORD}\" \
		-e 'CREATE DATABASE IF NOT EXISTS wp_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
"

# The toolchain lives at $DEV (composer.json, composer.lock, vendor/, wordpress/),
# not in $DEV/plugin: run-php-tests.sh mirrors the repository into $DEV/plugin and
# copies the toolchain in from $DEV on every run. Installing in $DEV/plugin is what
# made a fresh container fail with "cp: can't stat 'composer.json'".
docker cp "$ROOT/plugin/composer.json" "$CLI:$DEV/composer.json"

docker exec -w "$DEV" "$CLI" sh -lc '
	set -e
	if [ ! -x vendor/bin/phpunit ]; then
		COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist
	fi
	' 2>&1 | tail -5

# ACF Free: fetch once, keep an unpacked copy for every later run.
docker exec -w /var/www/html/wp-content/plugins "$CLI" sh -lc "
	set -e
	if [ ! -f /tmp/acf-free/advanced-custom-fields/acf.php ]; then
		mkdir -p /tmp/acf-free
		wp plugin install advanced-custom-fields --version=$ACF_VERSION --allow-root >/dev/null
		cp -r /var/www/html/wp-content/plugins/advanced-custom-fields /tmp/acf-free/
	fi
"

docker exec "$CLI" sh -lc 'echo "acf: $(grep -m1 "Version:" /tmp/acf-free/advanced-custom-fields/acf.php | tr -s " ")"; echo "phpunit: $(/tmp/sdc-dev/vendor/bin/phpunit --version)"'
