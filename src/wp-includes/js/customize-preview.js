/*
 * Script run inside a Customizer preview frame.
 */
(function( exports, $ ){
	var api = wp.customize,
		debounce;

	/**
	 * Returns a debounced version of the function.
	 *
	 * @todo Require Underscore.js for this file and retire this.
	 */
	debounce = function( fn, delay, context ) {
		var timeout;
		return function() {
			var args = arguments;

			context = context || this;

			clearTimeout( timeout );
			timeout = setTimeout( function() {
				timeout = null;
				fn.apply( context, args );
			}, delay );
		};
	};

	/**
	 * @constructor
	 * @augments wp.customize.Messenger
	 * @augments wp.customize.Class
	 * @mixes wp.customize.Events
	 */
	api.Preview = api.Messenger.extend({
		/**
		 * @param {object} params  - Parameters to configure the messenger.
		 * @param {object} options - Extend any instance parameter or method with this object.
		 */
		initialize: function( params, options ) {
			var preview = this, urlParser = document.createElement( 'a' );

			api.Messenger.prototype.initialize.call( preview, params, options );

			urlParser.href = preview.origin();
			preview.add( 'scheme', urlParser.protocol.replace( /:$/, '' ) );

			preview.body = $( document.body );
			preview.window = $( window );
			preview.window.on( 'scroll.preview', debounce( function() {
				preview.send( 'scroll', preview.window.scrollTop() );
			}, 200 ) );

			preview.bind( 'scroll', function( distance ) {
				preview.window.scrollTop( distance );
			});
		}
	});

	/**
	 * Inject the changeset UUID into links in the document.
	 *
	 * @returns {void}
	 */
	api.addLinkPreviewing = function addLinkPreviewing() {
		var linkSelectors = 'a[href], area';

		// Inject links into initial document.
		$( document.body ).find( linkSelectors ).each( function() {
			api.prepareLinkPreview( this );
		} );

		// Inject links for new elements added to the page.
		if ( 'undefined' !== typeof MutationObserver ) {
			api.mutationObserver = new MutationObserver( function( mutations ) {
				_.each( mutations, function( mutation ) {
					$( mutation.target ).find( linkSelectors ).each( function() {
						api.prepareLinkPreview( this );
					} );
				} );
			} );
			api.mutationObserver.observe( document.documentElement, {
				childList: true,
				subtree: true
			} );
		} else {

			// If mutation observers aren't available, fallback to just-in-time injection.
			$( document.documentElement ).on( 'click focus mouseover', linkSelectors, function() {
				api.prepareLinkPreview( this );
			} );
		}
	};

	/**
	 * Parse query string.
	 *
	 * @param {string} queryString Query string.
	 * @returns {object} Parsed query string.
	 */
	api.parseQueryString = function parseQueryString( queryString ) {
		var queryParams = {};
		_.each( queryString.split( '&' ), function( pair ) {
			var parts = pair.split( '=', 2 );
			if ( parts[0] ) {
				queryParams[ decodeURIComponent( parts[0] ) ] = _.isUndefined( parts[1] ) ? null : decodeURIComponent( parts[1] );
			}
		} );
		return queryParams;
	};

	/**
	 * Should the supplied link is previewable.
	 *
	 * @param {HTMLAnchorElement|HTMLAreaElement} element Link element.
	 * @param {string} element.search Query string.
	 * @param {string} element.pathname Path.
	 * @param {string} element.hostname Hostname.
	 * @returns {boolean} Is appropriate for changeset link.
	 */
	api.isLinkPreviewable = function isLinkPreviewable( element ) {
		var hasMatchingHost, urlParser;

		if ( 'javascript:' === element.protocol ) { // jshint ignore:line
			return true;
		}

		// Only web URLs can be previewed.
		if ( 'https:' !== element.protocol && 'http:' !== element.protocol ) {
			return false;
		}

		urlParser = document.createElement( 'a' );
		hasMatchingHost = ! _.isUndefined( _.find( api.settings.url.allowed, function( allowedUrl ) {
			urlParser.href = allowedUrl;
			if ( urlParser.host === element.host && urlParser.protocol === element.protocol ) {
				return true;
			}
			return false;
		} ) );
		if ( ! hasMatchingHost ) {
			return false;
		}

		// Skip wp login and signup pages.
		if ( /\/wp-(login|signup)\.php$/.test( element.pathname ) ) {
			return false;
		}

		// Allow links to admin ajax as faux frontend URLs.
		if ( /\/wp-admin\/admin-ajax\.php$/.test( element.pathname ) ) {
			return true;
		}

		// Disallow links to admin, includes, and content.
		if ( /\/wp-(admin|includes|content)(\/|$)/.test( element.pathname ) ) {
			return false;
		}

		return true;
	};

	/**
	 * Inject the customize_changeset_uuid query param into links on the frontend.
	 *
	 * @param {HTMLAnchorElement|HTMLAreaElement} element Link element.
	 * @param {object} element.search Query string.
	 * @returns {void}
	 */
	api.prepareLinkPreview = function prepareLinkPreview( element ) {
		var queryParams;

		// Skip links in admin bar.
		if ( $( element ).closest( '#wpadminbar' ).length ) {
			return;
		}

		// Make sure links in preview use HTTPS if parent frame uses HTTPS.
		if ( 'https' === api.preview.scheme.get() && 'http:' === element.protocol && -1 !== api.settings.url.allowedHosts.indexOf( element.host ) ) {
			element.protocol = 'https:';
		}

		if ( ! api.isLinkPreviewable( element ) ) {
			$( element ).addClass( 'customize-unpreviewable' );
			return;
		}
		$( element ).removeClass( 'customize-unpreviewable' );

		queryParams = api.parseQueryString( element.search.substring( 1 ) );
		queryParams.customize_changeset_uuid = api.settings.changeset.uuid;
		if ( ! api.settings.theme.active ) {
			queryParams.customize_theme = api.settings.theme.stylesheet;
		}
		if ( api.settings.channel ) {
			queryParams.customize_messenger_channel = api.settings.channel;
		}
		element.search = $.param( queryParams );

		// Prevent links from breaking out of preview iframe.
		if ( api.settings.channel ) {
			element.target = '_self';
		}
	};

	/**
	 * Inject the changeset UUID into Ajax requests.
	 *
	 * @access private
	 * @return {void}
	 */
	api.addRequestPreviewing = function addRequestPreviewing() {
		$.ajaxPrefilter( function prefilterAjax( options ) {
			var urlParser, queryParams;
			if ( ! api.settings.changeset.uuid ) {
				return;
			}

			urlParser = document.createElement( 'a' );
			urlParser.href = options.url;

			// Abort if the request is not for this site.
			if ( ! api.isLinkPreviewable( urlParser ) ) {
				return;
			}

			queryParams = api.parseQueryString( urlParser.search.substring( 1 ) );
			queryParams.customize_changeset_uuid = api.settings.changeset.uuid;
			if ( ! api.settings.theme.active ) {
				queryParams.customize_theme = api.settings.theme.stylesheet;
			}
			if ( api.settings.channel ) {
				queryParams.customize_messenger_channel = api.settings.channel;
			}
			urlParser.search = $.param( queryParams );

			options.url = urlParser.href;
		} );
	};

	/**
	 * Inject changeset UUID into forms, allowing preview to persist through submissions.
	 *
	 * @access private
	 * @returns {void}
	 */
	api.addFormPreviewing = function addFormPreviewing() {

		// Inject inputs for forms in initial document.
		$( document.body ).find( 'form' ).each( function() {
			api.prepareFormPreview( this );
		} );

		// Inject inputs for new forms added to the page.
		if ( 'undefined' !== typeof MutationObserver ) {
			api.mutationObserver = new MutationObserver( function( mutations ) {
				_.each( mutations, function( mutation ) {
					$( mutation.target ).find( 'form' ).each( function() {
						api.prepareFormPreview( this );
					} );
				} );
			} );
			api.mutationObserver.observe( document.documentElement, {
				childList: true,
				subtree: true
			} );
		}
	};

	/**
	 * Inject changeset into form inputs.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @returns {void}
	 */
	api.prepareFormPreview = function prepareFormPreview( form ) {
		var urlParser, stateParams = {};

		if ( ! form.action ) {
			form.action = location.href;
		}

		urlParser = document.createElement( 'a' );
		urlParser.href = form.action;

		// Make sure forms in preview use HTTPS if parent frame uses HTTPS.
		if ( 'https' === api.preview.scheme.get() && 'http:' === urlParser.protocol && -1 !== api.settings.url.allowedHosts.indexOf( urlParser.host ) ) {
			urlParser.protocol = 'https:';
			form.action = urlParser.href;
		}

		if ( ! api.isLinkPreviewable( urlParser ) ) {
			$( form ).addClass( 'customize-unpreviewable' );
			return;
		}
		$( form ).removeClass( 'customize-unpreviewable' );

		stateParams.customize_changeset_uuid = api.settings.changeset.uuid;
		if ( ! api.settings.theme.active ) {
			stateParams.customize_theme = api.settings.theme.stylesheet;
		}
		if ( api.settings.channel ) {
			stateParams.customize_messenger_channel = api.settings.channel;
		}

		_.each( stateParams, function( value, name ) {
			var input = $( form ).find( 'input[name="' + name + '"]' );
			if ( input.length ) {
				input.val( value );
			} else {
				$( form ).prepend( $( '<input>', {
					type: 'hidden',
					name: name,
					value: value
				} ) );
			}
		} );

		// Prevent links from breaking out of preview iframe.
		if ( api.settings.channel ) {
			form.target = '_self';
		}
	};

	/**
	 * Watch current URL and send keep-alive (heartbeat) messages to the parent.
	 *
	 * Keep the customizer pane notified that the preview is still alive
	 * and that the user hasn't navigated to a non-customized URL.
	 * These messages also keep the customizer updated on the current URL
	 * for JS-driven sites that use history.pushState()/history.replaceState().
	 *
	 * @returns {void}
	 */
	api.keepAliveCurrentUrl = function keepAliveCurrentUrl() {
		var currentUrl, urlParser, queryParams, needsParamRestoration = false;

		urlParser = document.createElement( 'a' );
		urlParser.href = location.href;
		queryParams = api.parseQueryString( urlParser.search.substr( 1 ) );

		if ( history.replaceState ) {
			needsParamRestoration = ! queryParams.customize_changeset_uuid || ( ! api.settings.theme.active && ! queryParams.customize_theme ) || ( api.settings.channel && ! queryParams.customize_messenger_channel );
		}

		// Scrub the URL of any customized state query params.
		_.each( api.settings.changeset.stateQueryParams, function( name ) {
			delete queryParams[ name ];
		} );
		if ( _.isEmpty( queryParams ) ) {
			urlParser.search = '';
		} else {
			urlParser.search = '?' + $.param( queryParams );
		}
		urlParser.hash = '';
		currentUrl = urlParser.href;

		// Ensure that the customized state params remain in the URL.
		if ( needsParamRestoration ) {
			urlParser.href = location.href;
			queryParams.customize_changeset_uuid = api.settings.changeset.uuid;
			if ( ! api.settings.theme.active ) {
				queryParams.customize_changeset_uuid = api.settings.theme.stylesheet;
			}
			if ( api.settings.theme.channel ) {
				queryParams.customize_messenger_channel = api.settings.channel;
			}
			urlParser.search = $.param( queryParams );
			history.replaceState( {}, '', urlParser.href ); // @todo This is going to clobber any state in any JS app. The state needs to be captured.
		}

		if ( api.settings.url.self !== currentUrl ) {
			api.settings.url.self = currentUrl;
			api.preview.send( 'ready', {
				currentUrl: api.settings.url.self,
				activePanels: api.settings.activePanels,
				activeSections: api.settings.activeSections,
				activeControls: api.settings.activeControls
			} );
		} else {
			api.preview.send( 'keep-alive' );
		}
	};

	$( function() {
		var bg, setValue;

		api.settings = window._wpCustomizeSettings;
		if ( ! api.settings ) {
			return;
		}

		api.preview = new api.Preview({
			url: window.location.href,
			channel: api.settings.channel
		});

		api.addLinkPreviewing();
		api.addRequestPreviewing();
		api.addFormPreviewing();
		$( document.body ).on( 'click.customize-unpreviewable', 'a.customize-unpreviewable', function( event ) {
			event.preventDefault();
			wp.a11y.speak( api.settings.l10n.linkUnpreviewable );
		} );
		$( document.body ).on( 'submit.customize-unpreviewable', 'form.customize-unpreviewable', function( event ) {
			event.preventDefault();
			wp.a11y.speak( api.settings.l10n.formUnpreviewable );
		} );

		/**
		 * Create/update a setting value.
		 *
		 * @param {string}  id            - Setting ID.
		 * @param {*}       value         - Setting value.
		 * @param {boolean} [createDirty] - Whether to create a setting as dirty. Defaults to false.
		 */
		setValue = function( id, value, createDirty ) {
			var setting = api( id );
			if ( setting ) {
				setting.set( value );
			} else {
				createDirty = createDirty || false;
				setting = api.create( id, value, {
					id: id
				} );

				// Mark dynamically-created settings as dirty so they will get posted.
				if ( createDirty ) {
					setting._dirty = true;
				}
			}
		};

		api.preview.bind( 'settings', function( values ) {
			$.each( values, setValue );
		});

		api.preview.trigger( 'settings', api.settings.values );

		$.each( api.settings._dirty, function( i, id ) {
			var setting = api( id );
			if ( setting ) {
				setting._dirty = true;
			}
		} );

		api.preview.bind( 'setting', function( args ) {
			var createDirty = true;
			setValue.apply( null, args.concat( createDirty ) );
		});

		api.preview.bind( 'sync', function( events ) {
			$.each( events, function( event, args ) {
				api.preview.trigger( event, args );
			});
			api.preview.send( 'synced' );
		});

		api.preview.bind( 'active', function() {
			api.preview.send( 'nonce', api.settings.nonce );

			api.preview.send( 'documentTitle', document.title );

			// Send scroll in case of loading via non-refresh.
			api.preview.send( 'scroll', $( window ).scrollTop() );
		});

		api.preview.bind( 'saved', function( response ) {
			var urlParser;

			if ( response.next_changeset_uuid ) {
				api.settings.changeset.uuid = response.next_changeset_uuid;

				// Update UUIDs in links and forms.
				$( document.body ).find( 'a[href], area' ).each( function() {
					api.prepareLinkPreview( this );
				} );
				$( document.body ).find( 'form' ).each( function() {
					api.prepareFormPreview( this );
				} );

				// Replace the UUID in the URL.
				urlParser = document.createElement( 'a' );
				urlParser.href = location.href;
				urlParser.search = urlParser.search.replace( /(\?|&)customize_changeset_uuid=[^&]+(&|$)/, '$1' );
				if ( urlParser.search.length > 1 ) {
					urlParser.search += '&';
				}
				urlParser.search += 'customize_changeset_uuid=' + response.next_changeset_uuid;

				if ( history.replaceState ) {
					history.replaceState( {}, document.title, urlParser.href ); // @todo This is going to clobber any state in any JS app. The state needs to be captured.
				}
			}

			api.trigger( 'saved', response );
		} );

		api.bind( 'saved', function() {
			api.each( function( setting ) {
				setting._dirty = false;
			} );
		} );

		api.preview.bind( 'nonce-refresh', function( nonce ) {
			$.extend( api.settings.nonce, nonce );
		} );

		/*
		 * Send a message to the parent customize frame with a list of which
		 * containers and controls are active.
		 */

		api.preview.send( 'ready', {
			currentUrl: api.settings.url.self,
			activePanels: api.settings.activePanels,
			activeSections: api.settings.activeSections,
			activeControls: api.settings.activeControls
		} );

		// Send ready when URL changes via JS.
		setInterval( api.keepAliveCurrentUrl, 1000 );

		// Display a loading indicator when preview is reloading, and remove on failure.
		api.preview.bind( 'loading-initiated', function () {
			$( 'body' ).addClass( 'wp-customizer-unloading' );
		});
		api.preview.bind( 'loading-failed', function () {
			$( 'body' ).removeClass( 'wp-customizer-unloading' );
		});

		/* Custom Backgrounds */
		bg = $.map(['color', 'image', 'position_x', 'repeat', 'attachment'], function( prop ) {
			return 'background_' + prop;
		});

		api.when.apply( api, bg ).done( function( color, image, position_x, repeat, attachment ) {
			var body = $(document.body),
				head = $('head'),
				style = $('#custom-background-css'),
				update;

			update = function() {
				var css = '';

				// The body will support custom backgrounds if either
				// the color or image are set.
				//
				// See get_body_class() in /wp-includes/post-template.php
				body.toggleClass( 'custom-background', !! ( color() || image() ) );

				if ( color() )
					css += 'background-color: ' + color() + ';';

				if ( image() ) {
					css += 'background-image: url("' + image() + '");';
					css += 'background-position: top ' + position_x() + ';';
					css += 'background-repeat: ' + repeat() + ';';
					css += 'background-attachment: ' + attachment() + ';';
				}

				// Refresh the stylesheet by removing and recreating it.
				style.remove();
				style = $('<style type="text/css" id="custom-background-css">body.custom-background { ' + css + ' }</style>').appendTo( head );
			};

			$.each( arguments, function() {
				this.bind( update );
			});
		});

		/**
		 * Custom Logo
		 *
		 * Toggle the wp-custom-logo body class when a logo is added or removed.
		 *
		 * @since 4.5.0
		 */
		api( 'custom_logo', function( setting ) {
			$( 'body' ).toggleClass( 'wp-custom-logo', !! setting.get() );
			setting.bind( function( attachmentId ) {
				$( 'body' ).toggleClass( 'wp-custom-logo', !! attachmentId );
			} );
		} );

		api.trigger( 'preview-ready' );
	});

})( wp, jQuery );
