<?php
/**
 * The [therapist_finder] shortcode.
 *
 * Renders every matching therapist server-side (good for SEO and works without JavaScript, via a
 * plain GET form), then assets/js/finder.js filters the cards instantly in the browser.
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode, data and rendering.
 */
final class Finder {

	const SHORTCODE = 'therapist_finder';

	/** Prefix for URL parameters, e.g. ?tf_location=fulham&tf_issue=anxiety. */
	const PARAM = 'tf_';

	/** Filter controls a finder can show, in display order. */
	const CONTROLS = array( 'location', 'service', 'issue', 'language', 'search' );

	/** @var int */
	private static $instances = 0;

	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_style( 'ltf-finder', plugins_url( 'assets/css/finder.css', PLUGIN_FILE ), array(), asset_version( 'assets/css/finder.css' ) );
		wp_register_script(
			'ltf-finder',
			plugins_url( 'assets/js/finder.js', PLUGIN_FILE ),
			array(),
			asset_version( 'assets/js/finder.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Load the stylesheet in <head> when we can tell the page uses the finder (avoids a flash of
		// unstyled content). The shortcode callback enqueues it too, for widgets and page builders.
		if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), self::SHORTCODE ) ) {
			wp_enqueue_style( 'ltf-finder' );
		}
	}

	/**
	 * [therapist_finder] handler.
	 *
	 * Attributes:
	 * - service, location, issue, language: comma-separated term slugs. Results are fixed to them
	 *   (e.g. service="couples-counselling" on the Couples page) and that filter is not shown –
	 *   unless the facet is explicitly listed in `filters`, in which case the value is only the
	 *   starting selection and visitors can change it.
	 * - filters: which controls to show. Default: all of location,issue,service,language,search
	 *   except any facet fixed above.
	 * - order:   default (menu order) | name | random (random is shuffled in the browser, so it
	 *            survives page caching and spreads enquiries across the team).
	 * - title:   optional heading. help: yes|no. url: yes|no (keep filters in the address bar).
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public static function render_shortcode( $atts ): string {
		$explicit_filters = is_array( $atts ) && isset( $atts['filters'] );

		$atts = shortcode_atts(
			array(
				'location' => '',
				'service'  => '',
				'issue'    => '',
				'language' => '',
				'filters'  => implode( ',', self::CONTROLS ),
				'order'    => 'default',
				'title'    => '',
				'help'     => 'yes',
				'url'      => 'yes',
			),
			$atts,
			self::SHORTCODE
		);

		++self::$instances;
		wp_enqueue_style( 'ltf-finder' );
		wp_enqueue_script( 'ltf-finder' );

		$settings = Settings::all();
		$controls = array_values( array_intersect( self::CONTROLS, self::csv( $atts['filters'] ) ) );
		$locked   = array();
		$defaults = array();

		foreach ( array_keys( Content_Model::facets() ) as $facet ) {
			$slugs = array_values( array_filter( array_map( 'sanitize_title', self::csv( $atts[ $facet ] ) ) ) );
			if ( ! $slugs ) {
				continue;
			}
			if ( $explicit_filters && in_array( $facet, $controls, true ) ) {
				$defaults[ $facet ] = $slugs[0];
			} else {
				$locked[ $facet ] = $slugs;
			}
		}
		$controls = array_values( array_diff( $controls, array_keys( $locked ) ) );

		$order      = in_array( $atts['order'], array( 'default', 'name', 'random' ), true ) ? $atts['order'] : 'default';
		$therapists = self::query( $locked, $order );

		// Only offer choices that at least one therapist in this finder matches, and drop filters
		// that would have a single choice (e.g. Language when everyone speaks English).
		$options = array();
		foreach ( Content_Model::facets() as $facet => $taxonomy ) {
			if ( in_array( $facet, $controls, true ) ) {
				$groups = self::options( $facet, $taxonomy, $therapists );
				if ( count( self::option_slugs( $groups ) ) > 1 ) {
					$options[ $facet ] = $groups;
				}
			}
		}

		// Initial state: URL parameter (if this finder owns the URL) > shortcode default > none.
		$url_sync = 'no' !== $atts['url'] && 1 === self::$instances;
		$state    = array();
		foreach ( $options as $facet => $groups ) {
			$value           = $url_sync ? self::request_param( self::PARAM . $facet ) : null;
			$value           = null === $value ? ( $defaults[ $facet ] ?? '' ) : sanitize_title( $value );
			$state[ $facet ] = in_array( $value, self::option_slugs( $groups ), true ) ? $value : '';
		}
		$has_search = in_array( 'search', $controls, true );
		$query      = ( $has_search && $url_sync ) ? (string) self::request_param( self::PARAM . 'q' ) : '';

		$visible = 0;
		foreach ( $therapists as &$therapist ) {
			$therapist['visible'] = self::matches( $therapist, $state, $query );
			$visible             += $therapist['visible'] ? 1 : 0;
		}
		unset( $therapist );

		$locked_location = isset( $locked['location'] ) && 1 === count( $locked['location'] ) ? $locked['location'][0] : '';
		$locked_service  = isset( $locked['service'] ) && 1 === count( $locked['service'] ) ? $locked['service'][0] : '';
		$types           = Booking::appointment_types();
		$active_service  = ! empty( $state['service'] ) ? $state['service'] : $locked_service;

		$context = array(
			'uid'              => 'ltf-' . self::$instances,
			'title'            => $atts['title'],
			'show_help'        => 'no' !== $atts['help'],
			'settings'         => $settings,
			'therapists'       => $therapists,
			'options'          => $options,
			'state'            => $state,
			'query'            => $query,
			'has_search'       => $has_search,
			'total'            => count( $therapists ),
			'visible'          => $visible,
			'labels'           => self::labels(),
			'preserve'         => self::preserved_params(),
			'reset_url'        => remove_query_arg( self::param_names() ),
			'active_location'  => ! empty( $state['location'] ) ? $state['location'] : $locked_location,
			'appointment_type' => $types[ $active_service ] ?? '',
			'accent'           => self::accent_vars( (string) $settings['accent'] ),
			'js_config'        => array(
				'param'            => self::PARAM,
				'urlSync'          => $url_sync,
				'order'            => $order,
				'defaults'         => $defaults,
				'lockedLocation'   => $locked_location,
				'lockedService'    => $locked_service,
				'appointmentTypes' => (object) $types,
				'issueLimit'       => (int) $settings['issue_limit'],
				'contactUrl'       => $settings['contact_url'],
				'i18n'             => self::js_strings(),
			),
		);

		return self::template( 'finder', array( 'ctx' => $context ) );
	}

	/**
	 * Card markup for one therapist (called from the finder template).
	 */
	public static function render_card( array $therapist, array $ctx ): string {
		return self::template(
			'card',
			array(
				't'   => $therapist,
				'ctx' => $ctx,
			)
		);
	}

	/**
	 * Book button(s). Mirrors renderBooking() in finder.js – keep the two in step.
	 */
	public static function booking_html( array $therapist, string $location, string $appointment_type, string $contact_url ): string {
		$links = $therapist['booking'];

		if ( '' !== $location && isset( $links[ $location ] ) ) {
			$links = array( $location => $links[ $location ] );
		}

		if ( ! $links ) {
			return sprintf(
				'<a class="ltf-btn ltf-btn--primary" href="%s">%s</a>',
				esc_url( $contact_url ),
				esc_html__( 'Enquire', 'leone-therapist-finder' )
			);
		}

		if ( 1 === count( $links ) ) {
			$slug = (string) key( $links );
			$link = current( $links );
			/* translators: %s: clinic name, e.g. Fulham. */
			$label = $link['online'] ? __( 'Book online', 'leone-therapist-finder' ) : sprintf( __( 'Book in %s', 'leone-therapist-finder' ), $link['label'] );

			return sprintf(
				'<a class="ltf-btn ltf-btn--primary" href="%s" data-ltf-book-link="%s">%s</a>',
				esc_url( Booking::with_appointment_type( $link['url'], $appointment_type ) ),
				esc_attr( $slug ),
				esc_html( $label )
			);
		}

		$items = '';
		foreach ( $links as $slug => $link ) {
			/* translators: %s: clinic name, e.g. Fulham. */
			$label  = $link['online'] ? __( 'Online session', 'leone-therapist-finder' ) : sprintf( __( '%s clinic', 'leone-therapist-finder' ), $link['label'] );
			$items .= sprintf(
				'<li><a class="ltf-book-menu__link" href="%s" data-ltf-book-link="%s">%s<span>%s</span></a></li>',
				esc_url( Booking::with_appointment_type( $link['url'], $appointment_type ) ),
				esc_attr( $slug ),
				self::icon( $link['online'] ? 'video' : 'pin' ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<details class="ltf-book-menu"><summary class="ltf-btn ltf-btn--primary">%s</summary><ul class="ltf-book-menu__list">%s</ul></details>',
			esc_html__( 'Book a session', 'leone-therapist-finder' ),
			$items
		);
	}

	public static function status_text( int $visible, int $total ): string {
		if ( $visible === $total ) {
			/* translators: %d: number of therapists. */
			return sprintf( _n( 'Showing %d therapist', 'Showing all %d therapists', $total, 'leone-therapist-finder' ), $total );
		}

		/* translators: 1: number shown, 2: total number of therapists. */
		return sprintf( __( 'Showing %1$d of %2$d therapists', 'leone-therapist-finder' ), $visible, $total );
	}

	/**
	 * Small inline SVG icons (decorative).
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'pin'   => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10.5 5-3v9l-5-3"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="ltf-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Fetch therapists, restricted to any fixed (locked) facets.
	 *
	 * @param array  $locked Facet => term slugs.
	 * @param string $order  default|name|random.
	 */
	private static function query( array $locked, string $order ): array {
		$args = array(
			'post_type'           => Content_Model::post_type(),
			'post_status'         => 'publish',
			'posts_per_page'      => 200,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'orderby'             => 'name' === $order ? 'title' : array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'order'               => 'ASC',
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, bounded set.
				array(
					'key'     => Content_Model::META_HIDE,
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$facets = Content_Model::facets();
		foreach ( $locked as $facet => $slugs ) {
			$args['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'taxonomy' => $facets[ $facet ],
				'field'    => 'slug',
				'terms'    => $slugs,
			);
		}

		$query          = new \WP_Query( apply_filters( 'ltf_query_args', $args, $locked ) );
		$location_terms = Content_Model::location_terms();
		$therapists     = array();

		update_post_thumbnail_cache( $query );

		foreach ( $query->posts as $post ) {
			$therapists[] = self::therapist( $post, $location_terms );
		}

		return apply_filters( 'ltf_therapists', $therapists, $locked );
	}

	/**
	 * Everything a card and the filters need about one therapist.
	 *
	 * @param \WP_Post   $post           Therapist post.
	 * @param \WP_Term[] $location_terms All location terms, in display order.
	 */
	private static function therapist( \WP_Post $post, array $location_terms ): array {
		$facets = array();
		foreach ( Content_Model::facets() as $facet => $taxonomy ) {
			$facets[ $facet ] = array();
			$terms            = get_the_terms( $post, $taxonomy );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$facets[ $facet ][ $term->slug ] = $term->name;
				}
			}
		}

		// Locations follow the admin-defined order (Online first), each with its Acuity link.
		$calendars = Content_Model::get_calendars( $post->ID );
		$locations = array();
		$booking   = array();
		foreach ( $location_terms as $term ) {
			if ( ! isset( $facets['location'][ $term->slug ] ) ) {
				continue;
			}
			$locations[ $term->slug ] = $term->name;
			$url                      = isset( $calendars[ $term->term_id ] ) ? Booking::url( (string) $calendars[ $term->term_id ] ) : '';
			if ( '' !== $url ) {
				$booking[ $term->slug ] = array(
					'label'  => $term->name,
					'url'    => $url,
					'online' => Content_Model::is_online_location( $term ),
				);
			}
		}
		$facets['location'] = $locations;

		$role           = (string) get_post_meta( $post->ID, Content_Model::META_ROLE, true );
		$accreditations = array_values( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, Content_Model::META_ACCREDITATIONS, true ) ) ) ) );
		$summary        = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$summary        = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $summary ) ), 34 );

		$haystack = array( $post->post_title, $role, $summary, implode( ' ', $accreditations ) );
		foreach ( $facets as $names ) {
			$haystack[] = implode( ' ', $names );
		}

		$data = array(
			'id'             => $post->ID,
			'name'           => $post->post_title,
			'url'            => get_permalink( $post ),
			'role'           => $role,
			'years'          => (int) get_post_meta( $post->ID, Content_Model::META_YEARS, true ),
			'accreditations' => $accreditations,
			'summary'        => $summary,
			'photo'          => self::photo_html( $post ),
			'facets'         => $facets,
			'booking'        => $booking,
			'search'         => self::normalise( implode( ' ', $haystack ) ),
		);

		return apply_filters( 'ltf_therapist_data', $data, $post );
	}

	private static function photo_html( \WP_Post $post ): string {
		$html = '';

		if ( has_post_thumbnail( $post ) ) {
			// Decorative: the therapist's name sits right next to it.
			$html = get_the_post_thumbnail(
				$post,
				'medium',
				array(
					'class'   => 'ltf-card__photo',
					'alt'     => '',
					'loading' => 'lazy',
					'sizes'   => '96px',
				)
			);
		}

		if ( '' === $html ) {
			$words    = preg_split( '/\s+/', trim( $post->post_title ) );
			$initials = '';
			foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
			$html = '<span class="ltf-card__initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
		}

		return (string) apply_filters( 'ltf_therapist_photo_html', $html, $post );
	}

	/**
	 * Choices for one filter, grouped by parent term (issues use parents as <optgroup>s).
	 *
	 * @return array[] List of [ 'label' => group name or '', 'options' => slug => name ].
	 */
	private static function options( string $facet, string $taxonomy, array $therapists ): array {
		$used = array();
		foreach ( $therapists as $therapist ) {
			foreach ( array_keys( $therapist['facets'][ $facet ] ) as $slug ) {
				$used[ $slug ] = true;
			}
		}

		if ( ! $used ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'location' === $facet ? 'term_id' : 'name',
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$by_id = array();
		foreach ( $terms as $term ) {
			$by_id[ $term->term_id ] = $term;
		}

		$groups = array();
		foreach ( $terms as $term ) {
			if ( ! isset( $used[ $term->slug ] ) ) {
				continue;
			}
			$parent = ( $term->parent && isset( $by_id[ $term->parent ] ) ) ? $by_id[ $term->parent ] : null;
			$key    = $parent ? $parent->term_id : 0;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'label'   => $parent ? $parent->name : '',
					'options' => array(),
				);
			}
			$groups[ $key ]['options'][ $term->slug ] = $term->name;
		}

		ksort( $groups ); // Ungrouped first, then groups in the order they were created.

		return array_values( $groups );
	}

	private static function option_slugs( array $groups ): array {
		$slugs = array();
		foreach ( $groups as $group ) {
			$slugs = array_merge( $slugs, array_map( 'strval', array_keys( $group['options'] ) ) );
		}

		return $slugs;
	}

	/**
	 * Mirrors matches() in finder.js.
	 */
	private static function matches( array $therapist, array $state, string $query ): bool {
		foreach ( $state as $facet => $value ) {
			if ( '' !== $value && ! isset( $therapist['facets'][ $facet ][ $value ] ) ) {
				return false;
			}
		}

		foreach ( preg_split( '/\s+/', self::normalise( $query ), -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
			if ( false === strpos( $therapist['search'], $token ) ) {
				return false;
			}
		}

		return true;
	}

	private static function normalise( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', mb_strtolower( remove_accents( wp_strip_all_tags( $text ) ) ) ) );
	}

	private static function labels(): array {
		return array(
			'location' => __( 'Where would you like to meet?', 'leone-therapist-finder' ),
			'service'  => __( 'What would you like help with?', 'leone-therapist-finder' ),
			'issue'    => __( 'Anything more specific?', 'leone-therapist-finder' ),
			'language' => __( 'Language', 'leone-therapist-finder' ),
			'search'   => __( 'Search by name or keyword', 'leone-therapist-finder' ),
			'any'      => array(
				'location' => __( 'Anywhere', 'leone-therapist-finder' ),
				'service'  => __( 'Any type of therapy', 'leone-therapist-finder' ),
				'issue'    => __( 'Any issue', 'leone-therapist-finder' ),
				'language' => __( 'Any language', 'leone-therapist-finder' ),
			),
		);
	}

	private static function js_strings(): array {
		return array(
			/* translators: %d: number of therapists. */
			'showingAll'   => __( 'Showing all %d therapists', 'leone-therapist-finder' ),
			/* translators: 1: number shown, 2: total. */
			'showingSome'  => __( 'Showing %1$d of %2$d therapists', 'leone-therapist-finder' ),
			'bookOnline'   => __( 'Book online', 'leone-therapist-finder' ),
			/* translators: %s: clinic name. */
			'bookIn'       => __( 'Book in %s', 'leone-therapist-finder' ),
			'bookMenu'     => __( 'Book a session', 'leone-therapist-finder' ),
			'menuOnline'   => __( 'Online session', 'leone-therapist-finder' ),
			/* translators: %s: clinic name. */
			'menuClinic'   => __( '%s clinic', 'leone-therapist-finder' ),
			'enquire'      => __( 'Enquire', 'leone-therapist-finder' ),
			/* translators: %d: number of hidden issues. */
			'moreIssues'   => __( '+%d more', 'leone-therapist-finder' ),
			'fewerIssues'  => __( 'Show fewer', 'leone-therapist-finder' ),
			/* translators: 1: filter value, 2: number of therapists if removed. */
			'removeFilter' => __( 'Remove “%1$s” (%2$d)', 'leone-therapist-finder' ),
			/* translators: 1: search text, 2: number of therapists if cleared. */
			'clearSearch'  => __( 'Clear search “%1$s” (%2$d)', 'leone-therapist-finder' ),
		);
	}

	/**
	 * Accent colour plus a darker shade (for text and buttons with white text, to keep WCAG AA
	 * contrast — the Leone teal on its own is too light for white text) and a pale tint.
	 */
	private static function accent_vars( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$rgb = array_map( 'hexdec', str_split( $hex, 2 ) );

		$mix = static function ( float $amount, int $target ) use ( $rgb ): string {
			$out = '#';
			foreach ( $rgb as $channel ) {
				$out .= str_pad( dechex( (int) round( $channel + ( $target - $channel ) * $amount ) ), 2, '0', STR_PAD_LEFT );
			}
			return $out;
		};

		return sprintf( '--ltf-accent:#%s;--ltf-accent-strong:%s;--ltf-accent-soft:%s;', $hex, $mix( 0.32, 0 ), $mix( 0.88, 255 ) );
	}

	/**
	 * Other query args on the page, carried through the no-JS filter form.
	 */
	private static function preserved_params(): array {
		$keep = array();
		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
			$key = sanitize_key( (string) $key );
			if ( '' === $key || 0 === strpos( $key, self::PARAM ) || ! is_scalar( $value ) ) {
				continue;
			}
			$keep[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
		}

		return $keep;
	}

	private static function param_names(): array {
		$names = array();
		foreach ( array_merge( array_keys( Content_Model::facets() ), array( 'q' ) ) as $name ) {
			$names[] = self::PARAM . $name;
		}

		return $names;
	}

	/**
	 * @return string|null Null when the parameter is absent (so an empty value can mean "any").
	 */
	private static function request_param( string $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filter.
		if ( ! isset( $_GET[ $name ] ) || ! is_scalar( $_GET[ $name ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) );
	}

	private static function csv( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', strtolower( $value ) ) ) ) );
	}

	private static function template( string $name, array $vars ): string {
		$file = locate_template( 'leone-therapist-finder/' . $name . '.php' );
		if ( '' === $file ) {
			$file = PLUGIN_DIR . 'templates/' . $name . '.php';
		}

		ob_start();
		( static function ( string $__file, array $__vars ) {
			extract( $__vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template scope.
			include $__file;
		} )( $file, $vars );

		return (string) ob_get_clean();
	}
}
