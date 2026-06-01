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

cleanup_temp() {
    rm -rf sources_release sources_release_22 sources_release_21 sources_release_20 sources_release_15 sources_release_30 sources_release_40
}

cleanup_temp
if [ "$IS_RELEASE" != "release" ]; then
    rm -f ./OpenCartTranslateExpertClient-*.ocmod.zip
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
cd sources_release && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.3-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4bak' -x '*.oc4.twig' && cd "$SCRIPT_DIR"
mv "${OC23_CTRL}.oc4bak" "$OC23_CTRL"

# prepare release for OpenCart 2.2
cp -r sources_release sources_release_22
strip_oc4_event_blocks "sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php"
file_replace sources_release_22/install.php 'extension/module' 'module'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'ControllerExtensionModuleClientTranslateExpert' 'ControllerModuleClientTranslateExpert'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'extension/module/' 'module/'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'extension_module' 'module'
file_replace sources_release_22/upload/admin/controller/extension/module/client_translate_expert.php 'extension/extension' 'extension/module'
file_replace sources_release_22/upload/admin/model/extension/module/client_translate_expert.php 'ModelExtensionModuleClientTranslateExpert' 'ModelModuleClientTranslateExpert'
file_replace sources_release_22/upload/admin/view/javascript/client_translate_expert.js "'extension/module'" "'module'"
file_replace sources_release_22/upload/admin/view/javascript/client_translate_expert.js '"extension/module"' '"module"'

