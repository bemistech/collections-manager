import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	ToggleControl,
	SelectControl,
	CheckboxControl,
	Spinner,
	Button,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import StyleControls from './style-controls';
import PlacementControl from './placement-control';
import metadata from './block.json';

const PICKER_PATH = '/collections-manager/v1/picker';

let pickerPromise = null;

function fetchCollections() {
	if ( ! pickerPromise ) {
		pickerPromise = apiFetch( { path: PICKER_PATH } )
			.then( ( rows ) => ( Array.isArray( rows ) ? rows : [] ) )
			.catch( ( error ) => {
				pickerPromise = null;
				throw error;
			} );
	}

	return pickerPromise;
}

function collectionLabel( collection ) {
	if ( collection?.title?.rendered ) {
		return decodeEntities( collection.title.rendered.replace( /<[^>]*>/g, '' ) );
	}
	if ( typeof collection?.title === 'string' && collection.title ) {
		return decodeEntities( collection.title );
	}
	return `#${ collection?.id }`;
}

const VISIBLE_OPTIONS = [
	{
		label: __( 'Fill the row', 'collections-manager' ),
		value: '0',
	},
	...[ 2, 3, 4, 5, 6, 8, 10 ].map( ( count ) => ( {
		label: String( count ),
		value: String( count ),
	} ) ),
];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { showAllCollections, collectionIds, visibleCount, placement } = attributes;
	const placementSlug = typeof placement === 'string' ? placement : '';
	const selectedIds = Array.isArray( collectionIds ) ? collectionIds : [];

	const [ collections, setCollections ] = useState( [] );
	const [ pickerStatus, setPickerStatus ] = useState( 'loading' );
	const [ pickerAttempt, setPickerAttempt ] = useState( 0 );

	useEffect( () => {
		let cancelled = false;
		setPickerStatus( 'loading' );
		fetchCollections()
			.then( ( rows ) => {
				if ( cancelled ) {
					return;
				}
				setCollections( rows );
				setPickerStatus( 'ready' );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setPickerStatus( 'error' );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ pickerAttempt ] );

	const toggleCollection = ( id, checked ) => {
		const next = checked
			? [ ...selectedIds.filter( ( item ) => item !== id ), id ]
			: selectedIds.filter( ( item ) => item !== id );
		setAttributes( { collectionIds: next } );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Placement', 'collections-manager' ) }>
					<PlacementControl
						value={ placementSlug }
						onChange={ ( next ) => setAttributes( { placement: next } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Collections', 'collections-manager' ) }>
					<SelectControl
						label={ __( 'Collections in the row', 'collections-manager' ) }
						help={ __(
							'Fill the row uses every collection you include. Pick 4 or 5 to size tiles for that many; extras use the same slider as posts.',
							'collections-manager'
						) }
						value={ String( visibleCount ?? 0 ) }
						options={ VISIBLE_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { visibleCount: parseInt( value, 10 ) || 0 } )
						}
					/>
					{ !! placementSlug && (
						<p>
							{ __(
								'This block shows collections checked for the placement above.',
								'collections-manager'
							) }
						</p>
					) }
					{ ! placementSlug && (
					<ToggleControl
						label={ __( 'Show all collections', 'collections-manager' ) }
						help={ __(
							'Includes collections even when they have no posts yet.',
							'collections-manager'
						) }
						checked={ showAllCollections !== false }
						onChange={ ( value ) =>
							setAttributes( { showAllCollections: value } )
						}
					/>
					) }
					{ ! placementSlug && showAllCollections === false && (
						<div className="cm-browser-editor__picks">
							<p>
								{ __(
									'Choose which collections appear, in any order you check them.',
									'collections-manager'
								) }
							</p>
							{ 'loading' === pickerStatus && <Spinner /> }
							{ 'error' === pickerStatus && (
								<>
									<p>
										{ __(
											'Collections could not be loaded. The editor may be rate limited. Wait a moment and try again.',
											'collections-manager'
										) }
									</p>
									<Button
										variant="secondary"
										onClick={ () =>
											setPickerAttempt( ( current ) => current + 1 )
										}
									>
										{ __( 'Try again', 'collections-manager' ) }
									</Button>
								</>
							) }
							{ 'ready' === pickerStatus &&
								collections.map( ( collection ) => (
									<CheckboxControl
										key={ collection.id }
										label={ collectionLabel( collection ) }
										checked={ selectedIds.includes( collection.id ) }
										onChange={ ( checked ) =>
											toggleCollection( collection.id, checked )
										}
									/>
								) ) }
							{ 'ready' === pickerStatus && ! collections.length && (
								<p>
									{ __(
										'No collections yet. Add some in Collections Manager.',
										'collections-manager'
									) }
								</p>
							) }
						</div>
					) }
				</PanelBody>
				<StyleControls
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
				httpMethod="POST"
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						icon="index-card"
						label={ metadata.title }
						instructions={
							placementSlug
								? __(
										'No collections are checked for this placement yet.',
										'collections-manager'
								  )
								: showAllCollections === false && ! selectedIds.length
								? __(
										'Turn on Show all collections, or check the collections this block should display.',
										'collections-manager'
								  )
								: __(
										'No collections yet. Add some in Collections Manager.',
										'collections-manager'
								  )
						}
					/>
				) }
			/>
		</div>
	);
}
