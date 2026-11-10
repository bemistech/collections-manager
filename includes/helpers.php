<?php
/**
 * Shared collection queries and payload shaping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function collections_manager_get_post_ids( $collection_id ) {
	$ids = get_post_meta( $collection_id, COLLECTIONS_MANAGER_POSTS_META, true );
	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

function collections_manager_save_post_ids( $collection_id, $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	update_post_meta( $collection_id, COLLECTIONS_MANAGER_POSTS_META, $ids );
}

/**
 * Public post types a collection may include.
 *
 * @return array<string, string> Slug => label.
 */
function collections_manager_collectable_post_types() {
	$types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $types['attachment'], $types[ COLLECTIONS_MANAGER_POST_TYPE ] );

	$out = array();
	foreach ( $types as $name => $object ) {
		$out[ $name ] = $object->labels->name ? $object->labels->name : $name;
	}

	/**
	 * Filter post types available for collections.
	 *
	 * @param array<string, string> $out Slug => label.
	 */
	return apply_filters( 'collections_manager_collectable_post_types', $out );
}

/**
 * Public taxonomies editors can filter by for a post type.
 *
 * @param string $post_type Post type slug.
 * @return array<string, string> Taxonomy => label.
 */
function collections_manager_taxonomies_for_post_type( $post_type ) {
	$out = array();
	foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
		if ( ! $taxonomy instanceof WP_Taxonomy ) {
			continue;
		}
		if ( ! $taxonomy->public || ! $taxonomy->show_ui || 'post_format' === $taxonomy->name ) {
			continue;
		}
		$out[ $taxonomy->name ] = $taxonomy->labels->name ? $taxonomy->labels->name : $taxonomy->name;
	}

	$ordered   = array();
	$preferred = array( 'category', 'post_tag' );
	foreach ( $preferred as $name ) {
		if ( isset( $out[ $name ] ) ) {
			$ordered[ $name ] = $out[ $name ];
		}
	}
	foreach ( $out as $name => $label ) {
		if ( ! isset( $ordered[ $name ] ) ) {
			$ordered[ $name ] = $label;
		}
	}

	return $ordered;
}

/**
 * How many cards the homepage carousel shows before View more.
 *
 * @return int
 */
function collections_manager_browser_preview_limit() {
	return max( 1, (int) apply_filters( 'collections_manager_browser_preview_limit', 12 ) );
}

/**
 * @param mixed $settings Query, YouTube, or feed settings.
 * @return bool
 */
function collections_manager_is_unlimited( $settings ) {
	return is_array( $settings ) && ! empty( $settings['unlimited'] );
}

/**
 * Fetch size for a source: preview size when unlimited, otherwise the stored cap.
 *
 * @param array $settings Source settings.
 * @param int   $fallback Default when limit is missing.
 * @return int
 */
function collections_manager_source_fetch_limit( $settings, $fallback = 12 ) {
	if ( collections_manager_is_unlimited( $settings ) ) {
		return collections_manager_browser_preview_limit();
	}

	$limit = absint( $settings['limit'] ?? $fallback );
	if ( $limit < 1 ) {
		$limit = (int) $fallback;
	}
	if ( $limit > 50 ) {
		$limit = 50;
	}

	return $limit;
}

/**
 * @return array{post_type:string,taxonomy:string,terms:int[],limit:int,unlimited:bool}
 */
function collections_manager_default_query() {
	return array(
		'post_type' => 'post',
		'taxonomy'  => 'category',
		'terms'     => array(),
		'limit'     => 12,
		'unlimited' => false,
	);
}

/**
 * @param mixed $input Raw query settings.
 * @return array{post_type:string,taxonomy:string,terms:int[],limit:int,unlimited:bool}
 */
