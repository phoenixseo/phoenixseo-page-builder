<?php
/**
 * The field editor on the component screen.
 *
 * A component's fields are describable in one JSON list, but asking an editor
 * to write that JSON by hand is asking them to know the host's field schema by
 * heart. This class supplies the part that cannot be guessed: which field
 * types exist, what each of them can be configured with, and in what range the
 * values have to be.
 *
 * The split is deliberate:
 *
 *   - PHP owns the catalog. One list of types and options, translated, and it
 *     is what the browser receives. Two lists would drift.
 *   - The browser owns the rows. Adding, reordering, removing and the
 *     type-specific option controls are DOM work; nothing about them needs a
 *     request.
 *   - The submitted value stays a single `sdcpb_fields` textarea. The server
 *     path (`Sdcpb_Admin::parse_fields()`) does not change, and a site with
 *     JavaScript switched off still has a usable form.
 *
 * The textarea is therefore both the transport and the fallback, not the
 * interface.
 *
 * @package SdcPageBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field catalog and markup for the component form.
 */
class Sdcpb_Field_Editor {

	/**
	 * Option kinds the browser knows how to render.
	 *
	 * Kept as strings rather than as a class constant list, because the value
	 * travels to the browser and an unknown kind must degrade to a text input
	 * instead of throwing there.
	 */
	const KIND_TEXT     = 'text';
	const KIND_TEXTAREA = 'textarea';
	const KIND_NUMBER   = 'number';
	const KIND_BOOL     = 'bool';
	const KIND_SELECT   = 'select';
	const KIND_PAIRS    = 'pairs';

