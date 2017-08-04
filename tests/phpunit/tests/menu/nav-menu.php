<?php

/**
 * @group navmenus
 */
class Tests_Nav_Menu_Theme_Change extends WP_UnitTestCase {
	
	function setUp() {
		register_nav_menus( array(
			'primary' => 'Primary',
		) );
		
		parent::setUp();
	}
	
	function tearDown() {
		global $_wp_registered_nav_menus;
		
		remove_theme_mod( 'nav_menu_locations' );
		$_wp_registered_nav_menus = array();
		
		parent::tearDown();
	}
	
	/**
	 * Two themes with one location each should just map.
	 */
	function test_one_location_each() {
		$old_nav_menu_locations = array( 'unique-slug' => 1 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), $old_nav_menu_locations );
	}
	
	/**
	 * Locations with the same name should map.
	 */
	function test_locations_with_same_slug() {
		$old_nav_menu_locations = array( 'primary' => 1, 'secondary' => 2 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		register_nav_menu( 'secondary', 'Secondary' );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), $old_nav_menu_locations );
	}
	
	/**
	 * If the new theme was previously active, we should fall back to that data.
	 */
	function test_new_theme_previously_active() {
		$old_nav_menu_locations = array( 'primary' => 3 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		$previous_locations = array( 'primary' => 1, 'secondary' => 2 );
		set_theme_mod( 'nav_menu_locations', $previous_locations );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), array_merge( $previous_locations, $old_nav_menu_locations ) );
	}
	
	/**
	 * Make educated guesses on theme locations.
	 */
	function test_location_guessing() {
		$old_nav_menu_locations = array( 'header' => 1, 'footer' => 2 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		register_nav_menu( 'secondary', 'Secondary' );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), array( 'primary' => 1, 'secondary' => 2 ) );
	}
	
	/**
	 * Make sure two locations that fall in the same group don't get the same menu assigned.
	 */
	function test_location_guessing_one_menu_per_group() {
		$old_nav_menu_locations = array( 'top-menu' => 1, 'secondary' => 2 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		register_nav_menu( 'main', 'Main' );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), array( 'main' => 1 ) );
	}
	
	/**
	 * Make sure two locations that fall in the same group get menus assigned from the same group.
	 */
	function test_location_guessing_one_menu_per_location() {
		$old_nav_menu_locations = array( 'navigation-menu' => 1, 'top-menu' => 2 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		register_nav_menu( 'main', 'Main' );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), array( 'main' => 1, 'primary' => 2 ) );
	}
	
	/**
	 * Technically possible to register menu locations numerically.
	 */
	function test_numerical_locations() {
		$old_nav_menu_locations = array( 'main' => 1, 'secondary' => 2, 'tertiary' => 3 );
		update_option( 'theme_switch_menu_locations', $old_nav_menu_locations );
		register_nav_menu( 1 , 'First' );
		
		_wp_menus_changed();
		$this->assertEqualSets( get_theme_mod( 'nav_menu_locations' ), array( 'primary' => 1 ) );
	}
}
