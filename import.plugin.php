<?php

	class Import extends Plugin implements Importer {
		
		private $import_name = 'BlogML XML File';
		
		// a list of default options we should ignore when performing an import
		private $ignore_options = array(
			'base_url',			// may have changed, will be defined in the new instance's install instead
			'cron_running',		// internal
			'db_version',		// internal
			'next_cron',		// internal
			'GUID',				// will have changed, will be defined in the new instance's install instead
			'private-GUID',
			'public-GUID',
			'installed',		// internal
			'active_plugins',	// this will likely include a number of plugins you don't already have and will deactivate the importer - bad!
		);
		
		// store the tags, posts, and users we're importing so we can convert old IDs to new IDs
		private $tags = array();
		private $posts = array();
		private $users = array();
		
		public function filter_import_names ( $import_names ) {
			
			$import_names[] = _t( $this->import_name );
			
			return $import_names;
			
		}
		
		public function filter_import_stage ( $output, $import_name, $stage, $step ) {
			
			// only run if we're the importer being used
			if ( _t( $this->import_name ) != $import_name ) {
				// must return $output as it could contain the output of another plugin!
				return $output;
			}
						
			// inputs we'll hand to our stage methods
			$inputs = array();
			
			// figure out if we're ready to increase the stage
			switch ( $stage ) {
				
				case 1:
					
					if ( isset( $_FILES['import_file'] ) ) {
						
						if ( !is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
							$inputs['warning'] = _t( 'Upload failed!' );
						}
						else {
							
							// make sure we can even parse the xml before we try to save it
							if ( $xml = $this->load_xml( $_FILES['import_file']['tmp_name'] ) ) {
								
								// make sure we can save the file
								if ( $filename = $this->save_file( $_FILES['import_file']['tmp_name'] ) ) {
									$inputs['filename'] = $filename;
									$inputs['xml'] = $xml;
									$stage = 2;
								}
								else {
									$inputs['warning'] = _t( 'Unable to store XML file for parsing.' );
								}
								
							}
							else {
								$inputs['warning'] = _t( 'Unable to parse XML file.' );
							}
							
						}
						
					}
					
					break;
					
				case 2:
					
					
					
					break;
				
			}
			
			
			// now execute the right stage
			switch ( $stage ) {
				default:
				case 1:
					$output = $this->stage1( $inputs );
					break;
					
				case 2:
					$output = $this->stage2( $inputs );
					break;
			}
			
			return $output;
			
			
		}
		
		private function stage1 ( $inputs ) {
			
			if ( isset( $inputs['warning'] ) ) {
				$warning = '<p class="warning">' . $inputs['warning'] . '</p>';
			}
			else {
				$warning = '';
			}
			
			$output = $warning;
			$output .= '<label for="import_file">' . _t( 'BlogML File' ) . '</label>';
			$output .= '<input type="file" id="import_file" name="import_file" />';
			$output .= '<input type="hidden" name="stage" value="1" />';
			$output .= '<input type="submit" value="' . _t( 'Import' ) . '" />';
			
			return $output;
			
		}
		
		private function stage2 ( $inputs ) {
			
			if ( isset( $inputs['warning'] ) ) {
				$warning = '<p class="warning">' . $inputs['warning'] . '</p>';
			}
			else {
				$warning = '';
			}
			
			$output = $warning;
			$output .= _t( 'Loading file %s', array( $inputs['filename'] ) );
			
			$output .= $this->import_authors( $inputs['xml'] );
			$output .= $this->import_options( $inputs['xml'] );
			$output .= $this->import_tags( $inputs['xml'] );
			$output .= $this->import_posts( $inputs['xml'] );
			
			return $output;
			
		}
		
		/**
		 * Alter the enctype of the importer's <form> for the steps that deal with file uploading.
		 * 
		 * @param string $enctype The current form enctype.
		 * @param string $import_name The name of the importer currently running.
		 * @param string $stage The stage we're currently on (or '' for the first one).
		 */
		public function filter_import_form_enctype ( $enctype, $import_name, $stage ) {
			
			// only run if we're the importer being used
			if ( _t( $this->import_name ) != $import_name ) {
				// must return $enctype!
				return $enctype;
			}
			
			// if the stage is not set (the very first stage is not set) or is 1
			if ( $stage == null || $stage == 1 ) {
				return 'multipart/form-data';
			}
			else {
				return $enctype;
			}
			
		}
		
		/**
		 * Attempt to load the contents of a file and parse it as an XML object.
		 * 
		 * @param string $filename The complete local filename of the file to attempt to load.
		 * @return mixed A SimpleXMLElement on successful parsing, boolean false on failure.
		 */
		private function load_xml ( $filename ) {
			
			$xml_data = file_get_contents( $filename );
			
			try {
				$xml = new SimpleXMLElement($xml_data);
				return $xml;
			}
			catch ( Exception $e ) {
				// log the reason we were unable to parse the XML file
				EventLog::log( _t('Unable to parse XML file. See detail for error message.', 'err', 'import', 'BlogML', array( $e->getMessage(), $xml_data ) ) );
				return false;
			}
			
		}
		
		/**
		 * Attempt to load the contents of a file and parse it as an XML object.
		 * 
		 * @param string $filename The complete local filename of the file to attempt to load.
		 * @return mixed A DOMDocument on successful parsing, boolean false on failure.
		 */
		private function load_xml_dom ( $filename ) {
			
			$xml_data = file_get_contents( $filename );
			
			try {
				$dom = new SimpleXMLElement($xml_data);
				return $dom;
			}
			catch ( Exception $e ) {
				// log the reason we were unable to parse the XML file
				EventLog::log( _t('Unable to parse XML file. See detail for error message.', 'err', 'import', 'BlogML', array( $e->getMessage(), $xml_data ) ) );
				return false;
			}
			
		}
		
		/**
		 * Save the temporary uploaded file to a permanent temporary file.
		 * 
		 * @param string $filename The name of the temporary upload file.
		 */
		private function save_file ( $filename ) {
			
			$name = tempnam( sys_get_temp_dir(), 'habari-' );
			
			$move = move_uploaded_file( $filename, $name );
			
			if ( $move ) {
				return $name;
			}
			else {
				return false;
			}
			
		}
		
		private function import_authors ( $xml ) {
			
			$output = '<h3>' . _t( 'Users' ) . '</h3>';
			
			// save the number of users we end up creating
			$user_count = 0;
			
			$output .= '<ul>';
			foreach ( $xml->authors->author as $author ) {
				
				// pull out all our values and cast them
				$id = intval( $author['id'] );
				$approved = (boolean)$author['approved'];
				$email = strval( $author['email'] );
				$username = strval( $author->title );
				
				
				// see if a user with this username already exists
				$user = User::get_by_name( $username );
				
				if ( $user !== false ) {
					// if the user exists, save their old ID into an info attribute
					$user->info->old_id = $id;
					// and update
					$user->info->commit();
					
					// store the old ID so we can reference it for posts later on
					$this->users[ $id ] = $user->id;
					
					$output .= '<li>' . _t('Associated imported user %1$s with existing user %2$s', array( $username, $user->username ) ) . '</li>';
					
					EventLog::log( _t('Associated imported user %1$s with existing user %1$s.', array( $username, $user->username )), 'info', 'import', 'BlogML' );
					
				}
				else {
					
					// we must create a new user
					try {
						
						$u = User::create( array(
							'username' => $username,
							'email' => $email,
						) );
						
						$u->info->old_id = $id;
						$u->info->commit();
						
						// store the old ID so we can reference it for posts later on
						$this->users[ $id ] = $u->id;

						$output .= '<li>' . _t('Created new user %1$s. Their old ID was %2$d.', array( $u->username, $id )) . '</li>';
						EventLog::log( _t('Created new user %1$s. Their old ID was %2$d.', array( $u->username, $id )), 'info', 'import', 'BlogML' );
						
						$user_count++;
						
					}
					catch ( Exception $e ) {
						
						// no idea why we might error out, but catch it if we do
						EventLog::log( $e->getMessage(), 'err', 'import', 'BlogML', array( $author->asXML(), $e ) );
						Session::error( _t( 'There was an error importing user %1$s. See the EventLog for details.', array( $username ) ) );
						
					}
					
				}
				
			}	// foreach author
			$output .= '</ul>';
			return $output;
			
		}
		
		private function import_options ( $xml ) {
			
			$output = '<h3>' . _t( 'Options' ) . '</h3>';
			
			$output .= '<ul>';
			foreach ( $xml->{'extended-properties'}->property as $property ) {
				
				
				// get the attributes we need and cast them as strings
				$name = (string) $property->attributes()->name;
				$value = (string) $property->attributes()->value;
				
				if ( in_array( $name, $this->ignore_options ) ) {
					$output .= '<li>' . _t('Skipping option %s', array($name) ) . '</li>';
					continue;
				}
				
				$output .= '<li>' . _t('Importing option %s', array($name) ) . '</li>';
				
				// try to unserialize the option value
				
				// unserialize() can return false on error or if it's the actual serialized value
				// so make sure that the value is not a serialized 'false'
				if ( $value === serialize(false) ) {
					// just unserialize it
					$value = false;
				}
				else {
					
					// @ to hide any errors when unserializing
					$t_value = @unserialize( $value );
					
					// if $t_value is false, that means it's an error (we checked for boolean value false earlier)
					if ( $t_value === false ) {
						// nothing - use $value as is
					}
					else {
						// we unserialized successfully, use $t_value
						$value = $t_value;
					}
					
				}
				
				// set the option
				Options::set( $name, $value );
				
				// log a debug message
				EventLog::log( _t('Imported option %s', array( $name )), 'debug', 'import', 'BlogML', array( $name, $value ) );
				
			}
			$output .= '</ul>';
			
			return $output;
			
		}
		
		private function import_tags ( $xml ) {
			
			$output = '<h3>' . _t( 'Tags' ) . '</h3>';
			
			$output .= '<ul>';
			foreach ( $xml->categories->category as $category ) {
				
				// get the properties and cast them
				$id = intval( $category->attributes()->id );
				$term = strval( $category->title );
				$term_display = strval( $category->attributes()->description );
				
				// try to get the tag
				$tag = Tags::get_by_text( $term );
				
				if ( $tag == false ) {
					// create the tag, it didn't exist - assumes text passed in is the display version, so that's fine
					$tag = Tag::create( array(
						'term' => $term,
						'term_display' => $term_display,
					) );
					$output .= '<li>' . _t('Created tag %s.', array( $term ) ) . '</li>';
				}
				else {
					$output .= '<li>' . _t('Found existing tag %s.', array( $term ) ) . '</li>';
				}
				
				// save the old ID => new ID so we can reference it properly for posts
				$this->tags[ $id ] = $tag->id;
				
			}
			$output .= '</ul>';
			
			return $output;
			
		}
		
		private function import_posts ( $xml ) {
			
			$in_db = DB::get_column( 'select value from {postinfo} where name = :name', array( ':name' => 'old_id' ) );

			$output = '<h3>' . _t( 'Posts' ) . '</h3>';
			
			$output .= '<ul>';
			foreach ( $xml->posts->post as $post ) {
				
				// get our values and cast them
				$id = intval( $post['id'] );
				$date_created = HabariDateTime::date_create( $post['date-created'] );
				$date_modified = HabariDateTime::date_create( $post['date-modified'] );
				$approved = (boolean)$post['approved'];
				$slug = ltrim( $post['post-url'], '/' );	// it's a relative path, we want to trim the / before it so it's just a slug
				$type = trim( strval( $post['type'] ) );
				if( $type == 'normal') {
					$type = 'entry';
				}
				else {
					$type = 'page';
				}
				
				$title = strval( $post->title );
				$content = strval( $post->content );
				// we could also use this as the slug. i'm not sure what it's really for, though
				// $slug = strval( $post->{'post-name'} );
				
				// get the first author, we only support one
				$author_id = intval( $post->authors->author[0]->attributes()->ref );

				if ( in_array( $id, $in_db ) ) {
					// Skip the post if we already have it
					$output .= '<li>' . _t('Found existing post %s.', array( $slug ) ) . '</li>';
					continue;
				}
				
				// create the post
				$new_post = Post::create( array(
					'title' => $title,
					'content' => $content,
					'user_id' => $this->users[ $author_id ],
					'status' => ( $approved == true ) ? Post::status('published') : Post::status('draft'),
					'pubdate' => $date_created,
					'updated' => $date_modified,		// major edit, used modified
					'modified' => $date_modified,		// minor edit, we'll set it to the same modified
					'content_type' => Post::type( $type ),
					'slug' => $slug,
				) );
				
				$new_post->info->old_id = $id;
				
				$output .= '<li>' . _t('Created post %s', array( $title ));
				
				// get all the post tags
				$post_tags = new Terms();
				$output .= '<ul>';
				foreach ( $post->categories->category as $tag ) {
					
					$old_id = intval( $tag->attributes()->ref );
					
					// $this->tags is old ID => new ID. we'll key the posts_tags array just for our own sanity
					$post_tags[] = Tags::get_by_id( $this->tags[ $old_id ] );
					
					$output .= '<li>' . _t('Tagged post with old tag ID %1$d, new tag ID %2$d', array( $old_id, $this->tags[ $old_id ] )) . '</li>';
					
				}
				$output .= '</ul>';
				

				$new_post->tags = $post_tags;
				$new_post->update();		// save the tags and original id
				
				// get all the post comments
				$output .= '<ul>';
				foreach ( $post->comments->comment as $comment ) {
					
					// @todo get the comments!

				}
				$output .= '</ul>';

				$output .= '</li>';
				
			}
			$output .= '</ul>';
			
			return $output;
			
		}
		
	}

?>