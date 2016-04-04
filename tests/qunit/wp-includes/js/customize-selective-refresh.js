/* global _, jQuery, QUnit, wp */
jQuery( function() {

	var $, api, getAssertText, testSettingChange, titleMockSettings, descriptionMockSettings;

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
		var assertText = 'The % value is set properly';
		return assertText.replace( '%', valueName );
	};

	QUnit.module( 'Initial setup of Selective Refresh' );

	QUnit.test( 'Initial properties and settings', function( assert ) {
		var customizeQuery = api.selectiveRefresh.getCustomizeQuery();

		assert.equal( customizeQuery.nonce , api.settings.nonce.preview, 'customizeQuery nonce value is correct.' );
		assert.equal( customizeQuery.theme, api.settings.theme.stylesheet, 'customizeQuery theme value is correct.' );
		assert.equal( customizeQuery.wp_customize, 'on', 'customizeQuery wp_customize value is correct' );
		assert.ok( _.isEmpty( api.selectiveRefresh.partialConstructor, 'Initially, there is no partialConstructor.' ) );
		assert.ok( _.isEmpty( api.selectiveRefresh.data.partials, 'Initially, there are no partials.' ) );
		assert.ok( _.isEmpty( api.selectiveRefresh._pendingPartialRequests, 'Initially, there are no pending partial requests.' ) );
		assert.ok( _.isEmpty( api.selectiveRefresh._debouncedTimeoutId, 'Initially, there is no _debouncedTimeoutId.' ) );
		assert.ok( _.isEmpty( api.selectiveRefresh._currentRequest, 'Initially, there is no current request.' ) );
	});

	QUnit.test( 'selectiveRefresh object has all values in api.Events', function( assert ) {
		assert.ok( function() {
			var eventsKeys, doEventsMatch;

			eventsKeys = _.keys( api.Events );
			doEventsMatch = eventsKeys.length ? true : false;
			_.each( eventsKeys, function( key ) {
				if ( api.selectiveRefresh[ key ] !== api.Events[ key ] ) {
					doEventsMatch = false;
				}
			});
			return doEventsMatch;
		});
	});

	QUnit.test( 'Test a mock partial with placements, including methods and properties.' , function( assert ) {
		var partialId, selector, elementWIthId, options, mockPartial, settingValue, relatedSetting,
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
		api.selectiveRefresh.partial.add( partialId, mockPartial );
		settingValue = 'bar';
		relatedSetting = api.create(
			partialId,
			settingValue,
			{
				id: partialId
			}
		);

		expectedPlacement = mockPartial.placements()[ 0 ];

		// Test that the mockPartial settings are correctly applied.
		assert.equal( mockPartial.id, partialId, getAssertText( 'id' ) );
		assert.equal( mockPartial.params.selector, selector, getAssertText( 'selector' ) );
		assert.equal( mockPartial.params.containerInclusive, true, getAssertText( 'containerInclusive' ) );
		assert.equal( mockPartial.params.fallbackRefresh, false, getAssertText( 'fallbackRefresh' ) );
		assert.equal( expectedPlacement.partial.id, partialId, getAssertText( 'partialId' ) );
		assert.equal( expectedPlacement.partial.params.selector, selector, getAssertText( 'selector') );
		assert.equal( mockPartial.settings(), partialId, getAssertText( 'settings' ) );
		assert.ok( mockPartial.isRelatedSetting( relatedSetting.id ), 'The isRelatedSetting method identifies a related setting.' );
		assert.notOk( mockPartial.isRelatedSetting( 'fooBar' ), 'The isRelatedSetting method identifies an unrelated setting.' );

		placementNoContainer = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: '',
			context: ''
		});

		placementNoAddedContent = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: $( selector ),
			context: '',
			addedContent: false
		});

		placementNoStringAddedContent = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: $( selector ),
			context: '',
			addedContent: 124
		});

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
				});
				mockPartial.renderContent( placementNoPartial );
				return true;
			} catch( error ) {
				return false;
			}
		})(), 'A placement with no partial produces an error, as expected.' );

		placementContext = { 'partial-context' : 'location' };
		placementContainer = $( selector );

		placementWithContextAndContainer = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			context: placementContext,
			container: placementContainer,
			addedContent: 'Additional content'
		});

		assert.ok( _.isEqual( placementWithContextAndContainer.context, placementContext, getAssertText( 'context') ) );
		assert.ok( _.isEqual( placementWithContextAndContainer.container, placementContainer, getAssertText( 'container' ) ) );
		assert.ok( mockPartial.renderContent( placementWithContextAndContainer ),
				   'A placement with sufficient arguments does not return false or an error when rendered'
		);
	});

	QUnit.module( 'Change setting values, and verify that the content is refreshed properly.' );

	/**
	 * Test the refresh of a partial, based on a settings change.
	 *
	 * @since 4.6
	 *
	 * @param {object} mockSettings The settings to change, including the id and selector.
	 * @return {null}
	 */
	testSettingChange = function ( mockSettings ) {

		QUnit.test( mockSettings.prettyPrintSlug, function ( assert ) {

			var settingValue, changedSettingValue, isContainerInclusive, relatedSetting,
				options, mockPartial, placement, done;

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
			 * Override ajax.send function, mocking a response from a server.
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

			// Insert the setting value into its place in the fixture markup.
			$( mockSettings.selector ).html( settingValue );

			// Create a partial based on the setting, and add it to api.selectiveRefresh.
			mockPartial = new api.selectiveRefresh.Partial( mockSettings.settingId, options);
			placement = mockPartial.placements()[ 0 ];
			api.selectiveRefresh.partial.add( mockPartial.id, mockPartial);

			api.trigger( 'preview-ready' );
			api.preview.trigger( 'active' );

			// Change the setting, which should trigger a refresh.
			relatedSetting.set( changedSettingValue );

			assert.equal( api( mockSettings.settingId ).get(), changedSettingValue, 'The setting object was changed properly');
			assert.ok(placement.container.hasClass('customize-partial-refreshing'), 'Placement has class customize-partial-refreshing upon refreshing');

			// Delay the end of this test until this 'done' function is called.
			done = assert.async();

			// Delay assertions because there is a buffer that delays a refresh in the requestPartial method.
			setTimeout( function () {
				assert.equal( $( mockSettings.selector ).html(), changedSettingValue, 'The title is changed properly');
				assert.equal( placement.container.html(), changedSettingValue, 'The placement has the changed setting value in its container.');
				assert.notOk( placement.container.hasClass( 'customize-partial-refreshing' ), 'Placement does not have class after refreshing.');
				assert.equal( $( mockSettings.selector ).html(), changedSettingValue, 'The content in the container was changed properly.');
				assert.equal( mockPartial.id, mockSettings.settingId, getAssertText( 'partial id' ) );
				assert.equal( mockPartial.params.selector, mockSettings.selector,  getAssertText( 'partial selector' ) );
				assert.equal( mockPartial.params.containerInclusive, isContainerInclusive,  getAssertText( 'containerInclusive' ) );
				assert.equal( mockPartial.params.fallbackRefresh, false, getAssertText( 'fallbackRefresh' ) );
				assert.equal( placement.partial.id, mockSettings.settingId, 'The partial id was set.');
				assert.equal( placement.partial.params.selector, mockSettings.selector, 'The selector selector was set.');
				assert.equal( mockPartial.settings(), mockSettings.settingId, 'The mock partial settings were set.');
				assert.ok( mockPartial.isRelatedSetting(relatedSetting.id), 'The partial selector was set.');
				assert.equal( placement.container.data( 'customize-partial-content-rendered' ), true, 'The placement has the correct class based on its state.');
				done();

			// Delay these assertions by slightly longer than the length of the buffer.
			}, api.selectiveRefresh.data.refreshBuffer + 100 );
		});

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

});
