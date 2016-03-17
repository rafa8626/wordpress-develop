/* global jQuery, QUnit */

wp.customize.bind( 'preview-ready', function() {

	QUnit.module( 'Custom Logo' );

	QUnit.test( 'custom logo body class corresponds to setting value', function( assert ) {
		assert.ok( jQuery( document.body ).hasClass( 'wp-custom-logo' ) );
		wp.customize( 'custom_logo' ).set( '' );
		assert.notOk( jQuery( document.body ).hasClass( 'wp-custom-logo' ) );
		wp.customize( 'custom_logo' ).set( 123 );
		assert.ok( jQuery( document.body ).hasClass( 'wp-custom-logo' ) );
	} );

} );
