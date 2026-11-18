<?php
/**
 * Pricing view: plan cards with a nested feature list.
 *
 * This is the example with real depth — `plans` is a repeater whose rows hold
 * another repeater (`features`). Two things follow from that, and both are why
 * the component exists in this shape:
 *
 * - The editor maintains each feature as its own row: reorderable, deletable,
 *   impossible to lose in a text block. A textarea with one line per feature
 *   would be one careless paste away from a broken list.
 * - The view does not know how many plans or features there are. Nothing here
 *   is hard-coded to three tiers, and a page with one plan or five looks right.
 *
 * Exactly one plan may be marked as the recommended one; if an author marks
 * several, they all look ordinary — a highlight that marks everything marks
 * nothing, and silently picking the first would be a guess about intent.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( ! sdcpb_has( 'plans' ) ) {
	return;
}

// Count the highlighted plans first: only a single one is a recommendation.
$sdcpb_plans       = Sdcpb_View::resolve( 'plans' );
$sdcpb_highlights  = 0;

foreach ( (array) $sdcpb_plans as $sdcpb_plan ) {
	if ( is_array( $sdcpb_plan ) && ! empty( $sdcpb_plan['highlight'] ) ) {
		++$sdcpb_highlights;
	}
}
?>
<section class="sdc-pricing">
	<?php if ( sdcpb_has( 'heading' ) ) : ?>
		<h2 class="sdc-pricing__heading"><?php sdcpb_text( 'heading' ); ?></h2>
	<?php endif; ?>

	<?php if ( sdcpb_has( 'intro' ) ) : ?>
		<p class="sdc-pricing__intro"><?php sdcpb_text( 'intro' ); ?></p>
	<?php endif; ?>

	<ul class="sdc-pricing__plans">
		<?php
		sdcpb_each(
			'plans',
			static function ( $plan ) use ( $sdcpb_highlights ) {
				$featured = 1 === (int) $sdcpb_highlights && ! empty( $plan['highlight'] );

				echo '<li class="sdc-pricing__plan' . ( $featured ? ' sdc-pricing__plan--featured' : '' ) . '">';

				if ( sdcpb_has( 'name', $plan ) ) {
					echo '<h3 class="sdc-pricing__name">';
					sdcpb_text( 'name', $plan );
					echo '</h3>';
				}

				if ( sdcpb_has( 'price', $plan ) ) {
					echo '<p class="sdc-pricing__price"><span class="sdc-pricing__amount">';
					sdcpb_text( 'price', $plan );
					echo '</span>';

					if ( sdcpb_has( 'period', $plan ) ) {
						echo ' <span class="sdc-pricing__period">';
						sdcpb_text( 'period', $plan );
						echo '</span>';
					}

					echo '</p>';
				}

				if ( sdcpb_has( 'description', $plan ) ) {
					echo '<p class="sdc-pricing__description">';
					sdcpb_text( 'description', $plan );
					echo '</p>';
				}

				if ( sdcpb_has( 'features', $plan ) ) {
					echo '<ul class="sdc-pricing__features">';

					sdcpb_each(
						'features',
						static function ( $feature ) {
							if ( ! sdcpb_has( 'text', $feature ) ) {
								return;
							}

							echo '<li class="sdc-pricing__feature">';
							sdcpb_text( 'text', $feature );
							echo '</li>';
						},
						$plan
					);

					echo '</ul>';
				}

				if ( sdcpb_has( 'link', $plan ) ) {
					echo '<p class="sdc-pricing__action">';
					sdcpb_link(
						'link',
						'sdc-pricing__button' . ( $featured ? ' sdc-pricing__button--featured' : '' ),
						'',
						$plan
					);
					echo '</p>';
				}

				echo '</li>';
			}
		);
		?>
	</ul>
</section>
