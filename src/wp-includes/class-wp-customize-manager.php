<?php
/**
 * WordPress Customize Manager classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

/**
 * Customize Manager class.
 *
 * Bootstraps the Customize experience on the server-side.
 *
 * Sets up the theme-switching process if a theme other than the active one is
 * being previewed and customized.
 *
 * Serves as a factory for Customize Controls and Settings, and
 * instantiates default Customize Controls and Settings.
 *
 * @since 3.4.0
 */
final class WP_Customize_Manager {
	/**
	 * An instance of the theme being previewed.
	 *
	 * @since 3.4.0
	 * @access protected
	 * @var WP_Theme
	 */
	protected $theme;

	/**
	 * The directory name of the previously active theme (within the theme_root).
	 *
	 * @since 3.4.0
	 * @access protected
	 * @var string
	 */
	protected $original_stylesheet;

	/**
	 * Whether this is a Customizer pageload.
	 *
	 * @since 3.4.0
	 * @access protected
	 * @var bool
	 */
	protected $previewing = false;

	/**
	 * Methods and properties dealing with managing widgets in the Customizer.
	 *
	 * @since 3.9.0
	 * @access public
	 * @var WP_Customize_Widgets
	 */
	public $widgets;

	/**
	 * Methods and properties dealing with managing nav menus in the Customizer.
	 *
	 * @since 4.3.0
	 * @access public
	 * @var WP_Customize_Nav_Menus
	 */
	public $nav_menus;

	/**
	 * Methods and properties dealing with selective refresh in the Customizer preview.
	 *
	 * @since 4.5.0
	 * @access public
	 * @var WP_Customize_Selective_Refresh
	 */
	public $selective_refresh;

	/**
	 * Registered instances of WP_Customize_Setting.
	 *
	 * @since 3.4.0
	 * @access protected
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Sorted top-level instances of WP_Customize_Panel and WP_Customize_Section.
	 *
	 * @since 4.0.0
	 * @access protected
	 * @var array
	 */
	protected $containers = array();

	/**
	 * Registered instances of WP_Customize_Panel.
	 *
	 * @since 4.0.0
	 * @access protected
	 * @var array
	 */
	protected $panels = array();

	/**
	 * List of core components.
	 *
	 * @since 4.5.0
	 * @access protected
	 * @var array
	 */
	protected $components = array( 'widgets', 'nav_menus' );

	/**
	 * Registered instances of WP_Customize_Section.
	 *
	 * @since 3.4.0
	 * @access protected
	 * @var array
	 */
	protected $sections = array();

	/**
	 * Registered instances of WP_Customize_Control.
	 *
	 * @since 3.4.0
	 * @access protected
	 * @var array
	 */
	protected $controls = array();

	/**
	 * Panel types that may be rendered from JS templates.
	 *
	 * @since 4.3.0
	 * @access protected
	 * @var array
	 */
	protected $registered_panel_types = array();

	/**
	 * Section types that may be rendered from JS templates.
	 *
	 * @since 4.3.0
	 * @access protected
	 * @var array
	 */
	protected $registered_section_types = array();

	/**
	 * Control types that may be rendered from JS templates.
	 *
	 * @since 4.1.0
	 * @access protected
	 * @var array
	 */
	protected $registered_control_types = array();

	/**
	 * Initial URL being previewed.
	 *
	 * @since 4.4.0
	 * @access protected
	 * @var string
	 */
	protected $preview_url;

	/**
	 * URL to link the user to when closing the Customizer.
	 *
	 * @since 4.4.0
	 * @access protected
	 * @var string
	 */
	protected $return_url;

	/**
	 * Mapping of 'panel', 'section', 'control' to the ID which should be autofocused.
	 *
	 * @since 4.4.0
	 * @access protected
	 * @var array
	 */
	protected $autofocus = array();

	/**
	 * Changeset UUID.
	 *
	 * @since 4.7.0
	 * @access protected
	 * @var string
	 */
	protected $changeset_uuid;

	/**
	 * Messenger channel.
	 *
	 * @since 4.7.0
	 * @access protected
	 * @var string
	 */
	protected $messenger_channel;

	/**
	 * Unsanitized values for Customize Settings parsed from $_POST['customized'].
	 *
	 * @var array
	 */
	private $_post_values;

	/**
	 * Changeset post ID.
	 *
	 * @var int|false
	 */
	private $_changeset_post_id;

	/**
	 * Changeset data loaded from a customize_changeset post.
	 *
	 * @var array
	 */
	private $_changeset_data;

