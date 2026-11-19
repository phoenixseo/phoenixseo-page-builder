<?php
/**
 * The examples that ship with the plugin, rendered.
 *
 * Every other test in the suite checks a mechanism: the lookup order, the
 * block, the helpers. None of them runs the `render.php` files that a user
 * sees first, so a broken example would ship green — a typo in a template is
 * a fatal error on a client's page and nothing in the suite would have said so.
 *
 * Two promises per example:
 *
 * - Empty data prints nothing. A component without values is an empty block in
 *   the canvas; a heading with no text underneath it is not.
 * - Real data prints the elements the example is *for*. A `<dl>` that quietly
 *   became a stack of divs, a `<meter>` that lost its bounds, a track total
 *   summed over half the tracks — all of that is invisible in a path check and
 *   obvious here.
 *
 * @package SdcPageBuilder
 */

/**
 * Renders the shipped examples and reads their markup.
 */
class ExampleTest extends Sdcpb_TestCase {

	/**
	 * Every shipped example with its own view.
	 *
	 * Types without `render.php` are covered by the generic renderer and are
	 * not this file's subject.
	 *
	 * @return string[]
	 */
	private function handwritten_types() {
		$types = array();

		foreach ( array_keys( Sdcpb_Schema::components() ) as $type ) {
			if ( file_exists( Sdcpb_Schema::dir() . '/' . $type . '/render.php' ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * The examples must at least exist, or the test below proves nothing.
	 */
	public function test_examples_with_a_view_are_shipped() {
		$this->assertGreaterThanOrEqual( 6, count( $this->handwritten_types() ) );
	}

	/**
	 * Empty data prints no markup at all.
	 */
	public function test_an_example_without_values_prints_nothing() {
		foreach ( $this->handwritten_types() as $type ) {
			$html = Sdcpb_Block::render( $type, array( 'data' => array() ) );

			$this->assertSame( '', $html, $type . ' printed markup without data.' );
		}
	}

	/**
	 * A record: the tracks are grouped by side, numbered by the browser, and
	 * the playing time is only summed when every track has one.
	 */
	public function test_vinyl_groups_tracks_and_sums_a_complete_total() {
		$data = array(
			'artist'  => 'The Beatles',
			'album'   => 'Abbey Road',
			'year'    => '1969',
			'label'   => 'Apple',
			'catalog' => 'PCS 7088',
			'tracks'  => array(
				array(
					'side'     => 'A',
					'title'    => 'Come Together',
					'duration' => '4:20',
				),
				array(
					'side'     => 'A',
					'title'    => 'Something',
					'duration' => '3:03',
				),
				array(
					'side'     => 'B',
					'title'    => 'Here Comes the Sun',
					'duration' => '3:05',
				),
			),
		);

		$html = Sdcpb_Block::render( 'vinyl', array( 'data' => $data ) );

		$this->assertStringContainsString( 'Page A', $html );
		$this->assertStringContainsString( 'Page B', $html );
		$this->assertStringContainsString( 'Come Together', $html );
		$this->assertStringContainsString( 'Here Comes the Sun', $html );
		$this->assertStringContainsString( '<ol class="sdc-vinyl__tracks"', $html );

		// 4:20 + 3:03 + 3:05 = 10:28.
		$this->assertStringContainsString( '3 tracks · 10:28', $html );

		// Both facts, printed once: the label line holds year, label, number.
		$this->assertStringContainsString( '1969 · Apple · PCS 7088', $html );

		// The hand-written view, not the generic markup.
		$this->assertStringNotContainsString( 'data-sdc-name', $html );
	}

	/**
	 * One unknown length and the total disappears — a sum over half the
	 * tracks is a number that looks authoritative and is wrong.
	 */
	public function test_vinyl_hides_a_partial_total() {
		$html = Sdcpb_Block::render(
			'vinyl',
			array(
				'data' => array(
					'artist' => 'Various',
					'album'  => 'Unbekannt',
					'tracks' => array(
						array(
							'side'     => 'A',
							'title'    => 'Eins',
							'duration' => '3:00',
						),
						array(
							'side'     => 'A',
							'title'    => 'Zwei',
							'duration' => '',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( '2 tracks', $html );

		// The known track still shows its own length; only the total is gone.
		$this->assertStringNotContainsString( '2 tracks · ', $html, 'A partial sum must not be printed as a total.' );
	}

	/**
	 * A track without a side lands on side A instead of vanishing.
	 */
	public function test_vinyl_defaults_a_missing_side_to_a() {
		$html = Sdcpb_Block::render(
			'vinyl',
			array(
				'data' => array(
					'album'  => 'Ohne Seite',
					'tracks' => array(
						array(
							'title'    => 'Erster',
							'duration' => '1:00',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( 'Page A', $html );
		$this->assertStringContainsString( 'Erster', $html );
	}

	/**
	 * A film: the facts are a definition list, the rating is a meter with its
	 * bounds, and the runtime is written out of a plain number.
	 */
	public function test_movie_prints_facts_cast_and_a_rating_meter() {
		$data = array(
			'title'    => 'Dr. Seltsam',
			'year'     => '1964',
			'director' => 'Stanley Kubrick',
			'genre'    => 'Satire',
			'runtime'  => 95,
			'rating'   => 8.4,
			'cast'     => array(
				array(
					'actor' => 'Peter Sellers',
					'role'  => 'Dr. Seltsam',
				),
				array(
					'actor' => 'George C. Scott',
					'role'  => 'General Turgidson',
				),
			),
			'synopsis' => 'Ein General dreht durch.',
		);

		$html = Sdcpb_Block::render( 'movie', array( 'data' => $data ) );

		$this->assertStringContainsString( '<dl class="sdc-movie__facts"', $html );
		$this->assertStringContainsString( '<dt class="sdc-movie__term">Director</dt>', $html );
		$this->assertStringContainsString( 'Stanley Kubrick', $html );

		// 95 minutes, not "95".
		$this->assertStringContainsString( '1h 35m', $html );

		// A meter without its range is a number without context.
		$this->assertStringContainsString( 'max="10"', $html );
		$this->assertStringContainsString( 'value="8.4"', $html );
		$this->assertStringContainsString( '8,4 / 10', $html );

		$this->assertStringContainsString( 'as Dr. Seltsam', $html );
		$this->assertStringContainsString( '(1964)', $html );

		$this->assertStringNotContainsString( 'data-sdc-name', $html );
	}

	/**
	 * No rating, no empty meter; no poster, no empty figure.
	 */
	public function test_movie_skips_what_it_does_not_have() {
		$html = Sdcpb_Block::render(
			'movie',
			array(
				'data' => array(
					'title'    => 'Ohne alles',
					'rating'   => 11,
					'synopsis' => 'Text.',
				),
			)
		);

		$this->assertStringContainsString( 'Ohne alles', $html );
		$this->assertStringNotContainsString( '<meter', $html, '11 is not a rating out of ten.' );
		$this->assertStringNotContainsString( '<dl', $html, 'An empty definition list must not be printed.' );
		$this->assertStringNotContainsString( '<img', $html, 'No poster, no image tag.' );
	}

	/**
	 * A series: seasons fold, an empty last year reads as "seit", and the
	 * episode total is counted over the nested rows.
	 */
	public function test_series_folds_seasons_and_counts_episodes() {
		$data = array(
			'title'      => 'Beispielserie',
			'creator'    => 'Jemand',
			'network'    => 'Ein Sender',
			'first_year' => '2019',
			'last_year'  => '',
			'rating'     => 8,
			'seasons'    => array(
				array(
					'number'   => '1',
					'year'     => '2019',
					'episodes' => array(
						array( 'title' => 'Pilot' ),
						array( 'title' => 'Zweite Folge' ),
					),
				),
				array(
					// No number: the view counts it as the second season.
					'year'     => '2020',
					'episodes' => array(
						array( 'title' => 'Dritte Folge' ),
					),
				),
			),
		);

		$html = Sdcpb_Block::render( 'series', array( 'data' => $data ) );

		$this->assertStringContainsString( '<details class="sdc-series__season">', $html );
		$this->assertStringContainsString( 'Season 1', $html );
		$this->assertStringContainsString( 'Season 2', $html );

		// Two seasons, three episodes, counted rather than maintained.
		$this->assertStringContainsString( '2 seasons, 3 episodes', $html );
		$this->assertStringContainsString( 'since 2019', $html );
		$this->assertStringContainsString( '<ol class="sdc-series__episodes"', $html );
		$this->assertStringContainsString( '8 / 10', $html );

		$this->assertStringNotContainsString( 'data-sdc-name', $html );
	}

	/**
	 * Two years print as a span, and a season the author left blank does not
	 * become "Season 5, 0 episodes".
	 */
	public function test_series_prints_a_span_and_drops_an_empty_season() {
		$html = Sdcpb_Block::render(
			'series',
			array(
				'data' => array(
					'title'      => 'Abgesetzt',
					'first_year' => '2001',
					'last_year'  => '2004',
					'seasons'    => array(
						array(
							'number'   => '1',
							'episodes' => array(
								array( 'title' => 'Eine Folge' ),
							),
						),
						array(), // A row nobody filled in.
					),
				),
			)
		);

		// An en dash, not a hyphen.
		$this->assertStringContainsString( '2001–2004', $html );
		$this->assertStringContainsString( '1 season, 1 episode', $html );
		$this->assertStringNotContainsString( 'Season 2', $html );
	}

	/**
	 * The examples that were there before still render — a change to a helper
	 * reaches all of them, and this is the only place it would show.
	 */
	public function test_the_earlier_examples_still_render() {
		$html = Sdcpb_Block::render(
			'pricing-table',
			array(
				'data' => array(
					'heading' => 'Preise',
					'plans'   => array(
						array(
							'name'      => 'Basis',
							'price'     => '19 €',
							'highlight' => false,
							'features'  => array( array( 'text' => 'Eine Seite' ) ),
						),
						array(
							'name'      => 'Profi',
							'price'     => '49 €',
							'highlight' => true,
							'features'  => array( array( 'text' => 'Alles' ) ),
						),
					),
				),
			)
		);

		$this->assertStringContainsString( 'sdc-pricing__plan--featured', $html );
		$this->assertStringContainsString( 'Eine Seite', $html );
	}
}