mkdir -p sources_release_22/upload/admin/controller/module
mv sources_release_22/upload/admin/controller/extension/module/* sources_release_22/upload/admin/controller/module/
rm -rf sources_release_22/upload/admin/controller/extension

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

cd sources_release_22 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.2-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 2.1
cp -r sources_release_22 sources_release_21
file_replace sources_release_21/upload/admin/controller/module/client_translate_expert.php '\$this->response->setOutput(\$this->load->view('\''module/client_translate_expert'\'', \$data));' 'if (file_exists(DIR_TEMPLATE . $this->config->get('\''config_template'\'') . '\''/template/module/client_translate_expert.tpl'\'')) {  $this->response->setOutput($this->load->view($this->config->get('\''config_template'\'') . '\''/template/module/client_translate_expert.tpl'\'', $data));} else {  $this->response->setOutput($this->load->view('\''module/client_translate_expert.tpl'\'', $data));}'
file_replace sources_release_21/upload/admin/controller/module/client_translate_expert.php 'load->language' 'language->load'
mv sources_release_21/upload/admin/language/en-gb sources_release_21/upload/admin/language/english
mv sources_release_21/upload/admin/language/ru-ru sources_release_21/upload/admin/language/russian
mv sources_release_21/upload/admin/language/uk-ua sources_release_21/upload/admin/language/ukrainian

cd sources_release_21 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.1-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 2.0
cp -r sources_release_21 sources_release_20
file_replace sources_release_20/upload/admin/controller/module/client_translate_expert.php 'language->load' 'load->language'

cd sources_release_20 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 1.5
cp -r sources_release_20 sources_release_15
file_replace sources_release_15/upload/admin/controller/module/client_translate_expert.php '\$data\[' '$this->data['
file_replace sources_release_15/upload/admin/controller/module/client_translate_expert.php '\$this->response->redirect' '$this->redirect'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'path="admin/controller/common/header\.php"' 'path="admin/controller/common/" name="header.php"'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'path="admin/view/template/common/header\.tpl"' 'path="admin/view/template/common/" name="header.tpl"'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '\$data\[' '$this->data['
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '<!--OC15-TE-->' '<?php if (version_compare(VERSION, "2.0", "<") \&\& strpos($bWn9UzpZeym_SERVER[REQUEST_URI], "module/client_translate_expert") !== false) { ?><script src="view/stylesheet/oc15/te/jquery-2.1.1.min.js" charset="utf-8"></script><script src="view/stylesheet/oc15/te/bootstrap.min.js" charset="utf-8"></script><link type="text/css" rel="stylesheet" href="view/stylesheet/oc15/te/bootstrap.css"><link type="text/css" rel="stylesheet" href="view/stylesheet/oc15/te/font-awesome.min.css"><?php } ?>'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'bWn9UzpZeym' ''

cd sources_release_15 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc1.5-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4.twig' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 3.0
cp -r sources_release sources_release_30
file_replace sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php 'extension/extension' 'marketplace/extension'
file_replace sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php 'token' 'user_token'
file_replace "sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php" '\$this->response->setOutput(\$this->load->view' '$this->config->set('\''template_engine'\'', '\''template'\''); $this->response->setOutput($this->load->view'
file_replace sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php "'client_translate_expert'" "'module_client_translate_expert'"

for setting in client_translate_expert_status client_translate_expert_key client_translate_expert_debug_status client_translate_expert_char_count client_translate_expert_reset_char_count_on_start_month client_translate_expert_show_char_count_globally client_translate_expert_last_reset_char_month; do
    module_setting="module_${setting}"
    for f in \
        sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php \
        sources_release_30/upload/admin/model/extension/module/client_translate_expert.php \
        sources_release_30/upload/admin/view/template/extension/module/client_translate_expert.tpl \
        sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml; do
        [ -f "$f" ] && file_replace "$f" "$setting" "$module_setting"
    done
done

file_replace sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml '.tpl' '.twig'
file_replace sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml '<\?php echo \$' '{{ '
file_replace sources_release_30/upload/system/OpenCartTranslateExpertClient.ocmod.xml '; \?\>' ' }}'
file_replace sources_release_30/upload/admin/view/javascript/client_translate_expert.js 'token' 'user_token'

# Strip OC4-only event handlers before zip (PHP 8.0 type hints break PHP 7.x)
OC30_CTRL="sources_release_30/upload/admin/controller/extension/module/client_translate_expert.php"
cp "$OC30_CTRL" "${OC30_CTRL}.oc4bak"
strip_oc4_event_blocks "$OC30_CTRL"
cd sources_release_30 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc3.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' -x '*.oc4bak' -x '*.oc4.twig' && cd "$SCRIPT_DIR"
mv "${OC30_CTRL}.oc4bak" "$OC30_CTRL"

# ============================================================
# prepare release for OpenCart 4.0
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

# Move controller and model
mv sources_release_40/upload/admin/controller/extension/module/client_translate_expert.php "$EXT_DIR/admin/controller/module/client_translate_expert.php"
mv sources_release_40/upload/admin/model/extension/module/client_translate_expert.php "$EXT_DIR/admin/model/module/client_translate_expert.php"

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
for lib in client_translate_expert_core.php client_translate_expert.php client_localization_translate_expert.php vh_google_translator.php; do
    mv "sources_release_40/upload/system/library/${lib}" "$EXT_DIR/system/library/${lib}"
done
# Also copy google-cloud-translate if present
if [ -d "sources_release_40/upload/system/library/google-cloud-translate" ]; then
    mv "sources_release_40/upload/system/library/google-cloud-translate" "$EXT_DIR/system/library/google-cloud-translate"
fi

# Remove OC 1.5 legacy stylesheets (not needed in OC4)
rm -rf "$EXT_DIR/admin/view/stylesheet/oc15"

# Copy the OC4-specific twig template
cp "sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig" \
   "$EXT_DIR/admin/view/template/module/client_translate_expert.twig"

# Move install.php to extension root
mv sources_release_40/install.php "$EXT_DIR/install.php" 2>/dev/null || true

# Clean up old structure directories (now empty or unneeded)
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
OC4_LIB_CORE="$EXT_DIR/system/library/client_translate_expert_core.php"
OC4_LIB_MAIN="$EXT_DIR/system/library/client_translate_expert.php"
OC4_LIB_LOC="$EXT_DIR/system/library/client_localization_translate_expert.php"
OC4_LIB_GOOGLE="$EXT_DIR/system/library/vh_google_translator.php"
OC4_INSTALL="$EXT_DIR/install.php"

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

# --- Update class extends for OC4 fully-qualified base classes ---
file_replace "$OC4_CONTROLLER" 'extends Controller' 'extends \\Opencart\\System\\Engine\\Controller'
file_replace "$OC4_MODEL" 'extends Model' 'extends \\Opencart\\System\\Engine\\Model'

# --- Update class names to OC4 short-name convention (namespace disambiguates) ---
file_replace "$OC4_CONTROLLER" 'ControllerExtensionModuleClientTranslateExpert' 'ClientTranslateExpert'
file_replace "$OC4_MODEL" 'ModelExtensionModuleClientTranslateExpert' 'ClientTranslateExpert'

# --- Remove PHP 5.x polyfills (hash_equals, boolval) — not needed for PHP 8.0+ ---
# Delete from "// add missed functions" through the closing "}" of boolval block (up to blank line before class)
if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' '/^\/\/ add missed functions$/,/^class /{ /^class /!d; }' "$OC4_CONTROLLER"
else
    sed -i '/^\/\/ add missed functions$/,/^class /{ /^class /!d; }' "$OC4_CONTROLLER"
fi

# --- Remove OC3 template_engine workaround (not needed in OC4) ---
file_replace "$OC4_CONTROLLER" "\\\$this->config->set('template_engine', 'template'); " ""

# --- Update route strings: extension/module/ -> extension/client_translate_expert/module/ ---
file_replace "$OC4_CONTROLLER" 'extension/module/client_translate_expert' 'extension/client_translate_expert/module/client_translate_expert'
file_replace "$OC4_MODEL" 'extension/module/client_translate_expert' 'extension/client_translate_expert/module/client_translate_expert'

# --- Update model variable name (OC4 auto-naming from route path) ---
file_replace "$OC4_CONTROLLER" 'model_extension_module_client_translate_expert' 'model_extension_client_translate_expert_module_client_translate_expert'

# --- Update controller route method separator (/ -> . for method calls) ---
# Routes like client_translate_expert/downloadDebugLog -> client_translate_expert.downloadDebugLog
file_replace "$OC4_CONTROLLER" 'client_translate_expert/downloadDebugLog' 'client_translate_expert.downloadDebugLog'
file_replace "$OC4_CONTROLLER" 'client_translate_expert/clearDebugLog' 'client_translate_expert.clearDebugLog'

# --- load->language, load->model, load->view paths are correct after route update ---
# OC4 uses full extension paths: extension/client_translate_expert/module/client_translate_expert

# --- Update model require_once paths for OC4 (use DIR_EXTENSION) ---
file_replace "$OC4_MODEL" "require_once(DIR_SYSTEM . 'library/client_translate_expert.php')" "require_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_translate_expert.php')"
file_replace "$OC4_MODEL" "require_once(DIR_SYSTEM . 'library/client_localization_translate_expert.php')" "require_once(DIR_EXTENSION . 'client_translate_expert/system/library/client_localization_translate_expert.php')"

# --- Update class instantiations in model to use namespaced classes ---
file_replace "$OC4_MODEL" 'new LibraryClientTranslateExpert(' 'new \\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\LibraryClientTranslateExpert('
file_replace "$OC4_MODEL" 'new LocalizationLibraryClientTranslateExpert(' 'new \\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\LocalizationLibraryClientTranslateExpert('

# --- Update class instantiation in core library for VHGoogleTranslator ---
file_replace "$OC4_LIB_CORE" 'new VHGoogleTranslator(' 'new \\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\VHGoogleTranslator('

# --- Update Log class instantiation for OC4 namespaced engine ---
file_replace "$OC4_LIB_CORE" 'new Log(' 'new \\Opencart\\System\\Library\\Log('

# --- Update install.php route for OC4 ---
file_replace "$OC4_INSTALL" 'extension/module/client_translate_expert' 'extension/client_translate_expert/module/client_translate_expert'

# --- Update JS: module prefix and method separator ---
# Change js_const_module_prefix to OC4 extension route prefix
file_replace "$OC4_JS" "js_const_module_prefix = 'extension/module'" "js_const_module_prefix = 'extension/client_translate_expert/module'"

# Change method separator in AJAX routes: /client_translate_expert/<method> -> /client_translate_expert.<method>
# These are the patterns after js_const_module_prefix + '/client_translate_expert/<method>'
# Replace longer method names first to avoid partial matches (e.g. translate vs translateTable)
file_replace "$OC4_JS" '/client_translate_expert/translatedCharCountInfo' '/client_translate_expert.translatedCharCountInfo'
file_replace "$OC4_JS" '/client_translate_expert/analizeTableDetail' '/client_translate_expert.analizeTableDetail'
file_replace "$OC4_JS" '/client_translate_expert/analizeLocalization' '/client_translate_expert.analizeLocalization'
file_replace "$OC4_JS" '/client_translate_expert/saveLocalizationFile' '/client_translate_expert.saveLocalizationFile'
file_replace "$OC4_JS" '/client_translate_expert/translateTable' '/client_translate_expert.translateTable'
file_replace "$OC4_JS" '/client_translate_expert/removeDataFromDb' '/client_translate_expert.removeDataFromDb'
file_replace "$OC4_JS" '/client_translate_expert/resetCharCount' '/client_translate_expert.resetCharCount'
file_replace "$OC4_JS" '/client_translate_expert/resetCache' '/client_translate_expert.resetCache'
file_replace "$OC4_JS" '/client_translate_expert/translate' '/client_translate_expert.translate'
file_replace "$OC4_JS" '/client_translate_expert/analize' '/client_translate_expert.analize'

# --- Apply module_ prefix to settings in OC4 template (same as OC3) ---
OC4_TEMPLATE="$EXT_DIR/admin/view/template/module/client_translate_expert.twig"
for setting in client_translate_expert_status client_translate_expert_key client_translate_expert_debug_status client_translate_expert_char_count client_translate_expert_reset_char_count_on_start_month client_translate_expert_show_char_count_globally client_translate_expert_last_reset_char_month; do
    module_setting="module_${setting}"
    file_replace "$OC4_TEMPLATE" "$setting" "$module_setting"
done

# --- Create install.json for OC4 extension installer ---
cat > "$EXT_DIR/install.json" <<INSTJSON
{
    "name": "OpenCart Translate Expert Client",
    "version": "${VERSION}",
    "author": "Volodymyr Hoshko",
    "link": "https://translator.codeguild.com.ua/"
}
INSTJSON

# --- Build OC4 zip ---
cd sources_release_40 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc4.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

cleanup_temp
echo "Build complete!"
