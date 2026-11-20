<?php
/**
 * Encrypted license key for Collections Manager updates.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores this plugin's license key. The field is never filled with the saved value.
 */
class License {

	const OPTION = 'collections_manager_license_key';

	/**
	 * Register the settings-page save handler.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Persist the license key from the Collections settings tab.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['collections_manager_save_license'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the Collections Manager license.', 'collections-manager' ) );
		}
		check_admin_referer( 'collections_manager_save_license' );

		$key   = isset( $_POST['collections_manager_license_key'] ) && is_string( $_POST['collections_manager_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['collections_manager_license_key'] ) ) : '';
		$saved = self::save( $key, ! empty( $_POST['collections_manager_license_clear'] ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'collections_manager',
					'tab'     => 'settings',
					'updated' => is_wp_error( $saved ) ? 'license-invalid' : 'license-saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Key sent with this plugin's update requests.
	 *
	 * @return string
	 */
	public static function plain() {
		$stored = get_option( self::OPTION, '' );
		return is_string( $stored ) ? self::open( $stored ) : '';
	}

	/**
	 * Whether a license key is saved.
	 *
	 * @return bool
	 */
	public static function saved() {
		return '' !== self::plain();
	}

	/**
	 * Save or remove the license key.
	 *
	 * @param string $raw   Submitted key. Blank keeps the saved key.
	 * @param bool   $clear Whether to remove the saved key.
	 * @return true|\WP_Error
	 */
	public static function save( $raw, $clear ) {
		$raw = trim( (string) $raw );
		if ( '' !== $raw ) {
			if ( ! preg_match( '/^[A-Za-z0-9_\-.~+\/=]{16,200}$/', $raw ) ) {
				return new \WP_Error( 'collections_manager_license_key', __( 'That license key was not saved. Use 16 to 200 letters, numbers, or the symbols _ - . ~ + / =.', 'collections-manager' ) );
			}
			$sealed = self::seal( $raw );
			if ( '' === $sealed ) {
				return new \WP_Error( 'collections_manager_license_key', __( 'The license key could not be stored securely, so it was not saved.', 'collections-manager' ) );
			}
			update_option( self::OPTION, $sealed, false );
			return true;
		}
		if ( $clear ) {
			delete_option( self::OPTION );
		}
		return true;
	}

	/**
	 * Encrypt a key with the site auth salt.
	 *
	 * @param string $key Plain key.
	 * @return string
	 */
	private static function seal( $key ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$nonce  = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $key, 'aes-256-gcm', self::material(), OPENSSL_RAW_DATA, $nonce, $tag );
		if ( false === $cipher || 16 !== strlen( $tag ) ) {
			return '';
		}
		return base64_encode( $nonce . $tag . $cipher );
	}

	/**
	 * Decrypt a stored key.
	 *
	 * @param string $stored Encoded ciphertext.
	 * @return string
	 */
	private static function open( $stored ) {
		if ( ! function_exists( 'openssl_decrypt' ) || '' === $stored ) {
			return '';
		}
		$raw = base64_decode( $stored, true );
		if ( ! is_string( $raw ) || strlen( $raw ) < 29 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::material(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Key material derived from the site salt and this plugin's option name.
	 *
	 * @return string
	 */
	private static function material() {
		return hash( 'sha256', wp_salt( 'auth' ) . self::OPTION, true );
	}
}
