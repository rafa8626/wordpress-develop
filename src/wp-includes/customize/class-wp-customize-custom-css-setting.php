<?php
/**
 * Customize API: WP_Customize_Custom_CSS_Setting class
 *
 * @note this needs to be an extension of WP_Customize_Setting
 * so that we can pass the $type and $transport.
 *
 * @todo escape/validate/sanitize.
 * @todo Implement CSSTidy.
 * @todo Figure out notifications.
 * @todo add hooks.
 * @todo create tests.
 * @todo ensure this works on Networks.
 * @todo check new code structure and ensure it makes sense.
 *
 * DONE
 * @todo save it to a post type.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.7.0
 */

/**
 * A setting that is used to filter a value, but will not save the results.
 *
 * Results should be properly handled using another setting or callback.
 *
 * @since 4.7.0
 *
 * @see WP_Customize_Setting
 */
final class WP_Customize_Custom_CSS_Setting extends WP_Customize_Setting {

	/**
	 * Setting Type
	 *
	 * @var string
	 */
	public $type = 'custom_css';

	/**
	 * Setting Transport
	 *
	 * @var string
	 */
	public $transport = 'postMessage';

	/**
	 * Setting Validation Callback
	 *
	 * @todo ensure we have easily-accessible (public) sanitization/validation functions.
	 *
	 * @var string
	 */
	public $validate_callback = '';

	/**
	 * Setting Sanitization Callback
	 *
	 * @var string
	 */
	public $sanitize_callback = '';

	/**
	 * WP_Customize_Custom_CSS_Setting constructor.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_Customize_Manager $manager The Customize Manager class.
	 * @param string               $id      An specific ID of the setting. Can be a
	 *                                      theme mod or option name.
	 * @param array                $args    Setting arguments.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args = array() );
		add_filter( "customize_value_{$this->id_data['base']}", array( $this, 'get_value' ) );
		add_action( "customize_update_{$this->type}", array( $this, 'update_setting' ) );
	}

	/**
	 * Fetch the value of the setting.
	 *
	 * @since 4.7.0
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	public function get_value( $value ) {
		$curr_style_post_id = WP_Custom_CSS::get_style_post_id();
		if ( ! empty( $curr_style_post_id ) && is_numeric( $curr_style_post_id ) ) {
			$post_obj = get_post( $curr_style_post_id );
			if ( ! empty( $post_obj->post_content ) ) {
				$value = $post_obj->post_content;
			}
		}
		return $value;
	}

	/**
	 * Bypass the process of saving the value of the "Custom CSS"
	 * customizer setting.
	 *
	 * This setting does not use "option" or "theme_mod," but
	 * rather "custom_css" to trigger saving the value to
	 * the custom post type.
	 *
	 * @see WP_Customize_Setting::update()
	 *
	 * @since 4.7.0
	 *
	 * @param string $value The value to be saved.
	 *
	 * @return bool
	 */
	public function update_setting( $value ) {

		$args = array(
			'post_content' => $value,
		);
		$current_theme_post_id = WP_Custom_CSS::get_style_post_id();
		if ( ! is_numeric( $current_theme_post_id ) ) {
			return $value;
		}
		$style_post = get_post( $current_theme_post_id );

		if ( ! empty( $style_post ) ) {
			$args['ID'] = $current_theme_post_id;
		}

		$return = wp_update_post( wp_slash( $args ) );
		if ( 0 !== $return ) {
			WP_Custom_CSS::clear_transient();
		}

		return $value;
	}
}