function collections_manager_sanitize_query( $input ) {
	$input     = is_array( $input ) ? $input : array();
	$types     = collections_manager_collectable_post_types();
	$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
	if ( ! isset( $types[ $post_type ] ) ) {
		$post_type = isset( $types['post'] ) ? 'post' : (string) array_key_first( $types );
	}

	$taxes    = $post_type ? collections_manager_taxonomies_for_post_type( $post_type ) : array();
	$taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
	if ( $taxonomy && ! isset( $taxes[ $taxonomy ] ) ) {
		$taxonomy = '';
	}
	if ( ! $taxonomy ) {
		$taxonomy = isset( $taxes['category'] ) ? 'category' : (string) array_key_first( $taxes );
	}

	$terms = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['terms'] ?? array() ) ) ) ) );
	if ( $taxonomy && $terms ) {
		$valid = array();
		foreach ( $terms as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$valid[] = $term_id;
			}
		}
		$terms = $valid;
	} else {
		$terms = array();
	}

	$limit = absint( $input['limit'] ?? 12 );
	if ( $limit < 1 ) {
		$limit = 12;
	}
	if ( $limit > 50 ) {
		$limit = 50;
	}

	return array(
		'post_type' => $post_type ? $post_type : 'post',
		'taxonomy'  => $taxonomy ? $taxonomy : '',
		'terms'     => $terms,
		'limit'     => $limit,
		'unlimited' => ! empty( $input['unlimited'] ),
	);
}

/**
 * @param int $collection_id Collection ID.
 * @return string `manual`, `query`, or `youtube`.
 */
function collections_manager_get_source( $collection_id ) {
	$source = get_post_meta( $collection_id, COLLECTIONS_MANAGER_SOURCE_META, true );
	if ( in_array( $source, array( 'query', 'youtube', 'feed' ), true ) ) {
		return $source;
	}

	return 'manual';
}

/**
 * @param int $collection_id Collection ID.
 * @return array{post_type:string,taxonomy:string,terms:int[],limit:int,unlimited:bool}
 */
function collections_manager_get_query( $collection_id ) {
	$stored = get_post_meta( $collection_id, COLLECTIONS_MANAGER_QUERY_META, true );
	return collections_manager_sanitize_query( is_array( $stored ) ? $stored : array() );
}

/**
 * @param int   $collection_id Collection ID.
 * @param mixed $query         Raw query settings.
 */
function collections_manager_save_query( $collection_id, $query ) {
	update_post_meta( $collection_id, COLLECTIONS_MANAGER_QUERY_META, collections_manager_sanitize_query( $query ) );
}

/**
 * @return array{mode:string,playlist:string,limit:int,unlimited:bool,channels:array,videos:array}
 */
function collections_manager_default_youtube() {
	return array(
		'mode'      => 'latest',
		'playlist'  => '',
		'limit'     => 24,
		'unlimited' => false,
		'channels'  => array(),
		'videos'    => array(),
	);
}

/**
 * @param mixed $input Raw YouTube settings.
 * @return array{mode:string,playlist:string,limit:int,unlimited:bool,channels:array,videos:array}
 */
