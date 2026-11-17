// Unit tests for the render helpers of the new admin design in client_translate_expert.js
// (teT, teIcon, teIconEl, teEl, teText, teBs, teAlerts, the char count helpers and the removal log).
//
// Usage:
//   cd tests/js && npm ci && node test_client_translate_expert_js_helpers.js
//
// The script loads the real JS file in jsdom two times: with jQuery 3.7.1 (OC 4.x, from npm) and with
// jQuery 1.7.1 (OC 1.5 admin pages, vendor/jquery-1.7.1.min.js, the file of OpenCart 1.5.6.4, MIT license).
// No browser, no OpenCart, no network. An exception in a check fails that check only.
'use strict';

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const JS_FILE = path.join(__dirname, '../../sources/upload/admin/view/javascript/client_translate_expert.js');
const JQUERY_VERSIONS = [
	{ label: 'jQuery 3.7.1', file: path.join(__dirname, 'node_modules/jquery/dist/jquery.js') },
	{ label: 'jQuery 1.7.1', file: path.join(__dirname, 'vendor/jquery-1.7.1.min.js') }
];

let passed = 0;
let failed = 0;

// cond is a function, so an exception in it fails this check and the next checks still run.
function check(message, cond) {
	let result = false;
	let error = null;
	try {
		result = cond() === true;
	} catch (e) {
		error = e;
	}
	if (result) {
		console.log(' [PASS] ' + message);
		passed++;
	} else {
		console.log(' [FAIL] ' + message + (error ? ' (exception: ' + error.message + ')' : ''));
		failed++;
	}
}

// withBs5: add a stub of the BS5 global "bootstrap" that records the calls in window.teCalls.
// Without it, the page has no Bootstrap and no jQuery plugins (the case of an OC 1.5 page other than the module page).
function makeWindow(jqueryFile, withBs5, bodyHtml) {
	const dom = new JSDOM(
		'<!doctype html><html><head></head><body>' + (bodyHtml || '<div class="te-app">'
		+ '<div id="te-analization-result"><p id="keep">old</p></div><div id="al" class="te-alerts"></div><div id="col" style="display:none">c</div><a id="tab" href="#x">t</a>'
		+ '</div>') + '</body></html>',
		{ runScripts: 'outside-only' }
	);
	const w = dom.window;
	w.eval(fs.readFileSync(jqueryFile, 'utf8'));
	w.teCalls = [];
	if (withBs5) {
		const stub = function(name) {
			const instance = {
				show: function() { w.teCalls.push(name + '.show'); },
				hide: function() { w.teCalls.push(name + '.hide'); },
				toggle: function() { w.teCalls.push(name + '.toggle'); }
			};
			return { getOrCreateInstance: function() { return instance; }, getInstance: function() { return instance; } };
		};
		w.bootstrap = { Modal: stub('Modal'), Tab: stub('Tab'), Collapse: stub('Collapse') };
	}
	// The existing init code of the file also runs here. An exception in it fails the run.
	w.eval(fs.readFileSync(JS_FILE, 'utf8'));
	return w;
}

function xhr(fields) {
	return Object.assign({ readyState: 4, status: 200, statusText: '', responseText: '', getResponseHeader: function() { return null; } }, fields);
}

function testTeT(w) {
	console.log('\nteT:');
	check('returns the key when no object exists', () => w.teT('missing_key') === 'missing_key');
	w.document.te_i18n_global = { te_global_reset: 'Reset G', only_global: 'G', a: 'Hello {name} {name} {x}' };
	w.document.te_i18n = { te_global_reset: 'Reset M', n: 5 };
	check('te_i18n wins over te_i18n_global', () => w.teT('te_global_reset') === 'Reset M');
	check('falls back to te_i18n_global', () => w.teT('only_global') === 'G');
	check('replaces all placeholders and keeps unknown ones', () => w.teT('a', { name: 'Bob' }) === 'Hello Bob Bob {x}');
	check('a null parameter keeps the placeholder', () => w.teT('a', { name: null }) === 'Hello {name} {name} {x}');
	check('a number parameter becomes text', () => w.teT('a', { name: 0 }) === 'Hello 0 0 {x}');
	check('a non-string value falls back to the key', () => w.teT('n') === 'n');
	check('a prototype key is not used', () => w.teT('toString') === 'toString');
}

function testDomHelpers(w) {
	console.log('\nteEl, teText, teIconEl:');
	check('teEl puts text in as text, and attributes as attributes', () => {
		const el = w.teEl('td', { 'class': 'a b', title: '<b>x</b>', skip: null }, '<img src=x onerror=alert(1)>');
		return el[0].outerHTML === '<td class="a b" title="<b>x</b>">&lt;img src=x onerror=alert(1)&gt;</td>';
	});
	check('teText with null gives an empty text', () => w.teText(w.$('<span>'), null).text() === '');
	check('teText with 0 gives "0"', () => w.teText(w.$('<span>'), 0).text() === '0');
	check('teIconEl is aria-hidden', () => w.teIconEl('times').attr('aria-hidden') === 'true');
}

function testNoBootstrap(w) {
	console.log('\nNo Bootstrap (OC 1.5 page other than the module page):');
	check('teIsBs5 is false', () => w.teIsBs5() === false);
	check('teIcon gives the FA 4 classes', () => w.teIcon('save') === 'fa fa-save');
	check('the adapter does not throw', () => {
		w.teBs.tab('#tab');
		w.teBs.modal('#tab', 'show');
		w.teBs.collapse('#nothing', 'toggle');
		return true;
	});
	check('collapse fallback shows the element', () => {
		w.teBs.collapse('#col', 'show');
		return w.$('#col').css('display') !== 'none';
	});
	check('collapse fallback hides the element', () => {
		w.teBs.collapse('#col', 'hide');
		return w.$('#col').css('display') === 'none';
	});
}

function testAlerts(w) {
	console.log('\nteAlerts:');
	w.document.te_i18n = { te_alert_details: 'Details', te_alert_close: 'Close', te_alert_not_json: 'Not JSON.' };
	w.document.te_i18n_global = { te_global_error_internal: 'Internal' };
	const message = (a) => a.find('.te-alert__message').text();
	const details = (a) => a.find('.te-alert__details').text();

	let a = null;
	check('a non-JSON string gives the not-JSON message', () => {
		a = w.teAlerts.fromResponse('#al', '<html><title>Login</title><script>alert(1)</script></html>');
		return message(a) === 'Not JSON.';
	});
	check('the details hold the response as text, not as HTML', () => a.find('script').length === 0 && details(a).indexOf('<script>') >= 0);
	check('the details are collapsed', () => a.find('.te-alert__details').hasClass('te-hidden'));
	check('the details button opens the details', () => {
		a.find('.te-js-alert-details').trigger('click');
		return !a.find('.te-alert__details').hasClass('te-hidden') && a.find('.te-js-alert-details').attr('aria-expanded') === 'true';
	});
	check('the close button removes the alert', () => {
		a.find('.te-js-alert-close').trigger('click');
		return w.$('#al .te-alert').length === 0;
	});
	check('the details are cut to 2000 characters', () => details(w.teAlerts.fromResponse('#al', 'x'.repeat(5000))).length === 2000);
	check('a JSON error string: message and additional_message as text', () => {
		a = w.teAlerts.fromResponse('#al', '{"success":0,"message":"<b>Bad</b>","additional_message":"more"}');
		return message(a) === '<b>Bad</b>' && details(a) === 'more';
	});
	check('a success object shows nothing', () => w.teAlerts.fromResponse('#al', { success: 1 }) === null && w.teAlerts.fromResponse('#al', { success: true }) === null);
	check('an error without a message uses te_global_error_internal and has no details', () => {
		a = w.teAlerts.fromResponse('#al', { success: 0 });
		return message(a) === 'Internal' && a.find('.te-alert__details').length === 0 && a.find('.te-js-alert-details').length === 0;
	});
	check('a jqXHR with an HTML body', () => {
		a = w.teAlerts.fromResponse('#al', xhr({ status: 500, statusText: 'Internal Server Error', responseText: '<h1>Fatal</h1>' }));
		return message(a) === 'Not JSON.' && details(a) === '<h1>Fatal</h1>';
	});
	check('a jqXHR with a JSON body', () => message(w.teAlerts.fromResponse('#al', xhr({ responseText: '{"success":0,"message":"M"}' }))) === 'M');
	check('a jqXHR with responseJSON', () => message(w.teAlerts.fromResponse('#al', xhr({ responseJSON: { success: 0, message: 'J' } }))) === 'J');
	check('a jqXHR with no body: the status is in the details', () => details(w.teAlerts.fromResponse('#al', xhr({ readyState: 0, status: 0, statusText: 'error' }))) === '0 error');
	check('a JSON array string is not a JSON object', () => message(w.teAlerts.fromResponse('#al', '[1,2]')) === 'Not JSON.');
	check('an array object is not a JSON object', () => {
		a = w.teAlerts.fromResponse('#al', [1, 2]);
		return message(a) === 'Not JSON.' && details(a) === '[1,2]';
	});
	check('a jqXHR with an array responseJSON is not a JSON object', () => message(w.teAlerts.fromResponse('#al', xhr({ responseJSON: [3], responseText: '[3]' }))) === 'Not JSON.');
	check('a missing container gives null', () => w.teAlerts.show('#none', 'error', 'x') === null);
	check('an unknown type becomes error', () => w.teAlerts.show('#al', 'weird', 'x').hasClass('te-alert--error'));
	check('an error alert has role alert, an info alert has role status', () => w.teAlerts.show('#al', 'error', 'x').attr('role') === 'alert' && w.teAlerts.show('#al', 'info', 'x').attr('role') === 'status');
	{
		// A success alert closes itself after teAlerts.successTimeout. Error and info alerts stay.
		const timers = [];
		const realSetTimeout = w.setTimeout;
		w.setTimeout = function(fn, ms) { timers.push({ fn: fn, ms: ms }); return timers.length; };
		w.jQuery.fx.off = true;
		w.$('#al').empty();
		const ok = w.teAlerts.show('#al', 'success', 'Saved');
		const err = w.teAlerts.show('#al', 'error', 'Bad');
		const inf = w.teAlerts.show('#al', 'info', 'Note');
		check('only a success alert starts a close timer, of teAlerts.successTimeout (5000 ms)', () => timers.length === 1 && timers[0].ms === 5000 && w.teAlerts.successTimeout === 5000);
		check('the success alert stays until the timer runs', () => w.$('#al .te-alert--success').length === 1);
		timers[0].fn();
		check('after the timer the success alert is removed, the error and info alerts stay', () => w.$('#al .te-alert--success').length === 0 && ok.closest('body').length === 0
			&& err.closest('#al').length === 1 && inf.closest('#al').length === 1);
		// jQuery 3 fadeOut also uses setTimeout. Count the alert timers (5000 ms) only.
		w.teAlerts.successTimeout = 0;
		w.teAlerts.show('#al', 'success', 'Kept');
		check('successTimeout 0: no timer, the success alert stays', () => timers.filter((t) => t.ms === 5000).length === 1 && w.$('#al .te-alert--success').length === 1);
		w.teAlerts.successTimeout = 5000;
		w.jQuery.fx.off = false;
		w.setTimeout = realSetTimeout;
		w.$('#al').empty();
	}
	check('clear empties the area', () => {
		w.teAlerts.clear('#al');
		return w.$('#al').children().length === 0;
	});
}

function testCharCountHelpers(w) {
	console.log('\nteFormatInt, teFormatCost, teCountState:');
	check('teFormatInt groups by 3 with a space', () => w.teFormatInt(300000) === '300 000' && w.teFormatInt(1234567) === '1 234 567');
	check('teFormatInt keeps small numbers', () => w.teFormatInt(0) === '0' && w.teFormatInt(999) === '999' && w.teFormatInt(1000) === '1 000');
	check('teFormatInt gives 0 for a value that is not a number', () => w.teFormatInt('abc') === '0' && w.teFormatInt(null) === '0' && w.teFormatInt(undefined) === '0');
	check('teFormatCost gives 2 decimals', () => w.teFormatCost(0) === '$0.00' && w.teFormatCost(1.5) === '$1.50' && w.teFormatCost('2.456') === '$2.46');
	check('teFormatCost gives $0.00 for a value that is not a number', () => w.teFormatCost('x') === '$0.00');
	check('teCountState is ok at the limit and over above it', () => w.teCountState({ charCount: 500000, monthFreeLimit: 500000 }) === 'ok'
		&& w.teCountState({ charCount: 500001, monthFreeLimit: 500000 }) === 'over');

	console.log('\nteRenderHeaderCount:');
	w.document.te_i18n_global = { te_global_char_count: 'Chars <b>', te_global_reset: 'Reset' };
	const ok = w.teRenderHeaderCount({ charCount: 300000, monthFreeLimit: 500000, costOverFreeLimit: 0 });
	check('ok class and value text', () => ok.hasClass('te-header-count') && ok.hasClass('te-header-count--ok')
		&& ok.find('.te-header-count__value').text() === '300 000 / 500 000 ($0.00)');
	check('the title is the value text', () => ok.attr('title') === '300 000 / 500 000 ($0.00)');
	check('two lines: label then value in __text, reset after it', () => {
		const lines = ok.children('.te-header-count__text').children();
		return lines.length === 2 && w.$(lines[0]).hasClass('te-header-count__label') && w.$(lines[1]).hasClass('te-header-count__value')
			&& ok.children().last().hasClass('te-header-count__reset');
	});
	check('a label with HTML stays text', () => ok.find('.te-header-count__label').text() === 'Chars <b>' && ok.find('b').length === 0);
	check('the reset button', () => {
		const b = ok.find('button.te-header-count__reset.te-js-reset-char-count');
		return b.length === 1 && b.attr('type') === 'button' && b.text() === 'Reset';
	});
	const over = w.teRenderHeaderCount({ charCount: 600000, monthFreeLimit: 500000, costOverFreeLimit: 2 });
	check('over class and cost', () => over.hasClass('te-header-count--over') && over.find('.te-header-count__value').text() === '600 000 / 500 000 ($2.00)');

	console.log('\nteRenderSummaryCount:');
	w.document.te_i18n = { te_summary_month: 'Month {count}/{limit}', te_summary_cost: 'Cost {cost}' };
	const m = w.teRenderSummaryCount({ charCount: 250000, monthFreeLimit: 500000, costOverFreeLimit: 0 });
	check('ok class, label and percent', () => m.hasClass('te-meter') && m.hasClass('te-meter--ok')
		&& m.find('.te-meter__label span').eq(0).text() === 'Month 250 000/500 000' && m.find('.te-meter__label span').eq(1).text() === '50 %');
	check('the fill width is the percent', () => m[0].querySelector('.te-meter__bar > .te-meter__fill').style.width === '50%');
	check('cost text and one reset button', () => m.find('.te-meter__cost').text().indexOf('Cost $0.00') === 0 && m.find('button.te-js-reset-char-count[type=button]').length === 1);
	const big = w.teRenderSummaryCount({ charCount: 900000, monthFreeLimit: 500000, costOverFreeLimit: 8 });
	check('over class and the percent stops at 100', () => big.hasClass('te-meter--over') && big.find('.te-meter__label span').eq(1).text() === '100 %'
		&& big[0].querySelector('.te-meter__fill').style.width === '100%');
	const zero = w.teRenderSummaryCount({ charCount: 5, monthFreeLimit: 0, costOverFreeLimit: 0 });
	check('a limit of 0 gives 0 %', () => zero.find('.te-meter__label span').eq(1).text() === '0 %');
	check('the button classes follow the Bootstrap version', () => {
		const b = m.find('button.te-js-reset-char-count');
		return w.teIsBs5() ? b.hasClass('btn-light') && b.hasClass('btn-sm') : b.hasClass('btn-default') && b.hasClass('btn-xs');
	});

	console.log('\nDelegated reset handler:');
	w.bindTeResetCharCountHandler();
	w.bindTeResetCharCountHandler();
	const host = w.$('<div id="rh"></div>').appendTo('body');
	host.append(w.teRenderHeaderCount({ charCount: 1, monthFreeLimit: 2, costOverFreeLimit: 0 }));
	let resetCalls = 0;
	let confirmText = null;
	const origReset = w.resetTranslationCharCount;
	w.resetTranslationCharCount = function() { resetCalls++; };
	w.document.te_i18n_global.te_global_reset_confirm = 'Sure?';
	w.confirm = function(text) { confirmText = text; return false; };
	host.find('.te-js-reset-char-count').trigger('click');
	check('confirm false: no reset', () => resetCalls === 0 && confirmText === 'Sure?');
	w.confirm = function() { return true; };
	host.find('.te-js-reset-char-count').trigger('click');
	check('confirm true: one reset call (the handler is bound once)', () => resetCalls === 1);
	w.resetTranslationCharCount = origReset;
	host.remove();
}