	/**
	 * Field types offered in the picker.
	 *
	 * Only types whose properties this editor can show are listed. Anything
	 * else is still *allowed* in the file — the JSON panel takes it — it just
	 * has no clickable row, because a row that silently drops half of its
	 * options is worse than no row at all.
	 *
	 * Property names are the host's own (`maxlength`, `return_format`,
	 * `button_label` …): the list is written into `definition.json` verbatim.
	 *
	 * @var array<string,array>
	 */
	/**
	 * Every field type the editor offers, its group, and the settings it exposes.
	 *
	 * Built when it is asked for rather than held in a constant, because the
	 * labels are translated: a translated string cannot live in a constant
	 * initializer, and translating it at file scope would run before the
	 * textdomain is loaded. The keys here are the ACF type names — they are
	 * never shown and never translated.
	 *
	 * @return array[]
	 */
	public static function definitions() {
		return array(
		'text'         => array(
			'label'   => __('Text Line', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'placeholder', 'label' => __('Placeholder', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'maxlength', 'label' => __('Maximum Characters', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 0 ),
				array( 'key' => 'prepend', 'label' => __('Before', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'append', 'label' => __('After', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'textarea'     => array(
			'label'   => __('Multiline Text', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'rows', 'label' => __('Rows in the Input Field', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 1, 'max' => 40 ),
				array( 'key' => 'placeholder', 'label' => __('Placeholder', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXTAREA ),
				array( 'key' => 'maxlength', 'label' => __('Maximum Characters', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 0 ),
			),
		),
		'wysiwyg'      => array(
			'label'   => __('Formatted Text', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(),
		),
		'number'       => array(
			'label'   => __('Number', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'min', 'label' => __('Smallest Value', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER ),
				array( 'key' => 'max', 'label' => __('Largest Value', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER ),
				array( 'key' => 'step', 'label' => __('Step', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 0 ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'placeholder', 'label' => __('Placeholder', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'prepend', 'label' => __('Before', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'append', 'label' => __('After', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'range'        => array(
			'label'   => __('Slider', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'min', 'label' => __('Smallest Value', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER ),
				array( 'key' => 'max', 'label' => __('Largest Value', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER ),
				array( 'key' => 'step', 'label' => __('Step', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 0 ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'prepend', 'label' => __('Before', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'append', 'label' => __('After', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'email'        => array(
			'label'   => __('Email Address', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'placeholder', 'label' => __('Placeholder', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'url'          => array(
			'label'   => __('Link URL', 'sdc-page-builder' ),
			'group'   => __('Text', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'placeholder', 'label' => __('Placeholder', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'select'       => array(
			'label'   => __('Select', 'sdc-page-builder' ),
			'group'   => __('Choice', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'choices', 'label' => __('Choices', 'sdc-page-builder' ), 'kind' => self::KIND_PAIRS ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array( 'key' => 'multiple', 'label' => __('Allow Multiple Selections', 'sdc-page-builder' ), 'kind' => self::KIND_BOOL ),
				array(
					'key'     => 'return_format',
					'label'   => __('Return Value', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'value' => 'Wert',
						'label' => __('Label', 'sdc-page-builder' ),
						'array' => 'Wert und Beschriftung',
					),
				),
			),
		),
		'checkbox'     => array(
			'label'   => __('Checkboxes', 'sdc-page-builder' ),
			'group'   => __('Choice', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'choices', 'label' => __('Choices', 'sdc-page-builder' ), 'kind' => self::KIND_PAIRS ),
				array(
					'key'     => 'layout',
					'label'   => __('Layout', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'vertical'   => 'Untereinander',
						'horizontal' => 'Nebeneinander',
					),
				),
				array( 'key' => 'default_value', 'label' => __('Default Value (values, comma-separated)', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'radio'        => array(
			'label'   => __('Radio Buttons', 'sdc-page-builder' ),
			'group'   => __('Choice', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'choices', 'label' => __('Choices', 'sdc-page-builder' ), 'kind' => self::KIND_PAIRS ),
				array(
					'key'     => 'layout',
					'label'   => __('Layout', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'vertical'   => 'Untereinander',
						'horizontal' => 'Nebeneinander',
					),
				),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'button_group' => array(
			'label'   => __('Button Group', 'sdc-page-builder' ),
			'group'   => __('Choice', 'sdc-page-builder' ),
			'options' => array(
				array( 'key' => 'choices', 'label' => __('Choices', 'sdc-page-builder' ), 'kind' => self::KIND_PAIRS ),
				array( 'key' => 'default_value', 'label' => __('Default Value', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
			),
		),
		'true_false'   => array(
			'label'   => __('Yes/No', 'sdc-page-builder' ),
			'group'   => __('Choice', 'sdc-page-builder' ),
			'options' => array(
				array(
					'key'     => 'default_value',
					'label'   => __('Default Value', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'0' => 'Nein',
						'1' => 'Ja',
					),
				),
				array( 'key' => 'ui', 'label' => __('Show as Toggle', 'sdc-page-builder' ), 'kind' => self::KIND_BOOL ),
			),
		),
		'image'        => array(
			'label'   => __('Image', 'sdc-page-builder' ),
			'group'   => __('Media', 'sdc-page-builder' ),
			'options' => array(
				array(
					'key'     => 'return_format',
					'label'   => __('Return Value', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'array' => 'Datenfeld',
						'url'   => 'Adresse',
						'id'    => 'Beitrags-Nummer',
					),
				),
			),
		),
		'file'         => array(
			'label'   => __('File', 'sdc-page-builder' ),
			'group'   => __('Media', 'sdc-page-builder' ),
			'options' => array(
				array(
					'key'     => 'return_format',
					'label'   => __('Return Value', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'array' => 'Datenfeld',
						'url'   => 'Adresse',
						'id'    => 'Beitrags-Nummer',
					),
				),
			),
		),
		'link'         => array(
			'label'   => __('Link', 'sdc-page-builder' ),
			'group'   => __('Media', 'sdc-page-builder' ),
			'options' => array(
				array(
					'key'     => 'return_format',
					'label'   => __('Return Value', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'url'   => 'Adresse',
						'array' => 'Datenfeld',
					),
				),
			),
		),
		'repeater'     => array(
			'label'      => __('Repeater', 'sdc-page-builder' ),
			'group'      => __('Structure', 'sdc-page-builder' ),
			'container'  => true,
			'options'    => array(
				array( 'key' => 'button_label', 'label' => __('Label of the Add Button', 'sdc-page-builder' ), 'kind' => self::KIND_TEXT ),
				array(
					'key'     => 'layout',
					'label'   => __('Editor Display', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'table' => 'Tabelle',
						'block' => __( 'Blocks', 'sdc-page-builder' ),
						'row'   => 'Zeilen',
					),
				),
				array( 'key' => 'min', 'label' => __('Minimum Rows', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 0 ),
				array( 'key' => 'max', 'label' => __('Maximum Rows', 'sdc-page-builder' ), 'kind' => self::KIND_NUMBER, 'min' => 0 ),
			),
		),
		'group'        => array(
			'label'     => __('Group', 'sdc-page-builder' ),
			'group'     => __('Structure', 'sdc-page-builder' ),
			'container' => true,
			'options'   => array(
				array(
					'key'     => 'layout',
					'label'   => __('Editor Display', 'sdc-page-builder' ),
					'kind'    => self::KIND_SELECT,
					'choices' => array(
						'block' => __( 'Blocks', 'sdc-page-builder' ),
						'table' => 'Tabelle',
						'row'   => 'Zeilen',
					),
				),
			),
		),
		);
	}

	/**
	 * Hook the assets.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Load the component screen's assets.
	 *
	 * One hook for the whole screen, because both halves are chrome of the
	 * same page: the field editor sits in the first tab, the tab machinery in
	 * between. Two `admin_enqueue_scripts` callbacks for one screen would mean
	 * two places to look when something on it does not load.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . Sdcpb_Admin::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'sdc-pb-tabs',
			SDCPB_URL . 'assets/admin-tabs.css',
			array(),
			self::asset_version( 'assets/admin-tabs.css' )
		);

		wp_enqueue_script(
			'sdc-pb-tabs',
			SDCPB_URL . 'assets/admin-tabs.js',
			array(),
			self::asset_version( 'assets/admin-tabs.js' ),
			true
		);

		wp_enqueue_style(
			'sdc-pb-field-editor',
			SDCPB_URL . 'assets/field-editor.css',
			array(),
			self::asset_version( 'assets/field-editor.css' )
		);

		wp_enqueue_script(
			'sdc-pb-field-editor',
			SDCPB_URL . 'assets/field-editor.js',
			array(),
			self::asset_version( 'assets/field-editor.js' ),
			true
		);

		/*
		 * No `wp_set_script_translations()`: every string this script shows
		 * arrives through `SDCPB_FIELD_EDITOR.labels` below, translated by PHP
		 * at localize time. Script translations would register metadata that
		 * nothing reads — the JS has no `wp.i18n` calls.
		 */
		wp_localize_script(
			'sdc-pb-field-editor',
			'SDCPB_FIELD_EDITOR',
			array(
				// A list, not a map: the picker shows the types in this order.
				'types'  => self::types(),
				'labels' => array(
					'field'          => __( 'Field', 'sdc-page-builder' ),
					'subField'       => __( 'Subfield', 'sdc-page-builder' ),
					'addField'       => __( 'Add Field', 'sdc-page-builder' ),
					'addSubField'    => __( 'Add Subfield', 'sdc-page-builder' ),
					'name'           => __( 'Field Name', 'sdc-page-builder' ),
					'label'          => __( 'Label', 'sdc-page-builder' ),
					'type'           => __( 'Field Type', 'sdc-page-builder' ),
					'instructions'   => __( 'Hint in the Editor', 'sdc-page-builder' ),
					'required'       => __( 'Required', 'sdc-page-builder' ),
					'options'        => __( 'Settings', 'sdc-page-builder' ),
					'subFields'      => __( 'Subfields', 'sdc-page-builder' ),
					'remove'         => __( 'Remove', 'sdc-page-builder' ),
					'up'             => __( 'Move Up', 'sdc-page-builder' ),
					'down'           => __( 'Move Down', 'sdc-page-builder' ),
					'collapse'       => __( 'Collapse', 'sdc-page-builder' ),
					'expand'         => __( 'Expand', 'sdc-page-builder' ),
					'empty'          => __( 'No fields yet. Choose a field type below and add it.', 'sdc-page-builder' ),
					'removeField'    => __( 'Remove this field and its settings?', 'sdc-page-builder' ),
					'removeChildren' => __( 'The new type is not a collection. The subfields of this field will be removed. Continue?', 'sdc-page-builder' ),
					/* translators: %s: field name that is missing */
					'noName'         => __( 'Every field needs a name. Missing for: %s', 'sdc-page-builder' ),
					/* translators: %s: field name that occurs twice */
					'duplicateName'  => __( 'This field name appears twice: %s', 'sdc-page-builder' ),
					'pairValue'      => __( 'Value', 'sdc-page-builder' ),
					'pairLabel'      => __( 'Label', 'sdc-page-builder' ),
					'pairAdd'        => __( 'Add Choice', 'sdc-page-builder' ),
					'nameHint'       => __( 'a–z, 0–9 and _ . Built from the label as long as you do not change it yourself.', 'sdc-page-builder' ),
					'jsonUpdated'    => __( 'The JSON was rewritten from the fields.', 'sdc-page-builder' ),
					/* translators: %s: parser message */
					'jsonBroken'     => __( 'The JSON in the text box is faulty and was not overwritten: %s', 'sdc-page-builder' ),
					'unknownType'    => __( 'This field type has no settings of its own here. Everything in the JSON is preserved on save — it can only be changed there.', 'sdc-page-builder' ),
				),
			)
		);
	}

	/**
	 * Cache-busting version for an asset.
	 *
	 * The plugin version moves in releases, not in development, so a stale
	 * `?ver=0.1.0` would keep serving yesterday's script while the file on disk
	 * already has today's. The file's mtime changes whenever the file does.
	 *
	 * @param string $relative Path inside the plugin directory.
	 * @return string
	 */
	private static function asset_version( $relative ) {
		$file = SDCPB_DIR . $relative;

		if ( is_readable( $file ) ) {
			$stamp = filemtime( $file );

			if ( false !== $stamp ) {
				return (string) $stamp;
			}
		}

		return SDCPB_VERSION;
	}

	/**
	 * The catalog, as a list the browser can iterate.
	 *
	 * @return array[]
	 */
	public static function types() {
		$out = array();

		foreach ( self::definitions() as $type => $definition ) {
			$options = array();

			foreach ( (array) $definition['options'] as $option ) {
				$options[] = $option;
			}

			$out[] = array(
				'type'      => (string) $type,
				'label'     => (string) $definition['label'],
				'group'     => (string) $definition['group'],
				'container' => ! empty( $definition['container'] ),
				'options'   => $options,
			);
		}

		return $out;
	}

	/**
	 * Does the catalog know this type?
	 *
	 * @param string $type Field type.
	 * @return bool
	 */
	public static function is_known( $type ) {
		return isset( self::definitions()[ (string) $type ] );
	}

	/**
	 * Markup: the row list, the picker, and the JSON fallback.
	 *
	 * @param string $json Current fields as JSON, without `key` entries. Empty
	 *                     for a component without fields.
	 */
	public static function render( $json ) {
		echo '<div class="sdc-fe" data-sdc-fe>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Choose a field type, add it, fill in the settings. The JSON for the definition.json is built from that.', 'sdc-page-builder' )
		);

		echo '<div class="sdc-fe__list" data-sdc-fe-list></div>';
		echo '<p class="sdc-fe__empty" data-sdc-fe-empty>' . esc_html__( 'No fields yet. Choose a field type below and add it.', 'sdc-page-builder' ) . '</p>';

		echo '<div class="sdc-fe__add">';
		self::picker( __( 'Add Field', 'sdc-page-builder' ) );
		echo '</div>';

		echo '<p class="sdc-fe__error" data-sdc-fe-error role="alert" hidden></p>';

		echo '<details class="sdc-fe__json">';
		echo '<summary>' . esc_html__( 'Edit JSON Directly', 'sdc-page-builder' ) . '</summary>';
		echo '<p class="description">' . esc_html__( 'Filled from the fields above and submitted with the form. If you change it by hand, click "Apply JSON" afterwards.', 'sdc-page-builder' ) . '</p>';
		printf(
			'<textarea id="sdcpb_fields" name="sdcpb_fields" rows="16" class="large-text code" spellcheck="false">%s</textarea>',
			esc_textarea( (string) $json )
		);
		echo '<p><button type="button" class="button" data-sdc-fe-apply>' . esc_html__( 'Apply JSON', 'sdc-page-builder' ) . '</button></p>';
		echo '</details>';

		echo '</div>';
	}

	/**
	 * A type picker: grouped select plus its button.
	 *
	 * Rendered here and cloned by the browser for nested containers, so both
	 * levels offer exactly the same types.
	 *
	 * @param string $button_label Button text.
	 */
	private static function picker( $button_label ) {
		$groups = array();

		foreach ( self::definitions() as $type => $definition ) {
			$groups[ $definition['group'] ][ $type ] = $definition['label'];
		}

		printf(
			'<select class="sdc-fe__select" data-sdc-fe-add-type aria-label="%s">',
			esc_attr__( 'Field type for the new field', 'sdc-page-builder' )
		);

		foreach ( $groups as $group => $types ) {
			printf( '<optgroup label="%s">', esc_attr( $group ) );

			foreach ( $types as $type => $label ) {
				printf( '<option value="%s">%s</option>', esc_attr( $type ), esc_html( $label ) );
			}

			echo '</optgroup>';
		}

		echo '</select>';

		printf(
			'<button type="button" class="button" data-sdc-fe-add>%s</button>',
			esc_html( $button_label )
		);
	}

	/**
	 * Properties each field row carries besides its type options.
	 *
	 * Used by the tests and by `Sdcpb_Admin::FIELD_PROPS` as documentation of
	 * what the editor can set; the writing side stays the whitelist there.
	 *
	 * @return string[]
	 */
	public static function base_props() {
		return array( 'name', 'label', 'type', 'instructions', 'required', 'sub_fields' );
	}
}
