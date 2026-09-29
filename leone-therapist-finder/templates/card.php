<?php
/**
 * One therapist card.
 *
 * Copy to yourtheme/leone-therapist-finder/card.php to override. The data-* attributes on the
 * <li> drive filtering in assets/js/finder.js.
 *
 * @var array $t   Therapist data from Finder::therapist().
 * @var array $ctx Finder context.
 * @package LeoneCentre\TherapistFinder
 */

use LeoneCentre\TherapistFinder\Finder;

defined( 'ABSPATH' ) || exit;

$active_issue    = $ctx['state']['issue'] ?? '';
$active_location = $ctx['active_location'];
$issue_limit     = (int) $ctx['settings']['issue_limit'];
$issue_links     = isset( $ctx['options']['issue'] );

// Put the issue the visitor searched for first, so they can see why this therapist matched.
$issues = $t['facets']['issue'];
if ( '' !== $active_issue && isset( $issues[ $active_issue ] ) ) {
	$issues = array( $active_issue => $issues[ $active_issue ] ) + $issues;
}
$extra = max( 0, count( $issues ) - $issue_limit );

$meta = array();
if ( $t['years'] > 0 ) {
	/* translators: %d: years of experience. */
	$meta[] = sprintf( __( '%d+ years’ experience', 'leone-therapist-finder' ), $t['years'] );
}
$meta = array_merge( $meta, $t['accreditations'] );
?>
<li class="ltf-card" data-ltf-card
	data-location="<?php echo esc_attr( implode( ' ', array_keys( $t['facets']['location'] ) ) ); ?>"
	data-issue="<?php echo esc_attr( implode( ' ', array_keys( $t['facets']['issue'] ) ) ); ?>"
	data-service="<?php echo esc_attr( implode( ' ', array_keys( $t['facets']['service'] ) ) ); ?>"
	data-language="<?php echo esc_attr( implode( ' ', array_keys( $t['facets']['language'] ) ) ); ?>"
	data-search="<?php echo esc_attr( $t['search'] ); ?>"
	data-booking="<?php echo esc_attr( (string) wp_json_encode( (object) $t['booking'] ) ); ?>"
	data-name="<?php echo esc_attr( $t['name'] ); ?>"
	<?php echo $t['visible'] ? '' : 'hidden'; ?>>
	<article class="ltf-card__inner">
		<?php /* Plain <div>s on purpose: themes commonly style (or script) <header>/<footer>/<aside> as the site header and footer. */ ?>
		<div class="ltf-card__header">
			<a class="ltf-card__media" href="<?php echo esc_url( $t['url'] ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo $t['photo']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup / escaped initials. ?>
			</a>
			<div class="ltf-card__heading">
				<h3 class="ltf-card__name"><a href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['name'] ); ?></a></h3>
				<?php if ( '' !== $t['role'] ) : ?>
					<p class="ltf-card__role"><?php echo esc_html( $t['role'] ); ?></p>
				<?php endif; ?>
				<?php if ( $meta ) : ?>
					<p class="ltf-card__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $t['facets']['location'] ) : ?>
			<ul class="ltf-card__locations" aria-label="<?php esc_attr_e( 'Sees clients', 'leone-therapist-finder' ); ?>">
				<?php foreach ( $t['facets']['location'] as $slug => $name ) : ?>
					<li class="ltf-loc<?php echo $slug === $active_location ? ' is-match' : ''; ?>" data-location="<?php echo esc_attr( $slug ); ?>">
						<?php echo Finder::icon( false !== strpos( $slug, 'online' ) ? 'video' : 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
						<?php echo esc_html( $name ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== $t['summary'] ) : ?>
			<p class="ltf-card__summary"><?php echo esc_html( $t['summary'] ); ?></p>
		<?php endif; ?>

		<?php if ( $issues ) : ?>
			<div class="ltf-card__issues">
				<p class="ltf-card__issues-label"><?php esc_html_e( 'Can help with', 'leone-therapist-finder' ); ?></p>
				<ul class="ltf-chips">
					<?php $i = 0; ?>
					<?php foreach ( $issues as $slug => $name ) : ?>
						<li class="ltf-chip-item<?php echo $slug === $active_issue ? ' is-match' : ''; ?>" data-issue="<?php echo esc_attr( $slug ); ?>" <?php echo $i++ >= $issue_limit ? 'hidden' : ''; ?>>
							<?php if ( $issue_links ) : ?>
								<a class="ltf-chip" href="<?php echo esc_url( add_query_arg( Finder::PARAM . 'issue', $slug, $ctx['reset_url'] ) ); ?>" data-ltf-issue="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></a>
							<?php else : ?>
								<span class="ltf-chip"><?php echo esc_html( $name ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $extra > 0 ) : ?>
					<?php /* A link to the profile without JavaScript; finder.js turns it into an expand toggle. */ ?>
					<a class="ltf-more" href="<?php echo esc_url( $t['url'] ); ?>" data-ltf-more>
						<?php
						/* translators: %d: number of hidden issues. */
						echo esc_html( sprintf( __( '+%d more', 'leone-therapist-finder' ), $extra ) );
						?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="ltf-card__actions">
			<a class="ltf-btn ltf-btn--ghost" href="<?php echo esc_url( $t['url'] ); ?>"><?php esc_html_e( 'View profile', 'leone-therapist-finder' ); ?><span class="screen-reader-text ltf-sr"> <?php echo esc_html( $t['name'] ); ?></span></a>
			<div class="ltf-book" data-ltf-book>
				<?php echo Finder::booking_html( $t, $active_location, $ctx['appointment_type'], (string) $ctx['settings']['contact_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in booking_html(). ?>
			</div>
		</div>
	</article>
</li>
