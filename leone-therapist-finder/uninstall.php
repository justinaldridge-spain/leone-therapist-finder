<?php
/**
 * Uninstall: removes plugin settings.
 *
 * Therapist tagging (locations, therapy types, issues, languages) and Acuity calendar IDs are
 * site content that took effort to enter, so they are kept unless the site owner opts in by
 * adding `define( 'LTF_REMOVE_ALL_DATA', true );` to wp-config.php before deleting the plugin.
 *
 * @package LeoneCentre\TherapistFinder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ltf_settings' );

if ( ! defined( 'LTF_REMOVE_ALL_DATA' ) || ! LTF_REMOVE_ALL_DATA ) {
	return;
}

foreach ( array( '_ltf_role', '_ltf_years', '_ltf_accreditations', '_ltf_calendars', '_ltf_hide' ) as $ltf_key ) {
	delete_post_meta_by_key( $ltf_key );
}

foreach ( array( 'ltf_location', 'ltf_service', 'ltf_issue', 'ltf_language' ) as $ltf_taxonomy ) {
	register_taxonomy( $ltf_taxonomy, array() ); // Needed for get_terms() / wp_delete_term() during uninstall.
	$ltf_terms = get_terms(
		array(
			'taxonomy'   => $ltf_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	foreach ( is_array( $ltf_terms ) ? $ltf_terms : array() as $ltf_term_id ) {
		wp_delete_term( (int) $ltf_term_id, $ltf_taxonomy );
	}
}
