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
			
		echo'<div id="media_library" class="wrapper">';

			echo '<div id="sidebar">';
			
				echo'<div class="gallery_type_title gallery_head">' . __('Directory','live-template-editor-directory') . '</div>';

				echo'<div id="gallery_sidebar" style="padding:0 4px 0 9px;">';
					
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

			echo'<div id="content" class="library-content" style="border-left: 1px solid #ddd;background:#fbfbfb;">';

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
								
									table {
								
										font-size:15px;
										
									}
									
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

											'field' 	=> 'item',
											'sortable' 	=> 'false',
											'content' 	=> '',
										),					
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
										$pagination	= 'scroll',
										$form		= false,
										$toolbar 	= 'toolbar',
										$card		= true,
										$itemHeight	= 320, 
										$fixedHeight= true, 
										$echo		= true,
										$pageSize	= $ltple->directory->per_page
									);	
								}						
							
							//echo'</div>';
						
						echo'</div>';
					
					echo'</div>';
				
				echo'</div>';
				
			echo'</div>';	

		echo'</div>';			

	echo get_footer();