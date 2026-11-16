if (document.readyState == 'loading') {
  document.addEventListener('DOMContentLoaded', initTranslateExpert);
} else {
  initTranslateExpert();
}

function initTranslateExpert() {

	initTranslateExpertMultiSelectButtons();
	teInitPage();
	initTranslateExpertCron();

	if ((typeof document.js_const_client_translate_expert_is_enabled !== 'undefined')
		&& document.js_const_client_translate_expert_is_enabled)
	{
		var t0 = performance.now();
		addTranslateExpertElements();
		var t1 = performance.now();
		var executionTime = t1 - t0;
		var intervalDelay = executionTime * 20;
		if (intervalDelay < 2000) {
			intervalDelay = 2000;
		}
		console.log('Translate Expert: Initial execution time: ' + executionTime + ' ms, interval delay set to ' + intervalDelay + ' ms');

		setInterval(function() {
			addTranslateExpertElements();
		}, intervalDelay);

		initModule();
	}
	else if ($('#te-summary-count').length)
	{
		document.js_const_module_prefix = 'extension/module';
		showTranslatedCharCount();
	}

	bindTeResetCharCountHandler();
}

function clickTranslateExpertLocalizationHeader(el)
{
	if ($( el ).parent().parent().next().is(":hidden"))
	{
		$( el ).parent().parent().next().show();
		$( el ).parent().parent().next().next().show();
		$( el ).parent().parent().next().next().next().show();
	}
	else
	{
		$( el ).parent().parent().next().hide();
		$( el ).parent().parent().next().next().hide();
		$( el ).parent().parent().next().next().next().hide();
	}
}

// Localization tabs: "catalog" (#tab-localization) and "admin" (#tab-admin-localization).
var translateExpertLocalizationIds = {
	catalog: {
		from: '#te-langbar-from',
		to: '#te-langbar-to',
		anchor: '#te-begin-localization-analization',
		button: '#te-begin-localization-analization',
		result: '#te-localization-analization-result'
	},
	admin: {
		from: '#te-langbar-from',
		to: '#te-langbar-to',
		anchor: '#te-begin-admin-localization-analization',
		button: '#te-begin-admin-localization-analization',
		result: '#te-admin-localization-analization-result'
	}
};

function getTranslateExpertLocalizationScope(el)
{
	return $(el).closest('[data-scope]').attr('data-scope') === 'admin' ? 'admin' : 'catalog';
}

function getTranslateExpertLocalizationResults(scope)
{
	if (!document.localizationAnalizationResults)
		document.localizationAnalizationResults = {};
	return document.localizationAnalizationResults[scope] || [];
}

function doSaveLocalizationExpert(el, callback = null)
{
	var headerEl = $(el).parent().parent().prev();
	var pathTo = headerEl.attr('data-path-to');
	var root = headerEl.attr('data-root') || 'core';
	var scope = getTranslateExpertLocalizationScope(el);
	console.log(pathTo);

	var fromEl = $(el).parent().parent().next().find('.localization-analize-file.from pre');
	var langFromId = fromEl.data('lang-from');
	console.log(langFromId);

	var toEl = $(el).parent().parent().next().find('.localization-analize-file.to textarea');
	var langToId = toEl.data('lang-to');
	console.log(langToId);
	var fileContent = toEl[0].value;

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/saveLocalizationFile&token=' + getOcToken(),
		{ fileContent: fileContent, langId: langToId, path: pathTo, scope: scope, root: root }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, toEl);
			return;
		}

		showTranslateExpertInfoMessage(document.js_const_saved, toEl);
		console.log('doSaveLocalizationExpert: success');

		if (callback)
			callback();

	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, toEl);
	});
}

function doTranslateLocalizationExpert(el, callback = null)
{
	var headerEl = $(el).parent().parent().prev();
	var pathTo = headerEl.attr('data-path-to');
	var root = headerEl.attr('data-root') || 'core';
	var scope = getTranslateExpertLocalizationScope(el);
	console.log(pathTo);

	var fromEl = $(el).parent().parent().next().find('.localization-analize-file.from pre');
	var langFromId = fromEl.data('lang-from');
	console.log(langFromId);

	var toEl = $(el).parent().parent().next().find('.localization-analize-file.to textarea');
	var langToId = toEl.data('lang-to');
	console.log(langToId);

	var analizationResult = getTranslateExpertLocalizationResults(scope).find((analizationResult) => analizationResult.pathTo == pathTo && (analizationResult.root || 'core') == root);
	if (analizationResult)
	{
		console.log(analizationResult);
		var toTranslate = analizationResult.toTranslate;
		doTranslateLocalization(langFromId, langToId, toTranslate, toEl, callback);
	}
}

// scope: "catalog", "admin", or an element inside the result div of a localization tab.
function doTranslateLocalizationAllExpert(index = 0, scope = 'catalog')
{
	if (typeof scope !== 'string')
		scope = getTranslateExpertLocalizationScope(scope);
	var ids = translateExpertLocalizationIds[scope] || translateExpertLocalizationIds.catalog;
	var allButtons = $(ids.result).find('.translate_localization_button');
	if (index < allButtons.length)
	{
		var btn = allButtons[index];
		var headerEl = $(btn).parent().parent().prev();
		var pathTo = headerEl.attr('data-path-to');
		var root = headerEl.attr('data-root') || 'core';
		if (root.indexOf('ext:') === 0)
			pathTo = '[' + root.substr(4) + '] ' + pathTo;

		showTranslateExpertModal(document.js_const_localization_translate_progress
			.replace('{currIndex}', index + 1)
			.replace('{count}', allButtons.length)
			.replace('{path}', pathTo)
		);

		doTranslateLocalizationExpert(btn, function(){
			doSaveLocalizationExpert(btn, function(){
				doTranslateLocalizationAllExpert(++index, scope);
			});
		});
	}
	else
	{
		showTranslateExpertModal(document.js_const_localization_translate_done
			.replace('{currIndex}', allButtons.length)
			.replace('{count}', allButtons.length)
			.replace('{path}', pathTo)
		);
	}
}

function doTranslateLocalization(langFromId, langToId, toTranslate, toEl, callback = null)
{
	// A value with PHP code (value.expression) is not sent to Google: its statement is copied unchanged.
	var textArr = [];
	Object.entries(toTranslate).forEach(([key, value]) => {
		var valueStr = value.expression ? '' : value.text.trim();
		if (valueStr)
			textArr.push(valueStr);
	});

	console.log(textArr);

	var appendTranslated = function(texts) {
		var translatedResult = toEl.text();
		if (!translatedResult
			|| (translatedResult.indexOf('<?php') === -1)
			|| ((translatedResult.indexOf('<?php') !== -1) && (translatedResult.indexOf('?>') !== -1) && (translatedResult.lastIndexOf('<?php') < translatedResult.lastIndexOf('?>')))
		)
		{
			translatedResult = translatedResult + "\n\n<?php\n";
		}
		if (translatedResult)
			translatedResult = translatedResult + "\n";
		var i = 0;
		Object.entries(toTranslate).forEach(([key, value]) => {
			if (value.expression)
			{
				translatedResult += "\n" + value.full_text;
				return;
			}
			var valueStr = value.text.trim();
			var translatedStr = valueStr ? texts[i++] : '';
			translatedStr = translatedStr.replaceAll('"', '\\"');
			translatedResult += "\n$_['" + key + "'] = \"" + translatedStr + "\";";
		});

		toEl.text(translatedResult);
		toEl[0].scrollTop = toEl[0].scrollHeight;

		if (callback)
			callback();
	};

	if (!textArr.length)
	{
		appendTranslated([]);
		return;
	}

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/translate&token=' + getOcToken(),
		{ textArr: JSON.stringify(textArr), source: langFromId, target: langToId, format: 'html' }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, toEl);
			return;
		}

		showTranslatedCharCount();
		appendTranslated(data.texts);
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, toEl);
	});
}

function updateGrapesJsElements()
{
	if ((typeof grapesjs !== "undefined") && grapesjs.editors && grapesjs.editors.length)
	{
		for(var i = 0; i < grapesjs.editors.length; i++)
		{
			if (grapesjs.editors[i].editor && grapesjs.editors[i].editor.attrsOrig && grapesjs.editors[i].editor.attrsOrig.id)
			{
				var elId = grapesjs.editors[i].editor.attrsOrig.id;
				if (elId.split('-').length == 2)
				{
					var langId = elId.split('-')[1];
					$('#' + elId).attr('name', 'text[' + langId + '][vh_grapesjs]');
				}
			}
		}
	}
}

function updateSimpleElements()
{
	var route = getParameter('route') || '';
	if (route.indexOf('/simple') == -1)
		return;

	if (!document.vh_settings_index)
		document.vh_settings_index = 1;

	var settings = $('div.setting')
		.filter(function(){
			return !$(this).data('te-vh-index');
		});
	settings.each(function( index ) {
		$(this).data('te-vh-index', 'vh_settings_index_' + (document.vh_settings_index++));
	});

	var els = $([
			"textarea.form-control",
			"input.form-control",
		].join(','))
		.filter(function(){
			if ($(this).attr('name'))
				return false;
			var langImage = $(this).parent().find('span.lang > img[alt]');
			return langImage.length == 1;
		});

	//console.log(els);
	els.each(function( index ) {
		var langCode = $(this).parent().find('span.lang > img[alt]').prop('alt').replace('_', '-');
		var langId = document.js_const_client_translate_expert_languages[langCode]['language_id'];
		var name = $(this).parent().parent().data('te-vh-index');

		var newName = "_te_vh[" + langId + "][" + name + "]";
		$(this).attr('name', newName);
	});
	//console.log(els);
}

