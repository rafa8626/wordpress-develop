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
				api( 'nav_menus_created_posts' ).set( component.autoDrafts );
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
			// Show users links to edit newly-published posts.
			api.control.each( function( control ) {
				var id, url, message;
				if ( 'nav_menu_item' === control.params.type ) {
					if ( -1 !== component.autoDrafts.indexOf( control.setting().object_id ) ) {
						id = component.autoDrafts[component.autoDrafts.indexOf( control.setting().object_id )];
						url = api.Menus.data.editPostURL.replace( '%d', id );
						message = api.Menus.data.l10n.newPostPublished.replace( '%3$s', url );
						message = message.replace( '%1$s', control.params.item_type_label );
						message = message.replace( '%2$s', control.setting().original_title );
						control.setting.notifications.add( 'content_published', new wp.customize.Notification(
							'content_published',
							{
								type: 'info',
								message: message
							}
						));
					}
				}
			});

			// Reset auto-drafts.
			component.autoDrafts = []; // Reset the array the next time an item is created. Don't update the setting yet as that would trigger the customizer's dirty state.
		} );

	} );

})( wp.customize, jQuery );
