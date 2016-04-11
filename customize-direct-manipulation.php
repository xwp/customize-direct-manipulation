<?php
/*
Plugin Name: Customize Direct Manipulation
Plugin URI: http://automattic.com/
Description: Click to edit in the Customizer
Author: Automattic
Author URI: http://automattic.com/
Version: 1.0.0
*/

/**
 * Copyright (c) 2015 Automattic. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

class Jetpack_Customizer_DM {

	private static $instance;

	private $menu_counter = 0;

	private $nav_menus = array();

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function init() {
		if ( ! is_customize_preview() ) {
			return;
		}
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_action( 'customize_preview_init', array( $this, 'preview_enqueue' ), 9 );
		add_filter( 'customize_widget_partial_refreshable', '__return_true', 20 );
		add_filter( 'wp_page_menu_args', array( $this, 'maybe_add_page_menu_class' ) );
		add_filter( 'wp_nav_menu_args', array( $this, 'make_nav_menus_discoverable' ) );
	}

	public function make_nav_menus_discoverable( $args ) {
		// only for theme locations
		if ( empty( $args['theme_location'] ) ) {
			return $args;
		}
		$location = $args['theme_location'];

		if ( ! empty( $args['container'] ) ) {
			$class_name = $this->make_unique_id( 'cdm-menu-for-' . $location );
			$this->store_menu( $class_name, $location );
			if ( ! empty( $args['container_class'] ) ) {
				$class_name = $args['container_class'] . ' ' . $class_name;
			}
			$args['container_class'] = $class_name;
		}

		return $args;
	}

	private function make_unique_id( $id ) {
		$this->menu_counter++;
		return $id . '-' . $this->menu_counter;
	}

	private function store_menu( $id, $location ) {
		// dedupe
		if ( isset( $this->nav_menus[ $id ] ) ) {
			return;
		}
		$locations = get_nav_menu_locations();
		if ( ! isset( $locations[ $location ] ) ) {
			return;
		}
		$menu = wp_get_nav_menu_object( $locations[ $location ] );
		if ( ! $menu ) {
			return;
		}
		$this->nav_menus[ $id ] = "nav_menu[{$menu->term_id}]";
	}

	private function get_menu_data() {
		$menus = array();
		foreach( $this->nav_menus as $id => $location ) {
			$menus[] = compact( 'id', 'location' );
		}

		return $menus;
	}

	public function admin_enqueue() {
		wp_enqueue_script( 'customizer-dm-admin', plugins_url( 'js/customizer-dm-admin.js', __FILE__ ), array( 'customize-controls' ), '20150914', true );
		wp_enqueue_style( 'customizer-dm-admin', plugins_url( 'css/cdm-admin.css', __FILE__ ) );
	}

	public function preview_enqueue() {
		wp_enqueue_style( 'customizer-dm-preview', plugins_url( 'css/customize-direct-manipulation.css', __FILE__ ), array(), '20150922' );
		wp_enqueue_script( 'customizer-dm-preview', plugins_url( 'js/customizer-dm-preview.js', __FILE__ ), array( 'jquery' ), '20150914', true );
		add_action( 'wp_footer', array( $this, 'add_script_data_in_footer' ) );
		add_filter( 'widget_links_args', array( $this, 'fix_widget_links' ) );
	}

	public function add_script_data_in_footer() {
		wp_localize_script( 'customizer-dm-preview', '_Customizer_DM', array(
			'menus' => $this->get_menu_data(),
			'headerImageSupport' => current_theme_supports( 'custom-header' )
		) );
	}

	public function maybe_add_page_menu_class( $args ) {
		if ( ! is_customize_preview() ) {
			return $args;
		}
		if ( ! ( 'wp_page_menu' === $args['fallback_cb'] && isset( $args['theme_location'] ) ) ) {
			return $args;
		}
		$args['menu_class'] .= " cdm-fallback-menu cdm-menu-location-{$args['theme_location']}";
		return $args;
	}

	/**
	 * The links widget is awesome because it doesn't spit out the widget instance ID, instead punting
	 * to `wp_list_bookmarks()` which assigns IDs based on link categories (and even spits out multiple widgets if
	 * you have more than one link category) which is all completely awesome.
	 *
	 * AWESOME!!!!!!
	 */
	public function fix_widget_links( $args ) {
		foreach( debug_backtrace() as $traced ) {
			if ( isset( $traced['class'] ) && 'WP_Widget_Links' === $traced['class'] && isset( $traced['object']) ) {
				$args['category_before'] = str_replace( 'id="%id"', 'data-id="%id" id="' . $traced['object']->id . '"', $args['category_before'] );
				break;
			}
		}
		return $args;
	}
}

add_action( 'init', array( Jetpack_Customizer_DM::get_instance(), 'init' ) );