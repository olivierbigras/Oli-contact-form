<?php
/**
 * Parse CF7-like tag syntax and generate HTML.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form parser class.
 */
class Oli_CF_Form_Parser {

	/**
	 * Field types that render as a single-line input.
	 *
	 * @var string[]
	 */
	const INPUT_TYPES = array( 'text', 'email', 'tel', 'phone', 'url', 'number', 'date' );

	/**
	 * Render-time posted values and field errors (no-JS return).
	 *
	 * @var array<string, mixed>
	 */
	private static $render_state = array();

	/**
	 * Parse form template and return HTML.
	 *
	 * @param string               $template Form template with CF7-like tags.
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $state    Optional values/errors to replay after a no-JS submit.
	 * @return string Parsed HTML.
	 */
	public static function parse( $template, $form_id, $state = array() ) {
		self::$render_state = is_array( $state ) ? $state : array();

		$html = self::normalize_template( $template );

		$html = preg_replace_callback(
			'/\[([^\]]+)\]/',
			function ( $matches ) use ( $form_id ) {
				return self::render_parsed_field( self::parse_tag( $matches[1] ), (int) $form_id );
			},
			$html
		);

		$html = self::inject_required_marks( (string) $html );

		self::$render_state = array();

		return self::kses( (string) $html );
	}

	/**
	 * Extract field definitions from a template.
	 *
	 * @param string $template Form template.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse_fields( $template ) {
		$fields = array();

		preg_match_all( '/\[([^\]]+)\]/', self::normalize_template( $template ), $matches );

		if ( empty( $matches[1] ) ) {
			return $fields;
		}

		foreach ( $matches[1] as $raw_tag ) {
			$field = self::parse_tag( $raw_tag );

			if ( empty( $field['type'] ) ) {
				continue;
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Parse a single tag into a field definition.
	 *
	 * @param string $tag Tag content without brackets.
	 * @return array<string, mixed>
	 */
	public static function parse_tag( $tag ) {
		$tokens = self::tokenize( $tag );

		if ( empty( $tokens ) ) {
			return array();
		}

		$type_raw = array_shift( $tokens );
		$required = false;

		if ( substr( $type_raw, -1 ) === '*' ) {
			$required = true;
			$type     = rtrim( $type_raw, '*' );
		} else {
			$type = $type_raw;
		}

		$type = strtolower( $type );

		if ( 'phone' === $type ) {
			$type = 'tel';
		}

		$name       = '';
		$attributes = array();
		$values     = array();
		$optional   = self::tag_has_bare_option( $tag, 'optional' );

		if ( 'submit' !== $type && ! empty( $tokens ) && false === strpos( $tokens[0], ':' ) ) {
			$name = array_shift( $tokens );
		}

		foreach ( $tokens as $token ) {
			if ( preg_match( '/^([a-z0-9_-]+):(.*)$/i', $token, $matches ) ) {
				$attributes[ strtolower( $matches[1] ) ] = $matches[2];
				continue;
			}

			if ( 'acceptance' === $type && 'optional' === strtolower( $token ) && $optional ) {
				continue;
			}

			$values[] = $token;
		}

		if ( 'acceptance' === $type ) {
			$required = ! $optional;
		}

		return array(
			'type'       => $type,
			'name'       => $name,
			'required'   => $required,
			'attributes' => $attributes,
			'values'     => $values,
		);
	}

	/**
	 * Whether a tag has an unquoted option such as CF7 `optional`.
	 *
	 * @param string $tag    Tag content without brackets.
	 * @param string $option Option name.
	 * @return bool
	 */
	public static function tag_has_bare_option( $tag, $option ) {
		$option = strtolower( (string) $option );

		if ( '' === $option ) {
			return false;
		}

		$bare = preg_replace( '/"[^"]*"|\'[^\']*\'/', '', (string) $tag );

		if ( ! is_string( $bare ) ) {
			return false;
		}

		return (bool) preg_match( '/(?:^|[\s])' . preg_quote( $option, '/' ) . '(?:[\s]|$)/i', $bare );
	}

