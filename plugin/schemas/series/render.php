<?php
/**
 * Series view: seasons and their episodes, folded away until somebody asks.
 *
 * This is the example for a list whose length nobody controls, and for three
 * derived values that must not be fields:
 *
 * - **Season and episode numbers.** Both come from a nested `<ol>`, so the
 *   browser counts. A season added in the middle renumbers itself; a number
 *   typed by hand would still say "Staffel 2" above the third one.
 * - **The episode total.** Summed over the nested rows. It is the one number
 *   a series page is always asked for, and the one that goes stale first when
 *   it is maintained by hand.
 * - **The year span.** "2019–2023" and "seit 2019" are the same two facts told
 *   differently — the second one is what an empty `last_year` means.
 *
 * Each season is a `<details>`: with nine seasons and 24 episodes each, an
 * open page is 200 lines of episode titles and nobody reads it. `<details>`
 * folds without a script, is keyboard operable and findable by the browser's
 * own search.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

// A series is its title. Without one, the seasons below it would be a table
// of contents with no book.
if ( ! sdcpb_has( 'title' ) ) {
	return;
}

$sdcpb_series_seasons = array();
$sdcpb_series_total   = 0;

foreach ( (array) Sdcpb_View::resolve( 'seasons' ) as $sdcpb_series_index => $sdcpb_series_season ) {
	if ( ! is_array( $sdcpb_series_season ) ) {
		continue;
	}

	$sdcpb_series_episodes = array();

	foreach ( (array) Sdcpb_View::resolve( 'episodes', $sdcpb_series_season ) as $sdcpb_series_episode ) {
		if ( is_array( $sdcpb_series_episode ) && '' !== trim( Sdcpb_View::to_text( Sdcpb_View::resolve( 'title', $sdcpb_series_episode ) ) ) ) {
			$sdcpb_series_episodes[] = $sdcpb_series_episode;
		}
	}

	// A season with neither a number nor a single episode is an empty row the
	// author left behind; printing "Staffel 5, 0 Folgen" would look like data.
	$sdcpb_series_number = Sdcpb_View::to_text( Sdcpb_View::resolve( 'number', $sdcpb_series_season ) );

	if ( '' === $sdcpb_series_number && ! $sdcpb_series_episodes ) {
		continue;
	}

	$sdcpb_series_total += count( $sdcpb_series_episodes );

	$sdcpb_series_seasons[] = array(
		'number'   => '' === $sdcpb_series_number ? ( $sdcpb_series_index + 1 ) : $sdcpb_series_number,
		'year'     => Sdcpb_View::to_text( Sdcpb_View::resolve( 'year', $sdcpb_series_season ) ),
		'episodes' => $sdcpb_series_episodes,
	);
}

$sdcpb_series_first = Sdcpb_View::to_text( Sdcpb_View::resolve( 'first_year' ) );
$sdcpb_series_last  = Sdcpb_View::to_text( Sdcpb_View::resolve( 'last_year' ) );
$sdcpb_series_years = '';

if ( '' !== $sdcpb_series_first && '' !== $sdcpb_series_last && $sdcpb_series_first !== $sdcpb_series_last ) {
	// An en dash, not a hyphen: a span, not a compound word.
	$sdcpb_series_years = $sdcpb_series_first . '–' . $sdcpb_series_last;
} elseif ( '' !== $sdcpb_series_first && '' === $sdcpb_series_last ) {
	/* translators: %s: first year of the series. */
	$sdcpb_series_years = sprintf( __( 'since %s', 'sdc-page-builder' ), $sdcpb_series_first );
} else {
	$sdcpb_series_years = $sdcpb_series_first;
}

$sdcpb_series_run = array( $sdcpb_series_years );

/*
 * Both numbers pluralise, and both are counters the author does not type: a
 * series with one season and one episode must not read "1 Staffeln, 1 Folgen".
 */
if ( $sdcpb_series_seasons ) {
	$sdcpb_series_run[] = sprintf(
		/* translators: 1: number of seasons, 2: number of episodes. */
		__( '%1$s, %2$s', 'sdc-page-builder' ),
		sprintf(
			/* translators: %d: number of seasons. */
			_n( '%d season', '%d seasons', count( $sdcpb_series_seasons ), 'sdc-page-builder' ),
			count( $sdcpb_series_seasons )
		),
		sprintf(
			/* translators: %d: number of episodes. */
			_n( '%d episode', '%d episodes', $sdcpb_series_total, 'sdc-page-builder' ),
			$sdcpb_series_total
		)
	);
}

$sdcpb_series_facts = array(
	/* translators: Term in a component's fact list. */
	'creator' => array( __( 'Idea', 'sdc-page-builder' ), Sdcpb_View::to_text( Sdcpb_View::resolve( 'creator' ) ) ),
	/* translators: Term in a component's fact list. */
	'network' => array( __( 'Network', 'sdc-page-builder' ), Sdcpb_View::to_text( Sdcpb_View::resolve( 'network' ) ) ),
	/* translators: Term in a component's fact list. */
	'genre'   => array( __( 'Genre', 'sdc-page-builder' ), Sdcpb_View::to_text( Sdcpb_View::resolve( 'genre' ) ) ),
	/* translators: Term in a component's fact list. */
	'run'     => array( __( 'Runtime', 'sdc-page-builder' ), implode( ' · ', array_filter( $sdcpb_series_run, 'strlen' ) ) ),
);

