/**
 * Smoke test for assets/js/oli-cf-public.js
 *
 * Proves handleAjaxResponse binds `self`, shows a banner on success and
 * failure, and re-enables the submit button (spinner removed) on every path.
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let failed = 0;

function assert(cond, label) {
	if (!cond) {
		failed += 1;
		process.stderr.write('FAIL ' + label + '\n');
		return;
	}
	process.stdout.write('OK   ' + label + '\n');
}

const src = fs.readFileSync(path.join(__dirname, '..', 'assets/js/oli-cf-public.js'), 'utf8');

assert(/handleAjaxResponse:\s*function[\s\S]*?var self = this;/.test(src), 'handleAjaxResponse declares var self = this');
assert(src.indexOf('resetSubmit') !== -1, 'resetSubmit helper exists');
assert(/complete:\s*function/.test(src), 'AJAX complete hook resets the button');

const submitState = {
	disabled: true,
	spinner: true,
	html: '<span class="oli-cf-spinner"></span>',
};

function chain(extra) {
	const obj = extra || {};
	obj.prop = function (name, value) {
		if (name === 'disabled' && typeof value !== 'undefined') {
			submitState.disabled = !!value;
		}
		return obj;
	};
	obj.find = function (sel) {
		if (sel === '.oli-cf-spinner') {
			return {
				remove: function () {
					submitState.spinner = false;
					submitState.html = '';
					return this;
				},
			};
		}
		return chain();
	};
	obj.remove = function () {
		return obj;
	};
	obj.append = function () {
		return obj;
	};
	obj.attr = function () {
		return obj;
	};
	obj.empty = function () {
		return obj;
	};
	obj.html = function (value) {
		if (typeof value !== 'undefined') {
			obj._html = value;
		}
		return obj._html || '';
	};
	obj.addClass = function () {
		return obj;
	};
	obj.removeClass = function () {
		return obj;
	};
	obj.removeAttr = function () {
		return obj;
	};
	obj.trigger = function () {
		return obj;
	};
	obj.closest = function () {
		return obj;
	};
	obj.filter = function () {
		return obj;
	};
	obj.first = function () {
		return obj;
	};
	obj.each = function () {
		return obj;
	};
	obj.text = function () {
		return '';
	};
	obj.val = function () {
		return '';
	};
	obj.is = function () {
		return false;
	};
	obj.length = obj.length || 0;
	return obj;
}

const output = chain({ length: 1, _html: '' });
output.html = function (value) {
	if (typeof value !== 'undefined') {
		output._html = value;
		return output;
	}
	return output._html || '';
};

const submit = chain({ length: 1 });

const formNode = {
	reset: function () {
		formNode.resetCalled = true;
	},
};

const form = chain({ length: 1 });
form[0] = formNode;
form.find = function () {
	const col = chain({ length: 0 });
	col.filter = function () {
		return chain({ length: 0 });
	};
	col.first = function () {
		return chain({ length: 0 });
	};
	col.remove = function () {
		return col;
	};
	return col;
};

const container = chain({ length: 1 });
container.offset = function () {
	return { top: 0 };
};

function jqueryStub(sel) {
	if (sel && sel.jquery) {
		return sel;
	}
	if (typeof sel === 'function') {
		return jqueryStub;
	}
	const el = chain({ length: 0 });
	el.ready = function () {
		return el;
	};
	el.animate = function () {
		return el;
	};
	el.text = function (value) {
		el._text = value == null ? '' : String(value);
		return el;
	};
	el.html = function () {
		return String(el._text || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	};
	return el;
}

jqueryStub.ajax = function () {
	return {};
};
jqueryStub.each = function (obj, cb) {
	if (!obj) {
		return;
	}
	Object.keys(obj).forEach(function (key) {
		cb(key, obj[key]);
	});
};

const oliCf = {
	strings: {
		unexpectedError: 'An unexpected error occurred. Please try again.',
		errorBannerOne: 'The form contains 1 error.',
		errorBannerMany: 'The form contains %d errors.',
	},
};

const sandbox = {
	jQuery: jqueryStub,
	oliCf: oliCf,
	window: { oliCf: oliCf },
	document: { ready: function () {} },
	console: console,
};

sandbox.window.window = sandbox.window;
sandbox.global = sandbox;
jqueryStub.fn = {};

vm.runInNewContext(src, sandbox);

const api = sandbox.window.OliContactForm;
assert(api && typeof api.handleAjaxResponse === 'function', 'OliContactForm.handleAjaxResponse is exported');
assert(typeof api.resetSubmit === 'function', 'OliContactForm.resetSubmit is exported');
assert(typeof api.showBanner === 'function', 'OliContactForm.showBanner is exported');

submitState.disabled = true;
submitState.spinner = true;
api.handleAjaxResponse(
	form,
	container,
	output,
	submit,
	{ success: true, data: { message: 'Thank you for your message. It has been sent.' } },
	sandbox.window.oliCf.strings,
	{}
);

assert(output._html.indexOf('oli-cf-response-success') !== -1, 'success response renders the success banner');
assert(output._html.indexOf('Thank you for your message. It has been sent.') !== -1, 'success banner contains the server message');
assert(submitState.disabled === false, 'submit button is enabled after success');
assert(submitState.spinner === false, 'spinner is removed after success');
assert(formNode.resetCalled === true, 'form is reset after success');

submitState.disabled = true;
submitState.spinner = true;
output._html = '';
api.handleAjaxResponse(
	form,
	container,
	output,
	submit,
	{ success: false, data: { message: 'There was an error sending your message. Please try again.' } },
	sandbox.window.oliCf.strings,
	{}
);

assert(output._html.indexOf('oli-cf-response-error') !== -1, 'failure response renders the error banner');
assert(output._html.indexOf('There was an error sending your message. Please try again.') !== -1, 'error banner contains the server message');
assert(submitState.disabled === false, 'submit button is enabled after a server error');
assert(submitState.spinner === false, 'spinner is removed after a server error');

submitState.disabled = true;
submitState.spinner = true;
api.resetSubmit(submit);
assert(submitState.disabled === false && submitState.spinner === false, 'resetSubmit clears disabled + spinner');

if (failed > 0) {
	process.stderr.write('\n' + failed + ' public JS test(s) failed.\n');
	process.exit(1);
}

process.stdout.write('\nAll public JS tests passed.\n');
process.exit(0);
