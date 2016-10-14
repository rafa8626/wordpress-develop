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
	 * The setting type.
	 *
	 * @var string
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public $type = 'custom_css';

	/**
	 * Used to determine if we're in the Preview.
	 *
	 * @var bool
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public $is_previewing;

	/**
	 * Setting Transport
	 *
	 * @var string
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public $transport = 'postMessage';

	/**
	 * WP_Customize_Custom_CSS_Setting constructor.
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @param WP_Customize_Manager $manager The Customize Manager class.
	 * @param string               $id      An specific ID of the setting. Can be a
	 *                                      theme mod or option name.
	 * @param array                $args    Setting arguments.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args = array() );

		add_filter( "customize_validate_{$this->id}", array( $this, 'validate_css' ), 10, 2 );
		add_filter( "customize_sanitize_{$this->id}", array( $this, 'sanitize_css' ), 10, 2 );
		add_action( "customize_preview_{$this->id}", array( $this, 'is_previewing' ) );

		if ( ! has_filter( "customize_value_{$this->id_data['base']}", array( $this, 'get_value' ) ) ) {
			add_filter( "customize_value_{$this->id_data['base']}", array( $this, 'get_value' ), 10, 2 );
		}

		if ( ! has_filter( "customize_update_{$this->type}", array( $this, 'update_setting' ) ) ) {
			add_filter( "customize_update_{$this->type}", array( $this, 'update_setting' ) );
		}
	}

	/**
	 * Set is_previewing to true.
	 *
	 * @action customize_preview_{$this->id}
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public function is_previewing() {
		$this->is_previewing = true;
	}

	/**
	 * Fetch the value of the setting.
	 *
	 * @see WP_Customize_Setting::value()
	 *
	 * @filter customize_value_{$this->id_data['base']}
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @param string $value The value.
	 * @param object $setting The Setting.
	 *
	 * @return string
	 */
	public function get_value( $value, $setting ) {
		$value = $setting->post_value();
		if ( $this->is_previewing && ! is_null( $value ) ) {
			return $value;
		}

		$custom_css_post = wp_get_custom_css_by_theme_name();
		if ( empty( $custom_css_post->post_content ) ) {
			return '';
		}
		return $custom_css_post->post_content;
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
	 * @todo remove string literals before counting characters for cases where
	 * a character is used in a "content:" string.
	 *
	 * Example:
	 * .element::before {
	 *   content: "(\"";
	 * }
	 * .element::after {
	 *   content: "\")";
	 * }
	 *
	 *
	 * @see WP_Customize_Setting::validate()
	 *
	 * @filter customize_validate_{$this->id}
	 *
	 * @access public
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

		// Ensure parentheses are balanced.
		if ( ! self::validate_balanced_characters( '(', ')', $css ) ) {
			$validity->add( 'unbalanced_braces', __( 'Your parentheses <code>()</code> are unbalanced. Make sure there is a closing <code>)</code> for every opening <code>(</code>.' ) );
		}

		// Ensure single quotes are equal.
		if ( ! self::validate_equal_characters( '\'', $css ) ) {
			$validity->add( 'unequal_single_quotes', __( 'Your single quotes <code>\'</code> are uneven. Make sure there is a closing <code>\'</code> for every opening <code>\'</code>.' ) );
		}

		// Ensure single quotes are equal.
		if ( ! self::validate_equal_characters( '"', $css ) ) {
			$validity->add( 'unequal_double_quotes', __( 'Your double quotes <code>"</code> are uneven. Make sure there is a closing <code>"</code> for every opening <code>"</code>.' ) );
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
	 * @filter customize_sanitize_{$this->id}
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @param string $css The input string.
	 *
	 * @return mixed
	 */
	public function sanitize_css( $css ) {
		return wp_sanitize_css( $css );
	}

	/**
	 * Bypass the process of saving the value of the "Additional CSS"
	 * customizer setting.
	 *
	 * This setting does not use "option" or "theme_mod," but
	 * rather "custom_css" to trigger saving.  The value is
	 * then saved to the custom_css CPT.
	 *
	 * This is already sanitized in the sanitize() method.
	 *
	 * @todo store post ID in a theme mod.
	 *
	 * @see WP_Customize_Setting::update()
	 *
	 * @action customize_update_{$this->type}
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @param string $value The input value.
	 *
	 * @return int  The custom_css Post ID.
	 */
	public static function update_setting( $value ) {
		$theme_name = get_stylesheet();
		$args = array(
			'post_content' => ( null === $value ) ? '' : $value,
			'post_title'   => $theme_name,
			'post_type'    => 'custom_css',
			'post_status'  => 'publish',
		);

		// Check to see if the post already exists.
		$post = get_page_by_title( $theme_name, 'OBJECT', 'custom_css' );
		if ( ! empty( $post->ID ) ) {
			$args['ID'] = $post->ID;
		}

		$post_id = wp_insert_post( $args );
		return $post_id;
	}

	/**
	 * Ensure there are a balanced number of paired characters.
	 *
	 * This is used to check that the number of opening and closing
	 * characters is equal.
	 *
	 * For instance, there should be an equal number of braces ("{", "}")
	 * in the CSS.
	 *
	 * @access public
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
	 * Ensure there are an even number of paired characters.
	 *
	 * This is used to check that the number of a specific
	 * character is even.
	 *
	 * For instance, there should be an even number of double quotes
	 * in the CSS.
	 *
	 * @access public
	 * @since 4.7.0
	 *
	 * @param string $char A character.
	 * @param string $css The CSS input string.
	 *
	 * @return bool
	 */
	public static function validate_equal_characters( $char, $css ) {
		$char_count = substr_count( $css, $char );
		return ( 0 === $char_count % 2 );
	}

	/**
	 * Count unclosed CSS Comments.
	 *
	 * Used during validation.
	 *
	 * @see self::validate()
	 *
	 * @access public
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
