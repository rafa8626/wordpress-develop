/* global equal, module, notOk, ok, test   */
jQuery( function() {

	var api, setupAndTearDown;

	setupAndTearDown = ( function() {
		return {
			beforeEach: function() {
				// To avoid altering global namespace, clone 'window.wp'
				api = jQuery.extend( true, {}, window.wp ).customize;
			},
			afterEach: function() {
				api = null;
			}
		};
	})();

	module( 'Customize Selective Refresh', setupAndTearDown );

	//test( 'Models look as expected', function() {
	//	ok( wp.customize.selectiveRefresh.Partial.extended( wp.customize.Class ) );
	//} );

	test( 'Initially, there are no partials', function() {
		ok( _.isEmpty( api.selectiveRefresh.data.partials ) );
	});

	test( 'selectiveRefresh object has events from api.Events', function() {
		ok( function() {
			var eventsKeys, eventsMatch;
			eventsKeys = _.keys( api.Events );
			eventsMatch = eventsKeys.length ? true : false;
			_.each( eventsKeys, function( key ) {
				if ( api.selectiveRefresh[ key ] !== api.Events[ key ] ) {
					eventsMatch = false;
				}
			});
			return eventsMatch;
		});
	});

	test( 'Mocking a partial and placements, and testing their methods' , function() {
		var partialId, selector, options, mockPartial, settingValue, relatedSetting,
			expectedPlacement, placementNoContainer, placementNoAddedContent,
			placementNoStringAddedContent, placementContext, placementContainer,
			placementWithContextAndContainer;

		partialId = '492';
		selector = '#fixture-mock-partial';
		jQuery( selector ).append( '<div>' ).data( 'customize-partial-id', partialId );
		options = {
			params : {
				containerInclusive: true,
				fallbackRefresh: false,
				selector: selector,
				settings: [ partialId ]
			}
		};
		mockPartial = new api.selectiveRefresh.Partial( partialId, options );
		settingValue = 'bar';
		relatedSetting = api.create(
			partialId,
			settingValue,
			{
				id: partialId
			}
		);
		expectedPlacement = mockPartial.placements()[ 0 ];
		mockPartial.preparePlacement( expectedPlacement );

		equal( mockPartial.id, partialId );
		equal( mockPartial.params.selector, selector );
		equal( mockPartial.params.containerInclusive, true );
		equal( mockPartial.params.fallbackRefresh, false );
		equal( expectedPlacement.partial.id, partialId );
		equal( expectedPlacement.partial.params.selector, selector );
		equal( mockPartial.settings(), partialId );
		ok( mockPartial.isRelatedSetting( relatedSetting.id ) );

		notOk( mockPartial.isRelatedSetting( 'fooBar' ) );
		ok( jQuery( expectedPlacement.container ).hasClass( 'customize-partial-refreshing' ) );

		placementNoContainer = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: '',
			context: ''
		});

		placementNoAddedContent = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: jQuery( selector ),
			context: '',
			addedContent: false
		});

		placementNoStringAddedContent = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			container: jQuery( selector ),
			context: '',
			addedContent: 124
		});

		notOk( mockPartial.renderContent( placementNoContainer ) );
		notOk( mockPartial.renderContent( placementNoAddedContent ) );
		notOk( mockPartial.renderContent( placementNoStringAddedContent ) );

		notOk( ( function() {
			var placementNoPartial;
			try {
				placementNoPartial = new api.selectiveRefresh.Placement( {
					partial: false,
					container: jQuery( selector ),
					context: ''
				});
				mockPartial.renderContent( placementNoPartial );
			} catch( error ) {
				return false;
			}
		})());

		placementContext = { 'partial-context' : 'location' };
		placementContainer = jQuery( selector );

		placementWithContextAndContainer = new api.selectiveRefresh.Placement( {
			partial: mockPartial,
			context: placementContext,
			container: placementContainer,
			addedContent: 'Additional content'
		});

		ok( _.isEqual( placementWithContextAndContainer.context, placementContext ) );
		ok( _.isEqual( placementWithContextAndContainer.container, placementContainer ) );

	});

});
