( function ( $ ) {
	var config = window.collectionsManagerDashboard || {};
	var frame;
	var searchTimer;
	var queryTermTimer;
	var taxFilters = {};
	var termCatalog = {};
	var browseToken = 0;
	var browsePage = 1;
	var previewToken = 0;
	var queryTermToken = 0;
	var addMatchKey = '';

	function selectedIds() {
		return $( '#cm-selected-posts li' )
			.map( function () {
				return String( $( this ).data( 'id' ) );
			} )
			.get();
	}

	function addPost( post ) {
		if ( selectedIds().indexOf( String( post.id ) ) !== -1 ) {
			return;
		}

		var $item = $( '<li/>' ).attr( 'data-id', post.id );
		$item.append(
			$( '<input type="hidden" name="collection[post_ids][]" />' ).val( post.id )
		);
		$item.append( iconButton( 'cm-drag', 'dashicons-move', i18n( 'drag', 'Drag to reorder' ) ) );
		$item.append( thumb( post.image ) );
		var $body = $( '<span class="cm-selected-posts__body"/>' );
		$body.append( $( '<span class="cm-selected-posts__title"/>' ).text( post.title ) );
		var meta = metaText( post );
		if ( meta ) {
			$body.append( $( '<span class="cm-selected-posts__meta"/>' ).text( meta ) );
		}
		$item.append( $body );
		$item.append( iconButton( 'cm-move-up', 'dashicons-arrow-up-alt2', i18n( 'moveUp', 'Move up' ) ) );
		$item.append(
			iconButton( 'cm-move-down', 'dashicons-arrow-down-alt2', i18n( 'moveDown', 'Move down' ) )
		);
		$item.append( iconButton( 'cm-remove-post', 'dashicons-no-alt', i18n( 'remove', 'Remove' ) ) );
		$( '#cm-selected-posts' ).append( $item );
	}

	function currentSource() {
		return $( 'input[name="collection[source]"]:checked' ).val() || 'manual';
	}

	function syncSource() {
		var source = currentSource();
		$( '#cm-panel-manual' ).prop( 'hidden', source !== 'manual' );
		$( '#cm-panel-query' ).prop( 'hidden', source !== 'query' );
		$( '#cm-panel-youtube' ).prop( 'hidden', source !== 'youtube' );
		$( '#cm-panel-feed' ).prop( 'hidden', source !== 'feed' );
		syncYoutubeMode();
		if ( source === 'manual' ) {
			ensureBrowse();
		}
		if ( source === 'query' ) {
			refreshQueryPreview();
		}
	}

	function youtubeMode() {
		return $( 'input[name="collection[youtube][mode]"]:checked' ).val() || 'latest';
	}

	function youtubeChannelIds() {
		return $( '#cm-youtube-channels li' )
			.map( function () {
				return String( $( this ).data( 'id' ) );
			} )
			.get();
	}

	function syncYoutubeMode() {
		var mode = youtubeMode();
		var hasChannel = youtubeChannelIds().length > 0;
		var unlocked =
			hasChannel || $.trim( $( '#cm-youtube-playlist-id' ).val() ) !== '';
		$( '#cm-youtube-need-channel' ).prop( 'hidden', unlocked );
		$( '#cm-youtube-after-channel' ).prop( 'hidden', ! unlocked );
		$( '#cm-youtube-latest' ).prop( 'hidden', mode !== 'latest' );
		$( '#cm-youtube-playlist' ).prop( 'hidden', mode !== 'playlist' );
		$( '#cm-youtube-videos' ).prop( 'hidden', mode !== 'videos' );
		syncLimitFields();
		if ( hasChannel && mode === 'playlist' ) {
			loadChannelPlaylists();
		}
	}

	function syncLimitFields() {
		var queryUnlimited = $( '#cm-query-unlimited' ).is( ':checked' );
		$( '#cm-query-limit-wrap' ).prop( 'hidden', queryUnlimited );

		var feedUnlimited = $( '#cm-feed-unlimited' ).is( ':checked' );
		$( '#cm-feed-limit-wrap' ).prop( 'hidden', feedUnlimited );
		$( '#cm-feed-unlimited-help' ).prop( 'hidden', ! feedUnlimited );

		var mode = youtubeMode();
		var ytUnlimited = $( '#cm-youtube-unlimited' ).is( ':checked' );
		$( '#cm-youtube-unlimited' )
			.closest( 'p' )
			.prop( 'hidden', mode === 'videos' );
		$( '#cm-youtube-limit-wrap' ).prop(
			'hidden',
			mode === 'videos' || ytUnlimited
		);
		$( '#cm-youtube-unlimited-help' ).prop(
			'hidden',
			mode === 'videos' || ! ytUnlimited
		);
	}

	function selectedVideoIds() {
		return $( '#cm-youtube-selected li' )
			.map( function () {
				return String( $( this ).data( 'id' ) );
			} )
			.get();
	}

	function channelOption( channel ) {
		return $( '<option/>' ).val( channel.id ).text( channel.title );
	}

	function fillChannelSelect( $pick ) {
		var current = $pick.val();
		$pick.empty();
		$( '#cm-youtube-channels li' ).each( function () {
			$pick.append(
				channelOption( {
					id: String( $( this ).data( 'id' ) ),
					title: $( this ).find( '.cm-selected-posts__title' ).text(),
				} )
			);
		} );
		if ( current && $pick.find( 'option[value="' + current + '"]' ).length ) {
			$pick.val( current );
		}
	}

	function refreshChannelPick() {
		fillChannelSelect( $( '#cm-youtube-channel-pick' ) );
		fillChannelSelect( $( '#cm-youtube-playlist-channel' ) );
		syncYoutubeMode();
	}

	function loadChannelPlaylists() {
		var channelId = $( '#cm-youtube-playlist-channel' ).val();
		var $status = $( '#cm-youtube-playlist-list-status' );
		var $pick = $( '#cm-youtube-playlist-pick' );
		var current = $.trim( $( '#cm-youtube-playlist-id' ).val() );
		if ( ! channelId ) {
			return;
		}
		if ( ! config.hasApiKey ) {
			$status.text(
				'Paste a playlist URL, or add an API key to list playlists.'
			);
			return;
		}
		$status.text( 'Loading playlists…' );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_youtube_channel_playlists',
			_ajax_nonce: config.nonce,
			channel_id: channelId,
		} ).done( function ( response ) {
			$pick.find( 'option:not(:first)' ).remove();
			if ( ! response || ! response.success ) {
				$status.text(
					( response && response.data && response.data.message ) ||
						'Could not list playlists.'
				);
				return;
			}
			var playlists = ( response.data && response.data.playlists ) || [];
			playlists.forEach( function ( playlist ) {
				var label = playlist.title;
				if ( playlist.count ) {
					label += ' (' + playlist.count + ')';
				}
				$pick.append( $( '<option/>' ).val( playlist.id ).text( label ) );
			} );
			if ( current && $pick.find( 'option[value="' + current + '"]' ).length ) {
				$pick.val( current );
			}
			if ( ! playlists.length ) {
				$status.text(
					( config.i18n && config.i18n.playlistsEmpty ) ||
						'No public playlists. Paste a URL instead.'
				);
				return;
			}
			$status.text( '' );
		} );
	}

	function addChannel( channel ) {
		if (
			$( '#cm-youtube-channels li[data-id="' + channel.id + '"]' ).length
		) {
			return;
		}
		var $item = $(
			'<li data-id="' +
				channel.id +
				'">' +
				'<input type="hidden" name="collection[youtube][channels][id][]" />' +
				'<input type="hidden" name="collection[youtube][channels][title][]" />' +
				'<span class="cm-selected-posts__title"></span>' +
				'<button type="button" class="button-link cm-remove-channel">Remove</button>' +
				'</li>'
		);
		$item.find( 'input' ).eq( 0 ).val( channel.id );
		$item.find( 'input' ).eq( 1 ).val( channel.title );
		$item.find( '.cm-selected-posts__title' ).text( channel.title );
		$( '#cm-youtube-channels' ).append( $item );
		refreshChannelPick();
	}

	function addVideo( video ) {
		if ( selectedVideoIds().indexOf( String( video.id ) ) !== -1 ) {
			return;
		}
		var $item = $(
			'<li data-id="' +
				video.id +
				'">' +
				'<input type="hidden" name="collection[youtube][video_ids][]" />' +
				'<input type="hidden" name="collection[youtube][video_titles][]" />' +
				'<span class="cm-selected-posts__title"></span>' +
				'<button type="button" class="button-link cm-move-up">Up</button>' +
				'<button type="button" class="button-link cm-move-down">Down</button>' +
				'<button type="button" class="button-link cm-remove-video">Remove</button>' +
				'</li>'
		);
		$item.find( 'input' ).eq( 0 ).val( video.id );
		$item.find( 'input' ).eq( 1 ).val( video.title );
		$item.find( '.cm-selected-posts__title' ).text( video.title );
		$( '#cm-youtube-selected' ).append( $item );
	}

	var youtubePageToken = '';

	function loadChannelVideos( append ) {
		var channelId = $( '#cm-youtube-channel-pick' ).val();
		var $status = $( '#cm-youtube-video-results' );
		if ( ! channelId ) {
			$status
				.empty()
				.append( $( '<li/>' ).text( ( config.i18n && config.i18n.addChannel ) || 'Add a channel first.' ) )
				.prop( 'hidden', false );
			return;
		}

		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_youtube_channel_videos',
			_ajax_nonce: config.nonce,
			channel_id: channelId,
			s: $.trim( $( '#cm-youtube-video-search' ).val() ),
			page_token: append ? youtubePageToken : '',
		} )
			.done( function ( response ) {
				if ( ! append ) {
					$status.empty();
				}
				if ( ! response || ! response.success ) {
					$status
						.empty()
						.append(
							$( '<li/>' ).text(
								( response && response.data && response.data.message ) ||
									'Could not load videos.'
							)
						)
						.prop( 'hidden', false );
					return;
				}
				var videos = response.data.videos || [];
				youtubePageToken = response.data.next || '';
				if ( ! videos.length && ! append ) {
					$status
						.append(
							$( '<li/>' ).text(
								( config.i18n && config.i18n.noVideos ) || 'No videos found.'
							)
						)
						.prop( 'hidden', false );
					$( '#cm-youtube-more' ).prop( 'hidden', true );
					return;
				}
				videos.forEach( function ( video ) {
					var $row = $(
						'<li><span></span><button type="button" class="button">Add</button></li>'
					);
					$row.find( 'span' ).text( video.title );
					$row.find( 'button' ).on( 'click', function () {
						addVideo( video );
					} );
					$status.append( $row );
				} );
				$status.prop( 'hidden', false );
				$( '#cm-youtube-more' ).prop( 'hidden', ! youtubePageToken );
			} )
			.fail( function () {
				$status
					.empty()
					.append( $( '<li/>' ).text( 'Could not load videos.' ) )
					.prop( 'hidden', false );
			} );
	}

	function taxonomiesFor( postType ) {
		var data = config.postTypes || {};
		return ( data[ postType ] && data[ postType ].taxonomies ) || {};
	}

	function i18n( key, fallback ) {
		return ( config.i18n && config.i18n[ key ] ) || fallback;
	}

	function metaText( post ) {
		var parts = [];
		if ( post.date ) {
			parts.push( post.date );
		}
		( post.terms || [] ).forEach( function ( term ) {
			parts.push( term );
		} );
		return parts.join( ' · ' );
	}

	function thumb( url ) {
		if ( ! url ) {
			return $( '<span class="cm-thumb cm-thumb--empty" aria-hidden="true"></span>' );
		}
		return $( '<img class="cm-thumb" alt="" />' ).attr( 'src', url );
	}

	function iconButton( className, icon, label ) {
		var $button = $( '<button type="button"/>' )
			.addClass( className )
			.attr( 'aria-label', label );
		if ( className !== 'cm-drag' ) {
			$button.addClass( 'button-link' );
		}
		$button.append( $( '<span class="dashicons" aria-hidden="true"/>' ).addClass( icon ) );
		return $button;
	}

	function sortTerms( terms, flat ) {
		if ( flat ) {
			return terms.map( function ( term ) {
				return $.extend( {}, term, { depth: 0 } );
			} );
		}

		var byParent = {};
		terms.forEach( function ( term ) {
			var parent = term.parent || 0;
			if ( ! byParent[ parent ] ) {
				byParent[ parent ] = [];
			}
			byParent[ parent ].push( term );
		} );

		var out = [];
		var seen = {};
		function walk( parent, depth ) {
			( byParent[ parent ] || [] ).forEach( function ( term ) {
				if ( seen[ term.id ] ) {
					return;
				}
				seen[ term.id ] = true;
				out.push( $.extend( {}, term, { depth: depth } ) );
				walk( term.id, depth + 1 );
			} );
		}
		walk( 0, 0 );
		terms.forEach( function ( term ) {
			if ( ! seen[ term.id ] ) {
				out.push( $.extend( {}, term, { depth: 0 } ) );
			}
		} );
		return out;
	}

	function termLabel( term ) {
		var depth = term.depth || 0;
		var prefix = depth ? new Array( depth + 1 ).join( '\u2013 ' ) : '';
		return prefix + term.name;
	}

	function getTerms( postType, taxonomy, search, hideEmpty ) {
		var cacheKey = postType + '|' + taxonomy + '|' + ( hideEmpty ? '1' : '0' );
		var deferred = $.Deferred();
		var catalog = termCatalog[ cacheKey ];
		var query = $.trim( search || '' );

		if ( catalog && ! catalog.truncated ) {
			var needle = query.toLowerCase();
			deferred.resolve( {
				terms: catalog.terms.filter( function ( term ) {
					return ! needle || term.name.toLowerCase().indexOf( needle ) !== -1;
				} ),
				truncated: false,
			} );
			return deferred.promise();
		}

		if ( catalog && catalog.truncated && query.length < 2 ) {
			deferred.resolve( {
				terms: query ? [] : catalog.terms,
				truncated: true,
				needMore: query.length === 1,
			} );
			return deferred.promise();
		}

		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_query_terms',
			_ajax_nonce: config.nonce,
			post_type: postType,
			taxonomy: taxonomy,
			s: query,
			hide_empty: hideEmpty ? 1 : 0,
		} )
			.done( function ( response ) {
				var payload =
					response && response.success && response.data
						? response.data
						: { terms: [], truncated: false };
				if ( ! Array.isArray( payload.terms ) ) {
					payload.terms = [];
				}
				if ( ! query ) {
					termCatalog[ cacheKey ] = payload;
				}
				deferred.resolve( payload );
			} )
			.fail( function () {
				deferred.resolve( { terms: [], truncated: false } );
			} );

		return deferred.promise();
	}

	function closeTaxMenus() {
		$( '.cm-tax__menu' ).prop( 'hidden', true );
		$( '.cm-tax__toggle' ).attr( 'aria-expanded', 'false' );
	}

	function syncTaxButton( $box ) {
		var taxonomy = $box.data( 'taxonomy' );
		var chosen = taxFilters[ taxonomy ];
		var $btn = $box.find( '.cm-tax__toggle' );
		$btn.text( chosen ? chosen.name : $box.data( 'label' ) );
		$btn.toggleClass( 'is-set', !! chosen );
	}

	function currentTaxesParam() {
		return Object.keys( taxFilters )
			.filter( function ( tax ) {
				return taxFilters[ tax ] && taxFilters[ tax ].id;
			} )
			.map( function ( tax ) {
				return tax + ':' + taxFilters[ tax ].id;
			} )
			.join( ';' );
	}

	function filtersActive() {
		return $.trim( $( '#cm-post-search' ).val() ) !== '' || currentTaxesParam() !== '';
	}

	function syncAddMatching() {
		var key = currentTaxesParam() + '|' + $.trim( $( '#cm-post-search' ).val() );
		var $btn = $( '#cm-add-matching' );
		if ( ! filtersActive() ) {
			$btn.prop( 'hidden', true );
			addMatchKey = key;
			return;
		}
		if ( key === addMatchKey ) {
			return;
		}
		addMatchKey = key;
		$btn.prop( 'hidden', false );
		$btn.text( i18n( 'addMatching', 'Add matching posts' ) ).data( 'page', 1 );
	}

	function pickerTaxonomies( postType ) {
		var taxes = taxonomiesFor( postType );
		var preferred = {};
		if ( taxes.category ) {
			preferred.category = taxes.category;
		}
		if ( taxes.post_tag ) {
			preferred.post_tag = taxes.post_tag;
		}
		return Object.keys( preferred ).length ? preferred : taxes;
	}

	function renderTaxFilters() {
		taxFilters = {};
		var postType = $( '#cm-manual-post-type' ).val();
		var taxes = pickerTaxonomies( postType );
		var $wrap = $( '#cm-picker-taxes' ).empty();
		Object.keys( taxes ).forEach( function ( slug ) {
			var label = taxes[ slug ];
			var $box = $( '<div class="cm-tax"/>' );
			$box.data( 'taxonomy', slug );
			$box.data( 'label', label );
			var $toggle = $( '<button type="button" class="button cm-tax__toggle"/>' )
				.attr( 'aria-expanded', 'false' )
				.attr( 'aria-haspopup', 'listbox' )
				.text( label );
			var $menu = $( '<div class="cm-tax__menu" hidden/>' );
			var $search = $( '<input type="search" class="cm-tax__search" autocomplete="off" />' ).attr(
				'placeholder',
				i18n( 'searchTax', 'Search %s…' ).replace( '%s', label )
			);
			var $searchRow = $( '<div class="cm-tax__search-row"/>' );
			$searchRow.append( $search, $( '<span class="cm-spinner" aria-hidden="true"/>' ) );
			$menu.append( $searchRow, $( '<ul class="cm-tax__list" role="listbox"/>' ) );
			$box.append( $toggle, $menu );
			$wrap.append( $box );
		} );
		syncAddMatching();
	}

	function refreshTaxMenu( $box, search ) {
		var taxonomy = $box.data( 'taxonomy' );
		var label = $box.data( 'label' );
		var postType = $( '#cm-manual-post-type' ).val();
		var token = ( $box.data( 'token' ) || 0 ) + 1;
		$box.data( 'token', token );
		armSpinner( $box );
		getTerms( postType, taxonomy, search, true ).done( function ( payload ) {
			if ( $box.data( 'token' ) !== token ) {
				return;
			}
			clearSpinner( $box );
			var $list = $box.find( '.cm-tax__list' ).empty();
			var $all = $( '<button type="button"/>' ).text(
				i18n( 'allTax', 'All %s' ).replace( '%s', label )
			);
			if ( ! taxFilters[ taxonomy ] ) {
				$all.attr( 'aria-current', 'true' );
			}
			$all.on( 'click', function () {
				delete taxFilters[ taxonomy ];
				syncTaxButton( $box );
				closeTaxMenus();
				loadPosts( false );
			} );
			$list.append( $( '<li/>' ).append( $all ) );

			if ( payload.needMore ) {
				$list.append(
					$( '<li class="cm-tax__empty"/>' ).text(
						i18n( 'typeMore', 'Type at least 2 letters to search.' )
					)
				);
				return;
			}

			var terms = sortTerms( payload.terms || [], !! payload.truncated || !! $.trim( search ) );
			terms.forEach( function ( term ) {
				var $btn = $( '<button type="button"/>' );
				$btn.append( $( '<span/>' ).text( termLabel( term ) ) );
				$btn.append( $( '<span class="cm-term-count"/>' ).text( String( term.count ) ) );
				if (
					taxFilters[ taxonomy ] &&
					String( taxFilters[ taxonomy ].id ) === String( term.id )
				) {
					$btn.attr( 'aria-current', 'true' );
				}
				$btn.on( 'click', function () {
					taxFilters[ taxonomy ] = { id: term.id, name: term.name };
					syncTaxButton( $box );
					closeTaxMenus();
					loadPosts( false );
				} );
				$list.append( $( '<li/>' ).append( $btn ) );
			} );

			if ( ! terms.length ) {
				$list.append(
					$( '<li class="cm-tax__empty"/>' ).text( i18n( 'noTerms', 'No matches.' ) )
				);
			} else if ( payload.truncated && ! $.trim( search ) ) {
				$list.append(
					$( '<li class="cm-tax__empty"/>' ).text(
						i18n( 'popularHint', 'Showing popular terms. Type to search the rest.' )
					)
				);
			}
		} );
	}

	function syncRowState( $btn, id ) {
		var on = selectedIds().indexOf( String( id ) ) !== -1;
		$btn.attr( 'aria-pressed', on ? 'true' : 'false' );
		$btn.find( '.cm-pick-row__state' ).text( on ? i18n( 'added', 'Added' ) : i18n( 'add', 'Add' ) );
	}

	function refreshSelected() {
		var count = selectedIds().length;
		$( '#cm-selected-count' ).text( String( count ) );
		$( '#cm-selected-empty' ).prop( 'hidden', count > 0 );
		$( '#cm-search-results .cm-pick-row' ).each( function () {
			syncRowState( $( this ), $( this ).attr( 'data-id' ) );
		} );
	}

	function browseRow( post ) {
		var $btn = $( '<button type="button" class="cm-pick-row"/>' ).attr( 'data-id', post.id );
		$btn.append( thumb( post.image ) );
		var $body = $( '<span class="cm-pick-row__body"/>' );
		$body.append( $( '<span class="cm-pick-row__title"/>' ).text( post.title ) );
		var meta = metaText( post );
		if ( meta ) {
			$body.append( $( '<span class="cm-pick-row__meta"/>' ).text( meta ) );
		}
		$btn.append( $body );
		$btn.append( $( '<span class="cm-pick-row__state"/>' ) );
		$btn.on( 'click', function () {
			togglePost( post );
		} );
		syncRowState( $btn, post.id );
		return $( '<li/>' ).append( $btn );
	}

	function togglePost( post ) {
		if ( selectedIds().indexOf( String( post.id ) ) !== -1 ) {
			$( '#cm-selected-posts li' )
				.filter( function () {
					return String( $( this ).attr( 'data-id' ) ) === String( post.id );
				} )
				.remove();
		} else {
			addPost( post );
		}
		refreshSelected();
	}

	function armSpinner( $root ) {
		window.clearTimeout( $root.data( 'spinTimer' ) );
		$root.data(
			'spinTimer',
			window.setTimeout( function () {
				$root.addClass( 'is-searching' );
			}, 120 )
		);
	}

	function clearSpinner( $root ) {
		window.clearTimeout( $root.data( 'spinTimer' ) );
		$root.removeClass( 'is-searching' );
	}

	function setPostSearchBusy( busy ) {
		$( '#cm-panel-manual' ).toggleClass( 'is-searching', !! busy );
		$( '#cm-post-search-wrap' ).toggleClass( 'is-searching', !! busy );
		$( '#cm-search-results' ).attr( 'aria-busy', busy ? 'true' : 'false' );
		if ( busy ) {
			$( '#cm-picker-status' ).text( i18n( 'searching', 'Searching…' ) );
		}
	}

	function previewVisiblePosts( query ) {
		var needle = $.trim( query || '' ).toLowerCase();
		var visible = 0;
		$( '#cm-search-results > li' ).each( function () {
			var $li = $( this );
			if ( $li.hasClass( 'cm-picker__searching' ) ) {
				return;
			}
			var title = $li.find( '.cm-pick-row__title' ).text().toLowerCase();
			var meta = $li.find( '.cm-pick-row__meta' ).text().toLowerCase();
			var show = ! needle || title.indexOf( needle ) !== -1 || meta.indexOf( needle ) !== -1;
			$li.toggleClass( 'is-filtered-out', ! show );
			if ( show ) {
				visible += 1;
			}
		} );
		$( '#cm-search-results .cm-picker__searching' ).remove();
		if ( needle && ! visible ) {
			var $note = $( '<li class="cm-picker__searching"/>' );
			$note.append( $( '<span class="cm-spinner" aria-hidden="true"/>' ) );
			$note.append( document.createTextNode( i18n( 'searching', 'Searching…' ) ) );
			$( '#cm-search-results' ).prepend( $note );
		}
	}

	function loadPosts( append ) {
		var token = ++browseToken;
		var page = append ? browsePage + 1 : 1;
		if ( ! append ) {
			setPostSearchBusy( true );
		}
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_search_posts',
			_ajax_nonce: config.nonce,
			s: $.trim( $( '#cm-post-search' ).val() ),
			post_type: $( '#cm-manual-post-type' ).val() || 'post',
			page: page,
			per_page: 16,
			taxes: currentTaxesParam(),
		} )
			.done( function ( response ) {
				if ( token !== browseToken ) {
					return;
				}
				var data = ( response && response.success && response.data ) || {};
				var posts = data.posts || [];
				var $list = $( '#cm-search-results' );
				if ( ! append ) {
					$list.empty();
				}
				if ( ! posts.length && ! append ) {
					$list.append(
						$( '<li class="cm-picker__empty"/>' ).text( i18n( 'noPosts', 'No posts match.' ) )
					);
				}
				posts.forEach( function ( post ) {
					$list.append( browseRow( post ) );
				} );
				browsePage = page;
				$( '#cm-load-more' ).prop( 'hidden', ! data.has_more );
				setPostSearchBusy( false );
				$( '#cm-picker-status' ).text(
					posts.length
						? i18n( 'newest', 'Newest first. Click a post to add or remove it.' )
						: ''
				);
				syncAddMatching();
			} )
			.fail( function () {
				if ( token !== browseToken ) {
					return;
				}
				setPostSearchBusy( false );
				$( '#cm-picker-status' ).text( i18n( 'loadError', 'Could not load posts.' ) );
			} );
	}

	function ensureBrowse() {
		if ( currentSource() !== 'manual' ) {
			return;
		}
		if ( $( '#cm-search-results' ).children().length ) {
			return;
		}
		loadPosts( false );
	}

	function queryTaxesParam() {
		var taxonomy = $( '#cm-query-taxonomy' ).val();
		if ( ! taxonomy ) {
			return '';
		}
		var ids = $( '#cm-query-selected-terms input' )
			.map( function () {
				return $( this ).val();
			} )
			.get()
			.filter( Boolean );
		if ( ! ids.length ) {
			return '';
		}
		return taxonomy + ':' + ids.join( ',' );
	}

	function selectedQueryTermIds() {
		var ids = {};
		$( '#cm-query-selected-terms input' ).each( function () {
			ids[ String( $( this ).val() ) ] = true;
		} );
		return ids;
	}

	function addQueryTerm( term ) {
		var id = String( term.id );
		if ( selectedQueryTermIds()[ id ] ) {
			return;
		}
		var $chip = $( '<span class="cm-chip"/>' );
		$chip.append( $( '<input type="hidden" name="collection[query][terms][]" />' ).val( id ) );
		$chip.append( $( '<span class="cm-chip__label"/>' ).text( term.name ) );
		$chip.append(
			$( '<button type="button" class="button-link cm-chip__remove"/>' )
				.attr( 'aria-label', i18n( 'remove', 'Remove' ) )
				.text( '\u00d7' )
		);
		$( '#cm-query-selected-terms' ).append( $chip );
		refreshQueryPreview();
	}

	function syncQueryTermPlaceholder() {
		var label = $( '#cm-query-taxonomy option:selected' ).text() || 'terms';
		$( '#cm-query-term-search' ).attr(
			'placeholder',
			i18n( 'searchTax', 'Search %s…' ).replace( '%s', label )
		);
		$( '#cm-query-term-search' ).prop( 'hidden', ! $( '#cm-query-taxonomy' ).val() );
	}

	function renderQueryTermResults( payload, search ) {
		var $list = $( '#cm-query-term-results' ).empty();
		if ( payload.needMore ) {
			$list.append(
				$( '<li class="cm-tax__empty"/>' ).text(
					i18n( 'typeMore', 'Type at least 2 letters to search.' )
				)
			);
			$list.prop( 'hidden', false );
			return;
		}

		var selected = selectedQueryTermIds();
		var terms = ( payload.terms || [] ).filter( function ( term ) {
			return ! selected[ String( term.id ) ];
		} );
		terms = sortTerms( terms, !! payload.truncated || !! $.trim( search ) );
		if ( ! terms.length ) {
			$list.append( $( '<li class="cm-tax__empty"/>' ).text( i18n( 'noTerms', 'No matches.' ) ) );
			$list.prop( 'hidden', false );
			return;
		}

		terms.forEach( function ( term ) {
			var $btn = $( '<button type="button"/>' );
			$btn.append( $( '<span/>' ).text( termLabel( term ) ) );
			$btn.append( $( '<span class="cm-term-count"/>' ).text( String( term.count ) ) );
			$btn.on( 'click', function () {
				addQueryTerm( term );
				$( '#cm-query-term-search' ).val( '' );
				loadQueryTermResults( '' );
			} );
			$list.append( $( '<li/>' ).append( $btn ) );
		} );
		if ( payload.truncated && ! $.trim( search ) ) {
			$list.append(
				$( '<li class="cm-tax__empty"/>' ).text(
					i18n( 'popularHint', 'Showing popular terms. Type to search the rest.' )
				)
			);
		}
		$list.prop( 'hidden', false );
	}

	function loadQueryTermResults( search ) {
		var postType = $( '#cm-query-post-type' ).val();
		var taxonomy = $( '#cm-query-taxonomy' ).val();
		var token = ++queryTermToken;
		if ( ! taxonomy ) {
			$( '#cm-query-term-results' ).empty().prop( 'hidden', true );
			return;
		}
		var $picker = $( '#cm-query-terms' );
		armSpinner( $picker );
		getTerms( postType, taxonomy, search, false ).done( function ( payload ) {
			if ( token !== queryTermToken ) {
				return;
			}
			clearSpinner( $picker );
			renderQueryTermResults( payload, search );
		} );
	}

	function refreshQueryPreview() {
		if ( currentSource() !== 'query' || ! $( '#cm-query-preview' ).length ) {
			return;
		}
		var token = ++previewToken;
		var $box = $( '#cm-query-preview' );
		var hasTerms = queryTaxesParam() !== '';
		$box.text( i18n( 'loading', 'Loading…' ) );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_search_posts',
			_ajax_nonce: config.nonce,
			post_type: $( '#cm-query-post-type' ).val() || 'post',
			taxes: queryTaxesParam(),
			per_page: 8,
			page: 1,
			s: '',
		} )
			.done( function ( response ) {
				if ( token !== previewToken ) {
					return;
				}
				var data = ( response && response.success && response.data ) || {};
				var posts = data.posts || [];
				$box.empty();
				$box.append(
					$( '<p class="description"/>' ).text(
						hasTerms
							? i18n( 'previewNewest', 'Newest matches' )
							: i18n(
									'previewAll',
									'No category or tag selected, so this is every published item of this type.'
							  )
					)
				);
				if ( ! posts.length ) {
					$box.append(
						$( '<p class="description"/>' ).text( i18n( 'noPosts', 'No posts match.' ) )
					);
					return;
				}
				var $ul = $( '<ul class="cm-query-preview__list"/>' );
				posts.forEach( function ( post ) {
					var $li = $( '<li/>' );
					$li.append( $( '<span/>' ).text( post.title ) );
					if ( post.date ) {
						$li.append( $( '<span class="cm-term-count"/>' ).text( post.date ) );
					}
					$ul.append( $li );
				} );
				$box.append( $ul );
				if ( data.has_more ) {
					$box.append(
						$( '<p class="description"/>' ).text(
							i18n( 'previewMore', 'More posts match than shown here.' )
						)
					);
				}
			} )
			.fail( function () {
				if ( token !== previewToken ) {
					return;
				}
				$box.text( i18n( 'loadError', 'Could not load posts.' ) );
			} );
	}

	function fillTaxonomySelect() {
		var postType = $( '#cm-query-post-type' ).val();
		var taxes = taxonomiesFor( postType );
		var current = $( '#cm-query-taxonomy' ).val();
		var $tax = $( '#cm-query-taxonomy' ).empty();
		var names = Object.keys( taxes );

		if ( ! names.length ) {
			$tax.append( $( '<option/>' ).val( '' ).text( 'None available' ) );
		} else {
			names.forEach( function ( name ) {
				$tax.append( $( '<option/>' ).val( name ).text( taxes[ name ] ) );
			} );
			if ( current && taxes[ current ] ) {
				$tax.val( current );
			}
		}

		$( '#cm-query-selected-terms' ).empty();
		$( '#cm-query-term-search' ).val( '' );
		$( '#cm-query-term-results' ).empty().prop( 'hidden', true );
		syncQueryTermPlaceholder();
		refreshQueryPreview();
	}

	function initPicker() {
		renderTaxFilters();
		refreshSelected();
		if ( $.fn.sortable ) {
			$( '#cm-selected-posts' ).sortable( {
				handle: '.cm-drag',
				axis: 'y',
				tolerance: 'pointer',
				placeholder: 'cm-sortable-placeholder',
				forcePlaceholderSize: true,
				cancel: 'input,textarea,select,option,.cm-remove-post,.cm-move-up,.cm-move-down',
			} );
		}
		syncQueryTermPlaceholder();
		ensureBrowse();
		if ( currentSource() === 'query' ) {
			refreshQueryPreview();
		}
	}

	$( '.cm-select-image' ).on( 'click', function ( event ) {
		event.preventDefault();
		if ( ! frame ) {
			frame = wp.media( {
				title: 'Select cover image',
				button: { text: 'Use this image' },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var url =
					att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
				$( '#cm-image-id' ).val( att.id );
				$( '.cm-image-preview' ).html(
					'<img src="' +
						url +
						'" alt="" style="max-width:240px;height:auto;display:block;" />'
				);
				$( '.cm-remove-image' ).prop( 'hidden', false );
			} );
		}
		frame.open();
	} );

	$( '.cm-remove-image' ).on( 'click', function ( event ) {
		event.preventDefault();
		$( '#cm-image-id' ).val( '0' );
		$( '.cm-image-preview' ).empty();
		$( this ).prop( 'hidden', true );
	} );

	$( 'input[name="collection[source]"]' ).on( 'change', syncSource );
	$( 'input[name="collection[youtube][mode]"]' ).on( 'change', syncYoutubeMode );
	$( '#cm-query-unlimited, #cm-youtube-unlimited, #cm-feed-unlimited' ).on(
		'change',
		syncLimitFields
	);
	syncLimitFields();

	$( '#cm-youtube-add-channel' ).on( 'click', function () {
		var value = $.trim( $( '#cm-youtube-channel-input' ).val() );
		if ( ! value ) {
			return;
		}
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_youtube_resolve_channel',
			_ajax_nonce: config.nonce,
			channel: value,
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				window.alert(
					( response && response.data && response.data.message ) ||
						'Could not add that channel.'
				);
				return;
			}
			addChannel( response.data );
			$( '#cm-youtube-channel-input' ).val( '' );
		} );
	} );

	$( '#cm-youtube-channels' ).on( 'click', '.cm-remove-channel', function () {
		$( this ).closest( 'li' ).remove();
		refreshChannelPick();
	} );

	$( '#cm-youtube-load-videos' ).on( 'click', function () {
		loadChannelVideos( false );
	} );

	$( '#cm-youtube-more' ).on( 'click', function () {
		loadChannelVideos( true );
	} );

	$( '#cm-youtube-selected' ).on( 'click', '.cm-remove-video', function () {
		$( this ).closest( 'li' ).remove();
	} );

	$( '#cm-youtube-selected' ).on( 'click', '.cm-move-up', function () {
		var $item = $( this ).closest( 'li' );
		$item.prev( 'li' ).before( $item );
	} );

	$( '#cm-youtube-selected' ).on( 'click', '.cm-move-down', function () {
		var $item = $( this ).closest( 'li' );
		$item.next( 'li' ).after( $item );
	} );

	$( '#cm-youtube-playlist-pick' ).on( 'change', function () {
		var value = $( this ).val();
		if ( value ) {
			$( '#cm-youtube-playlist-id' ).val( value );
		}
	} );

	$( '#cm-youtube-playlist-channel' ).on( 'change', function () {
		if ( youtubeMode() === 'playlist' ) {
			loadChannelPlaylists();
		}
	} );

	$( '#cm-youtube-preview-latest' ).on( 'click', function () {
		var $status = $( '#cm-youtube-latest-status' );
		$status.text( 'Checking…' );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_youtube_latest_preview',
			_ajax_nonce: config.nonce,
			channels: youtubeChannelIds().join( ',' ),
			limit: $( '#cm-youtube-limit' ).val(),
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				$status.text(
					( response && response.data && response.data.message ) ||
						'Could not load latest videos.'
				);
				return;
			}
			var count = response.data.count || 0;
			if ( ! count ) {
				$status.text(
					( config.i18n && config.i18n.previewEmpty ) || 'No videos found.'
				);
				return;
			}
			var template =
				( config.i18n && config.i18n.previewOk ) || 'Found %d videos.';
			$status.text( template.replace( '%d', String( count ) ) );
		} );
	} );

	$( '#cm-youtube-preview' ).on( 'click', function () {
		var $status = $( '#cm-youtube-preview-status' );
		$status.text( 'Checking…' );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_youtube_playlist_preview',
			_ajax_nonce: config.nonce,
			playlist: $( '#cm-youtube-playlist-id' ).val(),
			limit: $( '#cm-youtube-limit' ).val(),
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				$status.text(
					( response && response.data && response.data.message ) ||
						'Could not load that playlist.'
				);
				return;
			}
			var count = response.data.count || 0;
			if ( ! count ) {
				$status.text(
					( config.i18n && config.i18n.previewEmpty ) || 'No videos found.'
				);
				return;
			}
			var template =
				( config.i18n && config.i18n.previewOk ) || 'Found %d videos.';
			$status.text( template.replace( '%d', String( count ) ) );
		} );
	} );

	function fillFeedTypes( types, selected ) {
		var $sel = $( '#cm-feed-rest-base' ).empty();
		if ( ! types || ! types.length ) {
			$sel.append( $( '<option/>' ).val( 'posts' ).text( 'Posts' ) );
			return;
		}
		types.forEach( function ( type ) {
			$sel.append( $( '<option/>' ).val( type.slug ).text( type.name ) );
		} );
		if ( selected && $sel.find( 'option[value="' + selected + '"]' ).length ) {
			$sel.val( selected );
		}
	}

	function fillFeedCategories( cats, selected ) {
		var $sel = $( '#cm-feed-category' ).empty();
		$sel.append( $( '<option/>' ).val( '0' ).text( 'All categories' ) );
		( cats || [] ).forEach( function ( cat ) {
			$sel.append(
				$( '<option/>' )
					.val( cat.id )
					.attr( 'data-slug', cat.slug )
					.text( cat.name )
			);
		} );
		if ( selected && $sel.find( 'option[value="' + selected + '"]' ).length ) {
			$sel.val( String( selected ) );
		}
		syncFeedCategorySlug();
	}

	function syncFeedCategorySlug() {
		var $opt = $( '#cm-feed-category option:selected' );
		$( '#cm-feed-category-slug' ).val( $opt.attr( 'data-slug' ) || '' );
	}

	$( '#cm-feed-category' ).on( 'change', syncFeedCategorySlug );

	$( '#cm-feed-discover' ).on( 'click', function () {
		var $status = $( '#cm-feed-discover-status' );
		$status.text( 'Detecting…' );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_feed_discover',
			_ajax_nonce: config.nonce,
			url: $( '#cm-feed-url' ).val(),
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				$status.text(
					( response && response.data && response.data.message ) ||
						'Could not detect that URL.'
				);
				return;
			}
			var data = response.data;
			$( '#cm-feed-kind' ).val( data.kind || 'auto' );
			if ( data.url ) {
				$( '#cm-feed-url' ).val( data.url );
			}
			$( '#cm-feed-rest-fields' ).prop( 'hidden', data.kind === 'rss' );
			if ( data.kind === 'rest' ) {
				fillFeedTypes( data.types, $( '#cm-feed-rest-base' ).val() );
				fillFeedCategories( data.categories, $( '#cm-feed-category' ).val() );
				$status.text(
					( config.i18n && config.i18n.feedRest ) ||
						'WordPress site detected.'
				);
			} else {
				$status.text(
					( config.i18n && config.i18n.feedRss ) || 'XML feed detected.'
				);
			}
		} );
	} );

	$( '#cm-feed-preview' ).on( 'click', function () {
		var $status = $( '#cm-feed-preview-status' );
		$status.text( 'Checking…' );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_feed_preview',
			_ajax_nonce: config.nonce,
			url: $( '#cm-feed-url' ).val(),
			kind: $( '#cm-feed-kind' ).val(),
			rest_base: $( '#cm-feed-rest-base' ).val(),
			category_id: $( '#cm-feed-category' ).val(),
			category_slug: $( '#cm-feed-category-slug' ).val(),
			limit: $( '#cm-feed-limit' ).val(),
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				$status.text(
					( response && response.data && response.data.message ) ||
						'Could not load that feed.'
				);
				return;
			}
			var count = response.data.count || 0;
			if ( ! count ) {
				$status.text(
					( config.i18n && config.i18n.feedEmpty ) || 'No items found.'
				);
				return;
			}
			var template = ( config.i18n && config.i18n.feedOk ) || 'Found %d items.';
			$status.text( template.replace( '%d', String( count ) ) );
		} );
	} );

	$( '#cm-manual-post-type' ).on( 'change', function () {
		renderTaxFilters();
		loadPosts( false );
	} );

	$( '#cm-picker-taxes' ).on( 'click', '.cm-tax__toggle', function ( event ) {
		event.preventDefault();
		var $box = $( this ).closest( '.cm-tax' );
		var $menu = $box.find( '.cm-tax__menu' );
		var willOpen = $menu.prop( 'hidden' );
		closeTaxMenus();
		if ( ! willOpen ) {
			return;
		}
		$menu.prop( 'hidden', false );
		$box.find( '.cm-tax__toggle' ).attr( 'aria-expanded', 'true' );
		$box.find( '.cm-tax__search' ).trigger( 'focus' );
		refreshTaxMenu( $box, $.trim( $box.find( '.cm-tax__search' ).val() ) );
	} );

	$( '#cm-picker-taxes' ).on( 'input', '.cm-tax__search', function () {
		var $box = $( this ).closest( '.cm-tax' );
		var value = $( this ).val();
		window.clearTimeout( $box.data( 'timer' ) );
		$box.data(
			'timer',
			window.setTimeout( function () {
				refreshTaxMenu( $box, $.trim( value ) );
			}, 200 )
		);
	} );

	function isCollectionSearch( el ) {
		return $( el ).is(
			'#cm-post-search, #cm-query-term-search, #cm-youtube-video-search, .cm-tax__search'
		);
	}

	function runCollectionSearch( el ) {
		var $el = $( el );
		if ( $el.is( '#cm-post-search' ) ) {
			window.clearTimeout( searchTimer );
			loadPosts( false );
			return;
		}
		if ( $el.is( '.cm-tax__search' ) ) {
			var $box = $el.closest( '.cm-tax' );
			window.clearTimeout( $box.data( 'timer' ) );
			$box.find( '.cm-tax__menu' ).prop( 'hidden', false );
			$box.find( '.cm-tax__toggle' ).attr( 'aria-expanded', 'true' );
			refreshTaxMenu( $box, $.trim( $el.val() ) );
			return;
		}
		if ( $el.is( '#cm-query-term-search' ) ) {
			window.clearTimeout( queryTermTimer );
			loadQueryTermResults( $.trim( $el.val() ) );
			return;
		}
		if ( $el.is( '#cm-youtube-video-search' ) ) {
			$( '#cm-youtube-load-videos' ).trigger( 'click' );
		}
	}

	var searchEnterGuard = false;

	$( '#cm-collection-form' ).on( 'keydown', 'input', function ( event ) {
		if ( ( event.key !== 'Enter' && event.key !== 'NumpadEnter' ) || ! isCollectionSearch( this ) ) {
			return;
		}
		event.preventDefault();
		searchEnterGuard = true;
		runCollectionSearch( this );
	} );

	$( '#cm-collection-form' ).on( 'submit', function ( event ) {
		var active = document.activeElement;
		if ( ! searchEnterGuard && ! isCollectionSearch( active ) ) {
			return;
		}
		event.preventDefault();
		if ( ! searchEnterGuard && active ) {
			runCollectionSearch( active );
		}
		searchEnterGuard = false;
	} );

	$( '#cm-post-search' ).on( 'input', function () {
		var query = $( this ).val();
		setPostSearchBusy( true );
		previewVisiblePosts( query );
		window.clearTimeout( searchTimer );
		searchTimer = window.setTimeout( function () {
			loadPosts( false );
		}, 150 );
	} );

	$( '#cm-load-more' ).on( 'click', function () {
		loadPosts( true );
	} );

	$( '#cm-add-matching' ).on( 'click', function () {
		var $btn = $( this );
		var page = $btn.data( 'page' ) || 1;
		$btn.prop( 'disabled', true );
		$.getJSON( config.ajaxUrl, {
			action: 'collections_manager_search_posts',
			_ajax_nonce: config.nonce,
			s: $.trim( $( '#cm-post-search' ).val() ),
			post_type: $( '#cm-manual-post-type' ).val() || 'post',
			page: page,
			per_page: 40,
			taxes: currentTaxesParam(),
		} )
			.done( function ( response ) {
				var data = ( response && response.success && response.data ) || {};
				var posts = data.posts || [];
				var before = selectedIds().length;
				posts.forEach( addPost );
				refreshSelected();
				var added = selectedIds().length - before;
				if ( data.has_more ) {
					$btn.data( 'page', page + 1 ).text( i18n( 'addMore', 'Add more matches' ) );
				} else {
					$btn.prop( 'hidden', true );
				}
				$( '#cm-picker-status' ).text(
					added
						? i18n( 'addedCount', 'Added %d posts.' ).replace( '%d', String( added ) )
						: i18n( 'alreadyIn', 'Those posts are already in the collection.' )
				);
			} )
			.fail( function () {
				$( '#cm-picker-status' ).text( i18n( 'loadError', 'Could not load posts.' ) );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );

	$( '#cm-clear-selected' ).on( 'click', function () {
		if ( ! selectedIds().length ) {
			return;
		}
		if (
			! window.confirm(
				i18n( 'clearConfirm', 'Remove every selected post from this collection?' )
			)
		) {
			return;
		}
		$( '#cm-selected-posts' ).empty();
		refreshSelected();
	} );

	$( '#cm-selected-posts' ).on( 'click', '.cm-remove-post', function () {
		$( this ).closest( 'li' ).remove();
		refreshSelected();
	} );

	$( '#cm-selected-posts' ).on( 'click', '.cm-move-up', function () {
		var $item = $( this ).closest( 'li' );
		$item.prev( 'li' ).before( $item );
	} );

	$( '#cm-selected-posts' ).on( 'click', '.cm-move-down', function () {
		var $item = $( this ).closest( 'li' );
		$item.next( 'li' ).after( $item );
	} );

	$( '#cm-query-post-type' ).on( 'change', fillTaxonomySelect );

	$( '#cm-query-taxonomy' ).on( 'change', function () {
		$( '#cm-query-selected-terms' ).empty();
		$( '#cm-query-term-search' ).val( '' );
		$( '#cm-query-term-results' ).empty().prop( 'hidden', true );
		syncQueryTermPlaceholder();
		refreshQueryPreview();
	} );

	$( '#cm-query-selected-terms' ).on( 'click', '.cm-chip__remove', function () {
		$( this ).closest( '.cm-chip' ).remove();
		refreshQueryPreview();
	} );

	$( '#cm-query-term-search' ).on( 'focus', function () {
		loadQueryTermResults( $.trim( $( this ).val() ) );
	} );

	$( '#cm-query-term-search' ).on( 'input', function () {
		var value = $.trim( $( this ).val() );
		window.clearTimeout( queryTermTimer );
		queryTermTimer = window.setTimeout( function () {
			loadQueryTermResults( value );
		}, 200 );
	} );

	$( document ).on( 'click', function ( event ) {
		if ( ! $( event.target ).closest( '.cm-tax' ).length ) {
			closeTaxMenus();
		}
		if ( ! $( event.target ).closest( '#cm-query-terms' ).length ) {
			$( '#cm-query-term-results' ).prop( 'hidden', true );
		}
	} );

	$( document ).on( 'keydown', function ( event ) {
		if ( event.key === 'Escape' ) {
			closeTaxMenus();
			$( '#cm-query-term-results' ).prop( 'hidden', true );
		}
	} );

	initPicker();
} )( jQuery );
