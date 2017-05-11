<?php
/**
 * Unit tests covering WP_Widget_Text functionality.
 *
 * @package    WordPress
 * @subpackage widgets
 */

/**
 * Test wp-includes/widgets/class-wp-widget-text.php
 *
 * @group widgets
 */
class Test_WP_Widget_Text extends WP_UnitTestCase {
	/**
	 * Args passed to the widget_text filter.
	 *
	 * @var array
	 */
	protected $widget_text_args;

	/**
	 * Args passed to the widget_text_content filter.
	 *
	 * @var array
	 */
	protected $widget_text_content_args;

	/**
	 * Clean up global scope.
	 *
	 * @global WP_Scripts $wp_scripts
	 * @global WP_Styles  $wp_style
	 */
	function clean_up_global_scope() {
		global $wp_scripts, $wp_styles;
		parent::clean_up_global_scope();
		$wp_scripts = null;
		$wp_styles = null;
	}

	/**
	 * Test enqueue_admin_scripts method.
	 *
	 * @covers WP_Widget_Text::_register
	 */
	function test__register() {
		set_current_screen( 'widgets.php' );
		$widget = new WP_Widget_Text();
		$widget->_register();

		$this->assertEquals( 10, has_action( 'admin_print_scripts-widgets.php', array( $widget, 'enqueue_admin_scripts' ) ) );
		$this->assertEquals( 10, has_action( 'admin_footer-widgets.php', array( $widget, 'render_control_template_scripts' ) ) );
	}

	/**
	 * Test widget method.
	 *
	 * @covers WP_Widget_Text::widget
	 */
	function test_widget() {
		$widget = new WP_Widget_Text();
		$text = "Lorem ipsum dolor sit amet, consectetur adipiscing elit.\n Praesent ut turpis consequat lorem volutpat bibendum vitae vitae ante.";

		$args = array(
			'before_title'  => '<h2>',
			'after_title'   => "</h2>\n",
			'before_widget' => '<section>',
			'after_widget'  => "</section>\n",
		);
		$instance = array(
			'title'  => 'Foo',
			'text'   => $text,
			'filter' => false,
		);

		add_filter( 'widget_text_content', array( $this, 'filter_widget_text_content' ), 10, 3 );
		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();
		$this->assertNotContains( '<p>', $output );
		$this->assertNotContains( '<br />', $output );
		$this->assertEmpty( $this->widget_text_content_args );

		$instance['filter'] = true;
		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();
		$this->assertContains( '<p>', $output );
		$this->assertContains( '<br />', $output );
		$this->assertEmpty( $this->widget_text_content_args );

		add_filter( 'widget_text', array( $this, 'filter_widget_text' ), 10, 3 );
		$instance['filter'] = 'content';
		ob_start();
		$widget->widget( $args, $instance );
		$output = ob_get_clean();
		$this->assertContains( '<p>', $output );
		$this->assertContains( '<br />', $output );
		$this->assertCount( 3, $this->widget_text_args );
		$this->assertEquals( $instance['text'], $this->widget_text_args[0] );
		$this->assertEquals( $instance, $this->widget_text_args[1] );
		$this->assertEquals( $widget, $this->widget_text_args[2] );
		$this->assertCount( 3, $this->widget_text_content_args );
		$this->assertEquals( wpautop( $instance['text'] ), $this->widget_text_content_args[0] );
		$this->assertEquals( $instance, $this->widget_text_content_args[1] );
		$this->assertEquals( $widget, $this->widget_text_content_args[2] );
	}

	/**
	 * Filters the content of the Text widget.
	 *
	 * @param string         $widget_text The widget content.
	 * @param array          $instance    Array of settings for the current widget.
	 * @param WP_Widget_Text $widget      Current Text widget instance.
	 * @return string Widget text.
	 */
	function filter_widget_text( $widget_text, $instance, $widget ) {
		$this->widget_text_args = func_get_args();

		return $widget_text;
	}

	/**
	 * Filters the content of the Text widget to apply changes expected from the visual (TinyMCE) editor.
	 *
	 * @param string         $widget_text The widget content.
	 * @param array          $instance    Array of settings for the current widget.
	 * @param WP_Widget_Text $widget      Current Text widget instance.
	 * @return string Widget content.
	 */
	function filter_widget_text_content( $widget_text, $instance, $widget ) {
		$this->widget_text_content_args = func_get_args();

		return $widget_text;
	}

	/**
	 * Test update method.
	 *
	 * @covers WP_Widget_Text::update
	 */
	function test_update() {
		$widget = new WP_Widget_Text();
		$instance = array(
			'text'  => '',
			'title' => '',
		);

		wp_set_current_user( $this->factory()->user->create( array(
			'role' => 'author',
		) ) );

		// Should return valid instance.
		$expected = array(
			'text'   => '',
			'title'  => '',
			'filter' => 'content',
		);
		$result = $widget->update( $expected, $instance );
		$this->assertSame( $result, $expected );

		// Back-compat with pre-4.8.
		$this->assertTrue( ! empty( $expected['filter'] ) );

		wp_get_current_user()->add_cap( 'unfiltered_html' );
		$expected['text'] = '<script>alert( "Howdy!" );</script>';
		$result = $widget->update( $expected, $instance );
		if ( defined( 'WP_TESTS_MULTISITE') && WP_TESTS_MULTISITE ) {
			$expected['text'] = 'alert( "Howdy!" );';
		}
		$this->assertSame( $result, $expected );
	}

	/**
	 * Test enqueue_admin_scripts method.
	 *
	 * @covers WP_Widget_Text::enqueue_admin_scripts
	 */
	function test_enqueue_admin_scripts() {
		set_current_screen( 'widgets.php' );
		$widget = new WP_Widget_Text();
		$widget->enqueue_admin_scripts();

		$this->assertTrue( wp_script_is( 'text-widgets' ) );
	}

	/**
	 * Test render_control_template_scripts method.
	 *
	 * @covers WP_Widget_Text::render_control_template_scripts
	 */
	function test_render_control_template_scripts() {
		$widget = new WP_Widget_Text();

		ob_start();
		$widget->render_control_template_scripts();
		$output = ob_get_clean();

		$this->assertContains( '<script type="text/html" id="tmpl-widget-text-control-fields">', $output );
	}
}