function testRemoveLog(w) {
	console.log('\nteRenderRemoveLog:');
	w.document.te_i18n = {
		te_remove_completed: 'Done: {language}.', te_remove_start: 'Start', te_remove_table: 'T {table}: {rows}',
		te_remove_column: '- {column}: {rows}', te_remove_finish: 'Finish', te_time_in_sec: 'Time {sec}'
	};
	const data = {
		success: 1, target: { language_id: 2, code: 'uk', name: 'Uk <i>x</i>' }, timeInSec: 0.1234,
		steps: [
			{ type: 'start' },
			{ type: 'table', table: 'oc_<b>a</b>', clearedRows: 12345 },
			{ type: 'column', table: 'oc_<b>a</b>', column: 'name', clearedRows: 1000 },
			{ type: 'weird' },
			{ type: 'finish' }
		]
	};
	const log = w.teRenderRemoveLog(data);
	const lines = log.find('.te-log__line').map(function() { return w.$(this).text(); }).get();
	check('the root is .te-app and the heading is text', () => log.hasClass('te-app') && log.find('h2').text() === 'Done: Uk <i>x</i>.' && log.find('i').length === 0);
	check('the lines are in order and rows are formatted, unknown types are skipped', () => lines.join('|') === 'Start|T oc_<b>a</b>: 12 345|- name: 1 000|Finish');
	check('a table name with HTML stays text', () => log.find('b').length === 0);
	check('the time line', () => log.find('.te-log__time').text() === 'Time 0.1234');
	check('no target gives an empty language', () => w.teRenderRemoveLog({ steps: [], timeInSec: 0 }).find('h2').text() === 'Done: .');

	check('steps absent: no step lines and no exception', () => w.teRenderRemoveLog({ timeInSec: 1 }).find('.te-log__line').length === 0);
	check('steps as a string: no step lines and no exception', () => w.teRenderRemoveLog({ steps: 'abc', timeInSec: 1 }).find('.te-log__line').length === 0);
	check('timeInSec missing shows 0', () => w.teRenderRemoveLog({ steps: [] }).find('.te-log__time').text() === 'Time 0');

	console.log('\nmakeRemovingDataFromDbRequest:');
	const origGet = w.$.get;
	const origErr = w.showTranslateExpertErrorMessage;
	const origHide = w.hideTranslateExpertModal;
	let errCalls = 0;
	w.showTranslateExpertErrorMessage = function() { errCalls++; };
	w.hideTranslateExpertModal = function() {};
	w.document.js_const_module_prefix = 'extension/module';
	const origLog = w.console.log;
	w.console.log = function() {};
	w.$.get = function() { return w.$.Deferred().reject({ status: 500 }).promise(); };
	w.makeRemovingDataFromDbRequest();
	check('a failed request keeps the result area and shows the error', () => w.$('#keep').length === 1 && errCalls === 1);
	w.$.get = function() { return w.$.Deferred().resolve(data).promise(); };
	w.makeRemovingDataFromDbRequest();
	check('a successful request renders the log in the result area', () => w.$('#keep').length === 0 && w.$('#te-analization-result .te-log__line').length === 4);
	w.$.get = function() { return w.$.Deferred().resolve({ success: 0, message: 'No' }).promise(); };
	w.makeRemovingDataFromDbRequest();
	check('success 0 shows the error and keeps the log', () => errCalls === 2 && w.$('#te-analization-result .te-log__line').length === 4);
	w.console.log = origLog;
	w.$.get = origGet;
	w.showTranslateExpertErrorMessage = origErr;
	w.hideTranslateExpertModal = origHide;
}

function testShowCharCount(w) {
	console.log('\nshowTranslatedCharCount, showTeCharCountError:');
	const origGet = w.$.get;
	const origLog = w.console.log;
	w.console.log = function() {};
	w.document.js_const_module_prefix = 'extension/module';
	w.document.te_i18n_global = { te_global_char_count: 'Chars', te_global_reset: 'Reset', te_global_error_internal: 'Internal' };
	let requests = 0;
	let reply = null;
	w.$.get = function() {
		requests++;
		return reply === 'fail' ? w.$.Deferred().reject({ status: 500, responseText: 'x' }).promise() : w.$.Deferred().resolve(reply).promise();
	};
	const ok = { success: 1, charCount: 300000, monthFreeLimit: 500000, costOverFreeLimit: 0 };
	const body = w.$('body');
	const clean = function() { w.$('#te-summary-count, #te-summary-alerts, #header, #te-translated-char-count-info').remove(); requests = 0; };

	clean();
	body.append('<div id="te-summary-count"></div><div id="te-summary-alerts"></div>');
	w.document.js_const_client_translate_expert_show_char_count_is_enabled = false;
	reply = ok;
	w.showTranslatedCharCount();
	check('module page: the meter is in #te-summary-count, no header item, flag off still runs', () => requests === 1
		&& w.$('#te-summary-count .te-meter').length === 1 && w.$('#te-translated-char-count-info').length === 0);

	reply = { success: 0, message: 'Bad' };
	w.showTranslatedCharCount();
	check('module page: success 0 shows an alert in #te-summary-alerts', () => w.$('#te-summary-alerts .te-alert').length === 1);
	reply = 'fail';
	w.showTranslatedCharCount();
	check('module page: a rejected request shows one alert', () => w.$('#te-summary-alerts .te-alert').length === 1);

	clean();
	body.append('<div id="header"><ul class="nav pull-right"></ul></div>');
	reply = ok;
	w.document.js_const_client_translate_expert_show_char_count_is_enabled = false;
	w.showTranslatedCharCount();
	check('other page: flag off sends no request', () => requests === 0 && w.$('#te-translated-char-count-info').length === 0);

	w.document.js_const_client_translate_expert_show_char_count_is_enabled = true;
	w.showTranslatedCharCount();
	check('other page: the first call creates one li under the header', () => requests === 1
		&& w.$('#te-translated-char-count-info').length === 1 && w.$('#header > ul.nav > li#te-translated-char-count-info .te-header-count').length === 1);
	reply = { success: 1, charCount: 600000, monthFreeLimit: 500000, costOverFreeLimit: 1 };
	w.showTranslatedCharCount();
	check('other page: the second call reuses the li and updates it', () => w.$('#te-translated-char-count-info').length === 1
		&& w.$('#te-translated-char-count-info .te-header-count--over').length === 1);

	const countAlerts = function() { return w.$('.te-alert').length; };
	reply = { success: 0, message: 'Bad' };
	w.showTranslatedCharCount();
	check('other page: success 0 adds no alert and does not throw', () => countAlerts() === 0);
	reply = 'fail';
	w.showTranslatedCharCount();
	check('other page: a rejected request adds no alert and does not throw', () => countAlerts() === 0 && w.$('#te-translated-char-count-info').length === 1);

	clean();
	w.console.log = origLog;
	w.$.get = origGet;
}

function testBs5(w) {
	console.log('\nBootstrap 5 (OC 4.x):');
	check('teIsBs5 is true', () => w.teIsBs5() === true);
	check('teIcon maps an FA 4 name to the FA 6 name', () => w.teIcon('save') === 'fa-solid fa-floppy-disk');
	check('teIcon keeps a name that is not in the map', () => w.teIcon('language') === 'fa-solid fa-language');
	check('teIcon does not use prototype keys of the map', () => w.teIcon('toString') === 'fa-solid fa-toString');
	check('the adapter calls bootstrap.Tab, Collapse and Modal, and ignores an unknown action', () => {
		w.teCalls = [];
		w.teBs.tab('#tab');
		w.teBs.collapse('#col', 'toggle');
		w.teBs.modal('#tab', 'show');
		w.teBs.modal('#tab', 'bad');
		return w.teCalls.join(',') === 'Tab.show,Collapse.toggle,Modal.show';
	});
	const node = w.document.getElementById('tab');
	check('a modal hide during the show animation waits', () => {
		w.teCalls = [];
		node.teShowing = true;
		w.teBs.modal(node, 'hide');
		return w.teCalls.length === 0 && node.teHidePending === true;
	});
	check('a modal hide after the show animation hides', () => {
		w.teCalls = [];
		node.teShowing = false;
		w.teBs.modal(node, 'hide');
		return w.teCalls.join(',') === 'Modal.hide';
	});
}

// ---- Tab "Website content" ----

const SITE_I18N = {
	te_site_stop: 'Stop', te_site_log: 'Log', te_site_analyzing: 'Analyzing', te_site_translating: 'Translating {table}',
	te_site_col_table: 'Table / column', te_site_col_chars: 'Characters', te_site_col_chars_lang: 'Characters ({code})', te_site_col_actions: 'Actions',
	te_site_total: 'Total', te_site_translate: 'Translate', te_site_translate_all: 'Translate all', te_site_details: 'Details',
	te_site_not_supported: 'not supported', te_site_done: 'done', te_site_toggle: 'Toggle {table}', te_site_cnt_current: 'Now: {current}',
	te_site_cnt_table: 'Table {n} of {total}', te_site_cnt_request: 'Request {request}', te_site_cnt_chars: 'Characters found: {chars}',
	te_site_cnt_time: 'Time: {sec} s', te_site_cnt_translated: 'Translated: {chars}', te_site_translate_confirm: 'Translate {table} ({lang})?',
	te_site_translate_all_confirm: 'Translate all ({lang})?', te_site_log_table_done: 'Done {table} {chars}', te_site_detail_loading: 'Loading',
	te_site_detail_source: 'Source ({code})', te_site_detail_target: 'Target ({code})', te_site_empty: 'empty', te_site_identical: 'identical',
	te_site_detail_none: 'No values.', te_site_detail_more: 'Only 100.', te_site_state_done: 'Done', te_site_state_stopped: 'Stopped',
	te_site_state_error: 'Error', value_analization_mode_both: 'Both', value_analization_mode_only_empty: 'Only empty',
	value_analization_mode_same_value: 'Same', te_alert_details: 'Details', te_alert_close: 'Close', te_alert_not_json: 'Not JSON.',
	te_global_error_internal: 'Internal'
};
const LOC_I18N = {
	te_loc_analyzing: 'Analyzing files', te_loc_translating: 'Translating {path}', te_loc_cnt_files: 'Files {n} of {total}', te_loc_totals_files: 'Files: {count}',
	te_loc_totals_to_translate: 'To translate: {count}', te_loc_totals_values: 'Values: {count}', te_loc_totals_chars: 'Chars: {count}', te_loc_col_file: 'File',
	te_loc_col_keys: 'Keys', te_loc_col_chars: 'Chars', te_loc_col_state: 'State', te_loc_state_missing: 'missing', te_loc_state_partial: 'partial', te_loc_state_saved: 'saved',
	te_loc_toggle: 'Toggle {path}', te_loc_root: 'Extension: {name}', te_loc_expression: 'PHP code', te_loc_source_file: 'Source file',
	te_loc_translate_file: 'Translate file', te_loc_translate_save_file: 'Translate and save file', te_loc_save_file: 'Save file', te_loc_saved: 'Saved: {path}', te_loc_nothing_to_save: 'Nothing: {path}',
	te_loc_no_files: 'All translated.', te_loc_translate_all: 'Translate all', te_loc_translate_all_confirm: 'Translate all ({lang})?'
};

function siteHtml(activeTab) {
	const li = (id, href, label) => '<li' + (activeTab === href ? ' class="active"' : '') + '><a id="' + id + '" href="' + href + '" data-toggle="tab">' + label + '</a></li>';
	const opts = (list, selected) => list.map((o) => '<option value="' + o[0] + '"' + (selected && selected.indexOf(o[0]) >= 0 ? ' selected' : '') + '>' + o[1] + '</option>').join('');
	return '<ul class="nav nav-tabs">' + li('t-general', '#tab-general', 'g') + li('t-site', '#tab-translate-site', 's') + li('t-loc', '#tab-localization', 'l')
		+ li('t-admin', '#tab-admin-localization', 'a') + li('t-debug', '#tab-debug', 'd') + '</ul>'
		+ '<div class="te-app te-langbar-wrap" id="te-langbar-app"><div class="te-langbar te-hidden">'
		+ '<select id="te-langbar-from">' + opts([['1', 'English'], ['2', 'Ukrainian']], ['1']) + '</select>'
		+ '<button type="button" class="te-js-langbar-swap">x</button>'
		+ '<select id="te-langbar-to">' + opts([['1', 'English'], ['2', 'Ukrainian']], ['2']) + '</select></div></div>'
		+ '<div class="te-app" id="te-site-app"><div class="te-alerts" id="te-site-alerts"></div>'
		+ '<input type="radio" name="te-analization-mode" value="both" checked><input type="radio" name="te-analization-mode" value="only_empty"><input type="radio" name="te-analization-mode" value="same_value">'
		+ '<select id="te-analization-table" multiple>' + opts([['oc_a', 'a'], ['oc_b', 'b'], ['oc_c', 'c']]) + '</select>'
		+ '<input type="checkbox" id="te-analization-table-deep" checked>'
		+ '<input type="checkbox" id="te-analization-product-status-filter"><input type="checkbox" id="te-analization-product-quantity-filter">'
		+ '<select id="te-analization-product-category-id-filter" multiple>' + opts([['3', 'c3'], ['4', 'c4']]) + '</select>'
		+ '<select id="te-analization-product-stock-status-filter" multiple>' + opts([['7', 's7'], ['8', 's8']]) + '</select>'
		+ '<select id="te-analization-product-manufacturer-filter" multiple>' + opts([['5', 'm5'], ['6', 'm6']]) + '</select>'
		+ '<button type="button" id="te-begin-analization">Analyze</button>'
		+ '<button type="button" id="te-begin-removing_data_from_db" data-te-label="Remove">Remove</button>'
		+ '<button type="button" class="te-js-collapse" id="cbtn" data-te-target="#te-site-more-filters" aria-expanded="false">m</button><div id="te-site-more-filters" class="te-hidden"></div>'
		+ '<div id="te-cron-link-panel" data-cron-url="http://shop/index.php?route=cron" data-te-max-length="4000"><input id="te-cron-link"><div id="te-cron-link-error"></div><div id="te-cron-link-long"></div></div>'
		+ '<input id="te-cron-secret" value="abcdefghijklmnopqrstuvwx"><input id="te-cron-bulk-amount" value="">'
		+ '<div class="te-progress te-hidden" id="te-site-progress"></div><div id="te-analization-result"><p id="keep">old</p></div></div>'
		+ '<div class="te-app te-loc-app" id="te-loc-app-catalog" data-scope="catalog"><div class="te-alerts" id="te-loc-alerts-catalog"></div>'
		+ '<div class="te-loc-totals te-hidden" id="te-loc-totals-catalog"></div>'
		+ '<button type="button" id="te-begin-localization-analization" class="te-js-loc-analyze">Analyze</button>'
		+ '<div class="te-progress te-hidden" id="te-loc-progress-catalog"></div><div class="te-loc-result" id="te-loc-result-catalog"></div></div>'
		+ '<div class="te-app te-loc-app" id="te-loc-app-admin" data-scope="admin"><div class="te-alerts" id="te-loc-alerts-admin"></div>'
		+ '<div class="te-loc-totals te-hidden" id="te-loc-totals-admin"></div>'
		+ '<button type="button" id="te-begin-admin-localization-analization" class="te-js-loc-analyze">Analyze</button>'
		+ '<div class="te-progress te-hidden" id="te-loc-progress-admin"></div><div class="te-loc-result" id="te-loc-result-admin"></div></div>'
		+ '<div class="modal" id="translateExpertModal"><h5 id="translateExpertModalLabel">Orig</h5><button class="stop_modal_process">stop</button>'
		+ '<div class="modal-body">body</div><button class="close_modal" disabled>close</button></div>';
}

