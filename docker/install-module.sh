#!/usr/bin/env bash
# Install (or update) the built Translate Expert package into the running
# Docker test stores, the way a store owner would, then run smoke checks.
#
#   docker/install-module.sh [-p <package.full.zip>] [-c] <store|all>
#
#   stores: oc15 oc20 oc21 oc22 oc23 oc30 oc40 oc41
#
#   -p  package to install (default: newest in releases/)
#   -c  checks only: skip the install and only run the smoke checks
#
# Without -p, the newest releases/v*/OpenCartTranslateExpertClient-v*.full.zip
# is used (run ./build.release.sh first).
#
# The steps are the ones README.md (Installation) gives a store owner.
# First run per store: full install.
#   oc20-oc30   copy upload/ into the store, Modifications > Refresh,
#               Extensions > Modules > Install
#   oc15        copy upload/ into the store (vQmod applies
#               vqmod/xml/OpenCartTranslateExpertClient.xml by itself),
#               Extensions > Modules > Install
#   oc40, oc41  Extension Installer upload + install, Extensions > Modules > Install
# Later runs: the module is already installed, so only the files are replaced
# (plus a modification refresh on oc20-oc30). Recreate the store for a clean
# install: docker compose -f docker/compose.yaml down -v
#
# Works with macOS bash 3.2: no associative arrays, no mapfile.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE=(docker compose -f "$SCRIPT_DIR/compose.yaml")

ADMIN_USER="admin"
ADMIN_PASSWORD="admin"
MODULE_CODE="client_translate_expert"

usage() {
	echo "Usage: $0 [-p <package.full.zip>] [-c] <oc15|oc20|oc21|oc22|oc23|oc30|oc40|oc41|all>" >&2
	exit 2
}

log()  { echo "[$STORE] $*"; }
fail() { echo "[$STORE] ERROR: $*" >&2; FAILED=1; }

PACKAGE=""
CHECK_ONLY=0
while getopts "p:ch" opt; do
	case "$opt" in
		p) PACKAGE="$OPTARG" ;;
		c) CHECK_ONLY=1 ;;
		*) usage ;;
	esac
done
shift $((OPTIND - 1))
[ $# -eq 1 ] || usage

case "$1" in
	all) STORES="oc15 oc20 oc21 oc22 oc23 oc30 oc40 oc41" ;;
	oc15|oc20|oc21|oc22|oc23|oc30|oc40|oc41) STORES="$1" ;;
	*) usage ;;
esac

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

if [ "$CHECK_ONLY" = "0" ]; then
	if [ -z "$PACKAGE" ]; then
		# Versions are zero-padded (MM.mm.pppp), so a plain sort is a version sort.
		PACKAGE="$(ls "$REPO_ROOT"/releases/v*/OpenCartTranslateExpertClient-v*.full.zip 2>/dev/null | sort | tail -n 1 || true)"
		[ -n "$PACKAGE" ] || { echo "No package in releases/. Run ./build.release.sh first." >&2; exit 1; }
	fi
	[ -f "$PACKAGE" ] || { echo "Package not found: $PACKAGE" >&2; exit 1; }
	echo "Package: $PACKAGE"
	unzip -q "$PACKAGE" -d "$WORK/full"
fi

# --- per-store settings -----------------------------------------------------

store_port() {
	case "$1" in
		oc15) echo "${OC15_PORT:-8015}" ;;
		oc20) echo "${OC20_PORT:-8020}" ;;
		oc21) echo "${OC21_PORT:-8021}" ;;
		oc22) echo "${OC22_PORT:-8022}" ;;
		oc23) echo "${OC23_PORT:-8023}" ;;
		oc30) echo "${OC30_PORT:-8030}" ;;
		oc40) echo "${OC40_PORT:-8040}" ;;
		oc41) echo "${OC41_PORT:-8041}" ;;
	esac
}