function collections_manager_sanitize_youtube( $input ) {
	$input   = is_array( $input ) ? $input : array();
	$allowed = array( 'latest', 'playlist', 'videos' );
	$mode    = isset( $input['mode'] ) ? (string) $input['mode'] : 'latest';
	if ( ! in_array( $mode, $allowed, true ) ) {
		$mode = 'latest';
	}

	$playlist = \CollectionsManager\YouTube\Client::parse_playlist_id( (string) ( $input['playlist'] ?? '' ) );

	$limit = absint( $input['limit'] ?? 24 );
	if ( $limit < 1 ) {
		$limit = 24;
	}
	if ( $limit > 50 ) {
		$limit = 50;
	}

	$channels = array();
	$raw_channels = isset( $input['channels'] ) && is_array( $input['channels'] ) ? $input['channels'] : array();
	if ( isset( $raw_channels['id'] ) && is_array( $raw_channels['id'] ) ) {
		$ids    = $raw_channels['id'];
		$titles = isset( $raw_channels['title'] ) && is_array( $raw_channels['title'] ) ? $raw_channels['title'] : array();
		foreach ( $ids as $index => $channel_id ) {
			$channel_id = sanitize_text_field( (string) $channel_id );
			if ( ! preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $channel_id ) ) {
				continue;
			}
			$title = isset( $titles[ $index ] ) ? sanitize_text_field( (string) $titles[ $index ] ) : $channel_id;
			$channels[ $channel_id ] = array(
				'id'    => $channel_id,
				'title' => $title ? $title : $channel_id,
			);
		}
	} else {
		foreach ( $raw_channels as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$channel_id = sanitize_text_field( (string) ( $row['id'] ?? '' ) );
			if ( ! preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $channel_id ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
			$channels[ $channel_id ] = array(
				'id'    => $channel_id,
				'title' => $title ? $title : $channel_id,
			);
		}
	}

	$videos = array();
	$ids    = isset( $input['video_ids'] ) ? (array) $input['video_ids'] : array();
	$titles = isset( $input['video_titles'] ) ? (array) $input['video_titles'] : array();
	if ( isset( $input['videos'] ) && is_array( $input['videos'] ) && empty( $ids ) ) {
		foreach ( $input['videos'] as $row ) {
			if ( is_array( $row ) ) {
				$ids[]    = $row['id'] ?? '';
				$titles[] = $row['title'] ?? '';
			}
		}
	}

	foreach ( $ids as $index => $video_id ) {
		$video_id = \CollectionsManager\YouTube\Client::parse_video_id( (string) $video_id );
		if ( ! $video_id ) {
			continue;
		}
		$title = isset( $titles[ $index ] ) ? sanitize_text_field( (string) $titles[ $index ] ) : '';
		$videos[ $video_id ] = array(
			'id'    => $video_id,
			'title' => $title,
		);
	}

	return array(
		'mode'      => $mode,
		'playlist'  => $playlist,
		'limit'     => $limit,
		'unlimited' => ! empty( $input['unlimited'] ),
		'channels'  => array_values( $channels ),
		'videos'    => array_values( $videos ),
	);
}

/**
 * @param int $collection_id Collection ID.
 * @return array{mode:string,playlist:string,limit:int,unlimited:bool,channels:array,videos:array}
 */
function collections_manager_get_youtube( $collection_id ) {
	$stored = get_post_meta( $collection_id, COLLECTIONS_MANAGER_YOUTUBE_META, true );
	return collections_manager_sanitize_youtube( is_array( $stored ) ? $stored : array() );
}

/**
 * @param int   $collection_id Collection ID.
 * @param mixed $youtube       Raw settings.
 */
function collections_manager_save_youtube( $collection_id, $youtube ) {
	update_post_meta( $collection_id, COLLECTIONS_MANAGER_YOUTUBE_META, collections_manager_sanitize_youtube( $youtube ) );
}

/**
 * @return array{url:string,kind:string,rest_base:string,category_id:int,category_slug:string,limit:int,unlimited:bool}
 */
function collections_manager_default_feed() {
	return array(
		'url'           => '',
		'kind'          => 'auto',
		'rest_base'     => 'posts',
		'category_id'   => 0,
		'category_slug' => '',
		'limit'         => 12,
		'unlimited'     => false,
	);
}

/**
 * @param mixed $input Raw feed settings.
 * @return array{url:string,kind:string,rest_base:string,category_id:int,category_slug:string,limit:int}
 */
