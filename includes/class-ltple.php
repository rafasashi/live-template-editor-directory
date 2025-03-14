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

	var $list 		= null;
	var $per_page 	= 50;
	 
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
				
		add_filter('ltple_privacy_settings',array($this,'set_privacy_fields'),20);
			
		if( is_admin() ){
		
			add_action( 'show_user_profile', array( $this, 'show_user_directories' ),21,1 );
			add_action( 'edit_user_profile', array( $this, 'show_user_directories' ),21,1 );
			
			// save user programs
				
			add_action( 'personal_options_update', array( $this, 'save_user_directories' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_user_directories' ) );
		}
			
		$this->parent->register_post_type( 'directory','Directories','Directory', '', array(

			'public' 				=> true,
			'publicly_queryable' 	=> true,
			'exclude_from_search' 	=> true,
			'show_ui' 				=> true,
			'show_in_menu'		 	=> true,
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
			'menu_position' 		=> 10,
			'menu_icon' 			=> 'dashicons-open-folder',
		));
		
		add_filter('ltple_layer_is_editable', function($is_editable,$post){
			
			if( $post->post_type == 'directory' )
			
				$is_editable = false;
			
			return $is_editable;
			
		},10,2 );
		
		add_action( 'add_meta_boxes', function(){
			
			global $post;
			
			$fields = apply_filters( $post->post_type . '_custom_fields', array(), $post->post_type );
						
			$this->parent->admin->add_meta_boxes($fields);
		});

		//init profiler 
		
		add_action( 'init', array( $this, 'directory_init' ));	

		// Custom editor template
		
		add_filter( 'template_include', array( $this, 'directory_template'), 1 );	
		
        add_action('ltple_show_theme_footer', function($show){
           
           if( $this->parent->inWidget || strpos($this->parent->urls->current,'/directory/') !== false ){
               
                $show = false;
           }
           
            return $show;
           
       },10,1);
       
	} // End __construct ()
	
	public function get_directory_fields(){
		
		$fields=[];

		$fields[]=array(
		
			"metabox" => array(
					
				'name' 		=> 'directory_tab',
				'title' 	=> __( 'Profile tab name', 'live-template-editor-directory' ), 
				'screen'	=> array('directory'),
				'context' 	=> 'advanced',
			),
			'id'			=> 'directory_tab',
			'name'			=> 'directory_tab',
			'description'	=> 'Tab name in profile page',
			'type'			=> 'text',
		);
		
		$metabox = array(
				
			'name' 		=> 'directory_default_values',
			'title' 	=> __( 'Default values', 'live-template-editor-directory' ), 
			'screen'	=> array('directory'),
			'context' 	=> 'side',
		);
		
		$fields[]=array(
		
			'metabox' 		=> $metabox,
			'id'			=> 'directory_default_approval',
			'name'			=> 'directory_default_approval',
			'label'			=> __( 'Directory approval', 'live-template-editor-directory' ), 
			'description'	=> 'Default value for directory approval',
			'type'			=> 'select',
			'options'		=> array('on'=>'on','off'=>'off'),
		);
		
		$fields[]=array(
		
			'metabox' 		=> $metabox,
			'id'			=> 'directory_default_privacy',
			'name'			=> 'directory_default_privacy',
			'label'			=> __( 'User privacy', 'live-template-editor-directory' ), 
			'description'	=> 'Default value for user privacy',
			'type'			=> 'select',
			'options'		=> array('on'=>'on','off'=>'off'),
		);

		$fields[]=array(
		
			"metabox" => array(
			
				'name' 		=> 'directory_form',
				'title' 	=> __( 'Filters', 'live-template-editor-directory' ), 
				'screen'	=> array('directory'),
				'context' 	=> 'advanced',
				
			),
			'id'		=> 'directory_form',
			'name'		=> 'directory_form',
			'label'		=> '',
			'type'		=> 'form'
		);

		return $fields;
	}	

	public function show_user_directories( $user ) {
		
		if( current_user_can( 'administrator' ) ){

			if( $directories = $this->get_directory_list() ){
			
				$form = '';
				
				foreach( $directories as $directory ){
							
					$in_directory = $this->get_user_directory_approval($user, $directory->ID);

					$form .= '<h2>' . $directory->post_title . ' directory</h2>';

					$form .= '<table class="form-table">';
					$form .= '<tbody>';
						
						$form .= '<tr>';
						
							$form .= '<th><label>Approve</label></th>';
							
							$form .= '<td>';
								
								$form .=  $this->parent->admin->display_field( array(
						
									'type'				=> 'switch',
									'id'				=> $this->parent->_base . 'in_directory-' . $directory->ID,
									'data' 				=> $in_directory,
									'placeholder' 		=> '',
									'description'		=> '',
										
								), $user, false );
								
							$form .= '</td>';
							
						$form .= '</tr>';
						
					$form .= '</tbody>';
					$form .= '</table>';						
					
					if( $in_directory == 'on' ){
						
						$form .= $this->get_user_directory_form($user,$directory->ID);			
					}
				}
				
				if( !empty($form) ){
					
					echo $form;
				}
			}
		}	
	}

	public function save_user_directories( $user_id ) {
		
		if( $directories = $this->get_directory_list() ){
			
			foreach( $directories as $directory ){
				
				// directory approval
				
				$approval = 'off';
				
				if( !empty($_POST[$this->parent->_base . 'in_directory-'.$directory->ID]) && $_POST[$this->parent->_base . 'in_directory-'.$directory->ID] == 'on' ){
					
					$approval = 'on';
				}
				
				update_user_meta( $user_id, $this->parent->_base . 'in_directory-' . $directory->ID, $approval );
				
				// profile privacy
				
				$policy = 'off';				
				
				if( $approval == 'on' && !empty($_POST[$this->parent->_base . 'policy_directory-'.$directory->ID]) && $_POST[$this->parent->_base . 'policy_directory-'.$directory->ID] == 'on' ){
						
					$policy = 'on';
				}
				
				update_user_meta( $user_id, $this->parent->_base . 'policy_directory-' .  $directory->ID, $policy );
				
				// directory fields
				
				$this->save_user_directory_fields($user_id,$directory->ID,$_POST);
			}
		}
	}	
	
	public function save_user_directory_fields($user,$id,$fields){
		
		if( is_numeric($user) ){
		
			$user = get_user_by('id',$user);
		}
		
		if( !empty($user->ID) ){
			
			// policy setting
			
			$status = 'off';
			
			if( !empty($_POST[$this->parent->_base . 'policy_directory-'.$id]) && $_POST[$this->parent->_base . 'policy_directory-'.$id] == 'on' ){
				
				$status = 'on';
			}
			
			update_user_meta($user->ID,$this->parent->_base .'policy_directory-'. $id,$status);
			
			// form settings
			
			if( $data = $this->get_directory_form_data($id,$user) ){
				
				foreach( $data['name'] as $e => $name) {
					
					$field_id = $this->parent->_base . 'dir_' . $id . '_' . str_replace(array('-',' '),'_',$name);
					
					if( !empty($name) && !empty($fields[$field_id]) ){
						
						$value = ( $fields[$field_id] != '0' ? $fields[$field_id] : '' );
						
						update_user_meta( $user->ID, $field_id, $value );
					}
				}
			}
		}
	}
	
	public function directory_template( $template_path ){
		
		if( get_post_type() == 'directory' ){
			
			add_filter('ltple_css_framework',function($framework){
				
				return 'bootstrap-3';
				
			},9999999999,1);
			
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

			foreach( $this->list as $directory ){
				
				$directory->icon = 'fa fa-map-marker-alt';
				
				$directory->tab = get_post_meta($directory->ID,'directory_tab',true);
			}
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
				
				$tab = !empty($_GET['tab']) ? sanitize_title($_GET['tab']) : false;
				
				if( $tab == $directory->post_name . '-directory' ){
					
					$this->current = $directory;

					// save directory data
					
					if( !empty($_POST['submit-directory']) && intval($_POST['submit-directory']) == $this->current->ID ){
						
						$this->save_user_directory_fields($this->parent->user,$this->current->ID,$_POST);
					}
					
					// get profile form
					
					add_filter('ltple_profile_settings_' . $tab, array( $this, 'get_profile_settings_form' ));
					
					break;
				}
			}

			// get profile settings sidebar
			
			add_filter('ltple_profile_settings_sidebar', array( $this, 'get_sidebar' ),11,3);
			
			// get profile tabs
			
			//add_action('ltple_profile_tabs', array( $this, 'get_profile_tabs' ),10,1);			
			
			add_action('ltple_profile_about_description', array( $this, 'add_profile_description' ),10,1);
		}
	}
	
	public function set_privacy_fields(){

		if( $this->list = $this->get_directory_list() ){
			
			foreach( $this->list as $directory ){
				
				if( $this->get_user_directory_approval($this->parent->user, $directory->ID) == 'on' ){
				
					$this->parent->profile->privacySettings['directory-' .  $directory->ID] = array(

						'id' 			=> $this->parent->_base . 'policy_directory-' .  $directory->ID,
						'label'			=> $directory->post_title,
						'description'	=> 'Add me to the ' . $directory->post_title . ' directory',
						'type'			=> 'switch',
						'default'		=> $this->get_default_directory_privacy($directory->ID),
					);
				}
				else{
					
					$this->parent->profile->privacySettings['directory-' .  $directory->ID] = array(

						'id' 			=> $this->parent->_base . 'policy_directory-' .  $directory->ID,
						'label'			=> $directory->post_title,
						'type'			=> 'message',
						'value'			=> '<a class="btn btn-xs btn-success" style="margin-bottom:10px;padding:5px 10px;" href="' . $this->parent->urls->primary . '/contact/" target="_blank">Request</a>',
						'class'			=> 'directory-request',
						'description'	=> 'Contact us to be added to this directory',
						'style'			=> 'padding:0;',
					);
				}
			}
		}
	}
	
	public function get_directory_users($directory) {
	
		$directory_users = array();
				
		// set query arguments
		
		$args = array(
		
			'fields'		=> 'all',
			'number'		=> $this->per_page,
			'paged'			=> ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : ( !empty($_GET['page']) ? intval($_GET['page']) : 1 ),
		);
		
		if( $this->parent->settings->is_enabled('ranking') ){

			$args['orderby'] 	= 'meta_value_num';
			$args['meta_key'] 	= $this->parent->_base . 'stars';
			$args['order'] 		= 'DESC';
		}
		
		// search filter
		
		if( !empty($_GET['s']) ){
			
			$args['search'] = '*' . $_GET['s'] . '*';
			//$args['search_columns'] = array( 'user_nicename' );
		}

		$mq = 0;
		
		// filter privacy policy
		
		$args['meta_query'][$mq][] = array(

			'key' 		=> $this->parent->_base . 'policy_about-me',
			'value' 	=> 'off',
			'compare' 	=> '!='							
		);

		++$mq;
		
		// filter directory policy

		$args['meta_query'][$mq][] = array(

			'key' 		=> $this->parent->_base . 'policy_directory-' . $directory->ID,
			'value' 	=> 'off',
			'compare' 	=> '!='							
		);
		
		++$mq;
		
		// filter approval
		
		// TODO optimize query avoiding OR relation
		
		$directory_approval = get_post_meta($directory->ID,'directory_default_approval',true);
		
		$args['meta_query'][$mq]['relation'] = 'OR';
		
		$args['meta_query'][$mq][] = array(

			'key' 		=> $this->parent->_base . 'in_directory-' . $directory->ID,
			'value' 	=> 'on',
			'compare' 	=> '=',
		);		
		
		if( $directory_approval != 'off' ){
			
			$args['meta_query'][$mq][] = array(

				'key' 		=> $this->parent->_base . 'in_directory-' . $directory->ID,
				'compare' 	=> 'NOT EXISTS'				
			);				
		}
		
		++$mq;
		
		// filter last seen
		
		$args['meta_query'][$mq][] = array(

			'key' 		=> $this->parent->_base . '_last_seen',
			'value' 	=> 0,
			'compare' 	=> '>'							
		);
		
		++$mq;
		
		// filter request
		
		if( !empty($_GET['filter']) ){
			
			parse_str($_GET['filter'],$filter);
			
			foreach( $filter['directory_form'] as $name => $value ){
				
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

					$user->description 	= ( isset($user_meta['description'][0]) ? wp_trim_words($user_meta['description'][0],15) : '' );
					$user->avatar 		= $this->parent->image->get_avatar_url($user->ID);
					$user->banner 		= $this->parent->image->get_banner_url($user->ID);
					$user->profile 		= $this->parent->urls->profile . $user->ID . '/';
					$user->url 			= ( !empty($user->user_url) ? $user->user_url : '' );
					
					$directory_users[] = $user;
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
					
					$item ='<div class="" id="user-' . $user->ID . '">';
						
						$item.='<div style="position:relative;" class="panel panel-default">';
							
                            $item.='<a href="' . $user->profile . '">';
									
                                $item.='<div class="banner-overlay" style="width:100%;height:165px;position:absolute;background-image:linear-gradient(to bottom right,#284d6b,' . $this->parent->settings->mainColor . ');opacity:.4;"></div>';
							
                                $item.='<div class="thumb_wrapper" style="background:url(' . $user->banner . ');background-size:cover;background-repeat:no-repeat;background-position:center center;"></div>'; //thumb_wrapper					
							
                            $item.='</a>';
                            
							$item.='<div class="panel-body" style="padding-bottom:0;position:relative;">';
								
								$item.='<a class="product-logo" href="' . $user->profile . '" style="position:absolute;top:-25px;">';
									
									$item.='<img src="' . $user->avatar . '" style="height:45px;width:45px;border: 5px solid #fff;background:#fff;border-radius:250px;">';
									
								$item.='</a>';
								
								$item.='<div class="gallery-item" style="margin-top:10px;line-height:25px;height:30px;overflow:hidden;font-size:15px;font-weight:bold;">';
									
									$item.='<b>' . ucfirst($user->nickname) . '</b>';
								
								$item.='</div>';

								$item.='<div style="font-size: 11px;"></div>';
								 
							$item.='</div>';
							
							$item.='<div class="panel-footer" style="padding:0;margin-top:15px;">';
								
                                $item.='<div class="btn-group btn-group-justified">';
                                
                                    // about button
								
                                    $item.='<a class="btn" href="'. $user->profile . '" title="More info about ' . ucfirst($user->nickname) . '">About</a>';
                                
                                $item.='</div>';
                                
							$item.='</div>';
						
						$item.='</div>';
						
					$item.='</div>';

					$directory_rows[]['item'] = $item;
				}
			}
		}
		
		return $directory_rows;
	}
	
	public function get_default_directory_approval($directory_id){
		
		$default_value = get_post_meta($directory_id,'directory_default_approval',true);
		
		if( empty($default_value) ){
			
			$default_value = 'on';
		}
		
		return $default_value;
	}
	
	public function get_default_directory_privacy($directory_id){
		
		$default_value = get_post_meta($directory_id,'directory_default_privacy',true);
		
		if( empty($default_value) ){
			
			$default_value = 'on';
		}
		
		return $default_value;
	}
	
	public function get_user_directory_approval($user,$directory_id){

		$in_directory = get_user_meta( $user->ID, $this->parent->_base . 'in_directory-' . $directory_id, true );
		
		if( empty($in_directory) ){
			
			$in_directory = $this->get_default_directory_approval($directory_id);
		}

		return $in_directory;
	}
	
	public function user_in_directory($user, $directory_id){
		
		// get user policy
		
		if( !$user_policy = get_user_meta($user->ID, $this->parent->_base . 'policy_directory-' . $directory_id, true ) ){
		
			$user_policy = $this->get_default_directory_privacy($directory_id);
		}
		
		// get user approval

		$in_directory = $this->get_user_directory_approval($user,$directory_id);
		
		// is user in directory
		
		if( $user_policy == 'on' && $in_directory == 'on' ){
			
			return true;
		}
		
		return false;
	}
	
	/*
	public function get_profile_tabs($tabs){
		
		if( !empty($this->list) ){
		
			foreach( $this->list as $directory ){
				
				if( $this->user_in_directory($this->parent->profile->user, $directory->ID) ){
					
					$tab = array();
					
					$has_values = false;
					
					// get tab name
					
					$name = ucwords(strtolower($directory->tab));
					
					$slug = sanitize_title($directory->tab);
					
					$tab['name'] = $name;
					
					// get tab position
					  
					$tab['position'] = 2;
						
					// get tab content
					
					$tab['content'] = '<div class="col-xs-12 col-sm-7">';
						
						$tab['content'] .= '<table class="form-table">';
							
							foreach( $directory->directory_form['name'] as $e => $name ){
								
								$input = $directory->directory_form['input'][$e];
								
								if( $input != 'submit' && $input != 'label' && $input != 'title' ){

									$field_id = $this->parent->_base . 'dir_' . $directory->ID . '_' . str_replace(array('-',' '),'_',$name);
						
									$value = get_user_option($field_id,$this->parent->profile->user->ID);
									
									$tab['content'] .= '<tr>';
									
										$tab['content'] .= '<th style="width:200px;"><label for="'.$name.'">' . ucfirst( str_replace(array('-','_'),' ',$name) ) . '</label></th>';
										
										$tab['content'] .= '<td>';
										
											if( is_array($value) ){
												
												if( !empty($value) ){
													
													$has_values = true;
													
													foreach($value as $v){
														
														$tab['content'] .=  ucwords($v);
														$tab['content'] .=  '<br/>';
													}
												}
												else{
													
													$tab['content'] .=  '-';
												}
											}
											elseif( !empty($value) ){
												
												$has_values = true;
												
												$tab['content'] .=  ucwords($value);
											}
											else{
												
												$tab['content'] .=  '-';
											}
										
										$tab['content'] .= '</td>';
										
									$tab['content'] .= '</tr>';
								}
							}
							
						$tab['content'] .= '</table>';
						
					$tab['content'] .= '</div>';
					
					if($has_values){
						
						if( $this->parent->profile->tab == $slug ){
							
							add_action( 'wp_enqueue_scripts',function(){

								wp_register_style( $this->parent->_token . $this->parent->profile->tab, false, array());
								wp_enqueue_style( $this->parent->_token . $this->parent->profile->tab );
							
								wp_add_inline_style( $this->parent->_token . $this->parent->profile->tab, '

									#' . $this->parent->profile->tab . ' {
										
										margin-top:20px;
									}
									
								');

							},10 );								
						}
						
						$tabs[$slug] = $tab;
					}
				}
			}
		}
		
		return $tabs;
	}
	*/
	
	public function add_profile_description($description){
		
		if( !empty($this->list) ){
		
			foreach( $this->list as $directory ){
				
				if( $this->user_in_directory($this->parent->profile->user, $directory->ID) ){
					
					// get tab name
					
					$title = ucwords(strtolower($directory->tab));
					
					// get tab content

					$content = '';
					
					foreach( $directory->directory_form['name'] as $e => $name ){
						
						$input = $directory->directory_form['input'][$e];
						
						if( $input != 'submit' && $input != 'label' && $input != 'title' ){

							$field_id = $this->parent->_base . 'dir_' . $directory->ID . '_' . str_replace(array('-',' '),'_',$name);
				
							$values = get_user_option($field_id,$this->parent->profile->user->ID);
								
							$value = '';
						
							if( is_array($values) ){
								
								if( !empty($values) ){
									
									foreach($values as $v){
										
										$value .=  ucwords($v);
										$value .=  '<br/>';
									}
								}
							}
							elseif( !empty($values) ){
								
								$value .=  ucwords($values);
							}
							
							if( !empty($value) ){
									
								$content .= '<tr>';
								
									$content .= '<th style="width:200px;"><label for="'.$name.'">' . ucfirst( str_replace(array('-','_'),' ',$name) ) . '</label></th>';
									
									$content .= '<td>';

										$content .= $value;
									
									$content .= '</td>';
									
								$content .= '</tr>';
							}
						}
					}
					
					if( !empty($content) ){
					
						$description .= '<h5>' . $title . '</h5>';

						$description .= '<div class="table-responsive">';
							
							$description .= '<table class="table">';
								
								$description .= $content;
								
							$description .= '</table>';
							
						$description .= '</div>';
					}
				}
			}
		}
		
		return $description;
	}
	
	public function get_sidebar($sidebar,$currentTab){
		
		if( !empty($this->list) ){

			foreach( $this->list as $directory ){
				
				if( $this->user_in_directory($this->parent->user, $directory->ID) ){
				
					$sidebar .= '<li'.( $currentTab == $directory->post_name . '-directory' ? ' class="active"' : '' ).'><a href="'.$this->parent->urls->profile . '?tab=' . $directory->post_name . '-directory"><span class="' . $directory->icon . '"></span> ' . ucfirst( $directory->tab ) . '</a></li>';
				}
			}
		}
		
		return $sidebar;
	}
	
	public function get_directory_form_data($id){
		
		if( !isset($this->forms[$id]) ){
			
			$this->forms[$id] = get_post_meta($id,'directory_form',true);
		}
		
		return $this->forms[$id];
	}
	
	public function get_profile_settings_form(){
		
		$in_directory = $this->get_user_directory_approval($this->parent->user, $this->current->ID);
		
		if( $in_directory == 'on' ){
			
			echo'<div class="tab-pane active" id="custom-profile">';
			
				echo'<form action="' . $this->parent->urls->current . '" method="post" class="tab-content row" style="margin:5px;">';
					
					echo '<input type="hidden" name="submit-directory" value="' . $this->current->ID . '" />';
					
					echo'<div class="col-xs-12 col-sm-6">';
				
						echo'<h3>' . $this->current->post_title . ' Directory</h3>';
						
					echo'</div>';			
					
					echo'<div class="col-xs-12 col-sm-2 text-right" style="padding-top:10px;">';
						
						//echo'<a target="_blank" class="label label-primary" style="font-size: 13px;" href="'.$this->parent->urls->profile . $this->parent->user->ID . '/">view profile</a>';
						
						echo '<label style="padding-right:10px;height:35px;float:left;">Show / Hide</label>';
						
						echo $this->parent->admin->display_field( array(
				
							'type'			=> 'switch',
							'id'			=> $this->parent->_base . 'policy_directory-' . $this->current->ID,
							'data' 			=> $this->user_in_directory($this->parent->user, $this->current->ID),
							'placeholder' 	=> '',
							'description'	=> '',
								
						),false,false);
							
					echo'</div>';
					
					echo'<div class="col-xs-12 col-sm-2"></div>';
					
					echo'<div class="clearfix"></div>';
				
					echo'<div class="col-xs-12 col-sm-8">';
							
						if( $in_directory == 'on' ){
						
							echo $this->get_user_directory_form($this->parent->user,$this->current->ID);
						}
						
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
		else{
			
			echo '<div class="alert alert-warning">To add your profile to this directory please contact us.</div>';
		}
	}
	
	public function get_user_directory_form($user,$id){
		
		$form = '';
		
		if( $data = $this->get_directory_form_data($id,$user) ){

			$form .= '<table class="form-table">';

				foreach( $data['name'] as $e => $name) {
					
					if( !empty($name) && $data['input'][$e] != 'title' && $data['input'][$e] != 'label' && $data['input'][$e] != 'submit' ){
									
						// get field id
						
						$field_id = $this->parent->_base . 'dir_' . $id . '_' . str_replace(array('-',' '),'_',$name);

						// get required
						
						$required = ( ( empty($data['required'][$e]) || $data['required'][$e] == 'required' ) ? true : false );
								
						$form .= '<tr>';
						
							$form .= '<th><label for="'.$name.'">' . ucfirst( str_replace(array('-','_'),' ',$name) ) . '</label></th>';
							
							$form .= '<td>';
							
							if( $data['input'][$e] == 'checkbox' || $data['input'][$e] == 'select' ){
							
								if( $values = explode(PHP_EOL,$data['value'][$e]) ){

									// get options
											
									$options = [];
									
									if( $data['input'][$e] == 'select' ){
										
										$options[] = '';
									}
							
									foreach( $values as $value ){
										
										$value = trim($value);
										
										if( !empty($value) ){
										
											$options[strtolower($value)] = ucfirst($value);
										}
									}

									// get input
									
									if( $data['input'][$e] == 'checkbox' ){
							
										$form .=  $this->parent->admin->display_field( array(
								
											'type'				=> 'checkbox_multi',
											'id'				=> $field_id,
											'options' 			=> $options,
											'required' 			=> false,
											'description'		=> '',
											//'style'			=> 'margin:0px 10px;',
											
										), $user, false ); 
									}
									else{
										
										$form .=  $this->parent->admin->display_field( array(
								
											'type'				=> 'select',
											'id'				=> $field_id,
											'options' 			=> $options,
											'required' 			=> $required,
											'description'		=> '',
											//'style'			=> 'height:30px;padding:0px 5px;',
											
										), $user, false ); 											
									}
								}									
							}								
							else{
								
								$form .=  $this->display_field( array(
						
									'type'				=> $data['input'][$e],
									'id'				=> $field_id,
									'value' 			=> $data['value'][$e],
									'required' 			=> $required,
									'placeholder' 		=> '',
									'description'		=> ''
									
								), $user, false ); 
							}
							
							$form .= '</td>';
							
						$form .= '</tr>';
					}
				}
				
			$form .= '</table>';
		}
		
		return $form;
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
		
		//wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-frontend' );
	
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {
		
		//wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		//wp_enqueue_script( $this->_token . '-frontend' );
	
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {
		
		//wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array(), $this->_version );
		//wp_enqueue_style( $this->_token . '-admin' );
	
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
