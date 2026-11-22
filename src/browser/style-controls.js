import { __ } from '@wordpress/i18n';
import { PanelColorSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';

const PHONE_RATIOS = [
	{ label: __( 'Portrait 4:5', 'collections-manager' ), value: '4-5' },
	{ label: __( 'Square 1:1', 'collections-manager' ), value: '1-1' },
	{ label: __( 'Tall 3:4', 'collections-manager' ), value: '3-4' },
	{ label: __( 'Landscape 16:9', 'collections-manager' ), value: '16-9' },
	{ label: __( 'Story 9:16', 'collections-manager' ), value: '9-16' },
];

const TEXT_POSITIONS = [
	{ label: __( 'On the image', 'collections-manager' ), value: 'overlay' },
	{ label: __( 'Above the image', 'collections-manager' ), value: 'above' },
	{ label: __( 'Below the image', 'collections-manager' ), value: 'below' },
	{ label: __( 'Next to the image', 'collections-manager' ), value: 'beside' },
];

export default function StyleControls( { attributes, setAttributes } ) {
	const {
		useGlobalStyles,
		borderRadius,
		borderWidth,
		showShadow,
		borderColor,
		shadowColor,
		shadowOffsetX,
		shadowOffsetY,
		shadowBlur,
		shadowSpread,
		collectionTextPosition,
		cardTextPosition,
		textLayout,
		mobileAspectRatio,
		titleFontSize,
		countFontSize,
		cardTitleFontSize,
		itemGap,
		cardGap,
		panelRadius,
		panelPadding,
		panelBackground,
		navBackground,
		navColor,
	} = attributes;

	return (
		<>
			<PanelBody title={ __( 'Styles', 'collections-manager' ) } initialOpen={ true }>
				<ToggleControl
					label={ __( 'Use global styles', 'collections-manager' ) }
					help={ __(
						'Managed under Collections → Styles. Turn this off to style only this block.',
						'collections-manager'
					) }
					checked={ useGlobalStyles !== false }
					onChange={ ( value ) => setAttributes( { useGlobalStyles: value } ) }
				/>
			</PanelBody>
			{ useGlobalStyles === false && (
				<>
					<PanelBody title={ __( 'Images', 'collections-manager' ) } initialOpen={ true }>
						<SelectControl
							label={ __( 'Phone image shape', 'collections-manager' ) }
							help={ __(
								'Used on screens 781px wide and narrower. Portrait 4:5 crops landscape art the least while still filling the screen.',
								'collections-manager'
							) }
							value={ mobileAspectRatio }
							options={ PHONE_RATIOS }
							onChange={ ( value ) =>
								setAttributes( { mobileAspectRatio: value } )
							}
						/>
					</PanelBody>
					<PanelBody title={ __( 'Text', 'collections-manager' ) } initialOpen={ true }>
						<SelectControl
							label={ __( 'Collection title', 'collections-manager' ) }
							value={ collectionTextPosition }
							options={ TEXT_POSITIONS }
							onChange={ ( value ) =>
								setAttributes( { collectionTextPosition: value } )
							}
						/>
						<SelectControl
							label={ __( 'Post titles', 'collections-manager' ) }
							value={ cardTextPosition }
							options={ TEXT_POSITIONS }
							onChange={ ( value ) =>
								setAttributes( { cardTextPosition: value } )
							}
						/>
						<SelectControl
							label={ __( 'Title and count', 'collections-manager' ) }
							help={ __(
								'How the collection name and story count sit together.',
								'collections-manager'
							) }
							value={ textLayout }
							options={ [
								{
									label: __( 'Stacked', 'collections-manager' ),
									value: 'stack',
								},
								{
									label: __( 'Side by side', 'collections-manager' ),
									value: 'row',
								},
							] }
							onChange={ ( value ) => setAttributes( { textLayout: value } ) }
						/>
						<RangeControl
							label={ __( 'Collection title size', 'collections-manager' ) }
							value={ titleFontSize }
							onChange={ ( value ) =>
								setAttributes( { titleFontSize: value ?? 18 } )
							}
							min={ 12 }
							max={ 40 }
						/>
						<RangeControl
							label={ __( 'Story count size', 'collections-manager' ) }
							value={ countFontSize }
							onChange={ ( value ) =>
								setAttributes( { countFontSize: value ?? 12 } )
							}
							min={ 10 }
							max={ 24 }
						/>
						<RangeControl
							label={ __( 'Post title size', 'collections-manager' ) }
							value={ cardTitleFontSize }
							onChange={ ( value ) =>
								setAttributes( { cardTitleFontSize: value ?? 15 } )
							}
							min={ 12 }
							max={ 32 }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Spacing', 'collections-manager' ) } initialOpen={ false }>
						<RangeControl
							label={ __( 'Space between tiles', 'collections-manager' ) }
							value={ itemGap }
							onChange={ ( value ) => setAttributes( { itemGap: value ?? 14 } ) }
							min={ 0 }
							max={ 48 }
						/>
						<RangeControl
							label={ __( 'Space between cards', 'collections-manager' ) }
							value={ cardGap }
							onChange={ ( value ) => setAttributes( { cardGap: value ?? 12 } ) }
							min={ 0 }
							max={ 48 }
						/>
					</PanelBody>
					<PanelBody title={ __( 'Border & shadow', 'collections-manager' ) } initialOpen={ false }>
						<RangeControl
							label={ __( 'Border radius', 'collections-manager' ) }
							value={ borderRadius }
							onChange={ ( value ) =>
								setAttributes( { borderRadius: value ?? 0 } )
							}
							min={ 0 }
							max={ 40 }
						/>
						<RangeControl
							label={ __( 'Border width', 'collections-manager' ) }
							value={ borderWidth }
							onChange={ ( value ) =>
								setAttributes( { borderWidth: value ?? 0 } )
							}
							min={ 0 }
							max={ 8 }
						/>
						<ToggleControl
							label={ __( 'Drop shadow', 'collections-manager' ) }
							checked={ !! showShadow }
							onChange={ ( value ) => setAttributes( { showShadow: value } ) }
						/>
						{ !! showShadow && (
							<>
								<RangeControl
									label={ __( 'Shadow horizontal offset', 'collections-manager' ) }
									value={ shadowOffsetX }
									onChange={ ( value ) =>
										setAttributes( { shadowOffsetX: value ?? 0 } )
									}
									min={ -40 }
									max={ 40 }
								/>
								<RangeControl
									label={ __( 'Shadow vertical offset', 'collections-manager' ) }
									value={ shadowOffsetY }
									onChange={ ( value ) =>
										setAttributes( { shadowOffsetY: value ?? 0 } )
									}
									min={ -40 }
									max={ 40 }
								/>
								<RangeControl
									label={ __( 'Shadow blur', 'collections-manager' ) }
									value={ shadowBlur }
									onChange={ ( value ) =>
										setAttributes( { shadowBlur: value ?? 0 } )
									}
									min={ 0 }
									max={ 60 }
								/>
								<RangeControl
									label={ __( 'Shadow spread', 'collections-manager' ) }
									value={ shadowSpread }
									onChange={ ( value ) =>
										setAttributes( { shadowSpread: value ?? 0 } )
									}
									min={ -20 }
									max={ 40 }
								/>
							</>
						) }
					</PanelBody>
					<PanelBody title={ __( 'Slider box', 'collections-manager' ) } initialOpen={ false }>
						<RangeControl
							label={ __( 'Box radius', 'collections-manager' ) }
							value={ panelRadius }
							onChange={ ( value ) =>
								setAttributes( { panelRadius: value ?? 16 } )
							}
							min={ 0 }
							max={ 40 }
						/>
						<RangeControl
							label={ __( 'Box padding', 'collections-manager' ) }
							value={ panelPadding }
							onChange={ ( value ) =>
								setAttributes( { panelPadding: value ?? 18 } )
							}
							min={ 0 }
							max={ 48 }
						/>
					</PanelBody>
					<PanelColorSettings
						title={ __( 'Colors', 'collections-manager' ) }
						initialOpen={ false }
						colorSettings={ [
							{
								value: borderColor,
								onChange: ( value ) =>
									setAttributes( { borderColor: value || '' } ),
								label: __( 'Border', 'collections-manager' ),
							},
							...( showShadow
								? [
										{
											value: shadowColor,
											onChange: ( value ) =>
												setAttributes( { shadowColor: value || '' } ),
											label: __( 'Shadow', 'collections-manager' ),
										},
								  ]
								: [] ),
							{
								value: panelBackground,
								onChange: ( value ) =>
									setAttributes( { panelBackground: value || '' } ),
								label: __( 'Slider box', 'collections-manager' ),
							},
							{
								value: navBackground,
								onChange: ( value ) =>
									setAttributes( { navBackground: value || '' } ),
								label: __( 'Arrow background', 'collections-manager' ),
							},
							{
								value: navColor,
								onChange: ( value ) =>
									setAttributes( { navColor: value || '' } ),
								label: __( 'Arrow color', 'collections-manager' ),
							},
						] }
					/>
				</>
			) }
		</>
	);
}
