/* global _, JSON, jQuery, QUnit, wp */
jQuery( function() {

	var $, api, getAssertText, PostMessageListener, ListenerForEventAttachedToContainer,
		setupAndTeardown, testSettingChange, titleMockSettings, descriptionMockSettings;

	$ = jQuery;
	api = wp.customize;

	/**
	 * Get a string that describes an assertion.
	 *
	 * @since 4.6
	 *
	 * @param {string} valueName The value used in the assertion.
	 * @return {string} description String to describe the assertion in the .html file.
	 */
	getAssertText = function( valueName ) {
		var assertText = 'The % value is set properly.';
		return assertText.replace( '%', valueName );
	};

	/**
	 * Listen for messages sent via the postMessage transport.
	 *
	 * Must call init() method before listening for each message.
	 * Cannot have separate instances.
	 * This needs to override the method api.preview.targetWindow().postMessage
	 *
	 */
	PostMessageListener = ( function() {
		var self = this;

		/**
		 * Override previous function definition, to capture the messages that are sent via postMessage.
		 */
		api.preview.targetWindow = function() {
			return {
				postMessage: function( message ) {
					var messageId = JSON.parse( message).id;
					if ( self.messageIdsSent ) {
						self.messageIdsSent.push( messageId );
					}
				}
			};
		};

		return {
			/**
			 * Initialize with an empty array, to store message ids.
			 *
			 * @return {null}
			 */
			init: function() {
				self.messageIdsSent = [];
			},

			/**
			 * Find if a given message id was sent via the postMessage transport.
			 *
			 * @param {string} messageId The id that is passed as an argument to the postMessage method.
			 * @returns {boolean|null} wasMessageSent Whether a given message was sent since the init() method was called.
			 */
			wasMessageSent: function( messageId ) {
				if ( ! self.messageIdsSent ) {
					throw new Error( 'PostMessageListener must be initialized.' );
				}
				return self.messageIdsSent.includes( messageId );
			}
		};

	} )();

	/**
	  * Capture whether an event was fired for a given object or function.
	  *
	  * Must create a separate instance to listen to each event, using the 'new' keyword.
	  *
	  * @param {string} eventSlug The event to listen for.
	  * @param {object|function} container Where the event was fired.
	  *
	  *
	  */
	ListenerForEventAttachedToContainer = function( eventSlug, container ) {
		var self;
		self = this;
		self.didFire = false;

		// Record when the event is fired.
		container.bind( eventSlug, function() {
			self.didFire = true;
		} );

		return {
			/**
			 * Return whether the eventSlug passed in the instantiation has fired.
			 *
			 * @returns {boolean} didFire
			 */
			didEventFire: function () {
				return self.didFire;
			}
		};
	};

	setupAndTeardown = {
		beforeEach: function() {
			// Clear stored partials at the beginning of each module
			api.selectiveRefresh.partial = new api.Values( { defaultConstructor: api.selectiveRefresh.Partial } );
		},
		afterEach: function() {
			// Remove events stored in listener
			PostMessageListener.init();
		}
	};

	QUnit.module( 'Initial setup of Selective Refresh', setupAndTeardown );

	QUnit.test( 'Initial properties and settings', function( assert ) {
		var customizeQuery, assertItemsEmptyInData, itemsExpectedToBeEmpty;
		customizeQuery = api.selectiveRefresh.getCustomizeQuery();
		assertItemsEmptyInData = function( collection ) {
			_.each( collection, function( indexToTest ) {
				var assertionDescription = 'Initially, % data is empty.';
				assert.ok( _.isEmpty( api.selectiveRefresh.data[ indexToTest ] ), assertionDescription.replace( '%', indexToTest ) );
			} );
		};
		itemsExpectedToBeEmpty = [ 'partials', 'renderQueryVar', 'currentRequest' ];

		assertItemsEmptyInData( itemsExpectedToBeEmpty );
		assert.equal( customizeQuery.nonce, api.settings.nonce.preview, getAssertText( 'customizeQuery nonce value' ) );
		assert.equal( customizeQuery.theme, api.settings.theme.stylesheet, getAssertText( 'customizeQuery theme value' ) );
		assert.equal( customizeQuery.wp_customize, 'on', getAssertText( 'customizeQuery wp_customize' ) );
		assert.ok( _.isEmpty( api.selectiveRefresh.partialConstructor ), 'Initially, there is no partialConstructor.' );
		assert.ok( _.isEmpty( api.selectiveRefresh.data.l10n.shiftClickToEdit ), 'Initially, shiftClickToEdit is empty.' );
		assert.ok( _.isEmpty( api.selectiveRefresh._pendingPartialRequests ), 'Initially, there is no pending partial requests.' );
		assert.ok( _.isEmpty( api.selectiveRefresh._debouncedTimeoutId ), 'Initially, there is no _debouncedTimeoutId.' );
		assert.ok( _.isEmpty( api.selectiveRefresh._currentRequest ), 'Initially, there is no current request.' );
	} );

	QUnit.test( 'selectiveRefresh object has all values in api.Events', function( assert ) {
		assert.ok( function() {
			var eventsKeys, doEventsMatch;

			eventsKeys = _.keys( api.Events );
			doEventsMatch = eventsKeys.length ? true : false;
			_.each( eventsKeys, function( key ) {
				if ( api.selectiveRefresh[ key ] !== api.Events[ key ] ) {
					doEventsMatch = false;
				}
			} );
			return doEventsMatch;
		}, 'All values in api.Events are present.' );
	} );

	QUnit.test( 'Test a mock partial with placements, including methods and properties.' , function( assert ) {
		var partialId, selector, elementWIthId, options, mockPartial, settingValue, relatedSetting, expectedContainer,
			expectedPlacement, placementNoContainer, placementNoAddedContent, placementNoStringAddedContent,
			placementContext, placementContainer, placementWithContextAndContainer;

		partialId = 'mock-partial';
		selector = '#fixture-mock-partial';

		elementWIthId = $( '<div>' ).data( 'customize-partial-id', partialId );
		$( selector ).append( elementWIthId );

		options = {
			params : {
				containerInclusive: true,
				fallbackRefresh: false,
				selector: selector,
				settings: [ partialId ]
			}
		};

		mockPartial = new api.selectiveRefresh.Partial( partialId, options );

		assert.equal( mockPartial._pendingRefreshPromise, null, getAssertText( '_pendingRefreshPromise' ) );
		api.selectiveRefresh.partial.add( partialId, mockPartial );

		settingValue = 'bar';
		relatedSetting = api.create(
			partialId,
			settingValue,
			{
				id: partialId
			}
		);

		expectedContainer = $( selector );
		expectedPlacement = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: expectedContainer,
			context: expectedContainer.data( 'customize-partial-placement-context' )
		} );

		// Test that the mockPartial settings were correctly applied.
		PostMessageListener.init();
		assert.equal( mockPartial.id, partialId, getAssertText( 'id' ) );
		assert.equal( mockPartial.params.selector, selector, getAssertText( 'selector' ) );
		assert.equal( mockPartial.params.containerInclusive, true, getAssertText( 'containerInclusive' ) );
		assert.equal( mockPartial.params.fallbackRefresh, false, getAssertText( 'fallbackRefresh' ) );
		assert.equal( expectedPlacement.partial.id, partialId, getAssertText( 'partialId' ) );
		assert.equal( expectedPlacement.partial.params.selector, selector, getAssertText( 'selector') );
		assert.equal( 'object', typeof mockPartial.deferred, getAssertText( 'mockPartiald' ) );
		assert.equal( mockPartial.settings(), partialId, getAssertText( 'settings' ) );
		assert.ok( mockPartial.isRelatedSetting( relatedSetting.id ), 'The isRelatedSetting method identifies a related setting.' );
		assert.notOk( mockPartial.isRelatedSetting( 'fooBar' ), 'The isRelatedSetting method identifies an unrelated setting.' );

		mockPartial.showControl();
        assert.ok( PostMessageListener.wasMessageSent( 'focus-control-for-setting' ), 'Calling the showControl method sends the proper type of message.' );

		mockPartial.preparePlacement( expectedPlacement );
		assert.ok( $( expectedPlacement.container ).hasClass( 'customize-partial-refreshing' ), 'preparePlacement adds the correct class.' );

		placementNoContainer = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: '',
			context: ''
		} );

		placementNoAddedContent = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: $( selector ),
			context: '',
			addedContent: false
		} );

		placementNoStringAddedContent = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: $( selector ),
			context: '',
			addedContent: 124
		} );

		mockPartial.preparePlacement( expectedPlacement );
		assert.ok( $( expectedPlacement.container ).hasClass( 'customize-partial-refreshing' ),
					'The placement has the correct class when prepared.'
		);

		assert.notOk( mockPartial.renderContent( placementNoContainer ), 'A placement with no container is not rendered.' );
		assert.notOk( mockPartial.renderContent( placementNoAddedContent ), 'A placement with no addedContent is not rendered.' );
		assert.notOk( mockPartial.renderContent( placementNoStringAddedContent ), 'A placement with addedContent that is not a string is not rendered.' );

		assert.notOk( ( function() {
			var placementNoPartial;
			try {
				placementNoPartial = new api.selectiveRefresh.Placement( {
					partial: false,
					container: $( selector ),
					context: ''
				} );
				mockPartial.renderContent( placementNoPartial );
				return true;
			} catch( error ) {
				return false;
			}
		} )(), 'Creating a placement with no partial throws an error, as expected.' );

		placementContext = { 'partial-context' : 'location' };
		placementContainer = $( selector );

		placementWithContextAndContainer = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			context: placementContext,
			container: placementContainer,
			addedContent: 'Additional content'
		} );

		assert.ok( _.isEqual( placementWithContextAndContainer.context, placementContext ), getAssertText( 'context') );
		assert.ok( _.isEqual( placementWithContextAndContainer.container, placementContainer ), getAssertText( 'container' ) );
		assert.ok( mockPartial.renderContent( placementWithContextAndContainer ),
				   'A placement with sufficient arguments does not return false or an error when rendered.'
		);

		PostMessageListener.init();
		api.selectiveRefresh.requestFullRefresh();
		assert.ok( PostMessageListener.wasMessageSent( 'refresh' ),
			'On a request for a full-page refresh, the refresh message was sent via the postMessage transport.'
		);

	} );

	QUnit.module( 'Change setting values, and verify that the content was refreshed properly.', setupAndTeardown );

	/**
	 * Test the refresh of a partial, based on a settings change.
	 *
	 * @since 4.6
	 *
	 * @param {object} mockSettings The settings to change, including the id and selector.
	 * @return {null}
	 */
	testSettingChange = function( mockSettings ) {

		QUnit.test( mockSettings.prettyPrintSlug, function ( assert ) {

			var settingValue, changedSettingValue, isContainerInclusive, relatedSetting,
				options, contentRenderedListener, renderPartialsResponseListener, mockPartial,
				placement, activeListener, done;

			settingValue = 'Initial Setting';
			changedSettingValue = 'Changed Setting';
			isContainerInclusive = false;

			relatedSetting = api.create(
				mockSettings.settingId,
				settingValue,
				{
				 id: mockSettings.settingId
				}
			);

			options = {
				params: {
					containerInclusive: isContainerInclusive,
					fallbackRefresh: false,
					selector: mockSettings.selector,
					settings: [ mockSettings.settingId ],
					primarySetting: mockSettings.settingId,
					type: 'default'
				}
			};

			/**
			 * Override ajax.send function, mocking a successful response from a server.
			 *
			 * @since 4.6
			 *
			 * @param {string} action The action to fire, unused in this mock function.
			 * @param {object} options The options that would normally be sent with an ajax call.
			 * @return {jQuery.Promise}
			 */
			wp.ajax.send = function ( action, options ) {
				var mockAjaxData, deferred, extendedData, promise;

				mockAjaxData = {
					'contents': {},
					'errors': [],
					'nav_menu_instance_args': [],
					'wp_customize_render_partials': 1
				};
				mockAjaxData.contents[ mockSettings.settingId ] = [ changedSettingValue, changedSettingValue ];

				deferred = $.Deferred();
				extendedData = $.extend( options.data, mockAjaxData );
				deferred.resolveWith( this, [ extendedData ] );
				promise = deferred.promise();

				// The 'api.selectiveRefresh.requestPartial' method sometimes calls the 'abort' method of the ajax request.
				// So mock it with an empty function, to avoid an error.
				promise.abort = function() {};
				return promise;
			};

			PostMessageListener.init();

			// Insert the setting value into its place in the fixture markup.
			$( mockSettings.selector ).html( settingValue );

			contentRenderedListener = new ListenerForEventAttachedToContainer( 'partial-content-rendered', api.selectiveRefresh );
			renderPartialsResponseListener = new ListenerForEventAttachedToContainer( 'render-partials-response', api.selectiveRefresh );

			// Create a partial based on the setting, and add it to api.selectiveRefresh.
			mockPartial = new api.selectiveRefresh.Partial( mockSettings.settingId, options);
			placement = mockPartial.placements()[ 0 ];
			api.selectiveRefresh.partial.add( mockPartial.id, mockPartial);

			activeListener = new ListenerForEventAttachedToContainer( 'active', api.preview );
			api.trigger( 'preview-ready' );
			api.preview.trigger( 'active' );

			assert.ok( activeListener.didEventFire(), 'The active event is properly triggered.' );

			// Change the setting, which should trigger a refresh.
			relatedSetting.set( changedSettingValue );

			assert.equal( api( mockSettings.settingId ).get(), changedSettingValue, 'The setting object was changed properly.');
			assert.ok( placement.container.hasClass( 'customize-partial-refreshing' ), 'Placement has class customize-partial-refreshing upon refreshing.' );

			assert.ok( function() {
					var atLeastOnePartialPresent, foundNonResolvedPartial;

					api.selectiveRefresh.partial.each( function( partial ) {
						atLeastOnePartialPresent = true;
						if ( 'resolved' !== partial.deferred.ready.state() ) {
							foundNonResolvedPartial = true;
						}
					} );
					return ( atLeastOnePartialPresent && ! foundNonResolvedPartial );
				},
				'Partials are resolved after they are added.'
			);

			// Delay the end of this test until this 'done' function is called.
			done = assert.async();

			// Delay assertions because there is a buffer that delays a refresh in the requestPartial method.
			setTimeout( function () {
				assert.equal( $( mockSettings.selector ).html(), changedSettingValue, 'The title is changed properly.' );
				assert.equal( placement.container.html(), changedSettingValue, 'The placement has the changed setting value in its container.' );
				assert.notOk( placement.container.hasClass( 'customize-partial-refreshing' ), 'Placement does not have class after refreshing.' );
				assert.equal( $( mockSettings.selector ).html(), changedSettingValue, 'The content in the container was changed properly.' );
				assert.equal( mockPartial.id, mockSettings.settingId, getAssertText( 'partial id' ) );
				assert.equal( mockPartial.params.selector, mockSettings.selector,  getAssertText( 'partial selector' ) );
				assert.equal( mockPartial.params.containerInclusive, isContainerInclusive,  getAssertText( 'containerInclusive' ) );
				assert.equal( mockPartial.params.fallbackRefresh, false, getAssertText( 'fallbackRefresh' ) );
				assert.equal( placement.partial.id, mockSettings.settingId, 'The partial id was set.' );
				assert.equal( placement.partial.params.selector, mockSettings.selector, 'The selector selector was set.' );
				assert.equal( mockPartial.settings(), mockSettings.settingId, 'The mock partial settings were set.' );
				assert.ok( mockPartial.isRelatedSetting(relatedSetting.id), 'The partial selector was set.' );
				assert.equal( placement.container.data( 'customize-partial-content-rendered' ), true, 'The placement has the correct class based on its state.' );
				assert.ok( contentRenderedListener.didEventFire(), 'The partial-content-rendered event was fired.' );
				assert.ok( renderPartialsResponseListener.didEventFire(), 'The render-partials-response event was fired.' );
				assert.notOk( PostMessageListener.wasMessageSent( 'refresh' ),
					'Does not send a refresh request via postMessage in a successful update of a partial.'
                );
				done();

			// Delay these assertions by slightly longer than the length of the buffer.
			}, api.selectiveRefresh.data.refreshBuffer + 20 );
		} );

	};

	// Selectors are already present in the document inside the '#qunit-fixture' element.
	titleMockSettings = {
		settingId: 'blogname',
		selector: '.site-title a',
		prettyPrintSlug: 'Site Title'
	};

	descriptionMockSettings =  {
		settingId: 'description',
		selector: '.site-description',
		prettyPrintSlug: 'Site Description'
	};

	testSettingChange( titleMockSettings );
	testSettingChange( descriptionMockSettings );

	QUnit.module( 'fallbackRefresh', setupAndTeardown );

	QUnit.test( 'Full-page refresh is applied, as the partial options allow.', function( assert ) {
		var createPartialWithFallbackRefreshSetTo, testFallbackWithRefreshSetTo;

		/**
		 * Override ajax.send function, to mock a failed response from a server.
		 *
		 * This failed response triggers a full page refresh if the partial settings allow it.
		 *
		 * @since 4.6
		 *
		 * @param {string} action The action to fire, unused in this mock function.
		 * @return {jQuery.Promise}
		 */
		wp.ajax.send = function ( action ) {
			var deferred, promise;

			deferred = $.Deferred();
			deferred.rejectWith( action );
			promise = deferred.promise();

			// The 'api.selectiveRefresh.requestPartial' method sometimes calls the 'abort' method of the ajax request.
			// So mock it with an empty function, to avoid an error.
			promise.abort = function() {};
			return promise;
		};

		/**
		 * Factory function for a Partial, with a given fallbackRefresh setting.
		 *
		 * @param {bool} doFallbackRefresh Whether to do a full-page refresh as a fallback.
		 * @returns {wp.customize.selectiveRefresh.Partial}
		 */
		createPartialWithFallbackRefreshSetTo = function( doFallbackRefresh ) {
			var selector, options, partialId;

			selector = '#fixture-mock-partial';
			options = {
				params : {
					fallbackRefresh: doFallbackRefresh,
					containerInclusive: true,
					selector: selector,
					settings: doFallbackRefresh ? [ 'foo-setting' ] : [ 'bar-setting' ]
				}
			};
			partialId = doFallbackRefresh ? 'partial-true-refresh' : 'partial-false-refresh';

			return new api.selectiveRefresh.Partial( partialId, options );
		};

		/**
		 * Test the full-page refresh, based on partial options.
		 *
		 * @param {bool} doFallbackRefresh Setting for whether to apply a full-page refresh.
		 * @return {null}
		 */
		testFallbackWithRefreshSetTo = function( doFallbackRefresh ) {
			var mockPartial, assertionText, done;

 			mockPartial = createPartialWithFallbackRefreshSetTo( doFallbackRefresh );
			api.selectiveRefresh.partial.add( mockPartial.id, mockPartial );

			PostMessageListener.init();
			mockPartial.refresh();

			assertionText = doFallbackRefresh ?
				'There is a refresh when fallbackRefresh is set to true in the partial options.' :
				'There is no refresh when fallbackRefresh is set to false in the partial options.';

			done = assert.async();
			setTimeout( function () {
				assert.equal( PostMessageListener.wasMessageSent( 'refresh' ),
					doFallbackRefresh,
					assertionText
				);
				done();
			// Delay assertion by the length of the refresh buffer.
			}, api.selectiveRefresh.data.refreshBuffer );
		};

		testFallbackWithRefreshSetTo( false );
		testFallbackWithRefreshSetTo( true );

	} );

} );