store_package() {
	local f
	case "$1" in
		oc15) f="$(ls "$WORK"/full/*-oc1.5-*.ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
		oc20) f="$(ls "$WORK"/full/*-oc2.0-*.ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
		oc21) f="$(ls "$WORK"/full/*-oc2.1-*.ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
		oc22) f="$(ls "$WORK"/full/*-oc2.2-*.ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
		oc23) f="$(ls "$WORK"/full/*-oc2.3-*.ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
		oc30) f="$(ls "$WORK"/full/*-oc3.0-*.ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
		# One package serves OC 4.0 and 4.1. Older builds named its folder oc4.0.
		oc40|oc41) f="$(ls "$WORK"/full/*-oc4.[0x]-*/"$MODULE_CODE".ocmod.zip 2>/dev/null | head -n 1 || true)" ;;
	esac
	echo "$f"
}

# OC 4.0 and 4.1 share the extension installer, routes and admin login flow.
is_oc4() {
	case "$1" in oc40|oc41) return 0 ;; *) return 1 ;; esac
}

# OC 1.5 has no OCMOD: vQmod applies the module's XML.
is_oc15() {
	[ "$1" = "oc15" ]
}

# OC 1.5-2.2 keep modules at module/<code>; OC 2.3+ moved them to extension/.
is_pre23() {
	case "$1" in oc15|oc20|oc21|oc22) return 0 ;; *) return 1 ;; esac
}

# OC 1.5-2.3 pass the admin session as ?token=, OC 3.0+ as ?user_token=.
token_param() {
	if is_pre23 "$1" || [ "$1" = "oc23" ]; then echo "token"; else echo "user_token"; fi
}

# OC 1.5 has no common/dashboard: its admin home page is common/home.
dashboard_route() {
	if is_oc15 "$1"; then echo "common/home"; else echo "common/dashboard"; fi
}

# Extensions > Modules > Install, for OC 1.5-3.0.
module_install_route() {
	if is_pre23 "$1"; then echo "extension/module/install"; else echo "extension/extension/module/install"; fi
}

# --- helpers ----------------------------------------------------------------

sql() {
	"${COMPOSE[@]}" exec -T db mariadb -uopencart -popencart -N -B "$STORE" -e "$1"
}

# Note: the install and check functions are called as `f || continue`, and
# bash ignores `set -e` inside a function called that way. So every command
# that can fail in them carries its own `|| { fail ...; return 1; }`.

http() {
	curl -sS --connect-timeout 10 --max-time 120 -b "$JAR" -c "$JAR" "$@"
}

admin_url() {
	echo "$BASE/admin/index.php?route=$1&$(token_param "$STORE")=$TOKEN${2:-}"
}

# Pull a string value out of a flat JSON response, undoing \/ and &amp;.
json_value() {
	sed -n "s/.*\"$1\":\"\([^\"]*\)\".*/\1/p" | sed -e 's#\\/#/#g' -e 's/&amp;/\&/g'
}

login() {
	local location html login_token resp
	if is_oc4 "$STORE"; then
		html="$(http "$BASE/admin/index.php?route=common/login")" || { fail "login page request failed"; return 1; }
		login_token="$(echo "$html" | sed -n 's/.*login_token=\([0-9a-zA-Z]*\).*/\1/p' | head -n 1)"
		[ -n "$login_token" ] || { fail "no login_token on the login page"; return 1; }
		resp="$(http --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASSWORD" \
			"$BASE/admin/index.php?route=common/login.login&login_token=$login_token")" || { fail "login request failed"; return 1; }
		TOKEN="$(echo "$resp" | json_value redirect | sed -n 's/.*user_token=\([0-9a-zA-Z]*\).*/\1/p')"
	else
		location="$(http -o /dev/null -w '%{redirect_url}' \
			--data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASSWORD" \
			"$BASE/admin/index.php?route=common/login")" || { fail "login request failed"; return 1; }
		TOKEN="$(echo "$location" | sed -n "s/.*[?&]$(token_param "$STORE")=\([0-9a-zA-Z]*\).*/\1/p")"
	fi
	[ -n "$TOKEN" ] || { fail "admin login failed"; return 1; }
}

