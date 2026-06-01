if (document.readyState == 'loading') {
  document.addEventListener('DOMContentLoaded', initTranslateExpert);
} else {
  initTranslateExpert();
}

function initTranslateExpert() {

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

function doSaveLocalizationExpert(el, callback = null)
{
	var headerEl = $(el).parent().parent().prev();
	var pathTo = headerEl.data('path-to');
	console.log(pathTo);

	var fromEl = $(el).parent().parent().next().find('.localization-analize-file.from pre');
	var langFromId = fromEl.data('lang-from');
	console.log(langFromId);

	var toEl = $(el).parent().parent().next().find('.localization-analize-file.to textarea');
	var langToId = toEl.data('lang-to');
	console.log(langToId);
	var fileContent = toEl[0].value;

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/saveLocalizationFile&token=' + getOcToken(),
		{ fileContent: fileContent, langId: langToId, path: pathTo }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, toEl);
			return;
		}

		showTranslateExpertInfoMessage('Saved!', toEl);
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
	var pathTo = headerEl.data('path-to');
	console.log(pathTo);

	var fromEl = $(el).parent().parent().next().find('.localization-analize-file.from pre');
	var langFromId = fromEl.data('lang-from');
	console.log(langFromId);

	var toEl = $(el).parent().parent().next().find('.localization-analize-file.to textarea');
	var langToId = toEl.data('lang-to');
	console.log(langToId);

	document.localizationAnalizationResult.forEach((analizationResult) =>
	{
		if (analizationResult.pathTo == pathTo)
		{
			console.log(analizationResult);
			var toTranslate = analizationResult.toTranslate;
			doTranslateLocalization(langFromId, langToId, toTranslate, toEl, callback);
		}
	});
}

function doTranslateLocalizationAllExpert(index = 0)
{
	var allButtons = $('.translate_localization_button');
	if (index < allButtons.length)
	{
		var btn = allButtons[index];
		var headerEl = $(btn).parent().parent().prev();
		var pathTo = headerEl.data('path-to');

		showTranslateExpertModal('Translation of localization file {currIndex} of {count}: {path}.'
			.replace('{currIndex}', index + 1)
			.replace('{count}', allButtons.length)
			.replace('{path}', pathTo)
		);

		doTranslateLocalizationExpert(btn, function(){
			doSaveLocalizationExpert(btn, function(){
				doTranslateLocalizationAllExpert(++index);
			});
		});
	}
	else
	{
		showTranslateExpertModal('Translation of localization file {currIndex} of {count}: Done!'
			.replace('{currIndex}', allButtons.length)
			.replace('{count}', allButtons.length)
			.replace('{path}', pathTo)
		);
	}
}

function doTranslateLocalization(langFromId, langToId, toTranslate, toEl, callback = null)
{
	/**/
	var textArr = [];
	Object.entries(toTranslate).forEach(([key, value]) => {
		valueStr = value.text.trim();
		if (valueStr)
			textArr.push(valueStr);
	});

	console.log(textArr);

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/translate&token=' + getOcToken(),
		{ textArr: JSON.stringify(textArr), source: langFromId, target: langToId, format: 'html' }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, toEl);
			return;
		}

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
		if (translatedResult.indexOf('Массовый автоматический перевод текстов, товаров, категорий, статей и тд с Google translate API') === -1)
			translatedResult = translatedResult + "/*\nTranslated with\nМассовый автоматический перевод текстов, товаров, категорий, статей и тд с Google translate API\nhttps://translator.codeguild.com.ua/\n*/";

		var i = 0;
		Object.entries(toTranslate).forEach(([key, value]) => {
			var valueStr = value.text.trim();
			var translatedStr = valueStr ? data.texts[i++] : '';
			translatedStr = translatedStr.replaceAll('"', '\\"');
			translatedResult += "\n$_['" + key + "'] = \"" + translatedStr + "\";";
		});

		toEl.text(translatedResult);
		toEl[0].scrollTop = toEl[0].scrollHeight;

		showTranslatedCharCount();

		if (callback)
			callback();
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
	var route = getParameter('route');
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
	var els = $([
			"textarea[id^='description_'][id$='_description']"
		].join(','))
		.filter(function(){
			return !$(this).attr('name');
		});

	//console.log(els);
	els.each(function( index ) {
		var nameRegex = /^(.*)\_(\d+)\_(.*)$/;
		var nameMatch = nameRegex.exec($(this).attr('id'));
		var newName = nameMatch[1] + "_te_vh[" + nameMatch[2] + "][" + nameMatch[3] + "_te_vh]";
		$(this).attr('name', newName);
	});
	//console.log(els);
}

