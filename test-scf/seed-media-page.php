<?php
/**
 * Seed the demo page for the three media components (vinyl, movie, series).
 *
 * Run inside the wp-env CLI container:
 *
 *   docker cp test-scf/seed-media-page.php wp-env-test-scf-e54e6b03-cli-1:/tmp/
 *   docker exec wp-env-test-scf-e54e6b03-cli-1 \
 *       wp eval-file /tmp/seed-media-page.php --path=/var/www/html --allow-root
 *
 * Why this file exists a second time (test-scf/seed-demo-page.php is the older
 * one): a block's markup is not an attribute dump.
 *
 *   - Core blocks (heading, paragraph) carry their content as *inner HTML*
 *     between the comment delimiters. Passing `{"content":"..."}` makes
 *     `do_blocks()` return an empty string, and `rest_validate_value_from_schema`
 *     logs 'The "type" schema keyword for content can only be one of the
 *     built-in types' — the page renders, but only around the block.
 *   - SDC blocks are dynamic (`render_callback`), so they carry no inner HTML
 *     and are self-closing: `<!-- wp:sdcpb/vinyl {"data":{...}} /-->`.
 *
 * Images come from wp-content/uploads/sdc-demo/{vinyl,movie,series}.jpg;
 * preview/img/ in the repo holds the same files.
 */

$uploads = home_url( '/wp-content/uploads/sdc-demo/' );

$blocks = array();

/**
 * A heading block. `$html` is trusted markup, not user input.
 */
$heading = function ( $level, $html ) use ( &$blocks ) {
	$blocks[] = sprintf(
		'<!-- wp:heading {"level":%d} --><h%d class="wp-block-heading">%s</h%d><!-- /wp:heading -->',
		$level,
		$level,
		$html,
		$level
	);
};

$paragraph = function ( $html ) use ( &$blocks ) {
	$blocks[] = '<!-- wp:paragraph --><p>' . $html . '</p><!-- /wp:paragraph -->';
};

/**
 * A dynamic SDC block: no inner HTML, data as the single attribute.
 */
