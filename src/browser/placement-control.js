import { __ } from '@wordpress/i18n';
import { Button, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const TERMS_QUERY = {
	per_page: 100,
	orderby: 'name',
	order: 'asc',
	context: 'view',
};

function mergeTerms( terms, extras ) {
	const bySlug = new Map();
	( terms || [] ).forEach( ( term ) => {
		if ( term?.slug ) {
			bySlug.set( term.slug, term );
		}
	} );
	extras.forEach( ( term ) => {
		if ( ! term?.slug ) {
			return;
		}
		const current = bySlug.get( term.slug );
		bySlug.set( term.slug, {
			...( current || {} ),
			...term,
			placement_active:
				typeof current?.placement_active === 'boolean'
					? current.placement_active
					: term.placement_active,
		} );
	} );
	return Array.from( bySlug.values() );
}

function isInactive( term ) {
	return term?.placement_active === false;
}

export default function PlacementControl( { value, onChange } ) {
	const { terms, isResolving } = useSelect(
		( select ) => {
			const { getEntityRecords, isResolving: isResolvingRecords } =
				select( coreStore );

			return {
				terms: getEntityRecords(
					'taxonomy',
					'collection_placement',
					TERMS_QUERY
				),
				isResolving: isResolvingRecords(
					'taxonomy',
					'collection_placement',
					TERMS_QUERY
				),
			};
		},
		[]
	);
	const { invalidateResolution } = useDispatch( coreStore );
	const [ extras, setExtras ] = useState( [] );
	const [ newName, setNewName ] = useState( '' );
	const [ rename, setRename ] = useState( '' );
	const [ mode, setMode ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( '' );

	const allTerms = mergeTerms( terms, extras );
	const selected = allTerms.find( ( term ) => term.slug === value );

	useEffect( () => {
		setRename( selected?.name || '' );
	}, [ selected?.name, value ] );

	const refreshTerms = () =>
		invalidateResolution( 'getEntityRecords', [
			'taxonomy',
			'collection_placement',
			TERMS_QUERY,
		] );

	const remember = ( term ) => {
		setExtras( ( current ) => [
			...current.filter( ( item ) => item.slug !== term.slug ),
			term,
		] );
	};

	const createPlacement = async () => {
		const name = newName.trim();
		if ( ! name || busy ) {
			return;
		}

		setBusy( true );
		setNotice( '' );
		try {
			const term = await apiFetch( {
				path: '/collections-manager/v1/placements',
				method: 'POST',
				data: { name },
			} );
			remember( term );
			await refreshTerms();
			onChange( term.slug );
			setNewName( '' );
			setMode( '' );
			if ( term.existed ) {
				setNotice(
					__(
						'A placement with that name already exists. This block will use it.',
						'collections-manager'
					)
				);
			}
		} catch ( error ) {
			setNotice(
				error?.message ||
					__( 'The placement could not be created.', 'collections-manager' )
			);
		} finally {
			setBusy( false );
		}
	};

	const renamePlacement = async () => {
		const name = rename.trim();
		if ( ! value || ! name || busy || name === selected?.name ) {
			return;
		}

		setBusy( true );
		setNotice( '' );
		try {
			const term = await apiFetch( {
				path: `/collections-manager/v1/placements/${ value }`,
				method: 'PUT',
				data: { name },
			} );
			remember( term );
			await refreshTerms();
			setMode( '' );
			setNotice( __( 'Placement name updated.', 'collections-manager' ) );
		} catch ( error ) {
			setNotice(
				error?.message ||
					__( 'The placement could not be renamed.', 'collections-manager' )
			);
		} finally {
			setBusy( false );
		}
	};

	if ( isResolving && ! allTerms.length ) {
		return <Spinner />;
	}

	const active = [];
	const inactive = [];
	allTerms.forEach( ( term ) => {
		const option = { label: term.name, value: term.slug };
		if ( isInactive( term ) ) {
			inactive.push( {
				...option,
				label: `${ term.name } (${ __( 'inactive', 'collections-manager' ) })`,
			} );
		} else {
			active.push( option );
		}
	} );

	const options = [
		{
			label: __(
				'No placement — choose collections below',
				'collections-manager'
			),
			value: '',
		},
		...active,
	];
	if ( inactive.length ) {
		options.push( {
			label: __( 'Inactive', 'collections-manager' ),
			value: '__inactive__',
			disabled: true,
		} );
		options.push( ...inactive );
	}

	let help = __(
		'Leave this empty to pick collections on the block. A named placement is its own slot, chosen when you edit a collection.',
		'collections-manager'
	);
	if ( value && isInactive( selected ) ) {
		help = __(
			'Inactive until you update this page and the block still uses it. Assigned collections stay assigned.',
			'collections-manager'
		);
	} else if ( value ) {
		help = __(
			'Shows collections checked for this placement.',
			'collections-manager'
		);
	}

	return (
		<>
			<SelectControl
				label={ __( 'Collection placement', 'collections-manager' ) }
				value={ value || '' }
				options={ options }
				onChange={ ( next ) => {
					if ( '__inactive__' === next ) {
						return;
					}
					setNotice( '' );
					setMode( '' );
					onChange( next );
				} }
				help={ help }
				disabled={ busy }
			/>
			{ '' === mode && !! value && (
				<Button
					variant="link"
					onClick={ () => {
						setRename( selected?.name || '' );
						setMode( 'rename' );
						setNotice( '' );
					} }
					disabled={ busy }
				>
					{ __( 'Rename placement', 'collections-manager' ) }
				</Button>
			) }
			{ 'rename' === mode && (
				<TextControl
					label={ __( 'Placement name', 'collections-manager' ) }
					value={ rename }
					onChange={ setRename }
					disabled={ busy }
					help={ __(
						'Renaming keeps the same slot, so collections assigned to it stay assigned.',
						'collections-manager'
					) }
				/>
			) }
			{ 'rename' === mode && (
				<p>
					<Button
						variant="secondary"
						onClick={ renamePlacement }
						disabled={
							busy ||
							! rename.trim() ||
							rename.trim() === selected?.name
						}
					>
						{ __( 'Update name', 'collections-manager' ) }
					</Button>{ ' ' }
					<Button
						variant="link"
						onClick={ () => setMode( '' ) }
						disabled={ busy }
					>
						{ __( 'Cancel', 'collections-manager' ) }
					</Button>
				</p>
			) }
			{ '' === mode && (
				<Button
					variant="secondary"
					onClick={ () => {
						setMode( 'create' );
						setNotice( '' );
					} }
					disabled={ busy }
				>
					{ __( 'Create placement', 'collections-manager' ) }
				</Button>
			) }
			{ 'create' === mode && (
				<TextControl
					label={ __( 'New placement', 'collections-manager' ) }
					value={ newName }
					onChange={ setNewName }
					disabled={ busy }
					placeholder={ __( 'Home — featured', 'collections-manager' ) }
					onKeyDown={ ( event ) => {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
							createPlacement();
						}
					} }
					help={ __(
						'Pretty name for a new slot. The block switches to it after you create it.',
						'collections-manager'
					) }
				/>
			) }
			{ 'create' === mode && (
				<p>
					<Button
						variant="secondary"
						onClick={ createPlacement }
						disabled={ busy || ! newName.trim() }
					>
						{ __( 'Create placement', 'collections-manager' ) }
					</Button>{ ' ' }
					<Button
						variant="link"
						onClick={ () => setMode( '' ) }
						disabled={ busy }
					>
						{ __( 'Cancel', 'collections-manager' ) }
					</Button>
				</p>
			) }
			{ notice && <p>{ notice }</p> }
		</>
	);
}
