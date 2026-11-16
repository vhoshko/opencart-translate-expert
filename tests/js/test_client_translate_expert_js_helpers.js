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
function makeWindow(jqueryFile, withBs5) {
	const dom = new JSDOM(
		'<!doctype html><html><head></head><body><div class="te-app">'
		+ '<div id="te-analization-result"><p id="keep">old</p></div><div id="al" class="te-alerts"></div><div id="col" style="display:none">c</div><a id="tab" href="#x">t</a>'
		+ '</div></body></html>',
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
	w = makeWindow(version.file, true);
	testBs5(w);
	testCharCountHelpers(w);
}

console.log('\n=== Results: ' + passed + ' passed, ' + failed + ' failed, 0 skipped ===');
process.exit(failed === 0 ? 0 : 1);