function updateHandyProductManagerElements() {
	var route = getParameter('route');
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
		el = $('#te-analization-mode')[0];

	$('.alert.alert-danger').remove();
	$(el).before('<div class="alert alert-danger" style="display: none;"><i class="fa fa-exclamation-circle"></i> ' + error + '</div>');
	//$('.alert-danger').slideDown().delay(100000).slideUp();
	$('.alert-danger').slideDown();

	$('.stop_modal_process').hide();
	$('.close_modal').removeAttr('disabled');
	$('#translateExpertModal').modal('hide');
}

function showTranslateExpertInfoMessage(message, el) {
	if (!el)
		el = $('#te-analization-mode')[0];

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

function showTranslateExpertModal(message) {
	$('#translateExpertModal .modal-body').html(message);

	$('#translateExpertModal').modal({
		backdrop: 'static',
		keyboard: false
	});
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

function analizeTableDetail(langIdFrom, mode, tableName, textColumnName, langIdTo, product_status, product_quantity, product_category_id, stock_status) {

	$('.alert.alert-danger').remove();
	document.stopTranslationModelProcess = false;
	$('#statusLog').html('');

	$('.stop_modal_process').hide();
	$('.close_modal').removeAttr('disabled');

	showTranslateExpertModal('Preparing detail info ...');

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/analizeTableDetail&token=' + getOcToken(),
		{ langIdFrom: langIdFrom, mode: mode, tableName: tableName, textColumnName: textColumnName , langIdTo: langIdTo,
			product_status: product_status, product_quantity: product_quantity, product_category_id: product_category_id, stock_status: stock_status }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		showTranslateExpertModal(data.result);
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
	});
}


function doTranslateTableExpert(langIdFrom, mode, tableName, textColumnName, langIdTo, product_status, product_quantity, product_category_id, stock_status, confirmationMessage) {

	if (!confirm(confirmationMessage))
		return;

	$('.alert.alert-danger').remove();
	document.stopTranslationModelProcess = false;
	$('#statusLog').html('');

	$('.stop_modal_process').show();
	$('.close_modal').attr('disabled', 1);

	document.tablesToTranslate = (tableName == 'all')
		? $.map( $('.et_table').toArray(), function( el, i ) { return el.innerHTML; })
		: [ tableName ];

	showTranslateExpertModal(getTranslateExpertStatisticHtml('', document.js_const_in_progress_status, 0));

	doTranslateTableExpertRecursive(langIdFrom, mode, document.tablesToTranslate[0], textColumnName, langIdTo, product_status, product_quantity, product_category_id, stock_status);
}

function doTranslateTableExpertRecursive(langIdFrom, mode, tableName, textColumnName, langIdTo, product_status, product_quantity, product_category_id, stock_status) {

	$.post(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/translateTable&token=' + getOcToken(),
		{ langIdFrom: langIdFrom,  mode: mode, tableName: tableName, textColumnName: textColumnName, langIdTo: langIdTo, product_status: product_status, product_quantity: product_quantity, product_category_id, stock_status: stock_status }
	).done(function(data) {
		console.log(data);
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		if (document.stopTranslationModelProcess)
			return;

		showTranslatedCharCount();

		var translatedCharCount = data.translatedCharCount
			+ (parseInt($('#statusCurrent .statusTranslatedCharCount').html()) ? parseInt($('#statusCurrent .statusTranslatedCharCount').html()) : 0);

		if (data.done)
		{
			showTranslateExpertModal(getTranslateExpertStatisticHtml('', document.js_const_done_status, translatedCharCount));
			document.tablesToTranslate.shift();
			if (document.tablesToTranslate.length > 0)
			{
				showTranslateExpertModal(getTranslateExpertStatisticHtml($('#statusCurrent').html(), document.js_const_in_progress_status, 0));
				doTranslateTableExpertRecursive(langIdFrom, mode, document.tablesToTranslate[0], textColumnName, langIdTo, product_status, product_quantity, product_category_id, stock_status);
			}
			else
			{
				$('.stop_modal_process').hide();
				$('.close_modal').removeAttr('disabled');
				//$('#translateExpertModal').modal('hide');
			}
		}
		else
		{
			showTranslateExpertModal(getTranslateExpertStatisticHtml('', document.js_const_in_progress_status, translatedCharCount));
			doTranslateTableExpertRecursive(langIdFrom, mode, tableName, textColumnName, langIdTo, product_status, product_quantity, product_category_id, stock_status);
		}
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
	});

}

