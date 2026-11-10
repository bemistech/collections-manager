<?php
/**
 * Styles tab form and live preview.
 *
 * @package CollectionsManager
 *
 * @var array $styles Sanitized global styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CollectionsManager\Admin\StylesPage;
use CollectionsManager\Styles\Repository;

$preview_classes = 'cm-browser ' . Repository::classes( $styles );
$preview_style   = trim( Repository::palette_css() . ';' . Repository::to_css( $styles, true ), ';' );

if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice after redirect.
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Global styles saved.', 'collections-manager' ) . '</p></div>';
}
?>
<div class="cm-screen cm-screen--wide">
<p class="cm-lead">
	<?php esc_html_e( 'These styles apply to every Collections Browser block. Empty colors inherit the active theme (text color and palette). A block can opt out in the editor and set its own look.', 'collections-manager' ); ?>
</p>
<div class="cm-styles">
	<form class="cm-styles__form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=collections_manager&tab=styles' ) ); ?>">
		<?php wp_nonce_field( 'collections_manager_save_styles' ); ?>

		<section class="cm-dash-card">
		<h2><?php esc_html_e( 'Collection tiles', 'collections-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cm-style-item-gap"><?php esc_html_e( 'Space between tiles', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[item_gap]" id="cm-style-item-gap" type="number" min="0" max="48" value="<?php echo esc_attr( (string) $styles['item_gap'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-collection-text"><?php esc_html_e( 'Collection title', 'collections-manager' ); ?></label></th>
				<td>
					<select name="cm_styles[collection_text_position]" id="cm-style-collection-text">
						<option value="overlay" <?php selected( $styles['collection_text_position'], 'overlay' ); ?>><?php esc_html_e( 'On the image', 'collections-manager' ); ?></option>
						<option value="above" <?php selected( $styles['collection_text_position'], 'above' ); ?>><?php esc_html_e( 'Above the image', 'collections-manager' ); ?></option>
						<option value="below" <?php selected( $styles['collection_text_position'], 'below' ); ?>><?php esc_html_e( 'Below the image', 'collections-manager' ); ?></option>
						<option value="beside" <?php selected( $styles['collection_text_position'], 'beside' ); ?>><?php esc_html_e( 'Next to the image', 'collections-manager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-text-layout"><?php esc_html_e( 'Title and count', 'collections-manager' ); ?></label></th>
				<td>
					<select name="cm_styles[text_layout]" id="cm-style-text-layout">
						<option value="stack" <?php selected( $styles['text_layout'], 'stack' ); ?>><?php esc_html_e( 'Stacked', 'collections-manager' ); ?></option>
						<option value="row" <?php selected( $styles['text_layout'], 'row' ); ?>><?php esc_html_e( 'Side by side', 'collections-manager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-title-size"><?php esc_html_e( 'Collection title size', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[title_font_size]" id="cm-style-title-size" type="number" min="12" max="40" value="<?php echo esc_attr( (string) $styles['title_font_size'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-count-size"><?php esc_html_e( 'Story count size', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[count_font_size]" id="cm-style-count-size" type="number" min="10" max="24" value="<?php echo esc_attr( (string) $styles['count_font_size'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
		</table>
		</section>

		<section class="cm-dash-card">
		<h2><?php esc_html_e( 'Post cards', 'collections-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cm-style-card-gap"><?php esc_html_e( 'Space between cards', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[card_gap]" id="cm-style-card-gap" type="number" min="0" max="48" value="<?php echo esc_attr( (string) $styles['card_gap'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-card-text"><?php esc_html_e( 'Post titles', 'collections-manager' ); ?></label></th>
				<td>
					<select name="cm_styles[card_text_position]" id="cm-style-card-text">
						<option value="overlay" <?php selected( $styles['card_text_position'], 'overlay' ); ?>><?php esc_html_e( 'On the image', 'collections-manager' ); ?></option>
						<option value="above" <?php selected( $styles['card_text_position'], 'above' ); ?>><?php esc_html_e( 'Above the image', 'collections-manager' ); ?></option>
						<option value="below" <?php selected( $styles['card_text_position'], 'below' ); ?>><?php esc_html_e( 'Below the image', 'collections-manager' ); ?></option>
						<option value="beside" <?php selected( $styles['card_text_position'], 'beside' ); ?>><?php esc_html_e( 'Next to the image', 'collections-manager' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-card-title-size"><?php esc_html_e( 'Post title size', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[card_title_font_size]" id="cm-style-card-title-size" type="number" min="12" max="32" value="<?php echo esc_attr( (string) $styles['card_title_font_size'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
		</table>
		</section>

		<section class="cm-dash-card">
		<h2><?php esc_html_e( 'Border and shadow', 'collections-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cm-style-radius"><?php esc_html_e( 'Border radius', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[border_radius]" id="cm-style-radius" type="number" min="0" max="40" value="<?php echo esc_attr( (string) $styles['border_radius'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-width"><?php esc_html_e( 'Border width', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[border_width]" id="cm-style-width" type="number" min="0" max="8" value="<?php echo esc_attr( (string) $styles['border_width'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Border color', 'collections-manager' ); ?></th>
				<td>
					<?php StylesPage::color_control( 'cm-style-border-color', 'cm_styles[border_color]', $styles['border_color'], __( 'Inherit text color', 'collections-manager' ) ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Drop shadow', 'collections-manager' ); ?></th>
				<td>
					<label>
						<input name="cm_styles[show_shadow]" id="cm-style-shadow" type="checkbox" value="1" <?php checked( $styles['show_shadow'] ); ?> />
						<?php esc_html_e( 'Show a drop shadow on tiles and cards', 'collections-manager' ); ?>
					</label>
					<div class="cm-styles__shadow-fields" id="cm-style-shadow-fields" <?php echo $styles['show_shadow'] ? '' : 'hidden'; ?>>
						<div class="cm-styles__shadow-grid">
							<div>
								<label for="cm-style-shadow-x"><?php esc_html_e( 'Horizontal offset', 'collections-manager' ); ?></label>
								<input name="cm_styles[shadow_offset_x]" id="cm-style-shadow-x" type="number" min="-40" max="40" value="<?php echo esc_attr( (string) $styles['shadow_offset_x'] ); ?>" class="small-text" />
								<span class="description">px</span>
							</div>
							<div>
								<label for="cm-style-shadow-y"><?php esc_html_e( 'Vertical offset', 'collections-manager' ); ?></label>
								<input name="cm_styles[shadow_offset_y]" id="cm-style-shadow-y" type="number" min="-40" max="40" value="<?php echo esc_attr( (string) $styles['shadow_offset_y'] ); ?>" class="small-text" />
								<span class="description">px</span>
							</div>
							<div>
								<label for="cm-style-shadow-blur"><?php esc_html_e( 'Blur', 'collections-manager' ); ?></label>
								<input name="cm_styles[shadow_blur]" id="cm-style-shadow-blur" type="number" min="0" max="60" value="<?php echo esc_attr( (string) $styles['shadow_blur'] ); ?>" class="small-text" />
								<span class="description">px</span>
							</div>
							<div>
								<label for="cm-style-shadow-spread"><?php esc_html_e( 'Spread', 'collections-manager' ); ?></label>
								<input name="cm_styles[shadow_spread]" id="cm-style-shadow-spread" type="number" min="-20" max="40" value="<?php echo esc_attr( (string) $styles['shadow_spread'] ); ?>" class="small-text" />
								<span class="description">px</span>
							</div>
						</div>
						<p style="margin:16px 0 8px;"><strong><?php esc_html_e( 'Shadow color', 'collections-manager' ); ?></strong></p>
						<?php StylesPage::color_control( 'cm-style-shadow-color', 'cm_styles[shadow_color]', $styles['shadow_color'], __( 'Soft black', 'collections-manager' ) ); ?>
					</div>
				</td>
			</tr>
		</table>
		</section>

		<section class="cm-dash-card">
		<h2><?php esc_html_e( 'Slider box', 'collections-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cm-style-panel-radius"><?php esc_html_e( 'Box radius', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[panel_radius]" id="cm-style-panel-radius" type="number" min="0" max="40" value="<?php echo esc_attr( (string) $styles['panel_radius'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cm-style-panel-padding"><?php esc_html_e( 'Box padding', 'collections-manager' ); ?></label></th>
				<td>
					<input name="cm_styles[panel_padding]" id="cm-style-panel-padding" type="number" min="0" max="48" value="<?php echo esc_attr( (string) $styles['panel_padding'] ); ?>" class="small-text" />
					<span class="description">px</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Box background', 'collections-manager' ); ?></th>
				<td>
					<?php StylesPage::color_control( 'cm-style-panel-bg', 'cm_styles[panel_background]', $styles['panel_background'], __( 'Soft mix of text color', 'collections-manager' ) ); ?>
					<p class="description"><?php esc_html_e( 'Leave on the mix option to blend with whatever theme text color is active.', 'collections-manager' ); ?></p>
				</td>
			</tr>
		</table>
		</section>

		<section class="cm-dash-card">
		<h2><?php esc_html_e( 'Slider controls', 'collections-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Arrow background', 'collections-manager' ); ?></th>
				<td>
					<?php StylesPage::color_control( 'cm-style-nav-bg', 'cm_styles[nav_background]', $styles['nav_background'], __( 'Dark translucent', 'collections-manager' ) ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Arrow color', 'collections-manager' ); ?></th>
				<td>
					<?php StylesPage::color_control( 'cm-style-nav-color', 'cm_styles[nav_color]', $styles['nav_color'], __( 'White', 'collections-manager' ) ); ?>
				</td>
			</tr>
		</table>
		</section>

		<div class="cm-editor__bar">
			<?php submit_button( __( 'Save styles', 'collections-manager' ), 'primary', 'collections_manager_save_styles' ); ?>
		</div>
	</form>

	<div class="cm-styles__preview cm-dash-card">
		<h2><?php esc_html_e( 'Preview', 'collections-manager' ); ?></h2>
		<div id="cm-style-preview" class="<?php echo esc_attr( $preview_classes ); ?>" style="<?php echo esc_attr( $preview_style ); ?>">
			<div class="cm-browser__rail">
				<div class="cm-browser__rail-viewport">
					<div class="cm-browser__rail-container">
						<button type="button" class="cm-browser__tab" aria-selected="true">
							<span class="cm-browser__tab-cover">
								<span class="cm-browser__tab-shade"></span>
							</span>
							<span class="cm-browser__tab-meta">
								<span class="cm-browser__tab-label"><?php esc_html_e( 'Park Guides', 'collections-manager' ); ?></span>
								<span class="cm-browser__tab-count"><?php esc_html_e( '4 stories', 'collections-manager' ); ?></span>
							</span>
						</button>
					</div>
				</div>
			</div>
			<div class="cm-browser__stage is-open" style="grid-template-rows:1fr;opacity:1">
				<div class="cm-browser__stage-inner">
					<div class="cm-browser__panel">
						<div class="cm-browser__panel-header">
							<h3 class="cm-browser__panel-title"><?php esc_html_e( 'Park Guides', 'collections-manager' ); ?></h3>
						</div>
						<div class="cm-browser__carousel">
							<div class="cm-browser__viewport">
								<div class="cm-browser__track">
									<span class="cm-browser__card">
										<span class="cm-browser__card-media"><span class="cm-browser__card-shade"></span></span>
										<span class="cm-browser__card-title"><?php esc_html_e( 'Sample post', 'collections-manager' ); ?></span>
									</span>
								</div>
							</div>
							<button type="button" class="cm-browser__nav cm-browser__nav--next" aria-hidden="true">
								<span>›</span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
</div>
<?php
