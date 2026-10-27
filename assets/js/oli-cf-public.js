/**
 * Public JavaScript for Oli Contact Form
 * Handles AJAX submission, validation, and anti-spam integration
 */

(function() {
	'use strict';

	function focusHashResponse() {
		var hash = window.location.hash || '';
		if (hash.indexOf('#oli-cf-response-') !== 0) {
			return;
		}

		var el = document.getElementById(hash.slice(1));
		if (el && typeof el.focus === 'function') {
			el.focus();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', focusHashResponse);
	} else {
		focusHashResponse();
	}
})();

(function($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function fieldName($field) {
		return String($field.attr('name') || '').replace(/\[\]$/, '');
	}

	function fieldLabel($field) {
		var $itemLabel = $field.closest('.oli-cf-list-item').find('.oli-cf-list-item-label').first();
		if ($itemLabel.length) {
			var item = $itemLabel.text().replace(/\s+/g, ' ').trim();
			if (item) {
				return item;
			}
		}

		var $wrapLabel = $field.closest('label');
		if ($wrapLabel.length) {
			var clone = $wrapLabel.clone();
			clone.find('input, textarea, select, .oli-cf-required, .oli-cf-field-error, .oli-cf-list-item-label').remove();
			var text = clone.text().replace(/\s+/g, ' ').replace(/\*/g, '').trim();
			if (text) {
				return text;
			}
		}

		var id = $field.attr('id');
		if (id) {
			var $forLabel = $field.closest('form, .oli-cf-form').find('label[for="' + id + '"]').first();
			if ($forLabel.length) {
				var forClone = $forLabel.clone();
				forClone.find('input, textarea, select, .oli-cf-required, .oli-cf-field-error').remove();
				var forText = forClone.text().replace(/\s+/g, ' ').replace(/\*/g, '').trim();
				if (forText) {
					return forText;
				}
			}
		}

		var placeholder = $field.attr('placeholder');
		if (placeholder) {
			return placeholder;
		}

		return fieldName($field);
	}

	function formMessages($form) {
		var defaults = (window.oliCf && oliCf.strings) ? oliCf.strings : {};
		var encoded = $form.attr('data-oli-messages');
		var parsed = {};

		if (encoded) {
			try {
				parsed = JSON.parse(encoded);
			} catch (e) {
				parsed = {};
			}
		}

		return {
			invalid_required: parsed.invalid_required || defaults.invalidRequired || 'This field is required.',
			invalid_email: parsed.invalid_email || defaults.invalidEmail || 'Please enter a valid email address.',
			invalid_tel: parsed.invalid_tel || defaults.invalidTel || 'Please enter a valid phone number.',
			invalid_url: parsed.invalid_url || defaults.invalidUrl || 'Please enter a valid URL.',
			invalid_number: parsed.invalid_number || defaults.invalidNumber || 'Please enter a valid number.',
			invalid_date: parsed.invalid_date || defaults.invalidDate || 'Please enter a valid date.',
			accept_terms: parsed.accept_terms || defaults.acceptTerms || 'You must accept this to proceed.',
			validation_error: parsed.validation_error || defaults.validationError || 'One or more fields have an error. Please check and try again.'
		};
	}

	var OliContactForm = {
		init: function() {
			this.bindEvents();
			var $output = $('.oli-cf-container.oli-cf-has-status .oli-cf-response-output');
			if ($output.length) {
				this.focusStatus($output.first());
			}
		},

		bindEvents: function() {
			var self = this;

			$(document).on('submit', '.oli-cf-form', function(e) {
				e.preventDefault();
				self.handleSubmit($(this));
			});

			$(document).on('blur', '.oli-cf-field', function() {
				self.validateField($(this), formMessages($(this).closest('.oli-cf-form')));
			});
		},

		resetSubmit: function($submit) {
			if (!$submit || !$submit.length) {
				return;
			}

			$submit.prop('disabled', false).find('.oli-cf-spinner').remove();
		},

		focusStatus: function($output) {
			if (!$output || !$output.length) {
				return;
			}

			$output.attr('tabindex', '-1').trigger('focus');
		},

		handleSubmit: function($form) {
			var self = this;
			var formId = $form.find('[name="oli_cf_form_id"]').val();
			var $container = $form.closest('.oli-cf-container');
			var $output = $container.find('.oli-cf-response-output');
			var $submit = $form.find('.oli-cf-submit');
			var strings = (window.oliCf && oliCf.strings) ? oliCf.strings : {};
			var messages = formMessages($form);

			$output.empty().attr('role', 'status');
			$form.find('.oli-cf-field-error').remove();

			var isValid = true;
			$form.find('.oli-cf-field').each(function() {
				if (!self.validateField($(this), messages)) {
					isValid = false;
				}
			});

			if (!isValid) {
				self.resetSubmit($submit);
				self.showBanner($form, $output, messages.validation_error, true);
				self.focusStatus($output);
				$form.find('[aria-invalid="true"]').first().trigger('focus');
				return false;
			}

			$submit.prop('disabled', true).append('<span class="oli-cf-spinner"></span>');

			var useV3 = window.oliCf && oliCf.recaptchaSiteKey && oliCf.recaptchaVersion === 'v3' && window.grecaptcha && typeof grecaptcha.execute === 'function';

			if (useV3) {
				grecaptcha.ready(function() {
					grecaptcha.execute(oliCf.recaptchaSiteKey, {action: 'submit'}).then(function(token) {
						$form.find('#oli-cf-recaptcha-' + formId).val(token);
						self.submitForm($form, $container, $output, $submit);
					}).catch(function() {
						self.resetSubmit($submit);
						self.showBanner($form, $output, strings.unexpectedError || 'An unexpected error occurred. Please try again.', true);
						self.focusStatus($output);
					});
				});
			} else {
				self.submitForm($form, $container, $output, $submit);
			}

			return false;
		},

		submitForm: function($form, $container, $output, $submit) {
			var self = this;
			var formData = new FormData($form[0]);
			var strings = (window.oliCf && oliCf.strings) ? oliCf.strings : {};
			var messages = formMessages($form);

			formData.append('action', 'oli_cf_submit_form');
			formData.append('nonce', (window.oliCf && oliCf.nonce) ? oliCf.nonce : '');

			$.ajax({
				url: oliCf.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function(response) {
					self.handleAjaxResponse($form, $container, $output, $submit, response, strings, messages);
				},
				error: function(xhr) {
					var response = xhr.responseJSON;
					if (!response && xhr.responseText) {
						try {
							response = JSON.parse(xhr.responseText);
						} catch (e) {
							response = null;
						}
					}

					if (response && typeof response.success !== 'undefined') {
						self.handleAjaxResponse($form, $container, $output, $submit, response, strings, messages);
						return;
					}

					self.resetSubmit($submit);
					self.showBanner($form, $output, strings.unexpectedError || 'An unexpected error occurred. Please try again.', true);
					self.focusStatus($output);
				},
				complete: function() {
					self.resetSubmit($submit);
				}
			});
		},

		handleAjaxResponse: function($form, $container, $output, $submit, response, strings, messages) {
			var self = this;
			self.resetSubmit($submit);

			try {
				if (response && response.success) {
					var ok = response.data && response.data.message ? response.data.message : (strings.unexpectedError || '');
					self.showBanner($form, $output, ok, false);
					if ($form[0] && typeof $form[0].reset === 'function') {
						$form[0].reset();
					}
					$form.find('.oli-cf-field').removeClass('oli-cf-invalid').attr('aria-invalid', 'false').removeAttr('aria-describedby');
					$form.find('.oli-cf-field-error').remove();
					self.focusStatus($output);
				} else {
					var failMessage = response && response.data && response.data.message ? response.data.message : (strings.unexpectedError || 'An unexpected error occurred. Please try again.');

					if (response && response.data && response.data.errors) {
						$.each(response.data.errors, function(field, error) {
							var $field = $form.find('[name="' + field + '"], [name="' + field + '[]"]').filter('.oli-cf-field').first();
							if ($field.length) {
								self.showFieldError($field, error);
							}
						});
					}

					self.showBanner($form, $output, failMessage, true);

					var $invalid = $form.find('[aria-invalid="true"]');
					if ($invalid.length) {
						$invalid.first().trigger('focus');
					} else {
						self.focusStatus($output);
					}
				}

				if ($container && $container.length && typeof $container.offset === 'function') {
					var offset = $container.offset();
					if (offset && typeof offset.top === 'number') {
						$('html, body').animate({
							scrollTop: offset.top - 50
						}, 300);
					}
				}
			} finally {
				self.resetSubmit($submit);
			}
		},

		showBanner: function($form, $output, message, isError) {
			var strings = (window.oliCf && oliCf.strings) ? oliCf.strings : {};
			$output.attr('role', isError ? 'alert' : 'status');

			var html = '<div class="' + (isError ? 'oli-cf-response-error' : 'oli-cf-response-success') + '">' +
				escapeHtml(message || '');

			if (isError) {
				var $invalid = $form.find('[aria-invalid="true"]');
				if ($invalid.length) {
					var count = $invalid.length;
					var banner = count === 1
						? (strings.errorBannerOne || 'The form contains 1 error.')
						: (strings.errorBannerMany || strings.errorBanner || 'The form contains %d errors.').replace('%d', count);
					html = '<div class="oli-cf-response-error">' + escapeHtml(banner) + '<ul class="oli-cf-validation-errors">';
					$invalid.each(function() {
						var $field = $(this);
						var id = $field.attr('id') || '';
						var err = $field.closest('.oli-cf-form-control-wrap').find('.oli-cf-field-error').text();
						var label = fieldLabel($field);
						var item = label ? (label + ' : ' + err) : err;
						html += '<li>';
						if (id) {
							html += '<a href="#' + escapeHtml(id) + '">' + escapeHtml(item) + '</a>';
						} else {
							html += escapeHtml(item);
						}
						html += '</li>';
					});
					html += '</ul>';
				}
			}

			html += '</div>';
			$output.html(html);
		},

		validateField: function($field, messages) {
			messages = messages || formMessages($field.closest('.oli-cf-form'));
			var value = $field.val();
			var type = $field.attr('type') || ($field.is('textarea') ? 'textarea' : 'text');
			var required = $field.prop('required') || $field.attr('aria-required') === 'true';
			var isValid = true;
			var message = '';

			this.clearFieldError($field);

			if ($field.is(':checkbox') && $field.hasClass('oli-cf-acceptance')) {
				if (required && !$field.prop('checked')) {
					isValid = false;
					message = messages.accept_terms;
				}
			} else if (required && $field.is(':checkbox')) {
				var groupName = $field.attr('name');
				if (groupName && !$formHasChecked($field.closest('.oli-cf-form'), groupName)) {
					isValid = false;
					message = messages.invalid_required;
				}
			} else if (required && (!value || String(value).length === 0)) {
				isValid = false;
				message = messages.invalid_required;
			}

			if (isValid && value && String(value).length > 0) {
				switch (type) {
					case 'email':
						if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
							isValid = false;
							message = messages.invalid_email;
						}
						break;
					case 'url':
						if (!/^https?:\/\/.+/.test(value)) {
							isValid = false;
							message = messages.invalid_url;
						}
						break;
					case 'tel':
						if (!/^[\d\s\+\-\(\)]+$/.test(value)) {
							isValid = false;
							message = messages.invalid_tel;
						}
						break;
					case 'number':
						if (isNaN(value)) {
							isValid = false;
							message = messages.invalid_number;
						}
						break;
					case 'date':
						if (isNaN(Date.parse(value))) {
							isValid = false;
							message = messages.invalid_date;
						}
						break;
				}
			}

			if (!isValid) {
				this.showFieldError($field, message);
			}

			return isValid;
		},

		showFieldError: function($field, message) {
			var name = fieldName($field) || 'field';
			var id = 'oli-cf-err-' + name;
			$field.addClass('oli-cf-invalid').attr('aria-invalid', 'true').attr('aria-describedby', id);

			var $wrap = $field.closest('.oli-cf-form-control-wrap');
			var $target = $wrap.length ? $wrap : $field.parent();
			$target.find('.oli-cf-field-error').remove();
			$target.append('<span class="oli-cf-field-error" id="' + id + '">' + escapeHtml(message) + '</span>');
		},

		clearFieldError: function($field) {
			$field.removeClass('oli-cf-invalid').attr('aria-invalid', 'false').removeAttr('aria-describedby');
			$field.closest('.oli-cf-form-control-wrap').find('.oli-cf-field-error').remove();
		}
	};

	function $formHasChecked($form, name) {
		return $form.find('[name="' + name + '"]').filter(':checked').length > 0;
	}

	$(document).ready(function() {
		OliContactForm.init();
	});

	window.OliContactForm = OliContactForm;
	window.oliCfTurnstileCallback = function() {};

})(jQuery);
