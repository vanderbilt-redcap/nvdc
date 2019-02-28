<?php
if (isset($_POST['mrnList'])) {
	// generate a temporary filename for the zip file we're about to make for user and send to client
    ob_start();
	
	// see if we will be able to generate a zip from the given mrns
	// if so, send ok, otherwise send error message
	$ret = $module->checkForMRNs($_POST['mrnList']);
	if (gettype($ret) == 'string') {
		$arr = ['download' => false, 'message' => $ret];
	} else {
		$arr = ['download' => true];
	}
	$response = json_encode($arr);
	
	//----------------------------------------------------------------------------------------------------
	// unfortunately, the commented out lines don't work to end an ajax request early
	
	// ob_end_clean();
	// header("Connection: close");
	// ignore_user_abort(true);
	// ob_start();
	echo($response);
	// $size = ob_get_length();
	// header("Content-Length: $size");
	// ob_end_flush();
	// flush();
	
	//----------------------------------------------------------------------------------------------------
	// now we start the process of actually creating the file
	if (gettype($ret) != "string") $module->makeZip($ret);
} elseif($_POST['checkForZip']) {
	$sidHash8 = substr(hash('md5', session_id()), 0, 8);
	$zipName = "NVDC_Files_$sidHash8.zip";
	$zipFilePath = $module->getModulePath() . "/userZips/$zipName";
	if (file_exists($zipFilePath)) {
		exit('true');
	} else {
		exit('false');
	}
} else {
	echo $module->printMRNForm();
}