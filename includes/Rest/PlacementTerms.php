<?php
/**
 * Authenticated read access for collection placement terms.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core term routes stay available for the block editor, and closed to anonymous visitors.
 */
class PlacementTerms extends \WP_REST_Terms_Controller {

	/**
	 * Hide the placement taxonomy from anonymous REST discovery.
	 */
	public static function register() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'hide_from_public_index' ), 10, 3 );
	}

	/**
	 * Listing terms is an editor action.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$allowed = self::can_read();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		return parent::get_items_permissions_check( $request );
	}

	/**
	 * A single placement term is not a public resource.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$allowed = self::can_read();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		return parent::get_item_permissions_check( $request );
	}

	/**
	 * Drop the taxonomy record from public discovery. Term routes are gated separately.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Response.
	 * @param array                                              $handler  Route handler.
	 * @param \WP_REST_Request                                   $request  Request.
	 * @return \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed
	 */
	public static function hide_from_public_index( $response, $handler, $request ) {
		unset( $handler );

		if ( is_wp_error( $response ) || ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$route  = untrailingslashit( (string) $request->get_route() );
		$single = '/wp/v2/taxonomies/' . COLLECTIONS_MANAGER_PLACEMENT;
		if ( '/wp/v2/taxonomies' !== $route && $single !== $route ) {
			return $response;
		}
		if ( ! is_wp_error( self::can_read() ) ) {
			return $response;
		}

		if ( $single === $route ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view placements.', 'collections-manager' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data[ COLLECTIONS_MANAGER_PLACEMENT ] ) ) {
			unset( $data[ COLLECTIONS_MANAGER_PLACEMENT ] );
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * People who can place the block, plus placement managers.
	 *
	 * @return true|\WP_Error
	 */
	private static function can_read() {
		$allowed = current_user_can( 'edit_posts' )
			|| current_user_can( 'edit_pages' )
			|| current_user_can( 'edit_theme_options' )
			|| current_user_can( 'manage_categories' );

		if ( $allowed ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to view placements.', 'collections-manager' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
