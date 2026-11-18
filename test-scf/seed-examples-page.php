<?php
/**
 * Seed the demo page that shows every shipped component.
 *
 * Run inside the wp-env CLI container:
 *
 *   docker cp test-scf/seed-examples-page.php wp-env-test-scf-e54e6b03-cli-1:/tmp/
 *   docker exec wp-env-test-scf-e54e6b03-cli-1 \
 *       wp eval-file /tmp/seed-examples-page.php --path=/var/www/html --allow-root
 *
 * The page has two jobs: it is the clickable demonstration for the dev site,
 * and it is what `test-scf/screenshots.py` photographs. Because of the second
 * job the blocks sit in a known order and each one renders into an element with
 * a stable class (`sdc-hero`, `sdc-vinyl`, …) — the screenshot tool clips by
 * that class instead of guessing pixel offsets.
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
 * preview/img/ in the repo holds the same files. The page is written in German
 * because the readme screenshots are taken from it and the plugin is German
 * first; the component markup itself is language-neutral.
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

/* ---- hero -------------------------------------------------------------- */

$paragraph(
	'Zehn Komponenten, jede ein eigener Block. Die Felder stehen in der Seitenleiste, ' .
	'die Ausgabe in einer <code>render.php</code>, die Styles in einer CSS-Datei — ' .
	'alles im Backend änderbar, ohne FTP und ohne Code-Deploy.'
);

$component(
	'hero',
	array(
		'heading' => 'Seiten bauen, ohne im Theme zu wühlen',
		'subline' => 'Jede Komponente bringt ihre Felder, ihre Ausgabe und ihre Styles mit. Was ein Redakteur sieht, ist das, was die Seite ausliefert — dasselbe Markup, keine Vorschau-Attrappe.',
		'cta'     => array( 'title' => 'Komponenten ansehen', 'url' => '#beispiele', 'target' => '' ),
	)
);

/* ---- feature-grid ------------------------------------------------------ */

$heading( 2, 'Was die Komponenten unterscheidet' );
$paragraph(
	'Vier Felder, vier Symbole: die Auswahl im Backend ist eine Liste, kein Freitext — ' .
	'ein Tippfehler kann kein Symbol erzeugen, das es nicht gibt.'
);

$component(
	'feature-grid',
	array(
		'heading' => 'Gebaut für Redaktionen',
		'intro'   => 'Keine Shortcodes, kein HTML im Editorfeld. Jede Komponente ist ein Block mit Feldern, die man ohne Anleitung ausfüllt.',
		'items'   => array(
			array(
				'icon'  => 'check',
				'title' => 'Felder statt Markup',
				'text'  => 'Der Editor kennt die Struktur. Verschachtelte Listen, Wiederholungen und Bilder werden als Felder gepflegt, nicht als HTML.',
				'link'  => array( 'title' => '', 'url' => '', 'target' => '' ),
			),
			array(
				'icon'  => 'bolt',
				'title' => 'Eine Ausgabe für alle',
				'text'  => 'Frontend und Editor-Vorschau rufen dieselbe PHP-Datei auf. Was im Editor steht, ist das, was ausgeliefert wird.',
				'link'  => array( 'title' => '', 'url' => '', 'target' => '' ),
			),
			array(
				'icon'  => 'shield',
				'title' => 'Keine Alternativen nötig',
				'text'  => 'Alle Texte entkommen, alle Ausgaben sind escaped. Fehlt eine Ansicht, wird das gemeldet statt stumm eine leere Fläche zu rendern.',
				'link'  => array( 'title' => '', 'url' => '', 'target' => '' ),
			),
			array(
				'icon'  => 'chart',
				'title' => 'Semantisch von Anfang an',
				'text'  => 'Definitionslisten für Fakten, details/summary für Staffeln, meter für Bewertungen — bedienbar per Tastatur, ohne JavaScript.',
				'link'  => array( 'title' => '', 'url' => '', 'target' => '' ),
			),
		),
	)
);

