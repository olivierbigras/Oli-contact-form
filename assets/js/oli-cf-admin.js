/**
 * Field and notification rows in the form editor.
 */
(function () {
	'use strict';

	function reindex(repeater) {
		var rows = repeater.querySelectorAll('.oli-cf-repeater-row');
		Array.prototype.forEach.call(rows, function (row, index) {
			var fields = row.querySelectorAll('input, select, textarea');
			Array.prototype.forEach.call(fields, function (field) {
				if (!field.name) {
					return;
				}
				field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
			});
		});
	}

	function addRow(button) {
		var target = button.getAttribute('data-oli-target');
		var repeater = document.querySelector('[data-oli-repeater="' + target + '"]');
		if (!repeater) {
			return;
		}
		var rows = repeater.querySelectorAll('.oli-cf-repeater-row');
		if (!rows.length) {
			return;
		}
		var clone = rows[rows.length - 1].cloneNode(true);
		var fields = clone.querySelectorAll('input, select, textarea');
		Array.prototype.forEach.call(fields, function (field) {
			if (field.type === 'checkbox') {
				field.checked = false;
			} else if (field.tagName === 'SELECT') {
				field.selectedIndex = 0;
			} else {
				field.value = '';
			}
		});
		repeater.appendChild(clone);
		reindex(repeater);
		syncFieldType(clone);
		syncCondition(clone);
		var focusable = clone.querySelector('select, input, textarea');
		if (focusable && focusable.focus) {
			focusable.focus();
		}
	}

	function replaceClassPrefix(row, prefix, next) {
		var classes = row.className.split(/\s+/).filter(function (name) {
			return name && name.indexOf(prefix) !== 0;
		});
		if (next) {
			classes.push(next);
		}
		row.className = classes.join(' ');
	}

	function syncFieldType(row) {
		var select = row.querySelector('select[name$="[type]"]');
		if (!select) {
			return;
		}
		replaceClassPrefix(row, 'oli-cf-type-', 'oli-cf-type-' + select.value);
	}

	function syncCondition(row) {
		var select = row.querySelector('select[name$="[condition_operator]"]');
		if (!select) {
			return;
		}
		replaceClassPrefix(row, 'oli-cf-condition-', 'oli-cf-condition-' + select.value);
	}

	document.addEventListener('change', function (event) {
		var select = event.target;
		if (!select || !select.matches) {
			return;
		}
		var row = select.closest ? select.closest('.oli-cf-repeater-row') : null;
		if (!row) {
			return;
		}
		if (select.matches('select[name$="[type]"]')) {
			syncFieldType(row);
		}
		if (select.matches('select[name$="[condition_operator]"]')) {
			syncCondition(row);
		}
	});

	document.addEventListener('submit', function (event) {
		var form = event.target;
		var message = form && form.getAttribute ? form.getAttribute('data-oli-confirm') : '';
		if (!message) {
			return;
		}
		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});

	document.addEventListener('click', function (event) {
		var button = event.target && event.target.closest ? event.target.closest('.notice[data-oli-dismiss="migration"] .notice-dismiss') : null;
		if (!button || !window.oliCfAdmin || !oliCfAdmin.dismissNonce || !window.fetch) {
			return;
		}
		var body = new window.FormData();
		body.append('action', 'oli_cf_dismiss_migration');
		body.append('_ajax_nonce', oliCfAdmin.dismissNonce);
		window.fetch(oliCfAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		});
	});

	document.addEventListener('click', function (event) {
		var add = event.target.closest ? event.target.closest('.oli-cf-add-row') : null;
		if (add) {
			event.preventDefault();
			addRow(add);
			return;
		}
		var remove = event.target.closest ? event.target.closest('.oli-cf-remove-row') : null;
		if (!remove) {
			return;
		}
		event.preventDefault();
		var row = remove.closest('.oli-cf-repeater-row');
		var repeater = remove.closest('.oli-cf-repeater');
		if (!row || !repeater) {
			return;
		}
		if (repeater.querySelectorAll('.oli-cf-repeater-row').length < 2) {
			return;
		}
		row.parentNode.removeChild(row);
		reindex(repeater);
	});
})();
