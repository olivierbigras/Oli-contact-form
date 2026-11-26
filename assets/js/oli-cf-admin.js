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
	}

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