function collections_manager_sanitize_feed( $input ) {
	$input = is_array( $input ) ? $input : array();
	$kind  = sanitize_key( (string) ( $input['kind'] ?? 'auto' ) );
	if ( ! in_array( $kind, array( 'auto', 'rest', 'rss' ), true ) ) {
		$kind = 'auto';
	}

	$rest_base = sanitize_key( (string) ( $input['rest_base'] ?? 'posts' ) );
	if ( ! $rest_base ) {
		$rest_base = 'posts';
	}

	$limit = absint( $input['limit'] ?? 12 );
	if ( $limit < 1 ) {
		$limit = 12;
	}
	if ( $limit > 50 ) {
		$limit = 50;
	}

	return array(
		'url'           => \CollectionsManager\Feed\Client::sanitize_url( (string) ( $input['url'] ?? '' ) ),
		'kind'          => $kind,
		'rest_base'     => $rest_base,
		'category_id'   => absint( $input['category_id'] ?? 0 ),
		'category_slug' => sanitize_title( (string) ( $input['category_slug'] ?? '' ) ),
		'limit'         => $limit,
		'unlimited'     => ! empty( $input['unlimited'] ),
	);
}

/**
 * @param int $collection_id Collection ID.
 * @return array{url:string,kind:string,rest_base:string,category_id:int,category_slug:string,limit:int,unlimited:bool}
 */
function collections_manager_get_feed( $collection_id ) {
	$stored = get_post_meta( $collection_id, COLLECTIONS_MANAGER_FEED_META, true );
	return collections_manager_sanitize_feed( is_array( $stored ) ? $stored : array() );
}

/**
 * @param int   $collection_id Collection ID.
 * @param mixed $feed          Raw settings.
 */
function collections_manager_save_feed( $collection_id, $feed ) {
	update_post_meta( $collection_id, COLLECTIONS_MANAGER_FEED_META, collections_manager_sanitize_feed( $feed ) );
}

/**
 * Query collections share one WP_Query per request so found_posts and cards stay in sync.
 *
 * @param int $collection_id Collection ID.
 * @return array{posts:WP_Post[],found:int}
 */
