<?php
/**
 * Plugin Name:       SDC Page Builder
 * Plugin URI:        https://github.com/phoenixseo/sdc-page-builder
 * Description:       Component-based page builder: ACF or SCF fields as building blocks, one block per component type, rendered server-side in the editor.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            PHOENIXSEO – Frank Pfabigan
 * Author URI:        https://phoenixseo.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sdc-page-builder
 * Domain Path:       /languages
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

define( 'SDCPB_VERSION', '0.3.0' );
define( 'SDCPB_FILE', __FILE__ );
define( 'SDCPB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDCPB_URL', plugin_dir_url( __FILE__ ) );

/*
 * `array_is_list()` is PHP 8.1+. The plugin header promises 7.4, and the
 * validator leans on the list-vs-map distinction, so provide it rather than
 * raise the minimum requirement of a plugin that otherwise works on 7.4.
 */
if ( ! function_exists( 'array_is_list' ) ) {
	/**
	 * Whether an array is a list with sequential 0-based integer keys.
	 *
	 * @param array $value Value to inspect.
	 * @return bool
	 */
	function array_is_list( $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, PHPCompatibility.FunctionNameRestrictions.NewGlobalFunction
		if ( ! is_array( $value ) ) {
			return false;
		}
		$expected = 0;
		foreach ( $value as $key => $unused ) {
			if ( $key !== $expected ) {
				return false;
			}
			++$expected;
		}
		return true;
	}
}

require_once SDCPB_DIR . 'includes/class-sdc-field-adapter.php';
require_once SDCPB_DIR . 'includes/class-sdc-store.php';
require_once SDCPB_DIR . 'includes/class-sdc-schema.php';
require_once SDCPB_DIR . 'includes/class-sdc-rest.php';
require_once SDCPB_DIR . 'includes/class-sdc-renderer.php';
require_once SDCPB_DIR . 'includes/class-sdc-editor.php';
require_once SDCPB_DIR . 'includes/class-sdc-field-editor.php';

/*
 * The view layer registers the `sdcpb_*` template helpers at file scope, which
 * is why there is no `Sdcpb_View::init()` below: a view is included by the
 * frontend, not hooked into it, and the helpers have to exist before the first
 * page renders.
 */
require_once SDCPB_DIR . 'includes/class-sdc-view.php';
require_once SDCPB_DIR . 'includes/class-sdc-view-editor.php';
require_once SDCPB_DIR . 'includes/class-sdc-style-editor.php';
require_once SDCPB_DIR . 'includes/class-sdc-tabs.php';
require_once SDCPB_DIR . 'includes/class-sdc-admin.php';
require_once SDCPB_DIR . 'includes/class-sdc-frontend.php';
require_once SDCPB_DIR . 'includes/class-sdc-block.php';
require_once SDCPB_DIR . 'includes/class-sdc-migration.php';
require_once SDCPB_DIR . 'includes/class-sdc-repair.php';
require_once SDCPB_DIR . 'includes/class-sdc-rename.php';

/*
 * Translations are not loaded here.
 *
 * Since WordPress 4.6 a plugin hosted on wordpress.org is translated by core:
 * it loads `wp-content/languages/plugins/sdc-page-builder-<locale>.mo`, which is
 * where language packs are installed, and it does so just in time. Calling
 * `load_plugin_textdomain()` on top of that loads the same catalog a second
 * time from the plugin directory, which is why the plugin review asks for the
 * call to go. The `Text Domain` and `Domain Path` headers stay — they are what
 * the packaging and the language packs key off.
 */

/**
 * Boot the plugin once all plugins are loaded, so the host field plugin
 * (ACF or SCF) has registered its API.
 */
function sdcpb_boot() {
	if ( ! Sdcpb_Field_Adapter::is_available() ) {
		add_action( 'admin_notices', 'sdcpb_missing_host_notice' );
		return;
	}

	Sdcpb_Store::init();
	Sdcpb_Schema::init();
	Sdcpb_Rest::init();
	Sdcpb_Frontend::init();
	Sdcpb_Block::init();
	Sdcpb_Migration::init();
	Sdcpb_Rename::init();

	if ( is_admin() ) {
		Sdcpb_Field_Editor::init();
		Sdcpb_Admin::init();
	}
}
add_action( 'plugins_loaded', 'sdcpb_boot' );

/**
 * Warn instead of fatal when neither ACF nor SCF is active.
 */
function sdcpb_missing_host_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'SDC Page Builder requires Advanced Custom Fields or Secure Custom Fields. Please activate one of the two.', 'sdc-page-builder' )
	);
}

/**
 * Let the builder's component schemas take part in ACF/SCF Local JSON loading.
 *
 * The plugin's own `schemas/` holds the examples. The site's components live
 * in the theme (`sdc-components/`) or in `wp-content/sdc-components/`, one
 * directory per component — see `Sdcpb_Schema::json_paths()`.
 *
 * @param array $paths Directories ACF reads JSON from.
 * @return array
 */
function sdcpb_json_load_path( $paths ) {
	$paths = (array) $paths;

	foreach ( Sdcpb_Schema::json_paths() as $path ) {
		$paths[] = $path;
	}

	return $paths;
}
add_filter( 'acf/json/load_paths', 'sdcpb_json_load_path' );

/**
 * Write a component's field group back to the file that defines it.
 *
 * @param string[] $paths Paths the host proposed.
 * @param array    $post  Field group being saved.
 * @return string[]
 */
function sdcpb_json_save_paths( $paths, $post ) {
	return Sdcpb_Schema::save_paths( (array) $paths, (array) $post );
}
add_filter( 'acf/json/save_paths', 'sdcpb_json_save_paths', 10, 2 );

/**
 * Keep a component's file name when the host writes it back.
 *
 * ACF names JSON files after the group key, so saving `definition.json` from
 * the admin would rename it. A component that came from a file keeps that
 * file's name.
 *
 * @param string $filename  Name the host proposed.
 * @param array  $post      Field group being saved.
 * @param string $load_path File it was loaded from, if any.
 * @return string
 */
function sdcpb_json_save_file_name( $filename, $post, $load_path ) {
	return Sdcpb_Schema::save_file_name( $filename, (array) $post, (string) $load_path );
}
add_filter( 'acf/json/save_file_name', 'sdcpb_json_save_file_name', 10, 3 );
