<?php
/**
 * Seeds the Playground demo with the Leone Centre team.
 *
 * Data in data/therapists.json was scraped from leonecentre.com (team page + profiles):
 * - locations and Acuity calendar IDs come from each therapist's "Book … Appointment" links;
 * - therapy types from each card's "can help with" links and role title;
 * - issues were keyword-matched from the bios, so they are a starting point for the Leone team
 *   to review, not a final tagging.
 *
 * Runs inside WordPress from the blueprint. Safe to re-run (skips if already seeded).
 *
 * @package LeoneCentre\TherapistFinder
 */

use LeoneCentre\TherapistFinder\Content_Model;

if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

if ( ! class_exists( Content_Model::class ) ) {
	echo "Therapist Finder plugin is not active.\n";
	return;
}

if ( get_option( 'ltf_demo_seeded' ) ) {
	echo "Demo already seeded.\n";
	return;
}

wp_set_current_user( 1 );

// With LTF_SEED_NO_TAGS the profiles are created untagged, like the real site before an import.
// Used to test the plugin's "Import tags" screen.
$ltf_no_tags = defined( 'LTF_SEED_NO_TAGS' ) && LTF_SEED_NO_TAGS;

$ltf_dir  = __DIR__;
$ltf_data = json_decode( (string) file_get_contents( $ltf_dir . '/data/therapists.json' ), true );

update_option( 'blogname', 'Leone Centre' );
update_option( 'blogdescription', 'Therapist finder prototype' );
update_option( 'permalink_structure', '/%postname%/' );

$ltf_term = static function ( string $taxonomy, string $slug, string $name, array $args = array() ): int {
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing ) {
		return (int) $existing->term_id;
	}
	$result = wp_insert_term( $name, $taxonomy, array_merge( array( 'slug' => $slug ), $args ) );
	if ( is_wp_error( $result ) ) {
		echo 'Term error (' . esc_html( $slug ) . '): ' . esc_html( $result->get_error_message() ) . "\n";
		return 0;
	}
	return (int) $result['term_id'];
};

// Terms.
$ltf_locations = array();
foreach ( $ltf_no_tags ? array() : $ltf_data['locations'] as $ltf_loc ) {
	$ltf_locations[ $ltf_loc['slug'] ] = $ltf_term( Content_Model::TAX_LOCATION, $ltf_loc['slug'], $ltf_loc['name'], array( 'description' => $ltf_loc['description'] ) );
}
foreach ( $ltf_no_tags ? array() : $ltf_data['services'] as $ltf_service ) {
	$ltf_term( Content_Model::TAX_SERVICE, $ltf_service['slug'], $ltf_service['name'] );
}
foreach ( $ltf_no_tags ? array() : $ltf_data['issues'] as $ltf_group ) {
	$ltf_parent = $ltf_term( Content_Model::TAX_ISSUE, $ltf_group['slug'], $ltf_group['name'] );
	foreach ( $ltf_group['children'] as $ltf_child ) {
		$ltf_term( Content_Model::TAX_ISSUE, $ltf_child['slug'], $ltf_child['name'], array( 'parent' => $ltf_parent ) );
	}
}
foreach ( $ltf_no_tags ? array() : $ltf_data['languages'] as $ltf_lang ) {
	$ltf_term( Content_Model::TAX_LANGUAGE, $ltf_lang['slug'], $ltf_lang['name'] );
}

// Therapists.
$ltf_post_type = Content_Model::post_type();
foreach ( $ltf_data['therapists'] as $ltf_t ) {
	$ltf_blocks = array();
	foreach ( $ltf_t['content'] as $ltf_para ) {
		$ltf_blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $ltf_para ) . "</p>\n<!-- /wp:paragraph -->";
	}

	$ltf_id = wp_insert_post(
		array(
			'post_type'    => $ltf_post_type,
			'post_status'  => 'publish',
			'post_title'   => $ltf_t['name'],
			'post_name'    => $ltf_t['slug'],
			'post_content' => implode( "\n\n", $ltf_blocks ),
			'post_excerpt' => $ltf_t['excerpt'],
			// Untagged mode mimics the live site, where every profile's order is 0.
			'menu_order'   => $ltf_no_tags ? 0 : (int) $ltf_t['menu_order'],
		),
		true
	);

	if ( is_wp_error( $ltf_id ) ) {
		echo 'Post error (' . esc_html( $ltf_t['name'] ) . '): ' . esc_html( $ltf_id->get_error_message() ) . "\n";
		continue;
	}

	if ( ! $ltf_no_tags ) {
		self_tag_therapist( $ltf_id, $ltf_t, $ltf_locations );
	}

	// Photo (downloaded from the team page into playground/photos/).
	foreach ( array( 'jpg', 'png' ) as $ltf_ext ) {
		$ltf_photo = $ltf_dir . '/photos/' . $ltf_t['slug'] . '.' . $ltf_ext;
		if ( ! file_exists( $ltf_photo ) ) {
			continue;
		}
		$ltf_tmp = wp_tempnam( $ltf_t['slug'] . '.' . $ltf_ext );
		copy( $ltf_photo, $ltf_tmp );
		$ltf_media = media_handle_sideload(
			array(
				'name'     => $ltf_t['slug'] . '.' . $ltf_ext,
				'tmp_name' => $ltf_tmp,
			),
			$ltf_id,
			$ltf_t['name']
		);
		if ( is_wp_error( $ltf_media ) ) {
			echo 'Photo error (' . esc_html( $ltf_t['name'] ) . '): ' . esc_html( $ltf_media->get_error_message() ) . "\n";
		} else {
			update_post_meta( $ltf_media, '_wp_attachment_image_alt', $ltf_t['photo_alt'] ?? $ltf_t['name'] );
			set_post_thumbnail( $ltf_id, $ltf_media );
		}
		break;
	}
}

