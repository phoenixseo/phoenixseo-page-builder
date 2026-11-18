<?php
/**
 * One-time move of everything the short `sdc` prefix left in stored data.
 *
 * The prefix grew from three characters to five so it cannot collide with
 * another plugin. Declarations were renamed in the same commit; the data they
 * wrote keeps the old names, and data is not code:
 *
 * - `_sdc_data` post meta holds every page's components. Reading only
 *   `_sdcpb_data` afterwards would render those pages empty.
 * - `wp:sdc/<type>` block delimiters sit inside `post_content`. The block is
 *   registered as `sdcpb/<type>` now, so a page that was never reopened in the
 *   editor would render nothing on the frontend.
 * - `group_sdc_component_<type>` is the field group key inside each
 *   component's `definition.json`. The registry only accepts the new key, so
 *   every component would disappear from the inserter.
 *
 * All three are moved by one pass, started from `init` once and then never
 * again. The pass is idempotent — a second run finds nothing to do — so an
 * interrupted request costs nothing but a repeated run.
 *
 * `uninstall.php` deletes only the new keys. A site that uninstalls without
 * ever running the migration therefore keeps its old rows; that is the
 * intended trade, because those rows are the user's content and deleting
 * something this routine never touched would be worse than leaving it.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Moves stored data from the short prefix to the current one.
 */
class Sdcpb_Rename {

	/**
	 * Option holding the version of the pass that completed.
	 */
	const OPTION = 'sdcpb_rename_version';

	/**
	 * Version of this pass. Marked done with `>=`, so it never re-runs after a
	 * bump unless a later pass raises it.
	 */
	const VERSION = 1;

	/**
	 * Document meta key, old and new.
	 */
	const LEGACY_DATA = '_sdc_data';
	const DATA        = '_sdcpb_data';

	/**
	 * Migration marker meta key, old and new.
	 */
	const LEGACY_MARKER = '_sdc_migrated';
	const MARKER        = '_sdcpb_migrated';

	/**
	 * Block delimiter in `post_content`, old and new.
	 */
	const LEGACY_BLOCK = 'wp:sdc/';
	const BLOCK        = 'wp:sdcpb/';

	/**
	 * Field group key prefix, old and new.
	 */
	const LEGACY_GROUP = 'group_sdc_component_';
	const GROUP        = 'group_sdcpb_component_';

	/**
	 * Hook the pass.
	 */
	public static function init() {
		// After `init` has loaded the textdomain and the host field plugin has
		// registered, but before any template reads a page.
		add_action( 'init', array( __CLASS__, 'maybe_run' ), 20 );
	}

	/**
	 * Run the pass unless it already completed.
	 */
	public static function maybe_run() {
		if ( ! self::needs_run() ) {
			return;
		}

		// Tables do not exist yet while WordPress installs itself.
		if ( function_exists( 'wp_installing' ) && wp_installing() ) {
			return;
		}

		self::run();
	}

	/**
	 * Whether the pass still has to run.
	 *
	 * @return bool
	 */
	public static function needs_run() {
		return (int) get_option( self::OPTION, 0 ) < self::VERSION;
	}

	/**
	 * Move all stored data to the current prefix.
	 *
	 * Everything here is a single statement over an indexed column or over
	 * `post_content`, so the pass finishes inside one request even on a site
	 * with thousands of pages. Nothing is batched: a half-migrated site would
	 * be a site whose pages render empty until the queue drains.
	 *
	 * @return array{data:int,marker:int,content:int,files:int,unwritable:string[],ok:bool}
	 *         Rows and files changed, paths that could not be written, and
	 *         whether every database statement succeeded.
	 */
	public static function run() {
		$report = array(
			'data'       => 0,
			'marker'     => 0,
			'content'    => 0,
			'files'      => 0,
			'unwritable' => array(),
			'ok'         => true,
		);

		$report['data']    = self::move_meta( self::LEGACY_DATA, self::DATA );
		$report['marker']  = self::move_meta( self::LEGACY_MARKER, self::MARKER );
		$report['content'] = self::move_content();
		$report['files']   = self::move_files( $report['unwritable'] );

		if ( $report['data'] < 0 || $report['marker'] < 0 || $report['content'] < 0 ) {
			$report['ok'] = false;

			return $report;
		}

		update_option( self::OPTION, self::VERSION, false );

		return $report;
	}