function makeSiteWindow(jqueryFile, withBs5, activeTab) {
	const w = makeWindow(jqueryFile, withBs5, siteHtml(activeTab || '#tab-general'));
	w.document.te_i18n = Object.assign({}, SITE_I18N, LOC_I18N);
	w.document.js_const_module_prefix = 'extension/module';
	w.console.log = function() {};
	if (!withBs5) {
		w.$.fn.modal = function(arg) { w.teCalls.push('modal:' + (typeof arg === 'string' ? arg : 'show')); return this; };
	}
	return w;
}

// $.get and $.post return a Deferred that the test settles by hand, so that the DOM can be checked between 2 batches.
function stubAjax(w) {
	const calls = { get: [], post: [] };
	const make = (kind) => function(url, data) {
		const dfd = w.$.Deferred();
		calls[kind].push({ url: url, data: data, dfd: dfd });
		return dfd.promise();
	};
	w.$.get = make('get');
	w.$.post = make('post');
	return calls;
}

const tableBatch = (table, supported, charCount, columns) => ({ table: table, supported: supported, charCount: charCount, columns: columns.map((c) => ({ column: c[0], charCount: c[1] })) });
const TARGET = { language_id: 2, code: 'uk-ua', name: 'Ukrainian', image: '' };

function testTeProgress(w) {
	console.log('\nteProgress:');
	w.$('<div class="te-app"><div class="te-progress te-hidden" id="pp"></div></div>').appendTo('body');
	w.teProgress.start('#pp', '<b>Title</b>');
	const p = w.$('#pp');
	check('start shows the panel and builds the parts', () => !p.hasClass('te-hidden') && p.find('.te-progress__head .te-progress__title').length === 1
		&& p.find('.te-progress__bar .te-progress__fill').length === 1 && p.find('.te-progress__counters').length === 1 && p.find('button.te-js-stop').text() === 'Stop');
	check('the title is text', () => p.find('.te-progress__title').text() === '<b>Title</b>' && p.find('b').length === 0);
	check('the log starts collapsed and its toggle is hidden', () => p.find('.te-progress__log').hasClass('te-hidden') && p.find('.te-progress__log-toggle').hasClass('te-hidden'));
	w.teProgress.update('#pp', { percent: 42.4, counters: ['a <i>', 'b'] });
	check('update sets the bar width and the counters as text', () => p.find('.te-progress__fill')[0].style.width === '42.4%'
		&& p.find('.te-progress__counters span').length === 2 && p.find('.te-progress__counters').text() === 'a <i>b' && p.find('i').length === 0);
	w.teProgress.update('#pp', { percent: null });
	check('a null percent hides the bar', () => p.find('.te-progress__bar').hasClass('te-hidden'));
	w.teProgress.update('#pp', { percent: 250 });
	check('a percent above 100 is cut to 100', () => p.find('.te-progress__fill')[0].style.width === '100%');
	w.teProgress.log('#pp', 'line <b>1</b>');
	w.teProgress.log('#pp', 'line 2');
	check('log appends text lines and shows the toggle', () => p.find('.te-progress__line').length === 2 && p.find('.te-progress__log').text() === 'line <b>1</b>line 2'
		&& !p.find('.te-progress__log-toggle').hasClass('te-hidden') && p.find('.te-progress__log b').length === 0);
	check('the log toggle opens the log (delegated collapse handler)', () => {
		w.teInitCollapse();
		p.find('.te-progress__log-toggle').trigger('click');
		return !p.find('.te-progress__log').hasClass('te-hidden') && p.find('.te-progress__log-toggle').attr('aria-expanded') === 'true';
	});
	w.teProgress.finish('#pp', 'stopped');
	check('finish stopped: modifier class, no stop button, state badge', () => p.hasClass('te-progress--stopped') && p.find('.te-js-stop').hasClass('te-hidden')
		&& p.find('.te-progress__head .te-badge--warn').text() === 'Stopped');
	w.teProgress.finish('#pp', 'error');
	check('finish error: one badge', () => p.hasClass('te-progress--error') && p.find('.te-progress__head .te-badge').length === 1 && p.find('.te-badge--error').text() === 'Error');
	w.teProgress.finish('#pp', 'done');
	check('finish done: bar at 100 %', () => p.hasClass('te-progress--done') && p.find('.te-progress__fill')[0].style.width === '100%');
	w.teProgress.start('#pp', 'Again');
	check('start again clears the old state', () => !p.hasClass('te-progress--done') && p.find('.te-badge').length === 0 && p.find('.te-progress__line').length === 0);
	w.$('#pp').parent().remove();
}

function testSiteFilters(w) {
	console.log('\nteSiteFilters, teSiteFilterParams, cron link:');
	let f = w.teSiteFilters();
	check('default: mode both, langbar values, nothing selected', () => f.mode === 'both' && f.langIdFrom === '1' && f.langIdTo === '2' && f.tables.length === 0 && f.deep === true
		&& f.productStatus === false && f.productQuantity === false && f.categoryIds.length === 0);
	let params = w.teSiteFilterParams(f);
	check('default params: no list and no checkbox parameter', () => JSON.stringify(params) === '{"langIdFrom":"1","langIdTo":"2","mode":"both"}');
	w.$('input[name="te-analization-mode"][value="same_value"]').prop('checked', true);
	w.$('#te-analization-table').val(['oc_a', 'oc_c']);
	w.$('#te-analization-product-status-filter').prop('checked', true);
	w.$('#te-analization-product-quantity-filter').prop('checked', true);
	w.$('#te-analization-product-category-id-filter').val(['3', '4']);
	w.$('#te-analization-product-stock-status-filter').val(['8']);
	w.$('#te-analization-product-manufacturer-filter').val(['5']);
	w.$('#te-analization-table-deep').prop('checked', false);
	f = w.teSiteFilters();
	check('the mode comes from the checked radio', () => f.mode === 'same_value');
	check('the multi values are arrays and the booleans are booleans', () => f.tables.join() === 'oc_a,oc_c' && f.categoryIds.join() === '3,4' && f.stockStatus.join() === '8'
		&& f.manufacturerIds.join() === '5' && f.productStatus === true && f.productQuantity === true && f.deep === false);
	params = w.teSiteFilterParams(f);
	check('params: the boxes go as 1, the lists as comma strings, in the order of the cron link', () => JSON.stringify(params)
		=== '{"langIdFrom":"1","langIdTo":"2","mode":"same_value","table":"oc_a,oc_c","product_status":1,"product_quantity":1,"product_category_id":"3,4","stock_status":"8","manufacturer_id":"5"}');
	w.$('#te-analization-product-status-filter').prop('checked', false);
	check('an unchecked box is left out (never the string "false")', () => !('product_status' in w.teSiteFilterParams(w.teSiteFilters())));
	check('post params have no table list but the table and column names', () => {
		const post = w.teSitePostParams(f, 'oc_a', '');
		return !('table' in post) && post.tableName === 'oc_a' && post.textColumnName === '' && post.mode === 'same_value';
	});
	w.updateTranslateExpertCronLink();
	check('the cron link has the parameters in the same order as before the change', () => w.$('#te-cron-link').val()
		=== 'http://shop/index.php?route=cron&secret=abcdefghijklmnopqrstuvwx&langIdFrom=1&langIdTo=2&mode=same_value&table=oc_a,oc_c&product_quantity=1&product_category_id=3,4&stock_status=8&manufacturer_id=5');
	w.$('#te-langbar-to').val('1').trigger('change');
	w.$('input[name="te-analization-mode"][value="both"]').prop('checked', true);
	w.$('#te-analization-table').val([]);
	w.$('#te-analization-product-category-id-filter').val([]);
	w.$('#te-analization-product-stock-status-filter').val([]);
	w.$('#te-analization-product-manufacturer-filter').val([]);
	w.$('#te-analization-product-quantity-filter').prop('checked', false);
	w.$('#te-langbar-to').val('2');
}

function testSiteCronBinding(w) {
	console.log('\nCron link binding on the langbar:');
	w.initTranslateExpertCron();
	w.$('#te-langbar-to').val('1').trigger('change');
	check('a langbar change updates the cron link', () => /langIdTo=1&/.test(w.$('#te-cron-link').val()));
	w.$('input[name="te-analization-mode"][value="only_empty"]').prop('checked', true).trigger('change');
	check('a mode radio change updates the cron link', () => /mode=only_empty/.test(w.$('#te-cron-link').val()));
	w.$('#te-langbar-to').val('2');
	w.$('input[name="te-analization-mode"][value="both"]').prop('checked', true);
}

function startAnalysis(w, calls) {
	w.$('#te-begin-analization').trigger('click');
	return calls.get.length;
}

