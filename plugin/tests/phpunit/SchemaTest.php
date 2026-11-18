<?php
/**
 * Component types, as declared by the JSON schemas only.
 *
 * @package SdcPageBuilder
 */

/**
 * The universality claim: a component type is a JSON file, nothing else. The
 * second type (`hero`) exists to prove it — if any test here needs code
 * outside `schemas/`, the claim is false.
 */
class SchemaTest extends Sdcpb_TestCase {

	/**
	 * Both shipped types are discovered from the schema directory.
	 */
	public function test_shipped_types_are_discovered() {
		$components = Sdcpb_Schema::components();

		$this->assertArrayHasKey( 'faq', $components );
		$this->assertArrayHasKey( 'hero', $components );
	}

	/**
	 * Every shipped type can be placed as a block.
	 *
	 * A block name is `namespace/name` with lowercase letters, digits and
	 * dashes. A type with an underscore in it — `feature_grid` — is a valid
	 * component type but not a block name: WordPress refuses the registration
	 * with a `_doing_it_wrong()` notice and the component never reaches the
	 * inserter. This keeps one from shipping again.
	 */
	public function test_every_shipped_type_is_a_block_name() {
		$types = array_keys( Sdcpb_Schema::components() );

		$this->assertNotEmpty( $types );

		foreach ( $types as $type ) {
			$this->assertMatchesRegularExpression(
				'/^[a-z0-9][a-z0-9-]*$/',
				(string) $type,
				'A component type doubles as a block name, so no underscore: ' . $type
			);
		}

		// Nothing is silently skipped at registration time.
		$this->assertSame( array(), array_values( array_diff( $types, Sdcpb_Block::types() ) ) );
	}

	/**
	 * A descriptor carries what the editor needs to render a component card.
	 */
	public function test_descriptor_carries_label_icon_and_fields() {
		$faq = Sdcpb_Schema::get( 'faq' );

		$this->assertSame( 'faq', $faq['type'] );
		$this->assertSame( 'group_sdcpb_component_faq', $faq['key'] );
		$this->assertSame( 'FAQ', $faq['label'] );
		$this->assertNotEmpty( $faq['icon'] );
		$this->assertNotEmpty( $faq['fields'] );
	}

	/**
	 * Fields are normalized to the flat shape the editor's JS consumes.
	 *
	 * Nesting is not in the descriptor: `normalize_field()` returns one row per
	 * top-level field, and the row structure comes from the rendered markup
	 * (see RendererTest).
	 */
	public function test_fields_are_normalized_to_a_flat_shape() {
		$fields = Sdcpb_Schema::fields( 'faq' );
		$names  = array_column( $fields, 'name' );

		$this->assertSame( array( 'heading', 'items' ), $names );

		foreach ( $fields as $field ) {
			$this->assertSame(
				array( 'key', 'name', 'label', 'type', 'required' ),
				array_keys( $field ),
				'Unexpected descriptor shape for field ' . $field['name']
			);
		}

		$this->assertSame( 'repeater', $fields[1]['type'] );
		$this->assertSame( 'field_sdc_faq_items', $fields[1]['key'] );
	}

	/**
	 * `version_string()` is what gets stored per component instance.
	 */
	public function test_version_string_is_type_at_version() {
		$this->assertSame( 'faq@1', Sdcpb_Schema::version_string( 'faq' ) );
		$this->assertSame( 'hero@1', Sdcpb_Schema::version_string( 'hero' ) );
	}

	/**
	 * An unknown type is null, not an error — the renderer relies on that.
	 */
	public function test_unknown_type_returns_null() {
		$this->assertNull( Sdcpb_Schema::get( 'gibt-es-nicht' ) );
		$this->assertSame( array(), Sdcpb_Schema::fields( 'gibt-es-nicht' ) );
		$this->assertSame( 'gibt-es-nicht@1', Sdcpb_Schema::version_string( 'gibt-es-nicht' ) );
	}

