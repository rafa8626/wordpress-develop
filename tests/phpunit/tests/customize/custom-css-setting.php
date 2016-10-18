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
		$this->wp_customize->add_setting( $this->setting );
	}

	/**
	 * Tear down the test case.
	 */
	function tearDown() {
		parent::tearDown();
		$this->setting = null;
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
	 * @covers WP_Customize_Custom_CSS_Setting::__construct()
	 */
	function test_construct() {
		$this->assertTrue( post_type_exists( 'custom_css' ) );
		$this->assertEquals( 'custom_css', $this->setting->type );
		$this->assertEquals( 'twentysixteen', $this->setting->stylesheet );
		$this->assertEquals( 'unfiltered_css', $this->setting->capability );

		$exception = null;
		try {
			$x = new WP_Customize_Custom_CSS_Setting( $this->wp_customize, 'bad' );
			unset( $x );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );

		$exception = null;
		try {
			$x = new WP_Customize_Custom_CSS_Setting( $this->wp_customize, 'custom_css' );
			unset( $x );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
	}

	/**
	 * Test WP_Customize_Custom_CSS_Setting::update().
	 *
	 * @covers wp_get_custom_css()
	 * @covers WP_Customize_Custom_CSS_Setting::value()
	 * @covers WP_Customize_Custom_CSS_Setting::preview()
	 * @covers WP_Customize_Custom_CSS_Setting::update()
	 */
	function test_crud() {
		$original_css = 'body { color: black; }';
		$this->factory()->post->create( array(
			'post_title' => $this->setting->stylesheet,
			'post_name' => $this->setting->stylesheet,
			'post_content' => 'body { color: black; }',
			'post_status' => 'publish',
			'post_type' => 'custom_css',
		) );

		$this->assertEquals( $original_css, wp_get_custom_css( $this->setting->stylesheet ) );
		$this->assertEquals( $original_css, $this->setting->value() );

		$updated_css = 'body { color: blue; }';
		$this->wp_customize->set_post_value( $this->setting->id, $updated_css );
		$this->setting->save();
		$this->assertEquals( $updated_css, $this->setting->value() );
		$this->assertEquals( $updated_css, wp_get_custom_css( $this->setting->stylesheet ) );

		$previewed_css = 'body { color: red; }';
		$this->wp_customize->set_post_value( $this->setting->id, $previewed_css );
		$this->setting->preview();
		$this->assertEquals( $previewed_css, $this->setting->value() );
		$this->assertEquals( $previewed_css, wp_get_custom_css( $this->setting->stylesheet ) );
	}

	/**
	 * Test WP_Customize_Custom_CSS_Setting::sanitize().
	 *
	 * @covers WP_Customize_Custom_CSS_Setting::sanitize()
	 */
	function test_sanitize() {
		$this->markTestIncomplete();
	}

	/**
	 * Tests that validation errors are caught appropriately.
	 *
	 * Note that the $validity \WP_Error object must be reset each time
	 * as it picks up the Errors and passes them to the next assertion.
	 *
	 * @covers WP_Customize_Custom_CSS_Setting::validate()
	 */
	function test_validate() {

		// Empty CSS throws no errors.
		$result = $this->setting->validate( '' );
		$this->assertTrue( $result );

		// Basic, valid CSS throws no errors.
		$basic_css = 'body { background: #f00; } h1.site-title { font-size: 36px; } a:hover { text-decoration: none; } input[type="text"] { padding: 1em; }';
		$result = $this->setting->validate( $basic_css );
		$this->assertTrue( $result );

		// Check for Unclosed Comment.
		$unclosed_comment = $basic_css . ' /* This is a comment. ';
		$result = $this->setting->validate( $unclosed_comment );
		$this->assertTrue( array_key_exists( 'unclosed_comment', $result->errors ) );

		// Check for Unopened Comment.
		$unclosed_comment = $basic_css . ' This is a comment.*/';
		$result = $this->setting->validate( $unclosed_comment );
		$this->assertTrue( array_key_exists( 'imbalanced_comments', $result->errors ) );

		// Check for Unclosed Curly Brackets.
		$unclosed_curly_bracket = $basic_css . '  a.link { text-decoration: none;';
		$result = $this->setting->validate( $unclosed_curly_bracket );
		$this->assertTrue( array_key_exists( 'imbalanced_curly_brackets', $result->errors ) );

		// Check for Unopened Curly Brackets.
		$unopened_curly_bracket = $basic_css . '  a.link text-decoration: none; }';
		$result = $this->setting->validate( $unopened_curly_bracket );
		$this->assertTrue( array_key_exists( 'imbalanced_curly_brackets', $result->errors ) );

		// Check for Unclosed Braces.
		$unclosed_brace = $basic_css . '  input[type="text" { color: #f00; } ';
		$result = $this->setting->validate( $unclosed_brace );
		$this->assertTrue( array_key_exists( 'imbalanced_braces', $result->errors ) );

		// Check for Unopened Braces.
		$unopened_brace = $basic_css . ' inputtype="text"] { color: #f00; } ';
		$result = $this->setting->validate( $unopened_brace );
		$this->assertTrue( array_key_exists( 'imbalanced_braces', $result->errors ) );

		// Check for Imbalanced Double Quotes.
		$imbalanced_double_quotes = $basic_css . ' div.background-image { background-image: url( "image.jpg ); } ';
		$result = $this->setting->validate( $imbalanced_double_quotes );
		$this->assertTrue( array_key_exists( 'unequal_double_quotes', $result->errors ) );

		// Check for Imbalanced Single Quotes.
		$imbalanced_single_quotes = $basic_css . " div.background-image { background-image: url( 'image.jpg ); } ";
		$result = $this->setting->validate( $imbalanced_single_quotes );
		$this->assertTrue( array_key_exists( 'unequal_single_quotes', $result->errors ) );

		// Check for Unclosed Parentheses.
		$unclosed_parentheses = $basic_css . ' div.background-image { background-image: url( "image.jpg" ; } ';
		$result = $this->setting->validate( $unclosed_parentheses );
		$this->assertTrue( array_key_exists( 'imbalanced_parentheses', $result->errors ) );

		// Check for Unopened Parentheses.
		$unopened_parentheses = $basic_css . ' div.background-image { background-image: url "image.jpg" ); } ';
		$result = $this->setting->validate( $unopened_parentheses );
		$this->assertTrue( array_key_exists( 'imbalanced_parentheses', $result->errors ) );

		// A basic Content declaration with no other errors should not throw an error.
		$content_declaration = $basic_css . ' a:before { content: ""; display: block; }';
		$result = $this->setting->validate( $content_declaration );
		$this->assertTrue( $result );

		// An error, along with a Content declaration will throw two errors.
		// In this case, we're using an extra opening brace.
		$content_declaration = $basic_css . ' a:before { content: "["; display: block; }';
		$result = $this->setting->validate( $content_declaration );
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
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '/*', '*/', $valid_css );
		$this->assertTrue( $result, 'Imbalanced CSS comment characters should not be found in the test string.' );

		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '{', '}', $valid_css );
		$this->assertTrue( $result, 'Imbalanced Curly Braces should not be found in the test string.' );

		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '[',  ']', $valid_css );
		$this->assertTrue( $result, 'Imbalanced Braces should not be found in the test string.' );

		// Tests that should return false.
		$css = $valid_css . ' /* This is another comment.';
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '/*', '*/', $css );
		$this->assertEquals( false, $result, 'Imbalanced CSS comment characters should be found in the test string.' );

		$css = $valid_css . ' This is another comment. */';
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '/*', '*/', $css );
		$this->assertEquals( false, $result, 'Imbalanced CSS comment characters should be found in the test string.' );

		$css = $valid_css . ' textarea.focus { outline: none ';
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '{', '}', $css );
		$this->assertEquals( false, $result, 'Imbalanced Curly Braces should have be in the test string.' );

		$css = $valid_css . ' textarea.focus  outline: none }';
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '{', '}', $css );
		$this->assertEquals( false, $result, 'Imbalanced Curly Braces should be found in the test string.' );

		$css = $valid_css . ' inputtype="submit"] { color: #f00; }';
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '[',  ']', $css );
		$this->assertEquals( false, $result, 'Imbalanced Braces should have be in the test string.' );

		$css = $valid_css . ' input[type="submit" { color: #f00; }';
		$result = WP_Customize_Custom_CSS_Setting::validate_balanced_characters( '[',  ']', $css );
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
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '"', $string );
		$this->assertTrue( $result, 'An equal number of Double Quotes should be found in the test string.' );

		$string = "'This is a test string with an equal number of double quotes.'";
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( "'", $string );
		$this->assertTrue( $result, 'An equal number of Single Quotes should be found in the test string.' );

		$string = 'This is a string with two asterisks **';
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '*', $string );
		$this->assertTrue( $result, 'An equal number of Asterisks should be found in the test string.' );

		$string = '1234567891';
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '1', $string );
		$this->assertTrue( $result, 'An equal number of Digits ("1") should be found in the test string.' );

		// Should return false.
		$string = '"This is a test string with an unequal number of double quotes.';
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '"', $string );
		$this->assertEquals( false, $result, 'An equal number of Double Quotes should not be found in the test string.' );

		$string = "'This is a test string with an unequal number of single quotes.";
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( "'", $string );
		$this->assertEquals( false, $result, 'An equal number of Single Quotes should not be found in the test string.' );

		$string = 'This is a string with only one asterisk *';
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '*', $string );
		$this->assertEquals( false, $result, 'An equal number of Asterisks should not be found in the test string.' );

		$string = '1234567890';
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '1', $string );
		$this->assertEquals( false, $result, 'An equal number of Digits ("1") should not be found in the test string.' );

		// Three occurrences.
		$string = '1231567891';
		$result = WP_Customize_Custom_CSS_Setting::validate_equal_characters( '1', $string );
		$this->assertEquals( $result, false, 'An equal number of Digits ("1") should be found in the test string.  There were three.' );
	}

	/**
	 * Tests the number of times unclosed comments are found.
	 *
	 * @see WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments()
	 */
	function test_validate_count_unclosed_comments() {
		$string = '/* This is comment one. */  /* This is comment two. */';
		$result = WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments( $string );
		$this->assertEquals( 0, $result );

		$string = '/* This is comment one.  This is comment two. */';
		$result = WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments( $string );
		$this->assertEquals( 0, $result );

		// Remember, we're searching for unclosed -- not unbalanced -- comments.
		$string = 'This is comment one.  This is comment two. */';
		$result = WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments( $string );
		$this->assertEquals( 0 , $result );

		$string = '/* This is comment one. */  /* This is comment two.';
		$result = WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments( $string );
		$this->assertEquals( 1, $result );

		$string = '/* This is comment one.  /* This is comment two.';
		$result = WP_Customize_Custom_CSS_Setting::validate_count_unclosed_comments( $string );
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
		$result = WP_Customize_Custom_CSS_Setting::is_possible_content_error( $basic_css );
		$this->assertEquals( false, $result );

		// Should return true.
		$css = $basic_css . ' .link:before { content: "*"; display: block; }';
		$result = WP_Customize_Custom_CSS_Setting::is_possible_content_error( $css );
		$this->assertTrue( $result );

		// Add a space before the semicolon. Should still return true.
		$css = $basic_css . ' .link:before { content : "*"; display: block; }';
		$result = WP_Customize_Custom_CSS_Setting::is_possible_content_error( $css );
		$this->assertTrue( $result );
	}
}