refresh_modifications() {
	local route
	if [ "$STORE" = "oc30" ]; then route="marketplace/modification/refresh"; else route="extension/modification/refresh"; fi
	http -o /dev/null "$(admin_url "$route")" || { fail "modification refresh request failed"; return 1; }
	log "modifications refreshed"
}

# Prints the number of oc_extension rows for the module. Fails (and prints
# nothing) when the query fails, so a broken query is never read as "0".
module_rows() {
	sql "SELECT COUNT(*) FROM oc_extension WHERE type='module' AND code='$MODULE_CODE'"
}

# Fails the store if the module's install() printed PHP errors. The extension
# row is written before install() runs, so the row alone does not prove that
# install() was clean.
check_install_output() {
	local errs
	errs="$(echo "$1" | php_errors || true)"
	[ -z "$errs" ] || { fail "module install printed PHP errors:"$'\n'"$errs"; return 1; }
}

# Prints the first 300 characters of a response, for error messages.
excerpt() {
	echo "$1" | head -c 300
}

# --- OC 1.5 - 3.0 -----------------------------------------------------------

install_ocmod_store() {
	local pkg="$1" n resp
	if is_oc15 "$STORE"; then
		# README.md: vQmod must be installed before the module.
		docker exec "$CONTAINER" test -f /var/www/html/vqmod/vqmod.php \
			|| { fail "vQmod is not installed in the store (recreate it: docker/rebuild-stores.sh -s oc15)"; return 1; }
	fi
	unzip -q "$pkg" -d "$WORK/$STORE" || { fail "cannot unzip $(basename "$pkg")"; return 1; }
	docker cp "$WORK/$STORE/upload/." "$CONTAINER:/var/www/html/" || { fail "copying files into the store failed"; return 1; }
	docker exec "$CONTAINER" chown -R www-data:www-data /var/www/html || { fail "chown in the store failed"; return 1; }
	log "files copied"

	login || return 1
	if is_oc15 "$STORE"; then
		# vQmod has no refresh step: it rereads vqmod/xml/ when a file there changes.
		log "vQmod applies vqmod/xml/OpenCartTranslateExpertClient.xml on the next page load"
	else
		refresh_modifications || return 1
	fi

	n="$(module_rows)" || { fail "database query failed"; return 1; }
	if [ "$n" -gt 0 ]; then
		log "module already installed: files updated"
	else
		resp="$(http -L "$(admin_url "$(module_install_route "$STORE")" "&extension=$MODULE_CODE")")" \
			|| { fail "Extensions > Modules > Install request failed"; return 1; }
		check_install_output "$resp" || return 1
		n="$(module_rows)" || { fail "database query failed"; return 1; }
		[ "$n" -gt 0 ] || { fail "Extensions > Modules > Install did not register the module"; return 1; }
		log "module installed"
	fi
}

# --- OC 4.x (4.0, 4.1) ------------------------------------------------------

install_id() {
	sql "SELECT extension_install_id FROM oc_extension_install WHERE code='$MODULE_CODE' ORDER BY extension_install_id DESC LIMIT 1"
}

