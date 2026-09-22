<?php
/**
 * Therapist finder wrapper: filters, status line, results and help box.
 *
 * Copy to yourtheme/leone-therapist-finder/finder.php to override. Keep the data-* hooks and
 * ltf-* class names used by assets/js/finder.js.
 *
 * @var array $ctx Prepared by Finder::render_shortcode().
 * @package LeoneCentre\TherapistFinder
 */

use LeoneCentre\TherapistFinder\Finder;

defined( 'ABSPATH' ) || exit;

$uid      = $ctx['uid'];
$labels   = $ctx['labels'];
$state    = $ctx['state'];
$options  = $ctx['options'];
$settings = $ctx['settings'];
$filtered = '' !== $ctx['query'] || array_filter( $state );

// Location, therapy type and issue are the main questions; the rest fold away on phones.
$secondary = array_intersect( array( 'language' ), array_keys( $options ) );
$has_more  = $secondary || $ctx['has_search'];
?>
<div class="ltf" id="<?php echo esc_attr( $uid ); ?>" data-ltf style="<?php echo esc_attr( $ctx['accent'] ); ?>">

	<?php if ( '' !== $ctx['title'] ) : ?>
		<h2 class="ltf__title"><?php echo esc_html( $ctx['title'] ); ?></h2>
	<?php endif; ?>

	<?php if ( $options || $ctx['has_search'] ) : ?>
		<form class="ltf-filters" method="get" role="search" aria-label="<?php esc_attr_e( 'Filter therapists', 'leone-therapist-finder' ); ?>">
			<?php foreach ( $ctx['preserve'] as $key => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php endforeach; ?>

			<?php if ( isset( $options['location'] ) ) : ?>
				<fieldset class="ltf-filter ltf-filter--location" data-facet="location">
					<legend class="ltf-filter__label"><?php echo esc_html( $labels['location'] ); ?></legend>
					<div class="ltf-pills">
						<label class="ltf-pill">
							<input class="ltf-pill__input" type="radio" name="<?php echo esc_attr( Finder::PARAM . 'location' ); ?>" value="" <?php checked( '', $state['location'] ); ?>>
							<span class="ltf-pill__body"><span class="ltf-pill__text"><?php echo esc_html( $labels['any']['location'] ); ?></span><span class="ltf-pill__count"></span></span>
						</label>
						<?php foreach ( $options['location'] as $group ) : ?>
							<?php foreach ( $group['options'] as $slug => $name ) : ?>
								<label class="ltf-pill">
									<input class="ltf-pill__input" type="radio" name="<?php echo esc_attr( Finder::PARAM . 'location' ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $state['location'] ); ?>>
									<span class="ltf-pill__body">
										<?php echo Finder::icon( false !== strpos( $slug, 'online' ) ? 'video' : 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
										<span class="ltf-pill__text"><?php echo esc_html( $name ); ?></span><span class="ltf-pill__count"></span>
									</span>
								</label>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endif; ?>

			<div class="ltf-filter-row">
				<?php foreach ( array( 'service', 'issue', 'language' ) as $facet ) : ?>
					<?php
					if ( ! isset( $options[ $facet ] ) ) {
						continue;
					}
					?>
					<div class="ltf-filter<?php echo in_array( $facet, $secondary, true ) ? ' ltf-filter--secondary' : ''; ?>" data-facet="<?php echo esc_attr( $facet ); ?>">
						<label class="ltf-filter__label" for="<?php echo esc_attr( $uid . '-' . $facet ); ?>"><?php echo esc_html( $labels[ $facet ] ); ?></label>
						<select class="ltf-select" id="<?php echo esc_attr( $uid . '-' . $facet ); ?>" name="<?php echo esc_attr( Finder::PARAM . $facet ); ?>">
							<option value=""><?php echo esc_html( $labels['any'][ $facet ] ); ?></option>
							<?php foreach ( $options[ $facet ] as $group ) : ?>
								<?php if ( '' !== $group['label'] ) : ?>
									<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
								<?php endif; ?>
								<?php foreach ( $group['options'] as $slug => $name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $state[ $facet ] ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
								<?php if ( '' !== $group['label'] ) : ?>
									</optgroup>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endforeach; ?>

				<?php if ( $ctx['has_search'] ) : ?>
					<div class="ltf-filter ltf-filter--search ltf-filter--secondary">
						<label class="ltf-filter__label" for="<?php echo esc_attr( $uid . '-q' ); ?>"><?php echo esc_html( $labels['search'] ); ?></label>
						<input class="ltf-input" type="search" id="<?php echo esc_attr( $uid . '-q' ); ?>" name="<?php echo esc_attr( Finder::PARAM . 'q' ); ?>" value="<?php echo esc_attr( $ctx['query'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. EMDR, Italian, Susan', 'leone-therapist-finder' ); ?>" autocomplete="off">
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $has_more && ( isset( $options['location'] ) || isset( $options['service'] ) || isset( $options['issue'] ) ) ) : ?>
				<button type="button" class="ltf-filters__toggle" data-ltf-toggle-filters aria-expanded="false" data-more="<?php esc_attr_e( 'More filters', 'leone-therapist-finder' ); ?>" data-fewer="<?php esc_attr_e( 'Fewer filters', 'leone-therapist-finder' ); ?>"><?php esc_html_e( 'More filters', 'leone-therapist-finder' ); ?></button>
			<?php endif; ?>

			<div class="ltf-filters__submit">
				<button type="submit" class="ltf-btn ltf-btn--primary"><?php esc_html_e( 'Show therapists', 'leone-therapist-finder' ); ?></button>
			</div>
		</form>
	<?php endif; ?>

	<div class="ltf-status">
		<p class="ltf-status__text" role="status" aria-live="polite"><?php echo esc_html( Finder::status_text( $ctx['visible'], $ctx['total'] ) ); ?></p>
		<a class="ltf-status__reset" href="<?php echo esc_url( $ctx['reset_url'] ); ?>" <?php echo $filtered ? '' : 'hidden'; ?>><?php esc_html_e( 'Clear filters', 'leone-therapist-finder' ); ?></a>
	</div>

	<ul class="ltf-results" aria-label="<?php esc_attr_e( 'Therapists', 'leone-therapist-finder' ); ?>">
		<?php
		foreach ( $ctx['therapists'] as $therapist ) {
			echo Finder::render_card( $therapist, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in card template.
		}
		?>
	</ul>

	<div class="ltf-empty" <?php echo $ctx['visible'] > 0 ? 'hidden' : ''; ?>>
		<p class="ltf-empty__title"><?php esc_html_e( 'No therapists match all of those filters.', 'leone-therapist-finder' ); ?></p>
		<p class="ltf-empty__hint"><?php esc_html_e( 'Try removing one:', 'leone-therapist-finder' ); ?></p>
		<div class="ltf-empty__actions" data-ltf-relax>
			<a class="ltf-btn ltf-btn--ghost" href="<?php echo esc_url( $ctx['reset_url'] ); ?>"><?php esc_html_e( 'Clear all filters', 'leone-therapist-finder' ); ?></a>
		</div>
	</div>

	<?php if ( $ctx['show_help'] && '' !== trim( (string) $settings['help_text'] ) ) : ?>
		<aside class="ltf-help">
			<?php if ( '' !== $settings['help_title'] ) : ?>
				<h3 class="ltf-help__title"><?php echo esc_html( $settings['help_title'] ); ?></h3>
			<?php endif; ?>
			<div class="ltf-help__text"><?php echo wp_kses_post( wpautop( $settings['help_text'] ) ); ?></div>
		</aside>
	<?php endif; ?>

	<script type="application/json" class="ltf-config"><?php echo wp_json_encode( $ctx['js_config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