function collections_manager_query_collection_result( $collection_id ) {
	static $cache = array();

	$collection_id = (int) $collection_id;
	if ( isset( $cache[ $collection_id ] ) ) {
		return $cache[ $collection_id ];
	}

	$query      = collections_manager_get_query( $collection_id );
	$unlimited  = collections_manager_is_unlimited( $query );
	$args       = array(
		'post_type'           => $query['post_type'],
		'post_status'         => 'publish',
		'posts_per_page'      => collections_manager_source_fetch_limit( $query, 12 ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => ! $unlimited,
	);
	if ( $query['taxonomy'] && ! empty( $query['terms'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy'         => $query['taxonomy'],
				'field'            => 'term_id',
				'terms'            => $query['terms'],
				'include_children' => true,
				'operator'         => 'IN',
			),
		);
	}

	$wp_query = new WP_Query( $args );
	$posts    = is_array( $wp_query->posts ) ? $wp_query->posts : array();
	$cache[ $collection_id ] = array(
		'posts' => $posts,
		'found' => $unlimited ? (int) $wp_query->found_posts : count( $posts ),
	);

	return $cache[ $collection_id ];
}

/**
 * Archive, channel, playlist, or feed URL for a View more slide. Empty when capped.
 *
 * @param int $collection_id Collection ID.
 * @return string
 */
function collections_manager_collection_more_url( $collection_id ) {
	$source = collections_manager_get_source( $collection_id );
	if ( 'query' === $source ) {
		$query = collections_manager_get_query( $collection_id );
		if ( ! collections_manager_is_unlimited( $query ) ) {
			return '';
		}
		return collections_manager_query_archive_url( $query );
	}
	if ( 'youtube' === $source ) {
		return collections_manager_youtube_more_url( collections_manager_get_youtube( $collection_id ) );
	}
	if ( 'feed' === $source ) {
		$feed = collections_manager_get_feed( $collection_id );
		if ( ! collections_manager_is_unlimited( $feed ) ) {
			return '';
		}
		return (string) $feed['url'];
	}

	return '';
}

/**
 * @param array $query Sanitized query settings.
 * @return string
 */
function collections_manager_query_archive_url( $query ) {
	$query = is_array( $query ) ? $query : array();
	if ( ! empty( $query['taxonomy'] ) && ! empty( $query['terms'][0] ) ) {
		$link = get_term_link( (int) $query['terms'][0], $query['taxonomy'] );
		if ( ! is_wp_error( $link ) && $link ) {
			return (string) $link;
		}
	}

	$post_type = isset( $query['post_type'] ) ? (string) $query['post_type'] : 'post';
	if ( 'post' === $post_type ) {
		$page = (int) get_option( 'page_for_posts' );
		return $page ? (string) get_permalink( $page ) : home_url( '/' );
	}

	$archive = get_post_type_archive_link( $post_type );
	return $archive ? (string) $archive : '';
}

/**
 * @param array $settings Sanitized YouTube settings.
 * @return string
 */
function collections_manager_youtube_more_url( $settings ) {
	$settings = is_array( $settings ) ? $settings : array();
	if ( ! collections_manager_is_unlimited( $settings ) || 'videos' === ( $settings['mode'] ?? '' ) ) {
		return '';
	}
	if ( 'playlist' === ( $settings['mode'] ?? '' ) && ! empty( $settings['playlist'] ) ) {
		return 'https://www.youtube.com/playlist?list=' . rawurlencode( (string) $settings['playlist'] );
	}
	$channel_id = isset( $settings['channels'][0]['id'] ) ? (string) $settings['channels'][0]['id'] : '';
	if ( $channel_id ) {
		return 'https://www.youtube.com/channel/' . rawurlencode( $channel_id );
	}

	return '';
}

/**
 * @param int $collection_id Collection ID.
 * @return int
 */
function collections_manager_collection_item_count( $collection_id ) {
	if ( 'query' === collections_manager_get_source( $collection_id ) ) {
		$result = collections_manager_query_collection_result( $collection_id );
		return (int) $result['found'];
	}

	return count( collections_manager_get_collection_items( $collection_id ) );
}

/**
 * Published collections in dashboard order.
 *
 * @param array $query_args Extra get_posts arguments.
 * @return WP_Post[]
 */
function collections_manager_get_collections( $query_args = array() ) {
	$args = wp_parse_args(
		is_array( $query_args ) ? $query_args : array(),
		array(
			'post_type'      => COLLECTIONS_MANAGER_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => true,
		)
	);

	return get_posts( $args );
}

/**
 * Posts in a collection. Manual lists keep saved order; query lists are newest first.
 *
 * @param int $collection_id Collection ID.
 * @return WP_Post[]
 */
function collections_manager_get_collection_posts( $collection_id ) {
	$source = collections_manager_get_source( $collection_id );
	if ( in_array( $source, array( 'youtube', 'feed' ), true ) ) {
		return array();
	}

	if ( 'query' === collections_manager_get_source( $collection_id ) ) {
		$result = collections_manager_query_collection_result( $collection_id );
		return $result['posts'];
	}

	$ids = collections_manager_get_post_ids( $collection_id );
	if ( empty( $ids ) ) {
		return array();
	}

	$types = array_keys( collections_manager_collectable_post_types() );
	if ( empty( $types ) ) {
		$types = array( 'post' );
	}

	$posts = get_posts(
		array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $ids ),
			'no_found_rows'  => true,
		)
	);

	return is_array( $posts ) ? $posts : array();
}

/**
 * True while Gutenberg is rendering a block preview over REST.
 *
 * Editor previews skip remote YouTube and feed lookups. Those calls are slow,
 * and a feed pointed at this site counts against the same rate limit as the editor.
 *
 * @return bool
 */
function collections_manager_is_block_preview() {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return false;
	}

	$route = '';
	if ( isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
	}

	return '' !== $route && false !== strpos( $route, '/block-renderer/' );
}

