<?php
/**
 * Plugin Name: Collections Manager
 * Description: Curated collections of posts, managed from a dashboard and shown in a streaming-style browser block.
 * Author: Bemis Tech
 * Author URI: https://bemistech.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 0.2.10
 * Text Domain: collections-manager
 * Update URI: https://updates.bemistech.com/collections-manager.json
 *
 * @package CollectionsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COLLECTIONS_MANAGER_VERSION', '0.2.10' );
define( 'COLLECTIONS_MANAGER_FILE', __FILE__ );
define( 'COLLECTIONS_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'COLLECTIONS_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'COLLECTIONS_MANAGER_POST_TYPE', 'cm_collection' );
define( 'COLLECTIONS_MANAGER_POSTS_META', '_cm_post_ids' );
define( 'COLLECTIONS_MANAGER_SOURCE_META', '_cm_source' );
define( 'COLLECTIONS_MANAGER_QUERY_META', '_cm_query' );
define( 'COLLECTIONS_MANAGER_YOUTUBE_META', '_cm_youtube' );
define( 'COLLECTIONS_MANAGER_FEED_META', '_cm_feed' );
define( 'COLLECTIONS_MANAGER_PLACEMENT', 'collection_placement' );

require_once COLLECTIONS_MANAGER_PATH . 'includes/Autoloader.php';
CollectionsManager\Autoloader::register();
CollectionsManager\Admin\StylesPage::register();
CollectionsManager\Admin\SettingsPage::register();
CollectionsManager\Updates\Checker::register();
CollectionsManager\Updates\License::register();
CollectionsManager\Rest\Picker::register();
CollectionsManager\Placements::register();
CollectionsManager\PlacementUsage::register();
CollectionsManager\Rest\Placements::register();

require_once COLLECTIONS_MANAGER_PATH . 'includes/helpers.php';
require_once COLLECTIONS_MANAGER_PATH . 'includes/styles.php';
require_once COLLECTIONS_MANAGER_PATH . 'includes/cpt.php';
require_once COLLECTIONS_MANAGER_PATH . 'includes/dashboard.php';
require_once COLLECTIONS_MANAGER_PATH . 'includes/blocks.php';

register_activation_hook( __FILE__, 'collections_manager_activate' );
function collections_manager_activate() {
	collections_manager_register_post_type();
	flush_rewrite_rules();
}