function updateBatchEditorElements() {
	var nameRegex = /^(.*)\_(\d+)\_(.*)$/;
	var els = $([
			"textarea[id^='description_'][id$='_description']"
		].join(','))
		.filter(function(){
			return !$(this).attr('name') && (nameRegex.exec($(this).attr('id')) != null);
		});

	//console.log(els);
	els.each(function( index ) {
		var nameMatch = nameRegex.exec($(this).attr('id'));
		var newName = nameMatch[1] + "_te_vh[" + nameMatch[2] + "][" + nameMatch[3] + "_te_vh]";
		$(this).attr('name', newName);
	});
	//console.log(els);
}

function updateHandyProductManagerElements() {
	var route = getParameter('route') || '';
	if (route.indexOf('handy_product_manager/productList') == -1)
		return;


	var nameRegex = /^(.*)\-(\d+)\-(\d+)$/;
	var els = $([
			"#products-list tr[data-product-id] input[type='text'][id*='-']",
			"#products-list tr[data-product-id] textarea[id*='-']"
		].join(','))
		.filter(function(){
			return !$(this).attr('name') && (nameRegex.exec($(this).attr('id')) != null);
		});

	//console.log(els);
	els.each(function( index ) {
		var nameMatch = nameRegex.exec($(this).attr('id'));
		var newName = nameMatch[1] + "_te_vh[" + nameMatch[3] + "][" + nameMatch[2] + "_te_vh]";
		$(this).attr('name', newName);
	});
	//console.log(els);
}

function addTranslateExpertElements() {

	updateGrapesJsElements();
	updateSimpleElements();
	updateBatchEditorElements();
	updateHandyProductManagerElements();

	var els = $([
//			"#input-mpn", "#input-isbn",
			"div[name*='[vh_grapesjs]']",
			"input[type='text'][name*='['][name*=']']",
			"input.form-control[name*='_te_vh['][name*='['][name*=']']",
			"textarea[name*='['][name*=']']"
		].join(','))
		.filter(function(){
			return !$(this).attr('translate_expert_processed') && checkElementSupportTranslateExpert(this);
		});

	els.attr('translate_expert_processed', '1');

	var rootUrl = document.location.href.substr(0, document.location.href.indexOf('index.php'));
	var customStyleAttr = 'style="background-image: url(' + rootUrl + 'view/image/translate_expert_client/translate-icon-32.png);width:15px;height:15px;background-size:cover;border:none;"';

	$( '<input type="button" class="translate_button" ' + customStyleAttr + ' title="Translate" onclick="doTranslateExpert(this)">' ).insertBefore( els );
}

function checkElementSupportTranslateExpert(el) {

//	if ("input-mpn" == el.id || "input-isbn" == el.id) return 1;

	if (isTranslateExpertGrapesJs(el))
		return 1;

	var els = getTranslateExpertFromElement(el);
	if (!els || els.length == 0)
		return;

	var nameRegex = /^(uni_set)[^\d]*\[(\d+)\].*?(?:\[([^\[]+)\]|)$/;
	var nameMatch = nameRegex.exec(el.name);
	if (!nameMatch)
	{
		nameRegex = /^(.+)\[(\d+)\].*\[([^\[]+)\]$/;
		nameMatch = nameRegex.exec(el.name);
		if (!nameMatch)
		{
			nameRegex = /^(.+)\[(\d+)\].*?(?:\[([^\[]+)\]|)$/;
			nameMatch = nameRegex.exec(el.name);
		}
	}
	if (!nameMatch)
		return;

	var unsupportedFirstNames = ['product_attribute'];
	var unsupportedLastNames = ['quantity', 'quantity', 'priority', 'price', 'date_start', 'date_end', 'date', 'points', 'sort_order', 'model', 'sku', 'keyword', 'color', 'seourl', 'link', 'column'];

	if (unsupportedFirstNames.indexOf(nameMatch[1]) > -1)
		return;

	if (unsupportedLastNames.indexOf(nameMatch[3]) > -1)
		return;

	return 1;
}

function showTranslateExpertErrorMessage(data, el) {
	console.log('!!!!!!!!!!!Translate Expert Error!!!!!!!!!!!!!');
	console.log(data);

	var error = '';
	if (data.hasOwnProperty('message'))
		error = data.message;
	if (data.hasOwnProperty('responseText'))
		error = data.responseText;
	if (error == '')
	{
		error = 'Internal error, more info in console log!<br><br>';
		if (typeof data === 'string' || data instanceof String)
			error += (data.indexOf('</title>') !== -1 ? $("<p></p>").html(data).text() : data);
	}

	if (data.hasOwnProperty('additional_message'))
		error += '<br>' + data.additional_message;

	error = error.substring(0,2000);

	console.log(error);
	console.log(el);

	if (!el)
		el = $('#te-site-alerts')[0];

	$('.alert.alert-danger').remove();
	$(el).before('<div class="alert alert-danger" style="display: none;"><i class="fa fa-exclamation-circle"></i> ' + error + '</div>');
	//$('.alert-danger').slideDown().delay(100000).slideUp();
	$('.alert-danger').slideDown();

	$('.stop_modal_process').hide();
	$('.close_modal').removeAttr('disabled');
	hideTranslateExpertModal();
}

function showTranslateExpertInfoMessage(message, el) {
	if (!el)
		el = $('#te-site-alerts')[0];

	$('.te-info').remove();
	$(el).before('<div class="te-info" style="display: none;"><i class="fa fa-info-circle"></i> ' + message + '</div>');
	//$('.te-info').slideDown().delay(100000).slideUp();
	$('.te-info').slideDown();
}

function isTranslateExpertGrapesJs(el) {
	return $(el).attr('name') && $(el).attr('name').indexOf("[vh_grapesjs]") !== -1;
}

function isTranslateExpertSummernote(el) {
	return $(el).data != undefined && $(el).data() != undefined && $(el).data().summernote != undefined;
}

function isTranslateExpertSummernoteOld(el) {
	return ($(el).code != undefined) && ($(el).prop("tagName").toLowerCase() === 'textarea') && !!$(el).code();
}

function isTranslateExpertCKEDITOR(el) {
	return getElementCKEDITOR(el) != undefined;
}

function isTranslateExpertHTMLEditor(el) {
	return isTranslateExpertCKEDITOR(el)
		|| isTranslateExpertSummernote(el)
		|| isTranslateExpertSummernoteOld(el)
		|| isTranslateExpertGrapesJs(el);
}

function getElementCKEDITOR(el) {
	if (typeof(CKEDITOR) === "undefined")
		return undefined;

	if (el instanceof jQuery)
		el = el[0];
	var elName = el.id ? el.id : el.name;
	return CKEDITOR.instances[elName];
}

function setTranslateExpertValue(el, textValue) {
	$(el).trigger( "click" );
	var e = jQuery.Event("keydown");
	e.which = 32; // # Some key code value
	$(el).trigger(e);
	$(el).trigger('keyup');
	$(el).focus();

	if (isTranslateExpertGrapesJs(el))
	{
		var editor = grapesjs.editors.find(function (editor) {
			return editor.editor.attrsOrig.id == $(el).attr('id');
		});
		if (editor)
		{
			var elFrom = getTranslateExpertFromElement(el);

			var editorFrom = grapesjs.editors.find(function (editor) {
				return editor.editor.attrsOrig.id == $(elFrom).attr('id');
			});

			editor.editor.Components.clear();
			editor.editor.Css.clear();
			if (editorFrom)
			{
				editor.editor.setStyle(editorFrom.editor.getCss());
			}
			editor.editor.setComponents(textValue);
		}
	}
	else
	if (isTranslateExpertSummernote(el))
		$(el).summernote("code", textValue);
	else
	if (isTranslateExpertCKEDITOR(el))
		getElementCKEDITOR(el).setData(textValue);
	else
	if (isTranslateExpertSummernoteOld(el))
		$(el).code(textValue);
	else
		$(el).val(textValue);

	$(el).trigger("change");
	$(el).change();
}

function getTranslateExpertValue(el) {

	if (isTranslateExpertGrapesJs(el))
	{
		var editor = grapesjs.editors.find(function (editor) {
			return editor.editor.attrsOrig.id == $(el).attr('id');
		});
		return editor
			? editor.editor.getHtml()
			: null;
	}
	else
	if (isTranslateExpertSummernote(el))
		return $(el).summernote("code");
	else
	if (isTranslateExpertCKEDITOR(el))
		return getElementCKEDITOR(el).getData();
	else
	if (isTranslateExpertSummernoteOld(el))
		return $(el).code();
	else
		return $(el).val();
}

function getParameter(name) {
	var urlParams = new URLSearchParams(window.location.search);
	var value = urlParams.get(name);
	//console.log(value);
	return value;
}

function getOcToken() {
	return getParameter('token');
}

// Bootstrap 5 Modal.hide() does nothing while the show animation runs.
// A fast response can ask to hide the modal in that time, so remember the request
// and hide the modal when the animation ends.
function trackTranslateExpertModalTransition(modalEl) {
	if (modalEl.teTransitionTracked) {
		return;
	}
	modalEl.teTransitionTracked = true;
	modalEl.addEventListener('show.bs.modal', function() {
		modalEl.teShowing = true;
	});
	modalEl.addEventListener('shown.bs.modal', function() {
		modalEl.teShowing = false;
		if (modalEl.teHidePending) {
			modalEl.teHidePending = false;
			bootstrap.Modal.getOrCreateInstance(modalEl).hide();
		}
	});
}

function hideTranslateExpertModal() {
	var modalEl = document.getElementById('translateExpertModal');
	if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
		if (modalEl.teShowing) {
			modalEl.teHidePending = true;
			return;
		}
		var instance = bootstrap.Modal.getInstance(modalEl);
		if (instance) instance.hide();
	} else {
		$(modalEl).modal('hide');
	}
}

function showTranslateExpertModal(message) {
	var titleEl = $('#translateExpertModalLabel');
	if (titleEl.attr('data-te-original') !== undefined) {
		titleEl.text(titleEl.attr('data-te-original'));
	}
	$('#translateExpertModal .modal-body').html(message);

	var modalEl = document.getElementById('translateExpertModal');
	if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
		trackTranslateExpertModalTransition(modalEl);
		modalEl.teHidePending = false;
		bootstrap.Modal.getOrCreateInstance(modalEl).show();
	} else {
		$(modalEl).modal({
			backdrop: 'static',
			keyboard: false
		});
	}
}

function getTranslateExpertFromElement(el) {

/*
    if ("input-mpn" == el.id)
	{
		$("#input-isbn").attr('targetLangId', 1);
		return $("#input-isbn");
	}
    if ("input-isbn" == el.id)
	{
		$("#input-mpn").attr('targetLangId', 3);
		return $("#input-mpn");
	}
*/

	//product_attribute[1][product_attribute_description][3][text]
	//product_variant[variant][0][name][2]

	var nameRegex = /^(uni_set[^\d]*\[)(\d+)(\].*)$/;
	var nameMatch = nameRegex.exec($(el).attr('name'));
	if (!nameMatch)
	{
		nameRegex = /^(.*\[)(\d+)(\].*)$/;
		nameMatch = nameRegex.exec($(el).attr('name'));
	}
	if (!nameMatch)
		return;

	var elPrefix = nameMatch[1];
	var targetLangId = nameMatch[2];
	var elSuffix = nameMatch[3];

	//console.log(elPrefix);
	//console.log(targetLangId);
	//console.log(elSuffix);

	var textToTranslateElement = $(el.tagName + "[name^='" + elPrefix + "'][name$='" + elSuffix + "']");
	//console.log(textToTranslateElement);

	textToTranslateElement = textToTranslateElement.filter(function(){
			return (this != el) && !!getTranslateExpertValue($(this));
		})
		.first();
	return textToTranslateElement;
}

function getElementLangId(el) {
//    if ("input-mpn" == el.id) return 1;
//    if ("input-isbn" == el.id) return 3;
	var nameRegex = /^(uni_set[^\d]*\[)(\d+)(\].*)$/;
	var nameMatch = nameRegex.exec($(el).attr('name'));
	if (!nameMatch)
	{
		nameRegex = /^(.*\[)(\d+)(\].*)$/;
		nameMatch = nameRegex.exec($(el).attr('name'));
	}
	if (!nameMatch)
		return '';
	var elPrefix = nameMatch[1];
	var langId = nameMatch[2];
	var elSuffix = nameMatch[3];
	return langId;
}

function doTranslateExpert(event) {
	$('.alert.alert-danger').remove();
    var btn = event;
	var el = btn.nextSibling;

	var textToTranslateElement = getTranslateExpertFromElement(el);

	var textToTranslate = getTranslateExpertValue(textToTranslateElement);
	console.log(textToTranslate);

	if (!textToTranslate) return;

	var source = getElementLangId(textToTranslateElement[0]);
	var target = getElementLangId(el);

	var format = isTranslateExpertHTMLEditor(el) ? 'html' : 'text';

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/translate&token=' + getOcToken(),
		{ text: textToTranslate, source: source, target: target, format: format }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, btn);
			return;
		}

		setTranslateExpertValue(el, data.text);

		showTranslatedCharCount();

	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, btn);
	});
}

