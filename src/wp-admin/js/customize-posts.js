/* global jQuery, wp, console */

(function( api, $ ) {
	'use strict';

	var component;

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	component = api.Posts;

	component.autoDrafts = [];

	/**
	 * Insert a new `auto-draft` post.
	 *
	 * @param {object} params - Parameters for the draft post to create.
	 * @param {string} params.post_type - Post type to add.
	 * @param {number} params.title - Post title to use.
	 * @return {jQuery.promise} Promise resolved with the added post.
	 */
	component.insertAutoDraftPost = function( params ) {
		var request, deferred = $.Deferred();

		request = wp.ajax.post( 'customize-posts-insert-auto-draft', {
			'customize-menus-nonce': api.settings.nonce['customize-menus'],
			'wp_customize': 'on',
			'params': params
		} );

		request.done( function( response ) {
			if ( response.postId ) {
				deferred.resolve( response );
				component.autoDrafts.push( response.postId );
				api( 'nav_menus_created_posts' ).set( _.clone( component.autoDrafts ) );
			}
		} );

		request.fail( function( response ) {
			var error = response || '';

			if ( 'undefined' !== typeof response.message ) {
				error = response.message;
			}

			console.error( error );
			deferred.rejectWith( error );
		} );

		return deferred.promise();
	};

	api.bind( 'ready', function() {

		api.bind( 'saved', function() {

			// Reset auto-drafts.
			component.autoDrafts = []; // Reset the array the next time an item is created. Don't update the setting yet as that would trigger the customizer's dirty state.
		} );

	} );

})( wp.customize, jQuery );
