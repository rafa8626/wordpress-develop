<?php

/**
 * @group query
 * @group front-page-sections
 */

class Front_Page_Sections_Query extends WP_UnitTestCase {
	private $page_on_front;
	private $front_page_section;

	function setUp() {
		$this->page_on_front = self::factory()->post->create( array(
			'post_type' => 'page',
		) );
		$this->front_page_section = self::factory()->post->create( array(
			'post_type' => 'page',
		) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->page_on_front );
		update_option( 'front_page_sections', $this->front_page_section );
	}

	function tearDown() {
		global $wp_the_query;
		$wp_the_query->init();

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		delete_option( 'front_page_sections' );
	}

	function test_page_id_is_set() {
		global $wp_the_query;
		$wp_the_query->query( array() );

		$this->assertEquals( $this->page_on_front, $wp_the_query->query_vars['page_id'] );
	}

	function test_all_posts_are_returned() {
		global $wp_the_query;
		$wp_the_query->query( array() );

		$this->assertCount( 2, $wp_the_query->posts );
	}

	function test_posts_are_ordered() {
		global $wp_the_query;

		update_option( 'front_page_sections', "$this->front_page_section,$this->page_on_front" );

		$wp_the_query->query( array() );

		$this->assertCount( 2, $wp_the_query->posts );
		$this->assertEquals( $this->front_page_section, $wp_the_query->posts[0]->ID );
		$this->assertEquals( $this->page_on_front, $wp_the_query->posts[1]->ID );
		$this->assertEquals( $this->page_on_front, $wp_the_query->post->ID );
	}

	function test_lots_of_subsections_are_returned() {
		global $wp_the_query;

		$subsections = array();
		for( $i = 0; $i < 10; $i++ ) {
			$subsections[] = self::factory()->post->create( array(
				'post_type' => 'page',
			) );
		}

		update_option( 'front_page_sections', implode(',', $subsections ) );

		$wp_the_query->query( array() );

		$this->assertCount( 11, $wp_the_query->posts );
	}

	function test_get_post_doesnt_get_subsections() {
		$post = get_post( $this->page_on_front );
		$this->assertEquals( $this->page_on_front, $post->ID );
	}

	function test_get_pages_doesnt_get_subsections() {
		$pages = get_pages( array( 'include' => array( $this->page_on_front ) ) );

		$this->assertCount( 1, $pages );
		$this->assertEquals( $this->page_on_front, $pages[0]->ID );
	}

	function test_get_posts_doesnt_get_subsections() {
		$pages = get_posts( array( 'include' => array( $this->page_on_front ), 'post_type' => 'page' ) );

		$this->assertCount( 1, $pages );
		$this->assertEquals( $this->page_on_front, $pages[0]->ID );
	}

	function test_the_post_outputs_anchor_tag() {
		global $wp_the_query;
		$wp_the_query->query( array() );

		ob_start();
		while( $wp_the_query->have_posts() ) {
			$wp_the_query->the_post();
		}
		$actual = ob_get_contents();
		ob_end_clean();

		$expected  = '<a id="' . str_replace( '/', '.', get_page_uri( $this->page_on_front ) ) . '"></a>';
		$expected .= '<a id="' . str_replace( '/', '.', get_page_uri( $this->front_page_section ) ) . '"></a>';

		$this->assertEquals( $expected, $actual );
	}

	function test_new_wp_query_doesnt_output_anchor_tag() {
		$query = new WP_Query();
		$query->query( array() );

		ob_start();
		while( $query->have_posts() ) {
			$query->the_post();
		}
		$actual = ob_get_contents();
		ob_end_clean();

		$this->assertEmpty( $actual );
	}
}