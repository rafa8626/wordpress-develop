/* global QUnit */
jQuery( function() {

	var api = wp.customize;

    QUnit.module( 'Customize Selective Refresh' );

	//test( 'Models loassert.ok as expected', function() {
	//	assert.ok( wp.customize.selectiveRefresh.Partial.extended( wp.customize.Class ) );
	//} );

	QUnit.test( 'Initially, there are no partials', function( assert ) {
		assert.ok( _.isEmpty( api.selectiveRefresh.data.partials ) );
	});

	QUnit.test( 'selectiveRefresh object has events from api.Events', function( assert ) {
		assert.ok( function() {
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

	QUnit.test( 'Mocking a partial and placements, and testing their methods' , function( assert ) {
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

		assert.equal( mockPartial.id, partialId );
		assert.equal( mockPartial.params.selector, selector );
		assert.equal( mockPartial.params.containerInclusive, true );
		assert.equal( mockPartial.params.fallbackRefresh, false );
		assert.equal( expectedPlacement.partial.id, partialId );
		assert.equal( expectedPlacement.partial.params.selector, selector );
		assert.equal( mockPartial.settings(), partialId );
		assert.ok( mockPartial.isRelatedSetting( relatedSetting.id ) );

		assert.notOk( mockPartial.isRelatedSetting( 'fooBar' ) );
		assert.ok( jQuery( expectedPlacement.container ).hasClass( 'customize-partial-refreshing' ) );

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

		assert.notOk( mockPartial.renderContent( placementNoContainer ) );
		assert.notOk( mockPartial.renderContent( placementNoAddedContent ) );
		assert.notOk( mockPartial.renderContent( placementNoStringAddedContent ) );

		assert.notOk( ( function() {
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

		assert.ok( _.isEqual( placementWithContextAndContainer.context, placementContext ) );
		assert.ok( _.isEqual( placementWithContextAndContainer.container, placementContainer ) );

	});

});
