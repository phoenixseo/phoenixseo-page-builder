<?php
/**
 * I18n: the textdomain is real, not just declared.
 *
 * @package SdcPageBuilder
 */

/**
 * A `Text Domain:` header alone translates nothing. These tests assert the
 * domain is actually loaded, at the right moment (WordPress 6.7 warns when it
 * is touched before `init`), and that the POT file covers the strings.
 */
class I18nTest extends Sdcpb_TestCase {

	/**
	 * The plugin declares a text domain and a domain path.
	 */
	public function test_plugin_header_declares_text_domain_and_path() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( dirname( __DIR__, 2 ) . '/phoenixseo-page-builder.php', false, false );

		$this->assertSame( 'phoenixseo-page-builder', $data['TextDomain'] );
		$this->assertSame( '/languages', $data['DomainPath'] );
	}

	/**
	 * The plugin does not load its own textdomain.
	 *
	 * WordPress loads it from `wp-content/languages/plugins/` just in time, and
	 * the plugin review asks for `load_plugin_textdomain()` to be gone: on a
	 * wordpress.org-hosted plugin it only loads the same catalog a second time.
	 * The header test above pins the header this depends on.
	 */
	public function test_plugin_does_not_load_its_own_textdomain() {
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/phoenixseo-page-builder.php' );

		// The call with the argument, not the bare name: the comment where the
		// call used to be names the function in prose, and that prose is what
		// keeps the next reader from adding it back.
		$this->assertStringNotContainsString(
			"load_plugin_textdomain( 'phoenixseo-page-builder' )",
			$source,
			'load_plugin_textdomain() is back in the plugin file — wordpress.org loads the domain itself'
		);
	}

	/**
	 * The textdomain is not touched before `init`.
	 *
	 * WordPress 6.7+ logs "translation loading … triggered too early" when it
	 * is. This asserts the negative directly: at `plugins_loaded`, where the
	 * plugin boots, the domain is still untouched.
	 */
	public function test_textdomain_is_not_loaded_before_init() {
		$this->assertFalse(
			is_textdomain_loaded( 'phoenixseo-page-builder' ),
			'The textdomain was loaded before init — WordPress 6.7+ logs a notice for that'
		);
	}

	/**
	 * The source strings are English, and the POT proves it.
	 *
	 * This is the regression gate for the conversion itself: the plugin used to
	 * carry German source strings, which meant an English site rendered German.
	 * A translation cannot fix that — the source language *is* the fallback. So
	 * the check runs on the POT rather than on the shipped catalog: any German
	 * character in a `msgid` means a source string went back to German.
	 */
	public function test_source_strings_carry_no_german_characters() {
		$pot = dirname( __DIR__, 2 ) . '/languages/phoenixseo-page-builder.pot';
		$ids = self::pot_entries( (string) file_get_contents( $pot ) );

		$german = array();
		foreach ( $ids as $entry ) {
			foreach ( array( $entry['msgid'], $entry['plural'] ) as $string ) {
				if ( '' !== $string && preg_match( '/[äöüßÄÖÜ]/u', $string ) ) {
					$german[] = $string;
				}
			}
		}

		$this->assertGreaterThan( 100, count( $ids ), 'The POT holds almost no entries — the parser broke, not the strings' );
		$this->assertSame(
			array(),
			$german,
			'Source strings are German again — English is the fallback language: ' . wp_json_encode( $german, JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * The shipped German catalog translates a real string.
	 *
	 * `de_DE.po`/`.mo` are what makes a German site German. Testing the loading
	 * path with a throwaway catalog (below) proves WordPress reads a `.mo`; this
	 * test proves *our* `.mo` holds the strings — a generator that silently
	 * produced an empty catalog would pass the other one.
	 */
	public function test_shipped_german_catalog_translates_a_real_string() {
		$mo_file = dirname( __DIR__, 2 ) . '/languages/phoenixseo-page-builder-de_DE.mo';
		$this->assertFileExists( $mo_file, 'languages/phoenixseo-page-builder-de_DE.mo is missing — run test-scf/make-po.py' );

		unload_textdomain( 'phoenixseo-page-builder' );
		$loaded = load_textdomain( 'phoenixseo-page-builder', $mo_file, 'de_DE' );

		try {
			$translated = __( 'Save Component', 'phoenixseo-page-builder' );
			/* translators: %d: number of seasons; the string only exists to prove that plural forms load from the shipped catalog. */
			$plural_one = sprintf( _n( '%d season', '%d seasons', 1, 'phoenixseo-page-builder' ), 1 );
			/* translators: %d: number of seasons; same string as above with the plural form. */
			$plural_two = sprintf( _n( '%d season', '%d seasons', 3, 'phoenixseo-page-builder' ), 3 );
		} finally {
			// A leaked catalog would translate strings for every later test.
			unload_textdomain( 'phoenixseo-page-builder' );
		}

		$this->assertTrue( $loaded, 'load_textdomain() rejected the shipped catalog' );
		$this->assertSame( 'Komponente speichern', $translated );
		$this->assertSame( '1 Staffel', $plural_one, 'The singular plural form is wrong' );
		$this->assertSame( '3 Staffeln', $plural_two, 'The plural form is wrong' );
	}

	/**
	 * A compiled catalog does get picked up — proven with a throwaway one, so
	 * the loading path is tested rather than assumed.
	 */
	public function test_a_compiled_catalog_is_used_when_present() {
		$lang_dir = dirname( __DIR__, 2 ) . '/languages';
		$mo_file  = $lang_dir . '/sdc-probe-de_DE.mo';

		if ( ! class_exists( 'MO' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}
		$mo = new MO();
		$mo->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'Save Component',
					'translations' => array( 'PROBE-OK' ),
				)
			)
		);
		$this->assertTrue( $mo->export_to_file( $mo_file ) );

		unload_textdomain( 'phoenixseo-page-builder' );
		$loaded = load_textdomain( 'phoenixseo-page-builder', $mo_file, 'de_DE' );

		try {
			$result = __( 'Save Component', 'phoenixseo-page-builder' );
		} finally {
			// Leaving the probe catalog loaded would translate this string for
			// every test that runs after this one.
			unload_textdomain( 'phoenixseo-page-builder' );
		}

		wp_delete_file( $mo_file );

		$this->assertTrue( $loaded, 'load_textdomain() did not accept the catalog' );
		$this->assertSame( 'PROBE-OK', $result, 'The translated string was not used' );
	}

	/**
	 * The POT file exists, names the domain, and is not a hand-written stub.
	 */
	public function test_pot_file_declares_the_domain() {
		$pot = dirname( __DIR__, 2 ) . '/languages/phoenixseo-page-builder.pot';
		$this->assertFileExists( $pot, 'languages/phoenixseo-page-builder.pot is missing' );

		$contents = (string) file_get_contents( $pot );
		$this->assertStringContainsString( 'X-Domain: phoenixseo-page-builder', $contents );

		// Placeholder left by the generator would be a broken report link.
		$this->assertStringNotContainsString( 'wordpress.org/support/plugin/plugin', $contents );
	}

	/**
	 * Every translatable string in the shipped source has a POT entry.
	 *
	 * Before this test the POT was checked for two hand-picked strings, which
	 * is why it could fall 164 strings behind while the suite stayed green: the
	 * two strings it looked for were still there, the rest of the plugin was
	 * not. Both directions are checked — a string without an entry, and an
	 * entry no string produces — because a stale POT is exactly as wrong as a
	 * short one.
	 */
	public function test_pot_covers_every_source_string_and_carries_no_orphans() {
		$pot  = dirname( __DIR__, 2 ) . '/languages/phoenixseo-page-builder.pot';
		$ids  = self::pot_entries( (string) file_get_contents( $pot ) );
		$have = array();

		foreach ( $ids as $entry ) {
			$have[ $entry['msgid'] ] = true;
			if ( '' !== $entry['plural'] ) {
				$have[ $entry['plural'] ] = true;
			}
		}

		$missing = array();
		$seen    = array();
		foreach ( self::translatable_sources() as $file ) {
			foreach ( self::strings_in( $file ) as $string ) {
				$seen[ $string ] = basename( $file );
				if ( ! isset( $have[ $string ] ) ) {
					$missing[ $string ] = basename( $file );
				}
			}
		}

		// A regex that matches nothing would make both comparisons below pass
		// while checking nothing. The plugin has well over a hundred shipped
		// strings; anything far below that means the scanner broke, not that
		// the POT got better.
		$this->assertGreaterThan( 100, count( $seen ), 'The source scanner found almost no strings — the pattern is broken, not the POT' );

		$this->assertSame(
			array(),
			$missing,
			'Translatable strings with no POT entry: ' . wp_json_encode( $missing, JSON_UNESCAPED_UNICODE )
		);

		// Entries from the plugin header are generated from the file comment
		// block, not from a gettext call, so they are not in `$seen`.
		$orphans = array();
		foreach ( $ids as $entry ) {
			$from_header = false;
			foreach ( $entry['refs'] as $ref ) {
				if ( ! preg_match( '/:\d+$/', $ref ) ) {
					$from_header = true;
				}
			}
			if ( $from_header ) {
				continue;
			}
			foreach ( array( $entry['msgid'], $entry['plural'] ) as $string ) {
				if ( '' !== $string && ! isset( $seen[ $string ] ) ) {
					$orphans[ $string ] = $entry['refs'][0] ?? '';
				}
			}
		}

		$this->assertSame(
			array(),
			$orphans,
			'POT entries that no source string produces (regenerate the POT): ' . wp_json_encode( $orphans, JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Files whose strings are shipped and translated.
	 *
	 * `schemas/<typ>/render.php` belongs here: a component's labels are shown on
	 * the front end like any other string, and leaving them out let the POT fall
	 * 18 strings behind the eight example components — the suite stayed green
	 * because it never looked at them.
	 *
	 * @return string[]
	 */
	private static function translatable_sources() {
		$base    = dirname( __DIR__, 2 );
		$files   = glob( $base . '/includes/*.php' );
		$files   = array_merge( $files, glob( $base . '/assets/*.js' ) );
		$files   = array_merge( $files, glob( $base . '/schemas/*/render.php' ) );
		$files[] = $base . '/phoenixseo-page-builder.php';

		return array_values( array_filter( $files, 'is_file' ) );
	}

	/**
	 * Translatable literals inside a PHP or JS source file.
	 *
	 * Only the literal forms the plugin actually uses are recognised: a
	 * single-quoted first argument, for `__`, `_e`, the escaping variants,
	 * `_x`/`_ex`, and `_n`/`_nx` (whose plural is the second argument). A
	 * string built at runtime cannot be translated and is not expected here.
	 *
	 * @param string $file Path.
	 * @return string[]
	 */
	private static function strings_in( $file ) {
		$source = (string) file_get_contents( $file );
		$out    = array();

		$calls = '/\b(__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_x|_ex|_n|_nx)\(\s*\'((?:\\\\.|[^\'\\\\])*)\'(?:\s*,\s*\'((?:\\\\.|[^\'\\\\])*)\')?/';

		if ( preg_match_all( $calls, $source, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$out[] = self::unescape_php( $match[2] );

				// For `_n`/`_nx` the plural is a separate translatable string.
				if ( in_array( $match[1], array( '_n', '_nx' ), true ) && isset( $match[3] ) && '' !== $match[3] ) {
					$out[] = self::unescape_php( $match[3] );
				}
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Undo the escapes a single-quoted PHP/JS literal carries.
	 *
	 * @param string $literal Raw literal body.
	 * @return string
	 */
	private static function unescape_php( $literal ) {
		return str_replace( array( '\\\\', '\\\'' ), array( '\\', "'" ), $literal );
	}

	/**
	 * Parse a PO file into msgid/plural/refs entries.
	 *
	 * @param string $contents PO file.
	 * @return array[] Each with `msgid`, `plural` and `refs`.
	 */
	private static function pot_entries( $contents ) {
		$entries = array();
		$current = array(
			'msgid'  => '',
			'plural' => '',
			'refs'   => array(),
		);
		$field   = null;

		foreach ( preg_split( '/\r\n|\n|\r/', $contents ) as $line ) {
			if ( '' === trim( $line ) ) {
				if ( '' !== $current['msgid'] ) {
					$entries[] = $current;
				}
				$current = array(
					'msgid'  => '',
					'plural' => '',
					'refs'   => array(),
				);
				$field   = null;
				continue;
			}

			if ( 0 === strpos( $line, '#:' ) ) {
				foreach ( preg_split( '/\s+/', trim( substr( $line, 2 ) ) ) as $ref ) {
					if ( '' !== $ref ) {
						$current['refs'][] = $ref;
					}
				}
				continue;
			}
			if ( preg_match( '/^msgid "(.*)"$/', $line, $m ) ) {
				$current['msgid'] = self::unescape_po( $m[1] );
				$field            = 'msgid';
				continue;
			}
			if ( preg_match( '/^msgid_plural "(.*)"$/', $line, $m ) ) {
				$current['plural'] = self::unescape_po( $m[1] );
				$field             = 'plural';
				continue;
			}

			/*
			 * Everything after `msgstr` belongs to the translation. Without
			 * this the header's continuation lines (`"Project-Id-Version: …"`)
			 * were appended to the *msgid* of the header entry, which then
			 * looked like one giant untranslatable string.
			 */
			if ( 0 === strpos( $line, 'msgstr' ) ) {
				$field = null;
				continue;
			}
			// A continuation line continues whichever field was last opened.
			if ( preg_match( '/^"(.*)"$/', $line, $m ) && null !== $field ) {
				$current[ $field ] .= self::unescape_po( $m[1] );
			}
		}

		if ( '' !== $current['msgid'] ) {
			$entries[] = $current;
		}

		return $entries;
	}

	/**
	 * Undo PO escapes.
	 *
	 * @param string $value Escaped value.
	 * @return string
	 */
	private static function unescape_po( $value ) {
		return str_replace(
			array( '\\n', '\	', '\\"', '\\\\' ),
			array( "\n", chr( 9 ), '"', '\\' ),
			$value
		);
	}

	/**
	 * Every translatable string in the plugin uses the plugin's domain.
	 */
	public function test_all_translation_calls_use_the_plugin_domain() {
		$files   = glob( dirname( __DIR__, 2 ) . '/includes/*.php' );
		$files[] = dirname( __DIR__, 2 ) . '/phoenixseo-page-builder.php';

		$wrong = array();
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			if ( preg_match_all( '/(?:__|_e|esc_html__|esc_attr__|_x|_n)\(\s*[^,]+,\s*[\'"]([^\'"]+)[\'"]/', $source, $m ) ) {
				foreach ( $m[1] as $domain ) {
					// `_n()` puts the domain third; the pattern above catches the
					// singular and plural string, so only real differences count.
					if ( 'phoenixseo-page-builder' !== $domain && false === strpos( $domain, ' ' ) ) {
						$wrong[ $domain ] = basename( $file );
					}
				}
			}
		}

		$this->assertSame( array(), $wrong, 'Translatable strings with a foreign text domain: ' . wp_json_encode( $wrong ) );
	}
}
