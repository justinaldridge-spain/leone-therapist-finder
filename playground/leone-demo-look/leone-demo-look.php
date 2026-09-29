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

		if ( is_user_logged_in() || ! $local || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// Authenticate this very request as well as sending the cookie: the auth cookie WordPress
		// just issued is not in $_COOKIE yet, and redirecting instead would break Blueprint steps.
		$adopt = static function ( $cookie, $name ) {
			$_COOKIE[ $name ] = $cookie;
		};
		add_action(
			'set_auth_cookie',
			static function ( $cookie, $expire, $expiration, $user_id, $scheme ) use ( $adopt ) {
				$adopt( $cookie, 'secure_auth' === $scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE );
			},
			10,
			5
		);
		add_action(
			'set_logged_in_cookie',
			static function ( $cookie ) use ( $adopt ) {
				$adopt( $cookie, LOGGED_IN_COOKIE );
			}
		);

		wp_set_auth_cookie( 1, true );
		wp_set_current_user( 1 );
	},
	1
);

require_once __DIR__ . '/live-theme-preview.php';

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
