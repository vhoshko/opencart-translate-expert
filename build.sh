#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

VERSION=$(cat _version.txt | tr -d '[:space:]')
IS_RELEASE="${1:-}"
LOCAL_DATE=$(date +%Y%m%d)

echo "Building v$VERSION - $LOCAL_DATE"

file_replace() {
    local file="$1"
    local search="$2"
    local replace="$3"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|${search}|${replace}|g" "$file"
    else
        sed -i "s|${search}|${replace}|g" "$file"
    fi
}

strip_oc4_event_blocks() {
    local file="$1"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' '/\/\/ OC4_EVENT_START/,/\/\/ OC4_EVENT_END/d' "$file"
    else
        sed -i '/\/\/ OC4_EVENT_START/,/\/\/ OC4_EVENT_END/d' "$file"
    fi
}

# Check a finished package: the 7 library files are in the translate_expert folder
# and no old library file name or class name remains in the package contents.
OLD_LIB_NAMES='vh_google_translator|VHGoogleTranslator|LibraryClientTranslateExpert|ClientTranslateExpertCronHelper|ClientTranslateExpertI18n|ClientTranslateExpertRouteFilter|client_translate_expert_(core|cron_helper|i18n|route_filter)\.php|client_localization_translate_expert|library/client_translate_expert\.php'
check_package() {
    local zipfile="$1"
    local dir="$2"
    local libpath="$3"
    local listing
    listing=$(unzip -Z1 "$zipfile")
    local name
    for name in translate_expert translate_expert_core translate_expert_localization translate_expert_cron_helper translate_expert_i18n translate_expert_language_code_map translate_expert_route_filter translate_expert_google_client; do
        if ! grep -qxF "${libpath}/translate_expert/${name}.php" <<< "$listing"; then
            echo "ERROR: ${libpath}/translate_expert/${name}.php is missing in $(basename "$zipfile")" >&2
            exit 1
        fi
    done
    if grep -qE "${libpath}/(client_|vh_)[a-z_]*\.php" <<< "$listing"; then
        echo "ERROR: old flat library file in $(basename "$zipfile")" >&2
        exit 1
    fi
    if grep -rqIE "$OLD_LIB_NAMES" "$dir" --exclude-dir=google-cloud-translate; then
        echo "ERROR: old library file or class name in $(basename "$zipfile")" >&2
        grep -rInE "$OLD_LIB_NAMES" "$dir" --exclude-dir=google-cloud-translate >&2
        exit 1
    fi
}

cleanup_temp() {
    rm -rf sources_release sources_release_22 sources_release_21 sources_release_20 sources_release_15 sources_release_30 sources_release_40
}

cleanup_temp
if [ "$IS_RELEASE" != "release" ]; then
    rm -f ./OpenCartTranslateExpertClient-*.ocmod.zip
    rm -rf ./OpenCartTranslateExpertClient-oc4.x-v*/ ./OpenCartTranslateExpertClient-oc4.0-v*/
fi

# prepare release for OpenCart 2.3
cp -r sources sources_release
file_replace sources_release/upload/system/OpenCartTranslateExpertClient.ocmod.xml '{client_version}' "$VERSION"
file_replace sources_release/upload/admin/controller/extension/module/client_translate_expert.php '{client_version}' "$VERSION"

if [ -d "sources_external/google-cloud-translate" ]; then
    mkdir -p sources_release/upload/system/library/google-cloud-translate
    cp -r "sources_external/google-cloud-translate/"* sources_release/upload/system/library/google-cloud-translate/
fi

