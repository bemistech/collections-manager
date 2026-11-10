<?php
/**
 * Collections Browser — collection rail + expandable post carousel.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$collections = collections_manager_get_browser_payload( is_array( $attributes ) ? $attributes : array() );
if ( empty( $collections ) ) {
	return '';
}

$styles = collections_manager_resolve_browser_styles( is_array( $attributes ) ? $attributes : array() );
$visible = collections_manager_browser_visible_count( $attributes, count( $collections ) );
$fill = ! array_key_exists( 'visibleCount', $attributes ) || (int) $attributes['visibleCount'] < 1;
$classes = 'cm-browser ' . collections_manager_browser_style_classes( $styles );
if ( $fill ) {
	$classes .= ' cm-browser--rail-fill';
}

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => $classes,
		'style' => collections_manager_browser_styles_to_css( $styles ) . ';--cm-visible:' . (int) $visible,
	)
);
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php $instance = wp_unique_id( 'cm-browser-' ); ?>
	<div class="cm-browser__rail" role="region" aria-roledescription="<?php esc_attr_e( 'carousel', 'collections-manager' ); ?>" aria-label="<?php esc_attr_e( 'Collections', 'collections-manager' ); ?>">
		<button type="button" class="cm-browser__nav cm-browser__nav--prev" aria-label="<?php esc_attr_e( 'Previous collections', 'collections-manager' ); ?>" hidden>
			<span aria-hidden="true">‹</span>
		</button>
		<div class="cm-browser__rail-viewport">
			<div class="cm-browser__rail-container">
				<?php foreach ( $collections as $collection ) : ?>
					<?php
					$is_youtube = isset( $collection['source'] ) && 'youtube' === $collection['source'];
					$is_feed    = isset( $collection['source'] ) && 'feed' === $collection['source'];
					if ( $is_youtube ) {
						$count_label = sprintf(
							/* translators: %s: number of videos in the collection */
							_n( '%s video', '%s videos', $collection['count'], 'collections-manager' ),
							number_format_i18n( $collection['count'] )
						);
					} elseif ( $is_feed ) {
						$count_label = sprintf(
							/* translators: %s: number of remote items in the collection */
							_n( '%s item', '%s items', $collection['count'], 'collections-manager' ),
							number_format_i18n( $collection['count'] )
						);
					} else {
						$count_label = sprintf(
							/* translators: %s: number of posts in the collection */
							_n( '%s story', '%s stories', $collection['count'], 'collections-manager' ),
							number_format_i18n( $collection['count'] )
						);
					}
					?>
					<button
						type="button"
						class="cm-browser__tab"
						id="<?php echo esc_attr( $instance . '-tab-' . $collection['id'] ); ?>"
						aria-controls="<?php echo esc_attr( $instance . '-panel-' . $collection['id'] ); ?>"
						aria-expanded="false"
						data-collection-id="<?php echo esc_attr( (string) $collection['id'] ); ?>"
					>
						<span class="cm-browser__tab-cover">
							<?php
							$cover_html = collections_manager_card_image_html( $collection['cover_id'] ?? 0, $collection['cover'] ?? '' );
							if ( '' !== $cover_html ) {
								echo $cover_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in collections_manager_card_image_html().
							}
							?>
							<span class="cm-browser__tab-shade"></span>
						</span>
						<span class="cm-browser__tab-meta">
							<span class="cm-browser__tab-label"><?php echo esc_html( $collection['title'] ); ?></span>
							<span class="cm-browser__tab-count"><?php echo esc_html( $count_label ); ?></span>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<button type="button" class="cm-browser__nav cm-browser__nav--next" aria-label="<?php esc_attr_e( 'Next collections', 'collections-manager' ); ?>" hidden>
			<span aria-hidden="true">›</span>
		</button>
	</div>

	<div class="cm-browser__stage">
		<div class="cm-browser__stage-inner">
			<?php foreach ( $collections as $collection ) : ?>
				<?php
				$is_youtube = isset( $collection['source'] ) && 'youtube' === $collection['source'];
				$is_feed    = isset( $collection['source'] ) && 'feed' === $collection['source'];
				?>
				<div
					class="cm-browser__panel"
					id="<?php echo esc_attr( $instance . '-panel-' . $collection['id'] ); ?>"
					role="region"
					aria-labelledby="<?php echo esc_attr( $instance . '-tab-' . $collection['id'] ); ?>"
					hidden
					inert
					tabindex="-1"
					data-collection-id="<?php echo esc_attr( (string) $collection['id'] ); ?>"
				>
					<div class="cm-browser__panel-header">
						<div>
							<h3 class="cm-browser__panel-title"><?php echo esc_html( $collection['title'] ); ?></h3>
							<?php if ( ! empty( $collection['description'] ) ) : ?>
								<p class="cm-browser__panel-copy"><?php echo esc_html( $collection['description'] ); ?></p>
							<?php endif; ?>
						</div>
						<button type="button" class="cm-browser__close" aria-label="<?php esc_attr_e( 'Close collection', 'collections-manager' ); ?>">
							<?php esc_html_e( 'Close', 'collections-manager' ); ?>
						</button>
					</div>
					<div
						class="cm-browser__carousel"
						role="region"
						aria-roledescription="<?php esc_attr_e( 'carousel', 'collections-manager' ); ?>"
						aria-label="<?php echo esc_attr( $collection['title'] ); ?>"
					>
						<?php if ( empty( $collection['items'] ) ) : ?>
							<p class="cm-browser__empty"><?php
							if ( ! empty( $is_youtube ) ) {
								esc_html_e( 'No videos in this collection yet.', 'collections-manager' );
							} elseif ( ! empty( $is_feed ) ) {
								esc_html_e( 'No items in this collection yet.', 'collections-manager' );
							} else {
								esc_html_e( 'No stories in this collection yet.', 'collections-manager' );
							}
							?></p>
						<?php else : ?>
						<button type="button" class="cm-browser__nav cm-browser__nav--prev" aria-label="<?php echo esc_attr( ! empty( $is_youtube ) ? __( 'Previous videos', 'collections-manager' ) : ( ! empty( $is_feed ) ? __( 'Previous items', 'collections-manager' ) : __( 'Previous posts', 'collections-manager' ) ) ); ?>" hidden>
							<span aria-hidden="true">‹</span>
						</button>
						<div class="cm-browser__viewport">
							<div class="cm-browser__track">
								<?php foreach ( $collection['items'] as $item ) : ?>
									<?php
									$external = ! empty( $item['external'] );
									$card_class = 'cm-browser__card' . ( ! empty( $is_youtube ) ? ' cm-browser__card--youtube' : '' );
									?>
									<a class="<?php echo esc_attr( $card_class ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
										<span class="cm-browser__card-media">
											<?php
											$image_html = collections_manager_card_image_html( $item['image_id'] ?? 0, $item['image'] ?? '' );
											if ( '' !== $image_html ) {
												echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in collections_manager_card_image_html().
											}
											?>
											<span class="cm-browser__card-shade"></span>
										</span>
										<span class="cm-browser__card-title">
											<?php echo esc_html( $item['title'] ); ?>
											<?php if ( $external ) : ?>
												<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'collections-manager' ); ?></span>
											<?php endif; ?>
										</span>
									</a>
								<?php endforeach; ?>
								<?php if ( ! empty( $collection['more_url'] ) ) : ?>
									<?php
									$more_external = ! empty( $is_youtube ) || ! empty( $is_feed );
									?>
									<a class="cm-browser__card cm-browser__card--more" href="<?php echo esc_url( $collection['more_url'] ); ?>"<?php echo $more_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
										<span class="cm-browser__card-media" aria-hidden="true">
											<span class="cm-browser__card-more-label"><?php esc_html_e( 'View more', 'collections-manager' ); ?></span>
										</span>
										<span class="cm-browser__card-title">
											<?php esc_html_e( 'View more', 'collections-manager' ); ?>
											<?php if ( $more_external ) : ?>
												<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'collections-manager' ); ?></span>
											<?php endif; ?>
										</span>
									</a>
								<?php endif; ?>
							</div>
						</div>
						<button type="button" class="cm-browser__nav cm-browser__nav--next" aria-label="<?php echo esc_attr( ! empty( $is_youtube ) ? __( 'Next videos', 'collections-manager' ) : ( ! empty( $is_feed ) ? __( 'Next items', 'collections-manager' ) : __( 'Next posts', 'collections-manager' ) ) ); ?>" hidden>
							<span aria-hidden="true">›</span>
						</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
