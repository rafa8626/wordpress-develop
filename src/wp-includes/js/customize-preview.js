/*
 * Script run inside a Customizer preview frame.
 */
(function( exports, $ ){
	var api = wp.customize;

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
			this.nonce = params.nonce;
			this.theme = params.theme;
			this.transactionUuid = params.transactionUuid;
			this.allowedUrls = params.allowedUrls;

			this.add( 'scheme', this.origin() ).link( this.origin ).setter( function( to ) {
				var match = to.match( /^https?/ );
				return match ? match[0] : '';
			});

			// TODO: self.send( 'url', wp.customize.settings.requestUri );

			this.body = $( document.body );

			/*
			 * Limit the URL to internal, front-end links.
			 *
			 * If the frontend and the admin are served from the same domain, load the
			 * preview over ssl if the Customizer is being loaded over ssl. This avoids
			 * insecure content warnings. This is not attempted if the admin and frontend
			 * are on different domains to avoid the case where the frontend doesn't have
			 * ssl certs.
			 */
			this.body.on( 'click.preview', 'a', function( event ) {
				var link = $( this ), isInternalJumpLink, to;
				to = link.prop( 'href' );

				/*
				 * Note the shift key is checked so shift+click on widgets or
				 * nav menu items can just result on focusing on the corresponding
				 * control instead of also navigating to the URL linked to.
				 */
				if ( event.shiftKey ) {
					return;
				}

				isInternalJumpLink = ( '#' === link.attr( 'href' ).substr( 0, 1 ) )
				if ( isInternalJumpLink ) {
					return;
				}

				// @todo Instead of preventDefault and bailing, should we instead show an AYS/confirm dialog?

				if ( ! self.isAllowedUrl( to ) ) {
					self.send( 'url', to );
				} else {
					event.preventDefault();
				}
			});

			$( 'form[action], a[href]' ).each( function () {
				var url, el = $( this );
				url = el.prop( 'href' ) || el.prop( 'action' );
				if ( url && ! self.isAllowedUrl( url ) ) {
					el.addClass( 'customize-preview-not-allowed' );
					el.prop( 'title', api.settings.l10n.previewNotAllowed );
				}
			});

			this.body.on( 'submit.preview', 'form', function( event ) {
				var form = $( this );
				if ( ! self.isAllowedUrl( this.action ) ) {
					event.preventDefault();
					return;
				}

				// Inject the needed query parameters into the form
				_.each( self.getPersistentQueryVars(), function ( value, name ) {
					var input;
					if ( ! form[ name ] ) {
						input = $( '<input>', { type: 'hidden', name: name, value: value } );
						form.append( input );
					}
				});
			});

			// Inject persistent query vars into the Ajax request data
			$.ajaxPrefilter( function ( options ) {
				var a, query;
				a = $( '<a>', { href: options.url } );
				if ( self.isAllowedUrl( a.prop( 'href' ) ) && 'javascript' !== a.prop( 'protocol' ) ) {
					query = a.prop( 'search' );
					if ( query && '?' !== query ) {
						query += '&';
					}
					query += $.param( self.getPersistentQueryVars() );
					a.prop( 'search', query );
					options.url = a.prop( 'href' );
				}
			} );

			this.window = $( window );
		},

		/**
		 * Return whether the supplied URL is among those allowed to be previewed.
		 *
		 * @since 4.2.0
		 *
		 * @param {string} url
		 * @returns {boolean}
		 */
		isAllowedUrl: function ( url ) {
			var self = this, result;
			// @todo Instead of preventDefault and bailing, should we instead show an AYS/confirm dialog?

			if ( /^javascript:/i.test( url ) ) {
				return true;
			}

			// Check for URLs that include "/wp-admin/" or end in "/wp-admin", or which are for /wp-login.php
			// Strip hashes and query strings before testing.
			if ( /\/wp-admin(\/(?!admin-ajax\.php)|$)|\/wp-login\.php/.test( url.replace( /[#?].*$/, '' ) ) ) {
				return false;
			}

			// Attempt to match the URL to the control frame's scheme
			// and check if it's allowed. If not, try the original URL.
			$.each([ url.replace( /^https?/, self.scheme() ), url ], function( i, url ) {
				$.each( self.allowedUrls, function( i, allowed ) {
					var path;

					allowed = allowed.replace( /#.*$/, '' ); // Remove hash
					allowed = allowed.replace( /\?.*$/, '' ); // Remove query
					allowed = allowed.replace( /\/+$/, '' ); // Untrailing-slash
					path = url.replace( allowed, '' );

					if ( 0 === url.indexOf( allowed ) && /^([/#?]|$)/.test( path ) ) {
						result = url;
						return false;
					}
				});
				if ( result ) {
					return false;
				}
			});

			return !! result;
		},

		/**
		 * Get the query params that need to be included with each preview request.
		 *
		 * @returns {{wp_customize: string, theme: string, customize_messenger_channel: string}}
		 */
		getPersistentQueryVars: function () {
			return {
				'wp_customize': 'on',
				'theme': this.theme,
				'customize_messenger_channel': this.channel(),
				'customize_transaction_uuid': this.transactionUuid
			};
		}
	});

	$( function() {
		// @todo DOM Ready may be too late to intercept Ajax initial requests
		var bg, setValue;

		api.settings = window._wpCustomizeSettings;
		if ( ! api.settings ) {
			return;
		}

		api.preview = new api.Preview({
			url: window.location.href,
			channel: api.settings.channel,
			theme: api.settings.theme,
			allowedUrls: api.settings.url.allowed,
			transactionUuid: api.settings.transaction.uuid
		});
		if ( api.settings.error ) {
			api.preview.send( 'error', api.settings.error );
			return;
		}

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
		});

		api.preview.bind( 'saved', function( response ) {
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
			activePanels: api.settings.activePanels,
			activeSections: api.settings.activeSections,
			activeControls: api.settings.activeControls
		} );

		api.preview.bind( 'reload', function () {
			window.location.reload();
		});

		// @todo The following is probably unnecessary now, because there is only one iframe.
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

				if ( color() ) {
					css += 'background-color: ' + color() + ';';
				}

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
