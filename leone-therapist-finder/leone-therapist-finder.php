<?php
/**
 * Plugin Name:       Leone Therapist Finder
 * Description:       Filterable therapist directory with Acuity Scheduling booking links. Add it to any page with the [therapist_finder] shortcode.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leone-therapist-finder
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__ . '/';

/**
 * Asset version: the plugin version, or the file's modified time while debugging (cache-busting).
 */
function asset_version( string $relative_path ): string {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( PLUGIN_DIR . $relative_path ) ) {
		return (string) filemtime( PLUGIN_DIR . $relative_path );
	}

	return VERSION;
}

require_once PLUGIN_DIR . 'includes/class-settings.php';
require_once PLUGIN_DIR . 'includes/class-content-model.php';
require_once PLUGIN_DIR . 'includes/class-booking.php';
require_once PLUGIN_DIR . 'includes/class-finder.php';

if ( is_admin() ) {
	require_once PLUGIN_DIR . 'includes/class-admin.php';
}

register_activation_hook( __FILE__, array( Content_Model::class, 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action(
	'plugins_loaded',
	static function () {
		Content_Model::init();
		Finder::init();

		if ( is_admin() ) {
			Admin::init();
		}
	}
);

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'leone-therapist-finder', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
);
