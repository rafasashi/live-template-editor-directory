<?php
/*
 * Plugin Name: Live Template Editor Directory
 * Version: 1.0
 * Plugin URI: https://github.com/rafasashi
 * Description: Another Live Template Editor directory.
 * Author: Rafasashi
 * Author URI: https://github.com/rafasashi
 * Requires at least: 4.6
 * Tested up to: 4.7
 *
 * Text Domain: ltple
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Rafasashi
 * @since 1.0.0
 */
	
	/**
	* Add documentation link
	*
	*/
	
	if ( ! defined( 'ABSPATH' ) ) exit;
	
	/**
	 * Returns the main instance of LTPLE_Directory to prevent the need to use globals.
	 *
	 * @since  1.0.0
	 * @return object LTPLE_Directory
	 */
	function LTPLE_Directory ( $version = '1.0.0' ) {
		
		$instance = LTPLE_Client::instance( __FILE__, $version );
		
		if ( empty( $instance->directory ) ) {
			
			$instance->directory = new stdClass();
			
			$instance->directory = LTPLE_Directory::instance( __FILE__, $instance, $version );
		}

		return $instance;
	}	
	
	add_filter( 'plugins_loaded', function(){

		$dev_ip = '';
		
		if( defined('MASTER_ADMIN_IP') ){
			
			$dev_ip = MASTER_ADMIN_IP;
		}
		
		$mode = ( ($_SERVER['REMOTE_ADDR'] == $dev_ip || ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] == $dev_ip )) ? '-dev' : '');
		
		if( $mode == '-dev' ){
			
			ini_set('display_errors', 1);
		}

		// Load plugin functions
		require_once( 'includes'.$mode.'/functions.php' );	
		
		// Load plugin class files

		require_once( 'includes'.$mode.'/class-ltple.php' );
		require_once( 'includes'.$mode.'/class-ltple-settings.php' );

		// Autoload plugin libraries
		
		$lib = glob( __DIR__ . '/includes'.$mode.'/lib/class-ltple-*.php');
		
		foreach($lib as $file){
			
			require_once( $file );
		}
	
		if( $mode == '-dev' ){
			
			LTPLE_Directory('1.1.1');
		}
		else{
			
			LTPLE_Directory('1.1.0');
		}		
	});