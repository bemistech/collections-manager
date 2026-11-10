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
	}

	/**
	 * Hidden taxonomy. Editors create terms from the block, then check them on a collection.
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			COLLECTIONS_MANAGER_PLACEMENT,
			array( COLLECTIONS_MANAGER_POST_TYPE ),
			array(
				'labels'             => array(
					'name'          => __( 'Collection Placements', 'collections-manager' ),
					'singular_name' => __( 'Collection Placement', 'collections-manager' ),
					'search_items'  => __( 'Search placements', 'collections-manager' ),
					'all_items'     => __( 'All placements', 'collections-manager' ),
					'edit_item'     => __( 'Edit placement', 'collections-manager' ),
					'update_item'   => __( 'Update placement', 'collections-manager' ),
					'add_new_item'  => __( 'Add placement', 'collections-manager' ),
					'new_item_name' => __( 'New placement name', 'collections-manager' ),
					'not_found'     => __( 'No placements found.', 'collections-manager' ),
				),
				'public'             => false,
				'publicly_queryable' => false,
				'hierarchical'       => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => true,
				'show_admin_column'  => false,
				'show_tagcloud'      => false,
				'rewrite'            => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
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
		echo '<p class="cm-lead">' . esc_html__( 'Create a placement from the Collections Browser block. Then check it here when you edit a collection. A placement is inactive when no saved block still uses it.', 'collections-manager' ) . '</p>';
		echo '<table class="widefat striped cm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Used by', 'collections-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Collections', 'collections-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<tr><td colspan="5"><em>' . esc_html__( 'No placements yet. Add a Collections Browser block and create one in the sidebar.', 'collections-manager' ) . '</em></td></tr>';
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
