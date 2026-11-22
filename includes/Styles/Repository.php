<?php
/**
 * Global and resolved Collections Browser styles.
 *
 * @package CollectionsManager
 */

namespace CollectionsManager\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option storage, sanitization, and CSS custom properties.
 */
class Repository {

	const OPTION = 'collections_manager_global_styles';

	/**
	 * Defaults that follow currentColor / theme mix unless a color is set.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'border_radius'            => 12,
			'border_width'             => 0,
			'border_color'             => '',
			'show_shadow'              => false,
			'shadow_color'             => '',
			'shadow_offset_x'          => 0,
			'shadow_offset_y'          => 10,
			'shadow_blur'              => 24,
			'shadow_spread'            => 0,
			'collection_text_position' => 'overlay',
			'card_text_position'       => 'overlay',
			'text_layout'              => 'stack',
			'mobile_ratio'             => '4-5',
			'title_font_size'          => 18,
			'count_font_size'          => 12,
			'card_title_font_size'     => 15,
			'item_gap'                 => 14,
			'card_gap'                 => 12,
			'panel_radius'             => 16,
			'panel_padding'            => 18,
			'panel_background'         => '',
			'nav_background'           => '',
			'nav_color'                => '',
		);
	}

	/**
	 * Image shapes offered for phones (781px wide and narrower).
	 *
	 * @return array<string, array{label:string,css:string}>
	 */
	public static function mobile_ratios() {
		return array(
			'4-5'  => array( 'label' => __( 'Portrait 4:5', 'collections-manager' ), 'css' => '4 / 5' ),
			'1-1'  => array( 'label' => __( 'Square 1:1', 'collections-manager' ), 'css' => '1 / 1' ),
			'3-4'  => array( 'label' => __( 'Tall 3:4', 'collections-manager' ), 'css' => '3 / 4' ),
			'16-9' => array( 'label' => __( 'Landscape 16:9', 'collections-manager' ), 'css' => '16 / 9' ),
			'9-16' => array( 'label' => __( 'Story 9:16', 'collections-manager' ), 'css' => '9 / 16' ),
		);
	}

	/**
	 * Theme palette colors for pickers and admin preview.
	 *
	 * @return array<int, array{name:string,slug:string,css:string,hex:string}>
	 */
	public static function palette() {
		$palette = array();

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return $palette;
		}

		$groups = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( ! is_array( $groups ) ) {
			return $palette;
		}

		$seen = array();
		foreach ( $groups as $origin ) {
			if ( ! is_array( $origin ) ) {
				continue;
			}

			$colors = isset( $origin['color'] ) || isset( $origin['slug'] ) ? array( $origin ) : $origin;
			foreach ( $colors as $color ) {
				if ( ! is_array( $color ) || empty( $color['slug'] ) || empty( $color['color'] ) ) {
					continue;
				}

				$slug = sanitize_title( $color['slug'] );
				if ( ! $slug || isset( $seen[ $slug ] ) ) {
					continue;
				}

				$seen[ $slug ] = true;
				$palette[]     = array(
					'name' => isset( $color['name'] ) ? (string) $color['name'] : $slug,
					'slug' => $slug,
					'css'  => 'var(--wp--preset--color--' . $slug . ')',
					'hex'  => (string) $color['color'],
				);
			}
		}

