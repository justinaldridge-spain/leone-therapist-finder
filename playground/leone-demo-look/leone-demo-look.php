<?php
/**
 * Plugin Name: Leone demo look (Playground only)
 * Description: Loads the fonts leonecentre.com uses (Work Sans + Raleway) so the prototype previews
 *              roughly as it will on the live site, and keeps you logged in as admin on the local
 *              demo. Not part of the plugin – never install this on a real site.
 */

/*
 * Local demo auto-login. Playground builds a fresh site on every start, so login cookies left in
 * the browser from a previous run are rejected. This logs the visitor in as the demo admin
 * (user 1) whenever they are logged out – but only for requests from this machine, and only when
 * the local start command defines LEONE_DEMO_AUTOLOGIN (so a copy of this file elsewhere is inert).
 */
add_action(
	'init',
	static function () {
		if ( ! defined( 'LEONE_DEMO_AUTOLOGIN' ) || ! LEONE_DEMO_AUTOLOGIN ) {
			return;
		}

		$remote = $_SERVER['REMOTE_ADDR'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$local  = in_array( $remote, array( '127.0.0.1', '::1', '' ), true );

		if ( is_user_logged_in() || ! $local || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || isset( $_GET['ltf_demo_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		wp_set_auth_cookie( 1, true );

		// On the login screen go to where the visitor was heading; elsewhere reload the same page.
		$target = $GLOBALS['pagenow'] === 'wp-login.php'
			? ( isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : admin_url() ) // phpcs:ignore
			: ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore

		// The flag stops a redirect loop if the browser refuses the cookie.
		wp_safe_redirect( add_query_arg( 'ltf_demo_login', '1', $target ) );
		exit;
	},
	1
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'leone-demo-fonts', 'https://fonts.googleapis.com/css2?family=Raleway:wght@500;600;700&family=Work+Sans:wght@400;500;600&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_add_inline_style(
			'leone-demo-fonts',
			'body{font-family:"Work Sans",sans-serif}h1,h2,h3,h4,.wp-block-site-title,.ltf-card__name{font-family:Raleway,sans-serif}'
		);
	}
);
