( function ( $ ) {
	var config = window.collectionsManagerPlacementOrder || {};
	var $body = $( '#cm-placement-order' );
	var $status = $( '#cm-placement-order-status' );

	if ( ! $body.length || ! config.slug || ! window.wp || ! wp.apiFetch ) {
		return;
	}

	function setStatus( message, isError ) {
		$status.text( message || '' );
		$status.toggleClass( 'cm-placement-order__status--error', !! isError );
	}

	$body.sortable( {
		handle: '.cm-placement-order__handle',
		axis: 'y',
		helper: function ( event, row ) {
			var $cells = row.children();
			var $helper = row.clone();
			$helper.children().each( function ( index ) {
				$( this ).width( $cells.eq( index ).outerWidth() );
			} );
			return $helper;
		},
		update: function () {
			var ids = $body
				.children( 'tr' )
				.map( function () {
					return parseInt( $( this ).attr( 'data-id' ), 10 );
				} )
				.get()
				.filter( function ( id ) {
					return id > 0;
				} );

			setStatus( config.saving || '' );
			wp.apiFetch( {
				path: '/collections-manager/v1/placements/' + config.slug + '/order',
				method: 'POST',
				data: { ids: ids },
			} )
				.then( function () {
					setStatus( config.saved || '' );
				} )
				.catch( function () {
					setStatus( config.error || '', true );
				} );
		},
	} );
} )( window.jQuery );