function testSiteAnalysis(w) {
	console.log('\nAnalysis loop (2 batches, deep):');
	const calls = stubAjax(w);
	w.teSiteInit();
	w.$('#te-analization-table').val([]);
	w.$('#te-analization-table-deep').prop('checked', true);
	w.$('#te-site-alerts').empty();
	w.$('#te-begin-analization').trigger('click');
	w.$('#te-begin-analization').trigger('click');
	check('the first click sends one request, a second click while running sends none', () => calls.get.length === 1);
	check('the first request: route, langbar ids, mode, no continue parameters', () => /route=extension\/module\/client_translate_expert\/analize&token=/.test(calls.get[0].url)
		&& calls.get[0].data.langIdFrom === '1' && calls.get[0].data.langIdTo === '2' && calls.get[0].data.mode === 'both'
		&& !('continue_after_table' in calls.get[0].data) && !('product_status' in calls.get[0].data) && !('table' in calls.get[0].data));
	check('the start disables the buttons, empties the result, shows the panel with a stop button', () => w.$('#te-begin-analization').prop('disabled') === true
		&& w.$('#te-begin-removing_data_from_db').prop('disabled') === true && w.$('#keep').length === 0 && w.$('#te-analization-result table.te-table').length === 1
		&& !w.$('#te-site-progress').hasClass('te-hidden') && w.$('#te-site-progress .te-js-stop').length === 1);

	calls.get[0].dfd.resolve({
		success: 1, done: 0, target: TARGET, globalCharCount: 100, timeInSec: 0.54,
		tables: [tableBatch('oc_a', 1, 100, [['name', 40], ['description', 60], ['zero', 0]])],
		lastProcessedTable: 'oc_a', lastProcessedColumn: 'description', lastProcessedOffset: 50
	});
	const groupRow = w.$('tr.te-table__group[data-table="oc_a"]')[0];
	const nameRow = w.$('tr[data-table="oc_a"][data-column="name"]')[0];
	check('batch 1: group row, column rows, counts with the space format', () => !!groupRow && w.$(groupRow).children('td.te-num').text() === '100'
		&& w.$(nameRow).children('td.te-num').text() === '40' && w.$('tr[data-table="oc_a"][data-column]').length === 3);
	check('batch 1: the head shows the target code, the total row the count', () => w.$('#te-analization-result thead th').eq(1).text() === 'Characters (uk-ua)'
		&& w.$('#te-analization-result thead .te-table__total .te-num').text() === '100');
	check('the total row with Translate all is at the top: in thead after the column heads, no tfoot', () => w.$('#te-analization-result thead tr').length === 2
		&& w.$('#te-analization-result thead tr').eq(1).hasClass('te-table__total')
		&& w.$('#te-analization-result thead .te-table__total .te-js-site-translate-all').length === 1
		&& w.$('#te-analization-result tfoot').length === 0);
	check('batch 1: buttons: a column with characters has Details and Translate, a column with 0 has neither Details nor Translate', () => {
		const r = w.$(nameRow);
		const z = w.$('tr[data-column="zero"]');
		return r.find('.te-js-site-detail').length === 1 && r.find('.te-js-site-translate').length === 1 && z.find('.te-js-site-detail').length === 0 && z.find('.te-js-site-translate').length === 0;
	});
	check('batch 1: the group row has an expanded toggle and a Translate button, the translate buttons are off while running', () => w.$(groupRow).find('button.te-table__expand.te-js-site-toggle').attr('aria-expanded') === 'true'
		&& w.$(groupRow).find('.te-js-site-translate[data-table="oc_a"]').length === 1 && w.$(nameRow).find('.te-js-site-translate').prop('disabled') === true);
	check('batch 1: the progress shows the counters from the state', () => w.$('#te-site-progress .te-progress__counters').text() === 'Now: oc_aTable 1 of 3Request 1Characters found: 100Time: 0.5 s'
		&& w.$('#te-site-progress .te-progress__fill')[0].style.width.indexOf('33.3') === 0);
	check('the deep request 2 sends the continue parameters of lastProcessed*', () => calls.get.length === 2 && calls.get[1].data.continue_after_table === 'oc_a'
		&& calls.get[1].data.continue_after_column === 'description' && calls.get[1].data.continue_offset === 50);

	const nameTranslateBtn = w.$(nameRow).find('.te-js-site-translate')[0];
	w.$(groupRow).find('.te-js-site-toggle').trigger('click');
	calls.get[1].dfd.resolve({
		success: 1, done: 1, target: TARGET, globalCharCount: 80, timeInSec: 0.2,
		tables: [tableBatch('oc_a', 1, 20, [['name', 10], ['meta_title', 10]]), tableBatch('oc_b', 1, 50, [['title', 50]]), tableBatch('oc_c', 0, 10, [['text', 10]])],
		lastProcessedTable: 'oc_c', lastProcessedColumn: 'text', lastProcessedOffset: 0
	});
	check('batch 2: a new column of a collapsed table is hidden', () => w.$('tr[data-table="oc_a"][data-column="meta_title"]').hasClass('te-hidden')
		&& w.$(groupRow).find('.te-js-site-toggle').attr('aria-expanded') === 'false');
	w.$(groupRow).find('.te-js-site-toggle').trigger('click');
	check('batch 2: the expand shows the new column too', () => !w.$('tr[data-table="oc_a"][data-column="meta_title"]').hasClass('te-hidden'));
	check('batch 2: an existing Translate button stays the same node, no second button', () => w.$(nameRow).find('.te-js-site-translate').length === 1
		&& w.$(nameRow).find('.te-js-site-translate')[0] === nameTranslateBtn && w.$(nameRow).find('.te-js-site-detail').length === 1);
	check('batch 2: the same table has no second group row, its counts are added', () => w.$('tr.te-table__group[data-table="oc_a"]').length === 1 && w.$(groupRow).children('td.te-num').text() === '120'
		&& w.$(nameRow).children('td.te-num').text() === '50');
	check('batch 2: the old row nodes are the same nodes and still in the document', () => w.$('tr.te-table__group[data-table="oc_a"]')[0] === groupRow
		&& w.$('tr[data-table="oc_a"][data-column="name"]')[0] === nameRow && w.document.body.contains(groupRow) && w.document.body.contains(nameRow));
	check('batch 2: the new column is appended after the last column of its table, then the next tables', () => w.$('#te-analization-result tbody tr').map(function() {
		return w.$(this).attr('data-column') || w.$(this).attr('data-table');
	}).get().join() === 'oc_a,name,description,zero,meta_title,oc_b,title,oc_c,text');
	check('a column row: Translate is the first button, then Details', () => {
		const btns = w.$(nameRow).find('td.te-site-actions button');
		return btns.length === 2 && w.$(btns[0]).hasClass('te-js-site-translate') && w.$(btns[1]).hasClass('te-js-site-detail');
	});
	check('one empty spacer row between 2 tables, none before the first table', () => {
		const rows = w.$('#te-analization-result tbody tr');
		return w.$('#te-analization-result tbody tr.te-table__spacer').length === 2 && !w.$(rows[0]).hasClass('te-table__spacer')
			&& w.$('tr.te-table__group[data-table="oc_b"]').prev().hasClass('te-table__spacer') && w.$('tr.te-table__group[data-table="oc_c"]').prev().hasClass('te-table__spacer')
			&& w.$('tr.te-table__spacer td').attr('colspan') === '3';
	});
	check('batch 2: the total and the counters come from the state, no request after done', () => w.$('#te-analization-result thead .te-table__total .te-num').text() === '180' && calls.get.length === 2
		&& w.$('#te-site-progress .te-progress__counters').text() === 'Table 3 of 3Request 2Characters found: 180Time: 0.7 s'
		&& w.$('#te-site-progress .te-progress__fill')[0].style.width === '100%');
	check('done: the panel is done, the stop button is hidden, the buttons work again', () => w.$('#te-site-progress').hasClass('te-progress--done') && w.$('#te-site-progress .te-js-stop').hasClass('te-hidden')
		&& w.$('#te-begin-analization').prop('disabled') === false && w.$('#te-begin-removing_data_from_db').prop('disabled') === false
		&& w.$('#te-analization-result .te-js-site-translate').filter(function() { return this.disabled; }).length === 0 && w.teSiteState.running === false);
	check('an unsupported table: the muted badge, no Translate button', () => {
		const row = w.$('tr.te-table__group[data-table="oc_c"]');
		return row.find('.te-badge--muted').text() === 'not supported' && row.find('.te-js-site-translate').length === 0
			&& w.$('tr[data-column="text"]').find('.te-js-site-translate').length === 0 && w.$('tr[data-column="text"]').find('.te-js-site-detail').length === 1;
	});
	check('Translate all shows (there are supported tables with characters)', () => !w.$('#te-analization-result .te-js-site-translate-all').hasClass('te-hidden'));
	check('the toggle hides and shows the column rows of its table only', () => {
		w.$(groupRow).find('.te-js-site-toggle').trigger('click');
		const hidden = w.$('tr[data-table="oc_a"][data-column]').filter(function() { return w.$(this).hasClass('te-hidden'); }).length === 4
			&& !w.$('tr[data-table="oc_b"][data-column]').hasClass('te-hidden') && w.$(groupRow).find('.te-js-site-toggle').attr('aria-expanded') === 'false';
		w.$(groupRow).find('.te-js-site-toggle').trigger('click');
		return hidden && !w.$(nameRow).hasClass('te-hidden');
	});

	console.log('\nAnalysis: non-deep, the next request after the last table of the batch:');
	calls.get.length = 0;
	w.$('#te-analization-table-deep').prop('checked', false);
	w.$('#te-analization-table').val(['oc_a', 'oc_b']);
	w.$('#te-begin-analization').trigger('click');
	check('the first request sends the selected tables', () => calls.get[0].data.table === 'oc_a,oc_b');
	calls.get[0].dfd.resolve({ success: 1, done: 0, target: TARGET, globalCharCount: 5, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 5, [['name', 5]]), tableBatch('oc_b', 1, 0, [])],
		lastProcessedTable: 'oc_zzz', lastProcessedColumn: 'x', lastProcessedOffset: 9 });
	check('non-deep: continue_after_table is the last table of the batch, no column and no offset', () => calls.get[1].data.continue_after_table === 'oc_b'
		&& !('continue_after_column' in calls.get[1].data) && !('continue_offset' in calls.get[1].data));
	check('M is the number of selected tables', () => w.$('#te-site-progress .te-progress__counters').text().indexOf('Table 2 of 2') >= 0);
	calls.get[1].dfd.resolve({ success: 1, done: 1, target: TARGET, globalCharCount: 0, timeInSec: 0, tables: [], lastProcessedTable: null, lastProcessedColumn: null, lastProcessedOffset: 0 });
	w.$('#te-analization-table').val([]);

	console.log('\nAnalysis: non-deep, a last batch with no tables and done = 0 (store run 2026-11-16):');
	calls.get.length = 0;
	w.$('#te-analization-table-deep').prop('checked', false);
	w.$('#te-begin-analization').trigger('click');
	calls.get[0].dfd.resolve({ success: 1, done: 0, target: TARGET, globalCharCount: 5, timeInSec: 0.1, tables: [tableBatch('oc_c', 1, 5, [['text', 5]])],
		lastProcessedTable: 'oc_c', lastProcessedColumn: 'text', lastProcessedOffset: 0 });
	check('the number of a table is its position in the table list (tables with 0 characters are not in the response)', () => w.$('#te-site-progress .te-progress__counters').text().indexOf('Now: oc_cTable 3 of 3') === 0);
	calls.get[1].dfd.resolve({ success: 1, done: 0, target: TARGET, globalCharCount: 0, timeInSec: 0.1, tables: [],
		lastProcessedTable: 'oc_weight', lastProcessedColumn: 'name', lastProcessedOffset: 0 });
	check('an empty batch ends the run: N of N, no "Now:", the panel is done, no next request', () => calls.get.length === 2
		&& w.$('#te-site-progress .te-progress__counters').text().indexOf('Table 3 of 3') === 0 && w.$('#te-site-progress .te-progress__counters').text().indexOf('Now:') < 0
		&& w.$('#te-site-progress').hasClass('te-progress--done') && w.teSiteState.running === false);
	w.$('#te-analization-table-deep').prop('checked', true);

	console.log('\nAnalysis: done with tables that have 0 characters:');
	calls.get.length = 0;
	w.$('#te-begin-analization').trigger('click');
	calls.get[0].dfd.resolve({ success: 1, done: 1, target: TARGET, globalCharCount: 5, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 5, [['name', 5]])],
		lastProcessedTable: null, lastProcessedColumn: null, lastProcessedOffset: 0 });
	check('done: the count of tables is the total, the bar is full', () => w.$('#te-site-progress .te-progress__counters').text().indexOf('Table 3 of 3') >= 0
		&& w.$('#te-site-progress .te-progress__fill')[0].style.width === '100%');

	console.log('\nAnalysis: errors and stop:');
	calls.get.length = 0;
	w.$('#te-analization-table-deep').prop('checked', true);
	w.$('#te-begin-analization').trigger('click');
	calls.get[0].dfd.resolve({ success: 1, done: 0, target: TARGET, globalCharCount: 7, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 7, [['name', 7]])], lastProcessedTable: 'oc_a', lastProcessedColumn: 'name', lastProcessedOffset: 1 });
	calls.get[1].dfd.resolve({ success: 0, message: 'API <b>down</b>', additional_message: 'more' });
	check('success 0: the alert is in #te-site-alerts as text, the rows stay, the panel is in the error state, the buttons work', () => w.$('#te-site-alerts .te-alert__message').text() === 'API <b>down</b>'
		&& w.$('#te-site-alerts b').length === 0 && w.$('#te-analization-result tr.te-table__group').length === 1 && w.$('#te-site-progress').hasClass('te-progress--error')
		&& w.$('#te-begin-analization').prop('disabled') === false && w.teSiteState.running === false);
	calls.get.length = 0;
	w.$('#te-begin-analization').trigger('click');
	check('a new start clears the old alert', () => w.$('#te-site-alerts .te-alert').length === 0);
	calls.get[0].dfd.resolve({ success: 1, done: 0, target: TARGET, globalCharCount: 7, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 7, [['name', 7]])], lastProcessedTable: 'oc_a', lastProcessedColumn: 'name', lastProcessedOffset: 1 });
	calls.get[1].dfd.reject(xhr({ status: 500, statusText: 'Internal Server Error', responseText: '<html><title>Login</title></html>' }));
	check('.fail(): the alert is in #te-site-alerts, the rows stay, the buttons work', () => w.$('#te-site-alerts .te-alert__message').text() === 'Not JSON.'
		&& w.$('#te-site-alerts .te-alert__details').text().indexOf('Login') >= 0 && w.$('#te-analization-result tr.te-table__group').length === 1
		&& w.$('#te-site-progress').hasClass('te-progress--error') && w.$('#te-begin-analization').prop('disabled') === false);
	calls.get.length = 0;
	w.$('#te-begin-analization').trigger('click');
	w.$('#te-site-progress .te-js-stop').trigger('click');
	check('the stop button is disabled after a click', () => w.$('#te-site-progress .te-js-stop').prop('disabled') === true);
	calls.get[0].dfd.resolve({ success: 1, done: 0, target: TARGET, globalCharCount: 7, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 7, [['name', 7]])], lastProcessedTable: 'oc_a', lastProcessedColumn: 'name', lastProcessedOffset: 1 });
	check('stop: no next request, the panel is stopped, the batch rows stay, the buttons work', () => calls.get.length === 1 && w.$('#te-site-progress').hasClass('te-progress--stopped')
		&& w.$('#te-analization-result tr.te-table__group').length === 1 && w.$('#te-begin-analization').prop('disabled') === false);
	calls.get.length = 0;
	w.$('#te-begin-analization').trigger('click');
	calls.get[0].dfd.resolve('<html>login</html>');
	check('a string answer shows the not-JSON alert', () => w.$('#te-site-alerts .te-alert__message').text() === 'Not JSON.' && w.$('#te-site-progress').hasClass('te-progress--error'));
	w.$('#te-site-alerts').empty();
}

function testSiteDetail(w) {
	console.log('\nDetail view:');
	const calls = stubAjax(w);
	w.teSiteInit();
	w.$('#te-analization-table').val([]);
	w.$('#te-analization-product-status-filter').prop('checked', false);
	w.$('#te-begin-analization').trigger('click');
	calls.get[0].dfd.resolve({ success: 1, done: 1, target: TARGET, globalCharCount: 9, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 9, [['name', 9]])], lastProcessedTable: 'oc_a', lastProcessedColumn: 'name', lastProcessedOffset: 0 });
	w.$('#te-langbar-to').val('1');
	w.$('input[name="te-analization-mode"][value="only_empty"]').prop('checked', true);
	w.teCalls = [];
	w.$('tr[data-column="name"] .te-js-site-detail').trigger('click');
	check('the click POSTs the filters of the analysis (not of the page now), the table and the column', () => calls.post.length === 1
		&& /route=extension\/module\/client_translate_expert\/analizeTableDetail&token=/.test(calls.post[0].url) && calls.post[0].data.tableName === 'oc_a'
		&& calls.post[0].data.textColumnName === 'name' && calls.post[0].data.mode === 'both' && calls.post[0].data.langIdTo === '2' && !('product_status' in calls.post[0].data));
	check('the modal shows with a loading line, the title is table.column, the stop button is hidden, the close button works', () => w.teCalls.indexOf('modal:show') >= 0
		&& w.$('#translateExpertModal .modal-body').text() === 'Loading' && w.$('#translateExpertModalLabel').text() === 'oc_a.name'
		&& w.$('.stop_modal_process').css('display') === 'none' && w.$('.close_modal').prop('disabled') === false);
	calls.post[0].dfd.resolve({
		success: 1, table: 'oc_a', column: 'name', mode: 'both', languageFrom: { code: 'en-gb' }, languageTo: { code: 'uk-ua' }, idColumns: ['product_id'],
		rows: [
			{ ids: { product_id: 42 }, textFrom: '<script>window.xss=1</script><b>x</b>', textTo: '', count: 3 },
			{ ids: { product_id: 43 }, textFrom: 'Same', textTo: 'Same', count: 1 },
			{ ids: { product_id: 44 }, textFrom: 'A', textTo: 'B', count: 1 }
		],
		hasMore: 1
	});
	const body = w.$('#translateExpertModal .modal-body');
	check('the detail is a .te-app with the mode label, the id head and the language heads', () => body.children('.te-app').length === 1 && body.find('.te-muted').first().text() === 'Both'
		&& body.find('thead th').map(function() { return w.$(this).text(); }).get().join('|') === 'product_id|Source (en-gb)|Target (uk-ua)');
	check('a value with <script> and <b> is text, no element is made', () => body.find('script').length === 0 && body.find('b').length === 0 && w.window.xss === undefined
		&& body.find('tbody tr').eq(0).find('td').eq(1).text().indexOf('<script>window.xss=1</script><b>x</b>') === 0);
	check('the id values are in the cells, a count above 1 shows as a muted multiplication', () => body.find('tbody tr').eq(0).find('td').eq(0).text() === '42'
		&& body.find('tbody tr').eq(0).find('td').eq(1).find('.te-muted').text() === ' ×3' && body.find('tbody tr').eq(1).find('.te-muted').length === 0);
	check('empty gets a muted badge, identical a warn badge, a different value neither', () => body.find('tbody tr').eq(0).find('.te-badge--muted').text() === 'empty'
		&& body.find('tbody tr').eq(0).find('.te-badge--warn').length === 0 && body.find('tbody tr').eq(1).find('.te-badge--warn').text() === 'identical'
		&& body.find('tbody tr').eq(2).find('.te-badge').length === 0);
	check('hasMore adds the hint line', () => body.find('p.te-muted').last().text() === 'Only 100.');
	check('another modal user gets the original title back', () => {
		w.showTranslateExpertModal('plain');
		return w.$('#translateExpertModalLabel').text() === 'Orig';
	});
	check('zero rows: the "No values" line, no table, no hint', () => {
		const node = w.teRenderSiteDetail({ mode: 'only_empty', rows: [], idColumns: ['a'], hasMore: 0 });
		return node.find('table').length === 0 && node.find('p').eq(1).text() === 'No values.' && node.text().indexOf('Only 100.') < 0 && node.find('p').eq(0).text() === 'Only empty';
	});
	w.$('tr[data-column="name"] .te-js-site-detail').trigger('click');
	calls.post[1].dfd.resolve({ success: 0, message: 'Bad table' });
	check('an error goes to #te-site-alerts and the modal hides', () => w.$('#te-site-alerts .te-alert__message').text() === 'Bad table' && w.teCalls.indexOf('modal:hide') >= 0);
	w.$('#te-site-alerts').empty();
	w.$('tr[data-column="name"] .te-js-site-detail').trigger('click');
	calls.post[2].dfd.reject(xhr({ status: 500, responseText: 'Fatal' }));
	check('a failed request shows the not-JSON alert', () => w.$('#te-site-alerts .te-alert__message').text() === 'Not JSON.');
	w.$('#te-site-alerts').empty();
	w.$('input[name="te-analization-mode"][value="both"]').prop('checked', true);
	w.$('#te-langbar-to').val('2');
}

