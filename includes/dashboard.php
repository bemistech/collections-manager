<?php
/**
 * Rough dashboard for creating and ordering collections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'collections_manager_register_menu' );
function collections_manager_register_menu() {
	add_menu_page(
		__( 'Collections', 'collections-manager' ),
		__( 'Collections', 'collections-manager' ),
		'edit_posts',
		'collections_manager',
		'collections_manager_render_dashboard_page',
		'dashicons-index-card',
		29
	);
	add_submenu_page(
		'collections_manager',
		__( 'Collections', 'collections-manager' ),
		__( 'Dashboard', 'collections-manager' ),
		'edit_posts',
		'collections_manager',
		'collections_manager_render_dashboard_page'
	);
}

add_action( 'admin_init', 'collections_manager_handle_dashboard_actions' );
function collections_manager_handle_dashboard_actions() {
	if ( ! is_admin() ) {
		return;
	}

	if ( isset( $_POST['collections_manager_save'] ) ) {
		collections_manager_save_collection();
	}

	if ( isset( $_GET['collections_manager_trash'] ) ) {
		collections_manager_trash_collection();
	}
}

/**
 * Card payload for the collection post picker.
 *
 * Term names that match the active filter are listed first.
 *
 * @param WP_Post $post      Post object.
 * @param int[]   $highlight Term IDs the editor is filtering by.
 * @return array{id:int,title:string,date:string,image:string,terms:string[]}
 */
function collections_manager_picker_post_data( $post, $highlight = array() ) {
	$taxes     = collections_manager_taxonomies_for_post_type( $post->post_type );
	$highlight = array_map( 'absint', (array) $highlight );
	$matched   = array();
	$rest      = array();
	$prefer    = array( 'category', 'post_tag' );
	$seen_tax  = array();

	foreach ( array_merge( $prefer, array_keys( $taxes ) ) as $taxonomy ) {
		if ( isset( $seen_tax[ $taxonomy ] ) || ! isset( $taxes[ $taxonomy ] ) ) {
			continue;
		}
		$seen_tax[ $taxonomy ] = true;
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( in_array( (int) $term->term_id, $highlight, true ) ) {
				$matched[] = $term->name;
			} else {
				$rest[] = $term->name;
			}
		}
	}

	$labels = array_slice( array_merge( $matched, $rest ), 0, 2 );

	return array(
		'id'    => (int) $post->ID,
		'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
		'date'  => get_the_date( '', $post ),
		'image' => collections_manager_card_image_url( $post->ID, 'thumbnail' ),
		'terms' => $labels,
	);
}

/**
 * Date and term line under a picker title.
 *
 * @param array{date?:string,terms?:string[]} $data Picker payload.
 * @return string
 */
function collections_manager_picker_meta_text( $data ) {
	$parts = array();
	if ( ! empty( $data['date'] ) ) {
		$parts[] = (string) $data['date'];
	}
	if ( ! empty( $data['terms'] ) && is_array( $data['terms'] ) ) {
		foreach ( $data['terms'] as $term ) {
			$parts[] = (string) $term;
		}
	}

	return implode( ' · ', $parts );
}

/**
 * Parse `taxonomy:id,id;taxonomy:id` into a tax query. AND across taxonomies, IN within one.
 *
 * @param string $post_type Post type slug.
 * @param mixed  $raw       Filter string from the picker.
 * @return array<int|string, mixed>
 */
function collections_manager_picker_tax_query( $post_type, $raw ) {
	$raw = is_string( $raw ) ? $raw : '';
	if ( strlen( $raw ) > 500 ) {
		$raw = substr( $raw, 0, 500 );
	}

	$taxes = collections_manager_taxonomies_for_post_type( $post_type );
	$query = array( 'relation' => 'AND' );

	foreach ( explode( ';', $raw ) as $piece ) {
		$piece = trim( $piece );
		if ( '' === $piece || false === strpos( $piece, ':' ) ) {
			continue;
		}
		$parts = explode( ':', $piece, 2 );
		if ( count( $parts ) !== 2 ) {
			continue;
		}
		$taxonomy = sanitize_key( $parts[0] );
		if ( ! isset( $taxes[ $taxonomy ] ) ) {
			continue;
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $parts[1] ) ) ) ) );
		if ( ! $ids ) {
			continue;
		}
		$query[] = array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => $ids,
			'include_children' => true,
			'operator'         => 'IN',
		);
	}

	return count( $query ) > 1 ? $query : array();
}

/**
 * One chosen post in the manual picker. Markup matches the rows built in dashboard.js.
 *
 * @param WP_Post $post Post object.
 */
function collections_manager_render_selected_post( $post ) {
	$data = collections_manager_picker_post_data( $post );
	echo '<li data-id="' . esc_attr( (string) $data['id'] ) . '">';
	echo '<input type="hidden" name="collection[post_ids][]" value="' . esc_attr( (string) $data['id'] ) . '" />';
	echo '<button type="button" class="cm-drag" aria-label="' . esc_attr__( 'Drag to reorder', 'collections-manager' ) . '"><span class="dashicons dashicons-move" aria-hidden="true"></span></button>';
	if ( $data['image'] ) {
		echo '<img class="cm-thumb" alt="" src="' . esc_url( $data['image'] ) . '" />';
	} else {
		echo '<span class="cm-thumb cm-thumb--empty" aria-hidden="true"></span>';
	}
	echo '<span class="cm-selected-posts__body">';
	echo '<span class="cm-selected-posts__title">' . esc_html( $data['title'] ) . '</span>';
	$meta = collections_manager_picker_meta_text( $data );
	if ( $meta ) {
		echo '<span class="cm-selected-posts__meta">' . esc_html( $meta ) . '</span>';
	}
	echo '</span>';
	echo '<button type="button" class="button-link cm-move-up" aria-label="' . esc_attr__( 'Move up', 'collections-manager' ) . '"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>';
	echo '<button type="button" class="button-link cm-move-down" aria-label="' . esc_attr__( 'Move down', 'collections-manager' ) . '"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>';
	echo '<button type="button" class="button-link cm-remove-post" aria-label="' . esc_attr__( 'Remove', 'collections-manager' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
	echo '</li>';
}

/**
 * Limit picker search to titles. A full content search is much slower on a large site.
 *
 * @param string   $where SQL WHERE clause.
 * @param WP_Query $query Current query.
 * @return string
 */
function collections_manager_picker_title_where( $where, $query ) {
	if ( ! $query instanceof WP_Query ) {
		return $where;
	}
	$term = $query->get( 'cm_title_search' );
	if ( ! is_string( $term ) || '' === $term ) {
		return $where;
	}

	global $wpdb;
	$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $term ) . '%' );
	return $where;
}

add_action( 'wp_ajax_collections_manager_search_posts', 'collections_manager_ajax_search_posts' );
function collections_manager_ajax_search_posts() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$types     = collections_manager_collectable_post_types();
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
	if ( ! isset( $types[ $post_type ] ) ) {
		$post_type = isset( $types['post'] ) ? 'post' : (string) array_key_first( $types );
	}

	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$page     = isset( $_GET['page'] ) ? absint( wp_unslash( $_GET['page'] ) ) : 1;
	$per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : 16;
	if ( $page < 1 ) {
		$page = 1;
	}
	if ( $per_page < 1 ) {
		$per_page = 16;
	}
	if ( $per_page > 40 ) {
		$per_page = 40;
	}

	$args = array(
		'post_type'              => $post_type ? $post_type : 'post',
		'post_status'            => 'publish',
		'posts_per_page'         => $per_page + 1,
		'paged'                  => $page,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	);
	if ( '' !== $search ) {
		$args['cm_title_search'] = $search;
	}

	$tax_query = collections_manager_picker_tax_query( $post_type, isset( $_GET['taxes'] ) ? wp_unslash( $_GET['taxes'] ) : '' );
	$highlight = array();
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query;
		foreach ( $tax_query as $clause ) {
			if ( is_array( $clause ) && ! empty( $clause['terms'] ) ) {
				$highlight = array_merge( $highlight, array_map( 'absint', (array) $clause['terms'] ) );
			}
		}
	}

	if ( '' !== $search ) {
		add_filter( 'posts_where', 'collections_manager_picker_title_where', 10, 2 );
	}
	$query = new WP_Query( $args );
	if ( '' !== $search ) {
		remove_filter( 'posts_where', 'collections_manager_picker_title_where', 10 );
	}
	$posts    = is_array( $query->posts ) ? $query->posts : array();
	$has_more = count( $posts ) > $per_page;
	if ( $has_more ) {
		array_pop( $posts );
	}

	$results = array();
	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post ) {
			$results[] = collections_manager_picker_post_data( $post, $highlight );
		}
	}

	wp_send_json_success(
		array(
			'posts'    => $results,
			'page'     => $page,
			'has_more' => $has_more,
		)
	);
}

