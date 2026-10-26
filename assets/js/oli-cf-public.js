/**
 * Public JavaScript for Oli Contact Form
 * Handles AJAX submission, validation, and anti-spam integration
 */

(function($) {
	'use strict';

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	var OliContactForm = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			$(document).on('submit', '.oli-cf-form', function(e) {
				e.preventDefault();
				self.handleSubmit($(this));
			});

			$(document).on('blur', '.oli-cf-field', function() {
				self.validateField($(this));
			});
		},

		handleSubmit: function($form) {
			var self = this;
			var formId = $form.find('[name="oli_cf_form_id"]').val();
			var $container = $form.closest('.oli-cf-container');
			var $output = $container.find('.oli-cf-response-output');
			var $submit = $form.find('.oli-cf-submit');
			var strings = (window.oliCf && oliCf.strings) ? oliCf.strings : {};

			$output.empty();

			var isValid = true;
			$form.find('.oli-cf-field').each(function() {
				if (!self.validateField($(this))) {
					isValid = false;
				}
			});

			if (!isValid) {
				$output.html('<div class="oli-cf-response-error" role="alert">' +
					escapeHtml(strings.validationError || 'One or more fields have an error. Please check and try again.') +
					'</div>');
				return false;
			}

			$submit.prop('disabled', true).append('<span class="oli-cf-spinner"></span>');

			if (window.oliCf && oliCf.recaptchaSiteKey && window.grecaptcha) {
				grecaptcha.ready(function() {
					grecaptcha.execute(oliCf.recaptchaSiteKey, {action: 'submit'}).then(function(token) {
						$form.find('#oli-cf-recaptcha-' + formId).val(token);
						self.submitForm($form, $container, $output, $submit);
					});
				});
			} else {
				self.submitForm($form, $container, $output, $submit);
			}

			return false;
		},

		submitForm: function($form, $container, $output, $submit) {
			var formData = new FormData($form[0]);
			var strings = (window.oliCf && oliCf.strings) ? oliCf.strings : {};

			formData.append('action', 'oli_cf_submit_form');
			formData.append('nonce', (window.oliCf && oliCf.nonce) ? oliCf.nonce : '');

			$.ajax({
				url: oliCf.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					$submit.prop('disabled', false).find('.oli-cf-spinner').remove();

					if (response.success) {
						$output.html('<div class="oli-cf-response-success" role="alert">' +
							escapeHtml(response.data.message) +
							'</div>');
						$form[0].reset();
						$form.find('.oli-cf-field').removeClass('oli-cf-invalid').attr('aria-invalid', 'false');
					} else {
						var errorHtml = '<div class="oli-cf-response-error" role="alert">' +
							escapeHtml(response.data && response.data.message ? response.data.message : strings.unexpectedError);

						if (response.data && response.data.errors) {
							errorHtml += '<ul class="oli-cf-validation-errors">';
							$.each(response.data.errors, function(field, error) {
								errorHtml += '<li>' + escapeHtml(error) + '</li>';
								$form.find('[name="' + field + '"], [name="' + field + '[]"]').addClass('oli-cf-invalid').attr('aria-invalid', 'true');
							});
							errorHtml += '</ul>';
						}

						errorHtml += '</div>';
						$output.html(errorHtml);
					}

					$('html, body').animate({
						scrollTop: $container.offset().top - 50
					}, 300);
				},
				error: function() {
					$submit.prop('disabled', false).find('.oli-cf-spinner').remove();
					$output.html('<div class="oli-cf-response-error" role="alert">' +
						escapeHtml(strings.unexpectedError || 'An unexpected error occurred. Please try again.') +
						'</div>');
				}
			});
		},

		validateField: function($field) {
			var value = $field.val();
			var type = $field.attr('type') || 'text';
			var required = $field.prop('required');
			var isValid = true;

			$field.removeClass('oli-cf-invalid').attr('aria-invalid', 'false');

			if (required && (!value || value.length === 0)) {
				isValid = false;
			}

			if (value && value.length > 0) {
				switch (type) {
					case 'email':
						if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
							isValid = false;
						}
						break;

					case 'url':
						if (!/^https?:\/\/.+/.test(value)) {
							isValid = false;
						}
						break;

					case 'tel':
						if (!/^[\d\s\+\-\(\)]+$/.test(value)) {
							isValid = false;
						}
						break;

					case 'number':
						if (isNaN(value)) {
							isValid = false;
						}
						break;
				}
			}

			if (!isValid) {
				$field.addClass('oli-cf-invalid').attr('aria-invalid', 'true');
			}

			return isValid;
		}
	};

	$(document).ready(function() {
		OliContactForm.init();
	});

	window.OliContactForm = OliContactForm;
	window.oliCfTurnstileCallback = function() {};

})(jQuery);
