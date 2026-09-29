<?php
/**
 * Admin UI: therapist details box, list columns, therapy-type fields and the settings page.
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
final class Admin {

	const PAGE = 'ltf-settings';

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_columns' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_action( Content_Model::TAX_SERVICE . '_add_form_fields', array( __CLASS__, 'service_add_fields' ) );
		add_action( Content_Model::TAX_SERVICE . '_edit_form_fields', array( __CLASS__, 'service_edit_fields' ) );
		add_action( 'created_' . Content_Model::TAX_SERVICE, array( __CLASS__, 'save_service_fields' ) );
		add_action( 'edited_' . Content_Model::TAX_SERVICE, array( __CLASS__, 'save_service_fields' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function enqueue( string $hook ): void {
		$screen = get_current_screen();
		$ours   = ( $screen && $screen->post_type === Content_Model::post_type() ) || false !== strpos( $hook, self::PAGE );

		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style( 'ltf-admin', plugins_url( 'assets/css/admin.css', PLUGIN_FILE ), array(), asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'ltf-admin', plugins_url( 'assets/js/admin.js', PLUGIN_FILE ), array(), asset_version( 'assets/js/admin.js' ), true );
	}

	/* ------------------------------------------------------------------ */
	/* Therapist edit screen                                              */
	/* ------------------------------------------------------------------ */

	public static function add_meta_box(): void {
		add_meta_box(
			'ltf-therapist',
			__( 'Therapist finder', 'leone-therapist-finder' ),
			array( __CLASS__, 'render_meta_box' ),
			Content_Model::post_type(),
			'normal',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		$terms     = Content_Model::location_terms();
		$assigned  = wp_get_object_terms( $post->ID, Content_Model::TAX_LOCATION, array( 'fields' => 'ids' ) );
		$assigned  = is_array( $assigned ) ? array_map( 'intval', $assigned ) : array();
		$calendars = Content_Model::get_calendars( $post->ID );
		$years     = (int) get_post_meta( $post->ID, Content_Model::META_YEARS, true );

		wp_nonce_field( 'ltf_save_meta', 'ltf_meta_nonce' );
		?>
		<div class="ltf-metabox">
			<p>
				<label for="ltf_role"><strong><?php esc_html_e( 'Role / title', 'leone-therapist-finder' ); ?></strong></label>
				<input type="text" class="widefat" id="ltf_role" name="ltf_role" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, Content_Model::META_ROLE, true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Couples and Family Therapist', 'leone-therapist-finder' ); ?>">
			</p>
			<p>
				<label for="ltf_summary"><strong><?php esc_html_e( 'Finder summary', 'leone-therapist-finder' ); ?></strong></label>
				<textarea class="widefat" id="ltf_summary" name="ltf_summary" rows="3" data-ltf-summary placeholder="<?php esc_attr_e( 'A short description shown on the finder card, e.g. the summary used on the Meet Our Team page.', 'leone-therapist-finder' ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, Content_Model::META_SUMMARY, true ) ); ?></textarea>
				<span class="description">
					<?php esc_html_e( 'Around 30 words works best; roughly four lines are shown. Leave empty to use the excerpt, or the start of the bio.', 'leone-therapist-finder' ); ?>
					<span class="ltf-word-count" data-ltf-summary-count></span>
				</span>
			</p>
			<div class="ltf-metabox__cols">
				<p>
					<label for="ltf_years"><strong><?php esc_html_e( 'Years of experience', 'leone-therapist-finder' ); ?></strong></label>
					<input type="number" class="small-text" id="ltf_years" name="ltf_years" min="0" max="80" value="<?php echo $years ? esc_attr( (string) $years ) : ''; ?>">
				</p>
				<p class="ltf-metabox__grow">
					<label for="ltf_accreditations"><strong><?php esc_html_e( 'Accreditations', 'leone-therapist-finder' ); ?></strong></label>
					<input type="text" class="widefat" id="ltf_accreditations" name="ltf_accreditations" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, Content_Model::META_ACCREDITATIONS, true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. UKCP, AFT (comma separated)', 'leone-therapist-finder' ); ?>">
				</p>
			</div>

			<h4><?php esc_html_e( 'Where they see clients', 'leone-therapist-finder' ); ?></h4>
			<?php if ( ! $terms ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: link to the Locations screen. */
						esc_html__( 'No locations yet. %s first.', 'leone-therapist-finder' ),
						'<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . Content_Model::TAX_LOCATION . '&post_type=' . Content_Model::post_type() ) ) . '">' . esc_html__( 'Add locations', 'leone-therapist-finder' ) . '</a>'
					);
					?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php
					echo wp_kses(
						__( 'Tick each place this therapist works and paste their Acuity calendar ID for it (the number after <code>calendarID=</code> in their booking link) or the full booking link.', 'leone-therapist-finder' ),
						array( 'code' => array() )
					);
					?>
				</p>
				<table class="widefat striped ltf-locations">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Location', 'leone-therapist-finder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Acuity calendar ID or booking link', 'leone-therapist-finder' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Test', 'leone-therapist-finder' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $terms as $term ) : ?>
							<?php
							$ref = (string) ( $calendars[ $term->term_id ] ?? '' );
							$url = '' !== $ref ? Booking::url( $ref ) : '';
							$id  = 'ltf-loc-' . $term->term_id;
							?>
							<tr>
								<td>
									<label for="<?php echo esc_attr( $id ); ?>">
										<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="ltf_loc[<?php echo esc_attr( (string) $term->term_id ); ?>][on]" value="1" <?php checked( in_array( $term->term_id, $assigned, true ) ); ?> data-ltf-loc-toggle>
										<?php echo esc_html( $term->name ); ?>
									</label>
								</td>
								<td>
									<input type="text" class="regular-text code" name="ltf_loc[<?php echo esc_attr( (string) $term->term_id ); ?>][ref]" value="<?php echo esc_attr( $ref ); ?>" placeholder="<?php esc_attr_e( 'e.g. 12954163', 'leone-therapist-finder' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: location name. */ __( 'Acuity calendar for %s', 'leone-therapist-finder' ), $term->name ) ); ?>" data-ltf-loc-ref>
								</td>
								<td>
									<?php if ( $url ) : ?>
										<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Test link', 'leone-therapist-finder' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'leone-therapist-finder' ); ?></span> &#8599;</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p>
				<label>
					<input type="checkbox" name="ltf_hide" value="1" <?php checked( (bool) get_post_meta( $post->ID, Content_Model::META_HIDE, true ) ); ?>>
					<?php esc_html_e( 'Hide from the therapist finder (e.g. not taking new clients)', 'leone-therapist-finder' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Therapy types, issues and languages are set in the boxes in the sidebar. The card summary uses the excerpt, or the start of the bio.', 'leone-therapist-finder' ); ?>
			</p>
		</div>
		<?php
	}

	public static function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( $post->post_type !== Content_Model::post_type() ) {
			return;
		}

		$nonce = isset( $_POST['ltf_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ltf_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ltf_save_meta' ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::update_or_delete( $post_id, Content_Model::META_ROLE, sanitize_text_field( wp_unslash( $_POST['ltf_role'] ?? '' ) ) );
		self::update_or_delete( $post_id, Content_Model::META_SUMMARY, sanitize_textarea_field( wp_unslash( $_POST['ltf_summary'] ?? '' ) ) );
		self::update_or_delete( $post_id, Content_Model::META_YEARS, absint( $_POST['ltf_years'] ?? 0 ) );
		self::update_or_delete( $post_id, Content_Model::META_ACCREDITATIONS, sanitize_text_field( wp_unslash( $_POST['ltf_accreditations'] ?? '' ) ) );
		self::update_or_delete( $post_id, Content_Model::META_HIDE, empty( $_POST['ltf_hide'] ) ? '' : '1' );

		// Locations and their Acuity calendars. Refs are kept even for unticked rows, so
		// temporarily unticking a clinic does not lose the calendar ID.
		$rows      = isset( $_POST['ltf_loc'] ) && is_array( $_POST['ltf_loc'] ) ? wp_unslash( $_POST['ltf_loc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised per field below.
		$term_ids  = array();
		$calendars = array();
		foreach ( Content_Model::location_terms() as $term ) {
			$row = isset( $rows[ $term->term_id ] ) && is_array( $rows[ $term->term_id ] ) ? $rows[ $term->term_id ] : array();
			$ref = Content_Model::sanitize_booking_ref( (string) ( $row['ref'] ?? '' ) );
			if ( '' !== $ref ) {
				$calendars[ $term->term_id ] = $ref;
			}
			if ( ! empty( $row['on'] ) ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		wp_set_object_terms( $post_id, $term_ids, Content_Model::TAX_LOCATION );
		self::update_or_delete( $post_id, Content_Model::META_CALENDARS, $calendars );
	}

	/**
	 * @param mixed $value Empty values delete the meta.
	 */
	private static function update_or_delete( int $post_id, string $key, $value ): void {
		if ( empty( $value ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Data problems that would make a therapist hard to find or book.
	 */
	public static function checks( \WP_Post $post ): array {
		$problems  = array();
		$locations = get_the_terms( $post, Content_Model::TAX_LOCATION );
		$calendars = Content_Model::get_calendars( $post->ID );

		if ( ! is_array( $locations ) ) {
			$problems[] = __( 'No locations', 'leone-therapist-finder' );
		} else {
			foreach ( $locations as $term ) {
				if ( empty( $calendars[ $term->term_id ] ) ) {
					/* translators: %s: location name. */
					$problems[] = sprintf( __( 'No booking link for %s', 'leone-therapist-finder' ), $term->name );
				}
			}
		}
		if ( ! is_array( get_the_terms( $post, Content_Model::TAX_SERVICE ) ) ) {
			$problems[] = __( 'No therapy types', 'leone-therapist-finder' );
		}
		if ( ! is_array( get_the_terms( $post, Content_Model::TAX_ISSUE ) ) ) {
			$problems[] = __( 'No issues tagged', 'leone-therapist-finder' );
		}
		if ( ! has_post_thumbnail( $post ) ) {
			$problems[] = __( 'No photo', 'leone-therapist-finder' );
		}
		if ( '' === (string) get_post_meta( $post->ID, Content_Model::META_ROLE, true ) ) {
			$problems[] = __( 'No role / title', 'leone-therapist-finder' );
		}
		if ( '' === trim( (string) get_post_meta( $post->ID, Content_Model::META_SUMMARY, true ) ) && ! has_excerpt( $post ) ) {
			$problems[] = __( 'No finder summary (using the start of the bio)', 'leone-therapist-finder' );
		}

		return (array) apply_filters( 'ltf_admin_checks', $problems, $post );
	}

	/* ------------------------------------------------------------------ */
	/* Therapist list columns                                             */
	/* ------------------------------------------------------------------ */

	public static function register_columns(): void {
		$post_type = Content_Model::post_type();
		add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['ltf_where']    = __( 'Where & booking', 'leone-therapist-finder' );
				$out['ltf_services'] = __( 'Therapy types', 'leone-therapist-finder' );
				$out['ltf_issues']   = __( 'Issues', 'leone-therapist-finder' );
				$out['ltf_check']    = __( 'Finder check', 'leone-therapist-finder' );
			}
		}

		return $out;
	}

	public static function column( string $column, int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		switch ( $column ) {
			case 'ltf_where':
				$calendars = Content_Model::get_calendars( $post_id );
				$terms     = get_the_terms( $post, Content_Model::TAX_LOCATION );
				if ( ! is_array( $terms ) ) {
					echo '&mdash;';
					break;
				}
				usort( $terms, static fn( $a, $b ) => $a->term_id <=> $b->term_id ); // Same order as the finder.
				$parts = array();
				foreach ( $terms as $term ) {
					$ok      = ! empty( $calendars[ $term->term_id ] );
					$parts[] = sprintf(
						'<span class="ltf-pill-admin %1$s" title="%2$s">%3$s</span>',
						$ok ? 'is-ok' : 'is-warn',
						esc_attr( $ok ? __( 'Bookable', 'leone-therapist-finder' ) : __( 'No Acuity booking link', 'leone-therapist-finder' ) ),
						esc_html( $term->name )
					);
				}
				echo implode( ' ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				break;

			case 'ltf_services':
				$terms = get_the_terms( $post, Content_Model::TAX_SERVICE );
				echo is_array( $terms ) ? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) : '&mdash;';
				break;

			case 'ltf_issues':
				$terms = get_the_terms( $post, Content_Model::TAX_ISSUE );
				if ( is_array( $terms ) ) {
					printf( '<span title="%s">%d</span>', esc_attr( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ), count( $terms ) );
				} else {
					echo '&mdash;';
				}
				break;

			case 'ltf_check':
				if ( get_post_meta( $post_id, Content_Model::META_HIDE, true ) ) {
					echo '<span class="ltf-check is-muted">' . esc_html__( 'Hidden from finder', 'leone-therapist-finder' ) . '</span>';
					break;
				}
				$problems = self::checks( $post );
				if ( ! $problems ) {
					echo '<span class="ltf-check is-ok">' . esc_html__( 'Ready', 'leone-therapist-finder' ) . '</span>';
				} else {
					echo '<span class="ltf-check is-warn">' . esc_html( implode( '; ', $problems ) ) . '</span>';
				}
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Therapy type: Acuity appointment type                              */
	/* ------------------------------------------------------------------ */

	public static function service_add_fields(): void {
		wp_nonce_field( 'ltf_term_meta', 'ltf_term_nonce' );
		?>
		<div class="form-field">
			<label for="ltf_appointment_type"><?php esc_html_e( 'Acuity appointment type', 'leone-therapist-finder' ); ?></label>
			<input type="text" id="ltf_appointment_type" name="ltf_appointment_type" value="">
			<p><?php self::appointment_type_help(); ?></p>
		</div>
		<?php
	}

	public static function service_edit_fields( \WP_Term $term ): void {
		wp_nonce_field( 'ltf_term_meta', 'ltf_term_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="ltf_appointment_type"><?php esc_html_e( 'Acuity appointment type', 'leone-therapist-finder' ); ?></label></th>
			<td>
				<input type="text" id="ltf_appointment_type" name="ltf_appointment_type" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, Content_Model::TERM_META_APPOINTMENT_TYPE, true ) ); ?>">
				<p class="description"><?php self::appointment_type_help(); ?></p>
			</td>
		</tr>
		<?php
	}

	private static function appointment_type_help(): void {
		echo wp_kses(
			__( 'Optional. When a visitor books from a page filtered to this therapy type, Acuity opens with this appointment type selected. Use the numeric ID, or <code>category:Name</code> for a whole category.', 'leone-therapist-finder' ),
			array( 'code' => array() )
		);
	}

	public static function save_service_fields( int $term_id ): void {
		$nonce = isset( $_POST['ltf_term_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ltf_term_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ltf_term_meta' ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$value = Content_Model::sanitize_appointment_type( wp_unslash( $_POST['ltf_appointment_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by the callee.
		if ( '' === $value ) {
			delete_term_meta( $term_id, Content_Model::TERM_META_APPOINTMENT_TYPE );
		} else {
			update_term_meta( $term_id, Content_Model::TERM_META_APPOINTMENT_TYPE, $value );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings page                                                      */
	/* ------------------------------------------------------------------ */

	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Content_Model::post_type(),
			__( 'Therapist Finder', 'leone-therapist-finder' ),
			__( 'Finder settings', 'leone-therapist-finder' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function action_links( array $links ): array {
		$url = admin_url( 'edit.php?post_type=' . Content_Model::post_type() . '&page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'leone-therapist-finder' ) . '</a>' );

		return $links;
	}

	public static function register_settings(): void {
		register_setting(
			'ltf_settings',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section( 'ltf_general', __( 'Setup', 'leone-therapist-finder' ), '__return_false', self::PAGE );
		add_settings_section( 'ltf_display', __( 'Display', 'leone-therapist-finder' ), '__return_false', self::PAGE );

		$fields = array(
			'post_type'     => array( 'ltf_general', __( 'Therapist post type', 'leone-therapist-finder' ), 'post_type', __( 'The post type your therapist profiles use. If it does not exist, the plugin creates it.', 'leone-therapist-finder' ) ),
			'scheduler_url' => array( 'ltf_general', __( 'Acuity scheduling page', 'leone-therapist-finder' ), 'url', __( 'Calendar IDs are added to this address, e.g. https://leonecentre.as.me/?calendarID=12954163', 'leone-therapist-finder' ) ),
			'contact_url'   => array( 'ltf_general', __( 'Contact page', 'leone-therapist-finder' ), 'text', __( 'Used for the "Enquire" button when a therapist has no booking link.', 'leone-therapist-finder' ) ),
			'help_title'    => array( 'ltf_display', __( 'Help box heading', 'leone-therapist-finder' ), 'text', '' ),
			'help_text'     => array( 'ltf_display', __( 'Help box text', 'leone-therapist-finder' ), 'textarea', __( 'Shown under the results. Basic HTML allowed. Leave empty to hide.', 'leone-therapist-finder' ) ),
			'issue_limit'   => array( 'ltf_display', __( 'Issues shown per card', 'leone-therapist-finder' ), 'number', __( 'The rest are behind "+N more".', 'leone-therapist-finder' ) ),
			'accent'        => array( 'ltf_display', __( 'Accent colour', 'leone-therapist-finder' ), 'color', __( 'A darker shade is used automatically for buttons, to keep text readable.', 'leone-therapist-finder' ) ),
		);

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'ltf_' . $key,
				$field[1],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				$field[0],
				array(
					'key'       => $key,
					'type'      => $field[2],
					'help'      => $field[3],
					'label_for' => 'ltf_' . $key,
				)
			);
		}
	}

	public static function render_field( array $args ): void {
		$value = Settings::get( $args['key'] );
		$name  = Settings::OPTION . '[' . $args['key'] . ']';
		$id    = 'ltf_' . $args['key'];

		switch ( $args['type'] ) {
			case 'post_type':
				$types = get_post_types( array( 'show_ui' => true ), 'objects' );
				unset( $types['attachment'], $types['wp_block'], $types['wp_navigation'], $types['wp_template'], $types['wp_template_part'] );
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				if ( ! isset( $types[ $value ] ) ) {
					printf( '<option value="%1$s" selected>%1$s</option>', esc_attr( (string) $value ) );
				}
				foreach ( $types as $type ) {
					printf( '<option value="%s" %s>%s (%s)</option>', esc_attr( $type->name ), selected( $value, $type->name, false ), esc_html( $type->labels->name ), esc_html( $type->name ) );
				}
				echo '</select>';
				break;

			case 'textarea':
				printf( '<textarea class="large-text" rows="3" id="%s" name="%s">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;

			case 'number':
				printf( '<input type="number" class="small-text" min="1" max="20" id="%s" name="%s" value="%d">', esc_attr( $id ), esc_attr( $name ), (int) $value );
				break;

			case 'color':
				printf( '<input type="color" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
				break;

			default:
				printf( '<input type="%s" class="regular-text" id="%s" name="%s" value="%s">', 'url' === $args['type'] ? 'url' : 'text', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
		}

		if ( '' !== $args['help'] ) {
			echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
		}
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap ltf-settings">
			<h1><?php esc_html_e( 'Therapist Finder', 'leone-therapist-finder' ); ?></h1>
			<?php settings_errors(); ?>

			<div class="ltf-settings__grid">
				<div>
					<?php self::render_builder(); ?>
					<?php self::render_data_check(); ?>
				</div>
				<div class="ltf-admin-card">
					<form action="options.php" method="post">
						<?php
						settings_fields( 'ltf_settings' );
						do_settings_sections( self::PAGE );
						submit_button();
						?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_builder(): void {
		$facets = array(
			'service'  => array( __( 'Offer', 'leone-therapist-finder' ), __( 'any therapy type', 'leone-therapist-finder' ) ),
			'location' => array( __( 'Work in', 'leone-therapist-finder' ), __( 'any location', 'leone-therapist-finder' ) ),
			'issue'    => array( __( 'Help with', 'leone-therapist-finder' ), __( 'any issue', 'leone-therapist-finder' ) ),
		);
		$filters = array(
			'location' => __( 'Location', 'leone-therapist-finder' ),
			'service'  => __( 'Therapy type', 'leone-therapist-finder' ),
			'issue'    => __( 'Issue', 'leone-therapist-finder' ),
			'language' => __( 'Language', 'leone-therapist-finder' ),
			'search'   => __( 'Keyword search', 'leone-therapist-finder' ),
		);
		$tax = Content_Model::facets();
		?>
		<div class="ltf-admin-card" id="ltf-builder">
			<h2><?php esc_html_e( 'Shortcode builder', 'leone-therapist-finder' ); ?></h2>
			<p><?php esc_html_e( 'Choose what to show, then paste the shortcode into any page, e.g. the Couples Counselling page.', 'leone-therapist-finder' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Only show therapists who…', 'leone-therapist-finder' ); ?></th>
					<td class="ltf-builder__fixed">
						<?php foreach ( $facets as $facet => $text ) : ?>
							<?php
							$terms = get_terms(
								array(
									'taxonomy'   => $tax[ $facet ],
									'hide_empty' => false,
									'orderby'    => 'location' === $facet ? 'term_id' : 'name',
								)
							);
							?>
							<label>
								<span><?php echo esc_html( $text[0] ); ?></span>
								<select data-ltf-fix="<?php echo esc_attr( $facet ); ?>">
									<option value=""><?php echo esc_html( $text[1] ); ?></option>
									<?php foreach ( is_array( $terms ) ? $terms : array() as $term ) : ?>
										<?php
										if ( 'issue' === $facet && 0 === (int) $term->parent && get_term_children( $term->term_id, $term->taxonomy ) ) {
											continue; // Issue groups are headings, not tags.
										}
										?>
										<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?> (<?php echo (int) $term->count; ?>)</option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Filters visitors can use', 'leone-therapist-finder' ); ?></th>
					<td>
						<?php foreach ( $filters as $key => $label ) : ?>
							<label class="ltf-builder__check"><input type="checkbox" data-ltf-filter="<?php echo esc_attr( $key ); ?>" checked> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'A filter is switched off automatically when you fix its value above.', 'leone-therapist-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ltf-builder-order"><?php esc_html_e( 'Order', 'leone-therapist-finder' ); ?></label></th>
					<td>
						<select id="ltf-builder-order" data-ltf-order>
							<option value="default"><?php esc_html_e( 'Menu order', 'leone-therapist-finder' ); ?></option>
							<option value="random"><?php esc_html_e( 'Random on each visit (spreads enquiries across the team)', 'leone-therapist-finder' ); ?></option>
							<option value="name"><?php esc_html_e( 'By name', 'leone-therapist-finder' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ltf-builder-width"><?php esc_html_e( 'Width', 'leone-therapist-finder' ); ?></label></th>
					<td>
						<select id="ltf-builder-width" data-ltf-width>
							<option value=""><?php esc_html_e( 'Follow the page (default)', 'leone-therapist-finder' ); ?></option>
							<option value="wide"><?php esc_html_e( 'Wider than the page, up to 1200px', 'leone-therapist-finder' ); ?></option>
							<option value="full"><?php esc_html_e( 'Full width of the screen', 'leone-therapist-finder' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Use this when the page template is narrow and the cards look cramped.', 'leone-therapist-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ltf-builder-output"><?php esc_html_e( 'Shortcode', 'leone-therapist-finder' ); ?></label></th>
					<td>
						<div class="ltf-builder__output">
							<input type="text" readonly class="large-text code" id="ltf-builder-output" value="[therapist_finder]" data-ltf-output>
							<button type="button" class="button" data-ltf-copy><?php esc_html_e( 'Copy', 'leone-therapist-finder' ); ?></button>
						</div>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private static function render_data_check(): void {
		$posts = get_posts(
			array(
				'post_type'      => Content_Model::post_type(),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$problems = array();
		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, Content_Model::META_HIDE, true ) ) {
				continue;
			}
			$found = self::checks( $post );
			if ( $found ) {
				$problems[] = array( $post, $found );
			}
		}

		$empty_locations = array();
		foreach ( Content_Model::location_terms() as $term ) {
			if ( 0 === (int) $term->count ) {
				$empty_locations[] = $term->name;
			}
		}
		?>
		<div class="ltf-admin-card">
			<h2><?php esc_html_e( 'Data check', 'leone-therapist-finder' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: therapists ready, 2: total therapists. */
					esc_html__( '%1$d of %2$d published therapists are fully set up for the finder.', 'leone-therapist-finder' ),
					count( $posts ) - count( $problems ),
					count( $posts )
				);
				?>
			</p>
			<?php if ( $empty_locations ) : ?>
				<p class="ltf-check is-warn">
					<?php
					/* translators: %s: list of locations. */
					echo esc_html( sprintf( __( 'No therapists at: %s. These locations are hidden from visitors until someone is added.', 'leone-therapist-finder' ), implode( ', ', $empty_locations ) ) );
					?>
				</p>
			<?php endif; ?>
			<?php if ( $problems ) : ?>
				<ul class="ltf-data-check">
					<?php foreach ( $problems as $row ) : ?>
						<li><a href="<?php echo esc_url( (string) get_edit_post_link( $row[0] ) ); ?>"><?php echo esc_html( get_the_title( $row[0] ) ); ?></a> &mdash; <?php echo esc_html( implode( '; ', $row[1] ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
