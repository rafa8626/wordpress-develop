<?php

/**
 * @group themes
 */
class Tests_WP_Theme_Get_Theme_Starter_Content extends WP_UnitTestCase {

	/**
	 * @var array $core_content Content taken from wp-includes/theme.php.
	 */
	public $core_content;

	function setup_core_content() {
		$this->core_content = array(
			'widgets' => array(
				'text_business_info' => array( 'text', array(
					'title' => _x( 'Find Us', 'Theme starter content' ),
					'text' => join( '', array(
						'<p><strong>' . _x( 'Address', 'Theme starter content' ) . '</strong><br />',
						_x( '123 Main Street', 'Theme starter content' ) . '<br />' . _x( 'New York, NY 10001', 'Theme starter content' ) . '</p>',
						'<p><strong>' . _x( 'Hours', 'Theme starter content' ) . '</strong><br />',
						_x( 'Monday&mdash;Friday: 9:00AM&ndash;5:00PM', 'Theme starter content' ) . '<br />' . _x( 'Saturday &amp; Sunday: 11:00AM&ndash;3:00PM', 'Theme starter content' ) . '</p>'
					) ),
				) ),
				'search' => array( 'search', array(
					'title' => _x( 'Site Search', 'Theme starter content' ),
				) ),
				'text_credits' => array( 'text', array(
					'title' => _x( 'Site Credits', 'Theme starter content' ),
					'text' => sprintf( _x( 'This site was created on %s', 'Theme starter content' ), get_date_from_gmt( current_time( 'mysql', 1 ), 'c' ) ),
				) ),
			),
			'nav_menus' => array(
				'page_home' => array(
					'type' => 'post_type',
					'object' => 'page',
					'object_id' => '{{home}}',
				),
				'page_about' => array(
					'type' => 'post_type',
					'object' => 'page',
					'object_id' => '{{about-us}}',
				),
				'page_blog' => array(
					'type' => 'post_type',
					'object' => 'page',
					'object_id' => '{{blog}}',
				),
				'page_contact' => array(
					'type' => 'post_type',
					'object' => 'page',
					'object_id' => '{{contact-us}}',
				),

				'link_yelp' => array(
					'title' => _x( 'Yelp', 'Theme starter content' ),
					'url' => 'https://www.yelp.com',
				),
				'link_facebook' => array(
					'title' => _x( 'Facebook', 'Theme starter content' ),
					'url' => 'https://www.facebook.com/wordpress',
				),
				'link_twitter' => array(
					'title' => _x( 'Twitter', 'Theme starter content' ),
					'url' => 'https://twitter.com/wordpress',
				),
				'link_instagram' => array(
					'title' => _x( 'Instagram', 'Theme starter content' ),
					'url' => 'https://www.instagram.com/explore/tags/wordcamp/',
				),
				'link_email' => array(
					'title' => _x( 'Email', 'Theme starter content' ),
					'url' => 'mailto:wordpress@example.com',
				),
			),
			'posts' => array(
				'home' => array(
					'post_type' => 'page',
					'post_title' => _x( 'Homepage', 'Theme starter content' ),
					'post_content' => _x( 'Welcome home.', 'Theme starter content' ),
				),
				'about-us' => array(
					'post_type' => 'page',
					'post_title' => _x( 'About Us', 'Theme starter content' ),
					'post_content' => _x( 'More than you ever wanted to know.', 'Theme starter content' ),
				),
				'contact-us' => array(
					'post_type' => 'page',
					'post_title' => _x( 'Contact Us', 'Theme starter content' ),
					'post_content' => _x( 'Call us at 999-999-9999.', 'Theme starter content' ),
				),
				'blog' => array(
					'post_type' => 'page',
					'post_title' => _x( 'Blog', 'Theme starter content' ),
				),

				'homepage-section' => array(
					'post_type' => 'page',
					'post_title' => _x( 'A homepage section', 'Theme starter content' ),
					'post_content' => _x( 'This is an example of a homepage section, which are managed in theme options.', 'Theme starter content' ),
				),
			),
		);

	}


	/**
	 * Testing passing an empty array
	 */
	function test_add_theme_support_empty() {
		add_theme_support( 'starter-content', array() );
		$starter_content = get_theme_starter_content();

		$this->assertEmpty( $starter_content );
	}

	/**
	 * Testing passing no parameter.
	 */
	function test_add_theme_support_single_param() {
		add_theme_support( 'starter-content' );
		$starter_content = get_theme_starter_content();

		$this->assertEmpty( $starter_content );
	}


