<?php
/**
 * Register Collections Manager blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'collections_manager_register_image_sizes' );
/**
 * Portrait crop for phone collection cards. Existing files pick it up after their subsizes are generated.
 */
function collections_manager_register_image_sizes() {
	add_image_size( 'cm-portrait', 720, 1280, true );
}

add_action( 'init', 'collections_manager_register_blocks' );
function collections_manager_register_blocks() {
	$block_dir = COLLECTIONS_MANAGER_PATH . 'build/browser';
	if ( file_exists( $block_dir . '/block.json' ) ) {
		register_block_type( $block_dir );
	}
}
