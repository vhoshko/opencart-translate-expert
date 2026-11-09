#!/bin/sh
# First start: install OpenCart with its own CLI installer, then hand over to
# Apache. Later starts of the same container skip straight to Apache.
set -eu

cd /var/www/html

if ! grep -q "DB_DATABASE" config.php 2>/dev/null; then
	echo "[opencart] waiting for database ${DB_HOST}/${DB_NAME}..."
	until php -r 'exit(@mysqli_connect(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASSWORD"), getenv("DB_NAME")) ? 0 : 1);'; do
		sleep 2
	done

	# OC 4.0 refuses to install unless both config files already exist.
	[ -f config.php ] || cp config-dist.php config.php
	[ -f admin/config.php ] || cp admin/config-dist.php admin/config.php

	echo "[opencart] installing into ${DB_NAME} (${OC_HTTP_SERVER})..."
	php install/cli_install.php install \
		--db_hostname "$DB_HOST" \
		--db_username "$DB_USER" \
		--db_password "$DB_PASSWORD" \
		--db_database "$DB_NAME" \
		--db_driver mysqli \
		--db_port 3306 \
		--db_prefix oc_ \
		--username "$OC_ADMIN_USER" \
		--password "$OC_ADMIN_PASSWORD" \
		--email "$OC_ADMIN_EMAIL" \
		--http_server "$OC_HTTP_SERVER"

	if ! grep -q "DB_DATABASE" config.php; then
		echo "[opencart] install failed: config.php was not written" >&2
		exit 1
	fi

	# The admin shows a warning while install/ exists.
	rm -rf install
	chown -R www-data:www-data /var/www/html
	echo "[opencart] ready: ${OC_HTTP_SERVER}admin/ (${OC_ADMIN_USER} / ${OC_ADMIN_PASSWORD})"
fi

exec "$@"
