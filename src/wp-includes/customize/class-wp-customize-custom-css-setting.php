<?php
/**
 * Customize API: WP_Customize_Custom_CSS_Setting class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.7.0
 */

/**
 * Custom Setting to handle WP Custom CSS.
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
	public $type = 'wp_custom_css';

	/**
	 * Setting Transport
	 *
	 * @var string
	 */
	public $transport = 'postMessage';

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
	 * Validate CSS.
	 *
	 * Checks for unbalanced braces and unclosed comments.
	 *
	 * @todo Needs Expansion.
	 *
	 * @since 4.7.0
	 *
	 * @param string $css The input string.
	 *
	 * @return mixed
	 */
	public function validate( $css ) {
		// Make sure that there is a closing brace for each opening brace.
		if ( ! self::validate_balanced_brackets( $css ) ) {
			return new WP_Error( 'unbalanced_braces', __( 'Your braces <code>{}</code> are unbalanced. Make sure there is a closing <code>}</code> for every opening <code>{</code>.' ) );
		}

		// Make sure that any code comments are closed properly.
		$unclosed_comment_count = self::validate_count_unclosed_comments( $css );
		if ( 0 < $unclosed_comment_count ) {
			return new WP_Error( 'unclosed_comment', sprintf( _n( 'There is %s unclosed code comment. Close each comment with <code>*/</code>.', 'There are %s unclosed code comments. Close each comment with <code>*/</code>.', $unclosed_comment_count ), $unclosed_comment_count ) );
		}
	}

	/**
	 * Sanitize CSS.
	 *
	 * Currently runs a basic wp_kses check.
	 *
	 * @todo Needs Expansion.
	 *
	 * @since 4.7.0
	 *
	 * @param string $css The input string.
	 *
	 * @return mixed
	 */
	public function sanitize( $css ) {
		return wp_kses( $css, array( '\'', '\"', '>', '<', '+' ) );
	}

	/**
	 * Bypass the process of saving the value of the "Additional CSS"
	 * customizer setting.
	 *
	 * This setting does not use "option" or "theme_mod," but
	 * rather "wp_custom_css" to trigger saving the value to
	 * the custom post type.
	 *
	 * This is already sanitized in the sanitize() method.
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
		// If there is no post id, or the post object itself is empty return false.
		if ( ! is_numeric( $current_theme_post_id ) || empty( get_post( $current_theme_post_id ) ) ) {
			return false;
		}

		$args['ID'] = $current_theme_post_id;

		$result = wp_update_post( wp_slash( $args ) );
		if ( 0 !== $result ) {
			WP_Custom_CSS::clear_transient();
		}

		return true;
	}

	/**
	 * Ensure there are a balanced number of brackets.
	 *
	 * @since 4.7.0
	 *
	 * @param string $css The CSS input string.
	 *
	 * @return bool
	 */
	public static function validate_balanced_brackets( $css ) {
		return substr_count( $css, '{' ) === substr_count( $css, '}' );
	}

	/**
	 * Count unclosed CSS Comments.
	 *
	 * Used during validation.
	 *
	 * @see self::validate()
	 *
	 * @since 4.7.0
	 *
	 * @param string $css The CSS input string.
	 *
	 * @return int
	 */
	public static function validate_count_unclosed_comments( $css ) {
		$count = 0;
		$comments = explode( '/*', $css );
		if ( ! is_array( $comments ) || ( 2 < count( $comments ) ) ) {
			return $count;
		}
		unset( $comments[0] ); // The first array came before the first comment.
		foreach ( $comments as $comment ) {
			if ( false === strpos( $comment, '*/' ) ) {
				$count++;
			}
		}
		return $count;
	}
}
