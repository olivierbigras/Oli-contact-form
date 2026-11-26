/**
 * Form importer admin script for Oli Contact Form.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var selectAll = document.getElementById('oli-cf-select-all');

		if (!selectAll) {
			return;
		}

		selectAll.addEventListener('change', function () {
			var checked = this.checked;
			var checkboxes = document.querySelectorAll('.oli-cf-import-checkbox');

			checkboxes.forEach(function (checkbox) {
				checkbox.checked = checked;
			});
		});
	});
})();
