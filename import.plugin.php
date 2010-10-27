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
			'installed',		// internal
		);
		
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
			
			$output = '';
			
			// save the number of users we end up creating
			$user_count = 0;
			
			$output .= '<ul>';
			foreach ( $xml->authors->author as $author ) {
				
				// see if a user with this username already exists
				$user = User::get_by_name( $author->title );
				
				if ( $user instanceof User ) {
					// if the user exists, save their old ID into an info attribute
					$user->info->old_id = intval( $author->attributes()->id );
					// and update
					$user->update();
					
					$output .= '<li>' . _t('Associated imported user %s with existing user %s', array( $author->title, $user->username ) ) . '</li>';
					
					EventLog::log( _t('Associated imported user %s with existing user %s.', array( $author->title, $user->username )), 'info', 'import', 'BlogML' );
					
				}
				else {
					
					// we must create a new user
					try {
						
						$u = new User();
						$u->username = $author->title;
						$u->email = $author->attributes()->email;
						$u->info->old_id = intval( $author->attributes()->id );
						$u->insert();

						$output .= '<li>' . _t('Created new user %s. Their old ID was %d.', array( $user->username, $author->attributes()->id )) . '</li>';
						EventLog::log( _t('Created new user %s. Their old ID was %d.', array( $user->username, $author->attributes()->id )), 'info', 'import', 'BlogML' );
						
						$user_count++;
						
					}
					catch ( Exception $e ) {
						
						// no idea why we might error out, but catch it if we do
						EventLog::log( $e->getMessage(), 'err', 'import', 'BlogML', array( $author->asXML(), $e ) );
						Session::error( _t( 'There was an error importing user %s. See the EventLog for details.', array( $author->title ) ) );
						
					}
					
				}
				
			}	// foreach author
			$output .= '</ul>';
			
			return $output;
			
		}
		
		private function import_options ( $xml ) {
			
			$output = '<h3>' . _t( 'Options' ) . '</h3>';
			
			$output .= '<ul>';
			foreach ( $xml->children() as $child ) {
				
				if ( $child->getName() == 'extended-properties' ) {
					
					foreach ( $child->property as $property ) {
						
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
						EventLog::log( _t('Imported option %s', array( $property->attributes()->name )), 'debug', 'import', 'BlogML', array( $property->attributes()->name, $property->attributes()->value ) );
						
					}
					
				}
				
			}
			$output .= '</ul>';
			
			return $output;
			
		}
		
		private function import_tags ( $xml ) {
			
			$output = '';
			
			$output .= '<ul>';
			foreach ( $xml->categories->category as $category ) {
				
				$id = $category->attributes()->id;
				$title = $category->title;
				
				// @todo this seems to duplicate existing tags, but it shouldn't
				$tag = Tag::create( array( 'tag_text' => $title ) );
				
				$output .= '<li>' . _t('Creating tag %s.', array( $title ) ) . '</li>';
				
			}
			$output .= '</ul>';
			
			return $output;
			
		}
		
	}

?>