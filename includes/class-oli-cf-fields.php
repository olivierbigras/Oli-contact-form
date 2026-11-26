<?php
/**
 * Canonical form fields.
 *
 * Forms are a list of fields (type, name, label, required, options), stored as
 * JSON in post meta. A text form uses attributes, for example
 * {{field type="email" name="email" label="Email" required}}.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field schema, text form, and conversion from the previous template storage.
 */
class Oli_CF_Fields {

	/**
	 * Post meta key for the JSON field list.
	 *
	 * @var string
	 */
	const META = '_oli_cf_fields';

	/**
	 * Schema version stored on each form after conversion.
	 *
	 * @var string
	 */
	const SCHEMA = '2';

	/**
	 * Field types this plugin renders.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array(
			'text',
			'email',
			'tel',
			'url',
			'number',
			'date',
			'textarea',
			'select',
			'radio',
			'checkbox',
			'acceptance',
			'file',
			'hidden',
			'submit',
		);
	}

	/**
	 * Empty field.
	 *
	 * @return array<string, mixed>
	 */
	public static function blank() {
		return array(
			'type'        => 'text',
			'name'        => '',
			'label'       => '',
			'required'    => false,
			'placeholder' => '',
			'options'     => array(),
			'default'     => '',
			'maxlength'   => '',
			'minlength'   => '',
			'limit'       => '',
			'filetypes'   => '',
		);
	}

	/**
	 * Build one sanitized field.
	 *
	 * @param string               $type     Field type.
	 * @param string               $name     Machine name.
	 * @param string               $label    Visible label.
	 * @param bool                 $required Whether the field is required.
	 * @param array<string, mixed> $args     Optional properties.
	 * @return array<string, mixed>
	 */
	public static function make( $type, $name, $label, $required = false, $args = array() ) {
		$field             = self::blank();
		$field['type']     = self::normalize_type( $type );
		$field['name']     = self::sanitize_name( $name );
		$field['label']    = self::sanitize_label( $label );
		$field['required'] = (bool) $required;

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		foreach ( array( 'placeholder', 'default', 'maxlength', 'minlength', 'limit', 'filetypes' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$field[ $key ] = self::sanitize_label( $args[ $key ] );
			}
		}

		if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
			$field['options'] = self::sanitize_options( $args['options'] );
		}

		if ( 'submit' === $field['type'] && '' === $field['name'] ) {
			$field['name'] = 'submit';
		}

		if ( 'acceptance' === $field['type'] && '' === $field['label'] && ! empty( $field['options'] ) ) {
			$field['label']   = $field['options'][0];
			$field['options'] = array();
		}