$sdcpb_series_id = wp_unique_id( 'sdc-series-' );
?>
<article class="sdc-series" aria-labelledby="<?php echo esc_attr( $sdcpb_series_id ); ?>">
	<?php if ( sdcpb_has( 'poster' ) ) : ?>
		<figure class="sdc-series__poster">
			<?php sdcpb_image( 'poster', 'medium', array( 'class' => 'sdc-series__image' ) ); ?>
		</figure>
	<?php endif; ?>

	<div class="sdc-series__body">
		<h2 class="sdc-series__title" id="<?php echo esc_attr( $sdcpb_series_id ); ?>"><?php sdcpb_text( 'title' ); ?></h2>

		<?php if ( sdcpb_has( 'original_title' ) ) : ?>
			<p class="sdc-series__original">
				<?php
				/* translators: %s: original title of the component. */
				printf( esc_html__( 'Original title: %s', 'sdc-page-builder' ), esc_html( Sdcpb_View::to_text( Sdcpb_View::resolve( 'original_title' ) ) ) );
				?>
			</p>
		<?php endif; ?>

		<dl class="sdc-series__facts">
			<?php foreach ( $sdcpb_series_facts as $sdcpb_series_fact ) : ?>
				<?php if ( '' !== $sdcpb_series_fact[1] ) : ?>
					<div class="sdc-series__fact">
						<dt class="sdc-series__term"><?php echo esc_html( $sdcpb_series_fact[0] ); ?></dt>
						<dd class="sdc-series__value"><?php echo esc_html( $sdcpb_series_fact[1] ); ?></dd>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( is_numeric( Sdcpb_View::resolve( 'rating' ) ) && (float) Sdcpb_View::resolve( 'rating' ) >= 0 && (float) Sdcpb_View::resolve( 'rating' ) <= 10 ) : ?>
				<div class="sdc-series__fact">
					<?php /* translators: Term in a component's fact list. */ ?>
					<dt class="sdc-series__term"><?php esc_html_e( 'Rating', 'sdc-page-builder' ); ?></dt>
					<dd class="sdc-series__value">
						<?php
						$sdcpb_series_score = (float) Sdcpb_View::resolve( 'rating' );

						echo esc_html(
							floor( $sdcpb_series_score ) === $sdcpb_series_score
								? number_format( $sdcpb_series_score, 0, ',', '' ) . ' / 10'
								: number_format( $sdcpb_series_score, 1, ',', '' ) . ' / 10'
						);
						?>
					</dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php if ( sdcpb_has( 'synopsis' ) ) : ?>
			<p class="sdc-series__synopsis"><?php sdcpb_text( 'synopsis' ); ?></p>
		<?php endif; ?>

		<?php if ( $sdcpb_series_seasons ) : ?>
			<div class="sdc-series__seasons">
				<h3 class="sdc-series__seasons-title">
					<?php
					/* translators: %d: number of seasons. */
					printf( esc_html( _n( '%d season', '%d seasons', count( $sdcpb_series_seasons ), 'sdc-page-builder' ) ), count( $sdcpb_series_seasons ) );
					?>
				</h3>

				<?php foreach ( $sdcpb_series_seasons as $sdcpb_series_season ) : ?>
					<details class="sdc-series__season">
						<summary class="sdc-series__summary">
							<span class="sdc-series__season-name">
								<?php
								/* translators: %s: season number. */
								printf( esc_html__( 'Season %s', 'sdc-page-builder' ), esc_html( $sdcpb_series_season['number'] ) );
								?>
							</span>

							<?php if ( '' !== $sdcpb_series_season['year'] ) : ?>
								<span class="sdc-series__season-year"><?php echo esc_html( $sdcpb_series_season['year'] ); ?></span>
							<?php endif; ?>

							<?php if ( $sdcpb_series_season['episodes'] ) : ?>
								<span class="sdc-series__season-count">
									<?php
									printf(
										/* translators: %d: number of episodes. */
										esc_html( _n( '%d episode', '%d episodes', count( $sdcpb_series_season['episodes'] ), 'sdc-page-builder' ) ),
										count( $sdcpb_series_season['episodes'] )
									);
									?>
								</span>
							<?php endif; ?>
						</summary>

						<?php if ( $sdcpb_series_season['episodes'] ) : ?>
							<ol class="sdc-series__episodes">
								<?php foreach ( $sdcpb_series_season['episodes'] as $sdcpb_series_episode ) : ?>
									<li class="sdc-series__episode">
										<?php sdcpb_text( 'title', $sdcpb_series_episode ); ?>
									</li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( sdcpb_has( 'link' ) ) : ?>
			<p class="sdc-series__actions">
				<?php sdcpb_link( 'link', 'sdc-series__button' ); ?>
			</p>
		<?php endif; ?>
	</div>
</article>