function getTranslateExpertStatisticHtml(additionalHistoryLog, statusStr, translatedCharCount) {
	return document.js_const_statistic_html
		.replaceAll('{historyLog}',
			($('#statusLog').length > 0 ? $('#statusLog').html() + (additionalHistoryLog ? '<br>' : '') : '')
			+ additionalHistoryLog)
		.replaceAll('{table}', document.tablesToTranslate[0])
		.replaceAll('{status}', statusStr)
		.replaceAll('{translatedCharCount}', translatedCharCount);
}

function confirmStopTranslationModelProcess(confirmStr) {
	var res = confirm(confirmStr);
	if (res)
	{
		document.stopTranslationModelProcess = true;
		$('#translateExpertModal').modal('hide');
	}
	return res;
}

function makeLocalizationAnalizeRequest(nextFileIndex = 0)
{
	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');
	var langIdFrom = $('#te-localization-language-from').val();
	var langIdTo = $('#te-localization-language-to').val();

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/analizeLocalization'
		+ '&token=' + token
		+ '&langIdFrom=' + langIdFrom
		+ '&langIdTo=' + langIdTo
		+ '&nextFileIndex=' + nextFileIndex
	).done(function(data) {
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, $('#te-localization-language-from')[0]);
			$('#te-analization-result').html(data);
			return;
		}

		console.log(data);
		if (nextFileIndex == 0)
		{
			$('#te-localization-analization-result').html(data.html);
			document.localizationAnalizationResult = data.analize;
		}
		else
			document.localizationAnalizationResult = document.localizationAnalizationResult.concat(data.analize);

		$table = $('.localization-analize-table');
		$table.html($table.html() + data.detailsHtml);
		if(!data.done)
		{
			makeLocalizationAnalizeRequest(data.nextFileIndex);
		}

	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, $('#te-localization-language-from')[0]);
		$('#te-analization-result').html(data);
	});

}