add_action( 'wp_ajax_collections_manager_query_terms', 'collections_manager_ajax_query_terms' );
function collections_manager_ajax_query_terms() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$post_type  = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
	$taxonomy   = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$hide_empty = ! empty( $_GET['hide_empty'] );
	$taxes      = collections_manager_taxonomies_for_post_type( $post_type );
	$empty      = array(
		'terms'     => array(),
		'truncated' => false,
	);
	if ( ! $taxonomy || ! isset( $taxes[ $taxonomy ] ) ) {
		wp_send_json_success( $empty );
	}

	$count = wp_count_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => $hide_empty,
		)
	);
	$count = is_wp_error( $count ) ? 0 : (int) $count;

	$args = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => $hide_empty,
		'orderby'    => 'name',
		'order'      => 'ASC',
	);

	$truncated = false;
	if ( '' !== $search ) {
		$args['search'] = $search;
		$args['number'] = 30;
	} elseif ( $count > 200 ) {
		$args['number']  = 20;
		$args['orderby'] = 'count';
		$args['order']   = 'DESC';
		$truncated       = true;
	}

	$terms = get_terms( $args );
	if ( is_wp_error( $terms ) ) {
		wp_send_json_success( $empty );
	}

	$results = array();
	foreach ( $terms as $term ) {
		$results[] = array(
			'id'     => (int) $term->term_id,
			'name'   => $term->name,
			'count'  => (int) $term->count,
			'parent' => (int) $term->parent,
		);
	}

	if ( '' !== $search && count( $results ) >= 30 ) {
		$truncated = true;
	}

	wp_send_json_success(
		array(
			'terms'     => $results,
			'truncated' => $truncated,
		)
	);
}

add_action( 'wp_ajax_collections_manager_youtube_resolve_channel', 'collections_manager_ajax_youtube_resolve_channel' );
function collections_manager_ajax_youtube_resolve_channel() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$raw    = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
	$result = \CollectionsManager\YouTube\Client::resolve_channel( $raw );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

add_action( 'wp_ajax_collections_manager_youtube_channel_videos', 'collections_manager_ajax_youtube_channel_videos' );
function collections_manager_ajax_youtube_channel_videos() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$channel_id = isset( $_GET['channel_id'] ) ? sanitize_text_field( wp_unslash( $_GET['channel_id'] ) ) : '';
	$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$token      = isset( $_GET['page_token'] ) ? sanitize_text_field( wp_unslash( $_GET['page_token'] ) ) : '';
	$result     = \CollectionsManager\YouTube\Client::channel_videos( $channel_id, $search, $token );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

add_action( 'wp_ajax_collections_manager_youtube_playlist_preview', 'collections_manager_ajax_youtube_playlist_preview' );
function collections_manager_ajax_youtube_playlist_preview() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$playlist = isset( $_GET['playlist'] ) ? sanitize_text_field( wp_unslash( $_GET['playlist'] ) ) : '';
	$limit    = isset( $_GET['limit'] ) ? absint( wp_unslash( $_GET['limit'] ) ) : 12;
	$result   = \CollectionsManager\YouTube\Client::playlist_videos( $playlist, $limit );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'count' => count( $result ),
			'title' => isset( $result[0]['title'] ) ? $result[0]['title'] : '',
		)
	);
}

add_action( 'wp_ajax_collections_manager_youtube_channel_playlists', 'collections_manager_ajax_youtube_channel_playlists' );
function collections_manager_ajax_youtube_channel_playlists() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$channel_id = isset( $_GET['channel_id'] ) ? sanitize_text_field( wp_unslash( $_GET['channel_id'] ) ) : '';
	$result     = \CollectionsManager\YouTube\Client::channel_playlists( $channel_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'playlists' => $result ) );
}

add_action( 'wp_ajax_collections_manager_youtube_latest_preview', 'collections_manager_ajax_youtube_latest_preview' );
function collections_manager_ajax_youtube_latest_preview() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$raw      = isset( $_GET['channels'] ) ? sanitize_text_field( wp_unslash( $_GET['channels'] ) ) : '';
	$channels = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $channel_id ) {
		$channels[] = array( 'id' => $channel_id );
	}
	$limit  = isset( $_GET['limit'] ) ? absint( wp_unslash( $_GET['limit'] ) ) : 12;
	$result = \CollectionsManager\YouTube\Client::latest_from_channels( $channels, $limit );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'count' => count( $result ),
			'title' => isset( $result[0]['title'] ) ? $result[0]['title'] : '',
		)
	);
}

add_action( 'wp_ajax_collections_manager_feed_discover', 'collections_manager_ajax_feed_discover' );
function collections_manager_ajax_feed_discover() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$url    = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ), array( 'http', 'https' ) ) : '';
	$result = \CollectionsManager\Feed\Client::discover( $url );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