/**
 * Applies the demo tagging to one therapist.
 */
function self_tag_therapist( int $ltf_id, array $ltf_t, array $ltf_locations ): void {
	update_post_meta( $ltf_id, Content_Model::META_ROLE, $ltf_t['role'] );
	if ( $ltf_t['years'] ) {
		update_post_meta( $ltf_id, Content_Model::META_YEARS, (int) $ltf_t['years'] );
	}
	if ( $ltf_t['accreditations'] ) {
		update_post_meta( $ltf_id, Content_Model::META_ACCREDITATIONS, implode( ', ', $ltf_t['accreditations'] ) );
	}

	$ltf_calendars = array();
	foreach ( $ltf_t['calendars'] as $ltf_slug => $ltf_ref ) {
		if ( ! empty( $ltf_locations[ $ltf_slug ] ) ) {
			$ltf_calendars[ $ltf_locations[ $ltf_slug ] ] = (string) $ltf_ref;
		}
	}
	update_post_meta( $ltf_id, Content_Model::META_CALENDARS, $ltf_calendars );

	wp_set_object_terms( $ltf_id, $ltf_t['locations'], Content_Model::TAX_LOCATION );
	wp_set_object_terms( $ltf_id, $ltf_t['services'], Content_Model::TAX_SERVICE );
	wp_set_object_terms( $ltf_id, $ltf_t['issues'], Content_Model::TAX_ISSUE );
	wp_set_object_terms( $ltf_id, array_map( 'strtolower', $ltf_t['languages'] ), Content_Model::TAX_LANGUAGE );
}

// Demo pages: each shows a different shortcode set-up.
$ltf_pages = array(
	array(
		'find-a-therapist',
		'Find a Therapist',
		'Tell us where you would like to meet and what you would like help with, and we will show you the therapists who fit.',
		'[therapist_finder]',
	),
	array(
		'couples-counselling',
		'Couples Counselling',
		'Couples counselling helps you understand each other, communicate better and work through difficult times together. These are the therapists who work with couples:',
		'[therapist_finder service="couples-counselling"]',
	),
	array(
		'family-therapy',
		'Family Therapy',
		'Systemic family therapy helps families understand patterns, improve communication and support each other. These are our family therapists:',
		'[therapist_finder service="family-therapy"]',
	),
	array(
		'psychosexual-therapy',
		'Psychosexual Therapy',
		'Psychosexual therapy offers a safe, confidential space to explore sexual and intimacy concerns, individually or as a couple.',
		'[therapist_finder service="psychosexual-therapy"]',
	),
	array(
		'online-therapy',
		'Online Therapy',
		'Face-to-face sessions on Zoom, wherever you are. Every therapist below offers online sessions:',
		'[therapist_finder location="online"]',
	),
	array(
		'fulham',
		'Fulham Clinic',
		'Studio 19, Hurlingham Studios, Ranelagh Gardens, London SW6 3PA. Therapists who see clients in Fulham (shown in a random order on each visit):',
		'[therapist_finder location="fulham" order="random"]',
	),
);

foreach ( $ltf_pages as $ltf_i => $ltf_page ) {
	list( $ltf_slug, $ltf_title, $ltf_intro, $ltf_shortcode ) = $ltf_page;
	$ltf_content = "<!-- wp:paragraph -->\n<p>" . esc_html( $ltf_intro ) . "</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:group {\"align\":\"wide\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group alignwide\">"
		. "<!-- wp:shortcode -->\n" . $ltf_shortcode . "\n<!-- /wp:shortcode --></div>\n<!-- /wp:group -->";

	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $ltf_title,
			'post_name'    => $ltf_slug,
			'post_content' => $ltf_content,
			'menu_order'   => $ltf_i,
		)
	);
}

// Tidy default content.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $ltf_slug => $ltf_type ) {
	$ltf_post = get_page_by_path( $ltf_slug, OBJECT, $ltf_type );
	if ( $ltf_post ) {
		wp_delete_post( $ltf_post->ID, true );
	}
}

$ltf_front = get_page_by_path( 'find-a-therapist' );
if ( $ltf_front ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ltf_front->ID );
}

flush_rewrite_rules();
update_option( 'ltf_demo_seeded', gmdate( 'c' ) );

echo 'Seeded ' . count( $ltf_data['therapists'] ) . " therapists.\n";