function confirmStopTranslationModelProcess(confirmStr) {
	var res = confirm(confirmStr);
	if (res)
	{
		document.stopTranslationModelProcess = true;
		hideTranslateExpertModal();
	}
	return res;
}

function makeLocalizationAnalizeRequest(scope = 'catalog', nextFileIndex = 0)
{
	var ids = translateExpertLocalizationIds[scope] || translateExpertLocalizationIds.catalog;
	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');
	var langIdFrom = $(ids.from).val();
	var langIdTo = $(ids.to).val();

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/analizeLocalization'
		+ '&token=' + token
		+ '&langIdFrom=' + langIdFrom
		+ '&langIdTo=' + langIdTo
		+ '&nextFileIndex=' + nextFileIndex
		+ '&scope=' + scope
	).done(function(data) {
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, $(ids.anchor)[0]);
			$(ids.button).prop('disabled', false);
			return;
		}

		console.log(data);
		if (!document.localizationAnalizationResults)
			document.localizationAnalizationResults = {};
		if (nextFileIndex == 0)
		{
			$(ids.result).html(data.html);
			document.localizationAnalizationResults[scope] = data.analize;
		}
		else
			document.localizationAnalizationResults[scope] = getTranslateExpertLocalizationResults(scope).concat(data.analize);

		var $table = $(ids.result).find('.localization-analize-table');
		$table.html($table.html() + data.detailsHtml);
		if(!data.done)
		{
			makeLocalizationAnalizeRequest(scope, data.nextFileIndex);
		}
		else
		{
			$(ids.button).prop('disabled', false);
		}

	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, $(ids.anchor)[0]);
		$(ids.button).prop('disabled', false);
	});

}

function makeRemovingDataFromDbRequest() {

	var params = teSiteFilterParams(teSiteFilters());
	delete params.langIdFrom;
	delete params.mode;

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/removeDataFromDb'
		+ '&token=' + getOcToken(),
		params
	).done(function(data) {
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		console.log(data);

		$('#te-analization-result').empty().append(teRenderRemoveLog(data));

		$('#te-begin-analization').prop('disabled', false);

		$('.close_modal').removeAttr('disabled');
		hideTranslateExpertModal();
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
	});
}

function showTranslatedCharCount()
{
	var onModulePage = $('#te-summary-count').length > 0;

	if (!onModulePage && !document.js_const_client_translate_expert_show_char_count_is_enabled)
	{
		return;
	}

	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/translatedCharCountInfo'
		+ '&token=' + token
	).done(function(data) {
		if (!data.success)
		{
			showTeCharCountError(data);
			return;
		}

		if (onModulePage)
		{
			$('#te-summary-count').empty().append(teRenderSummaryCount(data));
			return;
		}

		var item = $('#te-translated-char-count-info');
		if (item.length == 0)
		{
			var parentElement = $('#header > .nav.pull-right');
			if (parentElement.length == 0)
			{
				parentElement = $('#header > .container-fluid > .nav.navbar-right');
			}
			if (parentElement.length == 0)
			{
				parentElement = $('#header .navbar-nav');
			}
			item = teEl('li', { 'class': 'dropdown nav-item', 'id': 'te-translated-char-count-info' });
			parentElement.prepend(item);
		}
		item.empty().append(teRenderHeaderCount(data));
	}).fail(function(data) {
		showTeCharCountError(data);
	});
}

// Module page: shows the error in the summary alerts. Other admin pages have no alert area: write to the console.
function showTeCharCountError(response)
{
	console.log(response);
	if ($('#te-summary-alerts').length)
	{
		teAlerts.clear('#te-summary-alerts');
		teAlerts.fromResponse('#te-summary-alerts', response);
	}
}

function resetTranslationCharCount()
{
	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/resetCharCount'
		+ '&token=' + token
	).done(function(data) {
		if (!data.success)
		{
			showTeCharCountError(data);
			return;
		}

		showTranslatedCharCount();
	}).fail(function(data) {
		showTeCharCountError(data);
	});
}

function resetTranslationCache()
{
	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/resetCache'
		+ '&token=' + token
	).done(function(data) {
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		console.log(data);
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
	});
}

function initModule()
{
	document.js_const_module_prefix = 'extension/module';

	teSiteInit();

	$('#te-begin-removing_data_from_db').click(function()
	{
		if (teSiteIsRunning())
			return;
		var confirmationString = document.js_const_entry_remove_translation_records_from_db_confirmation
			.replace('{langCode}', $('#te-langbar-to').find("option:selected").text());
		if (confirm(confirmationString))
		{
			teAlerts.clear('#te-site-alerts');
			$('#te-analization-result').html('');
			showTranslateExpertModal(document.js_const_entry_removing_data_from_db_process_is_started);

			makeRemovingDataFromDbRequest();
		}
	});

	$.each(translateExpertLocalizationIds, function(scope, ids) {
		$(ids.button).click(function() {
			$(ids.button).prop('disabled', true);
			makeLocalizationAnalizeRequest(scope);
		});
	});

	showTranslatedCharCount();
}

// "Select all" and "Clear selection" buttons of the analysis multi-select filters.
// The change event also updates the cron link.
function initTranslateExpertMultiSelectButtons()
{
	$('select[data-te-max]').each(function() {
		var select = $(this);
		var max = parseInt(select.attr('data-te-max'), 10);
		if (!(max > 0) || select.find('option').length <= max) {
			return;
		}
		var hint = getTranslateExpertLimitHint(select).show();
		$('.te-multi-select-all[data-te-target="#' + select.attr('id') + '"]').text(function() {
			return $(this).attr('data-te-caption-first') || $(this).text();
		});
		// A selection restored from the session can be longer than the cap
		var selected = select.find('option:selected');
		if (selected.length > max) {
			selected.slice(max).prop('selected', false);
			hint.addClass('text-danger');
		}
	});

	$('select[data-te-max]').on('change', function() {
		var select = $(this);
		var max = parseInt(select.attr('data-te-max'), 10);
		var hint = getTranslateExpertLimitHint(select);
		if (!(max > 0)) {
			return;
		}
		var selected = select.find('option:selected');
		if (selected.length > max) {
			selected.slice(max).prop('selected', false);
			hint.show().addClass('text-danger');
		} else {
			hint.removeClass('text-danger');
		}
	});

	$('.te-multi-select-all, .te-multi-select-clear').click(function() {
		var select = $($(this).attr('data-te-target'));
		var options = select.find('option');
		var max = parseInt(select.attr('data-te-max'), 10);
		if ($(this).hasClass('te-multi-select-all')) {
			options.prop('selected', false);
			(max > 0 && options.length > max ? options.slice(0, max) : options).prop('selected', true);
		} else {
			options.prop('selected', false);
		}
		select.change();
	});
}

