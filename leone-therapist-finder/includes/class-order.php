<?php
/**
 * "Order" screen: sets the order therapists appear in the finder.
 *
 * Stored in the post's menu_order, which the finder sorts by. The order is written straight to the
 * posts table rather than through wp_update_post(), so reordering does not change the modified
 * date or fire save hooks on every profile.
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Drag-and-drop ordering.
 */
final class Order_Screen {

	const PAGE = 'ltf-order';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Content_Model::post_type(),
			__( 'Order therapists', 'leone-therapist-finder' ),
			__( 'Order', 'leone-therapist-finder' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Set one therapist's position. Returns true when something changed.
	 */
	public static function set_position( int $post_id, int $position ): bool {
		global $wpdb;

		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== Content_Model::post_type() || (int) $post->menu_order === $position ) {
			return false;
		}

		$wpdb->update( $wpdb->posts, array( 'menu_order' => $position ), array( 'ID' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $post_id );

		return true;
	}

	/**
	 * Apply an order: [ post_id => position ]. Positions are renumbered from 1.
	 */
	public static function save( array $positions ): int {
		asort( $positions, SORT_NUMERIC );

		$position = 0;
		$saved    = 0;
		foreach ( array_keys( $positions ) as $post_id ) {
			$post_id = (int) $post_id;
			++$position;

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			if ( self::set_position( $post_id, $position ) ) {
				++$saved;
			}
		}

		return $saved;
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$saved = -1;

		if ( isset( $_POST['ltf_order'] ) && check_admin_referer( 'ltf_order' ) ) {
			$positions = array();
			foreach ( (array) wp_unslash( $_POST['ltf_order'] ) as $post_id => $position ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Cast below.
				$positions[ absint( $post_id ) ] = absint( $position );
			}
			$saved = self::save( $positions );
		}

		$therapists = get_posts(
			array(
				'post_type'      => Content_Model::post_type(),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 300,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
		?>
		<div class="wrap ltf-settings">
			<h1><?php esc_html_e( 'Order therapists', 'leone-therapist-finder' ); ?></h1>

			<?php if ( $saved >= 0 ) : ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: %d: number of therapists moved. */
						esc_html( _n( 'Order saved: %d therapist moved.', 'Order saved: %d therapists moved.', $saved, 'leone-therapist-finder' ) ),
						(int) $saved
					);
					?>
				</p></div>
			<?php endif; ?>

			<div class="ltf-admin-card">
				<p>
					<?php esc_html_e( 'This is the order therapists appear in the finder, including inside filtered results. Drag them into the order you want, or type position numbers, then save.', 'leone-therapist-finder' ); ?>
				</p>

				<?php if ( ! $therapists ) : ?>
					<p><?php esc_html_e( 'No therapists yet.', 'leone-therapist-finder' ); ?></p>
				<?php else : ?>
					<form method="post">
						<?php wp_nonce_field( 'ltf_order' ); ?>
						<ol class="ltf-order-list" id="ltf-order-list">
							<?php foreach ( $therapists as $index => $therapist ) : ?>
								<li class="ltf-order-item" data-id="<?php echo esc_attr( (string) $therapist->ID ); ?>">
									<span class="ltf-order-handle" aria-hidden="true">⠿</span>
									<label class="screen-reader-text" for="ltf-order-<?php echo esc_attr( (string) $therapist->ID ); ?>">
										<?php
										printf(
											/* translators: %s: therapist name. */
											esc_html__( 'Position for %s', 'leone-therapist-finder' ),
											esc_html( get_the_title( $therapist ) )
										);
										?>
									</label>
									<input type="number" class="small-text ltf-order-position" id="ltf-order-<?php echo esc_attr( (string) $therapist->ID ); ?>" name="ltf_order[<?php echo esc_attr( (string) $therapist->ID ); ?>]" value="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>" min="1" step="1">
									<?php if ( has_post_thumbnail( $therapist ) ) : ?>
										<?php echo get_the_post_thumbnail( $therapist, array( 40, 40 ), array( 'class' => 'ltf-order-photo', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.MultipleStatementAlignment ?>
									<?php endif; ?>
									<span class="ltf-order-name"><?php echo esc_html( get_the_title( $therapist ) ); ?></span>
									<span class="ltf-order-role"><?php echo esc_html( (string) get_post_meta( $therapist->ID, Content_Model::META_ROLE, true ) ); ?></span>
									<?php if ( 'publish' !== $therapist->post_status ) : ?>
										<span class="ltf-check is-muted"><?php echo esc_html( $therapist->post_status ); ?></span>
									<?php elseif ( get_post_meta( $therapist->ID, Content_Model::META_HIDE, true ) ) : ?>
										<span class="ltf-check is-muted"><?php esc_html_e( 'Hidden from finder', 'leone-therapist-finder' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
						<?php submit_button( __( 'Save order', 'leone-therapist-finder' ) ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery( function ( $ ) {
			var list = $( '#ltf-order-list' );
			if ( ! list.length || ! $.fn.sortable ) {
				return;
			}
			list.addClass( 'ltf-order-list--drag' ).sortable( {
				handle: '.ltf-order-handle',
				axis: 'y',
				placeholder: 'ltf-order-placeholder',
				forcePlaceholderSize: true,
				update: function () {
					list.find( '.ltf-order-position' ).each( function ( index ) {
						$( this ).val( index + 1 );
					} );
				}
			} );
		} );
		</script>
		<?php
	}
}