add_action( 'wp_ajax_collections_manager_feed_preview', 'collections_manager_ajax_feed_preview' );
function collections_manager_ajax_feed_preview() {
	check_ajax_referer( 'collections_manager_admin' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$feed   = array(
		'url'           => isset( $_GET['url'] ) ? wp_unslash( $_GET['url'] ) : '',
		'kind'          => isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'auto',
		'rest_base'     => isset( $_GET['rest_base'] ) ? sanitize_key( wp_unslash( $_GET['rest_base'] ) ) : 'posts',
		'category_id'   => isset( $_GET['category_id'] ) ? absint( wp_unslash( $_GET['category_id'] ) ) : 0,
		'category_slug' => isset( $_GET['category_slug'] ) ? sanitize_title( wp_unslash( $_GET['category_slug'] ) ) : '',
		'limit'         => isset( $_GET['limit'] ) ? absint( wp_unslash( $_GET['limit'] ) ) : 12,
	);
	$result = \CollectionsManager\Feed\Client::fetch( collections_manager_sanitize_feed( $feed ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'count' => count( $result ),
			'title' => isset( $result[0]['title'] ) ? $result[0]['title'] : '',
		)
	);
}

add_action( 'admin_enqueue_scripts', 'collections_manager_enqueue_dashboard_assets' );
function collections_manager_enqueue_dashboard_assets( $hook ) {
	if ( 'toplevel_page_collections_manager' !== $hook ) {
		return;
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';

	wp_enqueue_style(
		'collections-manager-dashboard',
		COLLECTIONS_MANAGER_URL . 'assets/dashboard.css',
		array(),
		COLLECTIONS_MANAGER_VERSION
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
	$placement_filter = isset( $_GET['placement'] ) ? sanitize_key( wp_unslash( $_GET['placement'] ) ) : '';
	if ( $placement_filter && current_user_can( 'edit_others_posts' ) && in_array( $tab, array( 'overview', 'collections' ), true ) ) {
		wp_enqueue_script(
			'collections-manager-placement-order',
			COLLECTIONS_MANAGER_URL . 'assets/placement-order.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-api-fetch' ),
			COLLECTIONS_MANAGER_VERSION,
			true
		);
		wp_localize_script(
			'collections-manager-placement-order',
			'collectionsManagerPlacementOrder',
			array(
				'slug'    => $placement_filter,
				'saving'  => __( 'Saving order…', 'collections-manager' ),
				'saved'   => __( 'Order saved.', 'collections-manager' ),
				'error'   => __( 'The order could not be saved.', 'collections-manager' ),
			)
		);
	}

	if ( 'styles' === $tab ) {
		wp_enqueue_style(
			'collections-manager-browser',
			COLLECTIONS_MANAGER_URL . 'build/browser/style-index.css',
			array(),
			COLLECTIONS_MANAGER_VERSION
		);
		wp_enqueue_style(
			'collections-manager-styles-admin',
			COLLECTIONS_MANAGER_URL . 'assets/styles-admin.css',
			array( 'collections-manager-dashboard', 'collections-manager-browser' ),
			COLLECTIONS_MANAGER_VERSION
		);
		wp_enqueue_script(
			'collections-manager-styles-admin',
			COLLECTIONS_MANAGER_URL . 'assets/styles-admin.js',
			array(),
			COLLECTIONS_MANAGER_VERSION,
			true
		);
		return;
	}

	if ( 'settings' === $tab || 'collections' !== $tab ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
	$editing = isset( $_GET['collection'] ) && '' !== sanitize_text_field( wp_unslash( $_GET['collection'] ) );
	if ( ! $editing ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'collections-manager-dashboard',
		COLLECTIONS_MANAGER_URL . 'assets/dashboard.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		COLLECTIONS_MANAGER_VERSION,
		true
	);
	$post_types = array();
	foreach ( collections_manager_collectable_post_types() as $slug => $label ) {
		$post_types[ $slug ] = array(
			'label'      => $label,
			'taxonomies' => collections_manager_taxonomies_for_post_type( $slug ),
		);
	}

	wp_localize_script(
		'collections-manager-dashboard',
		'collectionsManagerDashboard',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'collections_manager_admin' ),
			'postTypes' => $post_types,
			'hasApiKey' => \CollectionsManager\YouTube\Client::has_api_key(),
			'i18n'      => array(
				'noVideos'       => __( 'No videos found for that channel.', 'collections-manager' ),
				'addChannel'     => __( 'Add a channel first.', 'collections-manager' ),
				'previewOk'      => __( 'Found %d videos. Newest first.', 'collections-manager' ),
				'previewEmpty'   => __( 'No public videos found.', 'collections-manager' ),
				'playlistsEmpty' => __( 'No public playlists on that channel. Paste a playlist URL instead.', 'collections-manager' ),
				'feedOk'         => __( 'Found %d items.', 'collections-manager' ),
				'feedEmpty'      => __( 'No items found at that URL.', 'collections-manager' ),
				'feedRest'       => __( 'WordPress site detected. Choose a post type or category, then check the feed.', 'collections-manager' ),
				'feedRss'        => __( 'XML feed detected. Check the feed to preview items.', 'collections-manager' ),
				/* translators: %s: taxonomy label, such as Categories. */
				'allTax'         => __( 'All %s', 'collections-manager' ),
				/* translators: %s: taxonomy label. */
				'searchTax'      => __( 'Search %s…', 'collections-manager' ),
				'add'            => __( 'Add', 'collections-manager' ),
				'added'          => __( 'Added', 'collections-manager' ),
				'remove'         => __( 'Remove', 'collections-manager' ),
				'moveUp'         => __( 'Move up', 'collections-manager' ),
				'moveDown'       => __( 'Move down', 'collections-manager' ),
				'drag'           => __( 'Drag to reorder', 'collections-manager' ),
				'noPosts'        => __( 'No posts match.', 'collections-manager' ),
				'noTerms'        => __( 'No matches.', 'collections-manager' ),
				'loading'        => __( 'Loading…', 'collections-manager' ),
				'searching'      => __( 'Searching…', 'collections-manager' ),
				'loadError'      => __( 'Could not load posts.', 'collections-manager' ),
				'newest'         => __( 'Newest first. Click a post to add or remove it.', 'collections-manager' ),
				'addMatching'    => __( 'Add matching posts', 'collections-manager' ),
				'addMore'        => __( 'Add more matches', 'collections-manager' ),
				/* translators: %d: number of posts added. */
				'addedCount'     => __( 'Added %d posts.', 'collections-manager' ),
				'alreadyIn'      => __( 'Those posts are already in the collection.', 'collections-manager' ),
				'clearConfirm'   => __( 'Remove every selected post from this collection?', 'collections-manager' ),
				'typeMore'       => __( 'Type at least 2 letters to search.', 'collections-manager' ),
				'popularHint'    => __( 'Showing popular terms. Type to search the rest.', 'collections-manager' ),
				'previewNewest'  => __( 'Newest matches', 'collections-manager' ),
				'previewMore'    => __( 'More posts match than shown here.', 'collections-manager' ),
				'previewAll'     => __( 'No category or tag selected, so this is every published item of this type.', 'collections-manager' ),
			),
		)
	);
}

/**
 * Checkboxes for named block slots.
 *
 * @param int $collection_id Collection being edited, or 0 for a new one.
 */
function collections_manager_render_placement_checkboxes( $collection_id ) {
	$can_assign = current_user_can( 'edit_others_posts' );
	$selected   = $collection_id ? wp_get_object_terms( $collection_id, COLLECTIONS_MANAGER_PLACEMENT, array( 'fields' => 'ids' ) ) : array();
	$selected   = is_wp_error( $selected ) ? array() : array_map( 'intval', $selected );
	$placements = get_terms(
		array(
			'taxonomy'   => COLLECTIONS_MANAGER_PLACEMENT,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $placements ) || empty( $placements ) ) {
		echo '<p>' . esc_html__( 'No Placement yet. Create one from a Collections Browser block.', 'collections-manager' ) . '</p>';
		return;
	}

	echo '<fieldset class="cm-checks" aria-labelledby="cm-placements-label">';
	foreach ( $placements as $placement ) {
		$label = $placement->name;
		if ( ! \CollectionsManager\PlacementUsage::is_active( $placement ) ) {
			$label .= ' (' . __( 'inactive', 'collections-manager' ) . ')';
		}
		echo '<label>';
		echo '<input type="checkbox" name="collection[placements][]" value="' . esc_attr( (string) $placement->term_id ) . '" ' . checked( in_array( (int) $placement->term_id, $selected, true ), true, false ) . disabled( ! $can_assign, true, false ) . ' /> ';
		echo esc_html( $label );
		echo '</label>';
	}
	echo '</fieldset>';
	if ( $can_assign ) {
		echo '<p class="description">' . esc_html__( 'Checked collections show in blocks that use that Placement. Inactive means no saved block currently uses it.', 'collections-manager' ) . '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'You need permission to edit others’ posts to change Placement.', 'collections-manager' ) . '</p>';
	}
}

/**
 * Assign the collection to the checked placements.
 *
 * @param int   $collection_id Collection ID.
 * @param mixed $raw_ids       Posted term IDs.
 */
function collections_manager_save_placements( $collection_id, $raw_ids ) {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'collections_manager_save' ) ) {
		return;
	}

	$term_ids = array();
	foreach ( array_map( 'absint', (array) $raw_ids ) as $term_id ) {
		if ( ! $term_id ) {
			continue;
		}
		$term = get_term( $term_id, COLLECTIONS_MANAGER_PLACEMENT );
		if ( $term && ! is_wp_error( $term ) ) {
			$term_ids[] = (int) $term->term_id;
		}
	}

	wp_set_object_terms( $collection_id, $term_ids, COLLECTIONS_MANAGER_PLACEMENT );
}

