<?php
/**
 * Email notifications for a form.
 *
 * A form has a list of notifications (admin copy, sender confirmation, and
 * any further messages). Each one has recipients, a subject, a body, and an
 * optional condition. Tokens look like {field:email} and {site:title}.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification schema and conversion from the previous mail settings.
 */
class Oli_CF_Notifications {

	/**
	 * Post meta key.
	 *
	 * @var string
	 */
	const META = '_oli_cf_notifications';

	/**
	 * Empty notification.
	 *
	 * @return array<string, mixed>
	 */
	public static function blank() {
		return array(
			'id'        => '',
			'label'     => '',
			'enabled'   => false,
			'to'        => '',
			'from'      => '',
			'reply_to'  => '',
			'subject'   => '',
			'body'      => '',
			'headers'   => '',
			'condition' => array(
				'field'    => '',
				'operator' => 'always',
				'value'    => '',
			),
		);
	}

	/**
	 * Default admin notification and sender confirmation.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function defaults() {
		$admin             = self::blank();
		$admin['id']       = 'admin';
		$admin['label']    = __( 'Admin notification', 'oli-contact-form' );
		$admin['enabled']  = true;
		$admin['to']       = '{site:admin_email}';
		$admin['from']     = '{site:admin_email}';
		$admin['reply_to'] = '{field:email}';
		$admin['subject']  = __( 'New message from {field:name}', 'oli-contact-form' );
		$admin['body']     = sprintf(
			/* translators: 1: name token, 2: email token, 3: subject token, 4: message token. */
			__( "From: %1\$s <%2\$s>\nSubject: %3\$s\n\nMessage:\n%4\$s", 'oli-contact-form' ),
			'{field:name}',
			'{field:email}',
			'{field:subject}',
			'{field:message}'
		);

		$sender            = self::blank();
		$sender['id']      = 'sender';
		$sender['label']   = __( 'Sender confirmation', 'oli-contact-form' );
		$sender['enabled'] = false;
		$sender['to']      = '{field:email}';
		$sender['from']    = '{site:admin_email}';
		$sender['subject'] = __( 'Thank you for your message', 'oli-contact-form' );
		$sender['body']    = __( "Thank you for contacting us.\n\nWe will get back to you soon.", 'oli-contact-form' );

		return array( $admin, $sender );
	}

	/**
	 * Operators for a simple condition.
	 *
	 * @return array<string, string>
	 */
	public static function operators() {
		return array(
			'always'     => __( 'Always send', 'oli-contact-form' ),
			'not_empty'  => __( 'Field is not empty', 'oli-contact-form' ),
			'empty'      => __( 'Field is empty', 'oli-contact-form' ),
			'equals'     => __( 'Field equals', 'oli-contact-form' ),
			'not_equals' => __( 'Field does not equal', 'oli-contact-form' ),
		);
	}

	/**
	 * Notifications saved on a form.
	 *
	 * An empty stored list is respected. Missing meta falls back to the
	 * previous mail settings when those exist.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_form( $form_id ) {
		$stored = get_post_meta( (int) $form_id, self::META, true );

		if ( self::is_stored( $stored ) ) {
			return self::decode( $stored );
		}

		$mail  = get_post_meta( (int) $form_id, '_oli_cf_mail', true );
		$mail2 = get_post_meta( (int) $form_id, '_oli_cf_mail_2', true );

		if ( ( is_array( $mail ) && ! empty( $mail ) ) || ( is_array( $mail2 ) && ! empty( $mail2 ) ) ) {
			return self::from_legacy_mail( $mail, $mail2 );
		}

		return array();
	}

	/**
	 * Notifications for the editor, with defaults when nothing is saved yet.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_editor( $form_id ) {
		$list = self::for_form( $form_id );

		if ( empty( $list ) && ! self::is_stored( get_post_meta( (int) $form_id, self::META, true ) ) ) {
			return self::defaults();
		}

		return $list;
	}

	/**
	 * Whether meta contains an explicit list.
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
	 * Decode stored notifications.
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
	 * JSON for post meta.
	 *
	 * @param array<int, array<string, mixed>> $notifications Notifications.
	 * @return string
	 */
	public static function encode( $notifications ) {
		$json = wp_json_encode( self::normalize_list( $notifications ) );

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
	 * Write the notification list. The JSON is slashed because post meta unslashes on save.
	 *
	 * @param int                              $post_id       Form ID.
	 * @param array<int, array<string, mixed>> $notifications Notifications.
	 */
	public static function save( $post_id, $notifications ) {
		Oli_CF_Json_Meta::write( $post_id, self::META, self::encode( $notifications ) );
	}

	/**
	 * Normalize a list of notifications.
	 *
	 * @param array<int, mixed> $notifications Raw list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_list( $notifications ) {
		$clean = array();
		$used  = array();

		if ( ! is_array( $notifications ) ) {
			return $clean;
		}

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			$item = self::sanitize_one( $notification );

			if ( '' === $item['label'] && '' === $item['to'] && '' === $item['subject'] && '' === $item['body'] ) {
				continue;
			}

			if ( '' === $item['id'] ) {
				$item['id'] = 'notice';
			}

			$base = $item['id'];
			$i    = 2;

			while ( isset( $used[ $item['id'] ] ) ) {
				$item['id'] = $base . '-' . $i;
				++$i;
			}

			$used[ $item['id'] ] = true;
			$clean[]             = $item;
		}

		return $clean;
	}

	/**
	 * Sanitize one posted or stored notification.
	 *
	 * @param array<string, mixed> $notification Raw notification.
	 * @return array<string, mixed>
	 */
	public static function sanitize_one( $notification ) {
		$item             = self::blank();
		$item['id']       = Oli_CF_Fields::sanitize_name( isset( $notification['id'] ) ? $notification['id'] : '' );
		$item['label']    = Oli_CF_Fields::sanitize_label( isset( $notification['label'] ) ? $notification['label'] : '' );
		$item['enabled']  = ! empty( $notification['enabled'] );
		$item['to']       = Oli_CF_Mailer::sanitize_mail_line( isset( $notification['to'] ) ? $notification['to'] : '' );
		$item['from']     = Oli_CF_Mailer::sanitize_mail_line( isset( $notification['from'] ) ? $notification['from'] : '' );
		$item['reply_to'] = Oli_CF_Mailer::sanitize_mail_line( isset( $notification['reply_to'] ) ? $notification['reply_to'] : '' );
		$item['subject']  = Oli_CF_Mailer::sanitize_mail_line( isset( $notification['subject'] ) ? $notification['subject'] : '' );
		$item['body']     = Oli_CF_Mailer::sanitize_mail_body( isset( $notification['body'] ) ? $notification['body'] : '' );
		$item['headers']  = Oli_CF_Mailer::sanitize_mail_headers( isset( $notification['headers'] ) ? $notification['headers'] : '' );

		$condition = isset( $notification['condition'] ) && is_array( $notification['condition'] ) ? $notification['condition'] : array();

		if ( isset( $notification['condition_operator'] ) || isset( $notification['condition_field'] ) ) {
			$condition = array(
				'field'    => isset( $notification['condition_field'] ) ? $notification['condition_field'] : '',
				'operator' => isset( $notification['condition_operator'] ) ? $notification['condition_operator'] : 'always',
				'value'    => isset( $notification['condition_value'] ) ? $notification['condition_value'] : '',
			);
		}

		$operator = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'always';

		if ( ! isset( self::operators()[ $operator ] ) ) {
			$operator = 'always';
		}

		$item['condition'] = array(
			'field'    => Oli_CF_Fields::sanitize_name( isset( $condition['field'] ) ? $condition['field'] : '' ),
			'operator' => $operator,
			'value'    => Oli_CF_Fields::sanitize_label( isset( $condition['value'] ) ? $condition['value'] : '' ),
		);

		return $item;
	}

	/**
	 * Build notifications from the previous mail settings.
	 *
	 * @param mixed $mail  Previous primary mail settings.
	 * @param mixed $mail2 Previous second mail settings.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_legacy_mail( $mail, $mail2 ) {
		$list = array();

		if ( is_array( $mail ) && ! empty( $mail ) ) {
			$enabled = self::legacy_mail_enabled( $mail, true );
			$mail    = Oli_CF_Mailer::normalize_mail( $mail, false );
			$list[]  = self::from_mail_array( 'admin', __( 'Admin notification', 'oli-contact-form' ), $enabled, $mail );
		}

		if ( is_array( $mail2 ) && ! empty( $mail2 ) ) {
			$enabled = self::legacy_mail_enabled( $mail2, false );
			$mail2   = Oli_CF_Mailer::normalize_mail( $mail2, true );
			$list[]  = self::from_mail_array(
				'sender',
				__( 'Sender confirmation', 'oli-contact-form' ),
				$enabled,
				$mail2
			);
		}

		return self::normalize_list( $list );
	}

	/**
	 * Whether a previous mail box was turned on.
	 *
	 * @param array<string, mixed> $mail     Mail box.
	 * @param bool                 $fallback Value when the box does not say.
	 * @return bool
	 */
	private static function legacy_mail_enabled( $mail, $fallback ) {
		if ( array_key_exists( 'active', $mail ) ) {
			return ! empty( $mail['active'] );
		}

		if ( array_key_exists( 'enabled', $mail ) ) {
			return ! empty( $mail['enabled'] );
		}

		return $fallback;
	}

	/**
	 * One notification from a previous mail array.
	 *
	 * @param string               $id       Notification id.
	 * @param string               $label    Label.
	 * @param bool                 $enabled  Enabled flag.
	 * @param array<string, mixed> $mail     Mail settings.
	 * @return array<string, mixed>
	 */
	public static function from_mail_array( $id, $label, $enabled, $mail ) {
		$item             = self::blank();
		$item['id']       = $id;
		$item['label']    = $label;
		$item['enabled']  = (bool) $enabled;
		$item['to']       = self::rewrite_tokens( isset( $mail['to'] ) ? $mail['to'] : '' );
		$item['from']     = self::rewrite_tokens( isset( $mail['from'] ) ? $mail['from'] : '' );
		$item['reply_to'] = self::rewrite_tokens( isset( $mail['reply_to'] ) ? $mail['reply_to'] : '' );
		$item['subject']  = self::rewrite_tokens( isset( $mail['subject'] ) ? $mail['subject'] : '' );
		$item['body']     = self::rewrite_tokens( isset( $mail['body'] ) ? $mail['body'] : '' );
		$item['headers']  = self::rewrite_tokens( isset( $mail['additional_headers'] ) ? $mail['additional_headers'] : '' );

		return $item;
	}

	/**
	 * Map a notification onto the mail payload builder.
	 *
	 * @param array<string, mixed> $notification Notification.
	 * @return array<string, string>
	 */
	public static function to_mail_settings( $notification ) {
		$notification = self::sanitize_one( $notification );

		return array(
			'to'                 => $notification['to'],
			'from'               => $notification['from'],
			'subject'            => $notification['subject'],
			'body'               => $notification['body'],
			'reply_to'           => $notification['reply_to'],
			'additional_headers' => $notification['headers'],
		);
	}

	/**
	 * Whether a notification should be sent for this submission.
	 *
	 * @param array<string, mixed> $notification Notification.
	 * @param array<string, mixed> $data         Posted data.
	 * @return bool
	 */
	public static function condition_matches( $notification, $data ) {
		$notification = self::sanitize_one( $notification );
		$operator     = $notification['condition']['operator'];

		if ( 'always' === $operator ) {
			return true;
		}

		$field = $notification['condition']['field'];
		$value = ( '' !== $field && isset( $data[ $field ] ) ) ? $data[ $field ] : '';

		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}

		$value    = trim( (string) $value );
		$expected = (string) $notification['condition']['value'];

		if ( 'not_empty' === $operator ) {
			return '' !== $value;
		}

		if ( 'empty' === $operator ) {
			return '' === $value;
		}

		if ( 'equals' === $operator ) {
			return $value === $expected;
		}

		if ( 'not_equals' === $operator ) {
			return $value !== $expected;
		}

		return true;
	}

	/**
	 * Rewrite tokens from the previous mail syntax.
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	public static function rewrite_tokens( $text ) {
		$text = (string) $text;
		$map  = array(
			'[_site_admin_email]'  => '{site:admin_email}',
			'[_site_title]'        => '{site:title}',
			'[_site_url]'          => '{site:url}',
			'[_site_description]'  => '{site:description}',
			'[_remote_ip]'         => '{submission:ip}',
			'[_user_agent]'        => '{submission:user_agent}',
			'[_url]'               => '{submission:url}',
			'[_date]'              => '{submission:date}',
			'[_time]'              => '{submission:time}',
			'[_post_id]'           => '{post:id}',
			'[_post_name]'         => '{post:name}',
			'[_post_title]'        => '{post:title}',
			'[_post_url]'          => '{post:url}',
			'[_post_author]'       => '{post:author}',
			'[_post_author_email]' => '{post:author_email}',
			'[_user_login]'        => '{user:login}',
			'[_user_email]'        => '{user:email}',
			'[_user_display_name]' => '{user:display_name}',
			'[_user_first_name]'   => '{user:first_name}',
			'[_user_last_name]'    => '{user:last_name}',
			'[_user_id]'           => '{user:id}',
		);

		$text = strtr( $text, $map );

		$rewritten = preg_replace_callback(
			'/\[([A-Za-z][A-Za-z0-9_-]*)\]/',
			static function ( $matches ) {
				return '{field:' . $matches[1] . '}';
			},
			$text
		);

		return is_string( $rewritten ) ? $rewritten : $text;
	}

	/**
	 * Sanitize a posted list of notifications.
	 *
	 * @param mixed $posted Posted rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_posted( $posted ) {
		if ( ! is_array( $posted ) ) {
			return array();
		}

		return self::normalize_list( $posted );
	}
}