function getTranslateExpertLimitHint(select)
{
	return select.nextAll('.te-multi-select-limit').first();
}

// Cron settings controls work also when the module status is disabled,
// so the admin can set up the secret and the link before enabling the module.
function initTranslateExpertCron()
{
	if ($('#te-cron-secret').length == 0 && $('#te-cron-link-panel').length == 0) {
		return;
	}

	$('#te-cron-secret-generate').click(function() {
		var cryptoApi = window.crypto || window.msCrypto;
		var bytes = new Uint8Array(16);
		cryptoApi.getRandomValues(bytes);
		var secret = '';
		for (var i = 0; i < bytes.length; i++) {
			secret += ('0' + bytes[i].toString(16)).slice(-2);
		}
		$('#te-cron-secret').val(secret);
		updateTranslateExpertCronLink();
	});

	$('#te-cron-link').click(function() {
		this.select();
	});

	$('#te-langbar-from, #te-langbar-to, input[name="te-analization-mode"], #te-analization-table, #te-analization-product-status-filter, #te-analization-product-quantity-filter, #te-analization-product-category-id-filter, #te-analization-product-stock-status-filter, #te-analization-product-manufacturer-filter')
		.on('change', updateTranslateExpertCronLink);
	$('#te-cron-bulk-amount, #te-cron-secret').on('change input', updateTranslateExpertCronLink);
	updateTranslateExpertCronLink();
}

function updateTranslateExpertCronLink()
{
	var panel = $('#te-cron-link-panel');
	var baseUrl = panel.attr('data-cron-url');
	if (!baseUrl) {
		return;
	}

	var secretInput = $('#te-cron-secret');
	var secret = $.trim(secretInput.val() || '');
	var secretValid = /^[A-Za-z0-9_-]{16,128}$/.test(secret);
	var secretUnsaved = secretInput.length > 0 && secret !== $.trim(secretInput.prop('defaultValue') || '');
	$('#te-cron-secret-unsaved').toggle(secretUnsaved);
	$('#te-cron-link, #te-cron-link-help').toggle(secretValid);
	$('#te-cron-link-error').toggle(!secretValid);
	if (!secretValid) {
		$('#te-cron-link').val('');
		$('#te-cron-link-long').hide();
		return;
	}

	var listParam = function(name, value) {
		return name + '=' + encodeURIComponent(value).replace(/%2C/g, ',');
	};
	var params = ['secret=' + encodeURIComponent(secret)];
	var filterParams = teSiteFilterParams(teSiteFilters());
	for (var name in filterParams) {
		if (!Object.prototype.hasOwnProperty.call(filterParams, name)) {
			continue;
		}
		params.push(teSiteIsListParam(name) ? listParam(name, filterParams[name]) : name + '=' + encodeURIComponent(filterParams[name]));
	}
	var bulkAmount = $.trim($('#te-cron-bulk-amount').val() || '');
	if (/^[0-9]{1,9}$/.test(bulkAmount) && parseInt(bulkAmount, 10) > 0) {
		params.push('bulk_amount=' + encodeURIComponent(parseInt(bulkAmount, 10)));
	}

	var link = baseUrl + '&' + params.join('&');
	$('#te-cron-link').val(link);
	var maxLength = parseInt(panel.attr('data-te-max-length'), 10);
	$('#te-cron-link-long').toggle(maxLength > 0 && link.length > maxLength);
}

// ==========================================================================
// Render helpers of the new admin design (docs/pocs/2-admin-design).
// The char count and removal log functions use them. Keep this block in ES5 (var, function).
// This file loads on all admin pages: on OC 1.5 pages other than the module page there is
// jQuery 1.7.1 and no Bootstrap, so each helper must work without Bootstrap.
// ==========================================================================

// Returns the label for key from document.te_i18n (module page), then from document.te_i18n_global
// (all admin pages). If neither object has the key, returns the key. Replaces {name} with params.name.
function teT(key, params) {
	var text = null;
	var sources = [document.te_i18n, document.te_i18n_global];
	for (var i = 0; i < sources.length; i++) {
		var source = sources[i];
		if (source && typeof source === 'object' && Object.prototype.hasOwnProperty.call(source, key)
			&& typeof source[key] === 'string') {
			text = source[key];
			break;
		}
	}
	if (text === null) {
		text = String(key);
	}
	if (!params || typeof params !== 'object') {
		return text;
	}
	return text.replace(/\{([A-Za-z0-9_]+)\}/g, function(match, name) {
		return Object.prototype.hasOwnProperty.call(params, name) && params[name] !== null && typeof params[name] !== 'undefined'
			? String(params[name])
			: match;
	});
}

// True on Bootstrap 5 pages (OC 4.x). Bootstrap 3 has no global "bootstrap" object.
function teIsBs5() {
	return typeof bootstrap !== 'undefined' && !!bootstrap.Modal;
}

// FA 4 icon names that have a different main name in FA 6. FA 6 (OC 4.x) also knows most FA 4 names as aliases.
var teIconFa6Names = {
	'check-circle': 'circle-check',
	'exclamation-circle': 'circle-exclamation',
	'exclamation-triangle': 'triangle-exclamation',
	'info-circle': 'circle-info',
	'question-circle': 'circle-question',
	'times': 'xmark',
	'times-circle': 'circle-xmark',
	'refresh': 'rotate',
	'save': 'floppy-disk',
	'trash': 'trash-can',
	'exchange': 'right-left',
	'cog': 'gear'
};

// Returns the icon classes for an FA 4 icon name: "fa fa-<name>" on FA 4 (OC 1.5-3.0), "fa-solid fa-<name>" on FA 6 (OC 4.x).
function teIcon(name) {
	name = String(name);
	if (teIsBs5()) {
		return 'fa-solid fa-' + (Object.prototype.hasOwnProperty.call(teIconFa6Names, name) ? teIconFa6Names[name] : name);
	}
	return 'fa fa-' + name;
}

// Returns an <i> icon element. The icon is decorative: put the text label next to it.
function teIconEl(name) {
	return teEl('i', { 'class': teIcon(name), 'aria-hidden': 'true' });
}

// Makes a DOM node as a jQuery object. attrs: attribute name => value ("class" adds classes).
// text (optional) goes in with .text(), never as HTML.
function teEl(tag, attrs, text) {
	var el = $(document.createElement(tag));
	if (attrs && typeof attrs === 'object') {
		for (var name in attrs) {
			if (!Object.prototype.hasOwnProperty.call(attrs, name) || attrs[name] === null || typeof attrs[name] === 'undefined') {
				continue;
			}
			if (name === 'class') {
				el.addClass(String(attrs[name]));
			} else {
				el.attr(name, String(attrs[name]));
			}
		}
	}
	if (text !== null && typeof text !== 'undefined') {
		teText(el, text);
	}
	return el;
}

// Puts value into el as text. null and undefined give an empty text.
function teText(el, value) {
	return $(el).text(value === null || typeof value === 'undefined' ? '' : String(value));
}

// Bootstrap adapter: the same call on BS3 (jQuery plugins) and BS5 (bootstrap.* classes).
var teBs = {
	// Shows a tab. el: the tab link (a[data-toggle="tab"] or a[data-bs-toggle="tab"]).
	tab: function(el) {
		var node = $(el)[0];
		if (!node) {
			return;
		}
		if (teIsBs5() && bootstrap.Tab) {
			bootstrap.Tab.getOrCreateInstance(node).show();
		} else if ($.fn.tab) {
			$(node).tab('show');
		}
	},
	// action: 'show' or 'hide'. On BS5 a hide during the show animation waits for the animation end
	// (see trackTranslateExpertModalTransition).
	modal: function(el, action) {
		var node = $(el)[0];
		if (!node || (action !== 'show' && action !== 'hide')) {
			return;
		}
		if (teIsBs5()) {
			trackTranslateExpertModalTransition(node);
			if (action === 'show') {
				node.teHidePending = false;
				bootstrap.Modal.getOrCreateInstance(node).show();
			} else if (node.teShowing) {
				node.teHidePending = true;
			} else {
				var instance = bootstrap.Modal.getInstance(node);
				if (instance) instance.hide();
			}
		} else if ($.fn.modal) {
			$(node).modal(action);
		}
	},
	// action: 'show', 'hide' or 'toggle'. el: the collapsible element (not the button).
	collapse: function(el, action) {
		var node = $(el)[0];
		if (!node || (action !== 'show' && action !== 'hide' && action !== 'toggle')) {
			return;
		}
		if (teIsBs5() && bootstrap.Collapse) {
			bootstrap.Collapse.getOrCreateInstance(node, { toggle: false })[action]();
		} else if ($.fn.collapse) {
			$(node).collapse(action);
		} else if (action === 'toggle') {
			$(node).toggle();
		} else if (action === 'show') {
			$(node).show();
		} else {
			$(node).hide();
		}
	}
};

