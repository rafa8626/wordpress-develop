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
	public $type = 'code_editor';

	/**
	 * Empty method
	 *
	 * Don't render the control content from PHP,
	 * as it's rendered via JS on load.
	 *
	 * @since 4.7.0
	 */
	public function render_content() {}

	/**
	 * Render the textarea
	 *
	 * @since 4.7.0
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
			<div class="customize-control-content"><!-- @todo set the height in CSS -->
				<textarea class="code-editor" style="height: 200px;"></textarea>
			</div>
		</label>
		<?php
	}
}
