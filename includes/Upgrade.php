<?php
/**
 * One-time placement setup for existing installs.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds named placements without assigning collections.
 */
class Upgrade {

	/**
	 * Versioned flag. The routine exits once this option exists.
	 */
	const OPTION = 'collections_manager_placement_seed_v1';

	/**
	 * Run after the placement taxonomy is registered.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'seed' ), 20 );
	}

	/**
	 * Create the homepage placements when they are missing.
	 *
	 * Idempotent: a second run finds the option and does nothing, and insert
	 * is skipped when a slug already exists. No collections are assigned.
	 */
	public static function seed() {
		if ( get_option( self::OPTION ) ) {
			return;
		}
		if ( ! taxonomy_exists( COLLECTIONS_MANAGER_PLACEMENT ) ) {
			return;
		}

		$definitions = array(
			'home-collections' => 'Home Collections',
			'home-originals'   => 'WDWNT Originals',
		);

		foreach ( $definitions as $slug => $name ) {
			$existing = get_term_by( 'slug', $slug, COLLECTIONS_MANAGER_PLACEMENT );
			if ( $existing && ! is_wp_error( $existing ) ) {
				continue;
			}

			$created = wp_insert_term(
				$name,
				COLLECTIONS_MANAGER_PLACEMENT,
				array(
					'slug' => $slug,
				)
			);
			if ( is_wp_error( $created ) && 'term_exists' !== $created->get_error_code() ) {
				return;
			}
		}

		update_option( self::OPTION, COLLECTIONS_MANAGER_VERSION, false );
	}
}