/* ---- stats ------------------------------------------------------------- */

$heading( 2, 'Zahlen' );
$paragraph(
	'Drei Felder pro Zeile: Wert, Beschriftung, Fußnote. Die Zahlen hier sind Beispieldaten — ' .
	'die Komponente rechnet nicht, sie stellt dar.'
);

$component(
	'stats',
	array(
		'heading' => 'In Zahlen',
		'items'   => array(
			array( 'value' => '10', 'label' => 'Komponenten', 'note' => 'jede mit eigenen Feldern' ),
			array( 'value' => '0', 'label' => 'Plugins nötig', 'note' => 'ein Host-Plugin für die Felder' ),
			array( 'value' => '3', 'label' => 'Reiter', 'note' => 'Inhalt, Ansicht, Styles' ),
			array( 'value' => '1', 'label' => 'Ausgabedatei', 'note' => 'für Editor und Frontend' ),
		),
	)
);

/* ---- pricing-table ----------------------------------------------------- */

$heading( 2, 'Preise' );
$paragraph(
	'Die mittlere Spalte ist hervorgehoben, weil ein Feld sie hervorhebt — kein Sonderfall im Theme. ' .
	'Die Merkmale sind eine Liste pro Tarif, die Hervorhebung ein Schalter.'
);

$component(
	'pricing-table',
	array(
		'heading' => 'Tarife',
		'intro'   => 'Ein Beispiel mit drei Spalten. Die Preise sind erfunden.',
		'plans'   => array(
			array(
				'name'        => 'Einzelperson',
				'price'       => '9 €',
				'period'      => 'pro Monat',
				'description' => 'Für eine Seite und eine Person.',
				'features'    => array(
					array( 'text' => 'Eine Website' ),
					array( 'text' => 'Alle Komponenten' ),
					array( 'text' => 'E-Mail-Support' ),
				),
				'link'        => array( 'title' => 'Auswählen', 'url' => '#', 'target' => '' ),
				'highlight'   => false,
			),
			array(
				'name'        => 'Team',
				'price'       => '29 €',
				'period'      => 'pro Monat',
				'description' => 'Für Redaktionen, die zu mehreren arbeiten.',
				'features'    => array(
					array( 'text' => 'Fünf Websites' ),
					array( 'text' => 'Alle Komponenten' ),
					array( 'text' => 'Eigene Komponenten' ),
					array( 'text' => 'Support am selben Tag' ),
				),
				'link'        => array( 'title' => 'Auswählen', 'url' => '#', 'target' => '' ),
				'highlight'   => true,
			),
			array(
				'name'        => 'Agentur',
				'price'       => '99 €',
				'period'      => 'pro Monat',
				'description' => 'Für Betreuung mehrerer Kundenseiten.',
				'features'    => array(
					array( 'text' => 'Unbegrenzt Websites' ),
					array( 'text' => 'White-Label' ),
					array( 'text' => 'Eigene Komponenten' ),
					array( 'text' => 'Priorisierter Support' ),
				),
				'link'        => array( 'title' => 'Auswählen', 'url' => '#', 'target' => '' ),
				'highlight'   => false,
			),
		),
	)
);

/* ---- testimonials ----------------------------------------------------- */

$heading( 2, 'Stimmen' );
$paragraph(
	'Zitat, Name, Rolle, optional ein Bild. Ohne Bild bleibt die Zeile schmal statt eine leere Fläche zu zeigen. ' .
	'Die Personen sind erfunden — das ist Beispieldaten, kein Kundenversprechen.'
);

