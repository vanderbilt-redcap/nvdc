<?php
if (isset($_POST['mrnList'])) {
	// generate a temporary filename for the zip file we're about to make for user and send to client
	ini_set("log_errors", 1);
	ini_set("error_log", $module->getModulePath() . "/php-error.log");
    ob_start();
	
	$sidHash8 = substr(hash('md5', session_id()), 0, 8);
	$zipName = "NVDC_Files_$sidHash8.zip";
	$zipFilePath = $module->getModulePath() . "/userZips/$zipName";
	if (file_exists($zipFilePath)) unlink($zipFilePath);
	// see if we will be able to generate a zip from the given mrns
	// if so, send ok, otherwise send error message
	$ret = $module->checkForMRNs($_POST['mrnList']);
	if (gettype($ret) == 'string') {
		$arr = ['download' => false, 'message' => $ret];
	} else {
		$arr = ['download' => true];
	}
	$response = json_encode($arr);
	echo($response);
	if (gettype($ret) != "string") $module->makeZip($ret);
} elseif($_POST['checkForZip']) {
	ini_set("log_errors", 1);
	ini_set("error_log", $module->getModulePath() . "/php-error.log");
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