		return $palette;
	}

	/**
	 * Normalize a stored color to a safe CSS value.
	 *
	 * @param mixed $color Raw color.
	 * @return string
	 */
	public static function sanitize_color( $color ) {
		$color = trim( (string) $color );
		if ( '' === $color ) {
			return '';
		}

		if ( preg_match( '/^var:preset\|color\|([a-z0-9\-]+)$/i', $color, $matches ) ) {
			return 'var(--wp--preset--color--' . sanitize_title( $matches[1] ) . ')';
		}

		if ( preg_match( '/^var\(--wp--preset--color--[a-z0-9\-]+\)$/i', $color ) ) {
			return $color;
		}

		if ( sanitize_hex_color( $color ) ) {
			return $color;
		}

		if ( preg_match( '/^#([0-9a-fA-F]{4}|[0-9a-fA-F]{8})$/', $color ) ) {
			return $color;
		}

		if ( preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)$/', $color ) ) {
			return $color;
		}

		return '';
	}

	/**
	 * @param string $color    Stored color.
	 * @param string $fallback CSS fallback.
	 * @return string
	 */
	public static function color_to_css( $color, $fallback = 'currentColor' ) {
		$color = self::sanitize_color( $color );
		return $color ? $color : $fallback;
	}

	/**
	 * Saved global styles merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return self::sanitize( wp_parse_args( $saved, self::defaults() ) );
	}

	/**
	 * @param mixed $input Raw styles.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();

		return array(
			'border_radius'            => max( 0, min( 40, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
			'border_width'             => max( 0, min( 8, (int) ( $input['border_width'] ?? $defaults['border_width'] ) ) ),
			'border_color'             => self::sanitize_color( $input['border_color'] ?? '' ),
			'show_shadow'              => ! empty( $input['show_shadow'] ),
			'shadow_color'             => self::sanitize_color( $input['shadow_color'] ?? '' ),
			'shadow_offset_x'          => max( -40, min( 40, (int) ( $input['shadow_offset_x'] ?? $defaults['shadow_offset_x'] ) ) ),
			'shadow_offset_y'          => max( -40, min( 40, (int) ( $input['shadow_offset_y'] ?? $defaults['shadow_offset_y'] ) ) ),
			'shadow_blur'              => max( 0, min( 60, (int) ( $input['shadow_blur'] ?? $defaults['shadow_blur'] ) ) ),
			'shadow_spread'            => max( -20, min( 40, (int) ( $input['shadow_spread'] ?? $defaults['shadow_spread'] ) ) ),
			'collection_text_position' => self::choice( $input['collection_text_position'] ?? '', array( 'overlay', 'above', 'below', 'beside' ), $defaults['collection_text_position'] ),
			'card_text_position'       => self::choice( $input['card_text_position'] ?? '', array( 'overlay', 'above', 'below', 'beside' ), $defaults['card_text_position'] ),
			'text_layout'              => self::choice( $input['text_layout'] ?? '', array( 'stack', 'row' ), $defaults['text_layout'] ),
			'mobile_ratio'             => self::choice( $input['mobile_ratio'] ?? '', array_keys( self::mobile_ratios() ), $defaults['mobile_ratio'] ),
			'title_font_size'          => max( 12, min( 40, (int) ( $input['title_font_size'] ?? $defaults['title_font_size'] ) ) ),
			'count_font_size'          => max( 10, min( 24, (int) ( $input['count_font_size'] ?? $defaults['count_font_size'] ) ) ),
			'card_title_font_size'     => max( 12, min( 32, (int) ( $input['card_title_font_size'] ?? $defaults['card_title_font_size'] ) ) ),
			'item_gap'                 => max( 0, min( 48, (int) ( $input['item_gap'] ?? $defaults['item_gap'] ) ) ),
			'card_gap'                 => max( 0, min( 48, (int) ( $input['card_gap'] ?? $defaults['card_gap'] ) ) ),
			'panel_radius'             => max( 0, min( 40, (int) ( $input['panel_radius'] ?? $defaults['panel_radius'] ) ) ),
			'panel_padding'            => max( 0, min( 48, (int) ( $input['panel_padding'] ?? $defaults['panel_padding'] ) ) ),
			'panel_background'         => self::sanitize_color( $input['panel_background'] ?? '' ),
			'nav_background'           => self::sanitize_color( $input['nav_background'] ?? '' ),
			'nav_color'                => self::sanitize_color( $input['nav_color'] ?? '' ),
		);
	}

	/**
	 * Map Gutenberg attributes onto the sanitized style set.
	 *
	 * @param array $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	public static function from_attributes( array $attributes ) {
		$show_shadow = true;
		if ( array_key_exists( 'showShadow', $attributes ) ) {
			$show_shadow = ! empty( $attributes['showShadow'] );
		}

		return self::sanitize(
			array(
				'border_radius'            => $attributes['borderRadius'] ?? null,
				'border_width'             => $attributes['borderWidth'] ?? null,
				'border_color'             => $attributes['borderColor'] ?? '',
				'show_shadow'              => $show_shadow,
				'shadow_color'             => $attributes['shadowColor'] ?? '',
				'shadow_offset_x'          => $attributes['shadowOffsetX'] ?? null,
				'shadow_offset_y'          => $attributes['shadowOffsetY'] ?? null,
				'shadow_blur'              => $attributes['shadowBlur'] ?? null,
				'shadow_spread'            => $attributes['shadowSpread'] ?? null,
				'collection_text_position' => $attributes['collectionTextPosition'] ?? '',
				'card_text_position'       => $attributes['cardTextPosition'] ?? '',
				'text_layout'              => $attributes['textLayout'] ?? '',
				'mobile_ratio'             => $attributes['mobileAspectRatio'] ?? '',
				'title_font_size'          => $attributes['titleFontSize'] ?? null,
				'count_font_size'          => $attributes['countFontSize'] ?? null,
				'card_title_font_size'     => $attributes['cardTitleFontSize'] ?? null,
				'item_gap'                 => $attributes['itemGap'] ?? null,
				'card_gap'                 => $attributes['cardGap'] ?? null,
				'panel_radius'             => $attributes['panelRadius'] ?? null,
				'panel_padding'            => $attributes['panelPadding'] ?? null,
				'panel_background'         => $attributes['panelBackground'] ?? '',
				'nav_background'           => $attributes['navBackground'] ?? '',
				'nav_color'                => $attributes['navColor'] ?? '',
			)
		);
	}

	/**
	 * Global styles, or this block's overrides when Use global styles is off.
	 *
	 * @param array $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	public static function resolve( array $attributes ) {
		$use_global = ! array_key_exists( 'useGlobalStyles', $attributes ) || ! empty( $attributes['useGlobalStyles'] );
		if ( $use_global ) {
			return self::get();
		}

		return self::from_attributes( $attributes );
	}

	/**
	 * @param array $styles Sanitized styles.
	 * @return string
	 */
	public static function classes( array $styles ) {
		$styles = self::sanitize( $styles );

		$classes = array(
			'cm-browser--collection-text-' . $styles['collection_text_position'],
			'cm-browser--card-text-' . $styles['card_text_position'],
			'cm-browser--text-' . $styles['text_layout'],
			'cm-browser--phone-' . $styles['mobile_ratio'],
			$styles['show_shadow'] ? 'cm-browser--shadow' : 'cm-browser--no-shadow',
		);

		return implode( ' ', $classes );
	}

	/**
	 * Resolve a theme preset to hex for the Styles tab preview.
	 *
	 * @param string $color Stored color.
	 * @return string
	 */
	public static function preview_color( $color, $fallback = 'currentColor' ) {
		$color = self::sanitize_color( $color );
		if ( '' === $color ) {
			return $fallback;
		}

		foreach ( self::palette() as $item ) {
			if ( $color === $item['css'] && ! empty( $item['hex'] ) ) {
				return $item['hex'];
			}
		}

		return $color;
	}

	/**
	 * Inline palette custom properties for the admin preview.
	 *
	 * @return string
	 */
	public static function palette_css() {
		$parts = array();
		foreach ( self::palette() as $item ) {
			if ( empty( $item['slug'] ) || empty( $item['hex'] ) ) {
				continue;
			}
			$parts[] = '--wp--preset--color--' . $item['slug'] . ':' . $item['hex'];
		}

		return implode( ';', $parts );
	}

	/**
	 * CSS custom-property string for a style set.
	 *
	 * @param array $styles      Style set.
	 * @param bool  $for_preview Use hex for theme colors.
	 * @return string
	 */
	public static function to_css( array $styles, $for_preview = false ) {
		$styles = self::sanitize( $styles );

		$border = $for_preview
			? self::preview_color( $styles['border_color'], 'currentColor' )
			: self::color_to_css( $styles['border_color'], 'currentColor' );

		$shadow = self::sanitize_color( $styles['shadow_color'] ?? '' );
		if ( '' === $shadow ) {
			$shadow = '#00000061';
		} elseif ( $for_preview ) {
			$shadow = self::preview_color( $styles['shadow_color'], '#00000061' );
		}

		$hover_y = (int) $styles['shadow_offset_y'];
		$hover_y += ( $hover_y >= 0 ) ? 4 : -4;

		$panel_fallback = 'color-mix(in srgb, currentColor 8%, transparent)';
		$nav_bg_fallback = 'color-mix(in srgb, #000 72%, transparent)';

		$panel_bg = $styles['panel_background']
			? ( $for_preview ? self::preview_color( $styles['panel_background'], $panel_fallback ) : self::color_to_css( $styles['panel_background'], $panel_fallback ) )
			: $panel_fallback;

		$nav_bg = $styles['nav_background']
			? ( $for_preview ? self::preview_color( $styles['nav_background'], $nav_bg_fallback ) : self::color_to_css( $styles['nav_background'], $nav_bg_fallback ) )
			: $nav_bg_fallback;

		$nav_color = $styles['nav_color']
			? ( $for_preview ? self::preview_color( $styles['nav_color'], '#fff' ) : self::color_to_css( $styles['nav_color'], '#fff' ) )
			: '#fff';

		$vars = array(
			'--cm-tile-radius'     => $styles['border_radius'] . 'px',
			'--cm-border-width'    => $styles['border_width'] . 'px',
			'--cm-border-color'    => $border,
			'--cm-shadow-x'        => (int) $styles['shadow_offset_x'] . 'px',
			'--cm-shadow-y'        => (int) $styles['shadow_offset_y'] . 'px',
			'--cm-shadow-blur'     => (int) $styles['shadow_blur'] . 'px',
			'--cm-shadow-spread'   => (int) $styles['shadow_spread'] . 'px',
			'--cm-shadow-color'    => $shadow,
			'--cm-shadow-hover-y'  => $hover_y . 'px',
			'--cm-title-size'      => $styles['title_font_size'] . 'px',
			'--cm-count-size'      => $styles['count_font_size'] . 'px',
			'--cm-card-title-size' => $styles['card_title_font_size'] . 'px',
			'--cm-item-gap'        => $styles['item_gap'] . 'px',
			'--cm-card-gap'        => $styles['card_gap'] . 'px',
			'--cm-panel-radius'    => $styles['panel_radius'] . 'px',
			'--cm-panel-padding'   => $styles['panel_padding'] . 'px',
			'--cm-panel-bg'        => $panel_bg,
			'--cm-nav-bg'          => $nav_bg,
			'--cm-nav-color'       => $nav_color,
			'--cm-ratio-mobile'    => self::mobile_ratios()[ $styles['mobile_ratio'] ]['css'],
		);

		$parts = array();
		foreach ( $vars as $property => $value ) {
			$parts[] = $property . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Persist styles from the dashboard form.
	 *
	 * @param array $input Raw POST data.
	 */
	public static function save( array $input ) {
		update_option( self::OPTION, self::sanitize( $input ) );
	}

	/**
	 * @param mixed  $value   Raw choice.
	 * @param array  $allowed Allowed keys.
	 * @param string $default Fallback.
	 * @return string
	 */
	private static function choice( $value, array $allowed, $default ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}
}
