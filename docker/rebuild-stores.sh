#!/usr/bin/env bash
# Update the checkout (git fetch and git pull --ff-only), build the module,
# reinstall the Docker test stores from scratch and install the module into
# them. See docker/README.md.
#
#   docker/rebuild-stores.sh                  # all stores
#   docker/rebuild-stores.sh oc30             # one store (or several)
#   docker/rebuild-stores.sh -s oc23 oc40     # skip ./build.release.sh
#   docker/rebuild-stores.sh -n oc41          # skip git fetch and git pull
#
# Stores: oc15 oc20 oc21 oc22 oc23 oc30 oc40 oc41
#
# Recreating a store wipes it: OpenCart is reinstalled into its database, so
# anything translated or configured there is gone.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE=(docker compose -f "$SCRIPT_DIR/compose.yaml")
ALL_STORES=(oc15 oc20 oc21 oc22 oc23 oc30 oc40 oc41)
TIMEOUT="${READY_TIMEOUT:-600}"

usage() {
	echo "Usage: $0 [-s] [-n] [store...]   stores: ${ALL_STORES[*]} (default: all)" >&2
	echo "  -s  skip ./build.release.sh and install the existing release of _version.txt" >&2
	echo "  -n  skip git fetch and git pull (offline, local changes, or not a git checkout)" >&2
	exit 2
}

ORIG_ARGS=("$@")
BUILD=1
GIT_UPDATE=1
while getopts "snh" opt; do
	case "$opt" in
		s) BUILD=0 ;;
		n) GIT_UPDATE=0 ;;
		*) usage ;;
	esac
done
shift $((OPTIND - 1))

STORES=("$@")
[ ${#STORES[@]} -gt 0 ] || STORES=("${ALL_STORES[@]}")
for store in "${STORES[@]}"; do
	[[ " ${ALL_STORES[*]} " == *" $store "* ]] || { echo "Unknown store: $store" >&2; usage; }
done

# Get the newest commits of the current branch first, so the build and the
# stores use the pushed code. --ff-only stops the script instead of making a
# merge commit when the local branch and the remote branch have diverged.
# If the pull changed the commit, the script starts again, so a pulled change
# to this script applies at once. REBUILD_STORES_PULLED prevents a second pull.
# A git failure stops the script before the build, so the stores never get old
# code without a message. Use -n to skip this step.
git_failed() {
	echo "$1 Use -n to skip the git update." >&2
	exit 1
}
if [ "$GIT_UPDATE" -eq 1 ] && [ -z "${REBUILD_STORES_PULLED:-}" ]; then
	before="$(git -C "$REPO_ROOT" rev-parse HEAD 2>/dev/null)" \
		|| git_failed "$REPO_ROOT is not a git checkout."
	if branch="$(git -C "$REPO_ROOT" symbolic-ref -q --short HEAD)"; then
		echo "== updating the checkout ($branch)"
	else
		echo "== updating the checkout (detached HEAD)"
	fi
	git -C "$REPO_ROOT" fetch --prune || git_failed "git fetch failed."
	if [ -z "$branch" ]; then
		echo "HEAD is detached (no branch is checked out). Skipping git pull." >&2
	elif git -C "$REPO_ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{u}' >/dev/null 2>&1; then
		git -C "$REPO_ROOT" pull --no-rebase --ff-only || git_failed "git pull failed (see the git message above)."
	else
		echo "The branch $branch has no upstream branch. Skipping git pull." >&2
	fi
	if [ "$(git -C "$REPO_ROOT" rev-parse HEAD)" != "$before" ]; then
		echo "== the checkout changed, starting the script again"
		REBUILD_STORES_PULLED=1 exec "$SCRIPT_DIR/rebuild-stores.sh" ${ORIG_ARGS[@]+"${ORIG_ARGS[@]}"}
	fi
fi

if [ "$BUILD" -eq 1 ]; then
	# build.release.sh uses _version.txt as is. Bump the version in the commit
	# that changes the module sources (see CLAUDE.md), not here before a run:
	# pushed commits change _version.txt too, so a local bump stops the git pull.
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
			# The installer's own error is not an [opencart] line.
			echo "[$store] last log lines:" >&2
			tail -n 20 <<<"$logs" >&2 || true
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