		return $field;
	}

	/**
	 * Default fields for a new form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function defaults() {
		return array(
			self::make( 'text', 'name', __( 'Your name', 'oli-contact-form' ), true ),
			self::make( 'email', 'email', __( 'Your email', 'oli-contact-form' ), true ),
			self::make( 'text', 'subject', __( 'Subject', 'oli-contact-form' ), true ),
			self::make( 'textarea', 'message', __( 'Your message', 'oli-contact-form' ), false ),
			self::make( 'acceptance', 'consent', __( 'I consent to being contacted about this inquiry.', 'oli-contact-form' ), false ),
			self::make( 'submit', 'submit', __( 'Send', 'oli-contact-form' ), false ),
		);
	}

	/**
	 * Whether a string is the text form rather than an older template.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_markup( $text ) {
		return is_string( $text ) && false !== strpos( $text, '{{field' );
	}

	/**
	 * Fields saved on a form, or a conversion of the previous template.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_form( $form_id ) {
		$stored = get_post_meta( (int) $form_id, self::META, true );

		if ( self::is_stored( $stored ) ) {
			return self::decode( $stored );
		}

		$legacy = get_post_meta( (int) $form_id, '_oli_cf_legacy_template', true );

		if ( ! is_string( $legacy ) || '' === trim( $legacy ) ) {
			$legacy = get_post_meta( (int) $form_id, '_oli_cf_form_template', true );
		}

		if ( is_string( $legacy ) && '' !== trim( $legacy ) ) {
			$report = self::legacy_report( $legacy );

			return $report['fields'];
		}

		return array();
	}

	/**
	 * Decode stored JSON or an array into a field list.
	 *
	 * @param mixed $stored Stored value.
	 * @return array<int, array<string, mixed>>
	 */
	public static function decode( $stored ) {
		if ( is_string( $stored ) && '' !== $stored ) {
			$decoded = json_decode( $stored, true );
			$stored  = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return self::normalize_list( $stored );
	}

	/**
	 * Whether meta contains an explicit field list, including an empty list.
	 *
	 * @param mixed $stored Stored value.
	 * @return bool
	 */
	public static function is_stored( $stored ) {
		if ( is_array( $stored ) ) {
			return true;
		}

		return is_string( $stored ) && '' !== $stored;
	}

	/**
	 * JSON for post meta.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return string
	 */
	public static function encode( $fields ) {
		$json = wp_json_encode( self::normalize_list( $fields ) );

		return is_string( $json ) ? $json : '[]';
	}

	/**
	 * Read the JSON stored for one form.
	 *
	 * @param int $post_id Form ID.
	 * @return string
	 */
	public static function read( $post_id ) {
		return Oli_CF_Json_Meta::read( $post_id, self::META );
	}

	/**
	 * Write the field list. The JSON is slashed because post meta unslashes on save.
	 *
	 * @param int                              $post_id Form ID.
	 * @param array<int, array<string, mixed>> $fields  Fields.
	 */
	public static function save( $post_id, $fields ) {
		Oli_CF_Json_Meta::write( $post_id, self::META, self::encode( $fields ) );
	}

	/**
	 * Normalize a list of field arrays.
	 *
	 * @param array<int, mixed> $fields Raw fields.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_list( $fields ) {
		$clean = array();
		$used  = array();

		if ( ! is_array( $fields ) ) {
			return $clean;
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$made = self::make(
				isset( $field['type'] ) ? $field['type'] : 'text',
				isset( $field['name'] ) ? $field['name'] : '',
				isset( $field['label'] ) ? $field['label'] : '',
				! empty( $field['required'] ),
				$field
			);

			if ( '' === $made['name'] || ! in_array( $made['type'], self::types(), true ) ) {
				continue;
			}

			$made['name'] = self::unique_name( $made['name'], $made['name'], $used );
			$clean[]      = $made;
		}

		return $clean;
	}

	/**
	 * Parse the text form into fields.
	 *
	 * @param string $markup Markup.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_markup( $markup ) {
		$fields = array();

		if ( ! is_string( $markup ) || ! preg_match_all( '/\{\{field\b(.*?)\}\}/s', $markup, $matches ) ) {
			return $fields;
		}

		foreach ( $matches[1] as $raw ) {
			$attrs    = self::parse_markup_attributes( $raw );
			$type     = isset( $attrs['type'] ) ? $attrs['type'] : 'text';
			$name     = isset( $attrs['name'] ) ? $attrs['name'] : '';
			$label    = isset( $attrs['label'] ) ? $attrs['label'] : '';
			$required = isset( $attrs['required'] );

			if ( isset( $attrs['optional'] ) ) {
				$required = false;
			} elseif ( 'acceptance' === self::normalize_type( $type ) && ! isset( $attrs['required'] ) ) {
				$required = true;
			}

			$options = array();

			if ( ! empty( $attrs['options'] ) ) {
				$options = preg_split( '/\|/', (string) $attrs['options'] );
				$options = is_array( $options ) ? $options : array();
			}

			$fields[] = self::make(
				$type,
				'' !== $name ? $name : $label,
				$label,
				$required,
				array(
					'placeholder' => isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '',
					'default'     => isset( $attrs['default'] ) ? $attrs['default'] : '',
					'maxlength'   => isset( $attrs['maxlength'] ) ? $attrs['maxlength'] : '',
					'minlength'   => isset( $attrs['minlength'] ) ? $attrs['minlength'] : '',
					'limit'       => isset( $attrs['limit'] ) ? $attrs['limit'] : '',
					'filetypes'   => isset( $attrs['filetypes'] ) ? $attrs['filetypes'] : '',
					'options'     => $options,
				)
			);
		}

		return self::normalize_list( $fields );
	}

	/**
	 * Text form for a field list.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return string
	 */
	public static function to_markup( $fields ) {
		$lines = array();

		foreach ( self::normalize_list( $fields ) as $field ) {
			$parts   = array();
			$parts[] = 'type="' . $field['type'] . '"';
			$parts[] = 'name="' . $field['name'] . '"';

			if ( '' !== $field['label'] ) {
				$parts[] = 'label="' . str_replace( '"', '', $field['label'] ) . '"';
			}

			if ( 'acceptance' === $field['type'] ) {
				$parts[] = ! empty( $field['required'] ) ? 'required' : 'optional';
			} elseif ( ! empty( $field['required'] ) ) {
				$parts[] = 'required';
			}

			foreach ( array( 'placeholder', 'default', 'maxlength', 'minlength', 'limit', 'filetypes' ) as $key ) {
				if ( '' !== (string) $field[ $key ] ) {
					$parts[] = $key . '="' . str_replace( '"', '', (string) $field[ $key ] ) . '"';
				}
			}

			if ( ! empty( $field['options'] ) ) {
				$parts[] = 'options="' . str_replace( '"', '', implode( '|', $field['options'] ) ) . '"';
			}

			$lines[] = '{{field ' . implode( ' ', $parts ) . '}}';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Convert a template saved by a previous version.
	 *
	 * @param string $template Previous template.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_legacy_template( $template ) {
		$report = self::legacy_report( $template );

		return $report['fields'];
	}

	/**
	 * Convert a previous template and list tags this plugin does not render.
	 *
	 * @param string $template Previous template.
	 * @return array{fields: array<int, array<string, mixed>>, unsupported: array<int, array<string, string>>}
	 */
	public static function legacy_report( $template ) {
		$template    = Oli_CF_Form_Parser::normalize_template( $template );
		$labels      = Oli_CF_Form_Parser::field_labels( $template );
		$parsed      = Oli_CF_Form_Parser::parse_fields( $template );
		$fields      = array();
		$unsupported = array();

		foreach ( $parsed as $field ) {
			$type = isset( $field['type'] ) ? self::normalize_type( $field['type'] ) : '';
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';

			if ( ( '' === $type || 'submit' === $type ) && '' === $name ) {
				$name = 'submit';
			}

			$label = isset( $labels[ $name ] ) ? $labels[ $name ] : '';

			if ( '' === $label && 'acceptance' === $type && ! empty( $field['values'] ) && is_array( $field['values'] ) ) {
				$label = implode( ' ', $field['values'] );
			}

			if ( '' === $label && 'submit' === $type && ! empty( $field['values'][0] ) ) {
				$label = (string) $field['values'][0];
			}

			if ( ! in_array( $type, self::types(), true ) ) {
				$unsupported[] = array(
					'type'  => $type,
					'label' => '' !== $label ? $label : $name,
				);
				continue;
			}

			$attrs   = isset( $field['attributes'] ) && is_array( $field['attributes'] ) ? $field['attributes'] : array();
			$options = array();

			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $field['values'] ) && is_array( $field['values'] ) ) {
				$options = $field['values'];
			}

			$fields[] = self::make(
				$type,
				$name,
				$label,
				! empty( $field['required'] ),
				array(
					'placeholder' => isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '',
					'default'     => isset( $attrs['default'] ) ? $attrs['default'] : '',
					'maxlength'   => isset( $attrs['maxlength'] ) ? $attrs['maxlength'] : '',
					'minlength'   => isset( $attrs['minlength'] ) ? $attrs['minlength'] : '',
					'limit'       => isset( $attrs['limit'] ) ? $attrs['limit'] : '',
					'filetypes'   => isset( $attrs['filetypes'] ) ? $attrs['filetypes'] : '',
					'options'     => $options,
				)
			);
		}

		return array(
			'fields'      => self::normalize_list( $fields ),
			'unsupported' => $unsupported,
		);
	}

	/**
	 * Shape used by the HTML renderer.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<int, array<string, mixed>>
	 */
	public static function to_parser_fields( $fields ) {
		$parser = array();

		foreach ( self::normalize_list( $fields ) as $field ) {
			$attrs = array();

			foreach ( array( 'placeholder', 'default', 'maxlength', 'minlength', 'limit', 'filetypes' ) as $key ) {
				if ( '' !== (string) $field[ $key ] ) {
					$attrs[ $key ] = (string) $field[ $key ];
				}
			}

			$values = $field['options'];

			if ( 'acceptance' === $field['type'] && '' !== $field['label'] ) {
				$values = array( $field['label'] );
			}

			if ( 'submit' === $field['type'] ) {
				$values = array( '' !== $field['label'] ? $field['label'] : __( 'Send', 'oli-contact-form' ) );
			}

			$parser[] = array(
				'type'       => $field['type'],
				'name'       => $field['name'],
				'required'   => ! empty( $field['required'] ),
				'attributes' => $attrs,
				'values'     => $values,
				'label'      => $field['label'],
			);
		}

		return $parser;
	}

	/**
	 * Labels keyed by field name.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return array<string, string>
	 */
	public static function label_map( $fields ) {
		$map = array();

		foreach ( self::normalize_list( $fields ) as $field ) {
			if ( '' === $field['name'] || 'submit' === $field['type'] ) {
				continue;
			}

			if ( '' !== $field['label'] ) {
				$map[ $field['name'] ] = $field['label'];
				continue;
			}

			$map[ $field['name'] ] = Oli_CF_Form_Parser::humanize_field_name( $field['name'] );
		}

		return $map;
	}

	/**
	 * Whether any field is required.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @return bool
	 */
	public static function has_required( $fields ) {
		foreach ( self::normalize_list( $fields ) as $field ) {
			if ( ! empty( $field['required'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize a posted field builder.
	 *
	 * @param mixed $posted Posted rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_posted( $posted ) {
		if ( ! is_array( $posted ) ) {
			return array();
		}

		$rows = array();

		foreach ( $posted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$options = array();

			if ( isset( $row['options'] ) ) {
				$split   = preg_split( '/\r\n|\r|\n/', (string) $row['options'] );
				$options = is_array( $split ) ? $split : array();
			}

			$rows[] = self::make(
				isset( $row['type'] ) ? $row['type'] : 'text',
				isset( $row['name'] ) ? $row['name'] : '',
				isset( $row['label'] ) ? $row['label'] : '',
				! empty( $row['required'] ),
				array(
					'placeholder' => isset( $row['placeholder'] ) ? $row['placeholder'] : '',
					'default'     => isset( $row['default'] ) ? $row['default'] : '',
					'maxlength'   => isset( $row['maxlength'] ) ? $row['maxlength'] : '',
					'minlength'   => isset( $row['minlength'] ) ? $row['minlength'] : '',
					'limit'       => isset( $row['limit'] ) ? $row['limit'] : '',
					'filetypes'   => isset( $row['filetypes'] ) ? $row['filetypes'] : '',
					'options'     => $options,
				)
			);
		}

		return self::normalize_list( $rows );
	}

	/**
	 * A machine name that does not collide with names already used.
	 *
	 * @param string             $label    Preferred label or name.
	 * @param string             $fallback Fallback name.
	 * @param array<string,bool> $used     Names already taken.
	 * @return string
	 */
	public static function unique_name( $label, $fallback, &$used ) {
		$name = self::sanitize_name( $label );

		if ( '' === $name ) {
			$name = self::sanitize_name( $fallback );
		}

		if ( '' === $name ) {
			$name = 'field';
		}

		$base = $name;
		$i    = 2;

		while ( isset( $used[ $name ] ) ) {
			$name = $base . '-' . $i;
			++$i;
		}

		$used[ $name ] = true;

		return $name;
	}

	/**
	 * Normalize a type alias.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	public static function normalize_type( $type ) {
		$type = strtolower( trim( (string) $type ) );

		if ( 'phone' === $type ) {
			return 'tel';
		}

		return $type;
	}

	/**
	 * Sanitize a field machine name.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function sanitize_name( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '/[^a-z0-9_-]+/', '-', $name );
		$name = trim( (string) $name, '-' );

		if ( strlen( $name ) > 60 ) {
			$name = rtrim( substr( $name, 0, 60 ), '-' );
		}

		return $name;
	}

	/**
	 * Sanitize a single line of human text.
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	public static function sanitize_label( $text ) {
		$text = str_replace( array( "\0", '"', '[', ']', '{', '}' ), '', (string) $text );
		$text = wp_strip_all_tags( $text );

		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Sanitize choice labels.
	 *
	 * @param array<int, mixed> $options Raw options.
	 * @return string[]
	 */
	private static function sanitize_options( $options ) {
		$clean = array();

		foreach ( $options as $option ) {
			$option = self::sanitize_label( $option );

			if ( '' !== $option ) {
				$clean[] = $option;
			}
		}

		return $clean;
	}

	/**
	 * Read attributes from the inside of a field tag.
	 *
	 * @param string $raw Attribute text.
	 * @return array<string, string>
	 */
	private static function parse_markup_attributes( $raw ) {
		$attrs = array();

		if ( preg_match_all( '/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/', (string) $raw, $pairs, PREG_SET_ORDER ) ) {
			foreach ( $pairs as $pair ) {
				$attrs[ strtolower( $pair[1] ) ] = $pair[2];
			}
		}

		if ( preg_match_all( '/([a-zA-Z0-9_-]+)\s*=\s*\'([^\']*)\'/', (string) $raw, $pairs, PREG_SET_ORDER ) ) {
			foreach ( $pairs as $pair ) {
				$attrs[ strtolower( $pair[1] ) ] = $pair[2];
			}
		}

		$bare = preg_replace( '/([a-zA-Z0-9_-]+)\s*=\s*("[^"]*"|\'[^\']*\')/', ' ', (string) $raw );

		if ( is_string( $bare ) && preg_match_all( '/(?:^|\s)([a-zA-Z0-9_-]+)(?=\s|$)/', $bare, $flags ) ) {
			foreach ( $flags[1] as $flag ) {
				$key = strtolower( $flag );

				if ( ! isset( $attrs[ $key ] ) ) {
					$attrs[ $key ] = '1';
				}
			}
		}

		return $attrs;
	}
}
