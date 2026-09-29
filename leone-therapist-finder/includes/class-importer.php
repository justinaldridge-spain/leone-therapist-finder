<?php
/**
 * Imports therapist tagging (locations + Acuity calendars, therapy types, issues, languages)
 * onto profiles that already exist on the site, matched by their URL slug.
 *
 * It only ever writes this plugin's own taxonomies and meta. Titles, bios, excerpts, featured
 * images, page content and menu order are never touched. Every import records what each therapist
 * looked like beforehand, so it can be undone.
 *
 * @package LeoneCentre\TherapistFinder
 */

namespace LeoneCentre\TherapistFinder;

defined( 'ABSPATH' ) || exit;

/**
 * The Import tags screen.
 */
final class Importer {

	const PAGE        = 'ltf-import';
	const UNDO_OPTION = 'ltf_import_undo';
	const MAX_UPLOAD  = 2097152; // 2 MB.

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Content_Model::post_type(),
			__( 'Import therapist tags', 'leone-therapist-finder' ),
			__( 'Import tags', 'leone-therapist-finder' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function bundled_file(): string {
		return PLUGIN_DIR . 'data/leonecentre-team.json';
	}

	/* ------------------------------------------------------------------ */
	/* Screen                                                             */
	/* ------------------------------------------------------------------ */

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plan   = null;
		$result = null;
		$error  = '';

		if ( isset( $_POST['ltf_import_action'] ) ) {
			check_admin_referer( 'ltf_import' );

			$action    = sanitize_key( wp_unslash( $_POST['ltf_import_action'] ) );
			$overwrite = ! empty( $_POST['ltf_overwrite'] );

			if ( 'undo' === $action ) {
				$result = self::undo();
				if ( is_wp_error( $result ) ) {
					$error  = $result->get_error_message();
					$result = null;
				}
			} else {
				$data = self::read_source();
				if ( is_wp_error( $data ) ) {
					$error = $data->get_error_message();
				} else {
					$plan = self::plan( $data, $overwrite );
					if ( 'import' === $action ) {
						$result = self::apply( $plan );
						$plan   = null;
					}
				}
			}
		}

		$undo = get_option( self::UNDO_OPTION );
		?>
		<div class="wrap ltf-settings">
			<h1><?php esc_html_e( 'Import therapist tags', 'leone-therapist-finder' ); ?></h1>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( $result ) : ?>
				<?php self::render_result( $result ); ?>
			<?php endif; ?>

			<div class="ltf-admin-card">
				<p>
					<?php esc_html_e( 'Tags existing therapist profiles from a data file, matching them by their URL slug. It adds locations and Acuity calendar IDs, therapy types, issues and languages.', 'leone-therapist-finder' ); ?>
					<strong><?php esc_html_e( 'Bios, photos, titles and page content are never changed.', 'leone-therapist-finder' ); ?></strong>
					<?php esc_html_e( 'Preview first to see exactly what would change.', 'leone-therapist-finder' ); ?>
				</p>

				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'ltf_import' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Data file', 'leone-therapist-finder' ); ?></th>
							<td>
								<?php if ( file_exists( self::bundled_file() ) ) : ?>
									<p><?php esc_html_e( 'The file included with the plugin is used unless you upload one here.', 'leone-therapist-finder' ); ?></p>
								<?php else : ?>
									<p><?php esc_html_e( 'Upload a data file (JSON).', 'leone-therapist-finder' ); ?></p>
								<?php endif; ?>
								<input type="file" name="ltf_file" accept="application/json,.json">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Already tagged therapists', 'leone-therapist-finder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="ltf_overwrite" value="1" <?php checked( ! empty( $_POST['ltf_overwrite'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Redisplaying the submitted form. ?>>
									<?php esc_html_e( 'Replace their tags too', 'leone-therapist-finder' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Left unticked, therapists who already have tags are skipped, so hand-made changes are kept.', 'leone-therapist-finder' ); ?></p>
							</td>
						</tr>
					</table>
					<p>
						<button type="submit" name="ltf_import_action" value="preview" class="button button-secondary"><?php esc_html_e( 'Preview', 'leone-therapist-finder' ); ?></button>
						<?php if ( $plan ) : ?>
							<button type="submit" name="ltf_import_action" value="import" class="button button-primary"><?php esc_html_e( 'Import now', 'leone-therapist-finder' ); ?></button>
						<?php endif; ?>
					</p>
					<?php if ( $plan ) : ?>
						<p class="description"><?php esc_html_e( 'If you uploaded a file, choose it again before importing.', 'leone-therapist-finder' ); ?></p>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( $plan ) : ?>
				<?php self::render_plan( $plan ); ?>
			<?php endif; ?>

			<?php if ( is_array( $undo ) && ! empty( $undo['posts'] ) ) : ?>
				<div class="ltf-admin-card">
					<h2><?php esc_html_e( 'Undo', 'leone-therapist-finder' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: 1: number of therapists, 2: date and time. */
							esc_html__( 'The last import changed %1$d therapists on %2$s. Undoing puts their tags back exactly as they were and removes any terms it created that are still unused.', 'leone-therapist-finder' ),
							count( $undo['posts'] ),
							esc_html( wp_date( 'j M Y, H:i', (int) ( $undo['time'] ?? time() ) ) )
						);
						?>
					</p>
					<form method="post">
						<?php wp_nonce_field( 'ltf_import' ); ?>
						<button type="submit" name="ltf_import_action" value="undo" class="button"><?php esc_html_e( 'Undo last import', 'leone-therapist-finder' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_plan( array $plan ): void {
		$labels = array(
			'new'     => __( 'Will be tagged', 'leone-therapist-finder' ),
			'update'  => __( 'Tags will be replaced', 'leone-therapist-finder' ),
			'skip'    => __( 'Skipped (already tagged)', 'leone-therapist-finder' ),
			'missing' => __( 'No matching profile on this site', 'leone-therapist-finder' ),
		);
		?>
		<div class="ltf-admin-card">
			<h2><?php esc_html_e( 'Preview', 'leone-therapist-finder' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: therapists to tag, 2: skipped, 3: unmatched, 4: new terms. */
					esc_html__( '%1$d therapists to tag, %2$d skipped, %3$d not found on this site, and %4$d new terms to create.', 'leone-therapist-finder' ),
					(int) $plan['counts']['write'],
					(int) $plan['counts']['skip'],
					(int) $plan['counts']['missing'],
					(int) $plan['counts']['terms']
				);
				?>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Therapist', 'leone-therapist-finder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'leone-therapist-finder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Tags to apply', 'leone-therapist-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plan['rows'] as $row ) : ?>
						<tr>
							<td>
								<?php if ( $row['post_id'] ) : ?>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $row['post_id'] ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['name'] ); ?>
								<?php endif; ?>
								<br><code><?php echo esc_html( $row['slug'] ); ?></code>
							</td>
							<td>
								<span class="ltf-check <?php echo 'missing' === $row['status'] ? 'is-warn' : ( 'skip' === $row['status'] ? 'is-muted' : 'is-ok' ); ?>">
									<?php echo esc_html( $labels[ $row['status'] ] ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $row['summary'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $plan['unmatched_posts'] ) : ?>
				<p class="ltf-check is-warn">
					<?php
					printf(
						/* translators: %s: list of therapist names. */
						esc_html__( 'On the site but not in the data file: %s. Tag these by hand.', 'leone-therapist-finder' ),
						esc_html( implode( ', ', $plan['unmatched_posts'] ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_result( array $result ): void {
		?>
		<div class="notice notice-success">
			<p>
				<?php if ( 'undo' === $result['type'] ) : ?>
					<?php
					printf(
						/* translators: %d: number of therapists. */
						esc_html__( 'Undone: %d therapists put back as they were.', 'leone-therapist-finder' ),
						(int) $result['posts']
					);
					?>
				<?php else : ?>
					<?php
					printf(
						/* translators: 1: therapists tagged, 2: terms created. */
						esc_html__( 'Imported: %1$d therapists tagged and %2$d terms created.', 'leone-therapist-finder' ),
						(int) $result['posts'],
						(int) $result['terms']
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Content_Model::post_type() ) ); ?>"><?php esc_html_e( 'Check the therapist list', 'leone-therapist-finder' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array|\WP_Error
	 */
	private static function read_source() {
		$json = '';

		if ( ! empty( $_FILES['ltf_file']['tmp_name'] ) && UPLOAD_ERR_NO_FILE !== ( $_FILES['ltf_file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$file = $_FILES['ltf_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Checked below.

			if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
				return new \WP_Error( 'ltf_upload', __( 'The file could not be uploaded.', 'leone-therapist-finder' ) );
			}
			if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new \WP_Error( 'ltf_upload', __( 'The file could not be read.', 'leone-therapist-finder' ) );
			}
			if ( (int) $file['size'] > self::MAX_UPLOAD ) {
				return new \WP_Error( 'ltf_upload', __( 'That file is too large.', 'leone-therapist-finder' ) );
			}

			$json = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp file.
		} elseif ( file_exists( self::bundled_file() ) ) {
			$json = (string) file_get_contents( self::bundled_file() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
		} else {
			return new \WP_Error( 'ltf_source', __( 'No data file was uploaded, and the plugin has none included.', 'leone-therapist-finder' ) );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || empty( $data['therapists'] ) || ! is_array( $data['therapists'] ) ) {
			return new \WP_Error( 'ltf_source', __( 'That file is not a therapist data file.', 'leone-therapist-finder' ) );
		}

		return $data;
	}

	/**
	 * Work out what would change, without changing anything.
	 */
	private static function plan( array $data, bool $overwrite ): array {
		$facets = Content_Model::facets();
		$terms  = array();

		// Terms in the file that do not exist yet. Nothing is ever renamed or deleted.
		foreach ( array( 'location' => 'locations', 'service' => 'services', 'language' => 'languages' ) as $facet => $key ) {
			foreach ( (array) ( $data[ $key ] ?? array() ) as $term ) {
				if ( ! term_exists( (string) $term['slug'], $facets[ $facet ] ) ) {
					$terms[] = array( $facets[ $facet ], (string) $term['slug'], (string) $term['name'], '' );
				}
			}
		}
		foreach ( (array) ( $data['issues'] ?? array() ) as $group ) {
			if ( ! term_exists( (string) $group['slug'], $facets['issue'] ) ) {
				$terms[] = array( $facets['issue'], (string) $group['slug'], (string) $group['name'], '' );
			}
			foreach ( (array) ( $group['children'] ?? array() ) as $child ) {
				if ( ! term_exists( (string) $child['slug'], $facets['issue'] ) ) {
					$terms[] = array( $facets['issue'], (string) $child['slug'], (string) $child['name'], (string) $group['slug'] );
				}
			}
		}

		$rows    = array();
		$matched = array();
		$counts  = array(
			'write'   => 0,
			'skip'    => 0,
			'missing' => 0,
			'terms'   => count( $terms ),
		);

		foreach ( $data['therapists'] as $therapist ) {
			$slug = sanitize_title( (string) ( $therapist['slug'] ?? '' ) );
			$post = self::find_post( $slug, (string) ( $therapist['name'] ?? '' ) );

			$row = array(
				'name'      => (string) ( $therapist['name'] ?? $slug ),
				'slug'      => $slug,
				'post_id'   => $post ? $post->ID : 0,
				'status'    => 'missing',
				'summary'   => '',
				'therapist' => $therapist,
			);

			if ( $post ) {
				$matched[ $post->ID ] = true;
				$tagged               = self::is_tagged( $post->ID );

				if ( $tagged && ! $overwrite ) {
					$row['status'] = 'skip';
					++$counts['skip'];
				} else {
					$row['status']  = $tagged ? 'update' : 'new';
					$row['summary'] = self::summarise( $therapist );
					++$counts['write'];
				}
			} else {
				++$counts['missing'];
			}

			$rows[] = $row;
		}

		// Published therapists on the site that the file says nothing about.
		$unmatched = array();
		foreach ( get_posts(
			array(
				'post_type'      => Content_Model::post_type(),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
			)
		) as $post ) {
			if ( empty( $matched[ $post->ID ] ) ) {
				$unmatched[] = $post->post_title;
			}
		}

		return array(
			'terms'           => $terms,
			'rows'            => $rows,
			'counts'          => $counts,
			'unmatched_posts' => $unmatched,
			'overwrite'       => $overwrite,
		);
	}

	/**
	 * Create the missing terms, then tag each therapist, remembering how they were.
	 */
	private static function apply( array $plan ): array {
		$created = array();

		foreach ( $plan['terms'] as $term ) {
			list( $taxonomy, $slug, $name, $parent_slug ) = $term;

			if ( term_exists( $slug, $taxonomy ) ) {
				continue;
			}

			$args   = array( 'slug' => $slug );
			$parent = $parent_slug ? get_term_by( 'slug', $parent_slug, $taxonomy ) : null;
			if ( $parent ) {
				$args['parent'] = $parent->term_id;
			}

			$result = wp_insert_term( $name, $taxonomy, $args );
			if ( ! is_wp_error( $result ) ) {
				$created[ $taxonomy ][] = (int) $result['term_id'];
			}
		}

		$facets   = Content_Model::facets();
		$snapshot = array();
		$tagged   = 0;

		foreach ( $plan['rows'] as $row ) {
			if ( ! in_array( $row['status'], array( 'new', 'update' ), true ) || ! $row['post_id'] ) {
				continue;
			}

			$post_id   = (int) $row['post_id'];
			$therapist = $row['therapist'];

			// Remember the current state so the import can be undone.
			$before = array(
				'terms' => array(),
				'meta'  => array(),
				'order' => (int) get_post_field( 'menu_order', $post_id ),
			);
			foreach ( $facets as $taxonomy ) {
				$before['terms'][ $taxonomy ] = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			}
			foreach ( array( Content_Model::META_ROLE, Content_Model::META_SUMMARY, Content_Model::META_YEARS, Content_Model::META_ACCREDITATIONS, Content_Model::META_CALENDARS ) as $key ) {
				$before['meta'][ $key ] = get_post_meta( $post_id, $key, true );
			}
			$snapshot[ $post_id ] = $before;

			// Taxonomies. Terms are looked up by slug, so nothing new is created here by accident.
			foreach ( array( 'location' => 'locations', 'service' => 'services', 'issue' => 'issues', 'language' => 'languages' ) as $facet => $key ) {
				$ids = array();
				foreach ( (array) ( $therapist[ $key ] ?? array() ) as $slug ) {
					$term = get_term_by( 'slug', sanitize_title( (string) $slug ), $facets[ $facet ] );
					if ( $term ) {
						$ids[] = (int) $term->term_id;
					}
				}
				wp_set_object_terms( $post_id, $ids, $facets[ $facet ] );
			}

			// Acuity calendars, keyed by location term ID. Existing IDs are kept.
			$calendars = Content_Model::get_calendars( $post_id );
			foreach ( (array) ( $therapist['calendars'] ?? array() ) as $location_slug => $ref ) {
				$term = get_term_by( 'slug', sanitize_title( (string) $location_slug ), Content_Model::TAX_LOCATION );
				$ref  = Content_Model::sanitize_booking_ref( (string) $ref );
				if ( $term && '' !== $ref ) {
					$calendars[ (int) $term->term_id ] = $ref;
				}
			}
			if ( $calendars ) {
				update_post_meta( $post_id, Content_Model::META_CALENDARS, $calendars );
			}

			self::fill_meta( $post_id, Content_Model::META_ROLE, (string) ( $therapist['role'] ?? '' ), $plan['overwrite'] );
			self::fill_meta( $post_id, Content_Model::META_SUMMARY, sanitize_textarea_field( (string) ( $therapist['summary'] ?? '' ) ), $plan['overwrite'] );
			self::fill_meta( $post_id, Content_Model::META_YEARS, (int) ( $therapist['years'] ?? 0 ), $plan['overwrite'] );
			self::fill_meta( $post_id, Content_Model::META_ACCREDITATIONS, implode( ', ', (array) ( $therapist['accreditations'] ?? array() ) ), $plan['overwrite'] );

			// Running order from the data file. Only set when the profile has no order of its own,
			// unless the import was told to replace existing tags.
			$order = absint( $therapist['order'] ?? 0 );
			if ( $order && ( $plan['overwrite'] || 0 === $before['order'] ) ) {
				Order_Screen::set_position( $post_id, $order );
			}

			++$tagged;
		}

		// Only replace the undo record when something actually changed, so an accidental second
		// run cannot throw away the ability to undo the first.
		if ( $snapshot || $created ) {
			update_option(
				self::UNDO_OPTION,
				array(
					'time'  => time(),
					'posts' => $snapshot,
					'terms' => $created,
				),
				false
			);
		}

		return array(
			'type'  => 'import',
			'posts' => $tagged,
			'terms' => count( $plan['terms'] ),
		);
	}

	/**
	 * @param string|int $value
	 */
	private static function fill_meta( int $post_id, string $key, $value, bool $overwrite ): void {
		if ( empty( $value ) ) {
			return;
		}
		if ( ! $overwrite && '' !== (string) get_post_meta( $post_id, $key, true ) ) {
			return; // Keep what someone typed in by hand.
		}

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * @return array|\WP_Error
	 */
	private static function undo() {
		$undo = get_option( self::UNDO_OPTION );

		if ( ! is_array( $undo ) || empty( $undo['posts'] ) ) {
			return new \WP_Error( 'ltf_undo', __( 'There is no import to undo.', 'leone-therapist-finder' ) );
		}

		foreach ( $undo['posts'] as $post_id => $before ) {
			if ( isset( $before['order'] ) ) {
				Order_Screen::set_position( (int) $post_id, (int) $before['order'] );
			}
			foreach ( (array) $before['terms'] as $taxonomy => $ids ) {
				wp_set_object_terms( (int) $post_id, array_map( 'intval', (array) $ids ), (string) $taxonomy );
			}
			foreach ( (array) $before['meta'] as $key => $value ) {
				if ( '' === $value || array() === $value || null === $value ) {
					delete_post_meta( (int) $post_id, (string) $key );
				} else {
					update_post_meta( (int) $post_id, (string) $key, $value );
				}
			}
		}

		// Remove terms the import created, but only if nothing is using them now.
		foreach ( (array) ( $undo['terms'] ?? array() ) as $taxonomy => $ids ) {
			foreach ( (array) $ids as $term_id ) {
				$term = get_term( (int) $term_id, (string) $taxonomy );
				if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
					wp_delete_term( (int) $term_id, (string) $taxonomy );
				}
			}
		}

		$count = count( $undo['posts'] );
		delete_option( self::UNDO_OPTION );

		return array(
			'type'  => 'undo',
			'posts' => $count,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Match on the profile's URL slug, then fall back to an exact name match.
	 */
	private static function find_post( string $slug, string $name ): ?\WP_Post {
		$post_type = Content_Model::post_type();

		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $post instanceof \WP_Post ) {
				return $post;
			}
		}

		if ( '' === $name ) {
			return null;
		}

		$matches = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
			)
		);

		foreach ( $matches as $post ) {
			if ( 0 === strcasecmp( trim( $post->post_title ), trim( $name ) ) ) {
				return $post;
			}
		}

		return null;
	}

	private static function is_tagged( int $post_id ): bool {
		foreach ( Content_Model::facets() as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				return true;
			}
		}

		return (bool) Content_Model::get_calendars( $post_id );
	}

	private static function summarise( array $therapist ): string {
		$locations = count( (array) ( $therapist['locations'] ?? array() ) );
		$calendars = count( array_filter( (array) ( $therapist['calendars'] ?? array() ) ) );
		$services  = count( (array) ( $therapist['services'] ?? array() ) );
		$issues    = count( (array) ( $therapist['issues'] ?? array() ) );
		$languages = count( (array) ( $therapist['languages'] ?? array() ) );

		$parts = array(
			/* translators: %d: number of locations. */
			sprintf( _n( '%d location', '%d locations', $locations, 'leone-therapist-finder' ), $locations )
			/* translators: %d: number of booking links. */
			. ' ' . sprintf( _n( '(%d booking link)', '(%d booking links)', $calendars, 'leone-therapist-finder' ), $calendars ),
			/* translators: %d: number of therapy types. */
			sprintf( _n( '%d therapy type', '%d therapy types', $services, 'leone-therapist-finder' ), $services ),
			/* translators: %d: number of issues. */
			sprintf( _n( '%d issue', '%d issues', $issues, 'leone-therapist-finder' ), $issues ),
			/* translators: %d: number of languages. */
			sprintf( _n( '%d language', '%d languages', $languages, 'leone-therapist-finder' ), $languages ),
		);

		if ( ! empty( $therapist['summary'] ) ) {
			$parts[] = __( 'summary', 'leone-therapist-finder' );
		}

		if ( ! empty( $therapist['order'] ) ) {
			/* translators: %d: position in the finder. */
			$parts[] = sprintf( __( 'position %d', 'leone-therapist-finder' ), absint( $therapist['order'] ) );
		}

		return implode( ' · ', $parts );
	}
}
