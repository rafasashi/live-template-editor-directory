<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Directory_Settings {

	/**
	 * The single instance of LTPLE_Directory_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		
		$this->parent = $parent;
		
		$this->plugin 		 	= new stdClass();
		$this->plugin->slug  	= 'live-template-editor-directory';
		
		add_action( 'ltple_admin_menu' , array( $this, 'add_menu_items' ) );	
	}
	
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_items () {
		
		//add menu in wordpress dashboard
		
		add_submenu_page(
			'edit.php?post_type=live-editor',
			__( 'Directories', $this->plugin->slug ),
			__( 'Directories', $this->plugin->slug ),
			'edit_pages',
			'edit.php?post_type=directory'
		);
	}
}