# Strip OC4-only event handlers before zip (PHP 8.0 type hints break PHP 5.6/7.x)
OC23_CTRL="sources_release/upload/admin/controller/extension/module/client_translate_expert.php"
cp "$OC23_CTRL" "${OC23_CTRL}.oc4bak"
strip_oc4_event_blocks "$OC23_CTRL"
cd sources_release && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.3-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4bak' -x '*.oc4.twig' -x 'upload/admin/view/stylesheet/oc15/*' && cd "$SCRIPT_DIR"
check_package "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.3-v${VERSION}-${LOCAL_DATE}.ocmod.zip" sources_release upload/system/library
mv "${OC23_CTRL}.oc4bak" "$OC23_CTRL"

# prepare release for OpenCart 2.2
cp -r sources_release sources_release_22
strip_oc4_event_blocks "sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php"
file_replace sources_release_22/install.php 'extension/module' 'module'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'ControllerExtensionModuleClientTranslateExpert' 'ControllerModuleClientTranslateExpert'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'extension/module/' 'module/'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'extension_module' 'module'
file_replace sources_release_22/upload/admin/model/extension/module/client_translate_expert.php 'ModelExtensionModuleClientTranslateExpert' 'ModelModuleClientTranslateExpert'

mkdir -p sources_release_22/upload/admin/controller/module
mv sources_release_22/upload/admin/controller/extension/module/* sources_release_22/upload/admin/controller/module/
rm -rf sources_release_22/upload/admin/controller/extension

mkdir -p sources_release_22/upload/catalog/controller/module
file_replace sources_release_22/upload/catalog/controller/extension/module/client_translate_expert_cron.php 'ControllerExtensionModuleClientTranslateExpertCron' 'ControllerModuleClientTranslateExpertCron'
mv sources_release_22/upload/catalog/controller/extension/module/* sources_release_22/upload/catalog/controller/module/
rm -rf sources_release_22/upload/catalog/controller/extension

mkdir -p sources_release_22/upload/admin/model/module
mv sources_release_22/upload/admin/model/extension/module/* sources_release_22/upload/admin/model/module/
rm -rf sources_release_22/upload/admin/model/extension

for lang in en-gb uk-ua ru-ru; do
    mkdir -p "sources_release_22/upload/admin/language/${lang}/module"
    mv "sources_release_22/upload/admin/language/${lang}/extension/module/"* "sources_release_22/upload/admin/language/${lang}/module/"
    rm -rf "sources_release_22/upload/admin/language/${lang}/extension"
done

mkdir -p sources_release_22/upload/admin/view/template/module
mv sources_release_22/upload/admin/view/template/extension/module/* sources_release_22/upload/admin/view/template/module/
rm -rf sources_release_22/upload/admin/view/template/extension

cd sources_release_22 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.2-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' -x 'upload/admin/view/stylesheet/oc15/*' && cd "$SCRIPT_DIR"
check_package "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.2-v${VERSION}-${LOCAL_DATE}.ocmod.zip" sources_release_22 upload/system/library

# prepare release for OpenCart 2.1
cp -r sources_release_22 sources_release_21
file_replace sources_release_21/upload/admin/controller/module/client_translate_expert.php '\$this->response->setOutput(\$this->load->view('\''module/client_translate_expert'\'', \$data));' 'if (file_exists(DIR_TEMPLATE . $this->config->get('\''config_template'\'') . '\''/template/module/client_translate_expert.tpl'\'')) {  $this->response->setOutput($this->load->view($this->config->get('\''config_template'\'') . '\''/template/module/client_translate_expert.tpl'\'', $data));} else {  $this->response->setOutput($this->load->view('\''module/client_translate_expert.tpl'\'', $data));}'
file_replace sources_release_21/upload/admin/controller/module/client_translate_expert.php 'load->language' 'language->load'
mv sources_release_21/upload/admin/language/en-gb sources_release_21/upload/admin/language/english
mv sources_release_21/upload/admin/language/ru-ru sources_release_21/upload/admin/language/russian
mv sources_release_21/upload/admin/language/uk-ua sources_release_21/upload/admin/language/ukrainian

cd sources_release_21 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.1-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' -x 'upload/admin/view/stylesheet/oc15/*' && cd "$SCRIPT_DIR"
check_package "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.1-v${VERSION}-${LOCAL_DATE}.ocmod.zip" sources_release_21 upload/system/library

# prepare release for OpenCart 2.0
cp -r sources_release_21 sources_release_20
file_replace sources_release_20/upload/admin/controller/module/client_translate_expert.php 'language->load' 'load->language'

cd sources_release_20 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' -x 'upload/admin/view/stylesheet/oc15/*' && cd "$SCRIPT_DIR"
check_package "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" sources_release_20 upload/system/library

# prepare release for OpenCart 1.5
cp -r sources_release_20 sources_release_15
file_replace sources_release_15/upload/admin/controller/module/client_translate_expert.php '\$data\[' '$this->data['
file_replace sources_release_15/upload/admin/controller/module/client_translate_expert.php '\$this->response->redirect' '$this->redirect'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'path="admin/controller/common/header\.php"' 'path="admin/controller/common/" name="header.php"'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'path="admin/view/template/common/header\.tpl"' 'path="admin/view/template/common/" name="header.tpl"'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '\$data\[' '$this->data['
# OC 1.5 has no OCMOD: vQmod applies this file. A vQmod search that misses
# aborts the whole mod, so the searches must match the 1.5 header exactly:
# 1.5 has "protected function index()" and "<div id=\"header\">".
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'public function index(' 'function index('
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '<header ' '<div id="header">'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '<!--OC15-TE-->' '<?php if (version_compare(VERSION, "2.0", "<") \&\& strpos($bWn9UzpZeym_SERVER["REQUEST_URI"], "module/client_translate_expert") !== false) { ?><script src="view/stylesheet/oc15/te/jquery-2.1.1.min.js" charset="utf-8"></script><script src="view/stylesheet/oc15/te/bootstrap.min.js" charset="utf-8"></script><link type="text/css" rel="stylesheet" href="view/stylesheet/oc15/te/bootstrap.css"><link type="text/css" rel="stylesheet" href="view/stylesheet/oc15/te/font-awesome.min.css"><?php } ?>'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'bWn9UzpZeym' ''
# vQmod reads only vqmod/xml/*.xml, so the 1.5 package ships the file there.
mkdir -p sources_release_15/upload/vqmod/xml
mv sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml sources_release_15/upload/vqmod/xml/OpenCartTranslateExpertClient.xml
OC15_XML=sources_release_15/upload/vqmod/xml/OpenCartTranslateExpertClient.xml
if ! grep -qF '<![CDATA[function index(]]>' "$OC15_XML" || ! grep -qF '<![CDATA[<div id="header">]]>' "$OC15_XML"; then
    echo "ERROR: OC 1.5 vQmod search rewrite failed in $OC15_XML" >&2
    exit 1
fi

cd sources_release_15 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc1.5-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' && cd "$SCRIPT_DIR"
check_package "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc1.5-v${VERSION}-${LOCAL_DATE}.ocmod.zip" sources_release_15 upload/system/library

# prepare release for OpenCart 3.0
cp -r sources_release sources_release_30

file_replace sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml '.tpl' '.twig'
file_replace sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml '<\?php echo \$' '{{ '
file_replace sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml '; \?\>' ' }}'

# Strip OC4-only event handlers before zip (PHP 8.0 type hints break PHP 7.x)
OC30_CTRL="sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php"
cp "$OC30_CTRL" "${OC30_CTRL}.oc4bak"
strip_oc4_event_blocks "$OC30_CTRL"
cd sources_release_30 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc3.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4bak' -x '*.oc4.twig' -x 'upload/admin/view/stylesheet/oc15/*' && cd "$SCRIPT_DIR"
check_package "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc3.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" sources_release_30 upload/system/library
mv "${OC30_CTRL}.oc4bak" "$OC30_CTRL"

# ============================================================
# prepare release for OpenCart 4.x (one package for 4.0.2.x-4.1.0.x)
# ============================================================
# OC4 starts from the OC3 variant (shares user_token, module_ prefix, marketplace/extension route)
cp -r sources_release_30 sources_release_40

# --- Remove OCMOD XML (OC4 uses extension installer, not OCMOD) ---
rm -f sources_release_40/upload/system/OpenCartTranslateExpertClient.ocmod.xml

# --- Restructure directories for OC4 self-contained extension layout ---
# OC4 extensions live under extension/<extension_code>/ with paths relative to that root
EXT_DIR="sources_release_40/upload/extension/client_translate_expert"
mkdir -p "$EXT_DIR/admin/controller/module"
mkdir -p "$EXT_DIR/admin/model/module"
mkdir -p "$EXT_DIR/admin/view/template/module"
mkdir -p "$EXT_DIR/admin/view/javascript"
mkdir -p "$EXT_DIR/admin/view/stylesheet"
mkdir -p "$EXT_DIR/admin/view/image"
mkdir -p "$EXT_DIR/system/library"
mkdir -p "$EXT_DIR/catalog/controller/module"

# Move controller and model
mv sources_release_40/upload/admin/controller/extension/module/client_translate_expert.php "$EXT_DIR/admin/controller/module/client_translate_expert.php"
mv sources_release_40/upload/admin/model/extension/module/client_translate_expert.php "$EXT_DIR/admin/model/module/client_translate_expert.php"
mv sources_release_40/upload/catalog/controller/extension/module/client_translate_expert_cron.php "$EXT_DIR/catalog/controller/module/client_translate_expert_cron.php"

# Move JS, stylesheets, images
cp -r sources_release_40/upload/admin/view/javascript/* "$EXT_DIR/admin/view/javascript/" 2>/dev/null || true
cp -r sources_release_40/upload/admin/view/stylesheet/* "$EXT_DIR/admin/view/stylesheet/" 2>/dev/null || true
cp -r sources_release_40/upload/admin/view/image/* "$EXT_DIR/admin/view/image/" 2>/dev/null || true

# Move language files
for lang in en-gb uk-ua ru-ru; do
    mkdir -p "$EXT_DIR/admin/language/${lang}/module"
    mv "sources_release_40/upload/admin/language/${lang}/extension/module/client_translate_expert.php" \
       "$EXT_DIR/admin/language/${lang}/module/client_translate_expert.php"
done

# Move library files
mv sources_release_40/upload/system/library/translate_expert "$EXT_DIR/system/library/translate_expert"
# Also copy google-cloud-translate if present
if [ -d "sources_release_40/upload/system/library/google-cloud-translate" ]; then
    mv "sources_release_40/upload/system/library/google-cloud-translate" "$EXT_DIR/system/library/google-cloud-translate"
fi

# Remove OC 1.5 legacy stylesheets (not needed in OC4)
rm -rf "$EXT_DIR/admin/view/stylesheet/oc15"

# Copy the OC4-specific twig template
cp "sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig" \
   "$EXT_DIR/admin/view/template/module/client_translate_expert.twig"

# install.php is not shipped in the OC4 package: the OC4 installer never runs it
# (the module controller's install() registers events/permissions instead)
rm -f sources_release_40/install.php

# Clean up old structure directories (now empty or unneeded)
rm -rf sources_release_40/upload/catalog
rm -rf sources_release_40/upload/admin/controller/extension
rm -rf sources_release_40/upload/admin/model/extension
rm -rf sources_release_40/upload/admin/view/template/extension
rm -rf sources_release_40/upload/admin/view/javascript
rm -rf sources_release_40/upload/admin/view/stylesheet
rm -rf sources_release_40/upload/admin/view/image
rm -rf sources_release_40/upload/system/library
rm -rf sources_release_40/upload/system
for lang in en-gb uk-ua ru-ru; do
    rm -rf "sources_release_40/upload/admin/language/${lang}/extension"
done
rm -rf sources_release_40/upload/admin/language
rm -rf sources_release_40/upload/admin

OC4_CONTROLLER="$EXT_DIR/admin/controller/module/client_translate_expert.php"
OC4_MODEL="$EXT_DIR/admin/model/module/client_translate_expert.php"
OC4_JS="$EXT_DIR/admin/view/javascript/client_translate_expert.js"
OC4_LIB_CORE="$EXT_DIR/system/library/translate_expert/translate_expert_core.php"
OC4_LIB_MAIN="$EXT_DIR/system/library/translate_expert/translate_expert.php"
OC4_LIB_LOC="$EXT_DIR/system/library/translate_expert/translate_expert_localization.php"
OC4_LIB_GOOGLE="$EXT_DIR/system/library/translate_expert/translate_expert_google_client.php"
OC4_LIB_ROUTE_FILTER="$EXT_DIR/system/library/translate_expert/translate_expert_route_filter.php"
OC4_LIB_CRON_HELPER="$EXT_DIR/system/library/translate_expert/translate_expert_cron_helper.php"
OC4_LIB_I18N="$EXT_DIR/system/library/translate_expert/translate_expert_i18n.php"
OC4_LIB_LANGUAGE_CODE_MAP="$EXT_DIR/system/library/translate_expert/translate_expert_language_code_map.php"
OC4_CATALOG_CRON="$EXT_DIR/catalog/controller/module/client_translate_expert_cron.php"

# --- Add namespace declarations (insert after <?php on line 1) ---
add_namespace() {
    local file="$1"
    local ns="$2"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "1 a\\
namespace ${ns};
" "$file"
    else
        sed -i "1 a\\namespace ${ns};" "$file"
    fi
}

add_namespace "$OC4_CONTROLLER" 'Opencart\\Admin\\Controller\\Extension\\ClientTranslateExpert\\Module'
add_namespace "$OC4_MODEL" 'Opencart\\Admin\\Model\\Extension\\ClientTranslateExpert\\Module'
add_namespace "$OC4_LIB_CORE" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_MAIN" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_LOC" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_GOOGLE" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_ROUTE_FILTER" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_CRON_HELPER" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_I18N" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_LIB_LANGUAGE_CODE_MAP" 'Opencart\\Extension\\ClientTranslateExpert\\System\\Library'
add_namespace "$OC4_CATALOG_CRON" 'Opencart\\Catalog\\Controller\\Extension\\ClientTranslateExpert\\Module'

# --- Update class extends for OC4 fully-qualified base classes ---
file_replace "$OC4_CONTROLLER" 'extends Controller' 'extends \\Opencart\\System\\Engine\\Controller'
file_replace "$OC4_MODEL" 'extends Model' 'extends \\Opencart\\System\\Engine\\Model'
file_replace "$OC4_CATALOG_CRON" 'extends Controller' 'extends \\Opencart\\System\\Engine\\Controller'

# --- Update class names to OC4 short-name convention (namespace disambiguates) ---
file_replace "$OC4_CONTROLLER" 'ControllerExtensionModuleClientTranslateExpert' 'ClientTranslateExpert'
file_replace "$OC4_MODEL" 'ModelExtensionModuleClientTranslateExpert' 'ClientTranslateExpert'
file_replace "$OC4_CATALOG_CRON" 'ControllerExtensionModuleClientTranslateExpertCron' 'ClientTranslateExpertCron'

# --- Remove PHP 5.x polyfills (hash_equals, boolval) — not needed for PHP 8.0+ ---
# Delete from "// add missed functions" through the closing "}" of boolval block (up to blank line before class)
if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' '/^\/\/ add missed functions$/,/^class /{ /^class /!d; }' "$OC4_CONTROLLER"
else
    sed -i '/^\/\/ add missed functions$/,/^class /{ /^class /!d; }' "$OC4_CONTROLLER"
fi

# --- Update route strings: extension/module/ -> extension/client_translate_expert/module/ ---
file_replace "$OC4_CONTROLLER" 'extension/module/client_translate_expert' 'extension/client_translate_expert/module/client_translate_expert'

# --- Update model variable name (OC4 auto-naming from route path) ---
file_replace "$OC4_CONTROLLER" 'model_extension_module_client_translate_expert' 'model_extension_client_translate_expert_module_client_translate_expert'

# --- load->language, load->model, load->view paths are correct after route update ---
# OC4 uses full extension paths: extension/client_translate_expert/module/client_translate_expert

# --- Update class instantiations in model to use namespaced classes ---
file_replace "$OC4_MODEL" 'new TranslateExpert(' 'new \\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\TranslateExpert('
file_replace "$OC4_CATALOG_CRON" 'new TranslateExpert(' 'new \\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\TranslateExpert('
file_replace "$OC4_MODEL" 'new TranslateExpertLocalization(' 'new \\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\TranslateExpertLocalization('

# --- Update Log class instantiation for OC4 namespaced engine ---
file_replace "$OC4_LIB_CORE" 'new Log(' 'new \\Opencart\\System\\Library\\Log('

# --- Remove l2.image from SQL (OC4 oc_language table has no 'image' column) ---
if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' '/l2\.image language_image_to/d' "$OC4_LIB_MAIN"
else
    sed -i '/l2\.image language_image_to/d' "$OC4_LIB_MAIN"
fi

# --- Update Bootstrap modal calls for OC4 (Bootstrap 5 uses native JS API, not jQuery) ---
# showTranslateExpertModal and hideTranslateExpertModal in the source already use the Bootstrap 5 API
# (getOrCreateInstance, and a hide that waits for the show animation). The OC4 template sets
# data-bs-backdrop="static" and data-bs-keyboard="false", so the source functions need no rewrite.

# --- Update image paths for OC4 extension directory structure ---
# rootUrl in JS points to admin dir, so we need ../ to reach OC root where extension/ lives
file_replace "$OC4_JS" "rootUrl + 'view/image/translate_expert_client/" "rootUrl + '../extension/client_translate_expert/admin/view/image/translate_expert_client/"
# heading_title in the language files has a relative <img src> (relative to the admin dir), same fix
for lang in en-gb uk-ua ru-ru; do
    file_replace "$EXT_DIR/admin/language/${lang}/module/client_translate_expert.php" 'src="view/image/translate_expert_client/' 'src="../extension/client_translate_expert/admin/view/image/translate_expert_client/'
    if ! grep -q 'src="../extension/client_translate_expert/admin/view/image/translate_expert_client/' "$EXT_DIR/admin/language/${lang}/module/client_translate_expert.php"; then
        echo "ERROR: OC4 heading_title icon path rewrite failed in ${lang} language file" >&2
        exit 1
    fi
done

# --- Restructure for OC4 zip: installer expects files at root, not under upload/extension/<code>/ ---
# The installer extracts to DIR_EXTENSION/<code>/ using the zip filename as code
OC4_ZIP_ROOT="sources_release_40/oc4zip"
mkdir -p "$OC4_ZIP_ROOT"
cp -r "$EXT_DIR"/* "$OC4_ZIP_ROOT/"

# --- Create install.json at zip root ---
cat > "$OC4_ZIP_ROOT/install.json" <<INSTJSON
{
    "name": "OpenCart Translate Expert Client",
    "version": "${VERSION}",
    "description": "Translate products, categories, descriptions and language files via the Google Cloud Translation API. Supports OpenCart 4.0.2.x-4.1.0.x.",
    "author": "Volodymyr Hoshko",
    "link": "https://github.com/vhoshko/opencart-translate-expert"
}
INSTJSON

# --- Build OC4 zip (inside a named subfolder for the release archive) ---
OC4_SUBFOLDER="$SCRIPT_DIR/OpenCartTranslateExpertClient-oc4.x-v${VERSION}-${LOCAL_DATE}"
mkdir -p "$OC4_SUBFOLDER"
cd "$OC4_ZIP_ROOT" && zip -r "$OC4_SUBFOLDER/client_translate_expert.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"
check_package "$OC4_SUBFOLDER/client_translate_expert.ocmod.zip" "$OC4_ZIP_ROOT" system/library

cleanup_temp
echo "Build complete!"
