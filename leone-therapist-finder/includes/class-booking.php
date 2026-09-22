<?php
/**
 * Builds Acuity Scheduling deep links.
 *
 * Phase 1 links straight to Acuity's own scheduler, so intake forms, payment, confirmations and
 * cancellation rules keep working exactly as they do today. No API credentials are needed.
 *
 * @see https://developers.acuityscheduling.com/docs/embedding
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Acuity URL helpers.
 */
final class Booking {

	/**
	 * @param string $ref Acuity calendar ID, or a full booking URL.
	 */
	public static function url( string $ref ): string {
		$ref = trim( $ref );
		$url = '';

		if ( ctype_digit( $ref ) ) {
			$url = add_query_arg( 'calendarID', $ref, (string) Settings::get( 'scheduler_url' ) );
		} elseif ( filter_var( $ref, FILTER_VALIDATE_URL ) ) {
			$url = $ref;
		}

		return (string) apply_filters( 'ltf_booking_url', $url, $ref );
	}

	/**
	 * Pre-select an appointment type (e.g. "Couples session") on the Acuity page.
	 */
	public static function with_appointment_type( string $url, string $appointment_type ): string {
		if ( '' === $url || '' === $appointment_type ) {
			return $url;
		}

		return add_query_arg( 'appointmentType', rawurlencode( $appointment_type ), $url );
	}

	/**
	 * Service slug => Acuity appointment type, for services that have one configured.
	 */
	public static function appointment_types(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Content_Model::TAX_SERVICE,
				'hide_empty' => false,
				'meta_key'   => Content_Model::TERM_META_APPOINTMENT_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Handful of terms.
			)
		);

		$types = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$type = (string) get_term_meta( $term->term_id, Content_Model::TERM_META_APPOINTMENT_TYPE, true );
				if ( '' !== $type ) {
					$types[ $term->slug ] = $type;
				}
			}
		}

		return $types;
	}
}
