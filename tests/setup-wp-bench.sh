#!/bin/bash
# Install a throwaway WordPress that stores posts in SQLite.
set -euo pipefail

ROOT="${OLI_CF_WP_PATH:-/tmp/oli-cf-wp}"
PLUGIN_SRC="$(cd "$(dirname "$0")/.." && pwd)"
WP_CLI="${WP_CLI:-/tmp/wp-cli.phar}"
WP_VERSION="${OLI_CF_WP_VERSION:-6.7.2}"

if ! php -m | grep -qi sqlite; then
	echo "PHP sqlite extension is required." >&2
	exit 1
fi

if [[ ! -f "$WP_CLI" ]]; then
	curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	chmod +x "$WP_CLI"
fi

if [[ ! -f "$ROOT/wp-load.php" ]]; then
	rm -rf "$ROOT"
	mkdir -p "$ROOT"
	curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" | tar -xz -C "$(dirname "$ROOT")"
	# The tarball unpacks to a directory named wordpress.
	if [[ "$(dirname "$ROOT")/wordpress" != "$ROOT" ]]; then
		rm -rf "$ROOT"
		mv "$(dirname "$ROOT")/wordpress" "$ROOT"
	fi
fi

mkdir -p "$ROOT/wp-content/plugins" "$ROOT/wp-content/mu-plugins"
ln -sfn "$PLUGIN_SRC" "$ROOT/wp-content/plugins/oli-contact-form"

if [[ ! -f "$ROOT/wp-content/db.php" ]]; then
	ZIP="/tmp/sqlite-database-integration.zip"
	curl -fsSL -o "$ZIP" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
	rm -rf "$ROOT/wp-content/plugins/sqlite-database-integration"
	unzip -q -o "$ZIP" -d "$ROOT/wp-content/plugins"
	cp "$ROOT/wp-content/plugins/sqlite-database-integration/db.copy" "$ROOT/wp-content/db.php"
fi

if [[ ! -f "$ROOT/wp-config.php" ]]; then
	php "$WP_CLI" --path="$ROOT" config create \
		--dbname=oli_cf \
		--dbuser=unused \
		--dbpass=unused \
		--dbhost=localhost \
		--skip-check \
		--force
	php "$WP_CLI" --path="$ROOT" config set WP_DEBUG true --raw
	php "$WP_CLI" --path="$ROOT" core install \
		--url="http://example.test" \
		--title="Oli CF bench" \
		--admin_user=admin \
		--admin_password=password \
		--admin_email=admin@example.test \
		--skip-email
fi

php "$WP_CLI" --path="$ROOT" plugin activate oli-contact-form >/dev/null
echo "WordPress bench ready at $ROOT"