function collections_manager_save_collection() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage collections.', 'collections-manager' ) );
	}

	check_admin_referer( 'collections_manager_save' );

	$input = isset( $_POST['collection'] ) ? wp_unslash( $_POST['collection'] ) : array();
	$input = is_array( $input ) ? $input : array();

	$title       = sanitize_text_field( $input['title'] ?? '' );
	$description = sanitize_textarea_field( $input['description'] ?? '' );
	$image       = absint( $input['image_id'] ?? 0 );
	$order       = isset( $input['menu_order'] ) ? (int) $input['menu_order'] : 0;
	$post_ids      = array_map( 'absint', (array) ( $input['post_ids'] ?? array() ) );
	$allowed_src   = array( 'manual', 'query', 'youtube', 'feed' );
	$source        = isset( $input['source'] ) ? sanitize_key( (string) $input['source'] ) : 'manual';
	if ( ! in_array( $source, $allowed_src, true ) ) {
		$source = 'manual';
	}
	$query         = isset( $input['query'] ) ? $input['query'] : array();
	$youtube       = isset( $input['youtube'] ) ? $input['youtube'] : array();
	$feed          = isset( $input['feed'] ) ? $input['feed'] : array();
	$collection_id = absint( $input['id'] ?? 0 );

	if ( '' === $title ) {
		wp_safe_redirect( collections_manager_dashboard_url( array( 'tab' => 'collections', 'error' => 'missing-title', 'collection' => $collection_id ? $collection_id : 'new' ) ) );
		exit;
	}

	$postarr = array(
		'post_type'    => COLLECTIONS_MANAGER_POST_TYPE,
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_excerpt' => $description,
		'menu_order'   => $order,
	);

	if ( $collection_id ) {
		$existing = get_post( $collection_id );
		if ( ! $existing || COLLECTIONS_MANAGER_POST_TYPE !== $existing->post_type || ! current_user_can( 'edit_post', $collection_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this collection.', 'collections-manager' ) );
		}
		$postarr['ID'] = $collection_id;
	}

	$saved_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $saved_id ) || ! $saved_id ) {
		wp_safe_redirect( collections_manager_dashboard_url( array( 'tab' => 'collections', 'error' => 'save-failed', 'collection' => $collection_id ? $collection_id : 'new' ) ) );
		exit;
	}

	collections_manager_save_post_ids( $saved_id, $post_ids );
	update_post_meta( $saved_id, COLLECTIONS_MANAGER_SOURCE_META, $source );
	collections_manager_save_query( $saved_id, $query );
	collections_manager_save_youtube( $saved_id, $youtube );
	collections_manager_save_feed( $saved_id, $feed );

	if ( $image && wp_attachment_is_image( $image ) ) {
		set_post_thumbnail( $saved_id, $image );
	} else {
		delete_post_thumbnail( $saved_id );
	}

	collections_manager_save_placements( $saved_id, $input['placements'] ?? array() );

	wp_safe_redirect( collections_manager_dashboard_url( array( 'tab' => 'collections', 'updated' => 'saved' ) ) );
	exit;
}

function collections_manager_trash_collection() {
	$id = absint( wp_unslash( $_GET['collections_manager_trash'] ) );
	check_admin_referer( 'collections_manager_trash_' . $id );

	if ( ! $id || ! current_user_can( 'delete_post', $id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to delete this collection.', 'collections-manager' ) );
	}

	$post = get_post( $id );
	if ( $post && COLLECTIONS_MANAGER_POST_TYPE === $post->post_type ) {
		wp_trash_post( $id );
	}

	wp_safe_redirect( collections_manager_dashboard_url( array( 'tab' => 'collections', 'updated' => 'trashed' ) ) );
	exit;
}

function collections_manager_render_dashboard_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'collections-manager' ) );
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	if ( ! in_array( $tab, array( 'overview', 'collections', 'placements', 'styles', 'settings', 'how-to-use' ), true ) ) {
		$tab = 'overview';
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Collections Manager', 'collections-manager' ) . '</h1>';
	collections_manager_render_notices();
	echo '<h2 class="nav-tab-wrapper">';
	$tabs = array(
		'overview'     => __( 'Overview', 'collections-manager' ),
		'collections'  => __( 'Collections', 'collections-manager' ),
		'placements'   => __( 'Placement', 'collections-manager' ),
		'styles'       => __( 'Styles', 'collections-manager' ),
		'settings'     => __( 'Settings', 'collections-manager' ),
		'how-to-use'   => __( 'How to Use', 'collections-manager' ),
	);
	foreach ( $tabs as $id => $label ) {
		printf(
			'<a href="%s" class="nav-tab%s">%s</a>',
			esc_url( collections_manager_dashboard_url( array( 'tab' => $id ) ) ),
			$tab === $id ? ' nav-tab-active' : '',
			esc_html( $label )
		);
	}
	echo '</h2>';

	if ( 'collections' === $tab ) {
		collections_manager_render_collections_tab();
	} elseif ( 'placements' === $tab ) {
		\CollectionsManager\Placements::render_admin_tab();
	} elseif ( 'styles' === $tab ) {
		\CollectionsManager\Admin\StylesPage::render();
	} elseif ( 'settings' === $tab ) {
		\CollectionsManager\Admin\SettingsPage::render();
	} elseif ( 'how-to-use' === $tab ) {
		collections_manager_render_how_to_use_tab();
	} else {
		collections_manager_render_overview_tab();
	}

	echo '</div>';
}

function collections_manager_render_notices() {
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : '';
	$error   = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';

	$messages = array(
		'saved'     => __( 'Collection saved.', 'collections-manager' ),
		'trashed'   => __( 'Collection moved to trash.', 'collections-manager' ),
		'settings'  => __( 'Settings saved.', 'collections-manager' ),
		'constant'  => __( 'Settings were not changed because COLLECTIONS_MANAGER_YOUTUBE_API_KEY is defined in wp-config.php.', 'collections-manager' ),
	);
	$errors = array(
		'missing-title' => __( 'Please enter a collection name.', 'collections-manager' ),
		'save-failed'   => __( 'The collection could not be saved.', 'collections-manager' ),
	);

	if ( isset( $messages[ $updated ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $updated ] ) . '</p></div>';
	}
	if ( isset( $errors[ $error ] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $errors[ $error ] ) . '</p></div>';
	}
}

function collections_manager_render_overview_tab() {
	echo '<div class="cm-screen">';
	echo '<p class="cm-lead">' . esc_html__( 'Curated groups of posts. Add a collection, then place it with the Collections Browser block.', 'collections-manager' ) . '</p>';
	collections_manager_render_collections_toolbar();
	collections_manager_render_collections_table();
	echo '</div>';
}

function collections_manager_render_how_to_use_tab() {
	echo '<div class="cm-screen">';
	echo '<div class="cm-dash-card">';
	echo '<h2>' . esc_html__( 'How to use', 'collections-manager' ) . '</h2>';
	echo '<ol class="cm-steps">';
	echo '<li>' . esc_html__( 'Create a collection and give it a name. Add a custom cover only if you do not want the latest item’s image.', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'Choose how items are added: browse and click posts (filter by a category or tag when you want a group), pull the latest posts from a category or tag, load a remote WordPress site or XML feed (for example podcasts.wdwnt.com), or use a YouTube playlist or videos.', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'For a remote site, paste the homepage (WordPress REST is used when available) or an RSS/Atom URL such as a category feed. Optional category filters apply to WordPress REST sources.', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'For YouTube, add a channel, then choose latest uploads, a playlist from that channel, or specific videos. Listing playlists and browsing beyond the public RSS feed needs a Data API key under Collections → Settings (or COLLECTIONS_MANAGER_YOUTUBE_API_KEY in wp-config.php).', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'Filter the collection list to one Placement and drag the rows to set that Placement’s order. Display order is only the fallback.', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'Insert the Collections Browser block and choose a Placement. Check that Placement on each collection. Leave the Placement empty only on older blocks that still pick collections themselves. Readers tap a collection to open its carousel underneath — they stay on the page.', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'Set site-wide spacing, shadows, slider box, and colors under Collections → Styles. Those inherit the active theme unless you pick a palette or custom color.', 'collections-manager' ) . '</li>';
	echo '<li>' . esc_html__( 'In the block sidebar, leave “Use global styles” on, or turn it off to style only that block.', 'collections-manager' ) . '</li>';
	echo '</ol></div></div>';
}

function collections_manager_render_collections_tab() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
	$edit_id = isset( $_GET['collection'] ) ? sanitize_text_field( wp_unslash( $_GET['collection'] ) ) : '';
	$post    = null;
	$editing = false;
	if ( 'new' === $edit_id ) {
		$editing = true;
	} elseif ( '' !== $edit_id && absint( $edit_id ) ) {
		$post = get_post( absint( $edit_id ) );
		if ( $post && COLLECTIONS_MANAGER_POST_TYPE === $post->post_type ) {
			$editing = true;
		}
	}

	echo '<div class="cm-screen">';
	if ( $editing ) {
		printf(
			'<p class="cm-back"><a href="%s">%s</a></p>',
			esc_url( collections_manager_dashboard_url( array( 'tab' => 'collections' ) ) ),
			esc_html__( '← All collections', 'collections-manager' )
		);
		collections_manager_render_collection_form( $post );
		echo '</div>';
		return;
	}

	echo '<p class="cm-lead">' . esc_html__( 'Each collection is a row of stories. Source is how those stories are chosen.', 'collections-manager' ) . '</p>';
	collections_manager_render_collections_toolbar();
	collections_manager_render_collections_table();
	echo '</div>';
}