	/**
	 * Testing the items that have cases.
	 *
	 * Testing the text_credits content is problematic as the the dates won't match
	 * so it's not included here.
	 *
	 * @dataProvider data_default_content_sections
	 *
	 */
	function test_default_content_sections( $content, $expected_content ) {

		add_theme_support( 'starter-content', $content );

		$starter_content = get_theme_starter_content();

		$this->assertSame( $expected_content, $starter_content );
	}

	/**
	 * Dataprovider for test_default_content_sections
	 *
	 * @return array {
	 *    array {
	 *         array The content to pass to add_theme_support.
	 *         array The expected output.
	 *    }
	 * }
	 */
	function data_default_content_sections() {

		$this->setup_core_content();

		return array(
			// Widgets
			array(
				array(
					'widgets' => array(
						'sidebar-1' => array(
							'text_business_info',
							'search',
						),
					),
				),
				array(
					'widgets' => array(
						'sidebar-1' => array(
							$this->core_content['widgets']['text_business_info'],
							$this->core_content['widgets']['search'],
						),
					),
				),
			),

			// Nav Menus.
			array(
				array(
					'nav_menus' => array(
						'top' => array(
							'name'  => 'Menu Name',
							'items' => array(
								'page_home',
								'page_about',
								'page_blog',
								'page_contact',
								'link_yelp',
								'link_facebook',
								'link_twitter',
								'link_instagram',
								'link_email',
							),
						),
					),
				),
				array(
					'nav_menus' => array(
						'top' => array(
							'name'  => 'Menu Name',
							'items' => array(
								$this->core_content['nav_menus']['page_home'],
								$this->core_content['nav_menus']['page_about'],
								$this->core_content['nav_menus']['page_blog'],
								$this->core_content['nav_menus']['page_contact'],
								$this->core_content['nav_menus']['link_yelp'],
								$this->core_content['nav_menus']['link_facebook'],
								$this->core_content['nav_menus']['link_twitter'],
								$this->core_content['nav_menus']['link_instagram'],
								$this->core_content['nav_menus']['link_email'],
							),
						),
					),
				),
			),
			// Posts.
			array(
				array(
					'posts' => array(
						'home',
						'about-us',
						'contact-us',
						'blog',
						'homepage-section',
					),
				),
				array(
					'posts' => $this->core_content['posts'],
				),
			),

			// Options
			array(
				array(
					'options' => array(
						'show_on_front'  => 'page',
						'page_on_front'  => '{{home}}',
						'page_for_posts' => '{{blog}}',
					),
				),
				array(
					'options' => array(
						'show_on_front'  => 'page',
						'page_on_front'  => '{{home}}',
						'page_for_posts' => '{{blog}}',
					),
				),
			),

			//Theme mods.
			array(
				array(
					'theme_mods' => array(
						'panel_1' => '{{homepage-section}}',
						'panel_2' => '{{about-us}}',
						'panel_3' => '{{blog}}',
						'panel_4' => '{{contact-us}}',
					),
				),
				array(
					'theme_mods' => array(
						'panel_1' => '{{homepage-section}}',
						'panel_2' => '{{about-us}}',
						'panel_3' => '{{blog}}',
						'panel_4' => '{{contact-us}}',
					),
				),
			),
		);
	}

	/**
	 * Testing the filter with the text_credits widget.
	 */
	function test_get_theme_starter_content_filter() {

		add_theme_support( 'starter-content',
			array(
				'widgets' => array(
					'sidebar-1' => array(
						'text_credits',
					),
				),
			)
		);

		$expected = array(
			'widgets' => array(
				'sidebar-1' => array(
					 array(
					 	'text',
						 array(
						 	'title' => __( 'Site Credits' ),
						    'text'  => 'Changed to a hardcoded string',
						 ),
					),
				),
			),
		);

		add_filter( 'get_theme_starter_content', array( $this, 'filter_text_credits' ) );
		$starter_content = get_theme_starter_content();
		$this->assertSame( $expected , $starter_content );
	}

	/**
	 * Filter the text_widget to remove the dynamic time.
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	public function filter_text_credits( $content ) {
		$content['widgets']['sidebar-1'][0] = array(
			'text',
			array(
				'title' => __( 'Site Credits' ),
				'text'  => 'Changed to a hardcoded string',
			),

		);
		return $content;
	}

}

