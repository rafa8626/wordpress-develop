/* global equal, module, ok, test */

jQuery( function( $ ) {
	var api, setupAndTearDown, FooSuperClass, BarSubClass, foo, bar, ConstructorTestClass, newConstructor, constructorTest,
		$mockElement, mockString, firstInitialValue, firstValueInstance, wasCallbackFired, mockValueCallback;

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

	module( 'Customize Base: Class', setupAndTearDown );

	setupAndTearDown.beforeEach();
	FooSuperClass = api.Class.extend(
		{
			initialize: function ( instanceProps ) {
				$.extend( this, instanceProps || {} );
			},
			protoProp: 'protoPropValue'
		},
		{
			staticProp: 'staticPropValue'
		}
	);
	test( 'FooSuperClass is a function ', function () {
		equal( typeof FooSuperClass, 'function' );
	});
	test( 'FooSuperClass prototype has protoProp', function () {
		equal( FooSuperClass.prototype.protoProp, 'protoPropValue' );
	});
	test( 'FooSuperClass does not have protoProp', function () {
		equal( typeof FooSuperClass.protoProp, 'undefined' );
	});
	test( 'FooSuperClass has staticProp', function () {
		equal( FooSuperClass.staticProp, 'staticPropValue' );
	});
	test( 'FooSuperClass prototype does not have staticProp', function () {
		equal( typeof FooSuperClass.prototype.staticProp, 'undefined' );
	});

	foo = new FooSuperClass( { instanceProp: 'instancePropValue' } );
	test( 'FooSuperClass instance foo extended Class', function () {
		equal( foo.extended( api.Class ), true );
	});
	test( 'foo instance has protoProp', function () {
		equal( foo.protoProp, 'protoPropValue' );
	});
	test( 'foo instance does not have staticProp', function () {
		equal( typeof foo.staticProp, 'undefined' );
	});
	test( 'FooSuperClass instance foo ran initialize() and has supplied instanceProp', function () {
		equal( foo.instanceProp, 'instancePropValue' );
	});

	// @todo Test Class.applicator?
	// @todo do we test object.instance?

	module( 'Customize Base: Subclass', setupAndTearDown );

	BarSubClass = FooSuperClass.extend(
		{
			initialize: function ( instanceProps ) {
				FooSuperClass.prototype.initialize.call( this, instanceProps );
				this.subInstanceProp = 'subInstancePropValue';
			},
			subProtoProp: 'subProtoPropValue'
		},
		{
			subStaticProp: 'subStaticPropValue'
		}
	);
	test( 'BarSubClass prototype has subProtoProp', function () {
		equal( BarSubClass.prototype.subProtoProp, 'subProtoPropValue' );
	});
	test( 'BarSubClass prototype has parent FooSuperClass protoProp', function () {
		equal( BarSubClass.prototype.protoProp, 'protoPropValue' );
	});

	bar = new BarSubClass( { instanceProp: 'instancePropValue' } );
	test( 'BarSubClass instance bar its initialize() and parent initialize() run', function () {
		equal( bar.instanceProp, 'instancePropValue' );
		equal( bar.subInstanceProp, 'subInstancePropValue' );
	});

	test( 'BarSubClass instance bar extended FooSuperClass', function () {
		equal( bar.extended( FooSuperClass ), true );
	});


	// Implements todo : Test Class.constructor() manipulation
	module( 'Customize Base: Constructor Manipulation', setupAndTearDown );

	newConstructor = function ( instanceProps ) {
			$.extend( this , instanceProps || {} );
	};

	ConstructorTestClass = api.Class.extend(
		{
			constructor : newConstructor,
			protoProp: 'protoPropValue'
		},
		{
			staticProp: 'staticPropValue'
		}
	);

	test( 'New constructor added to class' , function () {
		equal( ConstructorTestClass.prototype.constructor , newConstructor );
	});
	test( 'Class with new constructor has protoPropValue' , function () {
		equal( ConstructorTestClass.prototype.protoProp , 'protoPropValue' );
	});

	constructorTest = new ConstructorTestClass( { instanceProp: 'instancePropValue' } );
	test( 'ConstructorTestClass instance constructorTest has the new constructor', function () {
		equal( constructorTest.constructor, newConstructor );
	});

	test( 'ConstructorTestClass instance constructorTest extended Class', function () {
		equal( constructorTest.extended( api.Class ), true );
	});

	test( 'ConstructorTestClass instance constructorTest has the added instance property', function () {
		equal( constructorTest.instanceProp , 'instancePropValue' );
	});


	module( 'Customize Base: wp.customize.ensure', setupAndTearDown );

	$mockElement = $( '<div id="mockElement"></div>' );

	test( 'Handles jQuery argument' , function() {
		equal( api.ensure( $mockElement ) , $mockElement );
	});

	mockString = '<div class="mockString"></div>';

	test( 'Handles string argument' , function() {
		ok( api.ensure( mockString ) instanceof jQuery );
	});


	module( 'Customize Base: Value Class', setupAndTearDown );

	firstInitialValue = true;
	firstValueInstance = new api.Value( firstInitialValue );

	test( 'Initialized with the right value' , function() {
		equal( firstValueInstance.get() , firstInitialValue );
	});

	test( '.set() works' , function() {
		firstValueInstance.set( false );
		equal( firstValueInstance.get() , false );
	});

	test( '.bind() adds new callback that fires on set()' , function() {
		wasCallbackFired = false;
		mockValueCallback = function() {
			wasCallbackFired = true;
		};
		firstValueInstance.bind( mockValueCallback );
		firstValueInstance.set( 'newValue' );
		ok( wasCallbackFired );
	});
});
