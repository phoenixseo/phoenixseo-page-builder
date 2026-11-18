<?php
/**
 * K5-Befund: welche Bestandsseiten melden eine fehlende Ansicht?
 *
 * @package Sdcpb_Page_Builder
 */

$posts = get_posts(
	array(
		'post_type'   => 'any',
		'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => Sdcpb_Store::META_KEY,
	)
);

foreach ( $posts as $post_id ) {
	$data = Sdcpb_Store::get_data( (int) $post_id );
	if ( empty( $data['components'] ) ) {
		continue;
	}

	$missing = array();
	foreach ( $data['components'] as $component ) {
		$type = isset( $component['type'] ) ? (string) $component['type'] : '';
		if ( '' === $type ) {
			continue;
		}
		if ( ! Sdcpb_Frontend::has_view( $type ) ) {
			$missing[] = $type;
		}
	}

	if ( $missing ) {
		printf(
			"#%d  %-40s  ohne Ansicht: %s\n",
			$post_id,
			get_the_title( $post_id ),
			implode( ', ', array_unique( $missing ) )
		);
	}
}

echo "---\nSchema-Typen: " . implode( ', ', array_keys( Sdcpb_Schema::components() ) ) . "\n";
echo 'Komponenten-Ordner: ' . implode( ', ', array_map( 'basename', Sdcpb_Schema::user_dirs() ) ) . "\n";
