<?php
/**
 * Test Test_WP_Customize_Custom_CSS_Setting.
 *
 * Tests WP_Customize_Custom_CSS_Setting.
 *
 * @group customize
 */
class Test_WP_Customize_Custom_CSS_Setting extends WP_UnitTestCase {

	/**
	 * Instance of WP_Customize_Manager which is reset for each test.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * The Setting instance.
	 *
	 * @var WP_Customize_Custom_CSS_Setting
	 */
	public $setting;

	/**
	 * If the current setting is being previewed.
	 *
	 * @var bool
	 */
	public $is_previewing = false;

	/**
	 * Set up the test case.
	 *
	 * @see WP_UnitTestCase::setup()
	 */
	function setUp() {
		parent::setUp();
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		global $wp_customize;
		$this->wp_customize = new WP_Customize_Manager();
		$wp_customize = $this->wp_customize;

		do_action( 'customize_register', $this->wp_customize );
		$this->setting = new WP_Customize_Custom_CSS_Setting( $this->wp_customize, 'custom_css[twentysixteen]' );
	}

	/**
	 * Tear down the test case.
	 */
	function tearDown() {
		$this->setting = null;
		$this->is_previewing = false;
	}

	/**
	 * Delete the $wp_customize global when cleaning up scope.
	 */
	function clean_up_global_scope() {
		global $wp_customize;
		$wp_customize = null;
		parent::clean_up_global_scope();
	}

	/**
	 * Test constructor.
	 *
	 * Mainly validates that the correct hooks exist.
	 *
	 * Also checks for the post type and the Setting Type.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::__construct()
	 */
	function test_construct() {
		$this->assertTrue( post_type_exists( 'custom_css' ) );
		$this->assertEquals( 'custom_css', $this->setting->type );
		$this->assertEquals( 10, has_filter( "customize_validate_{$this->setting->id}", array( $this->setting, 'validate_css' ) ) );
		$this->assertEquals( 10, has_filter( "customize_sanitize_{$this->setting->id}", array( $this->setting, 'sanitize_css' ) ) );
		$this->assertEquals( 10, has_action( "customize_preview_{$this->setting->id}", array( $this->setting, 'is_previewing' ) ) );
		$this->assertEquals( 10, has_filter( "customize_update_{$this->setting->type}", array( $this->setting, 'update_setting' ) ) );
	}

	/**
	 * Tests that $is_previewing is set correctly.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::is_previewing()
	 */
	function test_is_previewing() {
		$original_value = $this->setting->is_previewing;

		$this->setting->is_previewing = false;
		$this->setting->is_previewing();
		$this->assertTrue( $this->setting->is_previewing );

		$this->setting->is_previewing = $original_value;
	}