function makeAnalizeRequest(lastProcessedTable = null, lastProcessedColumn = null, lastProcessedOffset = null) {

	if (document.stopTranslationModelProcess) {
		$('#te-begin-analization').prop('disabled', false);
		return;
	}

	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');
	var langIdFrom = $('#te-analization-language-from').val();
	var langIdTo = $('#te-analization-language-to').val();
	var mode = $('#te-analization-mode').val();
	var table = $('#te-analization-table').val();
	var product_status = $('#te-analization-product-status-filter').is(':checked');
	var product_quantity = $('#te-analization-product-quantity-filter').is(':checked');
	var product_category_id = $('#te-analization-product-category-id-filter').val();
	var stock_status = $('#te-analization-product-stock-status-filter').val();
	var isDeep = $('#te-analization-table-deep').is(':checked');

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/analize'
		+ '&token=' + token
		+ '&langIdFrom=' + langIdFrom
		+ '&langIdTo=' + langIdTo
		+ '&mode=' + mode
		+ '&table=' + table
		+ (product_status ? '&product_status=1' : '')
		+ (product_quantity ? '&product_quantity=1' : '')
		+ (product_category_id ? '&product_category_id=' + product_category_id : '')
		+ (stock_status ? '&stock_status=' + stock_status : '')
		+ (lastProcessedTable ? '&continue_after_table=' + lastProcessedTable : '')
		+ (lastProcessedColumn ? '&continue_after_column=' + lastProcessedColumn : '')
		+ (lastProcessedOffset ? '&continue_offset=' + lastProcessedOffset : '')
	).done(function(data) {
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		console.log(data);
		/*
		$.each( data.result, function( tableKey, tableResult ) {
			console.log(tableKey);
			console.log(tableResult);
			console.log('');
			$.each( tableResult.columns, function( columnKey, columnResult ) {
				console.log(columnKey);
				console.log(columnResult);
				console.log('');
			});
		});
		*/

		if ($('#analizeBody').length == 0)
		{
			var bodyHtml = '';
			$.each( data.result, function( tableKey, tableResult ) {
				bodyHtml = bodyHtml + tableResult.headerHTML;
				$.each( tableResult.columns, function( columnKey, columnResult ) {
					bodyHtml = bodyHtml + columnResult.html;
				});
			});

			$('#te-analization-result').html(
				'<div id="analizeHeadInfo" class="container">' + data.analizeHeadInfo + '</div>' +
				'<div id="analizeBody" class="container">' + bodyHtml + '</div><br><br>' +
				'<div id="timeInSecInfo" class="container">' + data.timeInSecInfo + '</div>'
			);
		}
		else
		{
			var bodyHtml = '';
			$.each( data.result, function( tableKey, tableResult ) {

				var tableResultId = '#et_' + tableResult.table;
				var needToAddBr = ($(tableResultId).length == 0);
				if ($(tableResultId).length == 0)
					bodyHtml = bodyHtml + tableResult.headerHTML;
				else
				{
					var tableGlobalCharCountId = '#' + tableResult.table + '_globalCharCount';
					$(tableGlobalCharCountId).html(parseInt($(tableGlobalCharCountId).html()) + parseInt(tableResult.tableCharCount));

					$.each( tableResult.languageCharCounts, function( tableLangId, tableLangValue ) {
						var tableLangCharCountId = '#' + tableResult.table + '_CharCount_' + tableLangValue['code'];
						$(tableLangCharCountId).html(parseInt($(tableLangCharCountId).html()) + parseInt(tableLangValue['char_count']));
					});
				}

				$.each( tableResult.columns, function( columnKey, columnResult ) {

					var columnResultId = '#et_' + tableResult.table + '_' + columnResult.column;
					if ($(columnResultId).length == 0)
						bodyHtml = bodyHtml + columnResult.html;
					else
					{
						var columnGlobalCharCountId = '#' + columnResult.table + '_' + columnResult.column + '_globalCharCount';
						$(columnGlobalCharCountId).html(parseInt($(columnGlobalCharCountId).html()) + parseInt(columnResult.columnCharCount));

						$.each( columnResult.languageCharCounts, function( columnLangId, columnLangValue ) {
							var columnLangCharCountId = '#' + tableResult.table + '_' + columnResult.column + '_CharCount_' + columnLangValue['code'];
							$(columnLangCharCountId).html(parseInt($(columnLangCharCountId).html()) + parseInt(columnLangValue['char_count']));
						});
					}
				});
			});
			$('#analizeBody').html($('#analizeBody').html() + bodyHtml);


			$('#globalCharCount').html(parseInt($('#globalCharCount').html()) + parseInt(data.globalCharCount));
			$('#timeInSec').html((parseFloat($('#timeInSec').html()) + parseFloat(data.timeInSec)).toFixed(4));
			for (var id in data.globalLanguageCharCounts)
			{
				if (data.globalLanguageCharCounts.hasOwnProperty(id))
				{
					var langCountObj = data.globalLanguageCharCounts[id];
					var langElementId = 'globalLangCharCount' + langCountObj.code;
					$('#' + langElementId).html(parseInt($('#' + langElementId).html()) + parseInt(langCountObj.char_count));
				}
			}

			if (data.result.length > 0)
			{
				$('#analization_current_table').html(data.result[data.result.length - 1].table);
				$('#analization_request_number').html(parseInt($('#analization_request_number').html()) + 1);
			}
		}

		showTranslatedCharCount();

		if (data.done)
		{
			$('#te-begin-analization').prop('disabled', false);

			$('.close_modal').removeAttr('disabled');
			$('#translateExpertModal').modal('hide');
		}
		else
		{
			if (isDeep)
			{
				makeAnalizeRequest(data.lastProcessedTable, data.lastProcessedColumn, data.lastProcessedOffset);
			}
			else
			{
				var lastProcessedTable = data.result[data.result.length - 1].table;
				makeAnalizeRequest(lastProcessedTable);
			}
		}
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
		$('#te-analization-result').html(data);
	});
}

