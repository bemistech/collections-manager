<?php
/**
 * Collection post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'collections_manager_register_post_type' );
add_filter( 'wp_sitemaps_post_types', 'collections_manager_exclude_sitemap_post_types' );
add_filter( 'wp_sitemaps_taxonomies', 'collections_manager_exclude_sitemap_taxonomies' );

/**
 * Keep collections out of the core XML sitemap.
 *
 * @param array<string, WP_Post_Type> $post_types Post types included in the sitemap.
 * @return array<string, WP_Post_Type>
 */
function collections_manager_exclude_sitemap_post_types( $post_types ) {
	unset( $post_types[ COLLECTIONS_MANAGER_POST_TYPE ] );
	return $post_types;
}

/**
 * Keep collection placements out of the core XML sitemap.
 *
 * @param array<string, WP_Taxonomy> $taxonomies Taxonomies included in the sitemap.
 * @return array<string, WP_Taxonomy>
 */
function collections_manager_exclude_sitemap_taxonomies( $taxonomies ) {
	unset( $taxonomies[ COLLECTIONS_MANAGER_PLACEMENT ] );
	return $taxonomies;
}

/**
 * Register the private collections post type.
 */
function collections_manager_register_post_type() {
	register_post_type(
		COLLECTIONS_MANAGER_POST_TYPE,
		array(
			'labels'              => array(
				'name'          => __( 'Collections', 'collections-manager' ),
				'singular_name' => __( 'Collection', 'collections-manager' ),
				'add_new_item'  => __( 'Add New Collection', 'collections-manager' ),
				'edit_item'     => __( 'Edit Collection', 'collections-manager' ),
				'search_items'  => __( 'Search Collections', 'collections-manager' ),
				'not_found'     => __( 'No collections found.', 'collections-manager' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'excerpt', 'thumbnail', 'page-attributes' ),
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
		)
	);
}
