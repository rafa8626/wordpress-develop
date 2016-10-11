<?php
/**
 * Customize API: WP_Customize_Code_Editor_Control class
 *
 * Code Editor Control.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.7.0
 */

/**
 * Class WP_Customize_Code_Editor_Control
 *
 * @since 4.7.0
 *
 * @see WP_Customize_Control
 */
class WP_Customize_Code_Editor_Control extends WP_Customize_Control {

	/**
	 * The Control Type.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'code-editor';

	/**
	 * Enqueue scripts and styles for this Control.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function enqueue() {
		wp_enqueue_style( 'customize-code-editor' );
	}

	/**
	 * Empty method
	 *
	 * Don't render the control content from PHP,
	 * as it's rendered via JS on load.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function render_content() {}

	/**
	 * Render the textarea
	 *
	 * Line numbers stop at 999,
	 * but the textarea can continue to fill.
	 *
	 * Limiting the textarea itself requires
	 * handling both detecting keypress for newlines,
	 * as well as pasting from the clipboard.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function content_template() {
		?>
		<label>
			<# if ( data.label ) { #>
				<span class="customize-control-title">{{{ data.label }}}</span>
			<# } #>
			<# if ( data.description ) { #>
				<span class="description customize-control-description">{{{ data.description }}}</span>
			<# } #>
			<div class="customize-control-content">
				<div class="customize-control-code-editor-line-numbers">
					<?php echo join( '<br>', range( 1, 999 ) ); ?>
				</div>
				<textarea class="customize-control-code-editor-textarea"></textarea>
			</div>
		</label>
		<?php
	}
}
