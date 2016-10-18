<?php
/**
 * Customize API: WP_Customize_Theme_Control class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

/**
 * Customize Theme Control class.
 *
 * @since 4.2.0
 *
 * @see WP_Customize_Control
 */
class WP_Customize_Theme_Control extends WP_Customize_Control {

	/**
	 * Customize control type.
	 *
	 * @since 4.2.0
	 * @access public
	 * @var string
	 */
	public $type = 'theme';

	/**
	 * Theme object.
	 *
	 * @since 4.2.0
	 * @access public
	 * @var WP_Theme
	 */
	public $theme;

	/**
	 * Refresh the parameters passed to the JavaScript via JSON.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @see WP_Customize_Control::to_json()
	 */
	public function to_json() {
		parent::to_json();
		$this->json['theme'] = $this->theme;
	}

	/**
	 * Don't render the control content from PHP, as it's rendered via JS on load.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function render_content() {}

	/**
	 * Render a JS template for theme display.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function content_template() {
		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$active_url  = esc_url( remove_query_arg( 'customize_theme', $current_url ) );
		$preview_url = esc_url( add_query_arg( 'customize_theme', '__THEME__', $current_url ) ); // Token because esc_url() strips curly braces.
		$preview_url = str_replace( '__THEME__', '{{ data.theme.id }}', $preview_url );
		/* translators: %s: theme name */
		$details_label = sprintf( __( 'Details for theme: %s' ), '{{ data.theme.name }}' );
		/* translators: %s: theme name */
		$customize_label = sprintf( __( 'Customize theme: %s' ), '{{ data.theme.name }}' );
		/* translators: %s: theme name */
		$preview_label = sprintf( __( 'Live preview theme: %s' ), '{{ data.theme.name }}' );
		/* translators: %s: theme name */
		$install_label = sprintf( __( 'Install and preview theme: %s' ), '{{ data.theme.name }}' );
		?>
		<# if ( data.theme.active ) { #>
			<div class="theme active" tabindex="0" aria-describedby="{{ data.section }}-{{ data.theme.id }}-action {{ data.theme.id }}-name">
		<# } else { #>
			<div class="theme" tabindex="0" aria-describedby="{{ data.section }}-{{ data.theme.id }}-action {{ data.theme.id }}-name">
		<# } #>

			<# if ( data.theme.screenshot && data.theme.screenshot[0] ) { #>
				<div class="theme-screenshot">
					<img data-src="{{ data.theme.screenshot[0] }}" alt="" />
				</div>
			<# } else { #>
				<div class="theme-screenshot blank"></div>
			<# } #>

			<span class="more-details theme-details" id="{{ data.section }}-{{ data.theme.id }}-action" aria-label="<?php echo $details_label; ?>"><?php _e( 'Theme Details' ); ?></span>


			<# if ( 'installed' === data.theme.type && data.theme.hasUpdate ) { #>
				<div class="update-message notice inline notice-warning notice-alt" data-slug="{{ data.theme.id }}"><p><?php _e( 'New version available. <button class="button-link update-theme" type="button">Update now</button>' ); ?></p></div>
			<# } #>

			<# if ( data.theme.active ) { #>
				<h3 class="theme-name" id="{{ data.section }}-{{ data.theme.id }}-name">
					<?php
					/* translators: %s: theme name */
					printf( __( '<span>Current:</span> %s' ), '{{ data.theme.name }}' );
					?>
				</h3>
				<div class="theme-actions">
					<button type="button" class="button button-primary customize-theme" aria-label="<?php echo $customize_label; ?>"><?php _e( 'Customize' ); ?></button>
				</div>
				<div class="notice notice-success notice-alt"><p><?php _e( 'Installed' ); ?></p></div>
			<# } else if ( 'installed' === data.theme.type ) { #>
				<h3 class="theme-name" id="{{ data.section }}-{{ data.theme.id }}-name">{{ data.theme.name }}</h3>
				<div class="theme-actions">
					<button type="button" class="button button-primary preview-theme" aria-label="<?php echo $preview_label; ?>" data-preview-url="<?php echo esc_attr( $preview_url ); ?>"><?php _e( 'Live Preview' ); ?></span>
				</div>
				<div class="notice notice-success notice-alt"><p><?php _e( 'Installed' ); ?></p></div>
			<# } else { #>
				<h3 class="theme-name" id="{{ data.section }}-{{ data.theme.id }}-name">{{ data.theme.name }}</h3>
				<div class="theme-actions">
					<button type="button" class="button button-primary theme-install preview" aria-label="<?php echo $install_label; ?>" data-slug="{{ data.theme.id }}" data-name="{{ data.theme.name }}" data-previewurl="<?php echo esc_attr( $preview_url ); ?>"><?php _e( 'Install & Preview' ); ?></button>
				</div>
			<# } #>			
		</div>
	<?php
	}
}