install_oc4_store() {
	local pkg="$1" id resp next err n status steps=0
	local ext_dir="/var/www/html/extension/$MODULE_CODE"

	login || return 1

	id="$(install_id)" || { fail "database query failed"; return 1; }
	n="$(module_rows)" || { fail "database query failed"; return 1; }
	if [ -n "$id" ] && [ "$n" -gt 0 ]; then
		# Fully installed: replace the extension files. Clearing the folder first
		# drops files a newer build removed. The installer itself extracts the
		# whole package here, install.json included.
		unzip -q "$pkg" -d "$WORK/$STORE" || { fail "cannot unzip $(basename "$pkg")"; return 1; }
		docker exec "$CONTAINER" sh -c "rm -rf '$ext_dir' && mkdir -p '$ext_dir'" || { fail "clearing $ext_dir failed"; return 1; }
		docker cp "$WORK/$STORE/." "$CONTAINER:$ext_dir/" || { fail "copying files into the store failed"; return 1; }
		docker exec "$CONTAINER" chown -R www-data:www-data "$ext_dir" || { fail "chown in the store failed"; return 1; }
		log "module already installed: files updated"
		return 0
	fi

	if [ -n "$id" ]; then
		# An earlier run stopped part-way: carry on where it stopped.
		log "resuming install (extension_install_id=$id)"
	else
		# Extension Installer, step 1: upload. The file name becomes the extension code.
		resp="$(http -F "file=@$pkg;filename=$MODULE_CODE.ocmod.zip" "$(admin_url marketplace/installer.upload)")" \
			|| { fail "installer upload request failed"; return 1; }
		err="$(echo "$resp" | json_value error)"
		[ -z "$err" ] || { fail "installer upload: $err"; return 1; }
		id="$(install_id)" || { fail "database query failed"; return 1; }
		[ -n "$id" ] || { fail "installer upload did not register the extension: $(excerpt "$resp")"; return 1; }
		log "uploaded (extension_install_id=$id)"
	fi

	# Step 2: install. Each response names the next step until it is done.
	# The installer sets status = 1 once the files are extracted, and refuses to
	# run again while the extension folder exists, so skip a finished step 2.
	status="$(sql "SELECT status FROM oc_extension_install WHERE extension_install_id='$id'")" \
		|| { fail "database query failed"; return 1; }
	if [ "$status" = "1" ]; then
		next=""
		log "extension files already extracted"
	else
		next="$(admin_url marketplace/installer.install "&extension_install_id=$id")"
	fi
	while [ -n "$next" ]; do
		steps=$((steps + 1))
		[ $steps -le 20 ] || { fail "installer did not finish after 20 steps"; return 1; }
		resp="$(http "$next")" || { fail "installer request failed (step $steps)"; return 1; }
		case "$resp" in
			"{"*) ;;
			*) fail "installer step $steps did not return JSON: $(excerpt "$resp")"; return 1 ;;
		esac
		err="$(echo "$resp" | json_value error)"
		[ -z "$err" ] || { fail "installer: $err"; return 1; }
		next="$(echo "$resp" | json_value next)"
	done
	log "extension files installed"

	# Step 3: Extensions > Modules > Install (adds permissions, runs install()).
	resp="$(http "$(admin_url extension/module.install "&extension=$MODULE_CODE&code=$MODULE_CODE")")" \
		|| { fail "module install request failed"; return 1; }
	err="$(echo "$resp" | json_value error)"
	[ -z "$err" ] || { fail "module install: $err"; return 1; }
	check_install_output "$resp" || return 1
	n="$(module_rows)" || { fail "database query failed"; return 1; }
	[ "$n" -gt 0 ] || { fail "module install did not register the module: $(excerpt "$resp")"; return 1; }
	log "module installed"
}

# --- smoke checks -----------------------------------------------------------

php_errors() {
	# Matches PHP's and OpenCart's error output ("<b>Warning</b>: ...").
	grep -oE '(<b>)?(Fatal error|Parse error|Warning|Notice|Deprecated|Uncaught [A-Za-z\\]+)(</b>)?: .{0,240}' | sed -e 's/<[^>]*>//g' | head -n 3
}

