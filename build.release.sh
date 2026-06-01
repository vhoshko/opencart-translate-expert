#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

VERSION=$(cat _version.txt | tr -d '[:space:]')
LOCAL_DATE=$(date +%Y%m%d)

mkdir -p "./releases/v${VERSION}"
rm -f "./releases/v${VERSION}/OpenCartTranslateExpertClient-"*.zip

bash "$SCRIPT_DIR/build.sh"

zip "./releases/v${VERSION}/OpenCartTranslateExpertClient-v${VERSION}-${LOCAL_DATE}.full.zip" ./OpenCartTranslateExpertClient-*.ocmod.zip
rm -f ./OpenCartTranslateExpertClient-*.ocmod.zip

echo "Release v${VERSION} built in releases/v${VERSION}/"
