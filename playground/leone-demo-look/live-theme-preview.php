<?php
/**
 * Playground-only: reproduce the live site's styling context.
 *
 * Add ?livecss=1 to any demo page to load leonecentre.com's real theme stylesheets and wrap the
 * content in the same markup their pages use, so theme CSS bleeding into the finder can be
 * reproduced and fixed locally. Never part of the plugin.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the live-CSS preview switched on for this request?
 */
function leone_demo_live_css(): bool {
	return isset( $_GET['livecss'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! leone_demo_live_css() ) {
			return;
		}

		$base = 'https://www.leonecentre.com/wp-content/';
		wp_enqueue_style( 'leone-live-theme', $base . 'themes/leonecentre/dist/css/leonecentre.com-20200325022605.css', array(), '1710412426' );
		wp_enqueue_style( 'leone-live-custom', $base . 'themes/leonecentre/dist/css/custom.css', array( 'leone-live-theme' ), '1677140599' );
		wp_enqueue_style( 'leone-live-glossary', $base . 'plugins/glossary-by-codeat-premium/assets/css/css-pro/tooltip-material.css', array(), '2.3.11' );

		// The site's own custom CSS for content lists, copied from the live page.
		wp_add_inline_style(
			'leone-live-custom',
			'.entry-content ul { background-color: #f5f5f5; padding: 25px; list-style-type: square; border: 1px solid #2cb2b2; column-count: 2 !important; }
			 .entry-content ul li { margin-left: 5px; }'
		);
	},
	5
);

// ?ltfwidth=wide|full|1100 applies that width to the finder without editing a page.
add_filter(
	'shortcode_atts_therapist_finder',
	static function ( $out ) {
		if ( isset( $_GET['ltfwidth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out['width'] = sanitize_text_field( wp_unslash( $_GET['ltfwidth'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return $out;
	}
);

// The live pages render content inside this wrapper, and much of the theme CSS is scoped to it.
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! leone_demo_live_css() || is_admin() ) {
			return $content;
		}

		return '<div class="module text text-default text-primary-no-container mar-top-xl cms sa-cf"><div class="container container-md"><div class="row"><div class="col-md-8 col-sm-8"><div class="entry-content">' . $content . '</div></div></div></div></div>';
	},
	20
);
