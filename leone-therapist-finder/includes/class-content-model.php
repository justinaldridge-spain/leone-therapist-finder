<?php
/**
 * Taxonomies and meta that describe each therapist.
 *
 * The plugin attaches to the site's existing therapist post type (`associates` on leonecentre.com).
 * If that post type does not exist, a compatible one is registered so the plugin works standalone.
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the data model.
 */
final class Content_Model {

	const TAX_LOCATION = 'ltf_location';
	const TAX_SERVICE  = 'ltf_service';
	const TAX_ISSUE    = 'ltf_issue';
	const TAX_LANGUAGE = 'ltf_language';

	const META_ROLE           = '_ltf_role';
	const META_SUMMARY        = '_ltf_summary';
	const META_YEARS          = '_ltf_years';
	const META_ACCREDITATIONS = '_ltf_accreditations';
	const META_CALENDARS      = '_ltf_calendars';
	const META_HIDE           = '_ltf_hide';

	const TERM_META_APPOINTMENT_TYPE = '_ltf_appointment_type';

	public static function init(): void {
		// Priority 20 so a theme or plugin that registers the therapist post type on `init` runs first.
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Facet key => taxonomy, in the order filters are shown.
	 */
	public static function facets(): array {
		return array(
			'location' => self::TAX_LOCATION,
			'service'  => self::TAX_SERVICE,
			'issue'    => self::TAX_ISSUE,
			'language' => self::TAX_LANGUAGE,
		);
	}

	public static function post_type(): string {
		$post_type = sanitize_key( (string) Settings::get( 'post_type' ) );
		$post_type = $post_type ? $post_type : 'associates';

		// A theme may register e.g. `associate` with the URL slug `associates`. Use that rather than
		// creating a second post type whose URLs would clash with the real profile pages.
		if ( did_action( 'init' ) && ! post_type_exists( $post_type ) ) {
			foreach ( get_post_types( array(), 'objects' ) as $object ) {
				if ( is_array( $object->rewrite ) && ( $object->rewrite['slug'] ?? '' ) === $post_type ) {
					$post_type = $object->name;
					break;
				}
			}
		}

		return (string) apply_filters( 'ltf_post_type', $post_type );
	}

	public static function activate(): void {
		self::register();
		flush_rewrite_rules();
	}

	public static function register(): void {
		$post_type = self::post_type();

		if ( ! post_type_exists( $post_type ) ) {
			register_post_type(
				$post_type,
				array(
					'labels'        => array(
						'name'          => __( 'Therapists', 'leone-therapist-finder' ),
						'singular_name' => __( 'Therapist', 'leone-therapist-finder' ),
						'add_new_item'  => __( 'Add New Therapist', 'leone-therapist-finder' ),
						'edit_item'     => __( 'Edit Therapist', 'leone-therapist-finder' ),
						'new_item'      => __( 'New Therapist', 'leone-therapist-finder' ),
						'view_item'     => __( 'View Therapist', 'leone-therapist-finder' ),
						'search_items'  => __( 'Search Therapists', 'leone-therapist-finder' ),
						'not_found'     => __( 'No therapists found.', 'leone-therapist-finder' ),
						'all_items'     => __( 'All Therapists', 'leone-therapist-finder' ),
					),
					'public'        => true,
					'show_in_rest'  => true,
					'menu_icon'     => 'dashicons-groups',
					'menu_position' => 20,
					'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
					'rewrite'       => array(
						'slug'       => $post_type,
						'with_front' => false,
					),
				)
			);
		}

		// Admin-only taxonomies: the finder is the public face, so no term archives or query vars
		// (which could also clash with the finder's URL parameters).
		$common = array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_admin_column'  => false,
			'query_var'          => false,
			'rewrite'            => false,
			'hierarchical'       => true, // Checkbox UI: curated lists, no free-typed tags.
		);

		register_taxonomy(
			self::TAX_LOCATION,
			$post_type,
			array_merge(
				$common,
				array(
					'labels'       => self::labels( __( 'Location', 'leone-therapist-finder' ), __( 'Locations', 'leone-therapist-finder' ) ),
					'description'  => __( 'Clinics and online. Assigned from the "Therapist finder" box on each therapist, next to their Acuity booking link.', 'leone-therapist-finder' ),
					'show_in_rest' => false,
					'meta_box_cb'  => false,
				)
			)
		);

		register_taxonomy(
			self::TAX_SERVICE,
			$post_type,
			array_merge(
				$common,
				array(
					'labels'       => self::labels( __( 'Therapy type', 'leone-therapist-finder' ), __( 'Therapy types', 'leone-therapist-finder' ) ),
					'show_in_rest' => true,
				)
			)
		);

		register_taxonomy(
			self::TAX_ISSUE,
			$post_type,
			array_merge(
				$common,
				array(
					'labels'       => self::labels( __( 'Issue', 'leone-therapist-finder' ), __( 'Issues', 'leone-therapist-finder' ), __( 'Group', 'leone-therapist-finder' ) ),
					'description'  => __( 'Top-level issues act as groups in the filter; tag therapists with the specific issues underneath.', 'leone-therapist-finder' ),
					'show_in_rest' => true,
				)
			)
		);

		register_taxonomy(
			self::TAX_LANGUAGE,
			$post_type,
			array_merge(
				$common,
				array(
					'labels'       => self::labels( __( 'Language', 'leone-therapist-finder' ), __( 'Languages', 'leone-therapist-finder' ) ),
					'show_in_rest' => true,
				)
			)
		);

		$auth = array( __CLASS__, 'can_edit_meta' );

		register_post_meta(
			$post_type,
			self::META_ROLE,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			$post_type,
			self::META_SUMMARY,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			$post_type,
			self::META_YEARS,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			$post_type,
			self::META_ACCREDITATIONS,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			$post_type,
			self::META_HIDE,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			$post_type,
			self::META_CALENDARS,
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_calendars' ),
				'auth_callback'     => $auth,
			)
		);