function testSiteTranslate(w) {
	console.log('\nTable translation:');
	const calls = stubAjax(w);
	w.teSiteInit();
	let charCountCalls = 0;
	w.showTranslatedCharCount = function() { charCountCalls++; };
	w.$('#te-analization-table').val([]);
	w.$('#te-begin-analization').trigger('click');
	calls.get[0].dfd.resolve({
		success: 1, done: 1, target: TARGET, globalCharCount: 160, timeInSec: 0.1,
		tables: [
			tableBatch('oc_a', 1, 100, [['name', 40], ['description', 60]]),
			tableBatch('oc_b', 0, 50, [['title', 50]]),
			tableBatch('oc_c', 1, 0, []),
			tableBatch('oc_d', 1, 10, [['name', 10]])
		],
		lastProcessedTable: 'oc_d', lastProcessedColumn: 'name', lastProcessedOffset: 0
	});
	let asked = [];
	w.confirm = function(text) { asked.push(text); return false; };
	w.$('tr[data-column="name"][data-table="oc_a"] .te-js-site-translate').trigger('click');
	check('confirm false: no request, the confirmation names the table and column', () => calls.post.length === 0 && asked[0] === 'Translate oc_a.name (Ukrainian)?'
		&& w.teSiteState.running === false);
	w.confirm = function(text) { asked.push(text); return true; };
	w.$('tr[data-column="name"][data-table="oc_a"] .te-js-site-translate').trigger('click');
	check('a column: one POST with the column, the filters as 1 or left out', () => calls.post.length === 1 && calls.post[0].data.tableName === 'oc_a' && calls.post[0].data.textColumnName === 'name'
		&& /client_translate_expert\/translateTable&token=/.test(calls.post[0].url) && !('product_status' in calls.post[0].data) && calls.post[0].data.langIdFrom === '1');
	check('running: the panel has the title, one operation at a time (the analysis button does nothing)', () => w.teSiteState.running === true && w.teSiteState.mode === 'translate'
		&& w.$('#te-site-progress .te-progress__title').text() === 'Translating oc_a.name' && (w.$('#te-begin-analization').trigger('click'), calls.get.length === 1)
		&& w.$('#te-begin-analization').prop('disabled') === true);
	calls.post[0].dfd.resolve({ success: 1, done: 0, translatedCharCount: 70 });
	check('the counter adds translatedCharCount, the same job continues', () => calls.post.length === 2 && w.$('#te-site-progress .te-progress__counters').text() === 'Now: oc_a.nameTranslated: 70Request 1');
	calls.post[1].dfd.resolve({ success: 1, done: 1, translatedCharCount: '30' });
	check('the job is done: ok badge on the column row only, a log line, the end state, showTranslatedCharCount called once', () => w.$('tr[data-column="name"][data-table="oc_a"] .te-badge--ok').text() === 'done'
		&& w.$('tr[data-column="description"] .te-badge--ok').length === 0 && w.$('tr.te-table__group[data-table="oc_a"] .te-badge--ok').length === 0
		&& w.$('#te-site-progress .te-progress__line').text() === 'Done oc_a.name 100' && w.$('#te-site-progress').hasClass('te-progress--done')
		&& charCountCalls === 1 && w.$('#te-begin-analization').prop('disabled') === false && w.teSiteState.running === false);

	console.log('\nTranslate a table and Translate all:');
	calls.post.length = 0;
	w.$('tr.te-table__group[data-table="oc_a"] .te-js-site-translate').trigger('click');
	check('a table: textColumnName is empty', () => calls.post.length === 1 && calls.post[0].data.textColumnName === '' && calls.post[0].data.tableName === 'oc_a' && asked[asked.length - 1] === 'Translate oc_a (Ukrainian)?');
	calls.post[0].dfd.resolve({ success: 1, done: 1, translatedCharCount: 5 });
	check('a table job marks the group row', () => w.$('tr.te-table__group[data-table="oc_a"] .te-badge--ok').length === 1);
	calls.post.length = 0;
	charCountCalls = 0;
	w.$('#te-analization-result .te-js-site-translate-all').trigger('click');
	check('Translate all: the confirmation is the "all" text, the queue skips the unsupported and the 0-count tables', () => asked[asked.length - 1] === 'Translate all (Ukrainian)?' && calls.post.length === 1 && calls.post[0].data.tableName === 'oc_a'
		&& calls.post[0].data.textColumnName === '');
	calls.post[0].dfd.resolve({ success: 1, done: 1, translatedCharCount: 100 });
	check('after the first table the next one in the queue starts, the counter adds up', () => calls.post.length === 2 && calls.post[1].data.tableName === 'oc_d'
		&& w.$('#te-site-progress .te-progress__title').text() === 'Translating oc_d' && w.$('tr.te-table__group[data-table="oc_b"] .te-badge--ok').length === 0);
	calls.post[1].dfd.resolve({ success: 1, done: 1, translatedCharCount: 10 });
	check('the queue ends: 2 requests in all, counters 110, both groups marked, showTranslatedCharCount once at the end', () => calls.post.length === 2
		&& w.$('#te-site-progress .te-progress__counters').text() === 'Now: oc_dTranslated: 110Request 2' && w.$('tr.te-table__group[data-table="oc_d"] .te-badge--ok').length === 1
		&& w.$('tr.te-table__group[data-table="oc_c"] .te-badge--ok').length === 0 && charCountCalls === 1 && w.$('#te-site-progress').hasClass('te-progress--done'));

	console.log('\nTranslation: errors and stop:');
	calls.post.length = 0;
	charCountCalls = 0;
	w.$('tr.te-table__group[data-table="oc_d"] .te-js-site-translate').trigger('click');
	calls.post[0].dfd.resolve({ success: 0, message: 'Quota <i>over</i>', additional_message: 'x' });
	check('success 0: the alert in #te-site-alerts as text, the results stay, the end state error, showTranslatedCharCount called', () => w.$('#te-site-alerts .te-alert__message').text() === 'Quota <i>over</i>'
		&& w.$('#te-site-alerts .te-alert__message i').length === 0 && w.$('#te-analization-result tr.te-table__group').length === 4 && w.$('#te-site-progress').hasClass('te-progress--error')
		&& charCountCalls === 1 && w.teSiteState.running === false && w.$('#te-begin-analization').prop('disabled') === false);
	calls.post.length = 0;
	charCountCalls = 0;
	w.$('tr.te-table__group[data-table="oc_d"] .te-js-site-translate').trigger('click');
	calls.post[0].dfd.reject(xhr({ status: 500, responseText: 'Fatal' }));
	check('.fail(): the not-JSON alert, the results stay, the buttons work', () => w.$('#te-site-alerts .te-alert__message').text() === 'Not JSON.' && w.$('#te-analization-result tr.te-table__group').length === 4
		&& charCountCalls === 1 && w.$('#te-begin-analization').prop('disabled') === false);
	calls.post.length = 0;
	charCountCalls = 0;
	w.$('#te-analization-result .te-js-site-translate-all').trigger('click');
	w.$('#te-site-progress .te-js-stop').trigger('click');
	calls.post[0].dfd.resolve({ success: 1, done: 0, translatedCharCount: 3 });
	check('stop: no next request after the response, the panel is stopped, showTranslatedCharCount called', () => calls.post.length === 1 && w.$('#te-site-progress').hasClass('te-progress--stopped')
		&& charCountCalls === 1 && w.teSiteState.running === false);
	calls.post.length = 0;
	w.$('#te-analization-result .te-js-site-translate-all').trigger('click');
	w.$('#te-site-progress .te-js-stop').trigger('click');
	calls.post[0].dfd.resolve({ success: 1, done: 1, translatedCharCount: 3 });
	check('stop after a finished job: the next job of the queue does not start', () => calls.post.length === 1 && w.$('#te-site-progress').hasClass('te-progress--stopped'));
	w.$('#te-site-alerts').empty();
}

function testSiteLangbar(w) {
	console.log('\nLangbar, collapse, fallback anchor:');
	const bar = () => w.$('#te-langbar-app .te-langbar');
	w.teInitPage();
	check('init: the active tab is general, the langbar is hidden', () => bar().hasClass('te-hidden'));
	const shown = (id) => w.$(id).trigger('shown.bs.tab');
	check('jQuery event (BS3): the langbar shows on the 3 translation tabs', () => ['#t-site', '#t-loc', '#t-admin'].every((id) => {
		shown('#t-debug');
		const hiddenBefore = bar().hasClass('te-hidden');
		shown(id);
		return hiddenBefore && !bar().hasClass('te-hidden');
	}));
	check('jQuery event: it hides on the other tabs', () => ['#t-general', '#t-debug'].every((id) => {
		shown('#t-site');
		shown(id);
		return bar().hasClass('te-hidden');
	}));
	check('native event (BS5): it shows on a translation tab and hides on another', () => {
		const fire = (id) => w.document.querySelector(id).dispatchEvent(new w.CustomEvent('shown.bs.tab', { bubbles: true }));
		fire('#t-loc');
		const shownOnLoc = !bar().hasClass('te-hidden');
		fire('#t-debug');
		return shownOnLoc && bar().hasClass('te-hidden');
	});
	const w2 = makeSiteWindow(w.__jq, false, '#tab-admin-localization');
	w2.teInitPage();
	check('init from the active tab: a translation tab shows the langbar at once', () => !w2.$('#te-langbar-app .te-langbar').hasClass('te-hidden'));

	let changes = 0;
	w.$('#te-langbar-from, #te-langbar-to').on('change', function() { changes++; });
	w.$('.te-js-langbar-swap').trigger('click');
	check('swap: the 2 values change places and change fires on both', () => w.$('#te-langbar-from').val() === '2' && w.$('#te-langbar-to').val() === '1' && changes === 2);
	check('swap: the removal button label shows the new target language', () => w.$('#te-begin-removing_data_from_db').text() === 'Remove - English');
	w.$('.te-js-langbar-swap').trigger('click');
	check('swap again goes back', () => w.$('#te-langbar-from').val() === '1' && w.$('#te-langbar-to').val() === '2' && w.$('#te-begin-removing_data_from_db').text() === 'Remove - Ukrainian');
	w.$('#te-langbar-from').val('1');
	w.$('#te-langbar-to').val('1').trigger('change');
	w.$('.te-js-langbar-swap').trigger('click');
	check('swap with the same language in both: both stay the same', () => w.$('#te-langbar-from').val() === '1' && w.$('#te-langbar-to').val() === '1');
	w.$('#te-langbar-to').val('2').trigger('change');

	w.$('#cbtn').trigger('click');
	check('the collapse button opens its block', () => !w.$('#te-site-more-filters').hasClass('te-hidden') && w.$('#cbtn').attr('aria-expanded') === 'true');
	w.$('#cbtn').trigger('click');
	check('the collapse button closes it again', () => w.$('#te-site-more-filters').hasClass('te-hidden') && w.$('#cbtn').attr('aria-expanded') === 'false');

	w.hideTranslateExpertModal = function() {};
	w.showTranslateExpertErrorMessage({ message: 'Boom' }, null);
	check('the fallback anchor of showTranslateExpertErrorMessage is #te-site-alerts', () => w.$('#te-site-alerts').prev('.alert-danger').length === 1);
}

function testSiteBs5(w) {
	console.log('\nTab "Website content" on Bootstrap 5:');
	const calls = stubAjax(w);
	w.teSiteInit();
	w.$('#te-begin-analization').trigger('click');
	check('the stop button has the BS5 class', () => w.$('#te-site-progress .te-js-stop').hasClass('btn-secondary'));
	calls.get[0].dfd.resolve({ success: 1, done: 1, target: TARGET, globalCharCount: 9, timeInSec: 0.1, tables: [tableBatch('oc_a', 1, 9, [['name', 9]])], lastProcessedTable: 'oc_a', lastProcessedColumn: 'name', lastProcessedOffset: 0 });
	check('the row buttons have the BS5 class', () => w.$('tr[data-column="name"] .te-js-site-detail').hasClass('btn-outline-secondary'));
	w.teCalls = [];
	w.$('tr[data-column="name"] .te-js-site-detail').trigger('click');
	check('the detail opens the module modal with Bootstrap 5', () => w.teCalls.indexOf('Modal.show') >= 0);
	w.$('#te-site-alerts').empty();
}

// ---- Tabs "Catalog files" and "Admin files" ----

const LANG_FROM = { language_id: 1, code: 'en-gb', name: 'English', image: '' };
const locKey = (key, text) => ({ key: key, text: text, fullText: "$_['" + key + "'] = '" + text + "';", expression: 0 });
const locExpr = (key, code) => ({ key: key, text: '', fullText: "$_['" + key + "'] = " + code + ';', expression: 1 });
const locFile = (pathFrom, keys, extra) => Object.assign({
	root: 'core', rootName: '', pathFrom: pathFrom, pathTo: pathFrom, targetExists: 1, keys: keys,
	contentFrom: '<?php\n' + keys.map((k) => k.fullText).join('\n') + '\n', contentTo: '<?php\n// head\n'
}, extra || {});
const locBatch = (files, next, done, totals) => {
	const data = { success: 1, done: done ? 1 : 0, nextFileIndex: next, languageFrom: LANG_FROM, languageTo: TARGET, files: files };
	if (totals) {
		data.totals = totals;
	}
	return data;
};
const LOC_TOTALS = { fileCount: 12, needToTranslateFileCount: 2, needToTranslateValueCount: 5, needToTranslateCharCount: 1234 };

function locStart(w, calls, scope) {
	const id = scope === 'admin' ? '#te-begin-admin-localization-analization' : '#te-begin-localization-analization';
	w.$(id).trigger('click');
	return calls.get[calls.get.length - 1];
}

// Runs one analysis with a single batch (done) and returns after the end of the run.
function locLoad(w, calls, files, scope) {
	const req = locStart(w, calls, scope || 'catalog');
	req.dfd.resolve(locBatch(files, files.length, true, { fileCount: files.length, needToTranslateFileCount: files.length, needToTranslateValueCount: 1, needToTranslateCharCount: 1 }));
}

