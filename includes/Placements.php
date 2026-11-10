<?php
/**
 * Collection placement terms created from the browser block.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy that connects a collection to a named block slot.
 */
class Placements {

	/**
	 * Register the taxonomy after the collection post type.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 11 );
		add_action( 'template_redirect', array( __CLASS__, 'block_public_urls' ), 0 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'block_canonical' ), 0, 2 );
		add_filter( 'do_redirect_guess_404_permalink', array( __CLASS__, 'skip_guess' ) );
	}

	/**
	 * Hidden taxonomy. Editors create terms from the block, then check them on a collection.
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			COLLECTIONS_MANAGER_PLACEMENT,
			array( COLLECTIONS_MANAGER_POST_TYPE ),
			array(
				'labels'                => array(
					'name'          => __( 'Placement', 'collections-manager' ),
					'singular_name' => __( 'Placement', 'collections-manager' ),
					'search_items'  => __( 'Search Placement', 'collections-manager' ),
					'all_items'     => __( 'Placement', 'collections-manager' ),
					'edit_item'     => __( 'Edit Placement', 'collections-manager' ),
					'update_item'   => __( 'Update Placement', 'collections-manager' ),
					'add_new_item'  => __( 'Add Placement', 'collections-manager' ),
					'new_item_name' => __( 'New Placement name', 'collections-manager' ),
					'not_found'     => __( 'No Placement found.', 'collections-manager' ),
				),
				'public'                => false,
				'publicly_queryable'    => false,
				'hierarchical'          => false,
				'show_ui'               => false,
				'show_in_menu'          => false,
				'show_in_nav_menus'     => false,
				'show_in_rest'          => true,
				'rest_controller_class' => \CollectionsManager\Rest\PlacementTerms::class,
				'show_admin_column'     => false,
				'show_tagcloud'         => false,
				'rewrite'               => false,
				'query_var'             => false,
				'capabilities'          => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Front-end placement URLs are not archives.
	 */
	public static function block_public_urls() {
		if ( ! self::is_public_request() ) {
			return;
		}

		global $wp_query;
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Stop canonical redirects for placement URLs, including 404 guesses.
	 *
	 * @param string|false $redirect_url  Proposed redirect.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function block_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( self::is_public_request() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Do not guess a post from a placement path.
	 *
	 * @param bool $do_redirect Whether WordPress should guess a 404 redirect.
	 * @return bool
	 */
	public static function skip_guess( $do_redirect ) {
		if ( self::is_public_request() ) {
			return false;
		}

		return $do_redirect;
	}

	/**
	 * Whether this front-end request is aimed at a collection placement URL.
	 *
	 * @return bool
	 */
	private static function is_public_request() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		$taxonomy = COLLECTIONS_MANAGER_PLACEMENT;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing a public read, not a form submission.
		if ( isset( $_GET[ $taxonomy ] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing a public read, not a form submission.
		if ( isset( $_GET['taxonomy'] ) && $taxonomy === sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) ) {
			return true;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = is_string( $uri ) ? wp_parse_url( $uri, PHP_URL_PATH ) : '';
		$path = is_string( $path ) ? trim( $path, '/' ) : '';
		if ( '' === $path ) {
			return false;
		}

		$prefixes = array( $taxonomy, str_replace( '_', '-', $taxonomy ) );
		foreach ( $prefixes as $prefix ) {
			if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Placements tab: status and the pages or templates still using each name.
	 */
	public static function render_admin_tab() {
		$terms = get_terms(
			array(
				'taxonomy'   => COLLECTIONS_MANAGER_PLACEMENT,
				'hide_empty' => false,
			)
		);
		$usage = PlacementUsage::sync_all();

		echo '<div class="cm-screen">';
		echo '<p class="cm-lead">' . esc_html__( 'Create a Placement from the Collections Browser block. Then check it when you edit a collection. A Placement is inactive when no saved block still uses it.', 'collections-manager' ) . '</p>';
		echo '<table class="widefat striped cm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Used by', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Collections', 'collections-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<tr><td colspan="5"><em>' . esc_html__( 'No Placement yet. Add a Collections Browser block and create one in the sidebar.', 'collections-manager' ) . '</em></td></tr>';
		} else {
			foreach ( $terms as $placement ) {
				$used   = isset( $usage[ $placement->slug ] ) ? $usage[ $placement->slug ] : array();
				$active = ! empty( $used );
				echo '<tr>';
				echo '<td><strong>' . esc_html( $placement->name ) . '</strong></td>';
				echo '<td><code>' . esc_html( $placement->slug ) . '</code></td>';
				echo '<td><span class="cm-status ' . esc_attr( $active ? 'cm-status--active' : 'cm-status--inactive' ) . '">' . esc_html( $active ? __( 'Active', 'collections-manager' ) : __( 'Inactive', 'collections-manager' ) ) . '</span></td>';
				echo '<td>' . wp_kses_post( self::usage_html( $used ) ) . '</td>';
				echo '<td>' . esc_html( (string) $placement->count ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * @param array<int, array<string, mixed>> $used Usage rows.
	 * @return string
	 */
	private static function usage_html( $used ) {
		if ( empty( $used ) ) {
			return esc_html__( 'Not used by a block', 'collections-manager' );
		}

		$links = array();
		foreach ( $used as $row ) {
			$label = sprintf(
				/* translators: 1: post title, 2: post type singular name. */
				__( '%1$s (%2$s)', 'collections-manager' ),
				$row['title'],
				$row['type_label']
			);
			if ( ! empty( $row['edit_url'] ) ) {
				$links[] = '<a href="' . esc_url( $row['edit_url'] ) . '">' . esc_html( $label ) . '</a>';
			} else {
				$links[] = esc_html( $label );
			}
		}

		return implode( ', ', $links );
	}
}
