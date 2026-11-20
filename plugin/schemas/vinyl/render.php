<?php
/**
 * Vinyl view: one record with its track list, grouped by side.
 *
 * Two things make this component more than a field dump, and both are plain
 * PHP rather than a template trick:
 *
 * 1. The track list is read once with `Sdcpb_View::resolve()` and then looped
 *    over with `foreach`. `sdcpb_each()` is the convenient way to walk a
 *    repeater, but it walks it once and forgets it — here the same rows are
 *    needed twice: once for the total playing time, once for the markup.
 * 2. The total playing time is *calculated*, so nobody has to maintain a
 *    "duration" field that inevitably drifts from the track list. It is only
 *    printed when every track carries a readable length; a sum over half the
 *    sides would look authoritative and be wrong.
 *
 * The track number is not a field either. The markup is an `<ol>` per side, so
 * the browser numbers the tracks — a number typed by hand can disagree with
 * the order, and then the page says "3" next to the fourth song.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

/*
 * Nothing to show, nothing to print. A record without an artist, an album and
 * a track list is an empty block in the canvas, not a heading with no text.
 */
if ( ! sdcpb_has( 'artist' ) && ! sdcpb_has( 'album' ) && ! sdcpb_has( 'tracks' ) ) {
	return;
}

/*
 * A length as written on a sleeve: "3:42" or "1:03:42". Anything else — a
 * dash, a question mark, an empty field — is unknown, not zero.
 */
$sdcpb_vinyl_seconds = static function ( $text ) {
	$text = trim( (string) $text );

	if ( ! preg_match( '/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})$/', $text, $parts ) ) {
		return null;
	}

	$hours = isset( $parts[1] ) && '' !== $parts[1] ? (int) $parts[1] : 0;

	if ( $hours > 0 && (int) $parts[2] > 59 ) {
		return null;
	}

	return ( $hours * 3600 ) + ( (int) $parts[2] * 60 ) + (int) $parts[3];
};

// Seconds back to the same notation, so the total reads like the sleeve.
$sdcpb_vinyl_clock = static function ( $seconds ) {
	$seconds = (int) $seconds;
	$hours   = intdiv( $seconds, 3600 );
	$minutes = intdiv( $seconds % 3600, 60 );

	if ( $hours > 0 ) {
		return sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds % 60 );
	}

	return sprintf( '%d:%02d', $minutes, $seconds % 60 );
};

$sdcpb_vinyl_rows   = array();
$sdcpb_vinyl_known  = 0;
$sdcpb_vinyl_total  = 0;
$sdcpb_vinyl_sides  = array();

foreach ( (array) Sdcpb_View::resolve( 'tracks' ) as $sdcpb_vinyl_row ) {
	if ( ! is_array( $sdcpb_vinyl_row ) || '' === trim( Sdcpb_View::to_text( Sdcpb_View::resolve( 'title', $sdcpb_vinyl_row ) ) ) ) {
		continue;
	}

	$sdcpb_vinyl_side  = trim( Sdcpb_View::to_text( Sdcpb_View::resolve( 'side', $sdcpb_vinyl_row ) ) );
	$sdcpb_vinyl_side  = '' === $sdcpb_vinyl_side ? 'A' : $sdcpb_vinyl_side;
	$sdcpb_vinyl_length = $sdcpb_vinyl_seconds( Sdcpb_View::resolve( 'duration', $sdcpb_vinyl_row ) );

	if ( null !== $sdcpb_vinyl_length ) {
		++$sdcpb_vinyl_known;
		$sdcpb_vinyl_total += $sdcpb_vinyl_length;
	}

	$sdcpb_vinyl_sides[ $sdcpb_vinyl_side ][] = array(
		'row'    => $sdcpb_vinyl_row,
		'length' => null === $sdcpb_vinyl_length ? '' : $sdcpb_vinyl_clock( $sdcpb_vinyl_length ),
	);
}

// Sides read A, B, C, D — the order on the sleeve, not the order of typing.
ksort( $sdcpb_vinyl_sides );

$sdcpb_vinyl_count = 0;

foreach ( $sdcpb_vinyl_sides as $sdcpb_vinyl_group ) {
	$sdcpb_vinyl_count += count( $sdcpb_vinyl_group );
}

// Year, label and number in one line; empty parts disappear with the line.
$sdcpb_vinyl_meta = array_filter(
	array(
		Sdcpb_View::to_text( Sdcpb_View::resolve( 'year' ) ),
		Sdcpb_View::to_text( Sdcpb_View::resolve( 'label' ) ),
		Sdcpb_View::to_text( Sdcpb_View::resolve( 'catalog' ) ),
	),
	'strlen'
);
?>
<article class="sdc-vinyl">
	<div class="sdc-vinyl__head">
		<?php if ( sdcpb_has( 'cover' ) ) : ?>
			<figure class="sdc-vinyl__cover">
				<?php sdcpb_image( 'cover', 'medium', array( 'class' => 'sdc-vinyl__image' ) ); ?>
			</figure>
		<?php endif; ?>

		<div class="sdc-vinyl__meta">
			<?php if ( sdcpb_has( 'artist' ) ) : ?>
				<p class="sdc-vinyl__artist"><?php sdcpb_text( 'artist' ); ?></p>
			<?php endif; ?>

			<h2 class="sdc-vinyl__album">
				<?php
				if ( sdcpb_has( 'album' ) ) {
					sdcpb_text( 'album' );
				} elseif ( sdcpb_has( 'artist' ) ) {
					sdcpb_text( 'artist' );
				}
				?>
			</h2>

			<?php if ( $sdcpb_vinyl_meta ) : ?>
				<p class="sdc-vinyl__line"><?php echo esc_html( implode( ' · ', $sdcpb_vinyl_meta ) ); ?></p>
			<?php endif; ?>

			<?php if ( $sdcpb_vinyl_count > 0 ) : ?>
				<p class="sdc-vinyl__line sdc-vinyl__count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of tracks. */
							_n( '%d track', '%d tracks', $sdcpb_vinyl_count, 'phoenixseo-page-builder' ),
							$sdcpb_vinyl_count
						)
					);

					/*
					 * Only a complete sum is a sum. If one track has no length,
					 * the total of the others is a number that looks right and
					 * is not, so it stays off the page.
					 */
					if ( $sdcpb_vinyl_known === $sdcpb_vinyl_count ) {
						echo esc_html( ' · ' . $sdcpb_vinyl_clock( $sdcpb_vinyl_total ) );
					}
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $sdcpb_vinyl_count > 0 ) : ?>
		<div class="sdc-vinyl__sides">
			<?php foreach ( $sdcpb_vinyl_sides as $sdcpb_vinyl_side => $sdcpb_vinyl_group ) : ?>
				<section class="sdc-vinyl__side">
					<h3 class="sdc-vinyl__side-title">
						<?php
						/* translators: %s: side of the record, e.g. A. */
						printf( esc_html__( 'Page %s', 'phoenixseo-page-builder' ), esc_html( $sdcpb_vinyl_side ) );
						?>
					</h3>

					<ol class="sdc-vinyl__tracks">
						<?php foreach ( $sdcpb_vinyl_group as $sdcpb_vinyl_track ) : ?>
							<li class="sdc-vinyl__track">
								<span class="sdc-vinyl__track-title">
									<?php sdcpb_text( 'title', $sdcpb_vinyl_track['row'] ); ?>
								</span>

								<?php if ( '' !== $sdcpb_vinyl_track['length'] ) : ?>
									<span class="sdc-vinyl__track-time"><?php echo esc_html( $sdcpb_vinyl_track['length'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</article>