function testLocAnalysis(w) {
	console.log('\nLocalization analysis loop:');
	const calls = stubAjax(w);
	w.teLocInit();
	w.$('#te-begin-localization-analization').trigger('click');
	w.$('#te-begin-localization-analization').trigger('click');
	check('one click sends one request, a second click while running sends none', () => calls.get.length === 1);
	check('request: route, token, language ids from the langbar, nextFileIndex 0, scope', () => /route=extension\/module\/client_translate_expert\/analizeLocalization&token=/.test(calls.get[0].url)
		&& calls.get[0].data.langIdFrom == 1 && calls.get[0].data.langIdTo == 2 && calls.get[0].data.nextFileIndex === 0 && calls.get[0].data.scope === 'catalog');
	check('running: Analyze is disabled, the panel shows, the result has the table skeleton', () => w.$('#te-begin-localization-analization').prop('disabled') === true
		&& !w.$('#te-loc-progress-catalog').hasClass('te-hidden') && w.$('#te-loc-progress-catalog .te-js-stop').length === 1 && w.$('#te-loc-result-catalog table.te-table').length === 1);

	const f0 = locFile('/a.php', [locKey('a', 'A')]);
	const f1 = locFile('/b.php', []);
	const f2 = locFile('/c.php', [locKey('c', 'C')], { targetExists: 0, contentTo: '' });
	calls.get[0].dfd.resolve(locBatch([f0, f1, f2], 10, false, LOC_TOTALS));
	const rows = () => w.$('#te-loc-result-catalog tr.te-loc-file');
	const row0 = rows()[0];
	check('batch 0: only files with keys are rows, data-index is the server index', () => rows().length === 2 && w.$(rows()[0]).attr('data-index') === '0' && w.$(rows()[1]).attr('data-index') === '2');
	check('batch 0: badges partial and missing, key count, toggle button', () => w.$(rows()[0]).find('.te-badge--warn').text() === 'partial' && w.$(rows()[1]).find('.te-badge--error').text() === 'missing'
		&& w.$(rows()[0]).children('td.te-num').eq(0).text() === '1' && w.$(rows()[0]).children('td.te-loc-chars').text() === '0' && w.$(rows()[0]).find('button.te-table__expand').attr('aria-expanded') === 'false');
	check('batch 0: totals text and progress counters', () => !w.$('#te-loc-totals-catalog').hasClass('te-hidden') && w.$('#te-loc-totals-catalog').text() === 'Files: 12To translate: 2Values: 5Chars: 1 234'
		&& w.$('#te-loc-progress-catalog .te-progress__counters').text() === 'Files 10 of 12Request 1');
	check('batch 0: the second request has nextFileIndex 10 and the same scope', () => calls.get.length === 2 && calls.get[1].data.nextFileIndex === 10 && calls.get[1].data.scope === 'catalog');
	check('batch 0: the per-file buttons are disabled while the analysis runs', () => w.$('#te-loc-result-catalog .te-js-loc-translate').length === 2 && w.$('#te-loc-result-catalog .te-js-loc-translate-save').length === 2 && w.$('#te-loc-result-catalog .te-js-loc-translate, #te-loc-result-catalog .te-js-loc-translate-save, #te-loc-result-catalog .te-js-loc-save').filter(function() { return !this.disabled; }).length === 0);
	check('batch 0: the per-key translate buttons are disabled while the analysis runs', () => w.$('#te-loc-result-catalog .te-js-loc-key-translate').length > 0 && w.$('#te-loc-result-catalog .te-js-loc-key-translate').filter(function() { return !this.disabled; }).length === 0);
	check('batch 0: the total row is the second head row, with the keys to translate of the totals and a hidden Translate all', () => w.$('#te-loc-result-catalog thead tr').length === 2
		&& w.$('#te-loc-result-catalog thead tr').eq(1).hasClass('te-table__total') && w.$('#te-loc-result-catalog thead .te-loc-total').text() === '5'
		&& w.$('#te-loc-result-catalog thead tr').eq(0).children('th').map(function() { return w.$(this).text(); }).get().join('|') === 'File|Keys|Chars|State'
		&& w.$('#te-loc-result-catalog thead .te-table__total td').eq(2).is('.te-loc-total-chars') && w.$('#te-loc-result-catalog thead .te-loc-total-chars').text() === '1 234'
		&& w.$('#te-loc-result-catalog thead .te-table__total #te-loc-translate-all-catalog.te-js-loc-translate-all').length === 1
		&& w.$('#te-loc-translate-all-catalog').hasClass('te-hidden') && w.$('#te-loc-result-catalog tfoot').length === 0);
	calls.get[1].dfd.resolve(locBatch([locFile('/d.php', [locKey('d', 'D')])], 12, true, null));
	check('batch 1 (no totals): the nodes of batch 0 stay, a new row is added, the totals stay', () => rows().length === 3 && rows()[0] === row0 && w.$(rows()[2]).attr('data-index') === '3'
		&& w.$('#te-loc-totals-catalog').text().indexOf('Files: 12') === 0);
	check('done: the per-key translate buttons are enabled', () => w.$('#te-loc-result-catalog .te-js-loc-key-translate').filter(function() { return this.disabled; }).length === 0);
	check('done: the per-file buttons of all rows are enabled', () => w.$('#te-loc-result-catalog .te-js-loc-save').length === 3 && w.$('#te-loc-result-catalog .te-js-loc-translate, #te-loc-result-catalog .te-js-loc-save').filter(function() { return this.disabled; }).length === 0);
	check('done: te-progress--done, Analyze enabled, Translate all visible, no alert', () => w.$('#te-loc-progress-catalog').hasClass('te-progress--done') && w.$('#te-begin-localization-analization').prop('disabled') === false
		&& !w.$('#te-loc-translate-all-catalog').hasClass('te-hidden') && w.$('#te-loc-alerts-catalog .te-alert').length === 0 && w.teLocState.catalog.running === false);

	// stop between batches
	let req = locStart(w, calls, 'catalog');
	w.$('#te-loc-progress-catalog .te-js-stop').trigger('click');
	const countBefore = calls.get.length;
	req.dfd.resolve(locBatch([locFile('/a.php', [locKey('a', 'A')])], 10, false, LOC_TOTALS));
	check('stop: te-progress--stopped, no further request, the rows stay', () => calls.get.length === countBefore && w.$('#te-loc-progress-catalog').hasClass('te-progress--stopped')
		&& w.$('#te-loc-result-catalog tr.te-loc-file').length === 1 && w.$('#te-begin-localization-analization').prop('disabled') === false);

	// errors
	req = locStart(w, calls, 'catalog');
	req.dfd.resolve(locBatch([locFile('/a.php', [locKey('a', 'A')])], 10, false, LOC_TOTALS));
	calls.get[calls.get.length - 1].dfd.resolve({ success: 0, error_code: 3002, message: 'Boom', additional_message: 'More' });
	check('success 0: an error alert in the tab, te-progress--error, the rows stay, Analyze enabled', () => w.$('#te-loc-alerts-catalog .te-alert--error .te-alert__message').text() === 'Boom'
		&& w.$('#te-loc-progress-catalog').hasClass('te-progress--error') && w.$('#te-loc-result-catalog tr.te-loc-file').length === 1 && w.$('#te-begin-localization-analization').prop('disabled') === false);
	req = locStart(w, calls, 'catalog');
	check('a new analysis clears the alerts and the rows', () => w.$('#te-loc-alerts-catalog .te-alert').length === 0 && w.$('#te-loc-result-catalog tr.te-loc-file').length === 0);
	req.dfd.reject(xhr({ status: 500, responseText: JSON.stringify({ success: 0, message: 'Server down' }), responseJSON: { success: 0, message: 'Server down' } }));
	check('.fail with a jqXHR: the same error alert and state', () => w.$('#te-loc-alerts-catalog .te-alert__message').text() === 'Server down' && w.$('#te-loc-progress-catalog').hasClass('te-progress--error'));
	req = locStart(w, calls, 'catalog');
	req.dfd.resolve('<html><title>Login</title></html>');
	check('a non-JSON response: the not-JSON alert', () => w.$('#te-loc-alerts-catalog .te-alert__message').text() === 'Not JSON.' && w.$('#te-loc-progress-catalog').hasClass('te-progress--error'));

	// no keys at all
	req = locStart(w, calls, 'catalog');
	req.dfd.resolve(locBatch([locFile('/b.php', [])], 1, true, { fileCount: 1, needToTranslateFileCount: 0, needToTranslateValueCount: 0, needToTranslateCharCount: 0 }));
	check('no file has keys: an info alert, no Translate all, no table row', () => w.$('#te-loc-alerts-catalog .te-alert--info .te-alert__message').text() === 'All translated.'
		&& w.$('#te-loc-translate-all-catalog').hasClass('te-hidden') && w.$('#te-loc-result-catalog tr.te-loc-file').length === 0 && w.$('#te-loc-progress-catalog').hasClass('te-progress--done'));

	// nextFileIndex does not grow
	const before = calls.get.length;
	req = locStart(w, calls, 'catalog');
	req.dfd.resolve(locBatch([locFile('/a.php', [locKey('a', 'A')])], 0, false, LOC_TOTALS));
	check('nextFileIndex that does not grow ends the run, no request loop', () => calls.get.length === before + 1 && w.$('#te-loc-progress-catalog').hasClass('te-progress--done'));

	// roots and renamed files
	req = locStart(w, calls, 'catalog');
	req.dfd.resolve(locBatch([
		locFile('/a.php', [locKey('a', 'A')]),
		locFile('/p/x.php', [locKey('x', 'X')], { root: 'ext:opencart', rootName: 'opencart' }),
		locFile('/p/y.php', [locKey('y', 'Y')], { root: 'ext:opencart', rootName: 'opencart' }),
		locFile('/old.php', [locKey('o', 'O')], { pathTo: '/new.php' }),
		locFile('/z.php', [locKey('z', 'Z')])
	], 5, true, { fileCount: 5, needToTranslateFileCount: 5, needToTranslateValueCount: 5, needToTranslateCharCount: 5 }));
	check('OC4 roots: one group row with the extension name, none for core files', () => w.$('#te-loc-result-catalog tr.te-loc-root').length === 1 && w.$('#te-loc-result-catalog tr.te-loc-root').text() === 'Extension: opencart'
		&& w.$('#te-loc-result-catalog tr.te-loc-root td').attr('colspan') === '4');
	check('a renamed main file shows from and to', () => w.$('#te-loc-result-catalog tr.te-loc-file').eq(3).find('.te-table__name').text() === '/old.php → /new.php'
		&& w.$('#te-loc-result-catalog tr.te-loc-file').eq(0).find('.te-table__name').text() === '/a.php');

	// admin tab is independent
	const catalogFiles = w.teLocState.catalog.files.length;
	req = locStart(w, calls, 'admin');
	check('admin: scope admin in the request, its own panel and table, Analyze of catalog stays enabled', () => req.data.scope === 'admin' && w.$('#te-loc-result-admin table.te-table').length === 1
		&& w.$('#te-begin-admin-localization-analization').prop('disabled') === true && w.$('#te-begin-localization-analization').prop('disabled') === false);
	req.dfd.resolve(locBatch([locFile('/adm.php', [locKey('q', 'Q')])], 1, true, { fileCount: 1, needToTranslateFileCount: 1, needToTranslateValueCount: 1, needToTranslateCharCount: 1 }));
	check('admin: rows only in the admin result, the catalog state is unchanged', () => w.$('#te-loc-result-admin tr.te-loc-file').length === 1 && w.teLocState.catalog.files.length === catalogFiles
		&& w.$('#te-loc-result-catalog tr.te-loc-file .te-table__name').text().indexOf('/adm.php') === -1 && w.$('#te-loc-progress-admin').hasClass('te-progress--done'));
}

function testLocDiff(w) {
	console.log('\nLocalization key list and source file:');
	const calls = stubAjax(w);
	w.teLocInit();
	w.teInitCollapse();
	const kb = { key: 'b', text: '', fullText: "$_['b'] = sprintf('<b>%s</b>', $x);", expression: 1 };
	const ka = { key: 'a', text: "It\\'s", fullText: "$_['a'] = 'It\\'s';", expression: 0 };
	const kc = { key: 'c', text: 'Gone', fullText: "$_['c'] = 'Gone';", expression: 0 };
	const file = locFile('/a.php', [ka, kb, kc], { contentFrom: "<?php\n// <i>x</i>\n" + ka.fullText + "\n" + kb.fullText + "\n$_['other'] = 'z';\n" });
	locLoad(w, calls, [file]);
	const detail = () => w.$('#te-loc-result-catalog tr.te-table__detail[data-index="0"]');
	check('the detail row is hidden at first', () => detail().length === 1 && detail().hasClass('te-hidden'));
	w.$('#te-loc-result-catalog .te-js-loc-toggle').trigger('click');
	check('toggle shows the detail row and sets aria-expanded', () => !detail().hasClass('te-hidden') && w.$('#te-loc-result-catalog .te-js-loc-toggle').attr('aria-expanded') === 'true');
	check('the head shows the 2 language codes', () => detail().find('.te-diff__head span').length === 2 && detail().find('.te-diff__head span').eq(0).text() === 'en-gb' && detail().find('.te-diff__head span').eq(1).text() === 'uk-ua');
	check('a literal: the source shows the unescaped text, the target is an empty textarea with the key', () => detail().find('.te-diff__source').eq(0).text() === "It's"
		&& detail().find('textarea.te-diff__target[data-key="a"]').length === 1 && detail().find('textarea.te-diff__target[data-key="a"]').val() === '');
	check('an expression: the statement as text in both cells, a muted badge, no textarea', () => detail().find('.te-diff__source--code').text() === kb.fullText
		&& detail().find('.te-diff__target--code[data-key="b"]').text() === kb.fullText + 'PHP code' && detail().find('.te-diff__target--code .te-badge--muted').length === 1
		&& detail().find('textarea[data-key="b"]').length === 0 && detail().find('b').length === 0);
	const pre = detail().find('pre.te-loc-source');
	check('the source block is hidden at first', () => pre.hasClass('te-hidden') && detail().find('.te-js-collapse').attr('aria-expanded') === 'false');
	detail().find('.te-js-collapse').trigger('click');
	check('the collapse button opens the source block', () => !pre.hasClass('te-hidden') && detail().find('.te-js-collapse').attr('aria-expanded') === 'true');
	check('marks: one for each statement found, text only (no HTML from the file)', () => pre.find('mark.te-loc-hl').length === 2 && pre.find('mark').eq(0).text() === ka.fullText
		&& pre.find('mark').eq(1).text() === kb.fullText && pre.find('b, i').length === 0 && pre.text() === file.contentFrom);
	check('a statement that is not in the content gets no mark', () => pre.find('mark').filter(function() { return w.$(this).text() === kc.fullText; }).length === 0);
	check('the bar with Source file, Translate file and Save file is the first child of the key list, the source file block is under it, then the head and the keys', () => detail().find('.te-diff').children().eq(0).is('.te-diff__bar')
		&& detail().find('.te-diff__bar').children().length === 3 && detail().find('.te-diff__bar .te-js-collapse').length === 1 && detail().find('.te-diff__bar .te-js-loc-translate').length === 1 && detail().find('.te-diff__bar .te-js-loc-save').length === 1
		&& detail().find('.te-diff').children().eq(1).is('pre.te-loc-source') && detail().find('.te-diff').children().eq(2).is('.te-diff__head') && detail().find('.te-diff').children().eq(3).is('.te-diff__key'));
	check('the buttons Translate file and Save file have the row index', () => detail().find('.te-js-loc-translate').attr('data-index') === '0' && detail().find('.te-js-loc-save').attr('data-index') === '0');
	w.$('#te-loc-result-catalog .te-js-loc-toggle').trigger('click');
	check('a second click hides the detail row', () => detail().hasClass('te-hidden') && w.$('#te-loc-result-catalog .te-js-loc-toggle').attr('aria-expanded') === 'false');
}

