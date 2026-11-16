// Unit tests for the render helpers of the new admin design in client_translate_expert.js
// (teT, teIcon, teIconEl, teEl, teText, teBs, teAlerts).
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
		+ '<div id="al" class="te-alerts"></div><div id="col" style="display:none">c</div><a id="tab" href="#x">t</a>'
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
	w = makeWindow(version.file, true);
	testBs5(w);
}

console.log('\n=== Results: ' + passed + ' passed, ' + failed + ' failed, 0 skipped ===');
process.exit(failed === 0 ? 0 : 1);
