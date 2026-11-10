#!/usr/bin/env bash
# Build the module, reinstall the Docker test stores from scratch and install
# the module into them. See docker/README.md.
#
#   docker/rebuild-stores.sh                  # all stores
#   docker/rebuild-stores.sh oc30             # one store (or several)
#   docker/rebuild-stores.sh -s oc23 oc40     # skip ./build.release.sh
#
# Recreating a store wipes it: OpenCart is reinstalled into its database, so
# anything translated or configured there is gone.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE=(docker compose -f "$SCRIPT_DIR/compose.yaml")
ALL_STORES=(oc23 oc30 oc40)
TIMEOUT="${READY_TIMEOUT:-600}"

usage() {
	echo "Usage: $0 [-s] [store...]   stores: ${ALL_STORES[*]} (default: all)" >&2
	echo "  -s  skip ./build.release.sh and install the newest existing release" >&2
	exit 2
}

BUILD=1
while getopts "sh" opt; do
	case "$opt" in
		s) BUILD=0 ;;
		*) usage ;;
	esac
done
shift $((OPTIND - 1))

STORES=("$@")
[ ${#STORES[@]} -gt 0 ] || STORES=("${ALL_STORES[@]}")
for store in "${STORES[@]}"; do
	[[ " ${ALL_STORES[*]} " == *" $store "* ]] || { echo "Unknown store: $store" >&2; usage; }
done

if [ "$BUILD" -eq 1 ]; then
	# build.release.sh uses _version.txt as is. Bump it first if the module
	# sources changed since the last build (see CLAUDE.md).
	echo "== building release $(cat "$REPO_ROOT/_version.txt")"
	(cd "$REPO_ROOT" && ./build.release.sh)
fi

echo "== recreating ${STORES[*]}"
"${COMPOSE[@]}" up -d --build --force-recreate "${STORES[@]}"

# Each store installs OpenCart on its first start (docker/opencart/entrypoint.sh)
# and logs "[opencart] ready" when done, or an error and exits.
echo "== waiting for the stores to install (up to ${TIMEOUT}s)"
deadline=$((SECONDS + TIMEOUT))
pending=("${STORES[@]}")
while [ ${#pending[@]} -gt 0 ]; do
	still=()
	for store in "${pending[@]}"; do
		logs="$("${COMPOSE[@]}" logs --no-color "$store" 2>&1 || true)"
		if grep -q '\[opencart\] ready' <<<"$logs"; then
			echo "[$store] ready"
		elif grep -qE '\[opencart\] (install failed|adding uk-ua failed)' <<<"$logs" \
			|| [ -z "$("${COMPOSE[@]}" ps -q --status running "$store")" ]; then
			echo "[$store] install failed:" >&2
			grep '\[opencart\]' <<<"$logs" >&2 || true
			exit 1
		else
			still+=("$store")
		fi
	done
	pending=(${still[@]+"${still[@]}"})
	[ ${#pending[@]} -eq 0 ] && break
	if [ "$SECONDS" -ge "$deadline" ]; then
		echo "Timed out waiting for: ${pending[*]}" >&2
		exit 1
	fi
	sleep 5
done

echo "== installing the module"
status=0
for store in "${STORES[@]}"; do
	"$SCRIPT_DIR/install-module.sh" "$store" || status=1
done
exit "$status"