function testLocFileHelpers(w) {
	console.log('\nLocalization file helpers:');
	check('teLocQuote: single and double quote, default', () => w.teLocQuote("$_['a'] = 'x';") === "'" && w.teLocQuote('$_["a"] = "x";') === '"' && w.teLocQuote('$_[\'a\'] = "x";') === '"' && w.teLocQuote('') === "'");
	check("teLocUnescape single quote: \\' \\\\ and \\\\' in one pass", () => w.teLocUnescape("It\\'s", "'") === "It's" && w.teLocUnescape('a\\\\b', "'") === 'a\\b'
		&& w.teLocUnescape("a\\\\'", "'") === "a\\'" && w.teLocUnescape('a\\nb', "'") === 'a\\nb');
	check('teLocUnescape double quote: \\" \\n \\t \\$ \\\\', () => w.teLocUnescape('say \\"hi\\"', '"') === 'say "hi"' && w.teLocUnescape('a\\nb\\tc', '"') === 'a\nb\tc'
		&& w.teLocUnescape('\\$x', '"') === '$x' && w.teLocUnescape('a\\\\b', '"') === 'a\\b');
	check('teLocEscape: single quote and backslash', () => w.teLocEscape("It's") === "It\\'s" && w.teLocEscape('a\\b') === 'a\\\\b' && w.teLocEscape('plain') === 'plain');
	check('teLocStatement', () => w.teLocStatement('k', "It's") === "$_['k'] = 'It\\'s';");
	check('teLocDecodeEntities: numeric, named, ampersand, plain', () => w.teLocDecodeEntities('It&#39;s') === "It's" && w.teLocDecodeEntities('&quot;a&quot;') === '"a"'
		&& w.teLocDecodeEntities('a &amp; b') === 'a & b' && w.teLocDecodeEntities('plain') === 'plain');
	check('teLocDecodeEntities: a source with an entity keeps the answer verbatim, a source without entity is decoded', () => w.teLocDecodeEntities('&lt;b&gt; &#39;', '&lt;b&gt; x') === '&lt;b&gt; &#39;'
		&& w.teLocDecodeEntities('&lt;b&gt; &#39;', 'plain <b> text') === "<b> '" && w.teLocDecodeEntities('It&#39;s', 'a & b; c') === "It's");
	check('teLocUnescape double quote: \\v \\e \\f', () => w.teLocUnescape('a\\vb\\ec\\fd', '"') === 'a\vb\x1bc\fd');
	check('teLocUnescape double quote: octal', () => w.teLocUnescape('\\101\\7\\0x', '"') === 'A\x07\x00x' && w.teLocUnescape('\\1011', '"') === 'A1');
	check('teLocUnescape double quote: hex', () => w.teLocUnescape('\\x41\\x4a\\xZ', '"') === 'AJ\\xZ' && w.teLocUnescape('\\x4', '"') === '\x04');
	check('teLocUnescape double quote: unicode', () => w.teLocUnescape('\\u{41}\\u{1F600}\\u{e9}', '"') === 'A\u{1F600}\u00e9' && w.teLocUnescape('\\u{zz}', '"') === '\\u{zz}');
	check('teLocUnescape double quote: an unknown escape keeps the backslash, a single pass', () => w.teLocUnescape('\\q \\\\n \\\\101', '"') === '\\q \\n \\101' && w.teLocUnescape("\\v", "'") === '\\v');
	check('teLocDecodeEntities: a tag stays text and no script runs', () => w.teLocDecodeEntities('<img src=x onerror=window.teHit=1>&lt;b&gt;') === '<img src=x onerror=window.teHit=1><b>' && !w.teHit
		&& w.teLocDecodeEntities('<script>window.teHit=1</script>') === '<script>window.teHit=1</script>' && !w.teHit);
	const keys = [locKey('a', 'A'), locExpr('e', "sprintf('x')"), locKey('b', 'B')];
	check('teLocBuildFile: a missing target starts with <?php and a line break only', () => w.teLocBuildFile({ keys: keys, contentTo: '' }, { a: 'AA', b: "B'" }) === "<?php\n$_['a'] = 'AA';\n$_['e'] = sprintf('x');\n$_['b'] = 'B\\'';\n");
	check('teLocBuildFile: an existing target without ?> gets a blank line and the lines', () => w.teLocBuildFile({ keys: keys, contentTo: "<?php\n$_['z'] = 'Z';\n\n" }, { a: 'AA' }) === "<?php\n$_['z'] = 'Z';\n\n$_['a'] = 'AA';\n$_['e'] = sprintf('x');\n");
	check('teLocBuildFile: a target that ends with ?> gets a new <?php', () => w.teLocBuildFile({ keys: keys, contentTo: "<?php\n$_['z'] = 'Z';\n?>\n" }, { a: 'AA' }) === "<?php\n$_['z'] = 'Z';\n?>\n\n<?php\n$_['a'] = 'AA';\n$_['e'] = sprintf('x');\n");
	check('teLocBuildFile: an empty value is skipped', () => w.teLocBuildFile({ keys: keys, contentTo: '' }, { a: '  ', b: 'B' }) === "<?php\n$_['e'] = sprintf('x');\n$_['b'] = 'B';\n");
	check('teLocBuildFile: no line gives null', () => w.teLocBuildFile({ keys: [locKey('a', 'A')], contentTo: '<?php\n' }, {}) === null && w.teLocBuildFile({ keys: [], contentTo: '' }, { a: 'x' }) === null);
	check('teLocBuildFile: the same input gives the same output (no duplicate on a second save)', () => {
		const file = { keys: keys, contentTo: "<?php\n$_['z'] = 'Z';\n" };
		return w.teLocBuildFile(file, { a: 'AA' }) === w.teLocBuildFile(file, { a: 'AA' });
	});
	check('teLocBuildFile: a ?> inside a literal gives no second <?php', () => w.teLocBuildFile({ keys: keys, contentTo: "<?php\n$_['z'] = 'see ?> here';\n" }, { a: 'AA' }) === "<?php\n$_['z'] = 'see ?> here';\n\n$_['a'] = 'AA';\n$_['e'] = sprintf('x');\n");
	check('teLocBuildFile: a whitespace-only target starts with <?php', () => w.teLocBuildFile({ keys: keys, contentTo: ' \n\t\n' }, { a: 'AA' }) === "<?php\n$_['a'] = 'AA';\n$_['e'] = sprintf('x');\n");
	check('the content ends with one line break', () => /[^\n]\n$/.test(w.teLocBuildFile({ keys: keys, contentTo: '' }, { a: 'AA' })));
}

function testLocTranslateSave(w) {
	console.log('\nLocalization: translate and save one file:');
	const calls = stubAjax(w);
	w.teLocInit();
	let charCountCalls = 0;
	w.showTranslatedCharCount = function() { charCountCalls++; };
	const f0 = locFile('/a.php', [locKey('a', '  Hello  '), locKey('b', "It\\'s"), locExpr('c', "sprintf('x')"), locKey('d', '')], { contentTo: '' });
	const f1 = locFile('/b.php', [locKey('e', 'Empty')]);
	const f2 = locFile('/c.php', [locKey('f', 'Fail')]);
	locLoad(w, calls, [f0, f1, f2]);
	w.$('#te-langbar-from').val('2');
	w.$('#te-langbar-to').val('1');
	const row = (i) => w.$('#te-loc-result-catalog tr.te-table__detail[data-index="' + i + '"]');
	const ta = (i, key) => row(i).find('textarea[data-key="' + key + '"]');
	row(0).find('.te-js-loc-translate').trigger('click');
	const t = calls.post[0];
	check('translate file: route, the languages of the analysis (not the langbar), format html, texts of the literals only, trimmed and unescaped', () => /client_translate_expert\/translate&token=/.test(t.url) && !/translateTable/.test(t.url)
		&& t.data.source == 1 && t.data.target == 2 && t.data.format === 'html' && t.data.textArr === JSON.stringify(['Hello', "It's"]));
	check('the clicked button is disabled until the response', () => row(0).find('.te-js-loc-translate').prop('disabled') === true);
	t.dfd.resolve({ success: 1, texts: ['Hi &amp; co', 'It&#39;s <b>x</b>'] });
	check('the textareas get the decoded texts, the char count is shown again, the button works again', () => ta(0, 'a').val() === 'Hi & co' && ta(0, 'b').val() === "It's <b>x</b>"
		&& charCountCalls === 1 && row(0).find('.te-js-loc-translate').prop('disabled') === false);

	row(0).find('.te-js-loc-save').trigger('click');
	const s = calls.post[1];
	const expected = w.teLocBuildFile(f0, { a: 'Hi & co', b: "It's <b>x</b>" });
	check('save: route, content from teLocBuildFile, the target language of the analysis, path, scope, root', () => /client_translate_expert\/saveLocalizationFile&token=/.test(s.url)
		&& s.data.fileContent === expected && s.data.langId == 2 && s.data.path === '/a.php' && s.data.scope === 'catalog' && s.data.root === 'core'
		&& expected === "<?php\n$_['a'] = 'Hi & co';\n$_['b'] = 'It\\'s <b>x</b>';\n$_['c'] = sprintf('x');\n");
	s.dfd.resolve({ success: 1 });
	check('saved: badge saved, a success alert with the path', () => w.$('#te-loc-result-catalog tr.te-loc-file').eq(0).find('.te-badge--ok').text() === 'saved'
		&& w.$('#te-loc-alerts-catalog .te-alert--success .te-alert__message').text() === 'Saved: /a.php');
	row(0).find('.te-js-loc-save').trigger('click');
	check('a second save sends the same content', () => calls.post.length === 3 && calls.post[2].data.fileContent === expected);
	calls.post[2].dfd.resolve({ success: 1 });

	const posts = calls.post.length;
	row(1).find('.te-js-loc-save').trigger('click');
	check('an empty file: an info alert with the path, no request', () => calls.post.length === posts && w.$('#te-loc-alerts-catalog .te-alert--info .te-alert__message').text() === 'Nothing: /b.php'
		&& row(1).find('.te-js-loc-save').prop('disabled') === false);
	ta(2, 'f').val('Fehler');
	row(2).find('.te-js-loc-save').trigger('click');
	calls.post[calls.post.length - 1].dfd.resolve({ success: 0, message: 'Cannot write' });
	check('save with success 0: an error alert, the badge stays', () => w.$('#te-loc-alerts-catalog .te-alert--error .te-alert__message').text() === 'Cannot write'
		&& w.$('#te-loc-result-catalog tr.te-loc-file').eq(2).find('.te-badge--warn').length === 1 && row(2).find('.te-js-loc-save').prop('disabled') === false);
	row(2).find('.te-js-loc-translate').trigger('click');
	calls.post[calls.post.length - 1].dfd.reject(xhr({ status: 500, responseText: 'oops' }));
	check('translate .fail: an error alert and the button works again', () => w.$('#te-loc-alerts-catalog .te-alert--error').length === 2 && row(2).find('.te-js-loc-translate').prop('disabled') === false);
	w.$('#te-langbar-from').val('1');
	w.$('#te-langbar-to').val('2');

	locLoad(w, calls, [locFile('/ent.php', [locKey('p', '&lt;b&gt; x'), locKey('q', 'plain')])]);
	w.$('#te-loc-result-catalog tr.te-table__detail').eq(0).find('.te-js-loc-translate').trigger('click');
	calls.post[calls.post.length - 1].dfd.resolve({ success: 1, texts: ['&lt;b&gt; &#39;y', 'It&#39;s'] });
	const eta = (key) => w.$('#te-loc-result-catalog tr.te-table__detail').eq(0).find('textarea[data-key="' + key + '"]').val();
	check('a source with an entity keeps the Google value verbatim, a source without entity is decoded', () => eta('p') === '&lt;b&gt; &#39;y' && eta('q') === "It's");

	locLoad(w, calls, [locFile('/race.php', [locKey('r', 'R')])]);
	w.$('#te-loc-result-catalog tr.te-table__detail').eq(0).find('textarea[data-key="r"]').val('RR');
	w.$('#te-loc-result-catalog tr.te-table__detail').eq(0).find('.te-js-loc-save').trigger('click');
	const raceSave = calls.post[calls.post.length - 1];
	let thrown = null;
	w.addEventListener('error', function(e) { thrown = e; });
	w.$('#te-begin-localization-analization').trigger('click');
	try {
		raceSave.dfd.resolve({ success: 1 });
	} catch (e) {
		thrown = e;
	}
	check('save, then a new analysis before the answer: no exception, the success alert names the path', () => thrown === null && w.$('#te-loc-alerts-catalog .te-alert--success .te-alert__message').text() === 'Saved: /race.php');
	calls.get[calls.get.length - 1].dfd.resolve(locBatch([], 0, true, null));

	// The translate button of one key
	locLoad(w, calls, [locFile('/key.php', [locKey('k1', 'One'), locKey('k2', "Two\\'s"), locExpr('k3', "sprintf('x')"), locKey('k4', '')])]);
	const krow = w.$('#te-loc-result-catalog tr.te-table__detail').eq(0);
	const kbtn = (key) => krow.find('.te-js-loc-key-translate[data-key="' + key + '"]');
	check('each literal key has a translate button before its textarea, with the field icon, an expression has none', () => kbtn('k1').length === 1 && kbtn('k2').length === 1 && kbtn('k4').length === 1 && kbtn('k3').length === 0
		&& kbtn('k1').next().is('textarea[data-key="k1"]') && kbtn('k1').attr('title') === 'Translate'
		&& /view\/image\/translate_expert_client\/translate-icon-32\.png/.test(kbtn('k1').attr('style')));
	krow.find('textarea[data-key="k1"]').val('hand made');
	const kposts = calls.post.length;
	kbtn('k2').trigger('click');
	const kp = calls.post[calls.post.length - 1];
	check('key button: one request with only the text of this key, unescaped, the languages of the analysis', () => calls.post.length === kposts + 1 && /client_translate_expert\/translate&token=/.test(kp.url)
		&& kp.data.textArr === JSON.stringify(["Two\\'s".replace("\\'", "'")]) && kp.data.source == 1 && kp.data.target == 2 && kp.data.format === 'html'
		&& kbtn('k2').prop('disabled') === true);
	kp.dfd.resolve({ success: 1, texts: ['Zwei&#39;s'] });
	check('key button: only its textarea changes (decoded), the hand-made value of another key stays, the button works again', () => krow.find('textarea[data-key="k2"]').val() === "Zwei's"
		&& krow.find('textarea[data-key="k1"]').val() === 'hand made' && kbtn('k2').prop('disabled') === false);
	const kposts2 = calls.post.length;
	kbtn('k4').trigger('click');
	check('key button of an empty value: no request', () => calls.post.length === kposts2 && kbtn('k4').prop('disabled') === false);
	kbtn('k1').trigger('click');
	calls.post[calls.post.length - 1].dfd.resolve({ success: 0, message: 'Key failed' });
	check('key button with success 0: an error alert, the textarea keeps its value, the button works again', () => w.$('#te-loc-alerts-catalog .te-alert--error .te-alert__message').last().text() === 'Key failed'
		&& krow.find('textarea[data-key="k1"]').val() === 'hand made' && kbtn('k1').prop('disabled') === false);

	// The Translate and save file button of the file row
	locLoad(w, calls, [locFile('/account/account.php', [locKey('heading_title', 'My Account')], { targetExists: 0, contentTo: '', charCount: 1500 })]);
	const frow = w.$('#te-loc-result-catalog tr.te-loc-file').eq(0);
	const fbtn = frow.find('.te-js-loc-translate-save');
	const fdetail = w.$('#te-loc-result-catalog tr.te-table__detail').eq(0);
	check('file row: the third cell holds the characters to translate of the file (charCount), the key list spans 4 columns', () => frow.children('td').eq(1).text() === '1'
		&& frow.children('td').eq(2).is('.te-num.te-loc-chars') && frow.children('td').eq(2).text() === '1 500' && fdetail.children('td').attr('colspan') === '4');
	check('file row: a Translate and save file button in the third cell (under Translate all), before the state badge, no Translate file button in the row', () => fbtn.length === 1 && fbtn.text() === 'Translate and save file' && fbtn.attr('data-index') === '0'
		&& frow.children('td').eq(3).children().eq(0).is(fbtn) && frow.children('td').eq(3).children().eq(1).find('.te-badge--error').length === 1 && frow.find('.te-js-loc-translate').length === 0
		&& w.$('#te-loc-result-catalog thead .te-table__total td').eq(3).find('.te-js-loc-translate-all').length === 1 && fdetail.find('.te-js-loc-translate').text() === 'Translate file');
	const fposts = calls.post.length;
	fbtn.trigger('click');
	const fp = calls.post[calls.post.length - 1];
	check('file row: first the translate request with the texts of the file, the button is disabled', () => calls.post.length === fposts + 1 && /client_translate_expert\/translate&token=/.test(fp.url)
		&& fp.data.textArr === JSON.stringify(['My Account']) && fbtn.prop('disabled') === true);
	fp.dfd.resolve({ success: 1, texts: ['Мій акаунт'] });
	const fs2 = calls.post[calls.post.length - 1];
	check('file row: then the save request with the translated content, the button is still disabled', () => calls.post.length === fposts + 2 && /client_translate_expert\/saveLocalizationFile&token=/.test(fs2.url)
		&& fs2.data.fileContent === "<?php\n$_['heading_title'] = 'Мій акаунт';\n" && fs2.data.path === '/account/account.php' && fbtn.prop('disabled') === true);
	fs2.dfd.resolve({ success: 1 });
	check('file row: after the save the badge is saved, a success alert names the path, the button works again, the key list stays closed', () => frow.find('.te-badge--ok').text() === 'saved'
		&& w.$('#te-loc-alerts-catalog .te-alert--success .te-alert__message').last().text() === 'Saved: /account/account.php' && fbtn.prop('disabled') === false && fdetail.hasClass('te-hidden'));
	locLoad(w, calls, [locFile('/f.php', [locKey('x', 'X')])]);
	const gbtn = w.$('#te-loc-result-catalog tr.te-loc-file').eq(0).find('.te-js-loc-translate-save');
	const gposts = calls.post.length;
	gbtn.trigger('click');
	calls.post[calls.post.length - 1].dfd.resolve({ success: 0, message: 'Translate failed' });
	check('file row: a failed translation sends no save, shows an error alert, the button works again, the badge stays', () => calls.post.length === gposts + 1
		&& w.$('#te-loc-alerts-catalog .te-alert--error .te-alert__message').last().text() === 'Translate failed' && gbtn.prop('disabled') === false
		&& w.$('#te-loc-result-catalog tr.te-loc-file').eq(0).find('.te-badge--warn').length === 1);
	gbtn.trigger('click');
	calls.post[calls.post.length - 1].dfd.resolve({ success: 1, texts: ['Y'] });
	calls.post[calls.post.length - 1].dfd.resolve({ success: 0, message: 'Save failed' });
	check('file row: a failed save shows an error alert, the button works again, the badge stays', () => w.$('#te-loc-alerts-catalog .te-alert--error .te-alert__message').last().text() === 'Save failed'
		&& gbtn.prop('disabled') === false && w.$('#te-loc-result-catalog tr.te-loc-file').eq(0).find('.te-badge--warn').length === 1);
}