$component(
	'testimonials',
	array(
		'heading' => 'Wie es sich anfühlt',
		'items'   => array(
			array(
				'quote' => 'Ich habe drei Seiten neu gebaut, ohne einmal eine Datei anzufassen. Das ist der Unterschied, den ich gemeint habe.',
				'name'  => 'Marlene Wiese',
				'role'  => 'Redaktionsleitung, Beispielverlag',
				'photo' => '',
			),
			array(
				'quote' => 'Die Vorschau stimmt mit dem Frontend überein. Vorher habe ich nach jeder Änderung nachgeladen, um zu prüfen, ob es noch steht.',
				'name'  => 'Jonas Kreft',
				'role'  => 'Webentwicklung, Beispielagentur',
				'photo' => '',
			),
			array(
				'quote' => 'Neue Komponenten sind eine PHP-Datei und ein Feldkatalog. Das konnten wir nach einer Woche selbst.',
				'name'  => 'Aylin Demir',
				'role'  => 'Technische Leitung, Beispiel GmbH',
				'photo' => '',
			),
		),
	)
);

/* ---- cta-band ---------------------------------------------------------- */

$heading( 2, 'Handlungsaufruf' );
$paragraph(
	'Ein Band über die volle Breite, mit zwei Links und drei Tönen: Marke, dunkel, hell. ' .
	'Der zweite Link ist optional und fällt weg, wenn er leer ist.'
);

$component(
	'cta-band',
	array(
		'eyebrow'   => 'Selbst ansehen',
		'heading'   => 'Die Komponenten liegen im Plugin',
		'text'      => 'Wer das Plugin installiert hat, kann die Beispiele hier nachbauen — Feld für Feld, ohne eigene Datei.',
		'primary'   => array( 'title' => 'Zum Plugin', 'url' => '#', 'target' => '' ),
		'secondary' => array( 'title' => 'Fragen?', 'url' => '#', 'target' => '' ),
		'tone'      => 'dark',
	)
);

/* ---- faq --------------------------------------------------------------- */

$heading( 2, 'Häufige Fragen' );
$paragraph(
	'Ein Akkordeon aus <code>&lt;details&gt;</code>-Elementen: zugeklappt, durchsuchbar, ' .
	'ohne JavaScript bedienbar — und offen, wenn der Browser Text findet.'
);

$component(
	'faq',
	array(
		'heading' => 'Fragen zur Komponente',
		'items'   => array(
			array(
				'q' => 'Brauche ich ACF Pro?',
				'a' => 'Nein. Die Felder laufen mit Advanced Custom Fields (frei) und mit Secure Custom Fields. Beide werden erkannt, keins ist Pflicht für die Ausgabe.',
			),
			array(
				'q' => 'Was passiert mit bestehenden Seiten bei einem Update?',
				'a' => 'Die Daten bleiben, wo sie sind. Ändert eine Version den Namen eines gespeicherten Schlüssels, zieht eine Migration die Inhalte nach — automatisch und zusätzlich über ein WP-CLI-Kommando mit Trockenlauf.',
			),
			array(
				'q' => 'Kann ich eigene Komponenten anlegen?',
				'a' => 'Ja: ein Ordner mit definition.json, render.php und component.css. Die Komponente erscheint danach in der Komponenten-Liste und im Inserter.',
			),
			array(
				'q' => 'Funktioniert das ohne JavaScript im Frontend?',
				'a' => 'Ja. Die Ausgabe ist HTML und CSS; Akkordeons sind details/summary, Bewertungen ein meter-Element. JavaScript braucht nur der Editor.',
			),
		),
	)
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
	array( "I Want You (She's So Heavy)", '7:47' ),
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
$types    = preg_match_all( '/wp:sdcpb\/([a-z-]+)/', $content, $matches );

echo 'Post-ID:   ' . $page_id . "\n";
echo 'URL:      ' . get_permalink( $page_id ) . "\n";
echo 'Blöcke:   ' . substr_count( $content, '<!-- wp:' ) . "\n";
echo 'Typen:    ' . $types . ' — ' . implode( ', ', array_unique( $matches[1] ) ) . "\n";
echo 'gerendert: ' . strlen( $rendered ) . " Zeichen\n";
echo 'Komponenten im HTML: ' . preg_match_all( '/class="sdc-[a-z-]+/', $rendered ) . "\n";
