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

	# A second language, so the module has something to translate into. Only
	# the oc_language row: no language pack and no description rows, which the
	# module reads as empty. The store image has no mysql client, hence PHP.
	# The table is recreated on every reinstall, so this runs each time.
	echo "[opencart] adding language uk-ua..."
	php <<'PHP'
<?php
mysqli_report(MYSQLI_REPORT_OFF);
function fail($message) {
	// STDERR is not defined when PHP reads its script from stdin.
	file_put_contents('php://stderr', "[opencart] adding uk-ua failed: " . $message . "\n"
		. "[opencart] the store is half installed; recreate the container to retry\n");
	exit(1);
}
$db = @mysqli_connect(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if (!$db) fail(mysqli_connect_error());
if (!mysqli_set_charset($db, 'utf8mb4')) fail(mysqli_error($db));

$found = mysqli_query($db, "SELECT 1 FROM `oc_language` WHERE `code` = 'uk-ua'");
if (!$found) fail(mysqli_error($db));
if (mysqli_num_rows($found) > 0) exit(0);

// OC 2.3 and 3.0 have image and directory columns; OC 4.x has extension instead.
$columns = mysqli_query($db, "SHOW COLUMNS FROM `oc_language` LIKE 'directory'");
if (!$columns) fail(mysqli_error($db));
if (mysqli_num_rows($columns) > 0) {
	$sql = "INSERT INTO `oc_language` (`name`, `code`, `locale`, `image`, `directory`, `sort_order`, `status`)"
		. " VALUES ('Українська', 'uk-ua', 'uk-ua,uk_UA.UTF-8,uk_UA,ukrainian', 'ua.png', 'uk-ua', 2, 1)";
} else {
	$sql = "INSERT INTO `oc_language` (`name`, `code`, `locale`, `extension`, `sort_order`, `status`)"
		. " VALUES ('Українська', 'uk-ua', 'uk-ua,uk', '', 2, 1)";
}
if (!mysqli_query($db, $sql)) fail(mysqli_error($db));
PHP

	# The admin shows a warning while install/ exists.
	rm -rf install
	chown -R www-data:www-data /var/www/html
	echo "[opencart] ready: ${OC_HTTP_SERVER}admin/ (${OC_ADMIN_USER} / ${OC_ADMIN_PASSWORD})"
fi

exec "$@"
