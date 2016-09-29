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
			var self = this;

			api.Messenger.prototype.initialize.call( this, params, options );

			this.body = $( document.body );
			// this.body.on( 'click.preview', 'a', function( event ) {
			// 	var link, isInternalJumpLink;
			// 	link = $( this );
			// 	isInternalJumpLink = ( link.attr( 'href' ) && '#' === link.attr( 'href' ).substr( 0, 1 ) );
			// 	event.preventDefault();
			//
			// 	if ( isInternalJumpLink && '#' !== link.attr( 'href' ) ) {
			// 		$( link.attr( 'href' ) ).each( function() {
			// 			this.scrollIntoView();
			// 		} );
			// 	}
			//
			// 	/*
			// 	 * Note the shift key is checked so shift+click on widgets or
			// 	 * nav menu items can just result on focusing on the corresponding
			// 	 * control instead of also navigating to the URL linked to.
			// 	 */
			// 	if ( event.shiftKey || isInternalJumpLink ) {
			// 		return;
			// 	}
			// 	self.send( 'scroll', 0 );
			// 	self.send( 'url', link.prop( 'href' ) );
			// });

			// // You cannot submit forms.
			// this.body.on( 'submit.preview', 'form', function( event ) {
			// 	var urlParser;
			//
			// 	/*
			// 	 * If the default wasn't prevented already (in which case the form
			// 	 * submission is already being handled by JS), and if it has a GET
			// 	 * request method, then take the serialized form data and add it as
			// 	 * a query string to the action URL and send this in a url message
			// 	 * to the customizer pane so that it will be loaded. If the form's
			// 	 * action points to a non-previewable URL, the customizer pane's
			// 	 * previewUrl setter will reject it so that the form submission is
			// 	 * a no-op, which is the same behavior as when clicking a link to an
			// 	 * external site in the preview.
			// 	 */
			// 	if ( ! event.isDefaultPrevented() && 'GET' === this.method.toUpperCase() ) {
			// 		urlParser = document.createElement( 'a' );
			// 		urlParser.href = this.action;
			// 		if ( urlParser.search.substr( 1 ).length > 1 ) {
			// 			urlParser.search += '&';
			// 		}
			// 		urlParser.search += $( this ).serialize();
			// 		api.preview.send( 'url', urlParser.href );
			// 	}
			//
			// 	event.preventDefault();
			// });

			this.window = $( window );
			this.window.on( 'scroll.preview', debounce( function() {
				self.send( 'scroll', self.window.scrollTop() );
			}, 200 ));

			this.bind( 'scroll', function( distance ) {
				self.window.scrollTop( distance );
			});
		}
	});


	/**
	 * Inject the changeset UUID into links in the document.
	 *
	 * @returns {void}
	 */
	api.injectStateIntoLinks = function injectStateIntoLinks() {
		var linkSelectors = 'a, area';

		// Inject links into initial document.
		$( document.body ).find( linkSelectors ).each( function() {
			api.injectStateLinkParams( this );
		} );

		// Inject links for new elements added to the page.
		if ( 'undefined' !== typeof MutationObserver ) {
			api.mutationObserver = new MutationObserver( function( mutations ) {
				_.each( mutations, function( mutation ) {
					$( mutation.target ).find( linkSelectors ).each( function() {
						api.injectStateLinkParams( this );
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
				api.injectStateLinkParams( this );
			} );
		}
	};

	/**
	 * Is matching base URL (host and path)?
	 *
	 * @param {HTMLAnchorElement} parsedUrl Parsed URL.
	 * @param {string} parsedUrl.hostname Host.
	 * @param {string} parsedUrl.pathname Path.
	 * @returns {boolean} Whether matched.
	 */
	api.isMatchingBaseUrl = function isMatchingBaseUrl( parsedUrl ) {
		// @todo return parsedUrl.hostname === api.data.home_url.host && 0 === parsedUrl.pathname.indexOf( api.data.home_url.path );
		return true;
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
	 * Should the supplied link have a changeset UUID added (or does it have one already)?
	 *
	 * @param {HTMLAnchorElement|HTMLAreaElement} element Link element.
	 * @param {string} element.search Query string.
	 * @param {string} element.pathname Path.
	 * @param {string} element.hostname Hostname.
	 * @returns {boolean} Is appropriate for changeset link.
	 */
	api.shouldLinkHaveChangesetUuidParam = function shouldLinkHaveChangesetUuidParam( element ) {
		if ( ! api.isMatchingBaseUrl( element ) ) {
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

		// Disallow links to admin.
		if ( /\/wp-admin(\/|$)/.test( element.pathname ) ) {
			return false;
		}

		// Skip links in admin bar.
		if ( $( element ).closest( '#wpadminbar' ).length ) {
			return false;
		}

		return true;
	};

	/**
	 * Return whether the supplied link element has the changeset query param.
	 *
	 * @param {HTMLAnchorElement|HTMLAreaElement} element Link element.
	 * @param {object} element.search Query string.
	 * @returns {boolean} Whether query param is present.
	 */
	api.doesLinkHaveChangesetUuidQueryParam = function( element ) {
		return /(^|&)customize_changeset_uuid=/.test( element.search.substr( 1 ) );
	};

	/**
	 * Inject the customize_changeset_uuid query param into links on the frontend.
	 *
	 * @param {HTMLAnchorElement|HTMLAreaElement} element Link element.
	 * @param {object} element.search Query string.
	 * @returns {void}
	 */
	api.injectStateLinkParams = function injectStateLinkParams( element ) {
		var queryParams;

		if ( ! api.shouldLinkHaveChangesetUuidParam( element ) ) {
			return;
		}

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
	api.injectStateIntoRequests = function injectStateIntoRequests() {
		$.ajaxPrefilter( function prefilterAjax( options ) {
			var urlParser, queryParams;
			if ( ! api.settings.changeset.uuid ) {
				return;
			}

			urlParser = document.createElement( 'a' );
			urlParser.href = options.url;

			// Abort if the request is not for this site.
			if ( ! api.isMatchingBaseUrl( urlParser ) ) {
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
	api.injectStateIntoForms = function injectStateIntoForms() {

		// Inject inputs for forms in initial document.
		$( document.body ).find( 'form' ).each( function() {
			api.injectStateFormInputs( this );
		} );

		// Inject inputs for new forms added to the page.
		if ( 'undefined' !== typeof MutationObserver ) {
			api.mutationObserver = new MutationObserver( function( mutations ) {
				_.each( mutations, function( mutation ) {
					$( mutation.target ).find( 'form' ).each( function() {
						api.injectStateFormInputs( this );
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
	api.injectStateFormInputs = function injectStateFormInputs( form ) {
		var urlParser, inputs, stateParams = {};

		urlParser = document.createElement( 'a' );
		urlParser.href = form.action;
		if ( ! api.isMatchingBaseUrl( urlParser ) ) {
			return;
		}

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

	$( function() {
		var bg, setValue;

		api.settings = window._wpCustomizeSettings;
		if ( ! api.settings ) {
			return;
		}

		// @todo These need to be called even on unauthenticated requests. In other words, _wpCustomizeSettings should be output regardless.
		api.injectStateIntoLinks();
		api.injectStateIntoRequests();
		api.injectStateIntoForms();

		api.preview = new api.Preview({
			url: window.location.href,
			channel: api.settings.channel
		});

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
			api.trigger( 'saved', response );

			if ( response.next_changeset_uuid ) {
				api.settings.changeset.uuid = response.next_changeset_uuid;

				// Update UUIDs in links and forms.
				$( document.body ).find( 'a, area' ).each( function() {
					api.injectStateLinkParams( this );
				} );
				$( document.body ).find( 'form' ).each( function() {
					api.injectStateFormInputs( this );
				} );
			}
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

		// @todo Include currentUrl.
		api.preview.send( 'ready', {
			activePanels: api.settings.activePanels,
			activeSections: api.settings.activeSections,
			activeControls: api.settings.activeControls,
			settingValidities: api.settings.settingValidities
		} );

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
