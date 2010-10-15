<?php

	class Import extends Plugin {
		
		public function action_update_check ( ) {
			
			Update::add( 'Import', '7c4133b7-6578-4dc0-9dbe-f8ef89a9ec80', $this->info->version );
			
		}
		
	}

?>