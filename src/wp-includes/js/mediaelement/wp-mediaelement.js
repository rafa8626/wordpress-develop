/* global _wpmejsSettings */
(function( window, $ ) {

	window.wp = window.wp || {};

	// Reintegrate `plugins` since they don't exist in MEJS anymore; it won't affect anything in the player
	if (mejs.plugins === undefined) {
		mejs.plugins = {};
		mejs.plugins.silverlight = [];
		mejs.plugins.silverlight.push({
			types: []
		});
	}


	// Add missing global variables for backward compatibility
	if (mejs.MediaFeatures === undefined) {
		mejs.MediaFeatures = mejs.Features;
	}
	if (mejs.Utility === undefined) {
		mejs.Utility = mejs.Utils;
	}

	/**
	 * Create missing variables and have default `classPrefix` overridden to avoid issues.
	 *
	 * `media` is now a fake wrapper needed to simplify manipulation of various media types,
	 * so in order to access the `video` or `audio` tag, use `media.originalNode` or `player.node`;
	 * `player.container` used to be jQuery but now is a HTML element, and many elements inside
	 * the player rely on it being a HTML now, so its conversion is difficult; however, a
	 * `player.$container` new variable has been added to be used as jQuery object
	 */
	var init = MediaElementPlayer.prototype.init;
	MediaElementPlayer.prototype.init = function() {
		this.$media = this.$node = $(this.node);
		this.options.classPrefix = 'mejs-';
		init.call(this);
	};

	// Add jQuery ONLY to most of custom features' arguments for backward compatibility; default features rely 100%
	// on the arguments being HTML elements to work properly
	MediaElementPlayer.prototype.buildfeatures = function (player, controls, layers, media) {
		var defaultFeatures = [
			'playpause',
			'current',
			'progress',
			'duration',
			'tracks',
			'volume',
			'fullscreen'
		];
		for (var i = 0, total = this.options.features.length; i < total; i++) {
			var feature = this.options.features[i];
			if (this['build' + feature]) {
				try {
					// Use jQuery for non-default features
					if (defaultFeatures.indexOf(feature) === -1) {
						this['build' + feature](player, $(controls), $(layers), media);
					} else {
						this['build' + feature](player, controls, layers, media);
					}

				} catch (e) {
					console.error('error building ' + feature, e);
				}
			}
		}
	};

	function wpMediaElement() {
		var settings = {};

		/**
		 * Initialize media elements.
		 *
		 * Ensures media elements that have already been initialized won't be
		 * processed again.
		 *
		 * @since 4.4.0
		 *
		 * @returns {void}
		 */
		function initialize() {
			if ( typeof _wpmejsSettings !== 'undefined' ) {
				settings = $.extend( true, {}, _wpmejsSettings );
			}
			settings.classPrefix = 'mejs-';
			settings.success = settings.success || function (mejs) {
				var autoplay, loop;

				if ( mejs.rendererName && -1 !== mejs.rendererName.indexOf( 'flash' ) ) {
					autoplay = mejs.attributes.autoplay && 'false' !== mejs.attributes.autoplay;
					loop = mejs.attributes.loop && 'false' !== mejs.attributes.loop;

					if ( autoplay ) {
						mejs.addEventListener( 'canplay', function() {
							mejs.play();
						}, false );
					}

					if ( loop ) {
						mejs.addEventListener( 'ended', function() {
							mejs.play();
						}, false );
					}
				}
			};

			// Only initialize new media elements.
			$( '.wp-audio-shortcode, .wp-video-shortcode' )
				.not( '.mejs-container' )
				.filter(function () {
					return ! $( this ).parent().hasClass( 'mejs-mediaelement' );
				})
				.mediaelementplayer( settings );
		}

		return {
			initialize: initialize
		};
	}

	window.wp.mediaelement = new wpMediaElement();

	$( window.wp.mediaelement.initialize );

})( window, jQuery );
