/**
 * Admin JavaScript for Oli Contact Form
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		var strings = (window.oliCfAdmin && oliCfAdmin.strings) ? oliCfAdmin.strings : {};

		$('.oli-cf-tag-gen').on('click', function(e) {
			e.preventDefault();

			var tagType = $(this).data('tag-type');
			var tag = generateTag(tagType);

			if (tag) {
				insertAtCursor($('#oli-cf-form-template')[0], tag);
			}
		});

		function generateTag(type) {
			var name = prompt(strings.field_name || 'Enter field name (e.g., "your-name"):');

			if (!name && type !== 'submit') {
				return null;
			}

			if (name) {
				name = name.toLowerCase().replace(/[^a-z0-9-_]/g, '-');
			}

			var tag = '';
			var required = (type !== 'submit') ? confirm(strings.required || 'Is this field required?') : false;
			var requiredMark = required ? '*' : '';

			switch (type) {
				case 'text':
				case 'email':
				case 'tel':
				case 'url':
				case 'number':
				case 'date':
					tag = '[' + type + requiredMark + ' ' + name + ']';
					break;

				case 'textarea':
					tag = '[textarea' + requiredMark + ' ' + name + ']';
					break;

				case 'select':
				case 'checkbox':
				case 'radio':
					var options = prompt(strings.options || 'Enter options (separated by spaces, quote multi-word options):');
					if (!options) {
						return null;
					}
					tag = '[' + type + requiredMark + ' ' + name + ' ' + options + ']';
					break;

				case 'acceptance':
					var acceptLabel = prompt(strings.acceptance || 'Enter acceptance text:', strings.acceptance_def || 'I accept the terms');
					if (!acceptLabel) {
						return null;
					}
					tag = '[acceptance' + requiredMark + ' ' + name + ' "' + acceptLabel + '"]';
					break;

				case 'file':
					var limit = prompt(strings.file_limit || 'File size limit (e.g., "2mb"):', '5mb');
					var types = prompt(strings.file_types || 'Allowed file types (e.g., "jpg|png|pdf"):', 'jpg|png|pdf');
					tag = '[file' + requiredMark + ' ' + name;
					if (limit) {
						tag += ' limit:' + limit;
					}
					if (types) {
						tag += ' filetypes:' + types;
					}
					tag += ']';
					break;

				case 'submit':
					var buttonLabel = prompt(strings.button_text || 'Button text:', strings.send || 'Send');
					if (!buttonLabel) {
						return null;
					}
					tag = '[submit "' + buttonLabel + '"]';
					break;

				default:
					tag = '[' + type + ' ' + name + ']';
			}

			return tag;
		}

		function insertAtCursor(field, text) {
			if (!field) {
				return;
			}

			if (field.selectionStart || field.selectionStart === 0) {
				var startPos = field.selectionStart;
				var endPos = field.selectionEnd;
				field.value = field.value.substring(0, startPos) + text + field.value.substring(endPos, field.value.length);
				field.selectionStart = startPos + text.length;
				field.selectionEnd = startPos + text.length;
			} else {
				field.value += text;
			}

			field.focus();
		}
	});

})(jQuery);
