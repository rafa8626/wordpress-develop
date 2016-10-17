(function( wp, $ ) {

	if ( ! wp || ! wp.customize ) { return; }

	var api = wp.customize;

	api.PostCollection = api.PostCollection || {};

	api.DrawerModel = Backbone.Model.extend({
		defaults: {
			status: 'closed'
		},

		close: function() {
			this.set( 'status', 'closed' );
		},

		open: function() {
			this.set( 'status', 'open' );
		},

		toggle: function() {
			if ( 'open' === this.get( 'status' ) ) {
				this.close();
			} else {
				this.open();
			}
		}
	});

	api.DrawerManager = Backbone.Collection.extend({
		model: api.DrawerModel,

		initialize: function() {
			this.on( 'change:status', this.closeOtherDrawers );
		},

		closeOtherDrawers: function( model ) {
			if ( 'open' === model.get( 'status' ) ) {
				_.chain( this.models ).without( model ).invoke( 'close' );
			}
		}
	});

	api.DrawerView = wp.Backbone.View.extend({
		tagName: 'div',
		className: 'customize-drawer',

		initialize: function( options ) {
			this.controller = options.controller;
			this.listenTo( this.controller, 'change:status', this.updateStatusClass );
		},

		updateStatusClass: function() {
			if ( 'open' === this.controller.get( 'status' ) ) {
				this.$el.addClass( 'is-open' );
			} else {
				this.$el.removeClass( 'is-open' );
			}
		}
	});

	api.PostCollection.CustomizeSectionTitleView = wp.Backbone.View.extend({
		className: 'customize-section-title',
		template: wp.template( 'customize-section-title' ),

		events: {
			'click .customize-section-back': 'closeDrawer'
		},

		initialize: function( options ) {
			this.control = options.control;
		},

		render: function() {
			var data = {
					label: this.control.params.label,
					labels: this.control.params.labels
				};

			this.$el.html( this.template( data ) );

			return this;
		},

		closeDrawer: function( e ) {
			e.preventDefault();
			this.control.drawer.close();
		}
	});

	api.PostCollection.PostModel = Backbone.Model.extend({
		defaults: {
			title: '',
			order: 0
		}
	});

	api.PostCollection.PostsCollection = Backbone.Collection.extend({
		model: api.PostCollection.PostModel,

		comparator: function( post ) {
			return parseInt( post.get( 'order' ), 10 );
		}
	});

	api.PostCollection.ControlView = wp.Backbone.View.extend({
		initialize: function( options ) {
			this.control = options.control;
			this.setting = options.setting;

			this.listenTo( this.collection, 'add remove reset sort', this.updateSetting );
			this.listenTo( this.control.drawer, 'change:status', this.maybeTriggerSearch );
			this.listenTo( this.control.drawer, 'change:status', this.updateStatusClass );
		},

		render: function() {
			this.views.add([
				new api.PostCollection.ItemListView({
					collection: this.collection,
					control: this.control,
					parent: this
				}),
				new api.PostCollection.AddNewItemButtonView({
					control: this.control
				})
			]);

			return this;
		},

		maybeTriggerSearch: function() {
			if ( 'open' === this.control.drawer.get( 'status' ) && this.control.results.length < 1 ) {
				this.control.search();
			}
		},

		updateSetting: function() {
			var postIds = this.collection.sort({ silent: true }).pluck( 'id' ).join( ',' );
			this.setting.set( postIds );
		},

		updateStatusClass: function() {
			if ( 'open' === this.control.drawer.get( 'status' ) ) {
				this.$el.addClass( 'is-drawer-open' );
			} else {
				this.$el.removeClass( 'is-drawer-open' );
			}
		}
	});

	api.PostCollection.AddNewItemButtonView = wp.Backbone.View.extend({
		className: 'add-new-item button button-secondary alignright',
		tagName: 'button',

		events: {
			click: 'toggleDrawer'
		},

		initialize: function( options ) {
			this.control = options.control;
		},

		render: function() {
			this.$el.text( this.control.params.labels.addPosts );
			return this;
		},

		toggleDrawer: function( e ) {
			e.preventDefault();
			this.control.drawer.toggle();
		}
	});

	api.PostCollection.ItemListView = wp.Backbone.View.extend({
		className: 'wp-items-list',
		tagName: 'ol',

		initialize: function( options ) {
			var view = this;

			this.control = options.control;

			this.listenTo( this.collection, 'add', this.addItem );
			this.listenTo( this.collection, 'add remove', this.updateOrder );
			this.listenTo( this.collection, 'reset', this.render );
		},

		render: function() {
			this.$el.empty();
			this.collection.each( this.addItem, this );
			this.initializeSortable();
			return this;
		},

		initializeSortable: function() {
			this.$el.sortable({
				axis: 'y',
				delay: 150,
				forceHelperSize: true,
				forcePlaceholderSize: true,
				opacity: 0.6,
				start: function( e, ui ) {
					ui.placeholder.css( 'visibility', 'visible' );
				},
				update: _.bind(function() {
					this.updateOrder();
				}, this )
			});
		},

		addItem: function( item ) {
			var itemView = new api.PostCollection.ItemView({
				control: this.control,
				model: item,
				parent: this
			});

			this.$el.append( itemView.render().el );
		},

		moveDown: function( model ) {
			var index = this.collection.indexOf( model ),
				$items = this.$el.children();

			if ( index < this.collection.length - 1 ) {
				$items.eq( index ).insertAfter( $items.eq( index + 1 ) );
				this.updateOrder();
				wp.a11y.speak( this.control.params.labels.movedDown );
			}
		},

		moveUp: function( model ) {
			var index = this.collection.indexOf( model ),
				$items = this.$el.children();

			if ( index > 0 ) {
				$items.eq( index ).insertBefore( $items.eq( index - 1 ) );
				this.updateOrder();
				wp.a11y.speak( this.control.params.labels.movedUp );
			}
		},

		updateOrder: function() {
			_.each( this.$el.find( 'li' ), function( item, i ) {
				var id = $( item ).data( 'post-id' );
				this.collection.get( id ).set( 'order', i );
			}, this );

			this.collection.sort();
		}
	});

	api.PostCollection.ItemView = wp.Backbone.View.extend({
		tagName: 'li',
		className: 'wp-item',
		template: wp.template( 'wp-item' ),

		events: {
			'click .js-remove': 'destroy',
			'click .move-item-up': 'moveUp',
			'click .move-item-down': 'moveDown'
		},

		initialize: function( options ) {
			this.control = options.control;
			this.parent = options.parent;
			this.listenTo( this.model, 'destroy', this.remove );
		},

		render: function() {
			var isFrontPage = this.model.get( 'id' ) == api( 'page_on_front' )(),
				canDelete = ! this.control.params.includeFrontPage || ! isFrontPage,
				data = _.extend( this.model.toJSON(), {
					labels: this.control.params.labels,
					includeFrontPage: this.control.params.includeFrontPage,
					showDeleteButton: canDelete
				});

			this.$el.html( this.template( data ) );
			this.$el.data( 'post-id', this.model.get( 'id' ) );

			if ( ! canDelete ) {
				this.$el.addClass( 'hide-delete' );
			}

			return this;
		},

		moveDown: function( e ) {
			e.preventDefault();
			this.parent.moveDown( this.model );
		},

		moveUp: function( e ) {
			e.preventDefault();
			this.parent.moveUp( this.model );
		},

		/**
		 * Destroy the view's model.
		 *
		 * Avoid syncing to the server by triggering an event instead of
		 * calling destroy() directly on the model.
		 */
		destroy: function() {
			this.model.trigger( 'destroy', this.model );
		},

		remove: function() {
			this.$el.remove();
		}
	});

	api.PostCollection.DrawerNoticeView = wp.Backbone.View.extend({
		tagName: 'div',
		className: 'customize-drawer-notice',

		initialize: function( options ) {
			this.control = options.control;
			this.listenTo( this.control.state, 'change:notice', this.render );
		},

		render: function() {
			var notice = this.control.state.get( 'notice' );
			this.$el.toggle( !! notice.length ).text( notice );
			return this;
		}
	});

	api.PostCollection.SearchGroupView = wp.Backbone.View.extend({
		tagName: 'div',
		className: 'search-group',
		template: wp.template( 'search-group' ),

		events: {
			'click .clear-results' : 'clearResults',
			'input input': 'search'
		},

		initialize: function( options ) {
			this.control = options.control;
			this.listenTo( this.collection, 'add remove reset', this.updateClearResultsVisibility );
		},

		render: function() {
			this.$el.html( this.template({ labels: this.control.params.labels }) );
			this.$clearResults = this.$( '.clear-results' );
			this.$field = this.$( '.search-group-field' );
			this.$spinner = this.$el.append( '<span class="search-group-spinner spinner" />' ).find( '.spinner' );
			this.updateClearResultsVisibility();
			return this;
		},

		clearResults: function() {
			this.collection.reset();
			this.$field.val( '' ).trigger( 'input' ).focus();
		},

		search: function() {
			var view = this;

			this.$el.addClass( 'is-searching' );
			this.$spinner.addClass( 'is-active' );

			clearTimeout( this.timeout );
			this.timeout = setTimeout(function() {
				view.control.search( view.$field.val() )
					.always(function() {
						view.$el.removeClass( 'is-searching' );
						view.$spinner.removeClass( 'is-active' );
					});
			}, 300 );
		},

		updateClearResultsVisibility: function() {
			this.$clearResults.toggleClass( 'is-visible', !! this.collection.length && '' !== this.$field.val() );
		}
	});

	api.PostCollection.SearchResultsView = wp.Backbone.View.extend({
		tagName: 'div',
		className: 'search-results',

		initialize: function( options ) {
			this.control = options.control;
			this.listenTo( this.collection, 'reset', this.render );
		},

		render: function() {
			this.$list = this.$el.html( '<ul />' ).find( 'ul' );
			this.$el.toggleClass( 'hide-type-label', 1 === this.control.params.postTypes.length );

			if ( this.collection.length ) {
				this.collection.each( this.addItem, this );
			} else {
				this.$el.empty();
			}

			return this;
		},

		addItem: function( model ) {
			this.views.add( 'ul', new api.PostCollection.SearchResultView({
				control: this.control,
				model: model
			}));
		}
	});

	api.PostCollection.SearchResultView = wp.Backbone.View.extend({
		tagName: 'li',
		className: 'search-results-item',
		template: wp.template( 'search-result' ),

		events: {
			'click': 'addItem'
		},

		initialize: function( options ) {
			this.control = options.control;
			this.listenTo( this.control.posts, 'add remove reset', this.updateSelectedClass );
		},

		render: function() {
			var data = _.extend( this.model.toJSON(), {
				labels: this.control.params.labels
			});

			this.$el.html( this.template( data ) );
			this.updateSelectedClass();

			return this;
		},

		addItem: function() {
			this.control.posts.add( this.model );
		},

		updateSelectedClass: function() {
			this.$el.toggleClass( 'is-selected', !! this.control.posts.findWhere({ id: this.model.get( 'id' ) }) );
		}
	});

	api.PostCollection.PostCollectionControl = api.Control.extend({
		ready: function() {
			var controlView, drawerView,
				control = this,
				section = api.section( this.section() );

			this.drawer = new api.DrawerModel();
			api.drawerManager.add( this.drawer );

			this.posts = new api.PostCollection.PostsCollection( this.params.posts );
			this.results = new api.PostCollection.PostsCollection();
			delete this.params.posts;

			this.state = new Backbone.Model({
				notice: ''
			});

			if ( this.params.includeFrontPage ) {
				// Add the front page when it changes.
				api( 'page_on_front', function( setting ) {
					setting.bind( _.bind( control.onPageOnFrontChange, control ) );
				});
			}

			controlView = new api.PostCollection.ControlView({
				el: this.container,
				collection: this.posts,
				control: this,
				data: this.params,
				setting: this.setting
			});

			controlView.render();

			drawerView = new api.DrawerView({
				controller: this.drawer
			});

			drawerView.views.set([
				new api.PostCollection.CustomizeSectionTitleView({
					control: this
				}),
				new api.PostCollection.SearchGroupView({
					collection: this.results,
					control: this
				}),
				new api.PostCollection.DrawerNoticeView({
					control: this
				}),
				new api.PostCollection.SearchResultsView({
					collection: this.results,
					control: this
				})
			]);

			$( '.wp-full-overlay' ).append( drawerView.render().$el );

			section.expanded.bind(function( isOpen ) {
				if ( ! isOpen ) {
					control.drawer.close();
				}
			});
		},

		onPageOnFrontChange: function( value ) {
			var id = parseInt( value, 10 ),
				posts = this.posts.toJSON(),
				pageOnFrontControl = api.control( 'page_on_front' );

			if ( id > 1 && ! this.posts.findWhere({ id: id }) ) {
				posts.unshift({
					id: id,
					title: pageOnFrontControl.container.find( 'option:selected' ).text()
				});
			}

			if ( 2 === posts.length ) {
				posts = posts.shift();
			}

			// Reset the collection to re-render the view.
			this.posts.reset( posts );
		},

		search: function( query ) {
			var control = this;

			return wp.ajax.post( 'find_posts', {
				ps: query,
				post_types: this.params.postTypes,
				post_status: 'publish',
				format: 'json',
				_ajax_nonce: this.params.searchNonce
			}).done(function( response ) {
				control.results.reset( response );
				control.state.set( 'notice', '' );
			}).fail(function( response ) {
				control.results.reset();
				control.state.set( 'notice', response );
			});
		}
	});

	/**
	 * Toggle the front page sections control based on front page settings.
	 */
	function toggleFrontPageSectionsControl() {
		var controlId = 'front_page_sections',
			showOnFront = api( 'show_on_front' )(),
			pageOnFront = api( 'page_on_front' )(),
			isVisible = 'page' === showOnFront && parseInt( pageOnFront ) > 0;

		if ( api.control.has( controlId ) ) {
			api.control( controlId ).container.toggle( isVisible );
		}
	}

	/**
	 * Extends wp.customize.controlConstructor with control constructor for
	 * post_collection.
	 */
	$.extend( api.controlConstructor, {
		post_collection: api.PostCollection.PostCollectionControl
	});

	/**
	 * Create a global drawer manager.
	 */
	api.drawerManager = new api.DrawerManager();

	/**
	 * Toggle an HTML class on the body when drawers are opened or closed.
	 */
	$( document ).ready(function() {
		var $body = $( document.body );

		api.drawerManager.on( 'change:status', function() {
			if ( api.drawerManager.findWhere({ status: 'open' }) ) {
				$body.addClass( 'drawer-is-open' );
			} else {
				$body.removeClass( 'drawer-is-open' );
			}
		});
	});

	/**
	 * Bind events to toggle visibilty of the front page sections control.
	 */
	api.bind( 'ready', function() {
		api( 'show_on_front' ).bind( toggleFrontPageSectionsControl );
		api( 'page_on_front' ).bind( toggleFrontPageSectionsControl );
		api.section( 'static_front_page' ).expanded.bind( toggleFrontPageSectionsControl );
	});

})( window.wp, jQuery );