smoke_check() {
	local route body errs n events table ukua
	if is_oc4 "$STORE"; then
		route="extension/$MODULE_CODE/module/$MODULE_CODE"
	elif is_pre23 "$STORE"; then
		route="module/$MODULE_CODE"
	else
		route="extension/module/$MODULE_CODE"
	fi

	# Check 1. Look for a field only the module's settings form has: the module
	# file name alone is not enough, because the header injection puts
	# client_translate_expert.js on every admin page, including the login,
	# permission-denied and not-found pages. build.sh prefixes the setting with
	# module_ for 3.0 and 4.x, hence the optional prefix.
	body="$(http "$(admin_url "$route")")" || { fail "module page request failed"; return 1; }
	errs="$(echo "$body" | php_errors || true)"
	if ! grep -qE 'id="input-(module_)?client_translate_expert_key"' <<<"$body"; then
		fail "module page ($route) did not render the settings form (login, permission or not-found page?)"
	elif [ -n "$errs" ]; then
		fail "module page shows PHP errors:"$'\n'"$errs"
	else
		log "PASS module page renders without PHP errors"
	fi

	# Check 2.
	body="$(http "$(admin_url "$(dashboard_route "$STORE")")")" || { fail "dashboard request failed"; return 1; }
	if grep -q "$MODULE_CODE.js" <<<"$body"; then
		log "PASS admin header loads $MODULE_CODE.js"
	else
		fail "admin header does not load $MODULE_CODE.js"
	fi

	# Check 3. The table alone is weak evidence: OpenCart's own reinstall (a
	# recreated container) does not drop it. So also check the extension row
	# and, on 4.x, the two header events install() registers.
	n="$(module_rows)" || { fail "database query failed"; return 1; }
	if is_oc4 "$STORE"; then
		events="$(sql "SELECT COUNT(*) FROM oc_event WHERE code IN ('client_translate_expert_header_controller','client_translate_expert_header_view')")" \
			|| { fail "database query failed"; return 1; }
	else
		events=2
	fi
	table="$(sql "SHOW TABLES LIKE 'oc_translate_expert_same_translations'")" \
		|| { fail "database query failed"; return 1; }
	if [ "$table" != "oc_translate_expert_same_translations" ]; then
		fail "table oc_translate_expert_same_translations is missing"
	elif [ "$n" -lt 1 ]; then
		fail "module is not registered in oc_extension"
	elif [ "$events" -ne 2 ]; then
		fail "expected 2 header events in oc_event, found $events"
	elif is_oc4 "$STORE"; then
		log "PASS module registered (oc_extension, 2 header events, table)"
	else
		log "PASS module registered (oc_extension, table)"
	fi

	# Check 4. The entrypoint adds uk-ua next to the stock en-gb, so there is a
	# target language to translate into.
	n="$(sql "SELECT COUNT(*) FROM oc_language WHERE status = 1")" || { fail "database query failed"; return 1; }
	ukua="$(sql "SELECT code FROM oc_language WHERE code = 'uk-ua' AND status = 1")" \
		|| { fail "database query failed"; return 1; }
	if [ "$ukua" != "uk-ua" ]; then
		fail "language uk-ua is missing or disabled; recreate the store so its entrypoint adds it"
	elif [ "$n" -lt 2 ]; then
		fail "expected at least 2 enabled languages, found $n"
	else
		log "PASS $n enabled languages, including uk-ua"
	fi
}

# --- main -------------------------------------------------------------------

FAILED=0
for STORE in $STORES; do
	CONTAINER="$("${COMPOSE[@]}" ps -q "$STORE" 2>/dev/null || true)"
	if [ -z "$CONTAINER" ]; then
		fail "store is not running. Start it: docker compose -f docker/compose.yaml up -d --build $STORE"
		continue
	fi

	BASE="http://localhost:$(store_port "$STORE")"
	JAR="$WORK/$STORE.cookies"
	TOKEN=""

	if [ "$CHECK_ONLY" = "1" ]; then
		log "checking $BASE"
		login || continue
		smoke_check || continue
		continue
	fi

	PKG="$(store_package "$STORE")"
	[ -n "$PKG" ] || { fail "no package for $STORE inside $(basename "$PACKAGE")"; continue; }
	log "installing $(basename "$PKG") into $BASE"

	if is_oc4 "$STORE"; then
		install_oc4_store "$PKG" || continue
	else
		install_ocmod_store "$PKG" || continue
	fi
	smoke_check || continue
	log "admin: $BASE/admin/ ($ADMIN_USER / $ADMIN_PASSWORD)"
done

exit "$FAILED"