	/**
	 * Split a tag into tokens, keeping quoted strings intact.
	 *
	 * @param string $tag Tag content.
	 * @return string[]
	 */
	public static function tokenize( $tag ) {
		$tag    = trim( (string) $tag );
		$tokens = array();
		$length = strlen( $tag );
		$i      = 0;

		while ( $i < $length ) {
			if ( ctype_space( $tag[ $i ] ) ) {
				++$i;
				continue;
			}

			if ( '"' === $tag[ $i ] || "'" === $tag[ $i ] ) {
				$quote = $tag[ $i ];
				++$i;
				$buffer = '';

				while ( $i < $length && $tag[ $i ] !== $quote ) {
					$buffer .= $tag[ $i ];
					++$i;
				}

				$tokens[] = $buffer;

				if ( $i < $length ) {
					++$i;
				}

				continue;
			}

			$buffer = '';

			while ( $i < $length && ! ctype_space( $tag[ $i ] ) ) {
				$buffer .= $tag[ $i ];
				++$i;
			}

			$tokens[] = $buffer;
		}

		return $tokens;
	}

	/**
	 * Allowed HTML for rendered forms.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html() {
		$common = array(
			'class'            => true,
			'id'               => true,
			'style'            => true,
			'aria-hidden'      => true,
			'aria-invalid'     => true,
			'aria-required'    => true,
			'aria-live'        => true,
			'aria-describedby' => true,
			'role'             => true,
		);

		return array(
			'label'    => array_merge( $common, array( 'for' => true ) ),
			'span'     => array_merge( $common, array( 'data-name' => true ) ),
			'p'        => $common,
			'br'       => array(),
			'strong'   => array(),
			'em'       => array(),
			'b'        => array(),
			'i'        => array(),
			'div'      => $common,
			'a'        => array_merge(
				$common,
				array(
					'href'   => true,
					'title'  => true,
					'target' => true,
					'rel'    => true,
				)
			),
			'input'    => array_merge(
				$common,
				array(
					'type'            => true,
					'name'            => true,
					'value'           => true,
					'placeholder'     => true,
					'required'        => true,
					'accept'          => true,
					'min'             => true,
					'max'             => true,
					'step'            => true,
					'maxlength'       => true,
					'minlength'       => true,
					'data-size-limit' => true,
					'data-filetypes'  => true,
					'autocomplete'    => true,
					'tabindex'        => true,
					'checked'         => true,
				)
			),
			'textarea' => array_merge(
				$common,
				array(
					'name'        => true,
					'rows'        => true,
					'cols'        => true,
					'placeholder' => true,
					'required'    => true,
					'maxlength'   => true,
					'minlength'   => true,
				)
			),
			'select'   => array_merge(
				$common,
				array(
					'name'     => true,
					'required' => true,
				)
			),
			'option'   => array(
				'value'    => true,
				'selected' => true,
			),
			'button'   => array_merge(
				$common,
				array(
					'type' => true,
				)
			),
		);
	}

	/**
	 * KSES wrapper for form HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function kses( $html ) {
		return wp_kses( $html, self::allowed_html() );
	}

	/**
	 * Expand CF7 paired tags such as [acceptance]label[/acceptance].
	 *
	 * @param string $template Form template.
	 * @return string
	 */
	public static function normalize_template( $template ) {
		$template = (string) $template;

		$template = preg_replace_callback(
			'/\[acceptance(\*?)([^\]]*)\](.*?)\[\/acceptance\]/is',
			function ( $matches ) {
				$star  = $matches[1];
				$rest  = trim( $matches[2] );
				$label = trim( wp_strip_all_tags( $matches[3] ) );
				$label = str_replace( array( '"', '[', ']' ), '', $label );

				$out = 'acceptance' . $star;

				if ( '' !== $rest ) {
					$out .= ' ' . $rest;
				}

				if ( '' !== $label ) {
					$out .= ' "' . $label . '"';
				}

				return '[' . $out . ']';
			},
			$template
		);

		return is_string( $template ) ? $template : '';
	}