function testLocTranslateAll(w) {
	console.log('\nLocalization: Translate all:');
	const calls = stubAjax(w);
	w.teLocInit();
	let charCountCalls = 0;
	w.showTranslatedCharCount = function() { charCountCalls++; };
	const asked = [];
	w.confirm = function(text) { asked.push(text); return false; };
	locLoad(w, calls, [locFile('/a.php', [locKey('a', 'A')]), locFile('/n.php', []), locFile('/c.php', [locKey('c', 'C')])]);
	w.$('#te-loc-translate-all-catalog').trigger('click');
	check('confirm false: no request, the question names the language', () => calls.post.length === 0 && asked[0] === 'Translate all (Ukrainian)?' && w.teLocState.catalog.running === false);
	w.confirm = function() { return true; };
	w.$('#te-loc-translate-all-catalog').trigger('click');
	check('run: translate request of the first file, panel title with the file, buttons disabled, a second click does nothing', () => calls.post.length === 1 && /translate&token=/.test(calls.post[0].url)
		&& w.$('#te-loc-progress-catalog .te-progress__title').text() === 'Translating /a.php' && w.$('#te-begin-localization-analization').prop('disabled') === true
		&& w.$('#te-loc-translate-all-catalog').prop('disabled') === true && w.$('#te-loc-result-catalog .te-js-loc-save').prop('disabled') === true
		&& (w.$('#te-loc-translate-all-catalog').prop('disabled', false).trigger('click'), calls.post.length === 1) && (w.$('#te-loc-translate-all-catalog').prop('disabled', true), true));
	calls.post[0].dfd.resolve({ success: 1, texts: ['AA'] });
	check('then save of the same file', () => calls.post.length === 2 && /saveLocalizationFile/.test(calls.post[1].url) && calls.post[1].data.path === '/a.php');
	calls.post[1].dfd.resolve({ success: 1 });
	check('a log line per saved file, the next file starts, the badge is saved', () => w.$('#te-loc-progress-catalog .te-progress__line').text() === 'Saved: /a.php' && calls.post.length === 3
		&& /translate&token=/.test(calls.post[2].url) && w.$('#te-loc-progress-catalog .te-progress__title').text() === 'Translating /c.php'
		&& w.$('#te-loc-result-catalog tr.te-loc-file').eq(0).find('.te-badge--ok').length === 1
		&& w.$('#te-loc-progress-catalog .te-progress__counters').text() === 'Now: /c.phpFiles 2 of 2');
	calls.post[2].dfd.resolve({ success: 1, texts: ['CC'] });
	calls.post[3].dfd.resolve({ success: 1 });
	check('done: te-progress--done, the buttons work, the char count is shown again', () => w.$('#te-loc-progress-catalog').hasClass('te-progress--done') && w.$('#te-begin-localization-analization').prop('disabled') === false
		&& w.$('#te-loc-translate-all-catalog').prop('disabled') === false && w.$('#te-loc-progress-catalog .te-progress__line').length === 2 && charCountCalls >= 3
		&& w.$('#te-loc-progress-catalog .te-progress__counters').text().indexOf('Files 2 of 2') >= 0 && w.teLocState.catalog.running === false);

	check('after a full run: Translate all is hidden and a forced second start sends no request', () => {
		const n = calls.post.length;
		const hidden = w.$('#te-loc-translate-all-catalog').hasClass('te-hidden');
		w.teLocTranslateAllStart('catalog');
		return hidden && calls.post.length === n && w.teLocState.catalog.running === false;
	});
	w.teLocState.catalog.files.forEach(function(f) { f.saved = 0; });

	// stop
	const before = calls.post.length;
	w.$('#te-loc-translate-all-catalog').trigger('click');
	w.$('#te-loc-progress-catalog .te-js-stop').trigger('click');
	check('stop: the button is disabled at once', () => w.$('#te-loc-progress-catalog .te-js-stop').prop('disabled') === true);
	calls.post[before].dfd.resolve({ success: 1, texts: ['AA'] });
	calls.post[before + 1].dfd.resolve({ success: 1 });
	check('stop after the first file: te-progress--stopped, no request for the next file', () => calls.post.length === before + 2 && w.$('#te-loc-progress-catalog').hasClass('te-progress--stopped')
		&& w.$('#te-begin-localization-analization').prop('disabled') === false);

	// save error
	const before2 = calls.post.length;
	w.$('#te-loc-translate-all-catalog').trigger('click');
	calls.post[before2].dfd.resolve({ success: 1, texts: ['AA'] });
	calls.post[before2 + 1].dfd.resolve({ success: 0, message: 'Disk full' });
	check('save error: te-progress--error, an error alert, all buttons work again, no next file', () => calls.post.length === before2 + 2 && w.$('#te-loc-progress-catalog').hasClass('te-progress--error')
		&& w.$('#te-loc-alerts-catalog .te-alert--error .te-alert__message').text() === 'Disk full' && w.$('#te-begin-localization-analization').prop('disabled') === false
		&& w.$('#te-loc-translate-all-catalog').prop('disabled') === false && w.$('#te-loc-result-catalog .te-js-loc-save').prop('disabled') === false);

	// translate error
	const before3 = calls.post.length;
	w.$('#te-loc-translate-all-catalog').trigger('click');
	calls.post[before3].dfd.reject(xhr({ status: 500, responseText: 'oops' }));
	check('translate error: te-progress--error and the run ends', () => w.$('#te-loc-progress-catalog').hasClass('te-progress--error') && w.teLocState.catalog.running === false);

	// expression-only file
	locLoad(w, calls, [locFile('/e.php', [locExpr('x', "sprintf('a')")], { contentTo: '' })]);
	const before4 = calls.post.length;
	w.$('#te-loc-translate-all-catalog').trigger('click');
	check('an expression-only file: no translate request, the first request is the save with the statement', () => calls.post.length === before4 + 1 && /saveLocalizationFile/.test(calls.post[before4].url)
		&& calls.post[before4].data.fileContent === "<?php\n$_['x'] = sprintf('a');\n");
	calls.post[before4].dfd.resolve({ success: 1 });
	check('the run of the expression-only file is done', () => w.$('#te-loc-progress-catalog').hasClass('te-progress--done') && w.$('#te-loc-progress-catalog .te-progress__line').text() === 'Saved: /e.php');
}

function testLocBs5(w) {
	console.log('\nLocalization tabs on Bootstrap 5:');
	const calls = stubAjax(w);
	w.teLocInit();
	w.$('#te-begin-localization-analization').trigger('click');
	check('the stop button has the BS5 class', () => w.$('#te-loc-progress-catalog .te-js-stop').hasClass('btn-secondary'));
	calls.get[0].dfd.resolve(locBatch([locFile('/a.php', [locKey('a', 'A')])], 1, true, { fileCount: 1, needToTranslateFileCount: 1, needToTranslateValueCount: 1, needToTranslateCharCount: 1 }));
	check('the file buttons have the BS5 class', () => w.$('#te-loc-result-catalog .te-js-loc-translate').hasClass('btn-outline-secondary') && w.$('#te-loc-result-catalog .te-js-loc-save').hasClass('btn-outline-secondary'));
}

function testLocStatic() {
	console.log('\nLocalization tabs (static):');
	const base = path.join(__dirname, '../../sources/upload/admin');
	for (const tpl of ['client_translate_expert.tpl', 'client_translate_expert.oc4.twig']) {
		const text = fs.readFileSync(path.join(base, 'view/template/extension/module', tpl), 'utf8');
		check(tpl + ': both loc apps with data-scope, the 2 Analyze ids, progress and result blocks', () => /id="te-loc-app-catalog" data-scope="catalog"/.test(text) && /id="te-loc-app-admin" data-scope="admin"/.test(text)
			&& text.indexOf('id="te-begin-localization-analization"') > 0 && text.indexOf('id="te-begin-admin-localization-analization"') > 0
			&& ['catalog', 'admin'].every((sc) => text.indexOf('id="te-loc-progress-' + sc + '"') > 0 && text.indexOf('id="te-loc-result-' + sc + '"') > 0 && text.indexOf('id="te-loc-alerts-' + sc + '"') > 0));
		check(tpl + ': no old localization markup and no removed js constants', () => text.indexOf('localization-analize') === -1 && text.indexOf('js_const_saved') === -1
			&& text.indexOf('js_const_localization') === -1 && text.indexOf('te-localization-analization-result') === -1);
		check(tpl + ': the toolbar has no Translate all (it is in the total row of the table)', () => text.indexOf('te-js-loc-translate-all') === -1 && text.indexOf('te-loc-translate-all-') === -1);
		check(tpl + ': the tab panes keep their ids', () => text.indexOf('id="tab-localization"') > 0 && text.indexOf('id="tab-admin-localization"') > 0);
	}
	const js = fs.readFileSync(JS_FILE, 'utf8');
	const code = js.split('\n').filter((l) => !/^\s*\/\//.test(l)).join('\n');
	check('the JS has no arrow function, Object.entries or replaceAll outside a comment line', () => code.indexOf('=>') === -1 && code.indexOf('Object.entries(') === -1 && code.indexOf('.replaceAll(') === -1);
	check('the JS has no removed function of the old localization flow', () => ['makeLocalizationAnalizeRequest', 'doTranslateLocalization', 'translateExpertLocalizationIds', 'localizationAnalizationResults', 'showTranslateExpertInfoMessage'].every((n) => js.indexOf(n) === -1));
	check('the CSS has no old localization rule', () => fs.readFileSync(path.join(base, 'view/stylesheet/client_translate_expert.css'), 'utf8').indexOf('localization-analize') === -1);
}

console.log('=== client_translate_expert.js helper tests ===');
for (const version of JQUERY_VERSIONS) {
	console.log('\n--- ' + version.label + ' ---');
	let w = makeWindow(version.file, false);
	testTeT(w);
	testDomHelpers(w);
	testNoBootstrap(w);
	testAlerts(w);
	testCharCountHelpers(w);
	testRemoveLog(w);
	testShowCharCount(w);
	w = makeSiteWindow(version.file, false);
	w.__jq = version.file;
	testTeProgress(w);
	testSiteFilters(w);
	testSiteCronBinding(w);
	testSiteAnalysis(w);
	w = makeSiteWindow(version.file, false);
	testSiteDetail(w);
	w = makeSiteWindow(version.file, false);
	testSiteTranslate(w);
	w = makeSiteWindow(version.file, false);
	w.__jq = version.file;
	testSiteLangbar(w);
	w = makeSiteWindow(version.file, false);
	testLocAnalysis(w);
	w = makeSiteWindow(version.file, false);
	testLocDiff(w);
	w = makeSiteWindow(version.file, false);
	testLocFileHelpers(w);
	w = makeSiteWindow(version.file, false);
	testLocTranslateSave(w);
	w = makeSiteWindow(version.file, false);
	testLocTranslateAll(w);
	w = makeWindow(version.file, true);
	testBs5(w);
	testCharCountHelpers(w);
	w = makeSiteWindow(version.file, true);
	testSiteBs5(w);
	w = makeSiteWindow(version.file, true);
	testLocBs5(w);
}
testLocStatic();

console.log('\nTemplates (static):');
for (const tpl of ['client_translate_expert.tpl', 'client_translate_expert.oc4.twig']) {
	const text = fs.readFileSync(path.join(__dirname, '../../sources/upload/admin/view/template/extension/module', tpl), 'utf8');
	const radios = text.match(/<input[^>]*name="te-analization-mode"[^>]*>/g) || [];
	check(tpl + ': the 3 mode radios are outside the settings form (form="te-no-form")', () => radios.length === 3 && radios.every((r) => r.indexOf('form="te-no-form"') >= 0));
}
console.log('\n=== Results: ' + passed + ' passed, ' + failed + ' failed, 0 skipped ===');
process.exit(failed === 0 ? 0 : 1);