/**
 * Short label for how a collection is filled.
 *
 * @param string $source Source key.
 * @return string
 */
function collections_manager_source_label( $source ) {
	$labels = array(
		'manual'  => __( 'Chosen posts', 'collections-manager' ),
		'query'   => __( 'Latest posts', 'collections-manager' ),
		'youtube' => __( 'YouTube', 'collections-manager' ),
		'feed'    => __( 'Feed', 'collections-manager' ),
	);

	return isset( $labels[ $source ] ) ? $labels[ $source ] : $labels['manual'];
}

/**
 * Add button shared by the overview and the collections list.
 */
function collections_manager_render_collections_toolbar() {
	echo '<div class="cm-toolbar">';
	printf(
		'<a class="button button-primary" href="%s">%s</a>',
		esc_url( collections_manager_dashboard_url( array( 'tab' => 'collections', 'collection' => 'new' ) ) ),
		esc_html__( 'Add collection', 'collections-manager' )
	);
	echo '</div>';
}

/**
 * Names of the Placements assigned to a collection.
 *
 * @param int $collection_id Collection ID.
 * @return string
 */
function collections_manager_placement_labels( $collection_id ) {
	$terms = get_the_terms( $collection_id, COLLECTIONS_MANAGER_PLACEMENT );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '';
	}

	$names = array();
	foreach ( $terms as $term ) {
		$names[] = $term->name;
	}

	return implode( ', ', $names );
}

/**
 * Placement filter for the collection list.
 *
 * @param string $current Selected slug.
 */
function collections_manager_render_placement_filter( $current ) {
	$terms = get_terms(
		array(
			'taxonomy'   => COLLECTIONS_MANAGER_PLACEMENT,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
	if ( ! in_array( $tab, array( 'overview', 'collections' ), true ) ) {
		$tab = 'overview';
	}

	echo '<form method="get" class="cm-placement-filter" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
	echo '<input type="hidden" name="page" value="collections_manager" />';
	echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';
	echo '<label for="cm-placement-filter">' . esc_html__( 'Placement', 'collections-manager' ) . '</label>';
	echo '<select name="placement" id="cm-placement-filter">';
	echo '<option value="">' . esc_html__( 'All', 'collections-manager' ) . '</option>';
	foreach ( $terms as $term ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $term->slug ),
			selected( $current, $term->slug, false ),
			esc_html( $term->name )
		);
	}
	echo '</select>';
	submit_button( __( 'Filter', 'collections-manager' ), 'secondary', '', false );
	echo '</form>';
}

/**
 * Collection list used on the overview and the collections tab.
 */
function collections_manager_render_collections_table() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
	$slug     = isset( $_GET['placement'] ) ? sanitize_key( wp_unslash( $_GET['placement'] ) ) : '';
	$term     = '' !== $slug ? get_term_by( 'slug', $slug, COLLECTIONS_MANAGER_PLACEMENT ) : false;
	$filtered = $term && ! is_wp_error( $term );
	$can_sort = $filtered && current_user_can( 'edit_others_posts' );
	$unknown  = '' !== $slug && ! $filtered;

	collections_manager_render_placement_filter( $filtered ? $term->slug : '' );

	if ( $can_sort ) {
		echo '<p class="description">' . esc_html__( 'Drag the rows to set the order for this Placement.', 'collections-manager' ) . '</p>';
	}

	$collections = array();
	if ( $filtered ) {
		foreach ( \CollectionsManager\PlacementOrder::collection_ids( $term ) as $id ) {
			$collection = get_post( $id );
			if ( $collection instanceof WP_Post ) {
				$collections[] = $collection;
			}
		}
	} elseif ( ! $unknown ) {
		$collections = collections_manager_get_collections();
	}

	echo '<table class="widefat striped cm-table"><thead><tr>';
	if ( $can_sort ) {
		echo '<th class="cm-placement-order__grip" scope="col"><span class="screen-reader-text">' . esc_html__( 'Order', 'collections-manager' ) . '</span></th>';
	}
	echo '<th>' . esc_html__( 'Name', 'collections-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Source', 'collections-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Items', 'collections-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Placement', 'collections-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Order', 'collections-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Actions', 'collections-manager' ) . '</th>';
	echo '</tr></thead><tbody' . ( $can_sort ? ' id="cm-placement-order"' : '' ) . '>';

	if ( $unknown ) {
		echo '<tr><td colspan="' . (int) ( $can_sort ? 7 : 6 ) . '">' . esc_html__( 'That Placement could not be found.', 'collections-manager' ) . '</td></tr>';
	} elseif ( empty( $collections ) ) {
		$message = $filtered
			? __( 'No collections in this Placement yet.', 'collections-manager' )
			: __( 'No collections yet.', 'collections-manager' );
		echo '<tr><td colspan="' . (int) ( $can_sort ? 7 : 6 ) . '">' . esc_html( $message ) . '</td></tr>';
	} else {
		$position = 0;
		foreach ( $collections as $collection ) {
			++$position;
			$edit  = collections_manager_dashboard_url( array( 'tab' => 'collections', 'collection' => $collection->ID ) );
			$trash = wp_nonce_url(
				collections_manager_dashboard_url( array( 'tab' => 'collections', 'collections_manager_trash' => $collection->ID ) ),
				'collections_manager_trash_' . $collection->ID
			);
			echo '<tr' . ( $can_sort ? ' data-id="' . esc_attr( (string) $collection->ID ) . '"' : '' ) . '>';
			if ( $can_sort ) {
				echo '<td><button type="button" class="cm-placement-order__handle" aria-label="' . esc_attr__( 'Drag to reorder', 'collections-manager' ) . '"><span class="dashicons dashicons-move" aria-hidden="true"></span></button></td>';
			}
			printf( '<td><a href="%s"><strong>%s</strong></a></td>', esc_url( $edit ), esc_html( get_the_title( $collection ) ) );
			echo '<td>' . esc_html( collections_manager_source_label( collections_manager_get_source( $collection->ID ) ) ) . '</td>';
			echo '<td>' . esc_html( (string) collections_manager_collection_item_count( $collection->ID ) ) . '</td>';
			echo '<td>' . esc_html( collections_manager_placement_labels( $collection->ID ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $filtered ? $position : (int) $collection->menu_order ) ) . '</td>';
			echo '<td><span class="cm-row-actions">';
			printf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'collections-manager' ) );
			if ( current_user_can( 'delete_post', $collection->ID ) ) {
				printf( '<a class="submitdelete" href="%s">%s</a>', esc_url( $trash ), esc_html__( 'Trash', 'collections-manager' ) );
			}
			echo '</span></td></tr>';
		}
	}
	echo '</tbody></table>';
	if ( $can_sort ) {
		echo '<p id="cm-placement-order-status" class="cm-placement-order__status" role="status"></p>';
	}
}

