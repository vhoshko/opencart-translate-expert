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

cleanup_temp() {
    rm -rf sources_release sources_release_22 sources_release_21 sources_release_20 sources_release_15 sources_release_30
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

cd sources_release && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.3-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 2.2
cp -r sources_release sources_release_22
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

cd sources_release_22 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.2-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 2.1
cp -r sources_release_22 sources_release_21
file_replace sources_release_21/upload/admin/controller/module/client_translate_expert.php '\$this->response->setOutput(\$this->load->view('\''module/client_translate_expert'\'', \$data));' 'if (file_exists(DIR_TEMPLATE . $this->config->get('\''config_template'\'') . '\''/template/module/client_translate_expert.tpl'\'')) {  $this->response->setOutput($this->load->view($this->config->get('\''config_template'\'') . '\''/template/module/client_translate_expert.tpl'\'', $data));} else {  $this->response->setOutput($this->load->view('\''module/client_translate_expert.tpl'\'', $data));}'
file_replace sources_release_21/upload/admin/controller/module/client_translate_expert.php 'load->language' 'language->load'
mv sources_release_21/upload/admin/language/en-gb sources_release_21/upload/admin/language/english
mv sources_release_21/upload/admin/language/ru-ru sources_release_21/upload/admin/language/russian
mv sources_release_21/upload/admin/language/uk-ua sources_release_21/upload/admin/language/ukrainian

cd sources_release_21 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.1-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 2.0
cp -r sources_release_21 sources_release_20
file_replace sources_release_20/upload/admin/controller/module/client_translate_expert.php 'language->load' 'load->language'

cd sources_release_20 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc2.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

# prepare release for OpenCart 1.5
cp -r sources_release_20 sources_release_15
file_replace sources_release_15/upload/admin/controller/module/client_translate_expert.php '\$data\[' '$this->data['
file_replace sources_release_15/upload/admin/controller/module/client_translate_expert.php '\$this->response->redirect' '$this->redirect'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'path="admin/controller/common/header\.php"' 'path="admin/controller/common/" name="header.php"'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'path="admin/view/template/common/header\.tpl"' 'path="admin/view/template/common/" name="header.tpl"'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '\$data\[' '$this->data['
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml '<!--OC15-TE-->' '<?php if (version_compare(VERSION, "2.0", "<") \&\& strpos($bWn9UzpZeym_SERVER[REQUEST_URI], "module/client_translate_expert") !== false) { ?><script src="view/stylesheet/oc15/te/jquery-2.1.1.min.js" charset="utf-8"></script><script src="view/stylesheet/oc15/te/bootstrap.min.js" charset="utf-8"></script><link type="text/css" rel="stylesheet" href="view/stylesheet/oc15/te/bootstrap.css"><link type="text/css" rel="stylesheet" href="view/stylesheet/oc15/te/font-awesome.min.css"><?php } ?>'
file_replace sources_release_15/upload/system/OpenCartTranslateExpertClient.ocmod.xml 'bWn9UzpZeym' ''

cd sources_release_15 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc1.5-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

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

cd sources_release_30 && zip -r "$SCRIPT_DIR/OpenCartTranslateExpertClient-oc3.0-v${VERSION}-${LOCAL_DATE}.ocmod.zip" . -x '*.DS_Store' && cd "$SCRIPT_DIR"

cleanup_temp
echo "Build complete!"