	/**
	 * Constructor.
	 *
	 * @since 3.4.0
	 * @since 4.7.0 Added $args param.
	 *
	 * @param array $args {
	 *     Args.
	 *
	 *     @type string $changeset_uuid    Changeset UUID, the post_name for the customize_changeset post containing the customized state. Defaults to new UUID.
	 *     @type string $theme             Theme to be previewed (for theme switch). Defaults to customize_theme or theme query params.
	 *     @type string $messenger_channel Messenger channel. Defaults to customize_messenger_channel query param.
	 * }
	 */
	public function __construct( $args = array() ) {

		$args = array_merge(
			array_fill_keys( array( 'changeset_uuid', 'theme', 'messenger_channel' ), null ),
			$args
		);

		if ( ! isset( $args['changeset_uuid'] ) ) {
			$args['changeset_uuid'] = $this->generate_uuid();
		}

		// The theme and messenger_channel should be supplied via $args, but they are also looked at in the $_REQUEST global here for back-compat.
		if ( ! isset( $args['theme'] ) ) {
			if ( isset( $_REQUEST['customize_theme'] ) ) {
				$args['theme'] = wp_unslash( $_REQUEST['customize_theme'] );
			} elseif ( isset( $_REQUEST['theme'] ) ) { // Deprecated.
				$args['theme'] = wp_unslash( $_REQUEST['theme'] );
			}
		}
		if ( ! isset( $args['messenger_channel'] ) && isset( $_REQUEST['customize_messenger_channel'] ) ) {
			$args['messenger_channel'] = sanitize_key( wp_unslash( $_REQUEST['customize_messenger_channel'] ) );
		}

		$this->original_stylesheet = get_stylesheet();
		$this->theme = wp_get_theme( $args['theme'] );
		foreach ( array( 'changeset_uuid', 'messenger_channel' ) as $key ) {
			$this->$key = $args[ $key ];
		}

		require_once( ABSPATH . WPINC . '/class-wp-customize-setting.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-panel.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-section.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-control.php' );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-color-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-media-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-upload-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-cropped-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-site-icon-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-theme-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-widget-area-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-widget-form-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-location-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-name-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-auto-add-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-control.php' );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menus-panel.php' );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-themes-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-sidebar-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-section.php' );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-filter-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-setting.php' );

		/**
		 * Filters the core Customizer components to load.
		 *
		 * This allows Core components to be excluded from being instantiated by
		 * filtering them out of the array. Note that this filter generally runs
		 * during the {@see 'plugins_loaded'} action, so it cannot be added
		 * in a theme.
		 *
		 * @since 4.4.0
		 *
		 * @see WP_Customize_Manager::__construct()
		 *
		 * @param array                $components List of core components to load.
		 * @param WP_Customize_Manager $this       WP_Customize_Manager instance.
		 */
		$components = apply_filters( 'customize_loaded_components', $this->components, $this );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-selective-refresh.php' );
		$this->selective_refresh = new WP_Customize_Selective_Refresh( $this );

		if ( in_array( 'widgets', $components, true ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-widgets.php' );
			$this->widgets = new WP_Customize_Widgets( $this );
		}

		if ( in_array( 'nav_menus', $components, true ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-nav-menus.php' );
			$this->nav_menus = new WP_Customize_Nav_Menus( $this );
		}

		add_action( 'setup_theme', array( $this, 'setup_theme' ) );
		add_action( 'wp_loaded',   array( $this, 'wp_loaded' ) );

		// Do not spawn cron (especially the alternate cron) while running the Customizer.
		remove_action( 'init', 'wp_cron' );

		// Do not run update checks when rendering the controls.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		add_action( 'wp_ajax_customize_save',           array( $this, 'save' ) );
		add_action( 'wp_ajax_customize_refresh_nonces', array( $this, 'refresh_nonces' ) );

		add_action( 'customize_register',                 array( $this, 'register_controls' ) );
		add_action( 'customize_register',                 array( $this, 'register_dynamic_settings' ), 11 ); // allow code to create settings first
		add_action( 'customize_controls_init',            array( $this, 'prepare_controls' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_control_scripts' ) );

		// Render Panel, Section, and Control templates.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_panel_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_section_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_control_templates' ), 1 );

		// Export the settings to JS via the _wpCustomizeSettings variable.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customize_pane_settings' ), 1000 );
	}

	/**
	 * Generate a UUID for a changeset.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return string UUID.
	 */
	public function generate_uuid() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	/**
	 * Return true if it's an Ajax request.
	 *
	 * @since 3.4.0
	 * @since 4.2.0 Added `$action` param.
	 * @access public
	 *
	 * @param string|null $action Whether the supplied Ajax action is being run.
	 * @return bool True if it's an Ajax request, false otherwise.
	 */
	public function doing_ajax( $action = null ) {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! $action ) {
			return true;
		} else {
			/*
			 * Note: we can't just use doing_action( "wp_ajax_{$action}" ) because we need
			 * to check before admin-ajax.php gets to that point.
			 */
			return isset( $_REQUEST['action'] ) && wp_unslash( $_REQUEST['action'] ) === $action;
		}
	}

	/**
	 * Custom wp_die wrapper. Returns either the standard message for UI
	 * or the Ajax message.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $ajax_message Ajax return
	 * @param mixed $message UI message
	 */
	protected function wp_die( $ajax_message, $message = null ) {
		if ( $this->doing_ajax() ) {
			wp_die( $ajax_message );
		}

		if ( ! $message ) {
			$message = __( 'Cheatin&#8217; uh?' );
		}

		if ( $this->messenger_channel ) {
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			echo '<!DOCTYPE html><html>';
			wp_print_scripts( array( 'customize-base' ) );

			$settings = array(
				'messengerArgs' => array(
					'channel' => $this->messenger_channel,
					'url' => wp_customize_url(),
				),
				'error' => $ajax_message,
			);
			?>
			<script>
			( function( api, settings ) {
				var preview = new api.Messenger( settings.messengerArgs );
				preview.send( 'iframe-loading-error', settings.error );
			} )( wp.customize, <?php echo wp_json_encode( $settings ) ?> );
			</script>
			<?php
			echo "<p>$message</p>";
			echo '</html>';
			die(); // @todo Not testable.
		}

		wp_die( $message );
	}

	/**
	 * Return the Ajax wp_die() handler if it's a customized request.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @return callable Die handler.
	 */
	public function wp_die_handler() {
		_deprecated_function( __METHOD__, '4.7.0' );

		if ( $this->doing_ajax() || isset( $_POST['customized'] ) ) {
			return '_ajax_wp_die_handler';
		}

		return '_default_wp_die_handler';
	}

	/**
	 * Start preview and customize theme.
	 *
	 * Check if customize query variable exist. Init filters to filter the current theme.
	 *
	 * @since 3.4.0
	 */
	public function setup_theme() {
		global $pagenow;

		// Check permissions for customize.php access since this method is called before customize.php can run any code,
		if ( 'customize.php' === $pagenow && ! current_user_can( 'customize' ) ) {
			if ( ! is_user_logged_in() ) {
				auth_redirect();
			} else {
				wp_die(
					'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
					'<p>' . __( 'Sorry, you are not allowed to customize this site.' ) . '</p>',
					403
				);
			}
			return;
		}

		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $this->changeset_uuid ) ) {
			$this->wp_die( -1, __( 'Invalid changeset UUID' ) );
		}

		// If unauthenticated then require a valid changeset UUID to load the preview. In this way, the UUID serves as a secret key.
		if ( ! current_user_can( 'customize' ) && ! $this->changeset_post_id() ) {
			$this->wp_die( -1, __( 'Non-existent changeset UUID.' ) );
		}

		send_origin_headers();

		// Hide the admin bar if we're embedded in the customizer iframe.
		if ( $this->messenger_channel ) {
			show_admin_bar( false );
		}

		if ( $this->is_theme_active() ) {
			// Once the theme is loaded, we'll validate it.
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		} else {
			// If the requested theme is not the active theme and the user doesn't have the
			// switch_themes cap, bail.
			if ( ! current_user_can( 'switch_themes' ) ) {
				$this->wp_die( -1, __( 'Sorry, you are not allowed to edit theme options on this site.' ) );
			}

			// If the theme has errors while loading, bail.
			if ( $this->theme()->errors() ) {
				$this->wp_die( -1, $this->theme()->errors()->get_error_message() );
			}

			// If the theme isn't allowed per multisite settings, bail.
			if ( ! $this->theme()->is_allowed() ) {
				$this->wp_die( -1, __( 'The requested theme does not exist.' ) );
			}
		}

		$this->start_previewing_theme();
	}

	/**
	 * Callback to validate a theme once it is loaded
	 *
	 * @since 3.4.0
	 */
	public function after_setup_theme() {
		$doing_ajax_or_is_customized = ( $this->doing_ajax() || isset( $_POST['customized'] ) );
		if ( ! $doing_ajax_or_is_customized && ! validate_current_theme() ) {
			wp_redirect( 'themes.php?broken=true' );
			exit;
		}
	}

	/**
	 * If the theme to be previewed isn't the active theme, add filter callbacks
	 * to swap it out at runtime.
	 *
	 * @since 3.4.0
	 */
	public function start_previewing_theme() {
		// Bail if we're already previewing.
		if ( $this->is_preview() ) {
			return;
		}

		$this->previewing = true;

		if ( ! $this->is_theme_active() ) {
			add_filter( 'template', array( $this, 'get_template' ) );
			add_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			// @link: https://core.trac.wordpress.org/ticket/20027
			add_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_template', array( $this, 'get_template' ) );

			// Handle custom theme roots.
			add_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			add_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		/**
		 * Fires once the Customizer theme preview has started.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'start_previewing_theme', $this );
	}

	/**
	 * Stop previewing the selected theme.
	 *
	 * Removes filters to change the current theme.
	 *
	 * @since 3.4.0
	 */
	public function stop_previewing_theme() {
		if ( ! $this->is_preview() ) {
			return;
		}

		$this->previewing = false;

		if ( ! $this->is_theme_active() ) {
			remove_filter( 'template', array( $this, 'get_template' ) );
			remove_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			// @link: https://core.trac.wordpress.org/ticket/20027
			remove_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_template', array( $this, 'get_template' ) );

			// Handle custom theme roots.
			remove_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			remove_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		/**
		 * Fires once the Customizer theme preview has stopped.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'stop_previewing_theme', $this );
	}

	/**
	 * Get the changeset UUID.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return string UUID.
	 */
	public function changeset_uuid() {
		return $this->changeset_uuid;
	}

	/**
	 * Get the theme being customized.
	 *
	 * @since 3.4.0
	 *
	 * @return WP_Theme
	 */
	public function theme() {
		if ( ! $this->theme ) {
			$this->theme = wp_get_theme();
		}
		return $this->theme;
	}

	/**
	 * Get the registered settings.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get the registered controls.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function controls() {
		return $this->controls;
	}

	/**
	 * Get the registered containers.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function containers() {
		return $this->containers;
	}

	/**
	 * Get the registered sections.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function sections() {
		return $this->sections;
	}

	/**
	 * Get the registered panels.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @return array Panels.
	 */
	public function panels() {
		return $this->panels;
	}

	/**
	 * Checks if the current theme is active.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function is_theme_active() {
		return $this->get_stylesheet() == $this->original_stylesheet;
	}

	/**
	 * Register styles/scripts and initialize the preview of each setting
	 *
	 * @since 3.4.0
	 */
	public function wp_loaded() {

		/**
		 * Fires once WordPress has loaded, allowing scripts and styles to be initialized.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'customize_register', $this );

		/*
		 * Note that settings must be previewed here even outside the customizer preview
		 * and also in the customizer pane itself. This is to enable loading an existing
		 * changeset into the customizer. Previewing the settings only has to be prevented
		 * in the case of a customize_save action because then update_option()
		 * may short-circuit because it will detect that there are no changes to
		 * make.
		 */
		if ( ! $this->doing_ajax( 'customize_save' ) ) {
			foreach ( $this->settings as $setting ) {
				$setting->preview();
			}
		}

		if ( $this->is_preview() && ! is_admin() ) {
			$this->customize_preview_init();
		}
	}

	/**
	 * Prevents Ajax requests from following redirects when previewing a theme
	 * by issuing a 200 response instead of a 30x.
	 *
	 * Instead, the JS will sniff out the location header.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @param int $status Status.
	 * @return int
	 */
	public function wp_redirect_status( $status ) {
		_deprecated_function( __FUNCTION__, '4.7.0' );

		if ( $this->is_preview() && ! is_admin() ) {
			return 200;
		}

		return $status;
	}

	/**
	 * Get the changeset post.
	 *
	 * @todo Add persistent object caching for query.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return int|false
	 */
	public function changeset_post_id() {
		if ( isset( $this->_changeset_post_id ) ) {
			return $this->_changeset_post_id;
		}
		$changeset_post_query = new WP_Query( array(
			'post_type' => 'customize_changeset',
			'post_status' => get_post_stati(),
			'name' => $this->changeset_uuid,
			'number' => 1,
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_term_meta_cache' => false,
		) );
		if ( ! empty( $changeset_post_query->posts ) ) {
			$this->_changeset_post_id = $changeset_post_query->posts[0]->ID;
		} else {
			$this->_changeset_post_id = false;
		}
		return $this->_changeset_post_id;
	}

	/**
	 * Get changeset data.
	 *
	 * @since 4.7.0
	 *
	 * @return array Changeset data.
	 */
	public function changeset_data() {
		if ( isset( $this->_changeset_data ) ) {
			return $this->_changeset_data;
		}
		$changeset_post_id = $this->changeset_post_id();
		if ( ! $changeset_post_id ) {
			$this->_changeset_data = array();
		} else {
			$changeset_post = get_post( $changeset_post_id );
			if ( $changeset_post ) {
				$changeset = json_decode( $changeset_post->post_content, true );
				if ( is_array( $changeset ) ) {
					$this->_changeset_data = $changeset;
				} else {
					$this->_changeset_data = array();
				}
			}
		}
		return $this->_changeset_data;
	}

	/**
	 * Parse the incoming $_POST['customized'] JSON data and store the unsanitized
	 * settings for subsequent post_value() lookups.
	 *
	 * @since 4.1.1
	 * @since 4.7.0 Added $args param.
	 *
	 * @param array $args {
	 *     Args.
	 *
	 *     @type bool $exclude_changeset Whether or not the changeset values should also be included. This is excluded obtaining values for updating a snapshot.
	 *     @type bool $exclude_post_data Whether or not the post input values should also be included. When lacking a valid nonce, this should be excluded.
	 * }
	 * @return array
	 */
	public function unsanitized_post_values( $args = array() ) {
		$args = array_merge(
			array(
				'exclude_changeset' => false,
				'exclude_post_data' => ! current_user_can( 'customize' ),
			),
			$args
		);

		$values = array();

		if ( ! $args['exclude_changeset'] ) {
			$values = array_merge( $values, wp_list_pluck( $this->changeset_data(), 'value' ) );
		}

		if ( ! $args['exclude_post_data'] ) {
			if ( ! isset( $this->_post_values ) ) {
				if ( isset( $_POST['customized'] ) ) {
					$post_values = json_decode( wp_unslash( $_POST['customized'] ), true );
				} else {
					$post_values = array();
				}
				if ( is_array( $post_values ) ) {
					$this->_post_values = $post_values;
				} else {
					$this->_post_values = array();
				}
			}
			$values = array_merge( $values, $this->_post_values );
		}
		return $values;
	}

	/**
	 * Returns the sanitized value for a given setting from the request's POST data.
	 *
	 * @since 3.4.0
	 * @since 4.1.1 Introduced the `$default` parameter.
	 * @since 4.6.0 `$default` is now returned early when the setting post value is invalid.
	 * @access public
	 *
	 * @see WP_REST_Server::dispatch()
	 * @see WP_Rest_Request::sanitize_params()
	 * @see WP_Rest_Request::has_valid_params()
	 *
	 * @param WP_Customize_Setting $setting A WP_Customize_Setting derived object.
	 * @param mixed                $default Value returned $setting has no post value (added in 4.2.0)
	 *                                      or the post value is invalid (added in 4.6.0).
	 * @return string|mixed $post_value Sanitized value or the $default provided.
	 */
	public function post_value( $setting, $default = null ) {
		$post_values = $this->unsanitized_post_values();
		if ( ! array_key_exists( $setting->id, $post_values ) ) {
			return $default;
		}
		$value = $post_values[ $setting->id ];
		$valid = $setting->validate( $value );
		if ( is_wp_error( $valid ) ) {
			return $default;
		}
		$value = $setting->sanitize( $value );
		if ( is_null( $value ) || is_wp_error( $value ) ) {
			return $default;
		}
		return $value;
	}

	/**
	 * Override a setting's (unsanitized) value as found in any incoming $_POST['customized'].
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $setting_id ID for the WP_Customize_Setting instance.
	 * @param mixed  $value      Post value.
	 */
	public function set_post_value( $setting_id, $value ) {
		$this->unsanitized_post_values();
		$this->_post_values[ $setting_id ] = $value;

		/**
		 * Announce when a specific setting's unsanitized post value has been set.
		 *
		 * Fires when the WP_Customize_Manager::set_post_value() method is called.
		 *
		 * The dynamic portion of the hook name, `$setting_id`, refers to the setting ID.
		 *
		 * @since 4.4.0
		 *
		 * @param mixed                $value Unsanitized setting post value.
		 * @param WP_Customize_Manager $this  WP_Customize_Manager instance.
		 */
		do_action( "customize_post_value_set_{$setting_id}", $value, $this );

		/**
		 * Announce when any setting's unsanitized post value has been set.
		 *
		 * Fires when the WP_Customize_Manager::set_post_value() method is called.
		 *
		 * This is useful for `WP_Customize_Setting` instances to watch
		 * in order to update a cached previewed value.
		 *
		 * @since 4.4.0
		 *
		 * @param string               $setting_id Setting ID.
		 * @param mixed                $value      Unsanitized setting post value.
		 * @param WP_Customize_Manager $this       WP_Customize_Manager instance.
		 */
		do_action( 'customize_post_value_set', $setting_id, $value, $this );
	}

	/**
	 * Print JavaScript settings.
	 *
	 * @since 3.4.0
	 */
	public function customize_preview_init() {

		/*
		 * Now that Customizer previews are loaded into iframes via GET requests
		 * and natural URLs with transaction UUIDs added, we need to ensure that
		 * the responses are never cached by proxies. In practice, this will not
		 * be needed if the user is logged-in anyway. But if anonymous access is
		 * allowed then the auth cookies would not be sent and WordPress would
		 * not send no-cache headers by default.
		 */
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots: noindex, nofollow, noarchive' );
		}
		add_action( 'wp_head', 'wp_no_robots' );
		add_filter( 'wp_headers', array( $this, 'filter_iframe_security_headers' ) );

		/*
		 * If preview is being served inside the customizer preview iframe, and
		 * if the user doesn't have customize capability, then it is assumed
		 * that the user's session has expired and they need to re-authenticate.
		 */
		if ( $this->messenger_channel && ! current_user_can( 'customize' ) ) {
			$this->wp_die( -1, __( 'Unauthorized. You may remove the customize_messenger_channel param to preview as frontend.' ) );
			return;
		}

		if ( current_user_can( 'customize' ) ) {
			$this->prepare_controls();
		}

		wp_enqueue_script( 'customize-preview' );
		add_action( 'wp_head', array( $this, 'customize_preview_loading_style' ) );
		add_action( 'wp_footer', array( $this, 'customize_preview_settings' ), 20 );

		/**
		 * Fires once the Customizer preview has initialized and JavaScript
		 * settings have been printed.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'customize_preview_init', $this );
	}

	/**
	 * Filter the X-Frame-Options and Content-Security-Policy headers to ensure frontend can load in customizer.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param array $headers Headers.
	 * @return array Headers.
	 */
	public function filter_iframe_security_headers( $headers ) {
		$customize_url = admin_url( 'customize.php' );
		$headers['X-Frame-Options'] = 'ALLOW-FROM ' . $customize_url;
		$headers['Content-Security-Policy'] = 'frame-ancestors ' . preg_replace( '#^(\w+://[^/]+).+?$#', '$1', $customize_url );
		return $headers;
	}

	/**
	 * Prevent sending a 404 status when returning the response for the customize
	 * preview, since it causes the jQuery Ajax to fail. Send 200 instead.
	 *
	 * @since 4.0.0
	 * @deprecated 4.7.0
	 * @access public
	 */
	public function customize_preview_override_404_status() {
		_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Print base element for preview frame.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 */
	public function customize_preview_base() {
		_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Print a workaround to handle HTML5 tags in IE < 9.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0 Customizer no longer supports IE8, so all supported browsers recognize HTML5.
	 */
	public function customize_preview_html5() {
		_deprecated_function( __FUNCTION__, '4.7.0' );
	}

	/**
	 * Print CSS for loading indicators for the Customizer preview.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function customize_preview_loading_style() {
		?><style>
			body.wp-customizer-unloading {
				opacity: 0.25;
				cursor: progress !important;
				-webkit-transition: opacity 0.5s;
				transition: opacity 0.5s;
			}
			body.wp-customizer-unloading * {
				pointer-events: none !important;
			}
		</style><?php
	}

	/**
	 * Print JavaScript settings for preview frame.
	 *
	 * @since 3.4.0
	 */
	public function customize_preview_settings() {
		$setting_values = $this->unsanitized_post_values();

		$self_url = home_url( empty( $_SERVER['REQUEST_URI'] ) ? '/' : esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		$state_query_params = array(
			'customize_theme',
			'customize_changeset_uuid',
			'customize_messenger_channel',
		);
		$self_url = remove_query_arg( $state_query_params, $self_url );

		$settings = array(
			'changeset' => array(
				'uuid' => $this->changeset_uuid,
				'stateQueryParams' => $state_query_params, // @todo Additional persisted query vars may need to be indiated via a customized_persisted_query_vars param.
			),
			'theme' => array(
				'stylesheet' => $this->get_stylesheet(),
				'active'     => $this->is_theme_active(),
			),
			'url' => array(
				'self' => $self_url,
				'allowed' => array_map( 'esc_url_raw', $this->get_allowed_urls() ),
				'isCrossDomain' => $this->is_cross_domain(),
			),
			'channel' => $this->messenger_channel,
			'activePanels' => array(),
			'activeSections' => array(),
			'activeControls' => array(),
			'nonce' => current_user_can( 'customize' ) ? $this->get_nonces() : array(),
			'l10n' => array(
				'shiftClickToEdit' => __( 'Shift-click to edit this element.' ),
			),
			'_dirty' => array_keys( $setting_values ),
		);

		foreach ( $this->panels as $panel_id => $panel ) {
			if ( $panel->check_capabilities() ) {
				$settings['activePanels'][ $panel_id ] = $panel->active();
				foreach ( $panel->sections as $section_id => $section ) {
					if ( $section->check_capabilities() ) {
						$settings['activeSections'][ $section_id ] = $section->active();
					}
				}
			}
		}
		foreach ( $this->sections as $id => $section ) {
			if ( $section->check_capabilities() ) {
				$settings['activeSections'][ $id ] = $section->active();
			}
		}
		foreach ( $this->controls as $id => $control ) {
			if ( $control->check_capabilities() ) {
				$settings['activeControls'][ $id ] = $control->active();
			}
		}

		?>
		<script type="text/javascript">
			var _wpCustomizeSettings = <?php echo wp_json_encode( $settings ); ?>;
			_wpCustomizeSettings.values = {};
			(function( v ) {
				<?php
				/*
				 * Serialize settings separately from the initial _wpCustomizeSettings
				 * serialization in order to avoid a peak memory usage spike.
				 * @todo We may not even need to export the values at all since the pane syncs them anyway.
				 */
				foreach ( $this->settings as $id => $setting ) {
					if ( $setting->check_capabilities() ) {
						printf(
							"v[%s] = %s;\n",
							wp_json_encode( $id ),
							wp_json_encode( $setting->js_value() )
						);
					}
				}
				?>
			})( _wpCustomizeSettings.values );
		</script>
		<?php
	}

	/**
	 * Prints a signature so we can ensure the Customizer was properly executed.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 */
	public function customize_preview_signature() {
		_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Removes the signature in case we experience a case where the Customizer was not properly executed.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @param mixed $return Value passed through for {@see 'wp_die_handler'} filter.
	 * @return mixed Value passed through for {@see 'wp_die_handler'} filter.
	 */
	public function remove_preview_signature( $return = null ) {
		_deprecated_function( __METHOD__, '4.7.0' );

		return $return;
	}

	/**
	 * Is it a theme preview?
	 *
	 * @since 3.4.0
	 *
	 * @return bool True if it's a preview, false if not.
	 */
	public function is_preview() {
		return (bool) $this->previewing;
	}

	/**
	 * Retrieve the template name of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Template name.
	 */
	public function get_template() {
		return $this->theme()->get_template();
	}

	/**
	 * Retrieve the stylesheet name of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Stylesheet name.
	 */
	public function get_stylesheet() {
		return $this->theme()->get_stylesheet();
	}

	/**
	 * Retrieve the template root of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Theme root.
	 */
	public function get_template_root() {
		return get_raw_theme_root( $this->get_template(), true );
	}

	/**
	 * Retrieve the stylesheet root of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @return string Theme root.
	 */
	public function get_stylesheet_root() {
		return get_raw_theme_root( $this->get_stylesheet(), true );
	}

	/**
	 * Filters the current theme and return the name of the previewed theme.
	 *
	 * @since 3.4.0
	 *
	 * @param $current_theme {@internal Parameter is not used}
	 * @return string Theme name.
	 */
	public function current_theme( $current_theme ) {
		return $this->theme()->display('Name');
	}

	/**
	 * Validates setting values.
	 *
	 * Validation is skipped for unregistered settings or for values that are
	 * already null since they will be skipped anyway. Sanitization is applied
	 * to values that pass validation, and values that become null or `WP_Error`
	 * after sanitizing are marked invalid.
	 *
	 * @since 4.6.0
	 * @access public
	 *
	 * @see WP_REST_Request::has_valid_params()
	 * @see WP_Customize_Setting::validate()
	 *
	 * @param array $setting_values Mapping of setting IDs to values to validate and sanitize.
	 * @param array $options {
	 *     Options.
	 *
	 *     @var bool $validate_existence    Whether a setting's existence will be checked.
	 *     @var bool $validate_capability Whether the setting capability will be checked.
	 * }
	 * @return array Mapping of setting IDs to return value of validate method calls, either `true` or `WP_Error`.
	 */
	public function validate_setting_values( $setting_values, $options = array() ) {
		$options = wp_parse_args( $options, array(
			'validate_capability' => false,
			'validate_existence' => false,
		) );

		$validities = array();
		foreach ( $setting_values as $setting_id => $unsanitized_value ) {
			$setting = $this->get_setting( $setting_id );
			if ( ! $setting ) {
				if ( $options['validate_existence'] ) {
					$validities[ $setting_id ] = new WP_Error( 'unrecognized_setting', __( 'Setting does not exist or is unrecognized.' ) );
				}
				continue;
			}
			if ( is_null( $unsanitized_value ) ) {
				// @todo Should this be skipped or be invalid?
				continue;
			}
			if ( $options['validate_capability'] && ! current_user_can( $setting->capability ) ) {
				$validity = new WP_Error( 'unauthorized', __( 'Unauthorized.' ) );
			} else {
				$validity = $setting->validate( $unsanitized_value );
			}
			if ( ! is_wp_error( $validity ) ) {
				$value = $setting->sanitize( $unsanitized_value );
				if ( is_null( $value ) ) {
					$validity = false;
				} elseif ( is_wp_error( $value ) ) {
					$validity = $value;
				}
			}
			if ( false === $validity ) {
				$validity = new WP_Error( 'invalid_value', __( 'Invalid value.' ) );
			}
			$validities[ $setting_id ] = $validity;
		}
		return $validities;
	}

	/**
	 * Prepares setting validity for exporting to the client (JS).
	 *
	 * Converts `WP_Error` instance into array suitable for passing into the
	 * `wp.customize.Notification` JS model.
	 *
	 * @since 4.6.0
	 * @access public
	 *
	 * @param true|WP_Error $validity Setting validity.
	 * @return true|array If `$validity` was a WP_Error, the error codes will be array-mapped
	 *                    to their respective `message` and `data` to pass into the
	 *                    `wp.customize.Notification` JS model.
	 */
	public function prepare_setting_validity_for_js( $validity ) {
		if ( is_wp_error( $validity ) ) {
			$notification = array();
			foreach ( $validity->errors as $error_code => $error_messages ) {
				$notification[ $error_code ] = array(
					'message' => join( ' ', $error_messages ),
					'data' => $validity->get_error_data( $error_code ),
				);
			}
			return $notification;
		} else {
			return true;
		}
	}

	/**
	 * Save (update) changeset.
	 *
	 * @since 3.4.0
	 * @since 4.7.0 The semantics of this method have changed to update a changeset, optionally to also change the status.
	 */
	public function save() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'unauthenticated' );
		}

		if ( ! $this->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		$action = 'save-customize_' . $this->get_stylesheet();
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( 'invalid_nonce' );
		}

		$changeset_post_id = $this->changeset_post_id();
		if ( $changeset_post_id && 'publish' === get_post_status( $changeset_post_id ) ) {
			wp_send_json_error( 'changeset_already_published' );
		}

		if ( empty( $changeset_post_id ) ) {
			if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->create_posts ) ) {
				wp_send_json_error( 'cannot_create_changeset_post' );
			}
		} else {
			if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->edit_post, $changeset_post_id ) ) {
				wp_send_json_error( 'cannot_edit_changeset_post' );
			}
		}

		if ( ! empty( $_POST['customize_changeset_data'] ) ) {
			$input_changeset_data = json_decode( wp_unslash( $_POST['customize_changeset_data'] ), true );
			if ( ! is_array( $input_changeset_data ) ) {
				wp_send_json_error( 'invalid_customize_changeset_data' );
			}
			foreach ( $input_changeset_data as $setting_id => $setting_params ) {
				if ( null === $setting_params ) {
					$this->set_post_value( $setting_id, null ); // Explicit revert value.
				} elseif ( array_key_exists( 'value', $setting_params ) ) {
					$this->set_post_value( $setting_id, $setting_params['value'] );
				}
			}
		} else {
			$input_changeset_data = array();
		}

		// Validate title.
		$changeset_title = null;
		if ( isset( $_POST['customize_changeset_title'] ) ) {
			$changeset_title = sanitize_text_field( wp_unslash( $_POST['customize_changeset_title'] ) );
		}

		// Validate changeset status param.
		$changeset_status = null;
		if ( isset( $_POST['customize_changeset_status'] ) ) {
			$changeset_status = wp_unslash( $_POST['customize_changeset_status'] );
			if ( ! get_post_status_object( $changeset_status ) || ! in_array( $changeset_status, array( 'draft', 'pending', 'publish', 'future' ), true ) ) {
				wp_send_json_error( 'bad_customize_changeset_status', 400 );
			}
			$is_publish = ( 'publish' === $changeset_status || 'future' === $changeset_status );
			if ( $is_publish ) {
				if ( ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->publish_posts ) ) {
					wp_send_json_error( 'changeset_publish_unauthorized', 403 );
				}
				if ( false === has_action( 'publish_customize_changeset', '_wp_customize_publish_changeset' ) ) {
					wp_send_json_error( 'missing_publish_callback', 500 );
				}
			}
		}

		// Validate changeset date param. Date is assumed to be in local time for the WP.
		$changeset_date = null;
		if ( isset( $_POST['customize_changeset_date'] ) ) {
			$changeset_date = wp_unslash( $_POST['customize_changeset_date'] );
			if ( ! preg_match( '/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $changeset_date ) ) {
				wp_send_json_error( 'bad_customize_changeset_date', 400 );
			}
			if ( ( 'publish' === $changeset_status || 'future' === $changeset_status ) && ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->publish_posts ) ) {
				wp_send_json_error( 'changeset_publish_unauthorized', 403 );
			}
			$changeset_date_gmt = get_gmt_from_date( $changeset_date );
			$now = gmdate( 'Y-m-d H:i:59' );
			$is_future_dated = ( mysql2date( 'U', $changeset_date_gmt, false ) > mysql2date( 'U', $now, false ) );
			if ( ! $this->is_theme_active() && ( 'future' === $changeset_status || $is_future_dated ) ) {
				wp_send_json_error( 'cannot_schedule_theme_switches', 400 ); // @todo This should be allowed in the future, when theme is a regular setting.
			}
			$will_remain_auto_draft = ( ! $changeset_status && ( ! $changeset_post_id || 'auto-draft' === get_post_status( $changeset_post_id ) ) );
			if ( $changeset_date && $will_remain_auto_draft ) {
				wp_send_json_error( 'cannot_supply_date_for_auto_draft_changeset', 400 );
			}
		}

		/**
		 * Fires before save validation happens.
		 *
		 * Plugins can add just-in-time {@see 'customize_validate_{$this->ID}'} filters
		 * at this point to catch any settings registered after `customize_register`.
		 * The dynamic portion of the hook name, `$this->ID` refers to the setting ID.
		 *
		 * @since 4.6.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'customize_save_validation_before', $this );

		// Validate settings.
		$post_values = $this->unsanitized_post_values( array(
			'exclude_changeset' => true,
			'exclude_post_data' => false,
		) );
		$this->add_dynamic_settings( array_keys( $post_values ) );
		$setting_validities = $this->validate_setting_values( $post_values, array(
			'validate_capability' => true,
			'validate_existence' => true,
		) );
		$invalid_setting_count = count( array_filter( $setting_validities, 'is_wp_error' ) );
		$exported_setting_validities = array_map( array( $this, 'prepare_setting_validity_for_js' ), $setting_validities );

		/*
		 * Short-circuit if there are invalid settings and attempting to transition
		 * the changeset to a new status (i.e. publish). When the changeset_status
		 * is set then the request is considered transactional, where if any of the
		 * setting are invalid then none of them will be saved.
		 *
		 * @todo Consider a separate 'transactional' argument which defaults to true if the $changeset_status is set.
		 */
		if ( $changeset_status && $invalid_setting_count > 0 ) {
			$response = array(
				'setting_validities' => $exported_setting_validities,
				'message' => sprintf( _n( 'There is %s invalid setting.', 'There are %s invalid settings.', $invalid_setting_count ), number_format_i18n( $invalid_setting_count ) ),
			);

			/** This filter is documented in wp-includes/class-wp-customize-manager.php */
			$response = apply_filters( 'customize_save_response', $response, $this );
			wp_send_json_error( $response );
		}

		$response = array(
			'setting_validities' => $exported_setting_validities,
		);

		// Obtain/merge data for changeset.
		$updated_setting_ids = array();
		$original_changeset_data = $this->changeset_data();
		$data = $original_changeset_data;
		foreach ( $post_values as $setting_id => $unsanitized_value ) {
			$setting = $this->get_setting( $setting_id );
			if ( ! $setting ) {
				continue;
			}

			// Skip updating changeset with invalid values.
			if ( isset( $setting_validities[ $setting_id ] ) && is_wp_error( $setting_validities[ $setting_id ] ) ) {
				continue;
			}

			$updated_setting_ids[] = $setting_id;

			if ( ! isset( $data[ $setting_id ] ) ) {
				$data[ $setting_id ] = array();
			}
			$data[ $setting_id ]['value'] = $unsanitized_value;

			if ( isset( $input_changeset_data[ $setting_id ] ) && is_array( $input_changeset_data[ $setting_id ] ) ) {
				$setting_params = $input_changeset_data[ $setting_id ];
				unset( $setting_params['value'] );
				$data[ $setting_id ] = array_merge( $input_changeset_data[ $setting_id ], $setting_params );
			}
		}

		// Handle removal of settings from changeset.
		foreach ( $input_changeset_data as $setting_id => $setting_data ) {
			if ( ! is_null( $setting_data ) ) {
				continue;
			}
			$setting = $this->get_setting( $setting_id );
			if ( $setting && $setting->check_capabilities() ) {
				unset( $data[ $setting_id ] );
			}
		}

		$filter_context = array(
			'uuid' => $this->changeset_uuid(),
			'status' => $changeset_status,
			'date' => $changeset_date,
			'post_id' => $this->changeset_post_id(),
			'previous_data' => $original_changeset_data,
			'manager' => $this,
		);

		/**
		 * Filters the settings' data that will be persisted into the changeset.
		 *
		 * Plugins may amend additional data (such as additional meta for settings) into the changeset with this filter.
		 *
		 * @param array $data Updated changeset data, mapping setting IDs to arrays containing a $value item and optionally other metadata.
		 * @param array $context {
		 *     Filter context.
		 *
		 *     @type string               $uuid          Changeset UUID.
		 *     @type string               $title         Requested title for the changeset post.
		 *     @type string               $status        Requested status for the changeset post.
		 *     @type string               $date          Requested date for the changeset post.
		 *     @type int|false            $post_id       Post ID for the changeset, or false if it doesn't exist yet.
		 *     @type array                $previous_data Previous data contained in the changeset.
		 *     @type WP_Customize_Manager $manager       Manager instance.
		 * }
		 */
		$data = apply_filters( 'customize_changeset_save', $data, $filter_context );

		// Switch theme if publishing changes now.
		if ( 'publish' === $changeset_status && ! $this->is_theme_active() ) {
			// Temporarily stop previewing the theme to allow switch_themes() to operate properly.
			$this->stop_previewing_theme();
			switch_theme( $this->get_stylesheet() );
			update_option( 'theme_switched_via_customizer', true );
			$this->start_previewing_theme();
		}

		// Gather the data for wp_insert_post()/wp_update_post().
		$post_array = array(
			'post_content' => wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
		);
		if ( $changeset_title ) {
			$post_array['post_title'] = $changeset_title;
		}
		if ( $changeset_post_id ) {
			$post_array['ID'] = $changeset_post_id;
		} else {
			$post_array['post_type'] = 'customize_changeset';
			$post_array['post_name'] = $this->changeset_uuid();
			$post_array['post_status'] = 'auto-draft';
		}
		if ( $changeset_status ) {
			$post_array['post_status'] = $changeset_status;
		}
		if ( $changeset_date ) {
			$post_array['post_date'] = $changeset_date;
		}

		// @todo Consider a separate 'revision' param which makes this explicit, versus looking at whether the status is provided.
		$this->store_changeset_revision = ! empty( $changeset_status );
		add_filter( 'wp_save_post_revision_post_has_changed', array( $this, '_filter_revision_post_has_changed' ), 5, 3 );

		// @todo Add create revision arg for wp.customize.previewer.save() which defaults to true if status is provided.
		// Update the changeset post. The publish_customize_changeset action will cause the settings in the changeset to be saved via WP_Customize_Setting::save().
		$has_kses = ( false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
		if ( $has_kses ) {
			kses_remove_filters(); // Prevent KSES from corrupting JSON in post_content.
		}
		if ( $changeset_post_id ) {
			$r = wp_update_post( wp_slash( $post_array ), true );
		} else {
			$r = wp_insert_post( wp_slash( $post_array ), true );
			if ( ! is_wp_error( $changeset_post_id ) ) {
				$changeset_post_id = $r;
				$this->_changeset_post_id = $changeset_post_id;
			}
		}
		if ( $has_kses ) {
			kses_init_filters();
		}

		remove_filter( 'wp_save_post_revision_post_has_changed', array( $this, '_filter_revision_post_has_changed' ) );

		if ( is_wp_error( $r ) ) {
			$response['snapshot_save_failure'] = $r->get_error_code();

			/** This filter is documented in wp-includes/class-wp-customize-manager.php */
			$response = apply_filters( 'customize_save_response', $response, $this );
			wp_send_json_error( $response );
		}

		// Note that if the changeset status was publish, then it will get get set to trash if revisions are not supported.
		if ( 'publish' === $changeset_status ) {
			$response['changeset_status'] = $changeset_status;
		} else {
			$response['changeset_status'] = get_post_status( $changeset_post_id );
		}

		if ( 'publish' === $response['changeset_status'] ) {
			$response['next_changeset_uuid'] = $this->generate_uuid();
		}

		$response['changeset_updated_settings'] = $updated_setting_ids;

		/**
		 * Filters response data for a successful customize_save Ajax request.
		 *
		 * This filter does not apply if there was a nonce or authentication failure.
		 *
		 * @since 4.2.0
		 *
		 * @param array                $response Additional information passed back to the 'saved'
		 *                                       event on `wp.customize`.
		 * @param WP_Customize_Manager $this     WP_Customize_Manager instance.
		 */
		$response = apply_filters( 'customize_save_response', $response, $this );
		wp_send_json_success( $response );
	}

	/**
	 * Whether a changeset revision should be made.
	 *
	 * @since 4.7.0
	 * @access private
	 * @var bool
	 */
	protected $store_changeset_revision;

	/**
	 * Filters whether a changeset has changed to create a new revision.
	 *
	 * Note that this will not be called while a changeset post remains in auto-draft status.
	 *
	 * @since 4.7.0
	 * @access private
	 *
	 * @param bool    $post_has_changed Whether the post has changed.
	 * @param WP_Post $last_revision    The last revision post object.
	 * @param WP_Post $post             The post object.
	 *
	 * @return bool Whether a revision should be made.
	 */
	public function _filter_revision_post_has_changed( $post_has_changed, $last_revision, $post ) {
		unset( $last_revision );
		if ( 'customize_changeset' === $post->post_type ) {
			$post_has_changed = $this->store_changeset_revision;
		}
		return $post_has_changed;
	}

	/**
	 * Publish changeset values.
	 *
	 * This will the values contained in a changeset, even changesets that do not
	 * correspond to current manager instance. This is called by
	 * `_wp_customize_publish_changeset()` when a customize_changeset post is
	 * transitioned to the `publish` status.
	 *
	 * Please note that if the settings in the changeset are for a non-activated
	 * theme, the theme must first be switched to (via `switch_theme()`) before
	 * invoking this method.
	 *
	 * @since 4.7.0
	 * @see _wp_customize_publish_changeset()
	 *
	 * @param int $changeset_post_id ID for customize_changeset post. Defaults to the changeset for the current manager instance.
	 */
	public function publish_changeset_values( $changeset_post_id = null ) {

		if ( empty( $changeset_post_id ) ) {
			$changeset_post_id = $this->changeset_post_id();
		}
		if ( empty( $changeset_post_id ) ) {
			return;
		}
		$changeset_post = get_post( $changeset_post_id );
		if ( ! $changeset_post ) {
			return;
		}

		$changeset_data = json_decode( $changeset_post->post_content, true );
		if ( ! is_array( $changeset_data ) ) {
			return;
		}
		$this->add_dynamic_settings( array_keys( $changeset_data ) );

		/**
		 * Fires once the theme has switched in the Customizer, but before settings
		 * have been saved.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_save', $this );

		/*
		 * Ensure that all settings will allow themselves to be saved. Note that
		 * this is safe because the setting would have checked the capability
		 * when the setting value was written into the changeset. So this is why
		 * an additional check is not required here.
		 */
		$original_setting_capabilities = array();
		foreach ( $this->settings() as $setting ) {
			$original_setting_capabilities[ $setting->id ] = $setting->capability;
			$setting->capability = 'exist';
		}

		foreach ( $changeset_data as $setting_id => $setting_params ) {
			$setting = $this->get_setting( $setting_id );
			if ( $setting && isset( $setting_params['value'] ) ) {
				$this->set_post_value( $setting_id, $setting_params['value'] );
				$setting->save();
			}
		}

		/**
		 * Fires after Customize settings have been saved.
		 *
		 * @since 3.6.0
		 *
		 * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_save_after', $this );

		foreach ( $this->settings() as $setting ) {
			if ( isset( $original_setting_capabilities[ $setting->id ] ) ) {
				$setting->capability = $original_setting_capabilities[ $setting->id ];
			}
		}
	}

	/**
	 * Refresh nonces for the current preview.
	 *
	 * @since 4.2.0
	 */
	public function refresh_nonces() {
		if ( ! $this->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		wp_send_json_success( $this->get_nonces() );
	}

	/**
	 * Add a customize setting.
	 *
	 * @since 3.4.0
	 * @since 4.5.0 Return added WP_Customize_Setting instance.
	 * @access public
	 *
	 * @param WP_Customize_Setting|string $id   Customize Setting object, or ID.
	 * @param array                       $args Setting arguments; passed to WP_Customize_Setting
	 *                                          constructor.
	 * @return WP_Customize_Setting             The instance of the setting that was added.
	 */
	public function add_setting( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Setting ) {
			$setting = $id;
		} else {
			$class = 'WP_Customize_Setting';

			/** This filter is documented in wp-includes/class-wp-customize-manager.php */
			$args = apply_filters( 'customize_dynamic_setting_args', $args, $id );

			/** This filter is documented in wp-includes/class-wp-customize-manager.php */
			$class = apply_filters( 'customize_dynamic_setting_class', $class, $id, $args );

			$setting = new $class( $this, $id, $args );
		}

		$this->settings[ $setting->id ] = $setting;
		return $setting;
	}

	/**
	 * Register any dynamically-created settings, such as those from $_POST['customized']
	 * that have no corresponding setting created.
	 *
	 * This is a mechanism to "wake up" settings that have been dynamically created
	 * on the front end and have been sent to WordPress in `$_POST['customized']`. When WP
	 * loads, the dynamically-created settings then will get created and previewed
	 * even though they are not directly created statically with code.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param array $setting_ids The setting IDs to add.
	 * @return array The WP_Customize_Setting objects added.
	 */
	public function add_dynamic_settings( $setting_ids ) {
		$new_settings = array();
		foreach ( $setting_ids as $setting_id ) {
			// Skip settings already created
			if ( $this->get_setting( $setting_id ) ) {
				continue;
			}

			$setting_args = false;
			$setting_class = 'WP_Customize_Setting';

			/**
			 * Filters a dynamic setting's constructor args.
			 *
			 * For a dynamic setting to be registered, this filter must be employed
			 * to override the default false value with an array of args to pass to
			 * the WP_Customize_Setting constructor.
			 *
			 * @since 4.2.0
			 *
			 * @param false|array $setting_args The arguments to the WP_Customize_Setting constructor.
			 * @param string      $setting_id   ID for dynamic setting, usually coming from `$_POST['customized']`.
			 */
			$setting_args = apply_filters( 'customize_dynamic_setting_args', $setting_args, $setting_id );
			if ( false === $setting_args ) {
				continue;
			}

			/**
			 * Allow non-statically created settings to be constructed with custom WP_Customize_Setting subclass.
			 *
			 * @since 4.2.0
			 *
			 * @param string $setting_class WP_Customize_Setting or a subclass.
			 * @param string $setting_id    ID for dynamic setting, usually coming from `$_POST['customized']`.
			 * @param array  $setting_args  WP_Customize_Setting or a subclass.
			 */
			$setting_class = apply_filters( 'customize_dynamic_setting_class', $setting_class, $setting_id, $setting_args );

			$setting = new $setting_class( $this, $setting_id, $setting_args );

			$this->add_setting( $setting );
			$new_settings[] = $setting;
		}
		return $new_settings;
	}

	/**
	 * Retrieve a customize setting.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id Customize Setting ID.
	 * @return WP_Customize_Setting|void The setting, if set.
	 */
	public function get_setting( $id ) {
		if ( isset( $this->settings[ $id ] ) ) {
			return $this->settings[ $id ];
		}
	}

	/**
	 * Remove a customize setting.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id Customize Setting ID.
	 */
	public function remove_setting( $id ) {
		unset( $this->settings[ $id ] );
	}

	/**
	 * Add a customize panel.
	 *
	 * @since 4.0.0
	 * @since 4.5.0 Return added WP_Customize_Panel instance.
	 * @access public
	 *
	 * @param WP_Customize_Panel|string $id   Customize Panel object, or Panel ID.
	 * @param array                     $args Optional. Panel arguments. Default empty array.
	 *
	 * @return WP_Customize_Panel             The instance of the panel that was added.
	 */
	public function add_panel( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Panel ) {
			$panel = $id;
		} else {
			$panel = new WP_Customize_Panel( $this, $id, $args );
		}

		$this->panels[ $panel->id ] = $panel;
		return $panel;
	}

	/**
	 * Retrieve a customize panel.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $id Panel ID to get.
	 * @return WP_Customize_Panel|void Requested panel instance, if set.
	 */
	public function get_panel( $id ) {
		if ( isset( $this->panels[ $id ] ) ) {
			return $this->panels[ $id ];
		}
	}

	/**
	 * Remove a customize panel.
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $id Panel ID to remove.
	 */
	public function remove_panel( $id ) {
		// Removing core components this way is _doing_it_wrong().
		if ( in_array( $id, $this->components, true ) ) {
			/* translators: 1: panel id, 2: link to 'customize_loaded_components' filter reference */
			$message = sprintf( __( 'Removing %1$s manually will cause PHP warnings. Use the %2$s filter instead.' ),
				$id,
				'<a href="' . esc_url( 'https://developer.wordpress.org/reference/hooks/customize_loaded_components/' ) . '"><code>customize_loaded_components</code></a>'
			);

			_doing_it_wrong( __METHOD__, $message, '4.5.0' );
		}
		unset( $this->panels[ $id ] );
	}

	/**
	 * Register a customize panel type.
	 *
	 * Registered types are eligible to be rendered via JS and created dynamically.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @see WP_Customize_Panel
	 *
	 * @param string $panel Name of a custom panel which is a subclass of WP_Customize_Panel.
	 */
	public function register_panel_type( $panel ) {
		$this->registered_panel_types[] = $panel;
	}

	/**
	 * Render JS templates for all registered panel types.
	 *
	 * @since 4.3.0
	 * @access public
	 */
	public function render_panel_templates() {
		foreach ( $this->registered_panel_types as $panel_type ) {
			$panel = new $panel_type( $this, 'temp', array() );
			$panel->print_template();
		}
	}

	/**
	 * Add a customize section.
	 *
	 * @since 3.4.0
	 * @since 4.5.0 Return added WP_Customize_Section instance.
	 * @access public
	 *
	 * @param WP_Customize_Section|string $id   Customize Section object, or Section ID.
	 * @param array                       $args Section arguments.
	 *
	 * @return WP_Customize_Section             The instance of the section that was added.
	 */
	public function add_section( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Section ) {
			$section = $id;
		} else {
			$section = new WP_Customize_Section( $this, $id, $args );
		}

		$this->sections[ $section->id ] = $section;
		return $section;
	}

	/**
	 * Retrieve a customize section.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id Section ID.
	 * @return WP_Customize_Section|void The section, if set.
	 */
	public function get_section( $id ) {
		if ( isset( $this->sections[ $id ] ) )
			return $this->sections[ $id ];
	}

	/**
	 * Remove a customize section.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id Section ID.
	 */
	public function remove_section( $id ) {
		unset( $this->sections[ $id ] );
	}

	/**
	 * Register a customize section type.
	 *
	 * Registered types are eligible to be rendered via JS and created dynamically.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @see WP_Customize_Section
	 *
	 * @param string $section Name of a custom section which is a subclass of WP_Customize_Section.
	 */
	public function register_section_type( $section ) {
		$this->registered_section_types[] = $section;
	}

	/**
	 * Render JS templates for all registered section types.
	 *
	 * @since 4.3.0
	 * @access public
	 */
	public function render_section_templates() {
		foreach ( $this->registered_section_types as $section_type ) {
			$section = new $section_type( $this, 'temp', array() );
			$section->print_template();
		}
	}

	/**
	 * Add a customize control.
	 *
	 * @since 3.4.0
	 * @since 4.5.0 Return added WP_Customize_Control instance.
	 * @access public
	 *
	 * @param WP_Customize_Control|string $id   Customize Control object, or ID.
	 * @param array                       $args Control arguments; passed to WP_Customize_Control
	 *                                          constructor.
	 * @return WP_Customize_Control             The instance of the control that was added.
	 */
	public function add_control( $id, $args = array() ) {
		if ( $id instanceof WP_Customize_Control ) {
			$control = $id;
		} else {
			$control = new WP_Customize_Control( $this, $id, $args );
		}

		$this->controls[ $control->id ] = $control;
		return $control;
	}

	/**
	 * Retrieve a customize control.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id ID of the control.
	 * @return WP_Customize_Control|void The control object, if set.
	 */
	public function get_control( $id ) {
		if ( isset( $this->controls[ $id ] ) )
			return $this->controls[ $id ];
	}

	/**
	 * Remove a customize control.
	 *
	 * @since 3.4.0
	 *
	 * @param string $id ID of the control.
	 */
	public function remove_control( $id ) {
		unset( $this->controls[ $id ] );
	}

	/**
	 * Register a customize control type.
	 *
	 * Registered types are eligible to be rendered via JS and created dynamically.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @param string $control Name of a custom control which is a subclass of
	 *                        WP_Customize_Control.
	 */
	public function register_control_type( $control ) {
		$this->registered_control_types[] = $control;
	}

	/**
	 * Render JS templates for all registered control types.
	 *
	 * @since 4.1.0
	 * @access public
	 */
	public function render_control_templates() {
		foreach ( $this->registered_control_types as $control_type ) {
			$control = new $control_type( $this, 'temp', array(
				'settings' => array(),
			) );
			$control->print_template();
		}
		?>
		<script type="text/html" id="tmpl-customize-control-notifications">
			<ul>
				<# _.each( data.notifications, function( notification ) { #>
					<li class="notice notice-{{ notification.type || 'info' }} {{ data.altNotice ? 'notice-alt' : '' }}" data-code="{{ notification.code }}" data-type="{{ notification.type }}">{{ notification.message || notification.code }}</li>
				<# } ); #>
			</ul>
		</script>
		<?php
	}

	/**
	 * Helper function to compare two objects by priority, ensuring sort stability via instance_number.
	 *
	 * @since 3.4.0
	 *
	 * @param WP_Customize_Panel|WP_Customize_Section|WP_Customize_Control $a Object A.
	 * @param WP_Customize_Panel|WP_Customize_Section|WP_Customize_Control $b Object B.
	 * @return int
	 */
	protected function _cmp_priority( $a, $b ) {
		if ( $a->priority === $b->priority ) {
			return $a->instance_number - $b->instance_number;
		} else {
			return $a->priority - $b->priority;
		}
	}

	/**
	 * Prepare panels, sections, and controls.
	 *
	 * For each, check if required related components exist,
	 * whether the user has the necessary capabilities,
	 * and sort by priority.
	 *
	 * @since 3.4.0
	 */
	public function prepare_controls() {

		$controls = array();
		uasort( $this->controls, array( $this, '_cmp_priority' ) );

		foreach ( $this->controls as $id => $control ) {
			if ( ! isset( $this->sections[ $control->section ] ) || ! $control->check_capabilities() ) {
				continue;
			}

			$this->sections[ $control->section ]->controls[] = $control;
			$controls[ $id ] = $control;
		}
		$this->controls = $controls;

		// Prepare sections.
		uasort( $this->sections, array( $this, '_cmp_priority' ) );
		$sections = array();

		foreach ( $this->sections as $section ) {
			if ( ! $section->check_capabilities() ) {
				continue;
			}

			usort( $section->controls, array( $this, '_cmp_priority' ) );

			if ( ! $section->panel ) {
				// Top-level section.
				$sections[ $section->id ] = $section;
			} else {
				// This section belongs to a panel.
				if ( isset( $this->panels [ $section->panel ] ) ) {
					$this->panels[ $section->panel ]->sections[ $section->id ] = $section;
				}
			}
		}
		$this->sections = $sections;

		// Prepare panels.
		uasort( $this->panels, array( $this, '_cmp_priority' ) );
		$panels = array();

		foreach ( $this->panels as $panel ) {
			if ( ! $panel->check_capabilities() ) {
				continue;
			}

			uasort( $panel->sections, array( $this, '_cmp_priority' ) );
			$panels[ $panel->id ] = $panel;
		}
		$this->panels = $panels;

		// Sort panels and top-level sections together.
		$this->containers = array_merge( $this->panels, $this->sections );
		uasort( $this->containers, array( $this, '_cmp_priority' ) );
	}

	/**
	 * Enqueue scripts for customize controls.
	 *
	 * @since 3.4.0
	 */
	public function enqueue_control_scripts() {
		foreach ( $this->controls as $control ) {
			$control->enqueue();
		}
	}

	/**
	 * Determine whether the user agent is iOS.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return bool Whether the user agent is iOS.
	 */
	public function is_ios() {
		return wp_is_mobile() && preg_match( '/iPad|iPod|iPhone/', $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Get the template string for the Customizer pane document title.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return string The template string for the document title.
	 */
	public function get_document_title_template() {
		if ( $this->is_theme_active() ) {
			/* translators: %s: document title from the preview */
			$document_title_tmpl = __( 'Customize: %s' );
		} else {
			/* translators: %s: document title from the preview */
			$document_title_tmpl = __( 'Live Preview: %s' );
		}
		$document_title_tmpl = html_entity_decode( $document_title_tmpl, ENT_QUOTES, 'UTF-8' ); // Because exported to JS and assigned to document.title.
		return $document_title_tmpl;
	}

	/**
	 * Set the initial URL to be previewed.
	 *
	 * URL is validated.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @param string $preview_url URL to be previewed.
	 */
	public function set_preview_url( $preview_url ) {
		$preview_url = esc_url_raw( $preview_url );
		$this->preview_url = wp_validate_redirect( $preview_url, home_url( '/' ) );
	}

	/**
	 * Get the initial URL to be previewed.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return string URL being previewed.
	 */
	public function get_preview_url() {
		if ( empty( $this->preview_url ) ) {
			$preview_url = home_url( '/' );
		} else {
			$preview_url = $this->preview_url;
		}
		return $preview_url;
	}

	/**
	 * Determines whether the admin and the frontend are on different domains.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return bool Whether cross-domain.
	 */
	public function is_cross_domain() {
		$admin_origin = parse_url( admin_url() );
		$home_origin = parse_url( home_url() );
		$cross_domain = ( strtolower( $admin_origin['host'] ) !== strtolower( $home_origin['host'] ) );
		return $cross_domain;
	}

	/**
	 * Get URLs allowed to be previewed.
	 *
	 * If the front end and the admin are served from the same domain, load the
	 * preview over ssl if the Customizer is being loaded over ssl. This avoids
	 * insecure content warnings. This is not attempted if the admin and front end
	 * are on different domains to avoid the case where the front end doesn't have
	 * ssl certs. Domain mapping plugins can allow other urls in these conditions
	 * using the customize_allowed_urls filter.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @returns array Allowed URLs.
	 */
	public function get_allowed_urls() {
		$allowed_urls = array( home_url( '/' ) );

		if ( is_ssl() && ! $this->is_cross_domain() ) {
			$allowed_urls[] = home_url( '/', 'https' );
		}

		/**
		 * Filters the list of URLs allowed to be clicked and followed in the Customizer preview.
		 *
		 * @since 3.4.0
		 *
		 * @param array $allowed_urls An array of allowed URLs.
		 */
		$allowed_urls = array_unique( apply_filters( 'customize_allowed_urls', $allowed_urls ) );

		return $allowed_urls;
	}

	/**
	 * Set URL to link the user to when closing the Customizer.
	 *
	 * URL is validated.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @param string $return_url URL for return link.
	 */
	public function set_return_url( $return_url ) {
		$return_url = esc_url_raw( $return_url );
		$return_url = remove_query_arg( wp_removable_query_args(), $return_url );
		$return_url = wp_validate_redirect( $return_url );
		$this->return_url = $return_url;
	}

	/**
	 * Get URL to link the user to when closing the Customizer.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return string URL for link to close Customizer.
	 */
	public function get_return_url() {
		$referer = wp_get_referer();
		$excluded_referer_basenames = array( 'customize.php', 'wp-login.php' );

		if ( $this->return_url ) {
			$return_url = $this->return_url;
		} else if ( $referer && ! in_array( basename( parse_url( $referer, PHP_URL_PATH ) ), $excluded_referer_basenames, true ) ) {
			$return_url = $referer;
		} else if ( $this->preview_url ) {
			$return_url = $this->preview_url;
		} else {
			$return_url = home_url( '/' );
		}
		return $return_url;
	}

	/**
	 * Set the autofocused constructs.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @param array $autofocus {
	 *     Mapping of 'panel', 'section', 'control' to the ID which should be autofocused.
	 *
	 *     @type string [$control]  ID for control to be autofocused.
	 *     @type string [$section]  ID for section to be autofocused.
	 *     @type string [$panel]    ID for panel to be autofocused.
	 * }
	 */
	public function set_autofocus( $autofocus ) {
		$this->autofocus = array_filter( wp_array_slice_assoc( $autofocus, array( 'panel', 'section', 'control' ) ), 'is_string' );
	}

	/**
	 * Get the autofocused constructs.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return array {
	 *     Mapping of 'panel', 'section', 'control' to the ID which should be autofocused.
	 *
	 *     @type string [$control]  ID for control to be autofocused.
	 *     @type string [$section]  ID for section to be autofocused.
	 *     @type string [$panel]    ID for panel to be autofocused.
	 * }
	 */
	public function get_autofocus() {
		return $this->autofocus;
	}

	/**
	 * Get nonces for the Customizer.
	 *
	 * @since 4.5.0
	 * @return array Nonces.
	 */
	public function get_nonces() {
		$nonces = array(
			'save' => wp_create_nonce( 'save-customize_' . $this->get_stylesheet() ),
			'preview' => wp_create_nonce( 'preview-customize_' . $this->get_stylesheet() ),
		);

		/**
		 * Filters nonces for Customizer.
		 *
		 * @since 4.2.0
		 *
		 * @param array                $nonces Array of refreshed nonces for save and
		 *                                     preview actions.
		 * @param WP_Customize_Manager $this   WP_Customize_Manager instance.
		 */
		$nonces = apply_filters( 'customize_refresh_nonces', $nonces, $this );

		return $nonces;
	}

	/**
	 * Print JavaScript settings for parent window.
	 *
	 * @since 4.4.0
	 */
	public function customize_pane_settings() {

		$login_url = add_query_arg( array(
			'interim-login' => 1,
			'customize-login' => 1,
		), wp_login_url() );

		// Ensure dirty flags are set.
		foreach ( array_keys( $this->unsanitized_post_values() ) as $setting_id ) {
			$setting = $this->get_setting( $setting_id );
			if ( $setting ) {
				$setting->dirty = true;
			}
		}

		// Prepare Customizer settings to pass to JavaScript.
		$settings = array(
			'changeset' => array(
				'uuid' => $this->changeset_uuid,
				'status' => $this->changeset_post_id() ? get_post_status( $this->changeset_post_id() ) : '',
				'currentUserCapabilities' => get_post_type_object( 'customize_changeset' )->cap,
			),
			'theme'    => array(
				'stylesheet' => $this->get_stylesheet(),
				'active'     => $this->is_theme_active(),
			),
			'url'      => array(
				'preview'       => esc_url_raw( $this->get_preview_url() ),
				'parent'        => esc_url_raw( admin_url() ),
				'activated'     => esc_url_raw( home_url( '/' ) ),
				'ajax'          => esc_url_raw( admin_url( 'admin-ajax.php', 'relative' ) ),
				'allowed'       => array_map( 'esc_url_raw', $this->get_allowed_urls() ),
				'isCrossDomain' => $this->is_cross_domain(),
				'home'          => esc_url_raw( home_url( '/' ) ),
				'login'         => esc_url_raw( $login_url ),
			),
			'browser'  => array(
				'mobile' => wp_is_mobile(),
				'ios'    => $this->is_ios(),
			),
			'panels'   => array(),
			'sections' => array(),
			'nonce'    => $this->get_nonces(),
			'autofocus' => $this->get_autofocus(),
			'documentTitleTmpl' => $this->get_document_title_template(),
			'previewableDevices' => $this->get_previewable_devices(),
		);

		// Prepare Customize Section objects to pass to JavaScript.
		foreach ( $this->sections() as $id => $section ) {
			if ( $section->check_capabilities() ) {
				$settings['sections'][ $id ] = $section->json();
			}
		}

		// Prepare Customize Panel objects to pass to JavaScript.
		foreach ( $this->panels() as $panel_id => $panel ) {
			if ( $panel->check_capabilities() ) {
				$settings['panels'][ $panel_id ] = $panel->json();
				foreach ( $panel->sections as $section_id => $section ) {
					if ( $section->check_capabilities() ) {
						$settings['sections'][ $section_id ] = $section->json();
					}
				}
			}
		}

		?>
		<script type="text/javascript">
			var _wpCustomizeSettings = <?php echo wp_json_encode( $settings ); ?>;
			_wpCustomizeSettings.controls = {};
			_wpCustomizeSettings.settings = {};
			<?php

			// Serialize settings one by one to improve memory usage.
			echo "(function ( s ){\n";
			foreach ( $this->settings() as $setting ) {
				if ( $setting->check_capabilities() ) {
					printf(
						"s[%s] = %s;\n",
						wp_json_encode( $setting->id ),
						wp_json_encode( $setting->json() )
					);
				}
			}
			echo "})( _wpCustomizeSettings.settings );\n";

			// Serialize controls one by one to improve memory usage.
			echo "(function ( c ){\n";
			foreach ( $this->controls() as $control ) {
				if ( $control->check_capabilities() ) {
					printf(
						"c[%s] = %s;\n",
						wp_json_encode( $control->id ),
						wp_json_encode( $control->json() )
					);
				}
			}
			echo "})( _wpCustomizeSettings.controls );\n";
		?>
		</script>
		<?php
	}

	/**
	 * Returns a list of devices to allow previewing.
	 *
	 * @access public
	 * @since 4.5.0
	 *
	 * @return array List of devices with labels and default setting.
	 */
	public function get_previewable_devices() {
		$devices = array(
			'desktop' => array(
				'label' => __( 'Enter desktop preview mode' ),
				'default' => true,
			),
			'tablet' => array(
				'label' => __( 'Enter tablet preview mode' ),
			),
			'mobile' => array(
				'label' => __( 'Enter mobile preview mode' ),
			),
		);

		/**
		 * Filters the available devices to allow previewing in the Customizer.
		 *
		 * @since 4.5.0
		 *
		 * @see WP_Customize_Manager::get_previewable_devices()
		 *
		 * @param array $devices List of devices with labels and default setting.
		 */
		$devices = apply_filters( 'customize_previewable_devices', $devices );

		return $devices;
	}

	/**
	 * Register some default controls.
	 *
	 * @since 3.4.0
	 */
	public function register_controls() {

		/* Panel, Section, and Control Types */
		$this->register_panel_type( 'WP_Customize_Panel' );
		$this->register_section_type( 'WP_Customize_Section' );
		$this->register_section_type( 'WP_Customize_Sidebar_Section' );
		$this->register_control_type( 'WP_Customize_Color_Control' );
		$this->register_control_type( 'WP_Customize_Media_Control' );
		$this->register_control_type( 'WP_Customize_Upload_Control' );
		$this->register_control_type( 'WP_Customize_Image_Control' );
		$this->register_control_type( 'WP_Customize_Background_Image_Control' );
		$this->register_control_type( 'WP_Customize_Cropped_Image_Control' );
		$this->register_control_type( 'WP_Customize_Site_Icon_Control' );
		$this->register_control_type( 'WP_Customize_Theme_Control' );

		/* Themes */

		$this->add_section( new WP_Customize_Themes_Section( $this, 'themes', array(
			'title'      => $this->theme()->display( 'Name' ),
			'capability' => 'switch_themes',
			'priority'   => 0,
		) ) );

		// Themes Setting (unused - the theme is considerably more fundamental to the Customizer experience).
		$this->add_setting( new WP_Customize_Filter_Setting( $this, 'active_theme', array(
			'capability' => 'switch_themes',
		) ) );

		require_once( ABSPATH . 'wp-admin/includes/theme.php' );

		// Theme Controls.

		// Add a control for the active/original theme.
		if ( ! $this->is_theme_active() ) {
			$themes = wp_prepare_themes_for_js( array( wp_get_theme( $this->original_stylesheet ) ) );
			$active_theme = current( $themes );
			$active_theme['isActiveTheme'] = true;
			$this->add_control( new WP_Customize_Theme_Control( $this, $active_theme['id'], array(
				'theme'    => $active_theme,
				'section'  => 'themes',
				'settings' => 'active_theme',
			) ) );
		}

		$themes = wp_prepare_themes_for_js();
		foreach ( $themes as $theme ) {
			if ( $theme['active'] || $theme['id'] === $this->original_stylesheet ) {
				continue;
			}

			$theme_id = 'theme_' . $theme['id'];
			$theme['isActiveTheme'] = false;
			$this->add_control( new WP_Customize_Theme_Control( $this, $theme_id, array(
				'theme'    => $theme,
				'section'  => 'themes',
				'settings' => 'active_theme',
			) ) );
		}

		/* Site Identity */

		$this->add_section( 'title_tagline', array(
			'title'    => __( 'Site Identity' ),
			'priority' => 20,
		) );

		$this->add_setting( 'blogname', array(
			'default'    => get_option( 'blogname' ),
			'type'       => 'option',
			'capability' => 'manage_options',
		) );

		$this->add_control( 'blogname', array(
			'label'      => __( 'Site Title' ),
			'section'    => 'title_tagline',
		) );

		$this->add_setting( 'blogdescription', array(
			'default'    => get_option( 'blogdescription' ),
			'type'       => 'option',
			'capability' => 'manage_options',
		) );

		$this->add_control( 'blogdescription', array(
			'label'      => __( 'Tagline' ),
			'section'    => 'title_tagline',
		) );

		// Add a setting to hide header text if the theme doesn't support custom headers.
		if ( ! current_theme_supports( 'custom-header', 'header-text' ) ) {
			$this->add_setting( 'header_text', array(
				'theme_supports'    => array( 'custom-logo', 'header-text' ),
				'default'           => 1,
				'sanitize_callback' => 'absint',
			) );

			$this->add_control( 'header_text', array(
				'label'    => __( 'Display Site Title and Tagline' ),
				'section'  => 'title_tagline',
				'settings' => 'header_text',
				'type'     => 'checkbox',
			) );
		}

		$this->add_setting( 'site_icon', array(
			'type'       => 'option',
			'capability' => 'manage_options',
			'transport'  => 'postMessage', // Previewed with JS in the Customizer controls window.
		) );

		$this->add_control( new WP_Customize_Site_Icon_Control( $this, 'site_icon', array(
			'label'       => __( 'Site Icon' ),
			'description' => sprintf(
				/* translators: %s: site icon size in pixels */
				__( 'The Site Icon is used as a browser and app icon for your site. Icons must be square, and at least %s pixels wide and tall.' ),
				'<strong>512</strong>'
			),
			'section'     => 'title_tagline',
			'priority'    => 60,
			'height'      => 512,
			'width'       => 512,
		) ) );

		$this->add_setting( 'custom_logo', array(
			'theme_supports' => array( 'custom-logo' ),
			'transport'      => 'postMessage',
		) );

		$custom_logo_args = get_theme_support( 'custom-logo' );
		$this->add_control( new WP_Customize_Cropped_Image_Control( $this, 'custom_logo', array(
			'label'         => __( 'Logo' ),
			'section'       => 'title_tagline',
			'priority'      => 8,
			'height'        => $custom_logo_args[0]['height'],
			'width'         => $custom_logo_args[0]['width'],
			'flex_height'   => $custom_logo_args[0]['flex-height'],
			'flex_width'    => $custom_logo_args[0]['flex-width'],
			'button_labels' => array(
				'select'       => __( 'Select logo' ),
				'change'       => __( 'Change logo' ),
				'remove'       => __( 'Remove' ),
				'default'      => __( 'Default' ),
				'placeholder'  => __( 'No logo selected' ),
				'frame_title'  => __( 'Select logo' ),
				'frame_button' => __( 'Choose logo' ),
			),
		) ) );

		$this->selective_refresh->add_partial( 'custom_logo', array(
			'settings'            => array( 'custom_logo' ),
			'selector'            => '.custom-logo-link',
			'render_callback'     => array( $this, '_render_custom_logo_partial' ),
			'container_inclusive' => true,
		) );

		/* Colors */

		$this->add_section( 'colors', array(
			'title'          => __( 'Colors' ),
			'priority'       => 40,
		) );

		$this->add_setting( 'header_textcolor', array(
			'theme_supports' => array( 'custom-header', 'header-text' ),
			'default'        => get_theme_support( 'custom-header', 'default-text-color' ),

			'sanitize_callback'    => array( $this, '_sanitize_header_textcolor' ),
			'sanitize_js_callback' => 'maybe_hash_hex_color',
		) );

		// Input type: checkbox
		// With custom value
		$this->add_control( 'display_header_text', array(
			'settings' => 'header_textcolor',
			'label'    => __( 'Display Site Title and Tagline' ),
			'section'  => 'title_tagline',
			'type'     => 'checkbox',
			'priority' => 40,
		) );

		$this->add_control( new WP_Customize_Color_Control( $this, 'header_textcolor', array(
			'label'   => __( 'Header Text Color' ),
			'section' => 'colors',
		) ) );

		// Input type: Color
		// With sanitize_callback
		$this->add_setting( 'background_color', array(
			'default'        => get_theme_support( 'custom-background', 'default-color' ),
			'theme_supports' => 'custom-background',

			'sanitize_callback'    => 'sanitize_hex_color_no_hash',
			'sanitize_js_callback' => 'maybe_hash_hex_color',
		) );

		$this->add_control( new WP_Customize_Color_Control( $this, 'background_color', array(
			'label'   => __( 'Background Color' ),
			'section' => 'colors',
		) ) );


		/* Custom Header */

		$this->add_section( 'header_image', array(
			'title'          => __( 'Header Image' ),
			'theme_supports' => 'custom-header',
			'priority'       => 60,
		) );

		$this->add_setting( new WP_Customize_Filter_Setting( $this, 'header_image', array(
			'default'        => get_theme_support( 'custom-header', 'default-image' ),
			'theme_supports' => 'custom-header',
		) ) );

		$this->add_setting( new WP_Customize_Header_Image_Setting( $this, 'header_image_data', array(
			// 'default'        => get_theme_support( 'custom-header', 'default-image' ),
			'theme_supports' => 'custom-header',
		) ) );

		$this->add_control( new WP_Customize_Header_Image_Control( $this ) );

		/* Custom Background */

		$this->add_section( 'background_image', array(
			'title'          => __( 'Background Image' ),
			'theme_supports' => 'custom-background',
			'priority'       => 80,
		) );

		$this->add_setting( 'background_image', array(
			'default'        => get_theme_support( 'custom-background', 'default-image' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_setting( new WP_Customize_Background_Image_Setting( $this, 'background_image_thumb', array(
			'theme_supports' => 'custom-background',
		) ) );

		$this->add_control( new WP_Customize_Background_Image_Control( $this ) );

		$this->add_setting( 'background_repeat', array(
			'default'        => get_theme_support( 'custom-background', 'default-repeat' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_control( 'background_repeat', array(
			'label'      => __( 'Background Repeat' ),
			'section'    => 'background_image',
			'type'       => 'radio',
			'choices'    => array(
				'no-repeat'  => __('No Repeat'),
				'repeat'     => __('Tile'),
				'repeat-x'   => __('Tile Horizontally'),
				'repeat-y'   => __('Tile Vertically'),
			),
		) );

		$this->add_setting( 'background_position_x', array(
			'default'        => get_theme_support( 'custom-background', 'default-position-x' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_control( 'background_position_x', array(
			'label'      => __( 'Background Position' ),
			'section'    => 'background_image',
			'type'       => 'radio',
			'choices'    => array(
				'left'       => __('Left'),
				'center'     => __('Center'),
				'right'      => __('Right'),
			),
		) );

		$this->add_setting( 'background_attachment', array(
			'default'        => get_theme_support( 'custom-background', 'default-attachment' ),
			'theme_supports' => 'custom-background',
		) );

		$this->add_control( 'background_attachment', array(
			'label'      => __( 'Background Attachment' ),
			'section'    => 'background_image',
			'type'       => 'radio',
			'choices'    => array(
				'scroll'     => __('Scroll'),
				'fixed'      => __('Fixed'),
			),
		) );

		// If the theme is using the default background callback, we can update
		// the background CSS using postMessage.
		if ( get_theme_support( 'custom-background', 'wp-head-callback' ) === '_custom_background_cb' ) {
			foreach ( array( 'color', 'image', 'position_x', 'repeat', 'attachment' ) as $prop ) {
				$this->get_setting( 'background_' . $prop )->transport = 'postMessage';
			}
		}

		/*
		 * Static Front Page
		 * See also https://core.trac.wordpress.org/ticket/19627 which introduces the the static-front-page theme_support.
		 * The following replicates behavior from options-reading.php.
		 */

		$this->add_section( 'static_front_page', array(
			'title' => __( 'Static Front Page' ),
			'priority' => 120,
			'description' => __( 'Your theme supports a static front page.' ),
			'active_callback' => array( $this, 'has_published_pages' ),
		) );

		$this->add_setting( 'show_on_front', array(
			'default' => get_option( 'show_on_front' ),
			'capability' => 'manage_options',
			'type' => 'option',
		) );

		$this->add_control( 'show_on_front', array(
			'label' => __( 'Front page displays' ),
			'section' => 'static_front_page',
			'type' => 'radio',
			'choices' => array(
				'posts' => __( 'Your latest posts' ),
				'page'  => __( 'A static page' ),
			),
		) );

		$this->add_setting( 'page_on_front', array(
			'type'       => 'option',
			'capability' => 'manage_options',
		) );

		$this->add_control( 'page_on_front', array(
			'label' => __( 'Front page' ),
			'section' => 'static_front_page',
			'type' => 'dropdown-pages',
		) );

		$this->add_setting( 'page_for_posts', array(
			'type' => 'option',
			'capability' => 'manage_options',
		) );

		$this->add_control( 'page_for_posts', array(
			'label' => __( 'Posts page' ),
			'section' => 'static_front_page',
			'type' => 'dropdown-pages',
		) );
	}

	/**
	 * Return whether there are published pages.
	 *
	 * Used as active callback for static front page section and controls.
	 *
	 * @access private
	 * @since 4.7.0
	 *
	 * @returns bool Whether there are published (or to be published) pages.
	 */
	public function has_published_pages() {

		$setting = $this->get_setting( 'nav_menus_created_posts' );
		if ( $setting ) {
			foreach ( $setting->value() as $post_id ) {
				if ( 'page' === get_post_type( $post_id ) ) {
					return true;
				}
			}
		}

		return 0 !== count( get_pages() );
	}

	/**
	 * Add settings from the POST data that were not added with code, e.g. dynamically-created settings for Widgets
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @see add_dynamic_settings()
	 */
	public function register_dynamic_settings() {
		$setting_ids = array_keys( $this->unsanitized_post_values() );
		$this->add_dynamic_settings( $setting_ids );
	}

	/**
	 * Callback for validating the header_textcolor value.
	 *
	 * Accepts 'blank', and otherwise uses sanitize_hex_color_no_hash().
	 * Returns default text color if hex color is empty.
	 *
	 * @since 3.4.0
	 *
	 * @param string $color
	 * @return mixed
	 */
	public function _sanitize_header_textcolor( $color ) {
		if ( 'blank' === $color )
			return 'blank';

		$color = sanitize_hex_color_no_hash( $color );
		if ( empty( $color ) )
			$color = get_theme_support( 'custom-header', 'default-text-color' );

		return $color;
	}

	/**
	 * Callback for rendering the custom logo, used in the custom_logo partial.
	 *
	 * This method exists because the partial object and context data are passed
	 * into a partial's render_callback so we cannot use get_custom_logo() as
	 * the render_callback directly since it expects a blog ID as the first
	 * argument. When WP no longer supports PHP 5.3, this method can be removed
	 * in favor of an anonymous function.
	 *
	 * @see WP_Customize_Manager::register_controls()
	 *
	 * @since 4.5.0
	 * @access private
	 *
	 * @return string Custom logo.
	 */
	public function _render_custom_logo_partial() {
		return get_custom_logo();
	}
}