function collections_manager_render_collection_form( $post = null ) {
	$is_edit = $post instanceof WP_Post;
	$title   = $is_edit ? $post->post_title : '';
	$desc    = $is_edit ? $post->post_excerpt : '';
	$order   = $is_edit ? (int) $post->menu_order : 0;
	$image   = $is_edit ? (int) get_post_thumbnail_id( $post ) : 0;
	$source  = $is_edit ? collections_manager_get_source( $post->ID ) : 'manual';
	$query   = $is_edit ? collections_manager_get_query( $post->ID ) : collections_manager_default_query();
	$youtube = $is_edit ? collections_manager_get_youtube( $post->ID ) : collections_manager_default_youtube();
	$feed    = $is_edit ? collections_manager_get_feed( $post->ID ) : collections_manager_default_feed();
	$ids     = $is_edit ? collections_manager_get_post_ids( $post->ID ) : array();
	$selected_posts = array();
	foreach ( $ids as $id ) {
		$item = get_post( $id );
		if ( $item && 'publish' === $item->post_status ) {
			$selected_posts[] = $item;
		}
	}

	$post_types     = collections_manager_collectable_post_types();
	$query_taxes    = collections_manager_taxonomies_for_post_type( $query['post_type'] );
	$matched_count  = $is_edit && in_array( $source, array( 'query', 'youtube', 'feed' ), true ) ? collections_manager_collection_item_count( $post->ID ) : null;

	echo '<div class="cm-dash-card cm-dash-card--editor">';
	echo '<h2>' . esc_html( $is_edit ? __( 'Edit collection', 'collections-manager' ) : __( 'Add collection', 'collections-manager' ) ) . '</h2>';
	echo '<form id="cm-collection-form" method="post" action="' . esc_url( collections_manager_dashboard_url( array( 'tab' => 'collections' ) ) ) . '">';
	wp_nonce_field( 'collections_manager_save' );
	if ( $is_edit ) {
		echo '<input type="hidden" name="collection[id]" value="' . esc_attr( (string) $post->ID ) . '" />';
	}
	?>
	<div class="cm-editor__details">
		<div class="cm-editor__primary">
			<p class="cm-field">
				<label for="cm-title"><?php esc_html_e( 'Name', 'collections-manager' ); ?></label>
				<input name="collection[title]" id="cm-title" type="text" required value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p class="cm-field">
				<label for="cm-description"><?php esc_html_e( 'Short description', 'collections-manager' ); ?></label>
				<textarea name="collection[description]" id="cm-description" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
			</p>
			<p class="cm-field">
				<label for="cm-order"><?php esc_html_e( 'Display order', 'collections-manager' ); ?></label>
				<input name="collection[menu_order]" id="cm-order" type="number" class="small-text" value="<?php echo esc_attr( (string) $order ); ?>" />
				<span class="description"><?php esc_html_e( 'Fallback order when a Placement has no drag order of its own.', 'collections-manager' ); ?></span>
			</p>
			<div class="cm-field">
				<span class="cm-field__label" id="cm-placements-label"><?php esc_html_e( 'Placement', 'collections-manager' ); ?></span>
				<?php collections_manager_render_placement_checkboxes( $is_edit ? $post->ID : 0 ); ?>
			</div>
		</div>
		<div class="cm-editor__aside">
			<div class="cm-field">
				<span class="cm-field__label" id="cm-cover-label"><?php esc_html_e( 'Cover image', 'collections-manager' ); ?></span>
				<input type="hidden" name="collection[image_id]" id="cm-image-id" value="<?php echo esc_attr( (string) $image ); ?>" />
				<div class="cm-image-preview">
					<?php
					if ( $image ) {
						echo wp_get_attachment_image( $image, 'medium' );
					}
					?>
				</div>
				<p class="cm-inline-actions">
					<button type="button" class="button cm-select-image"><?php esc_html_e( 'Select image', 'collections-manager' ); ?></button>
					<button type="button" class="button-link cm-remove-image" <?php echo $image ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'collections-manager' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Optional. Without a custom cover, the collection uses the latest item’s image.', 'collections-manager' ); ?></p>
			</div>
		</div>
	</div>
	<div class="cm-editor__items">
		<h3><?php esc_html_e( 'Items', 'collections-manager' ); ?></h3>
				<fieldset class="cm-source-toggle">
					<legend class="screen-reader-text"><?php esc_html_e( 'How this collection gets items', 'collections-manager' ); ?></legend>
					<label>
						<input type="radio" name="collection[source]" value="manual" <?php checked( $source, 'manual' ); ?> />
						<?php esc_html_e( 'Choose posts', 'collections-manager' ); ?>
					</label>
					<label>
						<input type="radio" name="collection[source]" value="query" <?php checked( $source, 'query' ); ?> />
						<?php esc_html_e( 'Latest from a category or tag', 'collections-manager' ); ?>
					</label>
					<label>
						<input type="radio" name="collection[source]" value="youtube" <?php checked( $source, 'youtube' ); ?> />
						<?php esc_html_e( 'YouTube channel', 'collections-manager' ); ?>
					</label>
					<label>
						<input type="radio" name="collection[source]" value="feed" <?php checked( $source, 'feed' ); ?> />
						<?php esc_html_e( 'WordPress site or XML feed', 'collections-manager' ); ?>
					</label>
				</fieldset>

				<div id="cm-panel-manual" class="cm-source-panel cm-picker" <?php echo 'manual' === $source ? '' : 'hidden'; ?>>
					<div class="cm-picker__filters">
						<label for="cm-manual-post-type" class="screen-reader-text"><?php esc_html_e( 'Post type', 'collections-manager' ); ?></label>
						<select id="cm-manual-post-type">
							<?php foreach ( $post_types as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<div id="cm-picker-taxes" class="cm-picker__taxes"></div>
						<label for="cm-post-search" class="screen-reader-text"><?php esc_html_e( 'Search posts', 'collections-manager' ); ?></label>
						<span class="cm-search-field" id="cm-post-search-wrap">
							<input type="search" id="cm-post-search" placeholder="<?php esc_attr_e( 'Search posts…', 'collections-manager' ); ?>" autocomplete="off" />
							<span class="cm-spinner" aria-hidden="true"></span>
						</span>
					</div>
					<div class="cm-picker__layout">
						<section class="cm-picker__browse" aria-label="<?php esc_attr_e( 'Posts', 'collections-manager' ); ?>">
							<div class="cm-picker__head">
								<strong><?php esc_html_e( 'Posts', 'collections-manager' ); ?></strong>
								<button type="button" class="button" id="cm-add-matching" hidden><?php esc_html_e( 'Add matching posts', 'collections-manager' ); ?></button>
							</div>
							<ul class="cm-picker__results" id="cm-search-results"></ul>
							<div class="cm-picker__footer">
								<button type="button" class="button" id="cm-load-more" hidden><?php esc_html_e( 'Load more', 'collections-manager' ); ?></button>
								<p class="description" id="cm-picker-status" role="status"></p>
							</div>
						</section>
						<section class="cm-picker__selected" aria-label="<?php esc_attr_e( 'In this collection', 'collections-manager' ); ?>">
							<div class="cm-picker__head">
								<strong>
									<?php esc_html_e( 'In this collection', 'collections-manager' ); ?>
									<span id="cm-selected-count"><?php echo esc_html( (string) count( $selected_posts ) ); ?></span>
								</strong>
								<button type="button" class="button-link" id="cm-clear-selected"><?php esc_html_e( 'Clear', 'collections-manager' ); ?></button>
							</div>
							<p class="cm-picker__empty" id="cm-selected-empty" <?php echo $selected_posts ? 'hidden' : ''; ?>><?php esc_html_e( 'Click a post to add it. The top of this list is the first slide.', 'collections-manager' ); ?></p>
							<ul class="cm-selected-posts" id="cm-selected-posts">
								<?php foreach ( $selected_posts as $item ) : ?>
									<?php collections_manager_render_selected_post( $item ); ?>
								<?php endforeach; ?>
							</ul>
							<p class="description"><?php esc_html_e( 'Drag to set the carousel order.', 'collections-manager' ); ?></p>
						</section>
					</div>
					<p class="description"><?php esc_html_e( 'Filter by a category or tag, then add the posts you want. For a collection that updates when new posts are published, use Latest from a category or tag.', 'collections-manager' ); ?></p>
				</div>

				<div id="cm-panel-query" class="cm-source-panel" <?php echo 'query' === $source ? '' : 'hidden'; ?>>
					<p>
						<label for="cm-query-post-type"><?php esc_html_e( 'Post type', 'collections-manager' ); ?></label><br />
						<select name="collection[query][post_type]" id="cm-query-post-type">
							<?php foreach ( $post_types as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $query['post_type'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="cm-query-taxonomy"><?php esc_html_e( 'Category or tag', 'collections-manager' ); ?></label><br />
						<select name="collection[query][taxonomy]" id="cm-query-taxonomy">
							<?php if ( empty( $query_taxes ) ) : ?>
								<option value=""><?php esc_html_e( 'None available', 'collections-manager' ); ?></option>
							<?php else : ?>
								<?php foreach ( $query_taxes as $tax_slug => $tax_label ) : ?>
									<option value="<?php echo esc_attr( $tax_slug ); ?>" <?php selected( $query['taxonomy'], $tax_slug ); ?>><?php echo esc_html( $tax_label ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</p>
					<?php collections_manager_render_query_term_picker( $query['taxonomy'], $query['terms'] ); ?>
					<div class="cm-query-preview" id="cm-query-preview" aria-live="polite"></div>
					<p>
						<label>
							<input type="checkbox" name="collection[query][unlimited]" id="cm-query-unlimited" value="1" <?php checked( ! empty( $query['unlimited'] ) ); ?> />
							<?php esc_html_e( 'Promote all matching items', 'collections-manager' ); ?>
						</label>
					</p>
					<p id="cm-query-limit-wrap" <?php echo ! empty( $query['unlimited'] ) ? 'hidden' : ''; ?>>
						<label for="cm-query-limit"><?php esc_html_e( 'Maximum items', 'collections-manager' ); ?></label><br />
						<input name="collection[query][limit]" id="cm-query-limit" type="number" min="1" max="50" class="small-text" value="<?php echo esc_attr( (string) $query['limit'] ); ?>" />
					</p>
					<p class="description">
						<?php
						if ( null !== $matched_count ) {
							printf(
								esc_html(
									/* translators: %d: number of matching posts. */
									_n( 'This query currently matches %d published item.', 'This query currently matches %d published items.', $matched_count, 'collections-manager' )
								),
								(int) $matched_count
							);
							echo ' ';
						}
						if ( ! empty( $query['unlimited'] ) ) {
							esc_html_e( 'The carousel shows the latest 12, then a View more slide to the rest. Newest items appear first.', 'collections-manager' );
						} else {
							esc_html_e( 'Newest items appear first. Leave this empty to include every item of this post type.', 'collections-manager' );
						}
						?>
					</p>
				</div>

				<div id="cm-panel-youtube" class="cm-source-panel" <?php echo 'youtube' === $source ? '' : 'hidden'; ?>>
					<?php if ( ! \CollectionsManager\YouTube\Client::has_api_key() ) : ?>
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: settings tab link */
									__( 'Latest uploads work from a channel ID without a key. Add a YouTube Data API key under %s to look up @handles, list playlists, and browse more videos.', 'collections-manager' ),
									'<a href="' . esc_url( collections_manager_dashboard_url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( 'Settings', 'collections-manager' ) . '</a>'
								),
								array( 'a' => array( 'href' => array() ) )
							);
							?>
						</p>
					<?php endif; ?>
					<p>
						<label for="cm-youtube-channel-input"><?php esc_html_e( 'Add a channel', 'collections-manager' ); ?></label><br />
						<input type="text" id="cm-youtube-channel-input" class="regular-text" placeholder="@WDWNT, channel URL, or UC…" />
						<button type="button" class="button" id="cm-youtube-add-channel"><?php esc_html_e( 'Add channel', 'collections-manager' ); ?></button>
					</p>
					<ul class="cm-selected-posts" id="cm-youtube-channels">
						<?php foreach ( $youtube['channels'] as $channel ) : ?>
							<li data-id="<?php echo esc_attr( $channel['id'] ); ?>">
								<input type="hidden" name="collection[youtube][channels][id][]" value="<?php echo esc_attr( $channel['id'] ); ?>" />
								<input type="hidden" name="collection[youtube][channels][title][]" value="<?php echo esc_attr( $channel['title'] ); ?>" />
								<span class="cm-selected-posts__title"><?php echo esc_html( $channel['title'] ); ?></span>
								<button type="button" class="button-link cm-remove-channel"><?php esc_html_e( 'Remove', 'collections-manager' ); ?></button>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php
					$youtube_ready = ! empty( $youtube['channels'] ) || ( 'playlist' === $youtube['mode'] && '' !== $youtube['playlist'] );
					?>
					<p id="cm-youtube-need-channel" class="description" <?php echo $youtube_ready ? 'hidden' : ''; ?>>
						<?php esc_html_e( 'Add a channel, then choose latest videos, a playlist, or specific videos from that channel.', 'collections-manager' ); ?>
					</p>
					<div id="cm-youtube-after-channel" <?php echo $youtube_ready ? '' : 'hidden'; ?>>
						<fieldset class="cm-source-toggle">
							<legend class="screen-reader-text"><?php esc_html_e( 'Videos from this channel', 'collections-manager' ); ?></legend>
							<label>
								<input type="radio" name="collection[youtube][mode]" value="latest" <?php checked( $youtube['mode'], 'latest' ); ?> />
								<?php esc_html_e( 'Latest videos', 'collections-manager' ); ?>
							</label>
							<label>
								<input type="radio" name="collection[youtube][mode]" value="playlist" <?php checked( $youtube['mode'], 'playlist' ); ?> />
								<?php esc_html_e( 'A playlist', 'collections-manager' ); ?>
							</label>
							<label>
								<input type="radio" name="collection[youtube][mode]" value="videos" <?php checked( $youtube['mode'], 'videos' ); ?> />
								<?php esc_html_e( 'Specific videos', 'collections-manager' ); ?>
							</label>
						</fieldset>

						<p>
							<label>
								<input type="checkbox" name="collection[youtube][unlimited]" id="cm-youtube-unlimited" value="1" <?php checked( ! empty( $youtube['unlimited'] ) ); ?> />
								<?php esc_html_e( 'Promote all matching videos', 'collections-manager' ); ?>
							</label>
						</p>
						<p id="cm-youtube-limit-wrap" <?php echo ( 'videos' === $youtube['mode'] || ! empty( $youtube['unlimited'] ) ) ? 'hidden' : ''; ?>>
							<label for="cm-youtube-limit"><?php esc_html_e( 'Maximum videos', 'collections-manager' ); ?></label><br />
							<input name="collection[youtube][limit]" id="cm-youtube-limit" type="number" min="1" max="50" class="small-text" value="<?php echo esc_attr( (string) $youtube['limit'] ); ?>" />
						</p>
						<p class="description" id="cm-youtube-unlimited-help" <?php echo empty( $youtube['unlimited'] ) ? 'hidden' : ''; ?>>
							<?php esc_html_e( 'The carousel shows the latest 12, then a View more slide to the channel or playlist.', 'collections-manager' ); ?>
						</p>

						<div id="cm-youtube-latest" <?php echo 'latest' === $youtube['mode'] ? '' : 'hidden'; ?>>
							<p class="description"><?php esc_html_e( 'The collection stays up to date with the newest public uploads. If you add more than one channel, videos are mixed by date.', 'collections-manager' ); ?></p>
							<p>
								<button type="button" class="button" id="cm-youtube-preview-latest"><?php esc_html_e( 'Check latest videos', 'collections-manager' ); ?></button>
								<span id="cm-youtube-latest-status" class="description" role="status"></span>
							</p>
						</div>

						<div id="cm-youtube-playlist" <?php echo 'playlist' === $youtube['mode'] ? '' : 'hidden'; ?>>
							<p>
								<label for="cm-youtube-playlist-channel"><?php esc_html_e( 'Channel', 'collections-manager' ); ?></label><br />
								<select id="cm-youtube-playlist-channel">
									<?php foreach ( $youtube['channels'] as $channel ) : ?>
										<option value="<?php echo esc_attr( $channel['id'] ); ?>"><?php echo esc_html( $channel['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p>
								<label for="cm-youtube-playlist-pick"><?php esc_html_e( 'Playlists on this channel', 'collections-manager' ); ?></label><br />
								<select id="cm-youtube-playlist-pick">
									<option value=""><?php esc_html_e( 'Choose a playlist…', 'collections-manager' ); ?></option>
								</select>
								<span id="cm-youtube-playlist-list-status" class="description" role="status"></span>
							</p>
							<p>
								<label for="cm-youtube-playlist-id"><?php esc_html_e( 'Or paste a playlist URL or ID', 'collections-manager' ); ?></label><br />
								<input name="collection[youtube][playlist]" id="cm-youtube-playlist-id" type="text" class="regular-text" value="<?php echo esc_attr( $youtube['playlist'] ); ?>" placeholder="PLxxxxxxxx or youtube.com/playlist?list=" />
							</p>
							<p>
								<button type="button" class="button" id="cm-youtube-preview"><?php esc_html_e( 'Check playlist', 'collections-manager' ); ?></button>
								<span id="cm-youtube-preview-status" class="description" role="status"></span>
							</p>
						</div>

						<div id="cm-youtube-videos" <?php echo 'videos' === $youtube['mode'] ? '' : 'hidden'; ?>>
							<div class="cm-search-row">
								<label for="cm-youtube-channel-pick"><?php esc_html_e( 'Channel to browse', 'collections-manager' ); ?></label>
								<select id="cm-youtube-channel-pick">
									<?php foreach ( $youtube['channels'] as $channel ) : ?>
										<option value="<?php echo esc_attr( $channel['id'] ); ?>"><?php echo esc_html( $channel['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="search" id="cm-youtube-video-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search videos…', 'collections-manager' ); ?>" autocomplete="off" />
								<button type="button" class="button" id="cm-youtube-load-videos"><?php esc_html_e( 'Load videos', 'collections-manager' ); ?></button>
							</div>
							<ul class="cm-search-results" id="cm-youtube-video-results" hidden></ul>
							<p>
								<button type="button" class="button-link" id="cm-youtube-more" hidden><?php esc_html_e( 'Load more', 'collections-manager' ); ?></button>
							</p>
							<ul class="cm-selected-posts" id="cm-youtube-selected">
								<?php foreach ( $youtube['videos'] as $video ) : ?>
									<li data-id="<?php echo esc_attr( $video['id'] ); ?>">
										<input type="hidden" name="collection[youtube][video_ids][]" value="<?php echo esc_attr( $video['id'] ); ?>" />
										<input type="hidden" name="collection[youtube][video_titles][]" value="<?php echo esc_attr( $video['title'] ); ?>" />
										<span class="cm-selected-posts__title"><?php echo esc_html( $video['title'] ? $video['title'] : $video['id'] ); ?></span>
										<button type="button" class="button-link cm-move-up"><?php esc_html_e( 'Up', 'collections-manager' ); ?></button>
										<button type="button" class="button-link cm-move-down"><?php esc_html_e( 'Down', 'collections-manager' ); ?></button>
										<button type="button" class="button-link cm-remove-video"><?php esc_html_e( 'Remove', 'collections-manager' ); ?></button>
									</li>
								<?php endforeach; ?>
							</ul>
							<p class="description"><?php esc_html_e( 'Order here is the order in the carousel. Videos open on YouTube.', 'collections-manager' ); ?></p>
						</div>
					</div>
				</div>

				<div id="cm-panel-feed" class="cm-source-panel" <?php echo 'feed' === $source ? '' : 'hidden'; ?>>
					<p>
						<label for="cm-feed-url"><?php esc_html_e( 'Site or feed URL', 'collections-manager' ); ?></label><br />
						<input name="collection[feed][url]" id="cm-feed-url" type="url" class="regular-text" value="<?php echo esc_attr( $feed['url'] ); ?>" placeholder="https://podcasts.wdwnt.com" />
					</p>
					<input type="hidden" name="collection[feed][kind]" id="cm-feed-kind" value="<?php echo esc_attr( $feed['kind'] ); ?>" />
					<input type="hidden" name="collection[feed][category_slug]" id="cm-feed-category-slug" value="<?php echo esc_attr( $feed['category_slug'] ); ?>" />
					<p>
						<button type="button" class="button" id="cm-feed-discover"><?php esc_html_e( 'Detect site', 'collections-manager' ); ?></button>
						<span id="cm-feed-discover-status" class="description" role="status"></span>
					</p>
					<div id="cm-feed-rest-fields" <?php echo 'rss' === $feed['kind'] ? 'hidden' : ''; ?>>
						<p>
							<label for="cm-feed-rest-base"><?php esc_html_e( 'Post type', 'collections-manager' ); ?></label><br />
							<select name="collection[feed][rest_base]" id="cm-feed-rest-base">
								<option value="<?php echo esc_attr( $feed['rest_base'] ); ?>" selected><?php echo esc_html( $feed['rest_base'] ); ?></option>
							</select>
						</p>
						<p>
							<label for="cm-feed-category"><?php esc_html_e( 'Category', 'collections-manager' ); ?></label><br />
							<select name="collection[feed][category_id]" id="cm-feed-category">
								<option value="0"><?php esc_html_e( 'All categories', 'collections-manager' ); ?></option>
								<?php if ( $feed['category_id'] ) : ?>
									<option value="<?php echo esc_attr( (string) $feed['category_id'] ); ?>" selected><?php echo esc_html( $feed['category_slug'] ? $feed['category_slug'] : (string) $feed['category_id'] ); ?></option>
								<?php endif; ?>
							</select>
						</p>
					</div>
					<p>
						<label>
							<input type="checkbox" name="collection[feed][unlimited]" id="cm-feed-unlimited" value="1" <?php checked( ! empty( $feed['unlimited'] ) ); ?> />
							<?php esc_html_e( 'Promote all matching items', 'collections-manager' ); ?>
						</label>
					</p>
					<p id="cm-feed-limit-wrap" <?php echo ! empty( $feed['unlimited'] ) ? 'hidden' : ''; ?>>
						<label for="cm-feed-limit"><?php esc_html_e( 'Maximum items', 'collections-manager' ); ?></label><br />
						<input name="collection[feed][limit]" id="cm-feed-limit" type="number" min="1" max="50" class="small-text" value="<?php echo esc_attr( (string) $feed['limit'] ); ?>" />
					</p>
					<p class="description" <?php echo empty( $feed['unlimited'] ) ? 'hidden' : ''; ?> id="cm-feed-unlimited-help">
						<?php esc_html_e( 'The carousel shows the latest 12, then a View more slide to the source site.', 'collections-manager' ); ?>
					</p>
					<p>
						<button type="button" class="button" id="cm-feed-preview"><?php esc_html_e( 'Check feed', 'collections-manager' ); ?></button>
						<span id="cm-feed-preview-status" class="description" role="status"></span>
					</p>
					<p class="description"><?php esc_html_e( 'Paste a WordPress homepage, a /wp-json/ URL, or an RSS/Atom feed (including category feeds). Items open on the remote site.', 'collections-manager' ); ?></p>
				</div>
	</div>
	<?php
	echo '<div class="cm-editor__bar">';
	submit_button( $is_edit ? __( 'Save collection', 'collections-manager' ) : __( 'Add collection', 'collections-manager' ), 'primary', 'collections_manager_save' );
	echo '</div>';
	echo '</form></div>';
}

/**
 * Searchable category or tag picker for a query collection.
 *
 * Selected terms are chips. The script fills matches from search so large
 * taxonomies (tens of thousands of tags) are never printed in full.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param int[]  $selected Selected term IDs.
 */
function collections_manager_render_query_term_picker( $taxonomy, $selected ) {
	$selected = array_map( 'absint', (array) $selected );
	$taxes    = $taxonomy ? get_taxonomy( $taxonomy ) : null;
	$label    = ( $taxes && ! is_wp_error( $taxes ) && ! empty( $taxes->labels->name ) ) ? $taxes->labels->name : __( 'terms', 'collections-manager' );

	echo '<div class="cm-term-picker" id="cm-query-terms">';
	if ( ! $taxonomy ) {
		echo '<p class="description">' . esc_html__( 'This post type has no categories or tags.', 'collections-manager' ) . '</p>';
		echo '</div>';
		return;
	}

	echo '<div class="cm-term-picker__selected" id="cm-query-selected-terms">';
	foreach ( $selected as $term_id ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		echo '<span class="cm-chip">';
		echo '<input type="hidden" name="collection[query][terms][]" value="' . esc_attr( (string) $term->term_id ) . '" />';
		echo '<span class="cm-chip__label">' . esc_html( $term->name ) . '</span>';
		echo '<button type="button" class="button-link cm-chip__remove" aria-label="' . esc_attr__( 'Remove', 'collections-manager' ) . '">&times;</button>';
		echo '</span>';
	}
	echo '</div>';
	echo '<span class="cm-search-field">';
	printf(
		'<input type="search" id="cm-query-term-search" placeholder="%s" autocomplete="off" />',
		esc_attr(
			sprintf(
				/* translators: %s: taxonomy label, such as categories. */
				__( 'Search %s…', 'collections-manager' ),
				strtolower( $label )
			)
		)
	);
	echo '<span class="cm-spinner" aria-hidden="true"></span></span>';
	echo '<ul class="cm-term-results" id="cm-query-term-results" hidden></ul>';
	echo '</div>';
}