	/**
	 * Tests that validation errors are caught appropriately.
	 *
	 * Note that the $validity \WP_Error object must be reset each time
	 * as it picks up the Errors and passes them to the next assertion.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::validate_css()
	 */
	function test_validate_css() {

		$validity = new WP_Error();

		// Empty CSS throws no errors.
		$result = $this->setting->validate_css( $validity, '' );
		$this->assertEmpty( $result->errors );

		$validity = new WP_Error();
		// Basic, valid CSS throws no errors.
		$basic_css = 'body { background: #f00; } h1.site-title { font-size: 36px; } a:hover { text-decoration: none; } input[type="text"] { padding: 1em; }';
		$result = $this->setting->validate_css( $validity, $basic_css );
		$this->assertEmpty( $result->errors );

		$validity = new WP_Error();
		// Check for Unclosed Comment.
		$unclosed_comment = $basic_css . ' /* This is a comment. ';
		$result = $this->setting->validate_css( $validity, $unclosed_comment );
		$this->assertTrue( array_key_exists( 'unclosed_comment', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unopened Comment.
		$unclosed_comment = $basic_css . ' This is a comment.*/';
		$result = $this->setting->validate_css( $validity, $unclosed_comment );
		$this->assertTrue( array_key_exists( 'imbalanced_comments', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unclosed Curly Brackets.
		$unclosed_curly_bracket = $basic_css . '  a.link { text-decoration: none;';
		$result = $this->setting->validate_css( $validity, $unclosed_curly_bracket );
		$this->assertTrue( array_key_exists( 'imbalanced_curly_brackets', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unopened Curly Brackets.
		$unopened_curly_bracket = $basic_css . '  a.link text-decoration: none; }';
		$result = $this->setting->validate_css( $validity, $unopened_curly_bracket );
		$this->assertTrue( array_key_exists( 'imbalanced_curly_brackets', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unclosed Braces.
		$unclosed_brace = $basic_css . '  input[type="text" { color: #f00; } ';
		$result = $this->setting->validate_css( $validity, $unclosed_brace );
		$this->assertTrue( array_key_exists( 'imbalanced_braces', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unopened Braces.
		$unopened_brace = $basic_css . ' inputtype="text"] { color: #f00; } ';
		$result = $this->setting->validate_css( $validity, $unopened_brace );
		$this->assertTrue( array_key_exists( 'imbalanced_braces', $result->errors ) );

		$validity = new WP_Error();
		// Check for Imbalanced Double Quotes.
		$imbalanced_double_quotes = $basic_css . ' div.background-image { background-image: url( "image.jpg ); } ';
		$result = $this->setting->validate_css( $validity, $imbalanced_double_quotes );
		$this->assertTrue( array_key_exists( 'unequal_double_quotes', $result->errors ) );

		$validity = new WP_Error();
		// Check for Imbalanced Single Quotes.
		$imbalanced_single_quotes = $basic_css . " div.background-image { background-image: url( 'image.jpg ); } ";
		$result = $this->setting->validate_css( $validity, $imbalanced_single_quotes );
		$this->assertTrue( array_key_exists( 'unequal_single_quotes', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unclosed Parentheses.
		$unclosed_parentheses = $basic_css . ' div.background-image { background-image: url( "image.jpg" ; } ';
		$result = $this->setting->validate_css( $validity, $unclosed_parentheses );
		$this->assertTrue( array_key_exists( 'imbalanced_parentheses', $result->errors ) );

		$validity = new WP_Error();
		// Check for Unopened Parentheses.
		$unopened_parentheses = $basic_css . ' div.background-image { background-image: url "image.jpg" ); } ';
		$result = $this->setting->validate_css( $validity, $unopened_parentheses );
		$this->assertTrue( array_key_exists( 'imbalanced_parentheses', $result->errors ) );

		$validity = new WP_Error();
		// A basic Content declaration with no other errors should not throw an error.
		$content_declaration = $basic_css . ' a:before { content: ""; display: block; }';
		$result = $this->setting->validate_css( $validity, $content_declaration );
		$this->assertEmpty( $result->errors );

		$validity = new WP_Error();
		// An error, along with a Content declaration will throw two errors.
		// In this case, we're using an extra opening brace.
		$content_declaration = $basic_css . ' a:before { content: "["; display: block; }';
		$result = $this->setting->validate_css( $validity, $content_declaration );
		$this->assertTrue( array_key_exists( 'imbalanced_braces', $result->errors ) );
		$this->assertTrue( array_key_exists( 'css_validation_notice', $result->errors ) );
	}

	/**
	 * Tests that balanced characters are found appropriately.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::validate_balanced_characters()
	 */
	function test_validate_balanced_characters() {
		// Tests that should return true.
		$valid_css = 'body { background: #f00; } h1.site-title { font-size: 36px; } a:hover { text-decoration: none; } input[type="text"] { padding: 1em; } /* This is a comment */';
		$result = $this->setting->validate_balanced_characters( '/*', '*/', $valid_css );
		$this->assertTrue( $result, 'Imbalanced CSS comment characters should not be found in the test string.' );

		$result = $this->setting->validate_balanced_characters( '{', '}', $valid_css );
		$this->assertTrue( $result, 'Imbalanced Curly Braces should not be found in the test string.' );

		$result = $this->setting->validate_balanced_characters( '[',  ']', $valid_css );
		$this->assertTrue( $result, 'Imbalanced Braces should not be found in the test string.' );

		// Tests that should return false.
		$css = $valid_css . ' /* This is another comment.';
		$result = $this->setting->validate_balanced_characters( '/*', '*/', $css );
		$this->assertEquals( false, $result, 'Imbalanced CSS comment characters should be found in the test string.' );

		$css = $valid_css . ' This is another comment. */';
		$result = $this->setting->validate_balanced_characters( '/*', '*/', $css );
		$this->assertEquals( false, $result, 'Imbalanced CSS comment characters should be found in the test string.' );

		$css = $valid_css . ' textarea.focus { outline: none ';
		$result = $this->setting->validate_balanced_characters( '{', '}', $css );
		$this->assertEquals( false, $result, 'Imbalanced Curly Braces should have be in the test string.' );

		$css = $valid_css . ' textarea.focus  outline: none }';
		$result = $this->setting->validate_balanced_characters( '{', '}', $css );
		$this->assertEquals( false, $result, 'Imbalanced Curly Braces should be found in the test string.' );

		$css = $valid_css . ' inputtype="submit"] { color: #f00; }';
		$result = $this->setting->validate_balanced_characters( '[',  ']', $css );
		$this->assertEquals( false, $result, 'Imbalanced Braces should have be in the test string.' );

		$css = $valid_css . ' input[type="submit" { color: #f00; }';
		$result = $this->setting->validate_balanced_characters( '[',  ']', $css );
		$this->assertEquals( false, $result, 'Imbalanced Braces should have be in the test string.' );
	}

	/**
	 * Tests that an equal number of characters are found in a string.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::validate_equal_characters()
	 */
	function test_validate_equal_characters() {
		// Should return true.
		$string = '"This is a test string with an equal number of double quotes."';
		$result = $this->setting->validate_equal_characters( '"', $string );
		$this->assertTrue( $result, 'An equal number of Double Quotes should be found in the test string.' );

		$string = "'This is a test string with an equal number of double quotes.'";
		$result = $this->setting->validate_equal_characters( "'", $string );
		$this->assertTrue( $result, 'An equal number of Single Quotes should be found in the test string.' );

		$string = 'This is a string with two asterisks **';
		$result = $this->setting->validate_equal_characters( '*', $string );
		$this->assertTrue( $result, 'An equal number of Asterisks should be found in the test string.' );

		$string = '1234567891';
		$result = $this->setting->validate_equal_characters( '1', $string );
		$this->assertTrue( $result, 'An equal number of Digits ("1") should be found in the test string.' );

		// Should return false.
		$string = '"This is a test string with an unequal number of double quotes.';
		$result = $this->setting->validate_equal_characters( '"', $string );
		$this->assertEquals( false, $result, 'An equal number of Double Quotes should not be found in the test string.' );

		$string = "'This is a test string with an unequal number of single quotes.";
		$result = $this->setting->validate_equal_characters( "'", $string );
		$this->assertEquals( false, $result, 'An equal number of Single Quotes should not be found in the test string.' );

		$string = 'This is a string with only one asterisk *';
		$result = $this->setting->validate_equal_characters( '*', $string );
		$this->assertEquals( false, $result, 'An equal number of Asterisks should not be found in the test string.' );

		$string = '1234567890';
		$result = $this->setting->validate_equal_characters( '1', $string );
		$this->assertEquals( false, $result, 'An equal number of Digits ("1") should not be found in the test string.' );

		// Three occurrences.
		$string = '1231567891';
		$result = $this->setting->validate_equal_characters( '1', $string );
		$this->assertEquals( $result, false, 'An equal number of Digits ("1") should be found in the test string.  There were three.' );
	}

	/**
	 * Tests the number of times unclosed comments are found.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments()
	 */
	function test_validate_count_unclosed_comments() {
		$string = '/* This is comment one. */  /* This is comment two. */';
		$result = $this->setting->validate_count_unclosed_comments( $string );
		$this->assertEquals( 0, $result );

		$string = '/* This is comment one.  This is comment two. */';
		$result = $this->setting->validate_count_unclosed_comments( $string );
		$this->assertEquals( 0, $result );

		// Remember, we're searching for unclosed -- not unbalanced -- comments.
		$string = 'This is comment one.  This is comment two. */';
		$result = $this->setting->validate_count_unclosed_comments( $string );
		$this->assertEquals( 0 , $result );

		$string = '/* This is comment one. */  /* This is comment two.';
		$result = $this->setting->validate_count_unclosed_comments( $string );
		$this->assertEquals( 1, $result );

		$string = '/* This is comment one.  /* This is comment two.';
		$result = $this->setting->validate_count_unclosed_comments( $string );
		$this->assertEquals( 2, $result );
	}

	/**
	 * Tests if "content:" is found in a string.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments()
	 */
	function test_is_possible_content_error() {
		// Declaration "content:" does not exist.  Should return false.
		$basic_css = 'body { background: #f00; } h1.site-title { font-size: 36px; } a:hover { text-decoration: none; } input[type="text"] { padding: 1em; }';
		$result = $this->setting->is_possible_content_error( $basic_css );
		$this->assertEquals( false, $result );

		// Should return true.
		$css = $basic_css . ' .link:before { content: "*"; display: block; }';
		$result = $this->setting->is_possible_content_error( $css );
		$this->assertTrue( $result );

		// Add a space before the semicolon. Should still return true.
		$css = $basic_css . ' .link:before { content : "*"; display: block; }';
		$result = $this->setting->is_possible_content_error( $css );
		$this->assertTrue( $result );
	}
}