// Alert area of a tab (.te-alerts). All text goes in with .text().
// The container must be inside .te-app: the te-alert and te-hidden rules of the CSS exist only under .te-app.
var teAlerts = {
	detailsMaxLength: 2000,
	handlersBound: false,

	// type: 'error', 'info' or 'success'. details (optional): long text for the collapsed "Details" block.
	// Returns the alert element, or null if container is not found.
	show: function(container, type, message, details) {
		var area = $(container);
		if (!area.length) {
			return null;
		}
		teAlerts.bindHandlers();
		if (type !== 'info' && type !== 'success') {
			type = 'error';
		}
		var icons = { error: 'exclamation-circle', info: 'info-circle', success: 'check-circle' };
		var alertEl = teEl('div', { 'class': 'te-alert te-alert--' + type, 'role': type === 'error' ? 'alert' : 'status' });
		alertEl.append(teEl('span', { 'class': 'te-alert__icon' }).append(teIconEl(icons[type])));
		alertEl.append(teEl('div', { 'class': 'te-alert__message' }, message));

		var actions = teEl('div', { 'class': 'te-alert__actions' });
		var detailsText = details === null || typeof details === 'undefined' ? '' : String(details);
		if (detailsText !== '') {
			if (detailsText.length > teAlerts.detailsMaxLength) {
				detailsText = detailsText.substring(0, teAlerts.detailsMaxLength);
			}
			actions.append(teEl('button', { 'type': 'button', 'class': 'te-alert__btn te-js-alert-details', 'aria-expanded': 'false' }, teT('te_alert_details')));
		}
		actions.append(teEl('button', { 'type': 'button', 'class': 'te-alert__btn te-js-alert-close', 'aria-label': teT('te_alert_close'), 'title': teT('te_alert_close') })
			.append(teIconEl('times')));
		alertEl.append(actions);

		if (detailsText !== '') {
			alertEl.append(teEl('pre', { 'class': 'te-alert__details te-hidden' }, detailsText));
		}

		area.append(alertEl);
		return alertEl;
	},

	clear: function(container) {
		$(container).empty();
	},

	// Shows the error of an AJAX response: a parsed JSON object, a JSON or other string, or a jqXHR (.fail()).
	// A JSON object with success = 1 shows nothing. A non-JSON response shows te_alert_not_json and puts the
	// response text in "Details". Returns the alert element, or null if nothing shows.
	fromResponse: function(container, response) {
		var parsed = teAlerts.parseResponse(response);
		if (parsed.data) {
			if (parsed.data.success === 1 || parsed.data.success === true) {
				return null;
			}
			var message = typeof parsed.data.message === 'string' && parsed.data.message !== ''
				? parsed.data.message
				: teT('te_global_error_internal');
			var details = typeof parsed.data.additional_message === 'string' ? parsed.data.additional_message : '';
			return teAlerts.show(container, 'error', message, details);
		}
		return teAlerts.show(container, 'error', teT('te_alert_not_json'), parsed.text);
	},

	// Returns { data: object } for JSON, or { text: string } for any other response.
	parseResponse: function(response) {
		if (response && typeof response === 'object') {
			var isXhr = typeof response.readyState !== 'undefined' && typeof response.getResponseHeader === 'function';
			// An array is not a JSON object here, the same as in parseJsonObject().
			if (!isXhr) {
				return Array.isArray(response) ? { text: JSON.stringify(response) } : { data: response };
			}
			if (response.responseJSON && typeof response.responseJSON === 'object' && !Array.isArray(response.responseJSON)) {
				return { data: response.responseJSON };
			}
			var xhrText = typeof response.responseText === 'string' ? response.responseText : '';
			var xhrData = teAlerts.parseJsonObject(xhrText);
			if (xhrData) {
				return { data: xhrData };
			}
			if (xhrText === '') {
				xhrText = $.trim((typeof response.status === 'number' ? String(response.status) : '') + ' ' + String(response.statusText || ''));
			}
			return { text: xhrText };
		}
		var text = response === null || typeof response === 'undefined' ? '' : String(response);
		var data = teAlerts.parseJsonObject(text);
		return data ? { data: data } : { text: text };
	},

	parseJsonObject: function(text) {
		if (typeof text !== 'string' || text === '') {
			return null;
		}
		try {
			var data = JSON.parse(text);
			return data && typeof data === 'object' && !Array.isArray(data) ? data : null;
		} catch (e) {
			return null;
		}
	},

	// One delegated handler for all alerts (no inline onclick).
	bindHandlers: function() {
		if (teAlerts.handlersBound) {
			return;
		}
		teAlerts.handlersBound = true;
		$(document).on('click', '.te-js-alert-close', function() {
			$(this).closest('.te-alert').remove();
		});
		$(document).on('click', '.te-js-alert-details', function() {
			var details = $(this).closest('.te-alert').find('.te-alert__details');
			var expanded = details.hasClass('te-hidden');
			details.toggleClass('te-hidden', !expanded);
			$(this).attr('aria-expanded', expanded ? 'true' : 'false');
		});
	}
};

// ==========================================================================
// Render helpers for the char count (header item and module page summary) and for the removal log.
// ==========================================================================

