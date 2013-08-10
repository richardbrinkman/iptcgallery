<?php
	namespace classes;

	class Scanner extends IptcParser {

		public function __construct() {
			parent::__construct();
		}

		public function __destruct() {
			$this->log("finished");
			echo "event: finished\n";
			echo "data: Finished gallery scan\n\n";
			parent::__destruct();
		}

		public function scan() {
			foreach ($this->db->query("SELECT directory_id, dirname FROM directory") as list($directoryId, $dirname))
				$this->processDir($directoryId, $dirname, "");
		}

		public function processDir($directoryId, $root, $dir) {
			$path = $root . $dir;
			$this->log("Scanning: $path");
			foreach (scandir($path) as $file) 
				if ($file != "." && $file != "..") {
					$file = ltrim($dir . DIRECTORY_SEPARATOR . $file, DIRECTORY_SEPARATOR);
					if (is_dir($root . $file))
						$this->processDir($directoryId, $root, $file);
					else 
						$this->processPhoto($directoryId, $root, $file);
				}
		}

		protected function log($message) {
			echo "retry: 3600000\n";
			echo "data: $message\n\n";
			ob_flush();
			flush();
		}
	}
?>