	/**
	 * The hero type is link + textarea + text — three field types the FAQ type
	 * does not use, which is what makes it a real second case.
	 */
	public function test_hero_uses_field_types_the_faq_type_does_not() {
		$hero = array_column( Sdcpb_Schema::fields( 'hero' ), 'type', 'name' );
		$faq  = array_column( Sdcpb_Schema::fields( 'faq' ), 'type', 'name' );

		$this->assertSame( 'link', $hero['cta'] );
		$this->assertSame( 'textarea', $hero['subline'] );
		$this->assertNotContains( 'link', $faq );
	}

	/**
	 * One directory per component: the host's loader reads one level only.
	 *
	 * ACF's `scan_files()` skips sub directories, so a component in
	 * `sdc-components/hero/` is invisible unless that sub directory is
	 * registered as its own path. This asserts the expansion.
	 */
	public function test_json_paths_include_each_component_subdirectory() {
		$base = untrailingslashit( get_temp_dir() ) . '/sdc-paths-' . wp_generate_password( 8, false );
		$sub  = $base . '/' . Sdcpb_Schema::USER_DIR . '/hero';

		$this->assertTrue( mkdir( $sub, 0777, true ) );
		file_put_contents( $sub . '/definition.json', '{"key":"group_sdcpb_component_hero"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		add_filter(
			'sdcpb_component_dirs',
			function () use ( $base ) {
				return array( $base . '/' . Sdcpb_Schema::USER_DIR );
			}
		);

		$paths = Sdcpb_Schema::json_paths();

		remove_all_filters( 'sdcpb_component_dirs' );

		$this->assertContains( $base . '/' . Sdcpb_Schema::USER_DIR, $paths );
		$this->assertContains( $sub, $paths );
		$this->assertContains( Sdcpb_Schema::dir(), $paths );

		unlink( $sub . '/definition.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
		rmdir( $sub );
		rmdir( $base . '/' . Sdcpb_Schema::USER_DIR );
		rmdir( $base );
	}

	/**
	 * A directory that does not exist is not offered to the host.
	 */
	public function test_missing_component_dirs_are_ignored() {
		add_filter(
			'sdcpb_component_dirs',
			function () {
				return array( '/nonexistent/sdc-components' );
			}
		);

		$this->assertSame( array(), Sdcpb_Schema::user_dirs() );

		remove_all_filters( 'sdcpb_component_dirs' );
	}

	/**
	 * The view lookup ends where the JSON lookup ends: at the plugin.
	 *
	 * One list for definitions, views and stylesheets. A lookup takes the
	 * first hit, so the plugin has to come last — a component of the same type
	 * in a theme or in `wp-content/` still overrides the shipped one, but a
	 * shipped component now finds its own `render.php` instead of falling back
	 * to the generic markup.
	 */
	public function test_lookup_dirs_end_with_the_plugin_directory() {
		$base = untrailingslashit( get_temp_dir() ) . '/sdc-lookup-' . wp_generate_password( 8, false );

		$this->assertTrue( mkdir( $base, 0777, true ) );

		add_filter(
			'sdcpb_component_dirs',
			function () use ( $base ) {
				return array( $base );
			}
		);

		$dirs = Sdcpb_Schema::lookup_dirs();

		remove_all_filters( 'sdcpb_component_dirs' );
		rmdir( $base );

		$this->assertSame( array( $base, untrailingslashit( Sdcpb_Schema::dir() ) ), $dirs );
	}

	/**
	 * A `render.php` that ships with the plugin is found.
	 *
	 * This is the case that made the shipped examples render as generic
	 * markup: the file sat next to its `definition.json` in the plugin and the
	 * lookup never looked there.
	 */
	public function test_template_falls_back_to_the_plugin_directory() {
		$type = 'sdc-tmp-' . strtolower( wp_generate_password( 8, false ) );
		$dir  = Sdcpb_Schema::dir() . $type;

		$this->assertTrue( mkdir( $dir, 0777, true ) );

		try {
			file_put_contents( $dir . '/render.php', '<?php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

			$this->assertSame( $dir . '/render.php', Sdcpb_Schema::template( $type ) );
		} finally {
			unlink( $dir . '/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			rmdir( $dir );
		}
	}

	/**
	 * A theme view of the same type still wins over the shipped one.
	 */
	public function test_a_theme_template_overrides_the_plugin_template() {
		$type = 'sdc-tmp-' . strtolower( wp_generate_password( 8, false ) );
		$base = untrailingslashit( get_temp_dir() ) . '/sdc-tpl-' . wp_generate_password( 8, false );
		$ours = $base . '/' . $type;

		$this->assertTrue( mkdir( Sdcpb_Schema::dir() . $type, 0777, true ) );
		$this->assertTrue( mkdir( $ours, 0777, true ) );

		add_filter(
			'sdcpb_component_dirs',
			function () use ( $base ) {
				return array( $base );
			}
		);

		try {
			file_put_contents( Sdcpb_Schema::dir() . $type . '/render.php', '<?php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
			file_put_contents( $ours . '/render.php', '<?php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

			$this->assertSame( $ours . '/render.php', Sdcpb_Schema::template( $type ) );
		} finally {
			remove_all_filters( 'sdcpb_component_dirs' );
			unlink( Sdcpb_Schema::dir() . $type . '/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			unlink( $ours . '/render.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			rmdir( Sdcpb_Schema::dir() . $type );
			rmdir( $ours );
			rmdir( $base );
		}
	}

	/**
	 * A component keeps the file name it was loaded from.
	 *
	 * The host names JSON files after the group key. Left alone, saving
	 * `definition.json` from the admin would rename it to
	 * `group_sdcpb_component_hero.json` and leave a stale copy behind.
	 */
	public function test_saving_a_component_keeps_its_file_name() {
		$file = Sdcpb_Schema::dir() . 'hero/definition.json';
		$post = array( 'key' => Sdcpb_Schema::KEY_PREFIX . 'hero' );

		$this->assertSame( 'definition.json', Sdcpb_Schema::save_file_name( 'group_sdcpb_component_hero.json', $post, $file ) );
	}

	/**
	 * The file a component lives in is found by its key, not its name.
	 *
	 * Every component directory holds a `definition.json`, so the name alone
	 * cannot say which component a file belongs to.
	 */
	public function test_file_for_key_finds_the_defining_file() {
		$this->assertSame( Sdcpb_Schema::dir() . 'hero/definition.json', Sdcpb_Schema::file_for_key( 'group_sdcpb_component_hero' ) );
		$this->assertSame( Sdcpb_Schema::dir() . 'faq/definition.json', Sdcpb_Schema::file_for_key( 'group_sdcpb_component_faq' ) );
		$this->assertSame( '', Sdcpb_Schema::file_for_key( 'group_sdcpb_component_does_not_exist' ) );
		$this->assertSame( '', Sdcpb_Schema::file_for_key( 'group_other_plugin' ) );
	}

	/**
	 * A component's edit lands in its own directory, not in `acf-json/`.
	 *
	 * Leaving the host's save path in place would create a second file
	 * defining the same key — the loader would then see the component twice.
	 */
	public function test_save_paths_point_at_the_component_directory() {
		$paths = Sdcpb_Schema::save_paths( array( '/tmp/acf-json' ), array( 'key' => 'group_sdcpb_component_hero' ) );

		$this->assertSame( array( Sdcpb_Schema::dir() . 'hero' ), $paths );

		$untouched = Sdcpb_Schema::save_paths( array( '/tmp/acf-json' ), array( 'key' => 'group_other_plugin' ) );
		$this->assertSame( array( '/tmp/acf-json' ), $untouched );
	}

	/**
	 * Only files in the plugin's own component directories are claimed.
	 *
	 * A field group from another plugin's `acf-json/` belongs to the host's
	 * default naming; renaming it would break that plugin's own layout.
	 */
	public function test_save_file_name_leaves_foreign_groups_alone() {
		$proposed = 'group_sdcpb_component_hero.json';

		$this->assertSame(
			$proposed,
			Sdcpb_Schema::save_file_name( $proposed, array( 'key' => Sdcpb_Schema::KEY_PREFIX . 'hero' ), '/some/other/plugin/acf-json/' . $proposed )
		);
		$this->assertSame(
			'group_sdc_other.json',
			Sdcpb_Schema::save_file_name( 'group_sdc_other.json', array( 'key' => 'group_sdc_other' ), Sdcpb_Schema::dir() . 'el.json' )
		);
		$this->assertFalse( Sdcpb_Schema::owns_file( '' ) );
	}
}