// Returns n as an integer with groups of 3 digits separated by a space. A value that is not a number gives 0.
function teFormatInt(n) {
	n = Math.round(Number(n));
	if (!isFinite(n)) {
		n = 0;
	}
	var sign = n < 0 ? '-' : '';
	return sign + String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Returns an amount in USD with 2 decimals. A value that is not a number gives $0.00.
function teFormatCost(x) {
	x = Number(x);
	if (!isFinite(x)) {
		x = 0;
	}
	return '$' + x.toFixed(2);
}

// Returns 'over' if the month count is above the free limit, else 'ok'.
function teCountState(data) {
	return Number(data && data.charCount) > Number(data && data.monthFreeLimit) ? 'over' : 'ok';
}

// Returns the value text of the header item, for example "300 000 / 500 000 ($0.00)".
function teCountValueText(data) {
	return teFormatInt(data.charCount) + ' / ' + teFormatInt(data.monthFreeLimit) + ' (' + teFormatCost(data.costOverFreeLimit) + ')';
}

// Returns the char count item of the admin header. No Bootstrap is needed.
function teRenderHeaderCount(data) {
	var valueText = teCountValueText(data);
	var root = teEl('span', { 'class': 'te-header-count te-header-count--' + teCountState(data), 'title': valueText });
	// Two lines: the label above the value (te-header-count__text is a flex column).
	var text = teEl('span', { 'class': 'te-header-count__text' });
	text.append(teEl('span', { 'class': 'te-header-count__label' }, teT('te_global_char_count')));
	text.append(teEl('span', { 'class': 'te-header-count__value' }, valueText));
	root.append(text);
	root.append(teEl('button', { 'type': 'button', 'class': 'te-header-count__reset te-js-reset-char-count' }, teT('te_global_reset')));
	return root;
}

// Returns the usage meter of the module page summary.
function teRenderSummaryCount(data) {
	var count = Number(data.charCount);
	var limit = Number(data.monthFreeLimit);
	var percent = limit > 0 && isFinite(count) ? Math.min(100, Math.round(count / limit * 100)) : 0;
	var root = teEl('div', { 'class': 'te-meter te-meter--' + teCountState(data) });

	var label = teEl('div', { 'class': 'te-meter__label' });
	label.append(teEl('span', {}, teT('te_summary_month', { count: teFormatInt(count), limit: teFormatInt(limit) })));
	label.append(teEl('span', {}, percent + ' %'));
	root.append(label);

	var fill = teEl('div', { 'class': 'te-meter__fill' });
	fill.css('width', percent + '%');
	root.append(teEl('div', { 'class': 'te-meter__bar' }).append(fill));

	var cost = teEl('div', { 'class': 'te-meter__cost' });
	cost.append(teEl('span', {}, teT('te_summary_cost', { cost: teFormatCost(data.costOverFreeLimit) })));
	var btnClass = teIsBs5() ? 'btn btn-light btn-sm' : 'btn btn-default btn-xs';
	cost.append(teEl('button', { 'type': 'button', 'class': btnClass + ' te-js-reset-char-count' }, teT('te_global_reset')));
	root.append(cost);
	return root;
}

// Binds one delegated click handler for all reset buttons (header item and summary).
var teResetHandlerBound = false;
function bindTeResetCharCountHandler() {
	if (teResetHandlerBound) {
		return;
	}
	teResetHandlerBound = true;
	$(document).on('click', '.te-js-reset-char-count', function() {
		if (confirm(teT('te_global_reset_confirm'))) {
			resetTranslationCharCount();
		}
	});
}

// Returns the log of the data removal: a heading, one line for each step and the time.
function teRenderRemoveLog(data) {
	var root = teEl('div', { 'class': 'te-app' });
	root.append(teEl('h2', {}, teT('te_remove_completed', { language: data.target ? data.target.name : '' })));
	var log = teEl('div', { 'class': 'te-log' });
	var steps = $.isArray(data.steps) ? data.steps : [];
	for (var i = 0; i < steps.length; i++) {
		var step = steps[i];
		var line = null;
		if (!step) {
			continue;
		}
		if (step.type === 'start') {
			line = teT('te_remove_start');
		} else if (step.type === 'table') {
			line = teT('te_remove_table', { table: step.table, rows: teFormatInt(step.clearedRows) });
		} else if (step.type === 'column') {
			line = teT('te_remove_column', { column: step.column, rows: teFormatInt(step.clearedRows) });
		} else if (step.type === 'finish') {
			line = teT('te_remove_finish');
		}
		if (line !== null) {
			log.append(teEl('div', { 'class': 'te-log__line' }, line));
		}
	}
	root.append(log);
	root.append(teEl('div', { 'class': 'te-log__time' }, teT('te_time_in_sec', { sec: Number(data.timeInSec) || 0 })));
	return root;
}

// ==========================================================================
// Page frame: language bar and collapsible blocks.
// ==========================================================================

// The language bar shows on these tabs only.
function teLangbarTabs() {
	return ['#tab-translate-site', '#tab-localization', '#tab-admin-localization'];
}

function teUpdateLangbar(href) {
	var show = $.inArray(href, teLangbarTabs()) !== -1;
	$('#te-langbar-app .te-langbar').toggleClass('te-hidden', !show);
}

// The tab link of the active tab: li.active on BS3, a.active on BS5.
function teActiveTabHref() {
	var link = $('.nav-tabs li.active > a[href^="#tab-"], .nav-tabs a.active[href^="#tab-"]').first();
	return link.length ? link.attr('href') : '';
}

// Shows the target language in the label of the data removal button.
function teUpdateRemoveLabel() {
	var btn = $('#te-begin-removing_data_from_db');
	var base = btn.attr('data-te-label');
	if (!btn.length || typeof base === 'undefined') {
		return;
	}
	btn.text(base + ' - ' + $('#te-langbar-to option:selected').text());
}

function teInitLangbar() {
	var bar = $('#te-langbar-app');
	if (!bar.length || bar.data('teBound')) {
		return;
	}
	bar.data('teBound', true);

	teUpdateLangbar(teActiveTabHref());
	var onShown = function(e) {
		teUpdateLangbar($(e.target).closest('a').attr('href'));
	};
	// BS3 triggers a jQuery event. BS5 triggers a native event (and a jQuery event if it finds jQuery).
	$(document).on('shown.bs.tab', 'a[data-toggle="tab"], a[data-bs-toggle="tab"]', onShown);
	document.addEventListener('shown.bs.tab', onShown);

	bar.on('click', '.te-js-langbar-swap', function() {
		var from = $('#te-langbar-from');
		var to = $('#te-langbar-to');
		var fromValue = from.val();
		from.val(to.val());
		to.val(fromValue);
		from.trigger('change');
		to.trigger('change');
	});

	$('#te-langbar-to').on('change', teUpdateRemoveLabel);
	teUpdateRemoveLabel();
}

// Toggle button of a collapsed block: .te-js-collapse with data-te-target (a selector) and aria-expanded.
function teInitCollapse() {
	$(document).on('click', '.te-app .te-js-collapse', function() {
		var btn = $(this);
		var target = $(btn.attr('data-te-target'));
		var open = target.hasClass('te-hidden');
		target.toggleClass('te-hidden', !open);
		btn.attr('aria-expanded', open ? 'true' : 'false');
	});
}

function teInitPage() {
	teInitLangbar();
	teInitCollapse();
}

// ==========================================================================
// Progress panel (te-progress).
// ==========================================================================

var teProgress = {
	seq: 0,

	// Builds the panel in el (a .te-progress element inside .te-app) and shows it.
	start: function(el, title) {
		var root = $(el);
		teProgress.seq++;
		var logId = 'te-progress-log-' + teProgress.seq;
		root.empty().removeClass('te-hidden te-progress--done te-progress--stopped te-progress--error');

		var head = teEl('div', { 'class': 'te-progress__head' });
		head.append(teEl('span', { 'class': 'te-progress__title' }, title));
		head.append(teEl('button', { 'type': 'button', 'class': 'te-js-stop ' + (teIsBs5() ? 'btn btn-secondary btn-sm' : 'btn btn-default btn-sm') }, teT('te_site_stop')));
		root.append(head);

		var bar = teEl('div', { 'class': 'te-progress__bar', 'role': 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100' });
		bar.append(teEl('div', { 'class': 'te-progress__fill' }));
		root.append(bar);
		root.append(teEl('div', { 'class': 'te-progress__counters' }));
		root.append(teEl('button', { 'type': 'button', 'class': 'te-collapse-toggle te-js-collapse te-progress__log-toggle te-hidden', 'data-te-target': '#' + logId, 'aria-expanded': 'false', 'aria-controls': logId }, teT('te_site_log')));
		root.append(teEl('pre', { 'class': 'te-progress__log te-hidden', 'id': logId }));
		return root;
	},

	// info.percent: a number from 0 to 100, or null (no bar). info.counters: a text or an array of texts.
	update: function(el, info) {
		var root = $(el);
		info = info || {};
		var bar = root.find('.te-progress__bar');
		var percent = info.percent === null || typeof info.percent === 'undefined' ? NaN : Number(info.percent);
		if (!isFinite(percent)) {
			bar.addClass('te-hidden').removeAttr('aria-valuenow');
		} else {
			percent = Math.max(0, Math.min(100, percent));
			bar.removeClass('te-hidden').attr('aria-valuenow', String(Math.round(percent)));
			root.find('.te-progress__fill').css('width', percent + '%');
		}
		if (typeof info.counters !== 'undefined') {
			var box = root.find('.te-progress__counters').empty();
			var items = $.isArray(info.counters) ? info.counters : [info.counters];
			for (var i = 0; i < items.length; i++) {
				box.append(teEl('span', {}, items[i]));
			}
		}
	},

	setTitle: function(el, title) {
		teText($(el).find('.te-progress__title'), title);
	},

	log: function(el, text) {
		var root = $(el);
		var log = root.find('.te-progress__log');
		log.append(teEl('div', { 'class': 'te-progress__line' }, text));
		root.find('.te-progress__log-toggle').removeClass('te-hidden');
		if (log[0]) {
			log[0].scrollTop = log[0].scrollHeight;
		}
	},

	// state: 'done', 'stopped' or 'error'.
	finish: function(el, state) {
		var root = $(el);
		if (state !== 'stopped' && state !== 'error') {
			state = 'done';
		}
		root.addClass('te-progress--' + state);
		root.find('.te-js-stop').addClass('te-hidden');
		if (state === 'done') {
			root.find('.te-progress__bar').removeClass('te-hidden').attr('aria-valuenow', '100');
			root.find('.te-progress__fill').css('width', '100%');
		}
		var badgeClass = state === 'done' ? 'te-badge--ok' : (state === 'stopped' ? 'te-badge--warn' : 'te-badge--error');
		root.find('.te-progress__head .te-badge').remove();
		root.find('.te-progress__head').append(teEl('span', { 'class': 'te-badge ' + badgeClass }, teT('te_site_state_' + state)));
	}
};

// ==========================================================================
// Tab "Website content": filters, analysis, detail view and table translation.
// ==========================================================================

function teSiteIsListParam(name) {
	return name === 'table' || name === 'product_category_id' || name === 'stock_status' || name === 'manufacturer_id';
}

function teSiteMulti(selector) {
	var values = $(selector).val();
	return $.isArray(values) ? $.grep(values, function(v) { return v !== '' && v !== null; }) : [];
}

// Reads the filters from the page.
function teSiteFilters() {
	return {
		langIdFrom: $('#te-langbar-from').val(),
		langIdTo: $('#te-langbar-to').val(),
		mode: $('input[name="te-analization-mode"]:checked').val() || 'both',
		tables: teSiteMulti('#te-analization-table'),
		deep: $('#te-analization-table-deep').is(':checked'),
		productStatus: $('#te-analization-product-status-filter').is(':checked'),
		productQuantity: $('#te-analization-product-quantity-filter').is(':checked'),
		categoryIds: teSiteMulti('#te-analization-product-category-id-filter'),
		stockStatus: teSiteMulti('#te-analization-product-stock-status-filter'),
		manufacturerIds: teSiteMulti('#te-analization-product-manufacturer-filter')
	};
}

// Returns the request parameters of the filters. The order is the order of the cron link.
// A box that is not checked and a list that is empty are left out. The server reads a present box as true.
function teSiteFilterParams(filters) {
	var params = { langIdFrom: filters.langIdFrom, langIdTo: filters.langIdTo, mode: filters.mode };
	if (filters.tables.length) {
		params.table = filters.tables.join(',');
	}
	if (filters.productStatus) {
		params.product_status = 1;
	}
	if (filters.productQuantity) {
		params.product_quantity = 1;
	}
	if (filters.categoryIds.length) {
		params.product_category_id = filters.categoryIds.join(',');
	}
	if (filters.stockStatus.length) {
		params.stock_status = filters.stockStatus.join(',');
	}
	if (filters.manufacturerIds.length) {
		params.manufacturer_id = filters.manufacturerIds.join(',');
	}
	return params;
}

// Parameters of the POST requests analizeTableDetail and translateTable. columnName '' means all columns.
function teSitePostParams(filters, tableName, columnName) {
	var params = teSiteFilterParams(filters);
	delete params.table;
	params.tableName = tableName;
	params.textColumnName = columnName;
	return params;
}

function teSiteIsRunning() {
	return !!(teSiteState && teSiteState.running);
}

function teSiteNewState(filters) {
	return {
		filters: filters,
		target: null,
		tables: {},
		order: [],
		globalCharCount: 0,
		timeInSec: 0,
		requestNumber: 0,
		tableTotal: 0,
		running: false,
		stopRequested: false,
		mode: null
	};
}

var teSiteState = teSiteNewState(null);

var teSiteTranslate = { queue: [], current: null, translatedCharCount: 0, requestNumber: 0, running: false, stopRequested: false };

function teSiteBtnClass() {
	return teIsBs5() ? 'btn btn-outline-secondary btn-sm' : 'btn btn-default btn-xs';
}

function teSiteButton(cls, label, attrs) {
	var all = { 'type': 'button', 'class': cls + ' ' + teSiteBtnClass() };
	for (var name in attrs) {
		if (Object.prototype.hasOwnProperty.call(attrs, name)) {
			all[name] = attrs[name];
		}
	}
	return teEl('button', all, label);
}

function teSiteSetBusy(busy) {
	$('#te-begin-analization, #te-begin-removing_data_from_db, #te-site-app .te-js-site-translate, #te-site-app .te-js-site-translate-all').prop('disabled', busy);
}

function teSiteBuildTableSkeleton() {
	var wrap = teEl('div', { 'class': 'te-table-wrap' });
	var table = teEl('table', { 'class': 'te-table' });
	var headRow = teEl('tr');
	headRow.append(teEl('th', {}, teT('te_site_col_table')));
	headRow.append(teEl('th', { 'class': 'te-num te-site-chars-head' }, teT('te_site_col_chars')));
	headRow.append(teEl('th', {}, teT('te_site_col_actions')));
	table.append(teEl('thead').append(headRow));
	table.append(teEl('tbody', { 'class': 'te-site-tbody' }));
	var totalRow = teEl('tr', { 'class': 'te-table__total' });
	totalRow.append(teEl('td', {}, teT('te_site_total')));
	totalRow.append(teEl('td', { 'class': 'te-num te-site-total' }, '0'));
	totalRow.append(teEl('td', { 'class': 'te-site-actions' }).append(teSiteButton('te-js-site-translate-all te-hidden', teT('te_site_translate_all'), {})));
	table.append(teEl('tfoot').append(totalRow));
	return wrap.append(table);
}

function teSiteBuildGroupRow(entry) {
	var tr = teEl('tr', { 'class': 'te-table__group', 'data-table': entry.table });
	var label = teT('te_site_toggle', { table: entry.table });
	var nameCell = teEl('td');
	nameCell.append(teEl('button', { 'type': 'button', 'class': 'te-table__expand te-js-site-toggle', 'aria-expanded': 'true', 'aria-label': label, 'title': label }));
	nameCell.append(teEl('span', { 'class': 'te-table__name' }, entry.table));
	tr.append(nameCell);
	tr.append(teEl('td', { 'class': 'te-num' }, '0'));
	var actions = teEl('td', { 'class': 'te-site-actions' });
	var slot = teEl('span', { 'class': 'te-site-badge' });
	if (!entry.supported) {
		slot.append(teEl('span', { 'class': 'te-badge te-badge--muted' }, teT('te_site_not_supported')));
	}
	actions.append(slot);
	tr.append(actions);
	return tr;
}

function teSiteBuildColumnRow(entry, column) {
	var tr = teEl('tr', { 'data-table': entry.table, 'data-column': column });
	tr.append(teEl('td', { 'class': 'te-table__sub' }, column));
	tr.append(teEl('td', { 'class': 'te-num' }, '0'));
	tr.append(teEl('td', { 'class': 'te-site-actions' }).append(teEl('span', { 'class': 'te-site-badge' })));
	return tr;
}

// Sets the count cell of a row and adds the action buttons that the row needs (an existing button stays).
function teSiteRefreshRow(row, entry, column, charCount) {
	row.children('td.te-num').text(teFormatInt(charCount));
	var actions = row.children('td.te-site-actions');
	var attrs = { 'data-table': entry.table, 'data-column': column };
	if (column !== '' && charCount > 0 && !actions.children('.te-js-site-detail').length) {
		actions.prepend(teSiteButton('te-js-site-detail', teT('te_site_details'), attrs));
	}
	if (entry.supported && charCount > 0 && !actions.children('.te-js-site-translate').length) {
		var btn = teSiteButton('te-js-site-translate', teT('te_site_translate'), attrs);
		btn.prop('disabled', teSiteIsRunning());
		var slot = actions.children('.te-site-badge');
		if (slot.length) {
			slot.before(btn);
		} else {
			actions.append(btn);
		}
	}
}

// Adds the counts of a table of one batch to the state and to the result table.
function teSiteMergeTable(state, t) {
	if (!t || typeof t.table !== 'string') {
		return;
	}
	var tbody = $('#te-analization-result .te-site-tbody');
	var entry = Object.prototype.hasOwnProperty.call(state.tables, t.table) ? state.tables[t.table] : null;
	if (!entry) {
		entry = { table: t.table, supported: Number(t.supported) === 1 ? 1 : 0, charCount: 0, columns: {}, columnOrder: [], row: null, lastRow: null, columnRows: {} };
		entry.row = teSiteBuildGroupRow(entry);
		entry.lastRow = entry.row;
		state.tables[t.table] = entry;
		state.order.push(t.table);
		tbody.append(entry.row);
	}
	entry.charCount += Number(t.charCount) || 0;

	var columns = $.isArray(t.columns) ? t.columns : [];
	var collapsed = entry.row.find('.te-js-site-toggle').attr('aria-expanded') === 'false';
	for (var i = 0; i < columns.length; i++) {
		var name = columns[i] && typeof columns[i].column === 'string' ? columns[i].column : null;
		if (name === null) {
			continue;
		}
		if (!Object.prototype.hasOwnProperty.call(entry.columns, name)) {
			entry.columns[name] = 0;
			entry.columnOrder.push(name);
			var row = teSiteBuildColumnRow(entry, name);
			row.toggleClass('te-hidden', collapsed);
			entry.lastRow.after(row);
			entry.lastRow = row;
			entry.columnRows[name] = row;
		}
		entry.columns[name] += Number(columns[i].charCount) || 0;
		teSiteRefreshRow(entry.columnRows[name], entry, name, entry.columns[name]);
	}
	teSiteRefreshRow(entry.row, entry, '', entry.charCount);
}

// Returns the jobs of "Translate all": the tables of the result that the module supports and that have characters.
function teSiteQueueAll() {
	var queue = [];
	for (var i = 0; i < teSiteState.order.length; i++) {
		var entry = teSiteState.tables[teSiteState.order[i]];
		if (entry.supported && entry.charCount > 0) {
			queue.push({ table: entry.table, column: '' });
		}
	}
	return queue;
}

function teSiteUpdateTotals(state) {
	$('#te-analization-result .te-site-total').text(teFormatInt(state.globalCharCount));
	$('#te-analization-result .te-js-site-translate-all').toggleClass('te-hidden', teSiteQueueAll().length === 0);
	if (state.target) {
		$('#te-analization-result .te-site-chars-head').text(teT('te_site_col_chars_lang', { code: state.target.code }));
	}
}

function teSiteJobLabel(job) {
	return job.column ? job.table + '.' + job.column : job.table;
}

// An operation ends: the panel gets its final state and the buttons work again.
function teSiteEndRun(result) {
	teProgress.finish('#te-site-progress', result);
	teSiteState.running = false;
	teSiteState.mode = null;
	teSiteTranslate.running = false;
	teSiteSetBusy(false);
}

function teSiteFail(response) {
	teAlerts.fromResponse('#te-site-alerts', response);
	teSiteEndRun('error');
}

function teSiteStartAnalysis() {
	if (teSiteIsRunning()) {
		return;
	}
	teAlerts.clear('#te-site-alerts');
	var filters = teSiteFilters();
	teSiteState = teSiteNewState(filters);
	teSiteState.tableTotal = filters.tables.length || $('#te-analization-table option').length;
	teSiteState.running = true;
	teSiteState.mode = 'analyze';
	$('#te-analization-result').empty().append(teSiteBuildTableSkeleton());
	teSiteSetBusy(true);
	teProgress.start('#te-site-progress', teT('te_site_analyzing'));
	teSiteRequestAnalysis(null);
}

// next: { table, column, offset } of the batch before, or null for the first batch. A value goes in only if it is true.
function teSiteRequestAnalysis(next) {
	var state = teSiteState;
	var params = teSiteFilterParams(state.filters);
	if (next && next.table) {
		params.continue_after_table = next.table;
	}
	if (next && next.column) {
		params.continue_after_column = next.column;
	}
	if (next && next.offset) {
		params.continue_offset = next.offset;
	}
	state.requestNumber++;
	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/analize'
		+ '&token=' + getOcToken(),
		params
	).done(function(data) {
		teSiteOnAnalysisBatch(state, data);
	}).fail(function(xhr) {
		console.log(xhr);
		teSiteFail(xhr);
	});
}

function teSiteOnAnalysisBatch(state, data) {
	if (!data || !data.success) {
		console.log(data);
		teSiteFail(data);
		return;
	}
	if (data.target && !state.target) {
		state.target = data.target;
	}
	state.globalCharCount += Number(data.globalCharCount) || 0;
	state.timeInSec += Number(data.timeInSec) || 0;
	var tables = $.isArray(data.tables) ? data.tables : [];
	for (var i = 0; i < tables.length; i++) {
		teSiteMergeTable(state, tables[i]);
	}
	teSiteUpdateTotals(state);

	var seen = state.order.length;
	var total = state.tableTotal;
	var n = total > 0 ? Math.min(seen, total) : seen;
	// Tables with 0 characters are not in the response, so the count can stay below the total until the end.
	if (data.done && total > 0) {
		n = total;
	}
	// lastProcessed* names the position before the cut, so show the last table of this batch.
	var current = tables.length && tables[tables.length - 1] ? tables[tables.length - 1].table : (data.lastProcessedTable || '');
	teProgress.update('#te-site-progress', {
		percent: total > 0 ? n / total * 100 : null,
		counters: [
			teT('te_site_cnt_current', { current: current }),
			teT('te_site_cnt_table', { n: n, total: total > 0 ? total : seen }),
			teT('te_site_cnt_request', { request: state.requestNumber }),
			teT('te_site_cnt_chars', { chars: teFormatInt(state.globalCharCount) }),
			teT('te_site_cnt_time', { sec: state.timeInSec.toFixed(1) })
		]
	});

	if (data.done) {
		teSiteEndRun('done');
	} else if (state.stopRequested) {
		teSiteEndRun('stopped');
	} else if (state.filters.deep) {
		teSiteRequestAnalysis({ table: data.lastProcessedTable, column: data.lastProcessedColumn, offset: data.lastProcessedOffset });
	} else if (tables.length && tables[tables.length - 1]) {
		teSiteRequestAnalysis({ table: tables[tables.length - 1].table });
	} else {
		teSiteEndRun('done');
	}
}

// ---- Detail view of a column (in the module modal) ----

function teSiteSetModalTitle(text) {
	var titleEl = $('#translateExpertModalLabel');
	if (titleEl.attr('data-te-original') === undefined) {
		titleEl.attr('data-te-original', titleEl.text());
	}
	titleEl.text(text);
}

// Returns the content of the detail modal as a DOM node. All values go in as text.
function teRenderSiteDetail(data) {
	var root = teEl('div', { 'class': 'te-app' });
	root.append(teEl('p', { 'class': 'te-muted' }, teT('value_analization_mode_' + data.mode)));
	var rows = $.isArray(data.rows) ? data.rows : [];
	if (!rows.length) {
		root.append(teEl('p', {}, teT('te_site_detail_none')));
	} else {
		var idColumns = $.isArray(data.idColumns) ? data.idColumns : [];
		var table = teEl('table', { 'class': 'te-table' });
		var headRow = teEl('tr');
		for (var c = 0; c < idColumns.length; c++) {
			headRow.append(teEl('th', {}, idColumns[c]));
		}
		headRow.append(teEl('th', {}, teT('te_site_detail_source', { code: data.languageFrom ? data.languageFrom.code : '' })));
		headRow.append(teEl('th', {}, teT('te_site_detail_target', { code: data.languageTo ? data.languageTo.code : '' })));
		table.append(teEl('thead').append(headRow));
		var body = teEl('tbody');
		for (var r = 0; r < rows.length; r++) {
			var row = rows[r] || {};
			var tr = teEl('tr');
			for (var k = 0; k < idColumns.length; k++) {
				tr.append(teEl('td', {}, row.ids && typeof row.ids[idColumns[k]] !== 'undefined' ? row.ids[idColumns[k]] : ''));
			}
			var textFrom = row.textFrom === null || typeof row.textFrom === 'undefined' ? '' : String(row.textFrom);
			var textTo = row.textTo === null || typeof row.textTo === 'undefined' ? '' : String(row.textTo);
			var fromCell = teEl('td').append(teEl('span', { 'class': 'te-table__text' }, textFrom));
			if (Number(row.count) > 1) {
				fromCell.append(teEl('span', { 'class': 'te-muted' }, ' \u00d7' + Number(row.count)));
			}
			var toCell = teEl('td');
			if (textTo === '') {
				toCell.append(teEl('span', { 'class': 'te-badge te-badge--muted' }, teT('te_site_empty')));
			} else {
				toCell.append(teEl('span', { 'class': 'te-table__text' }, textTo));
				if (textTo === textFrom) {
					toCell.append(document.createTextNode(' '));
					toCell.append(teEl('span', { 'class': 'te-badge te-badge--warn' }, teT('te_site_identical')));
				}
			}
			tr.append(fromCell);
			tr.append(toCell);
			body.append(tr);
		}
		table.append(body);
		root.append(teEl('div', { 'class': 'te-table-wrap' }).append(table));
	}
	if (data.hasMore) {
		root.append(teEl('p', { 'class': 'te-muted' }, teT('te_site_detail_more')));
	}
	return root;
}

function teSiteShowDetail(table, column) {
	teAlerts.clear('#te-site-alerts');
	var filters = teSiteState.filters || teSiteFilters();

	$('.stop_modal_process').hide();
	$('.close_modal').removeAttr('disabled');
	showTranslateExpertModal(teEl('div', {}, teT('te_site_detail_loading')));
	teSiteSetModalTitle(table + '.' + column);

	var fail = function(response) {
		console.log(response);
		teAlerts.fromResponse('#te-site-alerts', response);
		hideTranslateExpertModal();
	};
	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/analizeTableDetail'
		+ '&token=' + getOcToken(),
		teSitePostParams(filters, table, column)
	).done(function(data) {
		if (!data || !data.success) {
			fail(data);
			return;
		}
		$('#translateExpertModal .modal-body').empty().append(teRenderSiteDetail(data));
	}).fail(fail);
}

// ---- Table translation ----

function teSiteTranslateStart(queue, all) {
	if (teSiteIsRunning() || !queue.length) {
		return;
	}
	var lang = teSiteState.target ? teSiteState.target.name : $.trim($('#te-langbar-to option:selected').text());
	var question = all
		? teT('te_site_translate_all_confirm', { lang: lang })
		: teT('te_site_translate_confirm', { table: teSiteJobLabel(queue[0]), lang: lang });
	if (!confirm(question)) {
		return;
	}
	teAlerts.clear('#te-site-alerts');
	teSiteTranslate = {
		queue: queue.slice(0),
		current: null,
		translatedCharCount: 0,
		requestNumber: 0,
		running: true,
		stopRequested: false,
		filters: teSiteState.filters || teSiteFilters(),
		jobChars: 0
	};
	teSiteState.running = true;
	teSiteState.mode = 'translate';
	teSiteState.stopRequested = false;
	teSiteSetBusy(true);
	teProgress.start('#te-site-progress', teT('te_site_translating', { table: teSiteJobLabel(queue[0]) }));
	teSiteTranslateNext();
}

function teSiteTranslateEnd(result) {
	teSiteEndRun(result);
	showTranslatedCharCount();
}

function teSiteTranslateNext() {
	var t = teSiteTranslate;
	if (!t.queue.length) {
		teSiteTranslateEnd('done');
		return;
	}
	if (t.stopRequested) {
		teSiteTranslateEnd('stopped');
		return;
	}
	t.current = t.queue[0];
	t.jobChars = 0;
	teProgress.setTitle('#te-site-progress', teT('te_site_translating', { table: teSiteJobLabel(t.current) }));
	teSiteTranslateRequest();
}

function teSiteTranslateRequest() {
	var t = teSiteTranslate;
	var job = t.current;
	t.requestNumber++;
	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/translateTable'
		+ '&token=' + getOcToken(),
		teSitePostParams(t.filters, job.table, job.column)
	).done(function(data) {
		if (!data || !data.success) {
			console.log(data);
			teAlerts.fromResponse('#te-site-alerts', data);
			teSiteTranslateEnd('error');
			return;
		}
		var chars = Number(data.translatedCharCount) || 0;
		t.translatedCharCount += chars;
		t.jobChars += chars;
		teProgress.update('#te-site-progress', {
			percent: null,
			counters: [
				teT('te_site_cnt_current', { current: teSiteJobLabel(job) }),
				teT('te_site_cnt_translated', { chars: teFormatInt(t.translatedCharCount) }),
				teT('te_site_cnt_request', { request: t.requestNumber })
			]
		});
		if (data.done) {
			teProgress.log('#te-site-progress', teT('te_site_log_table_done', { table: teSiteJobLabel(job), chars: teFormatInt(t.jobChars) }));
			teSiteMarkDone(job);
			t.queue.shift();
			teSiteTranslateNext();
		} else if (t.stopRequested) {
			teSiteTranslateEnd('stopped');
		} else {
			teSiteTranslateRequest();
		}
	}).fail(function(xhr) {
		console.log(xhr);
		teAlerts.fromResponse('#te-site-alerts', xhr);
		teSiteTranslateEnd('error');
	});
}

