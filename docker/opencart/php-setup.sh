#!/bin/sh
# Installs the PHP extensions each OpenCart release's installer checks for.
# Runs once at image build time. PHP_VERSION is set by the official php images.
set -eu

# The old PHP images sit on end-of-life Debian releases (PHP 5.6 = stretch,
# PHP 7.4 = bullseye). Once Debian moves a release to archive.debian.org, apt
# fails: either `apt-get update` errors, or (mid-move) the index loads but the
# packages 404. Either way, switch to the archive and try again.
use_archive() {
	echo "[php-setup] switching apt to archive.debian.org"
	for f in /etc/apt/sources.list /etc/apt/sources.list.d/*; do
		[ -f "$f" ] || continue
		sed -i -e 's|deb.debian.org|archive.debian.org|g' \
			-e 's|security.debian.org|archive.debian.org|g' "$f"
	done
	# The archive has no stretch-updates; drop *-updates if the update fails.
	if ! apt-get -o Acquire::Check-Valid-Until=false update; then
		for f in /etc/apt/sources.list /etc/apt/sources.list.d/*; do
			[ -f "$f" ] && sed -i -e '/-updates/d' "$f"
		done
		apt-get -o Acquire::Check-Valid-Until=false update
	fi
}

apt_install() {
	if apt-get update && apt-get install -y --no-install-recommends "$@"; then
		return 0
	fi
	use_archive
	apt-get install -y --no-install-recommends --allow-unauthenticated "$@"
}

case "$PHP_VERSION" in
	5.6.*)
		# OC 2.3 installer requires mcrypt (bundled with PHP up to 7.1).
		apt_install libpng-dev libjpeg-dev libfreetype6-dev libmcrypt-dev
		docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
		docker-php-ext-install -j"$(nproc)" gd mysqli mcrypt
		;;
	*)
		# OC 3.0 needs openssl (built in); OC 4.0/4.1 installers need zip.
		apt_install libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev
		docker-php-ext-configure gd --with-freetype --with-jpeg
		docker-php-ext-install -j"$(nproc)" gd mysqli zip
		;;
esac

# Show PHP errors in the page, so a broken module is visible straight away.
{
	echo 'display_errors = On'
	echo 'error_reporting = E_ALL'
	echo 'upload_max_filesize = 32M'
	echo 'post_max_size = 32M'
} > /usr/local/etc/php/conf.d/opencart-test.ini

rm -rf /var/lib/apt/lists/*
