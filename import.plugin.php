<?php

	class Import extends Plugin implements Importer {
		
		private $import_name = 'BlogML XML File';
		
		public function action_update_check ( ) {
			
			Update::add( 'Import', '7c4133b7-6578-4dc0-9dbe-f8ef89a9ec80', $this->info->version );
			
		}
		
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
			
			// figure out which stage we're on
			switch ( $stage ) {
				
				case 1:
					
					if ( isset( $_FILES['import_file'] ) ) {
						
						if ( !is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
							$inputs['warning'] = _t( 'Upload failed!' );
						}
						else {
							
							if ( $this->load_xml( $_FILES['import_file']['tmp_name'] ) ) {
								
								// one more nested level!
								if ( $filename = $this->save_file( $_FILES['import_file']['tmp_name'] ) ) {
									$inputs['filename'] = $filename;
									$stage = 2;
								}
								else {
									$inputs['warning'] = _t( 'Unable to store XML file for parsing.' );
								}
								
							}
							else {
								$inputs['warning'] = _t( 'Unable to load XML file.' );
							}
						}
						
					}
					
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
			
			return $output;
			
		}
		
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
		
		private function load_xml ( $filename ) {
			
			$xml = file_get_contents( $filename );
			
			try {
				$xml = new SimpleXMLElement($xml);
				return true;
			}
			catch ( Exception $e ) {
				return false;
			}
			
		}
		
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
		
	}

?>