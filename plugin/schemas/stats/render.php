<?php
/**
 * Stats view: three or four numbers, marked up as a description list.
 *
 * The point of this file is the markup choice. A `<dl>` says "these numbers
 * belong to these labels", which is what a screen reader needs to read "98
 * percent, Weiterempfehlung" instead of a stream of loose text. Generic field
 * output prints labelled divs — readable, but it does not say how the parts
 * relate.
 *
 * The heading is optional and, when present, becomes the list's accessible
 * name, so the numbers are announced as a group rather than as stray content.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( ! sdcpb_has( 'items' ) ) {
	return;
}

$sdcpb_heading_id = wp_unique_id( 'sdc-stats-' );
?>
<section class="sdc-stats">
	<?php if ( sdcpb_has( 'heading' ) ) : ?>
		<h2 class="sdc-stats__heading" id="<?php echo esc_attr( $sdcpb_heading_id ); ?>"><?php sdcpb_text( 'heading' ); ?></h2>
	<?php endif; ?>

	<dl
		class="sdc-stats__list"
		<?php if ( sdcpb_has( 'heading' ) ) : ?>
			aria-labelledby="<?php echo esc_attr( $sdcpb_heading_id ); ?>"
		<?php endif; ?>
	>
		<?php
		sdcpb_each(
			'items',
			static function ( $row ) {
				if ( ! sdcpb_has( 'value', $row ) && ! sdcpb_has( 'label', $row ) ) {
					return;
				}

				echo '<div class="sdc-stats__item">';

				// The number comes first in the visual order but second in the
				// reading order: `<dt>` is the term, `<dd>` its value, and a
				// list read aloud should name the term before the number.
				if ( sdcpb_has( 'label', $row ) ) {
					echo '<dt class="sdc-stats__label">';
					sdcpb_text( 'label', $row );
					echo '</dt>';
				}

				echo '<dd class="sdc-stats__value">';
				sdcpb_text( 'value', $row );

				if ( sdcpb_has( 'note', $row ) ) {
					echo '<span class="sdc-stats__note">';
					sdcpb_text( 'note', $row );
					echo '</span>';
				}

				echo '</dd>';
				echo '</div>';
			}
		);
		?>
	</dl>
</section>
