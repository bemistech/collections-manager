<?php
/**
 * Create and rename collection placements from the block editor.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CollectionsManager\PlacementUsage;

/**
 * Routes that let page and template editors name a placement.
 */
class Placements {

	/**
	 * Hook route registration.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * POST to create, PUT to rename. The slug is assigned once and then left alone.
	 */
	public static function register_routes() {
		register_rest_route(
			'collections-manager/v1',
			'/placements',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_item' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'collections-manager/v1',
			'/placements/(?P<slug>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'rename_item' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'slug' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_title',
					),
					'name' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * People who can place the block.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_edit() {
		if ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'manage_categories' ) ) {
			return true;
		}

		return new \WP_Error(
			'collections_manager_forbidden',
			__( 'Sorry, you are not allowed to edit placements.', 'collections-manager' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Create a placement from its pretty name, or return the term that already has that name.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_item( \WP_REST_Request $request ) {
		$name = trim( (string) $request->get_param( 'name' ) );
		if ( '' === $name || '' === sanitize_title( $name ) ) {
			return new \WP_Error(
				'collections_manager_missing_name',
				__( 'Enter a placement name.', 'collections-manager' ),
				array( 'status' => 400 )
			);
		}

		$existing = self::term_by_name( $name );
		if ( $existing ) {
			return rest_ensure_response( self::payload( $existing, true ) );
		}

		$result = wp_insert_term( $name, COLLECTIONS_MANAGER_PLACEMENT );
		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				$term = get_term( (int) $result->get_error_data(), COLLECTIONS_MANAGER_PLACEMENT );
				if ( $term && ! is_wp_error( $term ) ) {
					return rest_ensure_response( self::payload( $term, true ) );
				}
			}

			return new \WP_Error(
				'collections_manager_create_failed',
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$term = get_term( (int) $result['term_id'], COLLECTIONS_MANAGER_PLACEMENT );
		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error(
				'collections_manager_create_failed',
				__( 'The placement could not be created.', 'collections-manager' ),
				array( 'status' => 500 )
			);
		}

		update_term_meta( $term->term_id, PlacementUsage::META_ACTIVE, '0' );

		return rest_ensure_response( self::payload( $term, false ) );
	}

	/**
	 * Change the pretty name and keep the slug so assigned collections stay put.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rename_item( \WP_REST_Request $request ) {
		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		$name = trim( (string) $request->get_param( 'name' ) );
		$term = get_term_by( 'slug', $slug, COLLECTIONS_MANAGER_PLACEMENT );

		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error(
				'collections_manager_not_found',
				__( 'That placement could not be found.', 'collections-manager' ),
				array( 'status' => 404 )
			);
		}

		if ( '' === $name ) {
			return new \WP_Error(
				'collections_manager_missing_name',
				__( 'Enter a placement name.', 'collections-manager' ),
				array( 'status' => 400 )
			);
		}

		$other = self::term_by_name( $name );
		if ( $other && (int) $other->term_id !== (int) $term->term_id ) {
			return new \WP_Error(
				'collections_manager_name_exists',
				__( 'Another placement already uses that name.', 'collections-manager' ),
				array( 'status' => 400 )
			);
		}

		$updated = wp_update_term(
			$term->term_id,
			COLLECTIONS_MANAGER_PLACEMENT,
			array(
				'name' => $name,
			)
		);
		if ( is_wp_error( $updated ) ) {
			return new \WP_Error(
				'collections_manager_rename_failed',
				$updated->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$fresh = get_term( $term->term_id, COLLECTIONS_MANAGER_PLACEMENT );
		return rest_ensure_response( self::payload( $fresh, false ) );
	}

	/**
	 * @param string $name Pretty name.
	 * @return \WP_Term|null
	 */
	private static function term_by_name( $name ) {
		$term = get_term_by( 'name', $name, COLLECTIONS_MANAGER_PLACEMENT );
		if ( $term && ! is_wp_error( $term ) ) {
			return $term;
		}

		return null;
	}

	/**
	 * @param \WP_Term $term    Placement.
	 * @param bool     $existed Whether the name was already taken.
	 * @return array{id:int,name:string,slug:string,placement_active:bool,existed:bool}
	 */
	private static function payload( $term, $existed ) {
		return array(
			'id'               => (int) $term->term_id,
			'name'             => $term->name,
			'slug'             => $term->slug,
			'placement_active' => PlacementUsage::is_active( $term ),
			'existed'          => (bool) $existed,
		);
	}
}