/**
 * Card payloads for a collection (WordPress posts or YouTube videos).
 *
 * @param int $collection_id Collection ID.
 * @return array<int, array<string, mixed>>
 */
function collections_manager_get_collection_items( $collection_id ) {
	$source = collections_manager_get_source( $collection_id );
	if ( collections_manager_is_block_preview() && in_array( $source, array( 'youtube', 'feed' ), true ) ) {
		return array();
	}
	if ( 'youtube' === $source ) {
		return \CollectionsManager\YouTube\Client::items_for_collection( collections_manager_get_youtube( $collection_id ) );
	}
	if ( 'feed' === $source ) {
		return \CollectionsManager\Feed\Client::items_for_collection( collections_manager_get_feed( $collection_id ) );
	}

	$items = array();
	foreach ( collections_manager_get_collection_posts( $collection_id ) as $post ) {
		$image   = collections_manager_card_image_url( $post->ID );
		$items[] = array(
			'id'       => $post->ID,
			'title'    => get_the_title( $post ),
			'url'      => get_permalink( $post ),
			'image'    => $image,
			'image_id' => (int) get_post_thumbnail_id( $post ),
			'date'     => get_the_date( '', $post ),
			'external' => false,
		);
	}

	return $items;
}

/**
 * Card and cover markup. Both request the 9:16 crop and a srcset when the file is in the library.
 *
 * @param int    $attachment_id Attachment ID, or 0 when only a URL is known.
 * @param string $fallback_url  Image URL used when there is no attachment.
 * @return string Escaped image HTML.
 */
