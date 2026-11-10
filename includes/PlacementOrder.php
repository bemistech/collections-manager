<?php
/**
 * Ordered collection IDs for one placement.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores one ordered ID list on the placement term and resolves published collections.
 */
class PlacementOrder {

	/**
	 * Term meta key. A single list, not per-collection order meta.
	 */
	public const META_KEY = 'placement_order';

	/**
	 * Object-cache group for resolved ID lists.
	 */
	public const CACHE_GROUP = 'collection_placement';

	/**
	 * Upper bound for a placement query.
	 */
	const QUERY_LIMIT = 100;

	/**
	 * Register meta and cache invalidation.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 15 );
		add_action( 'set_object_terms', array( __CLASS__, 'invalidate_object_terms' ), 10, 6 );
		add_action( 'save_post_' . COLLECTIONS_MANAGER_POST_TYPE, array( __CLASS__, 'invalidate_collection' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'invalidate_collection' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'invalidate_collection' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'invalidate_collection' ) );
		add_action( 'edited_' . COLLECTIONS_MANAGER_PLACEMENT, array( __CLASS__, 'invalidate_term' ), 10, 3 );
		add_action( 'delete_' . COLLECTIONS_MANAGER_PLACEMENT, array( __CLASS__, 'invalidate_term' ), 10, 3 );
	}

	/**
	 * Keep the order list off the public REST API.
	 */
	public static function register_meta() {
		register_term_meta(
			COLLECTIONS_MANAGER_PLACEMENT,
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_stored' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_edit_meta() {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Stored value is a comma-separated list of IDs.
	 *
	 * @param mixed $value Raw meta.
	 * @return string
	 */
	public static function sanitize_stored( $value ) {
		$ids = self::parse_ids( $value );
		return implode( ',', $ids );
	}

	/**
	 * Saved order for a placement. IDs that are no longer published collections are dropped.
	 *
	 * @param int $term_id Placement term ID.
	 * @return int[]
	 */
	public static function order_ids( $term_id ) {
		$stored = get_term_meta( (int) $term_id, self::META_KEY, true );
		return self::published_collection_ids( self::parse_ids( $stored ) );
	}

	/**
	 * Replace the order list and clear the resolved cache.
	 *
	 * Only published collections that belong to the placement are kept, in the submitted order.
	 * Assigned collections missing from the submission stay at the end.
	 *
	 * @param \WP_Term $term Placement.
	 * @param mixed    $ids  Submitted IDs.
	 * @return int[]
	 */
	public static function set_ids( $term, $ids ) {
		$term_id  = (int) $term->term_id;
		$members  = self::member_ids( $term );
		$member   = array_fill_keys( $members, true );
		$ordered  = array();
		$seen     = array();

		foreach ( self::published_collection_ids( self::parse_ids( $ids ) ) as $id ) {
			if ( empty( $member[ $id ] ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$ordered[]  = $id;
			$seen[ $id ] = true;
		}

		foreach ( $members as $id ) {
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$ordered[] = $id;
		}

		update_term_meta( $term_id, self::META_KEY, implode( ',', $ordered ) );
		self::invalidate( $term_id );

		return $ordered;
	}

	/**
	 * Published collections in a placement, in placement order.
	 *
	 * @param \WP_Term $term Placement term.
	 * @return int[]
	 */
	public static function collection_ids( $term ) {
		$term_id = (int) $term->term_id;
		$cached  = wp_cache_get( self::cache_key( $term_id ), self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return array_map( 'intval', $cached );
		}

		$ids = self::sort_ids( self::member_ids( $term ), self::order_ids( $term_id ) );
		wp_cache_set( self::cache_key( $term_id ), $ids, self::CACHE_GROUP );

		return $ids;
	}

	/**
	 * Drop the cached ID list for a placement.
	 *
	 * @param int $term_id Placement term ID.
	 */
	public static function invalidate( $term_id ) {
		wp_cache_delete( self::cache_key( (int) $term_id ), self::CACHE_GROUP );
	}

	/**
	 * A collection's placements all need a fresh list after it changes.
	 *
	 * @param int $post_id Collection ID.
	 */
	public static function invalidate_collection( $post_id ) {
		$post_id = (int) $post_id;
		if ( COLLECTIONS_MANAGER_POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$term_ids = wp_get_object_terms( $post_id, COLLECTIONS_MANAGER_PLACEMENT, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $term_ids ) ) {
			return;
		}

		foreach ( $term_ids as $term_id ) {
			self::invalidate( (int) $term_id );
		}
	}

	/**
	 * Assignment changes the membership of every term touched by the write.
	 *
	 * @param int          $object_id  Object ID.
	 * @param mixed        $terms      New terms.
	 * @param array|mixed  $tt_ids     New term-taxonomy IDs.
	 * @param string       $taxonomy   Taxonomy.
	 * @param bool         $append     Whether terms were appended.
	 * @param array|mixed  $old_tt_ids Previous term-taxonomy IDs.
	 */
	public static function invalidate_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $object_id, $terms, $append );

		if ( COLLECTIONS_MANAGER_PLACEMENT !== $taxonomy ) {
			return;
		}

		$all = array_merge( (array) $tt_ids, (array) $old_tt_ids );
		foreach ( array_unique( array_map( 'intval', $all ) ) as $tt_id ) {
			if ( ! $tt_id ) {
				continue;
			}
			$term = get_term_by( 'term_taxonomy_id', $tt_id, COLLECTIONS_MANAGER_PLACEMENT );
			if ( $term && ! is_wp_error( $term ) ) {
				self::invalidate( (int) $term->term_id );
			}
		}
	}

	/**
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function invalidate_term( $term_id, $tt_id, $taxonomy ) {
		unset( $tt_id );

		if ( COLLECTIONS_MANAGER_PLACEMENT !== $taxonomy ) {
			return;
		}

		self::invalidate( (int) $term_id );
	}

	/**
	 * @param int $term_id Placement term ID.
	 * @return string
	 */
	private static function cache_key( $term_id ) {
		return 'ids:' . (int) $term_id;
	}

	/**
	 * Published collection IDs assigned to a placement.
	 *
	 * @param \WP_Term $term Placement.
	 * @return int[]
	 */
	private static function member_ids( $term ) {
		$query = new \WP_Query(
			array(
				'post_type'              => COLLECTIONS_MANAGER_POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::QUERY_LIMIT,
				'fields'                 => 'ids',
				'orderby'                => 'none',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One placement term, bounded to published collections.
				'tax_query'              => array(
					array(
						'taxonomy' => COLLECTIONS_MANAGER_PLACEMENT,
						'field'    => 'term_id',
						'terms'    => array( (int) $term->term_id ),
					),
				),
			)
		);

		return array_values( array_map( 'intval', (array) $query->posts ) );
	}

	/**
	 * Placement order first, then menu_order, then title.
	 *
	 * @param int[] $ids       Member IDs.
	 * @param int[] $preferred Saved order.
	 * @return int[]
	 */
	private static function sort_ids( array $ids, array $preferred ) {
		if ( ! $ids ) {
			return array();
		}

		$rank = array();
		foreach ( $preferred as $index => $id ) {
			$rank[ (int) $id ] = (int) $index;
		}

		_prime_post_caches( $ids, false, false );

		usort(
			$ids,
			static function ( $a, $b ) use ( $rank ) {
				$a_ranked = array_key_exists( $a, $rank );
				$b_ranked = array_key_exists( $b, $rank );
				if ( $a_ranked && $b_ranked && $rank[ $a ] !== $rank[ $b ] ) {
					return $rank[ $a ] <=> $rank[ $b ];
				}
				if ( $a_ranked !== $b_ranked ) {
					return $a_ranked ? -1 : 1;
				}

				$post_a  = get_post( $a );
				$post_b  = get_post( $b );
				$order_a = $post_a instanceof \WP_Post ? (int) $post_a->menu_order : 0;
				$order_b = $post_b instanceof \WP_Post ? (int) $post_b->menu_order : 0;
				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}

				$title_a = $post_a instanceof \WP_Post ? $post_a->post_title : '';
				$title_b = $post_b instanceof \WP_Post ? $post_b->post_title : '';
				return strcasecmp( $title_a, $title_b );
			}
		);

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * @param mixed $value Raw IDs.
	 * @return int[]
	 */
	private static function parse_ids( $value ) {
		if ( is_string( $value ) ) {
			$value = '' === $value ? array() : explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();
		foreach ( $value as $id ) {
			$id = absint( $id );
			if ( $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param int[] $ids Candidate IDs.
	 * @return int[]
	 */
	private static function published_collection_ids( array $ids ) {
		$kept = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( COLLECTIONS_MANAGER_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$kept[] = (int) $post->ID;
		}

		return $kept;
	}
}