		register_term_meta(
			self::TAX_SERVICE,
			self::TERM_META_APPOINTMENT_TYPE,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_appointment_type' ),
			)
		);
	}

	/**
	 * @param bool   $allowed   Unused.
	 * @param string $meta_key  Unused.
	 * @param int    $object_id Post ID.
	 */
	public static function can_edit_meta( $allowed, $meta_key, $object_id ): bool {
		return current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Location term ID => Acuity calendar ID or booking URL.
	 */
	public static function get_calendars( int $post_id ): array {
		$calendars = get_post_meta( $post_id, self::META_CALENDARS, true );

		return is_array( $calendars ) ? $calendars : array();
	}

	/**
	 * @param mixed $value Raw meta value.
	 */
	public static function sanitize_calendars( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $term_id => $ref ) {
			$ref     = self::sanitize_booking_ref( (string) $ref );
			$term_id = absint( $term_id );
			if ( $term_id && '' !== $ref ) {
				$clean[ $term_id ] = $ref;
			}
		}

		return $clean;
	}

	/**
	 * A booking reference is an Acuity calendar ID (digits) or a full booking URL.
	 */
	public static function sanitize_booking_ref( string $ref ): string {
		$ref = trim( $ref );

		if ( '' === $ref ) {
			return '';
		}

		if ( ctype_digit( $ref ) ) {
			return $ref;
		}

		$url = esc_url_raw( $ref, array( 'https', 'http' ) );

		return ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) ? $url : '';
	}

	/**
	 * Acuity appointment type: an ID, or `category:Name` to show a whole category.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_appointment_type( $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );

		return preg_match( '/^(\d+|category:[^<>"]{1,100})$/', $value ) ? $value : '';
	}

	/**
	 * Location terms in admin creation order (so "Online" can lead), not alphabetical.
	 *
	 * @return \WP_Term[]
	 */
	public static function location_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAX_LOCATION,
				'hide_empty' => false,
				'orderby'    => 'term_id',
			)
		);

		return is_array( $terms ) ? $terms : array();
	}

	public static function is_online_location( \WP_Term $term ): bool {
		return (bool) apply_filters( 'ltf_is_online_location', false !== strpos( $term->slug, 'online' ), $term );
	}

	private static function labels( string $singular, string $plural, string $parent = '' ): array {
		return array(
			'name'              => $plural,
			'singular_name'     => $singular,
			'menu_name'         => $plural,
			/* translators: %s: plural taxonomy name. */
			'search_items'      => sprintf( __( 'Search %s', 'leone-therapist-finder' ), $plural ),
			/* translators: %s: plural taxonomy name. */
			'all_items'         => sprintf( __( 'All %s', 'leone-therapist-finder' ), $plural ),
			/* translators: %s: singular taxonomy name. */
			'edit_item'         => sprintf( __( 'Edit %s', 'leone-therapist-finder' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'update_item'       => sprintf( __( 'Update %s', 'leone-therapist-finder' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'add_new_item'      => sprintf( __( 'Add New %s', 'leone-therapist-finder' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'new_item_name'     => sprintf( __( 'New %s Name', 'leone-therapist-finder' ), $singular ),
			'parent_item'       => $parent ? $parent : __( 'Parent', 'leone-therapist-finder' ),
			'parent_item_colon' => ( $parent ? $parent : __( 'Parent', 'leone-therapist-finder' ) ) . ':',
			/* translators: %s: plural taxonomy name. */
			'not_found'         => sprintf( __( 'No %s found.', 'leone-therapist-finder' ), strtolower( $plural ) ),
			/* translators: %s: plural taxonomy name. */
			'back_to_items'     => sprintf( __( '&larr; Back to %s', 'leone-therapist-finder' ), $plural ),
		);
	}
}