function makeRemovingDataFromDbRequest() {

	var urlParams = new URLSearchParams(window.location.search);
	var token = urlParams.get('token');
	var langIdTo = $('#te-analization-language-to').val();
	var table = $('#te-analization-table').val();
	var product_status = $('#te-analization-product-status-filter').is(':checked');
	var product_quantity = $('#te-analization-product-quantity-filter').is(':checked');
	var product_category_id = $('#te-analization-product-category-id-filter').val();
	var stock_status = $('#te-analization-product-stock-status-filter').val();

	$.get(document.location.href.split("?")[0] + '?route=' + document.js_const_module_prefix + '/client_translate_expert/removeDataFromDb'
		+ '&token=' + token
		+ '&langIdTo=' + langIdTo
		+ '&table=' + table
		+ (product_status ? '&product_status=1' : '')
		+ (product_quantity ? '&product_quantity=1' : '')
		+ (product_category_id ? '&product_category_id=' + product_category_id : '')
		+ (stock_status ? '&stock_status=' + stock_status : '')
	).done(function(data) {
		if (!data.success)
		{
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		console.log(data);

		$('#te-analization-result').html(
			'<div id="analizeHeadInfo" class="container"><h2>' + data.headerHTML + '</h2></div><br><br>' +
			'<div id="analizeBody" class="container">' + data.body + '</div><br><br>' +
			'<div id="timeInSecInfo" class="container">' + data.timeInSecInfo + '</div>'
		);

		$('#te-begin-analization').prop('disabled', false);

		$('.close_modal').removeAttr('disabled');
		$('#translateExpertModal').modal('hide');
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
		$('#te-analization-result').html(data);
	});
}

function showTranslatedCharCount()
{
	if (!document.js_const_client_translate_expert_show_char_count_is_enabled)
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
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		console.log(data);

		if ($('#te-translated-char-count-info').length == 0)
		{
			var parentElement = $('#header > .nav.pull-right');
			if (parentElement.length == 0)
			{
				parentElement = $('#header > .container-fluid > .nav.navbar-right');
			}
			parentElement.prepend('<li class="dropdown" id="te-translated-char-count-info">' + data.info + '</li>');
		}
		else
		{
			$('#te-translated-char-count-info').html(data.info);
		}
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
	});
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
			showTranslateExpertErrorMessage(data, null);
			return;
		}

		console.log(data);

		showTranslatedCharCount();
	}).fail(function(data) {
		console.log(data);
		showTranslateExpertErrorMessage(data, null);
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

	$('#te-begin-analization').click(function() {
		$('#te-begin-analization').prop('disabled', true);
		$('#te-analization-result').html('');
		document.stopTranslationModelProcess = false;
		$('.close_modal').attr('disabled', 1);
		showTranslateExpertModal(document.js_const_analization_process_is_started);

		makeAnalizeRequest();
	});

	$('#te-begin-removing_data_from_db').click(function()
	{
		var confirmationString = document.js_const_entry_remove_translation_records_from_db_confirmation
			.replace('{langCode}', $('#te-analization-language-to').find("option:selected").text());
		if (confirm(confirmationString))
		{
			$('#te-analization-result').html('');
			showTranslateExpertModal(document.js_const_entry_removing_data_from_db_process_is_started);

			makeRemovingDataFromDbRequest();
		}
	});

	$('#te-begin-localization-analization').click(function() {
		makeLocalizationAnalizeRequest();
	});

	showTranslatedCharCount();
}
