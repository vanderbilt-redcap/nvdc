<?php
namespace Vanderbilt\NVDC;

class NVDC extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function printall() {
		// $project = new \Project($this->getProjectId());
		// $info = "Project vars:\n". print_r(get_object_vars($project), true) . "\nProject methods:\n"  . print_r(get_class_methods($project), true);
		
		// $html = file_get_contents("html" . DIRECTORY_SEPARATOR . "print.html");
		// $html = str_replace("CONTENT", $info, $html);
		// echo $html;
		echo "test";
	}
}