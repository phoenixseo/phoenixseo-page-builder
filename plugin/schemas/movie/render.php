<?php
/**
 * Movie view: a poster, the facts a film page is asked for, and the cast.
 *
 * This is the example for two elements that no field type can produce:
 *
 * - The facts are a `<dl>`, not a stack of labels. "Regie — Kubrick" is a
 *   term and its value, and that is what the element says; a screen reader
 *   reads the pair, a grid of divs would read a list of words.
 * - The rating is a `<meter>`, the element the HTML standard has for a value
 *   inside a known range. It carries `min`, `max` and the value, so a reader
 *   announces "8,4 von 10" instead of reading a number without context.
 *
 * Both are skipped when empty. A film with no rating gets no empty meter, and
 * a `<dl>` with no `<dt>` in it is not printed at all — an empty definition
 * list is valid markup and embarrassing content.
 *
 * Runtime is stored as a plain number of minutes on purpose. "1 Std. 57 Min."
 * is a rendering, not data: sorted, added up or compared, the string is
 * useless, and every second film would be entered in a different notation.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

// Without a title there is no film to describe — a poster and a cast list
// alone would leave the reader without the one word the component is for.
if ( ! sdcpb_has( 'title' ) ) {
	return;
}

$sdcpb_movie_runtime = '';

if ( is_numeric( Sdcpb_View::resolve( 'runtime' ) ) ) {
	$sdcpb_movie_minutes = (int) Sdcpb_View::resolve( 'runtime' );

	if ( $sdcpb_movie_minutes > 0 ) {
		$sdcpb_movie_runtime = $sdcpb_movie_minutes >= 60
			/* translators: 1: hours, 2: minutes. */
			? sprintf( __( '%1$dh %2$dm', 'phoenixseo-page-builder' ), intdiv( $sdcpb_movie_minutes, 60 ), $sdcpb_movie_minutes % 60 )
			/* translators: %d: minutes. */
			: sprintf( __( '%d min', 'phoenixseo-page-builder' ), $sdcpb_movie_minutes );
	}
}

$sdcpb_movie_rating = null;

if ( is_numeric( Sdcpb_View::resolve( 'rating' ) ) ) {
	$sdcpb_movie_value = (float) Sdcpb_View::resolve( 'rating' );

	// Out of range is not a rating. Clamping it to 10 would invent a verdict.
	if ( $sdcpb_movie_value >= 0 && $sdcpb_movie_value <= 10 ) {
		$sdcpb_movie_rating = $sdcpb_movie_value;
	}
}

// 8, not 8,0 — but 8,4 keeps its decimal.
$sdcpb_movie_score = '';

if ( null !== $sdcpb_movie_rating ) {
	$sdcpb_movie_score = floor( $sdcpb_movie_rating ) === $sdcpb_movie_rating
		? number_format( $sdcpb_movie_rating, 0, ',', '' )
		: number_format( $sdcpb_movie_rating, 1, ',', '' );
}

$sdcpb_movie_facts = array(
	/* translators: Term in a component's fact list. */
	'director' => array( __( 'Director', 'phoenixseo-page-builder' ), Sdcpb_View::to_text( Sdcpb_View::resolve( 'director' ) ) ),
	/* translators: Term in a component's fact list. */
	'genre'    => array( __( 'Genre', 'phoenixseo-page-builder' ), Sdcpb_View::to_text( Sdcpb_View::resolve( 'genre' ) ) ),
	/* translators: Term in a component's fact list. */
	'runtime'  => array( __( 'Runtime', 'phoenixseo-page-builder' ), $sdcpb_movie_runtime ),
);

