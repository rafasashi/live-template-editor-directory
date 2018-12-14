<?php
	
	$ltple = LTPLE_Client::instance();

	echo get_header();

		include($ltple->views . '/navbar.php');
		
		if(isset($_SESSION['message'])){ 
		
			//output message
		
			echo $_SESSION['message'];
			
			//reset message
			
			$_SESSION['message'] ='';
		}
			
		// output directory
			
		echo'<div id="media_library">';

			echo'<div class="col-xs-3 col-sm-2" style="padding:0;">';
			
				echo'<ul class="nav nav-tabs tabs-left">';
					
					echo'<li class="gallery_type_title">' . $post->post_title . '</li>';

				echo'</ul>';
				
				echo'<div style="margin:0 7px;">';
				
					echo $ltple->admin->display_field( array(
			
						'type'				=> 'form',
						'id'				=> 'directory_form',
						'name'				=> 'directory_form',
						'action' 			=> '',
						'method' 			=> 'post',
						'description'		=> ''
						
					), $post, false );	

				echo'</div>';
						
			echo'</div>';

			echo'<div class="col-xs-9 col-sm-10 library-content" style="border-left: 1px solid #ddd;background:#fff;padding-bottom:15px;padding-top:15px;min-height:2100px;">';

				echo'<div class="tab-content">';

					echo '<div id="directory-' . $post->post_name . '">';
					
						echo'<ul class="nav nav-pills" role="tablist">';

							if ( !empty($ltple->directory->list) ){
								
								foreach( $ltple->directory->list as $directory ){
						
									echo'<li role="presentation"' . ( $directory->post_name == $post->post_name ? ' class="active"' : '' ) . '><a href="' . get_permalink( $directory ) . '" role="tab">'.strtoupper(str_replace('-',' ',$directory->post_name)).'</a></li>';
								}
							}
								
						echo'</ul>';
						
						echo'<div class="row">';
						
							//echo'<div class="col-xs-12">';

								echo'<style>
								
									.fixed-table-toolbar {
										
										margin-top: -48px;
										margin-bottom: -6px;
										display: inline-block;
										float: right;
									}
									
									.fixed-table-container {
										
										border:none !important;
									}
									
								</style>';
							
								// get directory
								
								if( $form = get_post_meta($post->ID, 'directory_form', true) ){
							
									// get table fields
									
									$fields = array(
										
										array(
		
											'field' 	=> 'avatar',
											'sortable' 	=> 'false',
											'content' 	=> 'Avatar',
										),
										array(
		
											'field' 	=> 'name',
											'sortable' 	=> 'true',
											'content' 	=> 'Name',
										),										
										array(
		
											'field' 	=> 'description',
											'sortable' 	=> 'true',
											'content' 	=> 'Description',
										),
										array(
		
											'field' 	=> 'url',
											'sortable' 	=> 'true',
											'content' 	=> 'Site',
										)											
									);
								
									// get table of results

									$ltple->api->get_table(
									
										$ltple->urls->api . 'ltple-directory/v1/' . $post->post_name . '?' . http_build_query($_POST, '', '&amp;'), 
										$fields, 
										$trash		= false,
										$export		= false,
										$search		= true,
										$toggle		= false,
										$columns	= false,
										$header		= true,
										$pagination	= true,
										$form		= false,
										$toolbar 	= 'toolbar'
									);	
								}						
							
							//echo'</div>';
						
						echo'</div>';
					
					echo'</div>';
				
				echo'</div>';
				
			echo'</div>';	

		echo'</div>';			

	echo get_footer();