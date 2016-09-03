<?php
/**
 * Customize API: WP_Customize_Code_Editor_Control class
 *
 * @todo trigger change in the customizer.
 * @todo make custom css <style> a conditional.
 * @todo create new CodeMirror theme.
 * @todo Implement CSSTidy.
 * @todo Figure out notifications.
 * @todo save it to a post type.
 * @todo customize-manager.php: l18n using sprintf().
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
	 * @access public
	 * @var string
	 */
	public $type = 'code_editor';

	/**
	 * Enqueue control-related scripts/styles
	 *
	 * @since 4.7.0
	 */
	public function enqueue() {
		parent::enqueue();
		wp_enqueue_script( 'codemirror' );
		wp_enqueue_style( 'codemirror' );
	}

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
			<div class="customize-control-content">
				<textarea style="width:100%;"  class="code-editor" <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
			</div>
		</label>
		<?php
	}
}