function teSiteMarkDone(job) {
	var entry = Object.prototype.hasOwnProperty.call(teSiteState.tables, job.table) ? teSiteState.tables[job.table] : null;
	var row = !entry ? null : (job.column ? entry.columnRows[job.column] : entry.row);
	if (row) {
		row.find('.te-site-badge').first().empty().append(teEl('span', { 'class': 'te-badge te-badge--ok' }, teT('te_site_done')));
	}
}

// ---- Handlers of the tab ----

function teSiteInit() {
	var app = $('#te-site-app');
	app.off('.teSite');
	app.on('click.teSite', '#te-begin-analization', teSiteStartAnalysis);
	app.on('click.teSite', '.te-js-stop', function() {
		if (!teSiteIsRunning()) {
			return;
		}
		if (teSiteState.mode === 'translate') {
			teSiteTranslate.stopRequested = true;
		} else {
			teSiteState.stopRequested = true;
		}
		$(this).prop('disabled', true);
	});
	app.on('click.teSite', '.te-js-site-toggle', function() {
		var btn = $(this);
		var expanded = btn.attr('aria-expanded') === 'true';
		btn.attr('aria-expanded', expanded ? 'false' : 'true');
		var table = btn.closest('tr').attr('data-table');
		var entry = Object.prototype.hasOwnProperty.call(teSiteState.tables, table) ? teSiteState.tables[table] : null;
		if (entry) {
			for (var i = 0; i < entry.columnOrder.length; i++) {
				entry.columnRows[entry.columnOrder[i]].toggleClass('te-hidden', expanded);
			}
		}
	});
	app.on('click.teSite', '.te-js-site-detail', function() {
		teSiteShowDetail($(this).attr('data-table'), $(this).attr('data-column'));
	});
	app.on('click.teSite', '.te-js-site-translate', function() {
		teSiteTranslateStart([{ table: $(this).attr('data-table'), column: $(this).attr('data-column') || '' }], false);
	});
	app.on('click.teSite', '.te-js-site-translate-all', function() {
		teSiteTranslateStart(teSiteQueueAll(), true);
	});
}
