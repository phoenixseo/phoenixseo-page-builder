<?php
/**
 * Testimonials view: quote cards in a horizontal, swipeable row.
 *
 * This file demonstrates two things a JSON-only component cannot do:
 *
 * 1. A quote is a `<blockquote>` inside a `<figure>` — the citation belongs to
 *    the quotation, and a screen reader reads it that way. Generic field output
 *    can only print labelled divs.
 * 2. The row is scrolled with CSS scroll snapping, not with a slider library.
 *    No script, no dependency, and the cards stay reachable with the keyboard
 *    because the scroll container is focusable.
 *
 * A missing photo falls back to the initials: an author who has no picture is
 * not forced to upload a grey placeholder.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( ! sdcpb_has( 'items' ) ) {
	return;
}

$sdcpb_initial = static function ( $name ) {
	$name = trim( Sdcpb_View::to_text( $name ) );

	if ( '' === $name ) {
		return '';
	}

	$first = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );

	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first, 'UTF-8' ) : strtoupper( $first );
};

/*
 * Two testimonial components on one page must not share an id, so the label
 * target is unique per instance rather than derived from the post.
 */
$sdcpb_heading_id = wp_unique_id( 'sdc-testimonials-' );
?>
<section class="sdc-testimonials">
	<?php if ( sdcpb_has( 'heading' ) ) : ?>
		<h2 class="sdc-testimonials__heading" id="<?php echo esc_attr( $sdcpb_heading_id ); ?>"><?php sdcpb_text( 'heading' ); ?></h2>
	<?php endif; ?>

	<div
		class="sdc-testimonials__row"
		role="group"
		tabindex="0"
		<?php if ( sdcpb_has( 'heading' ) ) : ?>
			aria-labelledby="<?php echo esc_attr( $sdcpb_heading_id ); ?>"
		<?php else : ?>
			aria-label="<?php esc_attr_e( 'Kundenstimmen', 'sdc-page-builder' ); ?>"
		<?php endif; ?>
	>
		<?php
		sdcpb_each(
			'items',
			static function ( $row ) use ( $sdcpb_initial ) {
				$name = Sdcpb_View::to_text( Sdcpb_View::resolve( 'name', $row ) );

				echo '<figure class="sdc-testimonials__card">';
				echo '<blockquote class="sdc-testimonials__quote"><p>';
				sdcpb_text( 'quote', $row );
				echo '</p></blockquote>';

				echo '<figcaption class="sdc-testimonials__caption">';

				if ( sdcpb_has( 'photo', $row ) ) {
					sdcpb_image( 'photo', 'thumbnail', array( 'class' => 'sdc-testimonials__photo' ), $row );
				} else {
					echo '<span class="sdc-testimonials__initials" aria-hidden="true">' . esc_html( $sdcpb_initial( $name ) ) . '</span>';
				}

				echo '<span class="sdc-testimonials__person">';
				echo '<span class="sdc-testimonials__name">' . esc_html( $name ) . '</span>';

				if ( sdcpb_has( 'role', $row ) ) {
					echo '<span class="sdc-testimonials__role">';
					sdcpb_text( 'role', $row );
					echo '</span>';
				}

				echo '</span>';
				echo '</figcaption>';
				echo '</figure>';
			}
		);
		?>
	</div>
</section>
