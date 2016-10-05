<?php
/**
 * Customize API: WP_Customize_Custom_CSS_Setting class
 *
 * This handles validation, sanitization and saving of the value.
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
		add_filter( "customize_validate_{$this->id}", array( $this, 'validate_css' ), 10, 2 );
		add_filter( "customize_sanitize_{$this->id}", array( $this, 'sanitize_css' ), 10, 2 );
		add_action( "customize_update_{$this->type}", array( $this, 'update_setting' ) );
	}

	/**
	 * Fetch the value of the setting.
	 *
	 * @see WP_Customize_Setting::value()
	 *
	 * @filter customize_value_{$this->id_data['base']}
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
	 * Checks for unbalanced braces, brackets and comments.
	 *
	 * Notifications are rendered when the Preview
	 * is saved.
	 *
	 * @todo Needs Expansion.
	 *
	 * @see WP_Customize_Setting::validate()
	 *
	 * @filter customize_validate_{$this->id}
	 *
	 * @since 4.7.0
	 *
	 * @param mixed  $validity WP_Error, else true.
	 * @param string $css The input string.
	 *
	 * @return mixed true|WP_Error True if the input was validated, otherwise WP_Error.
	 */
	public function validate_css( $validity, $css ) {
		// Make sure that there is a closing brace for each opening brace.
		if ( ! self::validate_balanced_characters( '{', '}', $css ) ) {
			$validity->add( 'unbalanced_braces', __( 'Your braces <code>{}</code> are unbalanced. Make sure there is a closing <code>}</code> for every opening <code>{</code>.' ) );
		}

		// Ensure brackets are balanced.
		if ( ! self::validate_balanced_characters( '[', ']', $css ) ) {
			$validity->add( 'unbalanced_braces', __( 'Your brackets <code>[]</code> are unbalanced. Make sure there is a closing <code>]</code> for every opening <code>[</code>.' ) );
		}

		/*
		 * Make sure any code comments are closed properly.
		 *
		 * The first check could miss stray an unpaired comment closing figure, so if
		 * The number appears to be balanced, then check for equal numbers
		 * of opening/closing comment figures.
		 *
		 * Although it may initially appear redundant, we use the first method
		 * to give more specific feedback to the user.
		 */
		$unclosed_comment_count = self::validate_count_unclosed_comments( $css );
		if ( 0 < $unclosed_comment_count ) {
			$validity->add( 'unclosed_comment', sprintf( _n( 'There is %s unclosed code comment. Close each comment with <code>*/</code>.', 'There are %s unclosed code comments. Close each comment with <code>*/</code>.', $unclosed_comment_count ), $unclosed_comment_count ) );
		} elseif ( ! self::validate_balanced_characters( '/*', '*/', $css ) ) {
			$validity->add( 'unbalanced_comments', __( 'There is an extra <code>*/</code>, indicating an end to a comment.  Be sure that there is an opening <code>/*</code> for every closing <code>*/</code>.' ) );
		}
		return $validity;
	}

	/**
	 * Sanitize CSS.
	 *
	 * Currently runs a basic wp_kses check.
	 *
	 * @todo Needs Expansion.
	 *
	 * @filter customize_sanitize_{$this->id}
	 *
	 * @since 4.7.0
	 *
	 * @param string $css The input string.
	 *
	 * @return mixed
	 */
	public function sanitize_css( $css ) {
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
	 * @action customize_update_{$this->type}
	 *
	 * @since 4.7.0
	 *
	 * @param string $value The input value.
	 *
	 * @return bool
	 */
	public function update_setting( $value ) {
		$args = array(
			'post_content' => $value,
		);
		$current_theme_post_id = WP_Custom_CSS::get_style_post_id();
		// If there is no post id, or the post object itself is empty return false.
		if ( ! is_numeric( $current_theme_post_id ) ) {
			return false;
		}

		$style_post = get_post( $current_theme_post_id );

		if ( empty( $style_post ) ) {
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
	 * Ensure there are a balanced number of paired characters.
	 *
	 * This is used to ensure the number of opening and closing
	 * characters is equal.
	 *
	 * For instance, there should be an equal number of braces ("{", "}")
	 * in the CSS.
	 *
	 * @since 4.7.0
	 *
	 * @param string $opening_char The opening character.
	 * @param string $closing_char The closing character.
	 * @param string $css The CSS input string.
	 *
	 * @return bool
	 */
	public static function validate_balanced_characters( $opening_char, $closing_char, $css ) {
		return substr_count( $css, $opening_char ) === substr_count( $css, $closing_char );
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
