<?php


class WP_Customize_Code_Editor_Control extends WP_Customize_Control {

	/**
	 * @access public
	 * @var string
	 */
	public $type = 'code_editor';

	/**
	 * Enqueue control related scripts/styles.
	 */
	public function enqueue() {
		wp_enqueue_script( 'customize-post-date-control' );
	}

	/**
	 * Don't render the control content from PHP, as it's rendered via JS on load.
	 *
	 * @since 3.4.0
	 */
	public function render_content() {}

	/**
	 * Render our custom textarea
	 *
	 * @since 3.5
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
								<textarea rows="20"  style="width:100%;" <?php $this->link(); ?>><?php echo esc_textarea( $this->value() ); ?></textarea>
							</div>
		</label>
		<?php
	}
}