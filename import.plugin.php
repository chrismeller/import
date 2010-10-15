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
		
		public function filter_import_stage ( $stageoutput, $import_name, $stage, $step ) {
			
			// only run if we're the importer being used
			if ( _t( $this->import_name ) != $import_name ) {
				// must return $stageoutput as it could contain the output of another plugin!
				return $stageoutput;
			}
			
		}
		
	}

?>