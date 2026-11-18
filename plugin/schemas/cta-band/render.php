<?php
/**
 * CTA band view: one statement, up to two ways onward.
 *
 * A `select` field plus a class name is the whole theming story here: the view
 * turns `tone` into a modifier class and the stylesheet decides what "brand",
 * "dark" and "light" mean. A site that wants its own colours overrides three
 * custom properties in its theme's CSS — no component file has to change.
 *
 * The secondary link is a text link, not a second button: two equally loud
 * buttons make the reader choose instead of act.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( ! sdcpb_has( 'heading' ) ) {
	return;
}

$sdcpb_tone = sanitize_html_class( (string) Sdcpb_View::resolve( 'tone' ) );

if ( ! in_array( $sdcpb_tone, array( 'brand', 'dark', 'light' ), true ) ) {
	$sdcpb_tone = 'brand';
}
?>
<section class="sdc-cta sdc-cta--<?php echo esc_attr( $sdcpb_tone ); ?>">
	<div class="sdc-cta__inner">
		<div class="sdc-cta__text">
			<?php if ( sdcpb_has( 'eyebrow' ) ) : ?>
				<p class="sdc-cta__eyebrow"><?php sdcpb_text( 'eyebrow' ); ?></p>
			<?php endif; ?>

			<h2 class="sdc-cta__heading"><?php sdcpb_text( 'heading' ); ?></h2>

			<?php if ( sdcpb_has( 'text' ) ) : ?>
				<p class="sdc-cta__lead"><?php sdcpb_text( 'text' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( sdcpb_has( 'primary' ) || sdcpb_has( 'secondary' ) ) : ?>
			<p class="sdc-cta__actions">
				<?php if ( sdcpb_has( 'primary' ) ) : ?>
					<?php sdcpb_link( 'primary', 'sdc-cta__button sdc-cta__button--primary' ); ?>
				<?php endif; ?>

				<?php if ( sdcpb_has( 'secondary' ) ) : ?>
					<?php sdcpb_link( 'secondary', 'sdc-cta__button sdc-cta__button--secondary' ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</section>
