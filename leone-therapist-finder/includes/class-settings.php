<?php
/**
 * Plugin settings (stored in a single option).
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, defaults and sanitises the plugin settings.
 */
final class Settings {

	const OPTION = 'ltf_settings';

	/**
	 * Default settings. Not translated: these are editable content, and this runs before `init`.
	 */
	public static function defaults(): array {
		return array(
			'post_type'     => 'associates',
			'scheduler_url' => 'https://leonecentre.as.me/',
			'contact_url'   => '/contact-us/',
			'help_title'    => 'Not sure who to choose?',
			'help_text'     => 'Our team can match you with the right therapist. Call us on <a href="tel:+442039301007">020 3930 1007</a> or <a href="/contact-us/">send us a message</a>.',
			'issue_limit'   => 4,
			'accent'        => '#2cb2b2',
		);
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Raw submitted value.
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		$post_type          = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
		$clean['post_type'] = $post_type ? $post_type : $defaults['post_type'];

		$scheduler              = esc_url_raw( trim( (string) ( $input['scheduler_url'] ?? '' ) ), array( 'https', 'http' ) );
		$clean['scheduler_url'] = $scheduler ? trailingslashit( $scheduler ) : $defaults['scheduler_url'];

		$clean['contact_url'] = esc_url_raw( trim( (string) ( $input['contact_url'] ?? '' ) ) );
		$clean['help_title']  = sanitize_text_field( (string) ( $input['help_title'] ?? '' ) );
		$clean['help_text']   = wp_kses_post( (string) ( $input['help_text'] ?? '' ) );
		$clean['issue_limit'] = max( 1, min( 20, absint( $input['issue_limit'] ?? $defaults['issue_limit'] ) ) );

		$accent          = sanitize_hex_color( (string) ( $input['accent'] ?? '' ) );
		$clean['accent'] = $accent ? $accent : $defaults['accent'];

		// The post type may have changed: let WordPress rebuild permalinks on the next request.
		if ( self::get( 'post_type' ) !== $clean['post_type'] ) {
			delete_option( 'rewrite_rules' );
		}

		return $clean;
	}
}
