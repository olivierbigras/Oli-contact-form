<?php
/**
 * Contract for a form-import source.
 *
 * @package Oli_Contact_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A source can be detected, listed, and converted without being modified.
 */
interface Oli_CF_Import_Adapter {

	/**
	 * Stable source key stored on imported forms.
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Name shown in the import screen.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the source plugin is active.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Whether saved forms remain, even if the plugin is inactive.
	 *
	 * @return bool
	 */
	public function has_data();

	/**
	 * Forms that can be imported.
	 *
	 * @return array<int, array{id: string, title: string}>
	 */
	public function list_forms();

	/**
	 * Convert one form. Returns null when it cannot be read.
	 *
	 * @param int|string $source_id Source form ID.
	 * @return array<string, mixed>|null
	 */
	public function convert( $source_id );
}
