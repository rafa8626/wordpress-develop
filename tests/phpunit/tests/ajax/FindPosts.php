<?php

/**
 * Testing ajax post finding.
 *
 * @group ajax
 */
class Tests_Ajax_Find_Posts extends WP_Ajax_UnitTestCase {

	public function test_wp_ajax_find_posts_returns_public_posts() {

		$this->_setRole( 'administrator' );

		$page_id = self::factory()->post->create( array(
			'post_type' => 'page',
		) );
		$page = get_post( $page_id );

		$post_id = $this->front_page_section = self::factory()->post->create();
		$post = get_post( $post_id );

		// Set up a default request
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );

		// Make the request
		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertThat( $response['data'], $this->stringContains( $page->post_title ) );
		$this->assertThat( $response['data'], $this->stringContains( $post->post_title ) );
	}

	public function test_wp_ajax_find_posts_does_not_return_attachments() {

		$this->_setRole( 'administrator' );

		$attachment_id = $this->front_page_section = self::factory()->post->create(array(
			'post_type' => 'attachment',
		) );
		$attachment = get_post( $attachment_id );

		// Set up a default request
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );

		// Make the request
		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertThat( $response['data'], $this->logicalNot( $this->stringContains( $attachment->post_title ) ) );
	}

	public function test_wp_ajax_find_posts_searches() {

		$this->_setRole( 'administrator' );

		$post_id = $this->front_page_section = self::factory()->post->create();
		$post = get_post( $post_id );

		$post2_id = $this->front_page_section = self::factory()->post->create();
		$post2 = get_post( $post2_id );

		// Set up a default request
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );
		$_POST['ps']          = $post->post_title;

		// Make the request
		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertThat( $response['data'], $this->stringContains( $post->post_title ) );
		$this->assertThat( $response['data'], $this->logicalNot( $this->stringContains( $post2->post_title ) ) );
	}

	public function test_wp_ajax_find_posts_filters_by_status() {

		$this->_setRole( 'administrator' );

		$draft_id = $this->front_page_section = self::factory()->post->create( array(
			'post_status' => 'draft',
		) );
		$draft = get_post( $draft_id );

		$post2_id = $this->front_page_section = self::factory()->post->create();
		$post2 = get_post( $post2_id );

		// Set up a default request
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );
		$_POST['status']      = 'draft';

		// Make the request
		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$this->assertThat( $response['data'], $this->stringContains( $draft->post_title ) );
		$this->assertThat( $response['data'], $this->logicalNot( $this->stringContains( $post2->post_title ) ) );
	}

	public function test_wp_ajax_find_posts_returns_json() {

		$this->_setRole( 'administrator' );

		$post_id = $this->front_page_section = self::factory()->post->create();
		$post = get_post( $post_id );

		// Set up a default request
		$_POST['_ajax_nonce'] = wp_create_nonce( 'find-posts' );
		$_POST['format']      = 'json';

		// Make the request
		try {
			$this->_handleAjax( 'find_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$response = json_decode( $this->_last_response, true );

		$post_type = get_post_type_object( $post->post_type );
		$expected = array(
			'id'    => $post->ID,
			'title' => $post->post_title,
			'type'  => $post_type->labels->singular_name,
		);

		$this->assertContains( $expected, $response['data'] );
	}
}
