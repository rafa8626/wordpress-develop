/* global _wpmejsSettings */
(function( window, $ ) {

	window.wp = window.wp || {};

	// Add missing global variables for backward compatibility
	if (mejs.MediaFeatures === undefined) {
		mejs.MediaFeatures = mejs.Features;
		console.log(mejs.MediaFeatures)
	}

	if (mejs.Utility === undefined) {
		mejs.Utility = mejs.Utils;
	}

	var init = MediaElementPlayer.prototype.init;
	MediaElementPlayer.prototype.init = function() {
		this.$media = this.$node = $(this.node);
		this.options.classPrefix = 'mejs-';
		init.call(this);
	};

	// Add jQuery to arguments in custom features for backward compatibility
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
						player = $(player);
						controls = $(controls);
						layers = $(layers);
						media = $(media);
					}
					this['build' + feature](player, controls, layers, media);
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
