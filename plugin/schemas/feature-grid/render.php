<?php
/**
 * Feature grid view: a list of cards, each with an icon, a title and a text.
 *
 * The icon is what this file demonstrates: the field stores a key (`check`,
 * `bolt`), and only this file knows what that key looks like. A `select` field
 * plus a view replaces an icon-picker plugin — and the markup stays inline SVG,
 * so there is no icon font to load and nothing to license.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( ! sdcpb_has( 'items' ) ) {
	return;
}

/*
 * One path per choice of the `icon` select field. The keys must match the
 * choices in definition.json: an unknown key prints no icon, never a broken
 * one — the component still reads fine without it.
 */
$sdcpb_feature_icons = array(
	'check'  => 'M20 6 9 17l-5-5',
	'bolt'   => 'M13 2 4 14h6l-1 8 9-12h-6l1-8z',
	'shield' => 'M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6l8-3z',
	'chart'  => 'M4 20V10M10 20V4M16 20v-7M3 20h18',
);
?>
<section class="sdc-feature-grid">
	<?php if ( sdcpb_has( 'heading' ) ) : ?>
		<h2 class="sdc-feature-grid__heading"><?php sdcpb_text( 'heading' ); ?></h2>
	<?php endif; ?>

	<?php if ( sdcpb_has( 'intro' ) ) : ?>
		<p class="sdc-feature-grid__intro"><?php sdcpb_text( 'intro' ); ?></p>
	<?php endif; ?>

	<ul class="sdc-feature-grid__items">
		<?php
		sdcpb_each(
			'items',
			static function ( $row ) use ( $sdcpb_feature_icons ) {
				$icon = isset( $row['icon'] ) ? (string) $row['icon'] : '';
				$path = isset( $sdcpb_feature_icons[ $icon ] ) ? $sdcpb_feature_icons[ $icon ] : '';

				echo '<li class="sdc-feature-grid__item">';

				if ( '' !== $path ) {
					printf(
						'<svg class="sdc-feature-grid__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="%s"/></svg>',
						esc_attr( $path )
					);
				}

				/*
				 * The link, when there is one, carries the title as its text.
				 * Wrapping the heading in the link instead would nest a block
				 * element inside an inline one.
				 */
				echo '<h3 class="sdc-feature-grid__title">';

				if ( sdcpb_has( 'link', $row ) ) {
					sdcpb_link( 'link', 'sdc-feature-grid__link', Sdcpb_View::to_text( Sdcpb_View::resolve( 'title', $row ) ), $row );
				} elseif ( sdcpb_has( 'title', $row ) ) {
					sdcpb_text( 'title', $row );
				}

				echo '</h3>';

				if ( sdcpb_has( 'text', $row ) ) {
					echo '<p class="sdc-feature-grid__text">';
					sdcpb_text( 'text', $row );
					echo '</p>';
				}

				echo '</li>';
			}
		);
		?>
	</ul>
</section>
