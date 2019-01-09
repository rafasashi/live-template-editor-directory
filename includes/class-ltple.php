<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class LTPLE_Directory {

	/**
	 * The single instance of LTPLE_Directory.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	 
	var $list = null;
	 
	public function __construct ( $file='', $parent, $version = '1.0.0' ) {

		$this->parent = $parent;
	
		$this->_version = $version;
		$this->_token	= md5($file);
		
		$this->message = '';
		
		// Load plugin environment variables
		$this->file 		= $file;
		$this->dir 			= dirname( $this->file );
		$this->views   		= trailingslashit( $this->dir ) . 'views';
		$this->vendor  		= WP_CONTENT_DIR . '/vendor';
		$this->assets_dir 	= trailingslashit( $this->dir ) . 'assets';
		$this->assets_url 	= esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );
		
		//$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$this->script_suffix = '';

		register_activation_hook( $this->file, array( $this, 'install' ) );
		
		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );
		
		$this->settings = new LTPLE_Directory_Settings( $this->parent );
		
		$this->admin = new LTPLE_Directory_Admin_API( $this );
		
		// Handle localisation
		
		$this->load_plugin_textdomain();
		
		add_action( 'init', array( $this, 'load_localisation' ), 0 );		

		// add privacy settings
				
		add_filter('ltple_privacy_settings',array($this,'set_privacy_fields'));
				
		$this->parent->register_post_type( 'directory', __( 'Directories', 'live-template-editor-directory' ), __( 'Directory', 'live-template-editor-directory' ), '', array(

			'public' 				=> true,
			'publicly_queryable' 	=> true,
			'exclude_from_search' 	=> true,
			'show_ui' 				=> true,
			'show_in_menu'		 	=> 'directory',
			'show_in_nav_menus' 	=> true,
			'query_var' 			=> true,
			'can_export' 			=> true,
			'rewrite' 				=> array('slug'=>'directory'),
			'capability_type' 		=> 'post',
			'has_archive' 			=> false,
			'hierarchical' 			=> false,
			'show_in_rest' 			=> true,
			//'supports' 			=> array( 'title', 'editor', 'author', 'excerpt', 'comments', 'thumbnail','page-attributes' ),
			'supports' 				=> array( 'title','page-attributes' ),
			'menu_position' 		=> 5,
			'menu_icon' 			=> 'dashicons-admin-post',
		));

		add_action( 'add_meta_boxes', function(){

			$this->parent->admin->add_meta_box (
			
				'directory_tab',
				__( 'Profile tab name', 'live-template-editor-directory' ), 
				array("directory"),
				'advanced'
			);
			
			$this->parent->admin->add_meta_box (
			
				'directory_default_policy',
				__( 'Default Policy', 'live-template-editor-directory' ), 
				array("directory"),
				'side'
			);
		
			$this->parent->admin->add_meta_box (
			
				'directory_include',
				__( 'Include', 'live-template-editor-directory' ), 
				array("directory"),
				'advanced'
			);
			
			$this->parent->admin->add_meta_box (
			
				'directory_exclude',
				__( 'Exclude', 'live-template-editor-directory' ), 
				array("directory"),
				'advanced'
			);
		
			$this->parent->admin->add_meta_box (
			
				'directory_form',
				__( 'Form', 'live-template-editor-directory' ), 
				array("directory"),
				'advanced'
			);
		});
		
		//init profiler 
		
		add_action( 'init', array( $this, 'directory_init' ));	

		// Custom editor template
		
		add_filter( 'template_include', array( $this, 'directory_template'), 1 );	
		
	} // End __construct ()
	
	public function directory_template( $template_path ){
		
		if( get_post_type() == 'directory' ){
		
			$template_path = $this->views . '/directory.php';
		}
		
		return $template_path;
	}
	
	public function get_directory_list(){
		
		if( is_null($this->list) ){
			
			//get list of directories
			
			$this->list = get_posts(array( 
		
				'post_type' 		=> 'directory',
				'orderby'		 	=> 'menu_order',
				'order'		 		=> 'ASC',
				'posts_per_page'	=> -1
				
			));			
		}
		
		return $this->list;
	}
	
	public function directory_init(){	
		
		if( is_admin() ) {
			
			add_filter('directory_custom_fields', array( $this, 'get_directory_fields' ));
		}
		elseif( $this->list = $this->get_directory_list() ){
			
			//Add Custom API Endpoints
			
			add_action( 'rest_api_init', function(){
				
				foreach( $this->list as $directory ){
				
					register_rest_route( 'ltple-directory/v1', '/' . $directory->post_name . '/', array(
						
						'methods' 	=> 'GET',
						'callback' 	=> array($this,'get_directory_rows'),
					));
				}
			});

			// get current directory profile settings
			
			foreach( $this->list as $directory ){
				
				if( !empty($_GET['tab']) && $_GET['tab'] == $directory->post_name . '-directory' ){
					
					$this->current = $directory;
					
					$this->current->form = get_post_meta($this->current->ID,'directory_form',true);
					
					// save directory data
					
					if( !empty($_POST['submit-directory']) && intval($_POST['submit-directory']) == $this->current->ID ){
						
						foreach( $this->current->form['name'] as $e => $name) {
							
							$field_id = $this->parent->_base . 'dir_' . $this->current->ID . '_' . str_replace(array('-',' '),'_',$name);
							
							if( !empty($name) && !empty($_POST[$field_id]) ){
								
								$value = ( $_POST[$field_id] != '0' ? $_POST[$field_id] : '' );
								
								update_user_meta( $this->parent->user->ID, $field_id, $value );
							}
						}
					}
					
					// get profile form
					
					add_filter('ltple_profile_settings_' . $_GET['tab'], array( $this, 'get_profile_settings_form' ));
					
					break;
				}
			}

			// get profile settings sidebar
			
			add_filter('ltple_profile_settings_sidebar', array( $this, 'get_profile_settings_sidebar' ));
			
			// get profile tabs
			
			add_action('ltple_profile_tabs', array( $this, 'get_profile_tabs' ));			
		}
	}
	
	public function set_privacy_fields(){

		if( $this->list = $this->get_directory_list() ){
			
			foreach( $this->list as $directory ){
				
				$this->parent->profile->privacySettings['directory-' .  $directory->ID] = array(

					'id' 			=> $this->parent->_base . 'policy_' . 'directory-' .  $directory->ID,
					'label'			=> $directory->post_title,
					'description'	=> 'Add me to the ' . $directory->post_title . ' directory',
					'type'			=> 'switch',
					'default'		=> get_post_meta($directory->ID,'directory_default_policy',true),
				);
			}
		}
	}
	
	public function get_directory_fields(){
		
		$fields=[];

		$fields[]=array(
			"metabox" =>
				array('name' 	=> 'directory_tab'),
				'id'			=> 'directory_tab',
				'name'			=> 'directory_tab',
				'description'	=> 'Tab name in profile page',
				'type'			=> 'text',
		);
		
		$fields[]=array(
			"metabox" =>
				array('name' 	=> 'directory_default_policy'),
				'id'			=> 'directory_default_policy',
				'name'			=> 'directory_default_policy',
				'description'	=> 'Default value of privacy policy',
				'type'			=> 'select',
				'options'		=> array('on'=>'on','off'=>'off'),
		);
		
		$fields[]=array(
			"metabox" =>
				array('name' 	=> 'directory_include'),
				'id'			=> 'directory_include',
				'name'			=> 'directory_include',
				'description'	=> '',
				'type'			=> 'checkbox_multi',
				'options' 		=> array( 
				
					'users' 		=> 'Users',
					'sponsorship' 	=> 'Sponsors',
				)
		);

		$fields[]=array(
			"metabox" =>
				array('name' 	=> 'directory_exclude'),
				'id'			=> 'directory_exclude',
				'name'			=> 'directory_exclude',
				'description'	=> '',
				'type'			=> 'checkbox_multi',
				'options' 		=> array( 
				
					'sponsorship' 	=> 'Sponsors',
				)
		);
		
		$fields[]=array(
			"metabox" =>
				array('name' => "directory_form"),
				'id'		=> "directory_form",
				'name'		=> 'directory_form',
				'label'		=> "",
				'type'		=> 'form'
		);

		return $fields;
	}
	
	
	public function get_directory_users($directory) {
	
		$directory_users = array();
				
		if( $include = get_post_meta($directory->ID,'directory_include',true) ){

			// set query arguments
			
			$args = array(
			
				'role__not_in' 	=> array('Administrator'),
				'fields'		=> 'all',
				'number'		=> 1000,
				'orderby'		=> 'meta_value_num',
				'meta_key'		=> $this->parent->_base . 'stars',
				'order'			=> 'DESC',
			);			
			
			$mq = 0;
			
			// filter policy
			
			$directory_policy = get_post_meta($directory->ID,'directory_default_policy',true);
			
			$args['meta_query'][$mq][] = array(

				'key' 		=> $this->parent->_base . 'policy_directory-' . $directory->ID,
				'value' 	=> 'on',
				'compare' 	=> '='							
			);			
			
			if( $directory_policy == 'on' ){
				
				$args['meta_query'][$mq]['relation'] = 'OR';

				$args['meta_query'][$mq][] = array(

					'key' 		=> $this->parent->_base . 'policy_directory-' . $directory->ID,
					'compare' 	=> 'NOT EXISTS'				
				);					
			}
			
			++$mq;
			
			// filter includes
			
			if( !in_array('users',$include) ){
				
				foreach( $include as $inc ){
					
					$args['meta_query'][$mq][] = array(

						'key' 		=> $this->parent->_base . 'user-programs',
						'value' 	=> $inc,
						'compare' 	=> 'LIKE'							
					);
				}
				
				++$mq;
			}

			// filter exclude
			/*
			if( $exclude = get_post_meta($directory->ID,'directory_exclude',true) ){
				
				$args['meta_query'][$mq]['relation'] = 'OR';
				
				$args['meta_query'][$mq][] = array(

					'key' 		=> $this->parent->_base . 'user-programs',
					'compare' 	=> 'NOT EXISTS'							
				);
				
				foreach( $exclude as $ex ){
				
					$args['meta_query'][$mq][] = array(

						'key' 		=> $this->parent->_base . 'user-programs',
						'value' 	=> $ex,
						'compare' 	=> 'NOT LIKE'							
					);
				}
				
				++$mq;
			}
			*/
			
			// filter last seen

			$args['meta_query'][$mq]['relation'] = 'OR';
			
			$args['meta_query'][$mq][] = array(

				'key' 		=> $this->parent->_base . '_last_seen',
				'value' 	=> 0,
				'compare' 	=> '>'							
			);
			
			++$mq;
			
			// filter request
			
			if( !empty($_GET['directory_form']) ){
				
				foreach( $_GET['directory_form'] as $name => $value ){
					
					if( $value != '0' ){
						
						$args['meta_query'][$mq]['relation'] = 'AND';
						
						if( is_array($value) ){
							
							$a = [];

							foreach($value as $v){
								
								$a[] = array(

									'key' 		=> $this->parent->_base . 'dir_' . $directory->ID . '_' . str_replace(array('-',' '),'_',$name),
									'value' 	=> $v,
									'compare' 	=> 'LIKE'							
								);									
							}
							
							$args['meta_query'][$mq][] = $a;								
						}
						else{
							
							$args['meta_query'][$mq][] = array(

								'key' 		=> $this->parent->_base . 'dir_' . $directory->ID . '_' . str_replace(array('-',' '),'_',$name),
								'value' 	=> $value,
								'compare' 	=> '='							
							);								
						}
					}
				}
				
				++$mq;
			}

			$q = new WP_User_Query( $args );		
			
			if( $users = $q->get_results() ){

				foreach($users as $user){
					
					if( $user_meta = get_user_meta($user->ID) ){

						$user->description 	= ( isset($user_meta['description'][0]) ? $user_meta['description'][0] : '' );
						$user->picture 		= $this->parent->image->get_avatar_url($user->ID);
						$user->url 			= ( !empty($user->user_url) ? $user->user_url : '' );
						
						$directory_users[] = $user;
					}
				}
			}
		}

		return $directory_users;
	}	
	
	public function get_directory_rows($request) {

		$directory_rows = [];
		
		$directory_name = explode( '?', $this->parent->urls->current );
		$directory_name = basename($directory_name[0]);
		
		if( $directory = get_page_by_path( $directory_name, OBJECT, 'directory' ) ){
		
			if( $directory_users = $this->get_directory_users($directory) ){
			
				foreach( $directory_users as $user ){
								
					$item = [];
					$item['avatar'] 		= '<img src="' . $user->picture . '" style="width:75px;height:75px;min-width:75px;min-height:75px;" />';
					$item['name'] 			= '<a href="' . $this->parent->urls->profile . $user->ID . '/" target="_blank">' . ucfirst($user->nickname) . '</a>';
					$item['description'] 	= $user->description;
					$item['stars'] 			= get_user_meta($user->ID, $this->parent->_base . 'stars', true );
					$item['url'] 			= ( !empty($user->url) ? '<a target="_blank" href="' . $user->url . '"><span class="glyphicon glyphicon-new-window" aria-hidden="true"></span></a>' : '' );
					
					$directory_rows[] = $item;
				}
			}
		}
		
		return $directory_rows;
	}
	
	public function user_in_diretory($user, $directory_id){
		
		if( !$user_policy = get_user_meta($user->ID, $this->parent->_base . 'policy_directory-' . $directory_id, true ) ){
		
			$user_policy = get_post_meta($directory_id,'directory_default_policy',true);
		}
		
		if( $user_policy == 'on' ){
			
			return true;
		}
		
		return false;
	}
	
	public function get_profile_tabs(){
		
		if( !empty($this->list) ){
		
			foreach( $this->list as $directory ){
				
				if( $this->user_in_diretory($this->parent->profile->user, $directory->ID) ){
					
					// get tab name
					
					$name = ucwords(strtolower($directory->directory_tab));
					
					$slug = sanitize_title($directory->directory_tab);
					
					$this->parent->profile->tabs[$slug]['name'] = $name;
					
					// get tab position
					  
					$this->parent->profile->tabs[$slug]['position'] = 2;
					
					// get tab content
					
					$this->parent->profile->tabs[$slug]['content'] = '<table class="form-table">';
						
						foreach( $directory->directory_form['name'] as $e => $name ){
							
							$input = $directory->directory_form['input'][$e];
							
							if( $input != 'submit' && $input != 'label' && $input != 'title' ){

								$field_id = $this->parent->_base . 'dir_' . $directory->ID . '_' . str_replace(array('-',' '),'_',$name);
					
								$value = get_user_option($field_id,$this->parent->profile->user->ID);
								
								$this->parent->profile->tabs[$slug]['content'] .= '<tr>';
								
									$this->parent->profile->tabs[$slug]['content'] .= '<th style="width:200px;"><label for="'.$name.'">' . ucfirst( str_replace(array('-','_'),' ',$name) ) . '</label></th>';
									
									$this->parent->profile->tabs[$slug]['content'] .= '<td>';
									
										if( is_array($value) ){
											
											if( !empty($value) ){
												
												foreach($value as $v){
													
													$this->parent->profile->tabs[$slug]['content'] .=  ucwords($v);
													$this->parent->profile->tabs[$slug]['content'] .=  '<br/>';
												}
											}
											else{
												
												$this->parent->profile->tabs[$slug]['content'] .=  '-';
											}
										}
										elseif( !empty($value) ){
											
											$this->parent->profile->tabs[$slug]['content'] .=  ucwords($value);
										}
										else{
											
											$this->parent->profile->tabs[$slug]['content'] .=  '-';
										}
									
									$this->parent->profile->tabs[$slug]['content'] .= '</td>';
									
								$this->parent->profile->tabs[$slug]['content'] .= '</tr>';
							}
						}
						
					$this->parent->profile->tabs[$slug]['content'] .= '</table>';
				}
			}
		}
	}
	
	public function get_profile_settings_sidebar(){
		
		if( !empty($this->list) ){
		
			echo'<li class="gallery_type_title">Directory settings</li>';

			$currentTab = ( !empty($_GET['tab']) ? $_GET['tab'] : 'general-info' );	
			
			foreach( $this->list as $directory ){
			
				echo'<li'.( $currentTab == $directory->post_name . '-directory' ? ' class="active"' : '' ).'><a href="'.$this->parent->urls->profile . '?tab=' . $directory->post_name . '-directory">' . ucfirst( $directory->post_title ) . '</a></li>';
			}
		}
	}
	
	public function get_profile_settings_form(){
		
		echo'<div class="tab-pane active" id="custom-profile">';
		
			echo'<form action="' . $this->parent->urls->current . '" method="post" class="tab-content row" style="margin:20px;">';
				
				echo '<input type="hidden" name="submit-directory" value="' . $this->current->ID . '" />';
				
				echo'<div class="col-xs-12 col-sm-6">';
			
					echo'<h3>' . $this->current->post_title . ' Directory</h3>';
					
				echo'</div>';			

				echo'<div class="col-xs-12 col-sm-2 text-right">';
					
					echo'<a target="_blank" class="label label-primary" style="font-size: 13px;" href="'.$this->parent->urls->profile . $this->parent->user->ID . '/">view profile</a>';
					
				echo'</div>';
				
				echo'<div class="col-xs-12 col-sm-2"></div>';
				
				echo'<div class="clearfix"></div>';
			
				echo'<div class="col-xs-12 col-sm-8">';

					echo'<table class="form-table">';

						foreach( $this->current->form['name'] as $e => $name) {
							
							if( !empty($name) && $this->current->form['input'][$e] != 'title' && $this->current->form['input'][$e] != 'label' && $this->current->form['input'][$e] != 'submit' ){
								
								echo'<tr>';
								
									echo'<th><label for="'.$name.'">' . ucfirst( str_replace(array('-','_'),' ',$name) ) . '</label></th>';
									
									echo'<td>';
									
									if( $this->current->form['input'][$e] == 'checkbox' || $this->current->form['input'][$e] == 'select' ){
									
										if( $values = explode(PHP_EOL,$this->current->form['value'][$e]) ){
											
											// get field id
											
											$field_id = $this->parent->_base . 'dir_' . $this->current->ID . '_' . str_replace(array('-',' '),'_',$name);

											// get required
											
											$required = ( ( empty($this->current->form['required'][$e]) || $this->current->form['required'][$e] == 'required' ) ? true : false );
													
											// get options
													
											$options = [];
											
											if( $this->current->form['input'][$e] == 'select' ){
												
												$options[] = '';
											}
									
											foreach( $values as $value ){
												
												$value = trim($value);
												
												if( !empty($value) ){
												
													$options[strtolower($value)] = ucfirst($value);
												}
											}

											// get input
											
											if( $this->current->form['input'][$e] == 'checkbox' ){
									
												echo $this->parent->admin->display_field( array(
										
													'type'				=> 'checkbox_multi',
													'id'				=> $field_id,
													'options' 			=> $options,
													'required' 			=> false,
													'description'		=> '',
													//'style'			=> 'margin:0px 10px;',
													
												), $this->parent->user, false ); 
											}
											else{
												
												echo $this->parent->admin->display_field( array(
										
													'type'				=> 'select',
													'id'				=> $field_id,
													'options' 			=> $options,
													'required' 			=> $required,
													'description'		=> '',
													//'style'			=> 'height:30px;padding:0px 5px;',
													
												), $this->parent->user, false ); 											
											}
										}									
									}								
									else{
										
										$html .= $this->display_field( array(
								
											'type'				=> $this->current->form['input'][$e],
											'id'				=> $field_id,
											'value' 			=> $this->current->form['value'][$e],
											'required' 			=> $required,
											'placeholder' 		=> '',
											'description'		=> ''
											
										), $this->parent->user, false ); 
									}
									
									echo'</td>';
									
								echo'</tr>';
							}
						}
						
					echo'</table>';
					
				echo'</div>';
				
				echo'<div class="clearfix"></div>';
				
				echo'<div class="col-xs-12 col-sm-6"></div>';

				echo'<div class="col-xs-12 col-sm-2 text-right">';
			
					echo'<button class="btn btn-sm btn-primary" style="width:100%;margin-top: 10px;">Update</button>';
					
				echo'</div>';

				echo'<div class="col-xs-12 col-sm-4"></div>';

			echo'</form>';
			
		echo'</div>';		
	}
	
	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new LTPLE_Client_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new LTPLE_Client_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		
		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );
	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {
		
		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-admin' );
	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		
		load_plugin_textdomain( $this->settings->plugin->slug, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_localisation ()

	/**
	 * Load plugin textdomain
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_plugin_textdomain () {
		
	    $domain = $this->settings->plugin->slug;

	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	} // End load_plugin_textdomain ()

	/**
	 * Main LTPLE_Directory Instance
	 *
	 * Ensures only one instance of LTPLE_Directory is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see LTPLE_Directory()
	 * @return Main LTPLE_Directory instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}
