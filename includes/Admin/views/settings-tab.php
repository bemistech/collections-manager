<?php
/**
 * YouTube settings form.
 *
 * @var bool   $locked Constant override.
 * @var string $stored Stored option value.
 * @var bool   $has    Whether any key is active.
 *
 * @package CollectionsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cm-screen">
<div class="cm-dash-card">
	<h2><?php esc_html_e( 'YouTube', 'collections-manager' ); ?></h2>
	<p><?php esc_html_e( 'Collections can load a playlist by ID, or let you pick videos from specific channels. Playlists still work from YouTube’s public feed. A Data API key unlocks @handle lookup and browsing more than the latest uploads.', 'collections-manager' ); ?></p>
	<?php if ( $has ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'A YouTube API key is active.', 'collections-manager' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( \CollectionsManager\Admin\SettingsPage::url() ); ?>">
		<?php wp_nonce_field( 'collections_manager_save_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cm-youtube-api-key"><?php esc_html_e( 'YouTube Data API key', 'collections-manager' ); ?></label></th>
				<td>
					<?php if ( $locked ) : ?>
						<p><?php esc_html_e( 'This site is using COLLECTIONS_MANAGER_YOUTUBE_API_KEY from wp-config.php.', 'collections-manager' ); ?></p>
					<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
						<input type="password" name="youtube_api_key" id="cm-youtube-api-key" class="regular-text" value="<?php echo esc_attr( $stored ); ?>" autocomplete="off" />
						<p class="description">
							<?php esc_html_e( 'Create a key in Google Cloud with the YouTube Data API v3 enabled. You can also define COLLECTIONS_MANAGER_YOUTUBE_API_KEY in wp-config.php instead of saving it here.', 'collections-manager' ); ?>
						</p>
					<?php else : ?>
						<p><?php echo $has ? esc_html__( 'A YouTube API key is configured.', 'collections-manager' ) : esc_html__( 'Ask an administrator to add a YouTube Data API key if you need @handle lookup or full channel browsing.', 'collections-manager' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php if ( ! $locked && current_user_can( 'manage_options' ) ) : ?>
			<?php submit_button( __( 'Save settings', 'collections-manager' ), 'primary', 'collections_manager_save_settings' ); ?>
		<?php elseif ( ! current_user_can( 'manage_options' ) ) : ?>
			<p><?php esc_html_e( 'Only administrators can change this key.', 'collections-manager' ); ?></p>
		<?php endif; ?>
	</form>
</div>
<?php
$license_saved  = \CollectionsManager\Updates\License::saved();
$license_notice = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="cm-dash-card">
	<h2><?php esc_html_e( 'License key', 'collections-manager' ); ?></h2>
	<p><?php esc_html_e( 'Collections Manager sends this key when it checks updates.bemistech.com for its own package. The key is stored encrypted and is not shown after you save.', 'collections-manager' ); ?></p>
	<?php if ( 'license-saved' === $license_notice ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'License settings saved.', 'collections-manager' ); ?></p></div>
	<?php elseif ( 'license-invalid' === $license_notice ) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'That license key was not saved. Use 16 to 200 letters, numbers, or the symbols _ - . ~ + / =.', 'collections-manager' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $license_saved ) : ?>
		<p><strong><?php esc_html_e( 'A license key is saved.', 'collections-manager' ); ?></strong></p>
	<?php else : ?>
		<p><?php esc_html_e( 'No license key is saved yet.', 'collections-manager' ); ?></p>
	<?php endif; ?>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<form method="post" action="<?php echo esc_url( \CollectionsManager\Admin\SettingsPage::url() ); ?>">
			<?php wp_nonce_field( 'collections_manager_save_license' ); ?>
			<input type="hidden" name="collections_manager_save_license" value="1">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cm-license-key"><?php esc_html_e( 'License key', 'collections-manager' ); ?></label></th>
					<td>
						<input type="password" name="collections_manager_license_key" id="cm-license-key" class="regular-text" value="" autocomplete="new-password" spellcheck="false" data-1p-ignore="true" data-lpignore="true" />
						<?php if ( $license_saved ) : ?>
							<p><label for="cm-license-clear"><input type="checkbox" id="cm-license-clear" name="collections_manager_license_clear" value="1"> <?php esc_html_e( 'Remove the saved key', 'collections-manager' ); ?></label></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save license', 'collections-manager' ) ); ?>
		</form>
	<?php else : ?>
		<p><?php esc_html_e( 'Only administrators can change the license key.', 'collections-manager' ); ?></p>
	<?php endif; ?>
</div>
</div>