function collections_manager_card_image_html( $attachment_id, $fallback_url = '' ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id && is_string( $fallback_url ) && '' !== $fallback_url ) {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = wp_parse_url( $fallback_url, PHP_URL_HOST );
		if ( is_string( $home ) && is_string( $host ) && 0 === strcasecmp( $home, $host ) ) {
			$attachment_id = (int) attachment_url_to_postid( $fallback_url );
		}
	}
	if ( $attachment_id ) {
		// Ask for the 9:16 crop. WordPress serves the full file, with srcset, when that subsize was never generated.
		$html = wp_get_attachment_image(
			$attachment_id,
			'cm-portrait',
			false,
			array(
				'alt'      => '',
				'sizes'    => '(max-width:781px) 110vw, 280px',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		if ( is_string( $html ) && '' !== $html ) {
			return $html;
		}
	}
	if ( ! is_string( $fallback_url ) || '' === $fallback_url ) {
		return '';
	}
	return '<img src="' . esc_url( $fallback_url ) . '" alt="" width="720" height="1280" sizes="(max-width:781px) 110vw, 280px" loading="lazy" decoding="async" />';
}

function collections_manager_card_image_url( $post_id, $size = 'medium_large' ) {
	$url = get_the_post_thumbnail_url( $post_id, $size );
	if ( $url ) {
		return $url;
	}

	return '';
}

function collections_manager_collection_cover_url( $collection ) {
	$collection = get_post( $collection );
	if ( ! $collection ) {
		return '';
	}

	$url = get_the_post_thumbnail_url( $collection, 'medium_large' );
	if ( $url ) {
		return $url;
	}

	foreach ( collections_manager_get_collection_items( $collection->ID ) as $item ) {
		if ( ! empty( $item['image'] ) ) {
			return (string) $item['image'];
		}
	}

	return '';
}

function collections_manager_dashboard_url( $args = array() ) {
	return add_query_arg(
		array_merge(
			array( 'page' => 'collections_manager' ),
			$args
		),
		admin_url( 'admin.php' )
	);
}

/**
 * Shape one collection for the browser block, including empty collections.
 *
 * @param WP_Post $collection Collection post.
 * @return array<string, mixed>
 */
function collections_manager_browser_item( $collection ) {
	$items = array();
	$cover = get_the_post_thumbnail_url( $collection, 'medium_large' );
	foreach ( collections_manager_get_collection_items( $collection->ID ) as $item ) {
		$image   = isset( $item['image'] ) ? (string) $item['image'] : '';
		$items[] = array(
			'id'       => $item['id'],
			'title'    => (string) $item['title'],
			'url'      => (string) $item['url'],
			'image'    => $image,
			'image_id' => isset( $item['image_id'] ) ? (int) $item['image_id'] : 0,
			'date'     => isset( $item['date'] ) ? (string) $item['date'] : '',
			'external' => ! empty( $item['external'] ),
		);
		if ( ! $cover && $image ) {
			$cover = $image;
		}
	}

	$source   = collections_manager_get_source( $collection->ID );
	$more_url = collections_manager_collection_more_url( $collection->ID );
	$preview  = collections_manager_browser_preview_limit();
	if ( $more_url ) {
		if ( 'query' === $source ) {
			$found = collections_manager_collection_item_count( $collection->ID );
			if ( $found <= count( $items ) ) {
				$more_url = '';
			}
		} elseif ( count( $items ) < $preview ) {
			$more_url = '';
		}
	}

	return array(
		'id'          => $collection->ID,
		'title'       => get_the_title( $collection ),
		'description' => $collection->post_excerpt,
		'cover'       => $cover ? $cover : '',
		'cover_id'    => (int) get_post_thumbnail_id( $collection ),
		'count'       => count( $items ),
		'source'      => $source,
		'more_url'    => $more_url,
		'items'       => $items,
	);
}

/**
 * Data the browser block and view script need.
 *
 * A placement slug limits the row to published collections in that term.
 * An unknown slug renders nothing. With no placement, the block keeps the
 * show-all or checklist behavior.
 *
 * @param array $attributes Block attributes.
 * @return array<int, array<string, mixed>>
 */
function collections_manager_get_browser_payload( $attributes = array() ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$placement  = isset( $attributes['placement'] ) ? sanitize_key( (string) $attributes['placement'] ) : '';
	$show_all   = ! array_key_exists( 'showAllCollections', $attributes ) || (bool) $attributes['showAllCollections'];
	$payload    = array();

	if ( '' !== $placement ) {
		$term = get_term_by( 'slug', $placement, COLLECTIONS_MANAGER_PLACEMENT );
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		foreach ( \CollectionsManager\PlacementOrder::collection_ids( $term ) as $id ) {
			$collection = get_post( $id );
			if ( ! $collection instanceof WP_Post || COLLECTIONS_MANAGER_POST_TYPE !== $collection->post_type || 'publish' !== $collection->post_status ) {
				continue;
			}
			$payload[] = collections_manager_browser_item( $collection );
		}
		return $payload;
	}

	if ( $show_all ) {
		foreach ( collections_manager_get_collections() as $collection ) {
			$payload[] = collections_manager_browser_item( $collection );
		}
		return $payload;
	}

	$ids = array_values(
		array_unique(
			array_filter(
				array_map( 'absint', (array) ( $attributes['collectionIds'] ?? array() ) )
			)
		)
	);

	foreach ( $ids as $id ) {
		$collection = get_post( $id );
		if ( ! $collection || COLLECTIONS_MANAGER_POST_TYPE !== $collection->post_type || 'publish' !== $collection->post_status ) {
			continue;
		}
		$payload[] = collections_manager_browser_item( $collection );
	}

	return $payload;
}

/**
 * How many collection tiles share the visible row. 0 means fill the row.
 *
 * @param array $attributes Block attributes.
 * @param int   $total      Collection count.
 * @return int
 */
function collections_manager_browser_visible_count( $attributes, $total ) {
	$total   = max( 0, (int) $total );
	$requested = array_key_exists( 'visibleCount', $attributes ) ? (int) $attributes['visibleCount'] : 0;
	if ( $total < 1 ) {
		return 1;
	}
	if ( $requested < 1 ) {
		return $total;
	}
	return max( 1, min( 12, $requested, $total ) );
}
