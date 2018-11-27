<?php
if (isset($_POST['mrnList'])){
	# do some processing on this mrn list then send to module
	# module will take this list and send user the .zip of alarm, log, and trends files
	preg_match_all("/(?:^|,\s*)(\d+)/", $_POST['mrnList'], $mrnList);
	echo "<pre>";
	print_r($mrnList);
	echo "</pre>";
	// $module->downloadFiles($mrnList);
} else {
	echo $module->getMRNForm();
}