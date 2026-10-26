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
	 * Parse form template and return HTML.
	 *
	 * @param string $template Form template with CF7-like tags.
	 * @param int    $form_id  Form ID.
	 * @return string Parsed HTML.
	 */
	public static function parse( $template, $form_id ) {
		$html = (string) $template;

		$html = preg_replace_callback(
			'/\[([^\]]+)\]/',
			function ( $matches ) use ( $form_id ) {
				return self::render_parsed_field( self::parse_tag( $matches[1] ), (int) $form_id );
			},
			$html
		);

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

		preg_match_all( '/\[([^\]]+)\]/', (string) $template, $matches );

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

		if ( 'submit' !== $type && ! empty( $tokens ) && false === strpos( $tokens[0], ':' ) ) {
			$name = array_shift( $tokens );
		}

		foreach ( $tokens as $token ) {
			if ( preg_match( '/^([a-z0-9_-]+):(.*)$/i', $token, $matches ) ) {
				$attributes[ strtolower( $matches[1] ) ] = $matches[2];
				continue;
			}

			$values[] = $token;
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
			'class'         => true,
			'id'            => true,
			'style'         => true,
			'aria-hidden'   => true,
			'aria-invalid'  => true,
			'aria-required' => true,
			'aria-live'     => true,
			'role'          => true,
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
					'data-size-limit' => true,
					'data-filetypes'  => true,
					'autocomplete'    => true,
					'tabindex'        => true,
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
		$class     = self::field_class( $html_type, $required );

		$placeholder = isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '';
		$default     = isset( $attrs['default'] ) ? $attrs['default'] : '';

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<input type="%s" name="%s" id="%s" class="%s" value="%s"%s%s%s aria-invalid="false" />',
			esc_attr( $html_type ),
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			esc_attr( $default ),
			$required ? ' required="required" aria-required="true"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
			isset( $attrs['id'] ) ? '' : ''
		);
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
		$class = self::field_class( 'textarea', $required );

		$placeholder = isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '';
		$default     = isset( $attrs['default'] ) ? $attrs['default'] : '';
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
			'<textarea name="%s" id="%s" class="%s" rows="%d" cols="%d"%s%s aria-invalid="false">%s</textarea>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$rows,
			$cols,
			$required ? ' required="required" aria-required="true"' : '',
			'' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
			esc_textarea( $default )
		);
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
		$id    = self::field_id( $form_id, $name );
		$class = self::field_class( 'select', $required );

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<select name="%s" id="%s" class="%s"%s aria-invalid="false">',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$required ? ' required="required" aria-required="true"' : ''
		);

		if ( $required || ! empty( $attrs['include_blank'] ) ) {
			$html .= '<option value="">---</option>';
		}

		foreach ( $values as $option ) {
			$html .= sprintf(
				'<option value="%s">%s</option>',
				esc_attr( $option ),
				esc_html( $option )
			);
		}

		$html .= '</select></span>';

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
		$id    = self::field_id( $form_id, $name );
		$class = self::field_class( 'checkbox', $required );

		$html = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );

		foreach ( $values as $index => $option ) {
			$checkbox_id = $id . '-' . absint( $index );

			$html .= sprintf(
				'<span class="oli-cf-list-item"><label><input type="checkbox" name="%s[]" value="%s" id="%s" class="%s"%s /><span class="oli-cf-list-item-label">%s</span></label></span>',
				esc_attr( $name ),
				esc_attr( $option ),
				esc_attr( $checkbox_id ),
				esc_attr( $class ),
				$required ? ' aria-required="true"' : '',
				esc_html( $option )
			);
		}

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
		$id    = self::field_id( $form_id, $name );
		$class = self::field_class( 'radio', $required );

		$html = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );

		foreach ( $values as $index => $option ) {
			$radio_id = $id . '-' . absint( $index );

			$html .= sprintf(
				'<span class="oli-cf-list-item"><label><input type="radio" name="%s" value="%s" id="%s" class="%s"%s /><span class="oli-cf-list-item-label">%s</span></label></span>',
				esc_attr( $name ),
				esc_attr( $option ),
				esc_attr( $radio_id ),
				esc_attr( $class ),
				$required ? ' required="required" aria-required="true"' : '',
				esc_html( $option )
			);
		}

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
		$id    = self::field_id( $form_id, $name );
		$label = ! empty( $values ) ? implode( ' ', $values ) : __( 'I accept the terms', 'oli-contact-form' );
		$class = self::field_class( 'acceptance', $required );

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<span class="oli-cf-list-item"><label><input type="checkbox" name="%s" value="1" id="%s" class="%s"%s aria-invalid="false" /><span class="oli-cf-list-item-label">%s</span></label></span>',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$required ? ' required="required" aria-required="true"' : '',
			wp_kses_post( $label )
		);
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
		$class = self::field_class( 'file', $required );

		$types  = isset( $attrs['filetypes'] ) ? $attrs['filetypes'] : Oli_CF_Uploads::DEFAULT_FILETYPES;
		$limit  = isset( $attrs['limit'] ) ? $attrs['limit'] : Oli_CF_Uploads::DEFAULT_LIMIT;
		$accept = Oli_CF_Uploads::accept_attribute( $types );

		$html  = sprintf( '<span class="oli-cf-form-control-wrap" data-name="%s">', esc_attr( $name ) );
		$html .= sprintf(
			'<input type="file" name="%s" id="%s" class="%s"%s%s data-size-limit="%s" data-filetypes="%s" aria-invalid="false" />',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $class ),
			$required ? ' required="required" aria-required="true"' : '',
			'' !== $accept ? ' accept="' . esc_attr( $accept ) . '"' : '',
			esc_attr( $limit ),
			esc_attr( $types )
		);
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
	 * @param string $type     Field type.
	 * @param bool   $required Is required.
	 * @return string
	 */
	private static function field_class( $type, $required ) {
		$class = 'oli-cf-field oli-cf-' . sanitize_html_class( $type );

		if ( $required ) {
			$class .= ' oli-cf-required';
		}

		return $class;
	}
}