	/**
	 * Rename one meta key.
	 *
	 * A post that already carries both keys would end up with two rows for the
	 * new key after a plain `UPDATE`, and `get_post_meta( …, true )` would then
	 * return whichever row the database hands over first. The stale old row is
	 * therefore dropped where the new one exists, which is the state a page
	 * reaches after it has been saved once since the update.
	 *
	 * @param string $from Old key.
	 * @param string $to   New key.
	 * @return int Rows changed, or -1 on a database error.
	 */
	private static function move_meta( $from, $to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time key rename; a cache would only hide the rows it changes.
		$dropped = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb.
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( SELECT post_id FROM ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ) AS already_moved )",
				$from,
				$to
			)
		);

		if ( false === $dropped ) {
			return -1;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See above.
		$moved = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb.
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				$to,
				$from
			)
		);

		return false === $moved ? -1 : (int) $moved;
	}

	/**
	 * Rewrite the block delimiters inside every post, revisions included.
	 *
	 * `REPLACE()` rather than a PHP loop: the rows that carry the delimiter are
	 * found by the database, and a page with a thousand blocks is still one
	 * write. `wp:sdc/` is not a substring of `wp:sdcpb/`, so the statement
	 * cannot match what it has already written.
	 *
	 * @return int Rows changed, or -1 on a database error.
	 */
	private static function move_content() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time content rewrite; the post cache is flushed below.
		$changed = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb.
				"UPDATE {$wpdb->posts} SET post_content = REPLACE( post_content, %s, %s ) WHERE post_content LIKE %s",
				self::LEGACY_BLOCK,
				self::BLOCK,
				'%' . $wpdb->esc_like( self::LEGACY_BLOCK ) . '%'
			)
		);

		if ( false === $changed ) {
			return -1;
		}

		if ( $changed > 0 ) {
			wp_cache_flush();
		}

		return (int) $changed;
	}

	/**
	 * Rename the field group key inside every component definition.
	 *
	 * The definitions are files, not rows: a component is a directory with a
	 * `definition.json` whose `key` says which type it holds. Some of those
	 * directories sit in a theme that a deploy owns; a file that cannot be
	 * written is collected instead of aborting the pass, so one read-only theme
	 * does not keep the meta and content moves from running.
	 *
	 * @param string[] $unwritable Collects paths that could not be written.
	 * @return int Files changed.
	 */
	private static function move_files( array &$unwritable ) {
		$changed = 0;

		if ( ! class_exists( 'Sdcpb_Schema' ) ) {
			return 0;
		}

		foreach ( Sdcpb_Schema::json_paths() as $dir ) {
			$entries = @scandir( untrailingslashit( $dir ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_scandir -- An unreadable directory must not warn.

			foreach ( (array) $entries as $entry ) {
				$entry = (string) $entry;

				if ( '.json' !== substr( $entry, -5 ) ) {
					continue;
				}

				$file = untrailingslashit( $dir ) . '/' . $entry;
				$raw  = @file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- Reading a component definition; an unreadable file must not warn.

				if ( false === $raw || false === strpos( (string) $raw, self::LEGACY_GROUP ) ) {
					continue;
				}

				$json = json_decode( (string) $raw, true );

				if ( ! is_array( $json ) || empty( $json['key'] ) || 0 !== strpos( (string) $json['key'], self::LEGACY_GROUP ) ) {
					continue;
				}

				$json['key'] = self::GROUP . substr( (string) $json['key'], strlen( self::LEGACY_GROUP ) );

				$encoded = wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

				if ( false === $encoded || ! self::write( $file, $encoded . "\n" ) ) {
					$unwritable[] = $file;
					continue;
				}

				++$changed;

				// The host names the file after the key on its own save; a file
				// that already carries the old key in its name is renamed here
				// so the folder does not end up with both.
				if ( 0 === strpos( $entry, self::LEGACY_GROUP ) ) {
					@rename( $file, untrailingslashit( $dir ) . '/' . self::GROUP . substr( $entry, strlen( self::LEGACY_GROUP ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-directory rename; a failure leaves the old name working.
				}
			}
		}

		if ( $changed > 0 ) {
			Sdcpb_Schema::flush();
		}

		return $changed;
	}

	/**
	 * Write a file through a temporary name.
	 *
	 * A half-written `definition.json` is a component that no longer loads, so
	 * the write lands next to the file and is renamed over it.
	 *
	 * @param string $file     Absolute path.
	 * @param string $contents Contents.
	 * @return bool
	 */
	private static function write( $file, $contents ) {
		$tmp  = $file . '.tmp';
		$done = file_put_contents( $tmp, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing a component file; WP_Filesystem would ask for credentials on a frontend request.

		if ( false === $done || ! @rename( $tmp, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-directory rename; a failure is reported here.
			wp_delete_file( $tmp );
			return false;
		}

		return true;
	}

	/**
	 * Move stored data to the current prefix, now or as a dry run.
	 *
	 * The same pass `init` runs on its own. It exists for the case where a site
	 * owner wants to do it deliberately — before a deploy, or on a copy of the
	 * database — and to see what it would touch.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only read. Nothing is written and the pass is not marked done.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sdc-pb rename --dry-run
	 *     wp sdc-pb rename
	 *
	 * @param string[]            $args       Positional arguments; unused.
	 * @param array<string,mixed> $assoc_args Flags.
	 */
	public static function cli( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		global $wpdb;

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$rows  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Report only.
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s )", self::LEGACY_DATA, self::LEGACY_MARKER ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb.
			);
			$posts = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Report only.
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s", '%' . $wpdb->esc_like( self::LEGACY_BLOCK ) . '%' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb.
			);

			WP_CLI::log( sprintf( 'meta=%d posts=%d', $rows, $posts ) );

			return;
		}

		$report = self::run();

		WP_CLI::log(
			sprintf(
				'meta=%d marker=%d content=%d files=%d',
				$report['data'],
				$report['marker'],
				$report['content'],
				$report['files']
			)
		);

		foreach ( $report['unwritable'] as $file ) {
			WP_CLI::warning( 'nicht schreibbar: ' . $file );
		}

		if ( ! $report['ok'] ) {
			WP_CLI::error( 'Mindestens ein Datenbank-Statement ist fehlgeschlagen; der Lauf ist nicht als erledigt markiert.' );
		}

		WP_CLI::success( 'Daten tragen das Präfix sdcpb.' );
	}
}

/*
 * Register the method, not a command class.
 *
 * A second class for one subcommand would be a second object structure in a
 * file that already has one, and the pass itself is what the command runs:
 * `cli()` above is the whole command.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'sdc-pb rename', array( 'Sdcpb_Rename', 'cli' ) );
}