$sdcpb_movie_id = wp_unique_id( 'sdc-movie-' );
?>
<article class="sdc-movie" aria-labelledby="<?php echo esc_attr( $sdcpb_movie_id ); ?>">
	<?php if ( sdcpb_has( 'poster' ) ) : ?>
		<figure class="sdc-movie__poster">
			<?php sdcpb_image( 'poster', 'medium', array( 'class' => 'sdc-movie__image' ) ); ?>
		</figure>
	<?php endif; ?>

	<div class="sdc-movie__body">
		<h2 class="sdc-movie__title" id="<?php echo esc_attr( $sdcpb_movie_id ); ?>">
			<?php
			if ( sdcpb_has( 'title' ) ) {
				sdcpb_text( 'title' );
			}

			if ( sdcpb_has( 'year' ) ) {
				echo ' <span class="sdc-movie__year">(' . esc_html( Sdcpb_View::to_text( Sdcpb_View::resolve( 'year' ) ) ) . ')</span>';
			}
			?>
		</h2>

		<?php if ( sdcpb_has( 'original_title' ) ) : ?>
			<p class="sdc-movie__original">
				<?php
				/* translators: %s: original title of the component. */
				printf( esc_html__( 'Original title: %s', 'phoenixseo-page-builder' ), esc_html( Sdcpb_View::to_text( Sdcpb_View::resolve( 'original_title' ) ) ) );
				?>
			</p>
		<?php endif; ?>

		<?php if ( array_filter( wp_list_pluck( $sdcpb_movie_facts, 1 ), 'strlen' ) || null !== $sdcpb_movie_rating ) : ?>
			<dl class="sdc-movie__facts">
				<?php foreach ( $sdcpb_movie_facts as $sdcpb_movie_fact ) : ?>
					<?php if ( '' !== $sdcpb_movie_fact[1] ) : ?>
						<div class="sdc-movie__fact">
							<dt class="sdc-movie__term"><?php echo esc_html( $sdcpb_movie_fact[0] ); ?></dt>
							<dd class="sdc-movie__value"><?php echo esc_html( $sdcpb_movie_fact[1] ); ?></dd>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>

				<?php if ( null !== $sdcpb_movie_rating ) : ?>
					<div class="sdc-movie__fact">
						<?php /* translators: Term in a component's fact list. */ ?>
						<dt class="sdc-movie__term"><?php esc_html_e( 'Rating', 'phoenixseo-page-builder' ); ?></dt>
						<dd class="sdc-movie__value sdc-movie__rating">
							<?php
							/*
							 * low/high/optimum decide the colour of the bar, the
							 * value decides its length. The number next to it is
							 * hidden from readers because the meter announces
							 * itself: two voices saying "8,4" is one too many.
							 */
							printf(
								'<meter class="sdc-movie__meter" min="0" max="10" low="4" high="7" optimum="10" value="%s">%s</meter><span class="sdc-movie__score" aria-hidden="true">%s</span>',
								esc_attr( (string) $sdcpb_movie_rating ),
								esc_html(
									/* translators: 1: score, 2: maximum. */
									sprintf( __( '%1$s by %2$s', 'phoenixseo-page-builder' ), $sdcpb_movie_score, '10' )
								),
								esc_html( $sdcpb_movie_score . ' / 10' )
							);
							?>
						</dd>
					</div>
				<?php endif; ?>
			</dl>
		<?php endif; ?>

		<?php if ( sdcpb_has( 'synopsis' ) ) : ?>
			<p class="sdc-movie__synopsis"><?php sdcpb_text( 'synopsis' ); ?></p>
		<?php endif; ?>

		<?php if ( sdcpb_has( 'cast' ) ) : ?>
			<div class="sdc-movie__cast">
				<?php /* translators: Heading above the cast list. */ ?>
				<h3 class="sdc-movie__cast-title"><?php esc_html_e( 'Cast', 'phoenixseo-page-builder' ); ?></h3>

				<ul class="sdc-movie__cast-list">
					<?php
					sdcpb_each(
						'cast',
						static function ( $row ) {
							if ( ! sdcpb_has( 'actor', $row ) ) {
								return;
							}

							echo '<li class="sdc-movie__person">';
							echo '<span class="sdc-movie__actor">';
							sdcpb_text( 'actor', $row );
							echo '</span>';

							if ( sdcpb_has( 'role', $row ) ) {
								echo '<span class="sdc-movie__role">';
								/* translators: %s: name of the character. */
								printf( esc_html__( 'as %s', 'phoenixseo-page-builder' ), esc_html( Sdcpb_View::to_text( Sdcpb_View::resolve( 'role', $row ) ) ) );
								echo '</span>';
							}

							echo '</li>';
						}
					);
					?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( sdcpb_has( 'link' ) ) : ?>
			<p class="sdc-movie__actions">
				<?php sdcpb_link( 'link', 'sdc-movie__button' ); ?>
			</p>
		<?php endif; ?>
	</div>
</article>
