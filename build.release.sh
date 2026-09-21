#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

VERSION=$(cat _version.txt | tr -d '[:space:]')
LOCAL_DATE=$(date +%Y%m%d)

mkdir -p "./releases/v${VERSION}"
rm -f "./releases/v${VERSION}/OpenCartTranslateExpertClient-"*.zip

# Clean up any leftover build artifacts from previous runs
rm -f ./OpenCartTranslateExpertClient-*.ocmod.zip
rm -rf ./OpenCartTranslateExpertClient-oc4.0-v*/

bash "$SCRIPT_DIR/build.sh" release

zip -r "./releases/v${VERSION}/OpenCartTranslateExpertClient-v${VERSION}-${LOCAL_DATE}.full.zip" ./OpenCartTranslateExpertClient-*.ocmod.zip ./OpenCartTranslateExpertClient-oc4.0-*/
rm -f ./OpenCartTranslateExpertClient-*.ocmod.zip
rm -rf ./OpenCartTranslateExpertClient-oc4.0-v*/

echo "Release v${VERSION} built in releases/v${VERSION}/"