	/**
	 * Whether a template contains at least one required field.
	 *
	 * @param string $template Form template.
	 * @return bool
	 */
	public static function template_has_required( $template ) {
		foreach ( self::parse_fields( $template ) as $field ) {
			if ( ! empty( $field['required'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Visible labels keyed by field name, for error summaries.
	 *
	 * Prefers wrapping <label> text, then acceptance values, then placeholder,
	 * then a humanized field name.
	 *
	 * @param string $template Form template.
	 * @return array<string, string>
	 */
	public static function field_labels( $template ) {
		$labels     = array();
		$normalized = self::normalize_template( $template );

		if ( preg_match_all( '/<label\b[^>]*>(.*?)<\/label>/is', $normalized, $blocks ) ) {
			foreach ( $blocks[1] as $inner ) {
				if ( ! preg_match( '/\[([^\]]+)\]/', $inner, $tag ) ) {
					continue;
				}

				$field = self::parse_tag( $tag[1] );

				if ( empty( $field['name'] ) ) {
					continue;
				}

				$text = wp_strip_all_tags( preg_replace( '/\[[^\]]+\]/', '', $inner ) );
				$text = trim( preg_replace( '/\s+/', ' ', str_replace( '*', '', (string) $text ) ) );

				if ( '' !== $text ) {
					$labels[ $field['name'] ] = $text;
				}
			}
		}

		foreach ( self::parse_fields( $normalized ) as $field ) {
			if ( empty( $field['name'] ) || isset( $labels[ $field['name'] ] ) ) {
				continue;
			}

			if ( 'acceptance' === $field['type'] && ! empty( $field['values'] ) ) {
				$labels[ $field['name'] ] = implode( ' ', $field['values'] );
				continue;
			}

			if ( ! empty( $field['attributes']['placeholder'] ) ) {
				$labels[ $field['name'] ] = $field['attributes']['placeholder'];
				continue;
			}

			$labels[ $field['name'] ] = self::humanize_field_name( $field['name'] );
		}

		return $labels;
	}

	/**
	 * Turn a field name such as your-email into a readable label.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public static function humanize_field_name( $name ) {
		$name = trim( preg_replace( '/\s+/', ' ', str_replace( array( '-', '_' ), ' ', (string) $name ) ) );

		if ( '' === $name ) {
			return '';
		}

		if ( function_exists( 'mb_convert_case' ) ) {
			return mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' );
		}

		return ucwords( $name );
	}

	/**
	 * JS-style error summary line: "Label : message".
	 *
	 * @param string $label   Field label.
	 * @param string $message Error message.
	 * @return string
	 */
	public static function format_error_summary_item( $label, $message ) {
		$label   = trim( (string) $label );
		$message = trim( (string) $message );

		if ( '' === $label ) {
			return $message;
		}

		if ( '' === $message ) {
			return $label;
		}

		return $label . ' : ' . $message;
	}

	/**
	 * Insert a required-field asterisk into labels that wrap a required input.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public static function inject_required_marks( $html ) {
		return preg_replace_callback(
			'/<label\b([^>]*)>(.*?)<\/label>/is',
			function ( $matches ) {
				if ( false !== strpos( $matches[2], '<span class="oli-cf-required"' ) ) {
					return $matches[0];
				}

				if ( ! preg_match( '/required="required"|aria-required="true"/', $matches[2] ) ) {
					return $matches[0];
				}

				return '<label' . $matches[1] . '><span class="oli-cf-required" aria-hidden="true"> *</span> ' . $matches[2] . '</label>';
			},
			(string) $html
		);
	}

	/**
	 * Render a parsed field.
	 *
	 * @param array<string, mixed> $field   Field definition.
	 * @param int                  $form_id Form ID.
	 * @return string
	 */
	private static function render_parsed_field( $field, $form_id ) {
		if ( empty( $field['type'] ) ) {
			return '';
		}

		$type     = $field['type'];
		$name     = isset( $field['name'] ) ? $field['name'] : '';
		$required = ! empty( $field['required'] );
		$attrs    = isset( $field['attributes'] ) ? $field['attributes'] : array();
		$values   = isset( $field['values'] ) ? $field['values'] : array();

		if ( in_array( $type, self::INPUT_TYPES, true ) ) {
			return self::render_input_field( $type, $name, $required, $attrs, $form_id );
		}

		switch ( $type ) {
			case 'textarea':
				return self::render_textarea( $name, $required, $attrs, $form_id );
			case 'select':
				return self::render_select( $name, $required, $attrs, $values, $form_id );
			case 'checkbox':
				return self::render_checkbox( $name, $required, $values, $form_id );
			case 'radio':
				return self::render_radio( $name, $required, $values, $form_id );
			case 'acceptance':
				return self::render_acceptance( $name, $required, $values, $form_id );
			case 'file':
				return self::render_file( $name, $required, $attrs, $form_id );
			case 'submit':
				return self::render_submit( $values, $form_id );
			default:
				return '';
		}
	}

	/**
	 * Render input field (text, email, tel, url, number, date).
	 *
	 * @param string               $type     Field type.
	 * @param string               $name     Field name.
	 * @param bool                 $required Is required.
	 * @param array<string,string> $attrs    Field attributes.
	 * @param int                  $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_input_field( $type, $name, $required, $attrs, $form_id ) {
		$html_type = ( 'tel' === $type ) ? 'tel' : $type;
		$id        = self::field_id( $form_id, $name );
		$error     = self::state_error( $name );
		$class     = self::field_class( $html_type, $required, '' !== $error );

		$placeholder = isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '';
		$default     = isset( $attrs['default'] ) ? $attrs['default'] : '';
		$posted      = self::state_value( $name );
		$value       = ( null !== $posted && ! is_array( $posted ) ) ? (string) $posted : $default;

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<input type="%s" name="%s" id="%s" class="%s" value="%s"%s%s%s%s />',
			esc_attr( $html_type ),
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			esc_attr( $value ),
			$required ? ' required="required" aria-required="true"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
			self::length_attributes( $attrs ),
			self::invalid_attrs( $name, $error )
		);
		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render textarea field.
	 *
	 * @param string               $name     Field name.
	 * @param bool                 $required Is required.
	 * @param array<string,string> $attrs    Field attributes.
	 * @param int                  $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_textarea( $name, $required, $attrs, $form_id ) {
		$id    = self::field_id( $form_id, $name );
		$error = self::state_error( $name );
		$class = self::field_class( 'textarea', $required, '' !== $error );

		$placeholder = isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '';
		$default     = isset( $attrs['default'] ) ? $attrs['default'] : '';
		$posted      = self::state_value( $name );
		$value       = ( null !== $posted && ! is_array( $posted ) ) ? (string) $posted : $default;
		$rows        = isset( $attrs['rows'] ) ? absint( $attrs['rows'] ) : 4;
		$cols        = isset( $attrs['cols'] ) ? absint( $attrs['cols'] ) : 40;

		if ( $rows < 1 ) {
			$rows = 4;
		}

		if ( $cols < 1 ) {
			$cols = 40;
		}

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<textarea name="%s" id="%s" class="%s" rows="%d" cols="%d"%s%s%s%s>%s</textarea>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$rows,
			$cols,
			$required ? ' required="required" aria-required="true"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
			self::length_attributes( $attrs ),
			self::invalid_attrs( $name, $error ),
			esc_textarea( $value )
		);
		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render select field.
	 *
	 * @param string               $name     Field name.
	 * @param bool                 $required Is required.
	 * @param array<string,string> $attrs    Field attributes.
	 * @param string[]             $values   Options.
	 * @param int                  $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_select( $name, $required, $attrs, $values, $form_id ) {
		$id     = self::field_id( $form_id, $name );
		$error  = self::state_error( $name );
		$class  = self::field_class( 'select', $required, '' !== $error );
		$posted = self::state_value( $name );
		$posted = ( null !== $posted && ! is_array( $posted ) ) ? (string) $posted : null;

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<select name="%s" id="%s" class="%s"%s%s>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$required ? ' required="required" aria-required="true"' : '',
			self::invalid_attrs( $name, $error )
		);

		if ( $required || ! empty( $attrs['include_blank'] ) ) {
			$html .= '<option value="">---</option>';
		}

		foreach ( $values as $option ) {
			$selected = ( null !== $posted && (string) $option === $posted ) ? ' selected="selected"' : '';
			$html    .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $option ),
				$selected,
				esc_html( $option )
			);
		}

		$html .= '</select>';
		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render checkbox field.
	 *
	 * @param string   $name     Field name.
	 * @param bool     $required Is required.
	 * @param string[] $values   Options.
	 * @param int      $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_checkbox( $name, $required, $values, $form_id ) {
		$id     = self::field_id( $form_id, $name );
		$error  = self::state_error( $name );
		$class  = self::field_class( 'checkbox', $required, '' !== $error );
		$posted = self::state_value( $name );
		$chosen = array();

		if ( is_array( $posted ) ) {
			$chosen = $posted;
		} elseif ( null !== $posted && '' !== $posted ) {
			$chosen = array( $posted );
		}

		$html = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );

		foreach ( $values as $index => $option ) {
			$checkbox_id = $id . '-' . absint( $index );
			$checked     = in_array( $option, $chosen, true ) ? ' checked="checked"' : '';

			$html .= sprintf(
				'<span class="oli-cf-list-item"><label><input type="checkbox" name="%s[]" value="%s" id="%s" class="%s"%s%s%s /><span class="oli-cf-list-item-label">%s</span></label></span>',
				esc_attr( $name ),
				esc_attr( $option ),
				esc_attr( $checkbox_id ),
				esc_attr( $class ),
				$required ? ' aria-required="true"' : '',
				$checked,
				self::invalid_attrs( $name, $error ),
				esc_html( $option )
			);
		}

		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render radio field.
	 *
	 * @param string   $name     Field name.
	 * @param bool     $required Is required.
	 * @param string[] $values   Options.
	 * @param int      $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_radio( $name, $required, $values, $form_id ) {
		$id     = self::field_id( $form_id, $name );
		$error  = self::state_error( $name );
		$class  = self::field_class( 'radio', $required, '' !== $error );
		$posted = self::state_value( $name );
		$posted = ( null !== $posted && ! is_array( $posted ) ) ? (string) $posted : null;

		$html = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );

		foreach ( $values as $index => $option ) {
			$radio_id = $id . '-' . absint( $index );
			$checked  = ( null !== $posted && (string) $option === $posted ) ? ' checked="checked"' : '';

			$html .= sprintf(
				'<span class="oli-cf-list-item"><label><input type="radio" name="%s" value="%s" id="%s" class="%s"%s%s%s /><span class="oli-cf-list-item-label">%s</span></label></span>',
				esc_attr( $name ),
				esc_attr( $option ),
				esc_attr( $radio_id ),
				esc_attr( $class ),
				$required ? ' required="required" aria-required="true"' : '',
				$checked,
				self::invalid_attrs( $name, $error ),
				esc_html( $option )
			);
		}

		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render acceptance field.
	 *
	 * @param string   $name     Field name.
	 * @param bool     $required Is required.
	 * @param string[] $values   Label parts.
	 * @param int      $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_acceptance( $name, $required, $values, $form_id ) {
		$id      = self::field_id( $form_id, $name );
		$label   = ! empty( $values ) ? implode( ' ', $values ) : __( 'I accept the terms', 'oli-contact-form' );
		$error   = self::state_error( $name );
		$class   = self::field_class( 'acceptance', $required, '' !== $error );
		$posted  = self::state_value( $name );
		$checked = ( null !== $posted && '1' === (string) $posted ) ? ' checked="checked"' : '';

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<span class="oli-cf-list-item"><label><input type="checkbox" name="%s" value="1" id="%s" class="%s"%s%s%s /><span class="oli-cf-list-item-label">%s</span></label></span>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$required ? ' required="required" aria-required="true"' : '',
			$checked,
			self::invalid_attrs( $name, $error ),
			wp_kses_post( $label )
		);
		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render file field.
	 *
	 * @param string               $name     Field name.
	 * @param bool                 $required Is required.
	 * @param array<string,string> $attrs    Field attributes.
	 * @param int                  $form_id  Form ID.
	 * @return string HTML.
	 */
	private static function render_file( $name, $required, $attrs, $form_id ) {
		$id    = self::field_id( $form_id, $name );
		$error = self::state_error( $name );
		$class = self::field_class( 'file', $required, '' !== $error );

		$types  = isset( $attrs['filetypes'] ) ? $attrs['filetypes'] : Oli_CF_Uploads::DEFAULT_FILETYPES;
		$limit  = isset( $attrs['limit'] ) ? $attrs['limit'] : Oli_CF_Uploads::DEFAULT_LIMIT;
		$accept = Oli_CF_Uploads::accept_attribute( $types );

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<input type="file" name="%s" id="%s" class="%s"%s%s data-size-limit="%s" data-filetypes="%s"%s />',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$required ? ' required="required" aria-required="true"' : '',
			'' !== $accept ? ' accept="' . esc_attr( $accept ) . '"' : '',
			esc_attr( $limit ),
			esc_attr( $types ),
			self::invalid_attrs( $name, $error )
		);
		$html .= self::field_error_html( $name, $error );
		$html .= '</span>';

		return $html;
	}

	/**
	 * Render submit button.
	 *
	 * @param string[] $values  Button label parts.
	 * @param int      $form_id Form ID.
	 * @return string HTML.
	 */
	private static function render_submit( $values, $form_id ) {
		$label = ! empty( $values ) ? implode( ' ', $values ) : __( 'Send', 'oli-contact-form' );

		return sprintf(
			'<button type="submit" class="oli-cf-submit" id="oli-cf-%d-submit">%s</button>',
			absint( $form_id ),
			esc_html( $label )
		);
	}

	/**
	 * maxlength / minlength HTML attributes from a parsed tag.
	 *
	 * @param array<string, string> $attrs Field attributes.
	 * @return string
	 */
	private static function length_attributes( $attrs ) {
		$html = '';

		if ( ! is_array( $attrs ) ) {
			return $html;
		}

		if ( isset( $attrs['maxlength'] ) && is_numeric( $attrs['maxlength'] ) ) {
			$max = absint( $attrs['maxlength'] );

			if ( $max > 0 ) {
				$html .= ' maxlength="' . $max . '"';
			}
		}

		if ( isset( $attrs['minlength'] ) && is_numeric( $attrs['minlength'] ) ) {
			$min = absint( $attrs['minlength'] );

			if ( $min > 0 ) {
				$html .= ' minlength="' . $min . '"';
			}
		}

		return $html;
	}

	/**
	 * Field element id.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $name    Field name.
	 * @return string
	 */
	private static function field_id( $form_id, $name ) {
		return 'oli-cf-' . absint( $form_id ) . '-' . sanitize_key( $name );
	}

	/**
	 * Field CSS class list.
	 *
	 * @param string $type      Field type.
	 * @param bool   $required  Is required.
	 * @param bool   $invalid   Whether the field has a replayed error.
	 * @return string
	 */
	private static function field_class( $type, $required, $invalid = false ) {
		$class = 'oli-cf-field oli-cf-' . sanitize_html_class( $type );

		if ( $required ) {
			$class .= ' oli-cf-required';
		}

		if ( $invalid ) {
			$class .= ' oli-cf-invalid';
		}

		return $class;
	}

	/**
	 * Posted value for the field currently being rendered, if any.
	 *
	 * @param string $name Field name.
	 * @return mixed
	 */
	private static function state_value( $name ) {
		if ( '' === $name || ! isset( self::$render_state['values'] ) || ! is_array( self::$render_state['values'] ) ) {
			return null;
		}

		if ( ! array_key_exists( $name, self::$render_state['values'] ) ) {
			return null;
		}

		return self::$render_state['values'][ $name ];
	}

	/**
	 * Replayed error message for a field.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private static function state_error( $name ) {
		if ( '' === $name || ! isset( self::$render_state['errors'] ) || ! is_array( self::$render_state['errors'] ) ) {
			return '';
		}

		if ( ! isset( self::$render_state['errors'][ $name ] ) || ! is_string( self::$render_state['errors'][ $name ] ) ) {
			return '';
		}

		return self::$render_state['errors'][ $name ];
	}

	/**
	 * aria-invalid / aria-describedby for a field with a replayed error.
	 *
	 * @param string $name  Field name.
	 * @param string $error Error message.
	 * @return string
	 */
	private static function invalid_attrs( $name, $error ) {
		if ( '' === $error ) {
			return ' aria-invalid="false"';
		}

		return ' aria-invalid="true" aria-describedby="' . esc_attr( 'oli-cf-err-' . sanitize_key( $name ) ) . '"';
	}

	/**
	 * Per-field error span.
	 *
	 * @param string $name  Field name.
	 * @param string $error Error message.
	 * @return string
	 */
	private static function field_error_html( $name, $error ) {
		if ( '' === $error ) {
			return '';
		}

		return sprintf(
			'<span class="oli-cf-field-error" id="%s">%s</span>',
			esc_attr( 'oli-cf-err-' . sanitize_key( $name ) ),
			esc_html( $error )
		);
	}
}