$component = function ( $type, $data ) use ( &$blocks ) {
	$blocks[] = sprintf(
		'<!-- wp:sdcpb/%s %s /-->',
		$type,
		wp_json_encode( array( 'data' => $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
};

$heading( 1, 'Drei Medien-Komponenten' );
$paragraph(
	'Schallplatte, Film und Serie — drei Beispiele, die zeigen, was ein eigener ' .
	'<code>render.php</code> leistet. Die Inhalte sind Beispieldaten, die Struktur ist die Komponente.'
);

/* ---- vinyl ------------------------------------------------------------- */

$heading( 2, 'Schallplatte' );
$paragraph(
	'Tracknummer und Gesamtspielzeit sind keine Felder: die Nummer kommt aus der Reihenfolge, ' .
	'die Summe wird gerechnet — und nur ausgegeben, wenn jede Länge lesbar ist.'
);

$tracks_a = array(
	array( 'Come Together', '4:20' ),
	array( 'Something', '3:03' ),
	array( "Maxwell's Silver Hammer", '3:27' ),
	array( "Oh! Darling", '3:26' ),
	array( "Octopus's Garden", '2:51' ),
	array( 'I Want You (She\'s So Heavy)', '7:47' ),
);
$tracks_b = array(
	array( 'Here Comes the Sun', '3:05' ),
	array( 'Because', '2:45' ),
	array( 'You Never Give Me Your Money', '4:02' ),
	array( 'Sun King', '2:26' ),
	array( 'Mean Mr. Mustard', '1:06' ),
	array( 'Polythene Pam', '1:12' ),
	array( 'She Came In Through the Bathroom Window', '1:57' ),
	array( 'Golden Slumbers', '1:31' ),
	array( 'Carry That Weight', '1:36' ),
	array( 'The End', '2:19' ),
	array( 'Her Majesty', '0:23' ),
);

$tracks = array();
foreach ( array( 'A' => $tracks_a, 'B' => $tracks_b ) as $side => $rows ) {
	foreach ( $rows as $row ) {
		$tracks[] = array( 'side' => $side, 'title' => $row[0], 'duration' => $row[1] );
	}
}

$component(
	'vinyl',
	array(
		'cover'   => $uploads . 'vinyl.jpg',
		'artist'  => 'The Beatles',
		'album'   => 'Abbey Road',
		'year'    => 1969,
		'label'   => 'Apple',
		'catalog' => 'PCS 7088',
		'tracks'  => $tracks,
	)
);

/* ---- movie ------------------------------------------------------------- */

$heading( 2, 'Film' );
$paragraph(
	'Die Fakten stehen in einer Definitionsliste, die Bewertung ist ein <code>&lt;meter&gt;</code> ' .
	'mit Grenzen, und die Laufzeit wird aus einer Minutenzahl geschrieben. Leere Felder fallen weg, ' .
	'statt leere Zeilen zu hinterlassen.'
);

$component(
	'movie',
	array(
		'poster'         => $uploads . 'movie.jpg',
		'title'          => 'Dr. Seltsam oder: Wie ich lernte, die Bombe zu lieben',
		'original_title' => 'Dr. Strangelove or: How I Learned to Stop Worrying and Love the Bomb',
		'year'           => 1964,
		'director'       => 'Stanley Kubrick',
		'genre'          => 'Satire',
		'runtime'        => 95,
		'rating'         => 8.4,
		'cast'           => array(
			array( 'actor' => 'Peter Sellers', 'role' => 'Dr. Seltsam / Präsident Merkin Muffley / Lionel Mandrake' ),
			array( 'actor' => 'George C. Scott', 'role' => 'General Buck Turgidson' ),
			array( 'actor' => 'Sterling Hayden', 'role' => 'General Jack D. Ripper' ),
			array( 'actor' => 'Slim Pickens', 'role' => 'Major T. J. Kong' ),
		),
		'synopsis'       => 'Ein General sperrt die Basis ab und befiehlt den Erstschlag; im War Room ringen Präsident, Militär und ein versehrter Wissenschaftler um die letzten Stunden. Kubricks Komödie über die Bombe ist eine über die Männer, die sie verwalten.',
		'link'           => array( 'title' => 'Auf IMDb ansehen', 'url' => 'https://www.imdb.com/title/tt0057012/', 'target' => '_blank' ),
	)
);

/* ---- series ------------------------------------------------------------ */

$heading( 2, 'Serie' );
$paragraph(
	'Staffeln sind <code>&lt;details&gt;</code> — zugeklappt, per Tastatur bedienbar, ohne JavaScript. ' .
	'Staffelnummern zählt der Browser, die Folgenzahl wird summiert. Figur und Inhalte dieses Beispiels sind erfunden.'
);

$component(
	'series',
	array(
		'poster'         => $uploads . 'series.jpg',
		'title'          => 'Nordlicht',
		'original_title' => 'Northern Light',
		'first_year'     => 2019,
		'last_year'      => '',
		'creator'        => 'Mira Halbach',
		'network'        => 'Nordfilm',
		'genre'          => 'Drama',
		'rating'         => 8.1,
		'synopsis'       => 'Eine Kommissarin kehrt in das Dorf zurück, in dem sie aufgewachsen ist, und findet einen Fall, den niemand dort gelöst haben will.',
		'seasons'        => array(
			array(
				'number'   => 1,
				'year'     => 2019,
				'episodes' => array(
					array( 'title' => 'Ankunft' ),
					array( 'title' => 'Das Bootshaus' ),
					array( 'title' => 'Zwei Zeugen' ),
					array( 'title' => 'Frost' ),
					array( 'title' => 'Die Schleuse' ),
					array( 'title' => 'Ablaufdatum' ),
				),
			),
			array(
				'number'   => 2,
				'year'     => 2021,
				'episodes' => array(
					array( 'title' => 'Rückkehr' ),
					array( 'title' => 'Der Deich' ),
					array( 'title' => 'Sturmflut' ),
					array( 'title' => 'Niedrigwasser' ),
				),
			),
			array(
				'number'   => 3,
				'year'     => 2023,
				'episodes' => array(
					array( 'title' => 'Neumond' ),
					array( 'title' => 'Letzte Fähre' ),
				),
			),
		),
		'link'           => array( 'title' => 'Alle Staffeln', 'url' => '#', 'target' => '' ),
	)
);

$content = implode( "\n\n", $blocks );

// get_page_by_title() is deprecated since 6.2 — query by title instead.
$existing = get_posts(
	array(
		'post_type'   => 'page',
		'title'       => 'SDC Beispiele',
		'post_status' => 'any',
		'numberposts' => 1,
	)
);

$postarr = array(
	'post_title'   => 'SDC Beispiele',
	'post_content' => $content,
	'post_status'  => 'publish',
	'post_type'    => 'page',
);

if ( $existing ) {
	$postarr['ID'] = $existing[0]->ID;
	$page_id       = wp_update_post( $postarr );
} else {
	$page_id = wp_insert_post( $postarr );
}

if ( is_wp_error( $page_id ) || ! $page_id ) {
	echo "Fehler beim Anlegen.\n";
	exit( 1 );
}

$rendered = do_blocks( (string) get_post_field( 'post_content', $page_id ) );

echo 'Post-ID:   ' . $page_id . "\n";
echo 'URL:      ' . get_permalink( $page_id ) . "\n";
echo 'Blöcke:   ' . substr_count( $content, '<!-- wp:' ) . "\n";
echo 'gerendert: ' . strlen( $rendered ) . " Zeichen\n";
echo 'Komponenten im HTML: ' . preg_match_all( '/sdc-(vinyl|movie|series)\b/', $rendered ) . "\n";
