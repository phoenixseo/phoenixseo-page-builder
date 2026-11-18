<?php
/**
 * The move from the short `sdc` prefix to `sdcpb` in stored data.
 *
 * Declarations were renamed in one commit; the data they had already written
 * keeps the old names. These tests pin what a site with existing pages needs:
 * the page still renders, the meta is readable under the new key, and a second
 * run changes nothing.
 *
 * @package SdcPageBuilder
 */

/**
 * Class RenameTest
 */
class RenameTest extends Sdcpb_TestCase {

	/**
	 * Directories this test created, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $tmp_dirs = array();

	/**
	 * Start from "not migrated yet".
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Sdcpb_Rename::OPTION );
	}

	/**
	 * Remove the temporary component directories.
	 */
	public function tear_down() {
		foreach ( $this->tmp_dirs as $dir ) {
			$this->remove_tree( $dir );
		}
		$this->tmp_dirs = array();

		parent::tear_down();
	}

	/**
	 * Delete a directory and everything in it.
	 *
	 * @param string $dir Absolute path.
	 */
	private function remove_tree( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) ) {
				$this->remove_tree( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
	}

	/**
	 * A component directory outside the plugin, registered through the filter.
	 *
	 * @param string $type Component type, also the directory name.
	 * @param string $key  Field group key to write into definition.json.
	 * @return string Directory holding the component.
	 */
	private function component_dir( $type, $key ) {
		$dir              = sys_get_temp_dir() . '/sdcpb-rename-' . wp_generate_password( 8, false );
		$this->tmp_dirs[] = $dir;

		mkdir( $dir . '/' . $type, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture.
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
			$dir . '/' . $type . '/definition.json',
			wp_json_encode(
				array(
					'key'    => $key,
					'title'  => 'Alt',
					'fields' => array(),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		);

		add_filter(
			'sdcpb_component_dirs',
			static function () use ( $dir ) {
				return array( $dir );
			}
		);

		Sdcpb_Schema::flush();

		return $dir;
	}

	/**
	 * The document moves to the new meta key and stays readable.
	 *
	 * This is the case that would silently empty a site: `Sdcpb_Store` reads
	 * `_sdcpb_data` only, so a page whose components still sit in `_sdc_data`
	 * renders nothing at all.
	 */
	public function test_document_moves_to_the_new_meta_key() {
		$post_id = $this->make_page();
		$data    = array( 'components' => array( $this->hero_component() ) );

		update_post_meta( $post_id, Sdcpb_Rename::LEGACY_DATA, $data );

		Sdcpb_Rename::run();

		$this->assertSame( $data, get_post_meta( $post_id, Sdcpb_Rename::DATA, true ) );
		$this->assertSame( '', get_post_meta( $post_id, Sdcpb_Rename::LEGACY_DATA, true ) );
	}

	/**
	 * The migration marker moves too.
	 *
	 * It records that a post was already converted from the old document to
	 * blocks. Losing it would make `Sdcpb_Migration` convert the page a second
	 * time and duplicate every component.
	 */
	public function test_migration_marker_moves() {
		$post_id = $this->make_page();

		update_post_meta( $post_id, Sdcpb_Rename::LEGACY_MARKER, '1' );
		Sdcpb_Rename::run();

		$this->assertSame( '1', get_post_meta( $post_id, Sdcpb_Rename::MARKER, true ) );
		$this->assertSame( '', get_post_meta( $post_id, Sdcpb_Rename::LEGACY_MARKER, true ) );
	}

	/**
	 * A page that already carries both keys keeps the new one.
	 *
	 * That is the state a page reaches after it has been saved once since the
	 * update. A plain `UPDATE` would leave two rows for the same key, and
	 * `get_post_meta( …, true )` would return whichever the database hands over
	 * first — a page that renders one thing now and another thing after a
	 * rerun.
	 */
	public function test_new_value_wins_where_both_keys_exist() {
		$post_id = $this->make_page();

		update_post_meta( $post_id, Sdcpb_Rename::LEGACY_DATA, array( 'components' => array( $this->faq_component() ) ) );
		update_post_meta( $post_id, Sdcpb_Rename::DATA, array( 'components' => array( $this->hero_component() ) ) );

		Sdcpb_Rename::run();

		$rows = get_post_meta( $post_id, Sdcpb_Rename::DATA, false );

		$this->assertCount( 1, $rows, 'the stale old row was left behind as a second row for the new key' );
		$this->assertSame( 'hero', $rows[0]['components'][0]['type'] );
	}

	/**
	 * Block delimiters in `post_content` are rewritten.
	 *
	 * The block is registered as `sdcpb/<type>` now. A page that was never
	 * reopened in the editor still carries `wp:sdc/<type>` — WordPress renders
	 * an unregistered block as nothing, so the component would vanish from the
	 * frontend.
	 */
	public function test_block_delimiters_are_rewritten() {
		$post_id = $this->make_page(
			array(
				'post_content' => "<!-- wp:sdc/hero {\"data\":{}} -->\n<!-- /wp:sdc/hero -->\n\n<!-- wp:sdc/faq -->\n<!-- /wp:sdc/faq -->",
			)
		);

		Sdcpb_Rename::run();

		$content = get_post_field( 'post_content', $post_id, 'raw' );

		$this->assertStringNotContainsString( 'wp:sdc/', $content );
		$this->assertStringContainsString( 'wp:sdcpb/hero', $content );
		$this->assertStringContainsString( '<!-- /wp:sdcpb/faq -->', $content );
	}

	/**
	 * The rewrite reaches revisions, which carry their own copy of the content.
	 *
	 * A restored revision would otherwise bring the old delimiter back.
	 */
	public function test_revisions_are_rewritten() {
		$post_id = $this->make_page( array( 'post_content' => '<!-- wp:sdc/hero --><!-- /wp:sdc/hero -->' ) );

		$revision = wp_save_post_revision( $post_id );

		$this->assertIsInt( $revision );

		Sdcpb_Rename::run();

		$this->assertStringContainsString(
			'wp:sdcpb/hero',
			get_post_field( 'post_content', $revision, 'raw' )
		);
	}

	/**
	 * Content that never held a component delimiter is left alone.
	 *
	 * `REPLACE()` would report every row as changed if the `WHERE` clause were
	 * missing, and the count is what the report shows.
	 */
	public function test_plain_content_is_not_touched() {
		$prose = "<!-- wp:paragraph -->\n<p>Nichts zu tun.</p>\n<!-- /wp:paragraph -->";
		$id    = $this->make_page( array( 'post_content' => $prose ) );

		$report = Sdcpb_Rename::run();

		$this->assertSame( $prose, get_post_field( 'post_content', $id, 'raw' ) );
		$this->assertSame( 0, $report['content'] );
	}

	/**
	 * A second run finds nothing to move.
	 *
	 * The pass runs from `init`, so on a site that fires `init` often it has to
	 * be free the second time — and it must not re-encode files that are
	 * already correct.
	 */
	public function test_second_run_changes_nothing() {
		$post_id = $this->make_page( array( 'post_content' => '<!-- wp:sdc/hero -->' ) );

		update_post_meta( $post_id, Sdcpb_Rename::LEGACY_DATA, array( 'components' => array() ) );

		$first  = Sdcpb_Rename::run();
		$second = Sdcpb_Rename::run();

		$this->assertSame( 1, $first['data'] );
		$this->assertSame( 0, $second['data'] );
		$this->assertSame( 0, $second['content'] );
		$this->assertFalse( Sdcpb_Rename::needs_run() );
	}

	/**
	 * The completed pass is recorded, so `init` stops asking.
	 */
	public function test_run_marks_itself_done() {
		$this->assertTrue( Sdcpb_Rename::needs_run() );

		Sdcpb_Rename::run();

		$this->assertFalse( Sdcpb_Rename::needs_run() );
		$this->assertSame( Sdcpb_Rename::VERSION, (int) get_option( Sdcpb_Rename::OPTION ) );
	}

	/**
	 * A failed database statement does not mark the pass done.
	 *
	 * Otherwise one broken statement would leave a site half-migrated with
	 * nothing left that ever retries it.
	 */
	public function test_a_failed_statement_leaves_the_pass_pending() {
		global $wpdb;

		$post_id = $this->make_page( array( 'post_content' => '<!-- wp:sdc/hero -->' ) );
		update_post_meta( $post_id, Sdcpb_Rename::LEGACY_DATA, array( 'components' => array() ) );

		// A read-only connection is not available here, so the statement is
		// broken by pointing the table names at nothing. `get_option()` keeps
		// working, which is what the assertion below reads.
		$real = $wpdb->postmeta;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test: makes the statement fail on purpose.
		$wpdb->postmeta = 'sdcpb_no_such_table';
		$wpdb->suppress_errors( true );

		$report = Sdcpb_Rename::run();

		$wpdb->suppress_errors( false );
		$wpdb->postmeta = $real;

		$this->assertFalse( $report['ok'] );
		$this->assertTrue( Sdcpb_Rename::needs_run() );
	}

	/**
	 * The field group key inside a component definition is renamed.
	 *
	 * The registry only accepts `group_sdcpb_component_<type>`. A component
	 * whose file still says `group_sdc_component_<type>` loads under its old
	 * type name, so it disappears from the inserter.
	 */
	public function test_field_group_key_in_a_component_file_is_renamed() {
		$dir = $this->component_dir( 'alt', Sdcpb_Rename::LEGACY_GROUP . 'alt' );

		$report = Sdcpb_Rename::run();

		$json = json_decode( (string) file_get_contents( $dir . '/alt/definition.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the fixture back.

		$this->assertSame( Sdcpb_Rename::GROUP . 'alt', $json['key'] );
		$this->assertSame( 1, $report['files'] );
		$this->assertSame( array(), $report['unwritable'] );
	}

	/**
	 * A correct file is left byte-for-byte alone.
	 *
	 * A pass that rewrote every definition on each run would lose a component
	 * that its owner edited by hand the moment the JSON round-trip differs.
	 */
	public function test_a_correct_file_is_not_rewritten() {
		$dir    = $this->component_dir( 'alt', Sdcpb_Rename::GROUP . 'alt' );
		$file   = $dir . '/alt/definition.json';
		$before = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the fixture back.
		$mtime  = filemtime( $file );

		$report = Sdcpb_Rename::run();

		clearstatcache();

		$this->assertSame( $before, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the fixture back.
		$this->assertSame( $mtime, filemtime( $file ) );
		$this->assertSame( 0, $report['files'] );
	}

	/**
	 * A component file whose key is something else entirely is not touched.
	 */
	public function test_foreign_key_is_not_rewritten() {
		$dir = $this->component_dir( 'alt', 'group_other_thing' );

		$report = Sdcpb_Rename::run();

		$json = json_decode( (string) file_get_contents( $dir . '/alt/definition.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the fixture back.

		$this->assertSame( 'group_other_thing', $json['key'] );
		$this->assertSame( 0, $report['files'] );
	}

	/**
	 * The plugin's own components are already on the new key.
	 *
	 * If one shipped definition still carried the old key, the migration would
	 * have to write inside the plugin directory — which a read-only deploy
	 * forbids — and the component would be missing from the inserter for
	 * everyone instead of only for the site that owned it.
	 */
	public function test_shipped_definitions_carry_the_new_key() {
		$legacy = 0;

		foreach ( (array) glob( SDCPB_DIR . 'schemas/*/definition.json' ) as $file ) {
			$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a shipped definition.
			if ( false !== strpos( $raw, Sdcpb_Rename::LEGACY_GROUP ) ) {
				++$legacy;
			}
		}

		$this->assertSame( 0, $legacy, 'a shipped definition still carries group_sdc_component_' );
	}
}
