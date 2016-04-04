/* global jQuery, QUnit, wp */

var testCustomizePreview = function() {

	QUnit.module( 'Custom Logo' );

	QUnit.test( 'custom logo body class corresponds to setting value', function( assert ) {
		assert.ok( jQuery( document.body ).hasClass( 'wp-custom-logo' ) );
		wp.customize( 'custom_logo' ).set( '' );
		assert.notOk( jQuery( document.body ).hasClass( 'wp-custom-logo' ) );
		wp.customize( 'custom_logo' ).set( 123 );
		assert.ok( jQuery( document.body ).hasClass( 'wp-custom-logo' ) );
	} );

	// The 'Selective Refresh' unit test triggers this event.
	// So unbind it, to avoid running this test many times.
	wp.customize.unbind( 'preview-ready', testCustomizePreview );

};

wp.customize.bind( 'preview-ready', testCustomizePreview );
