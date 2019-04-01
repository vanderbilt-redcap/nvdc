<?php
		$projectUsers = \REDCap::getUsers();
		\REDCap::allowUsers($projectUsers);
if (empty($_FILES)) {
	echo $module->printUploadForm();
} else {
	$statuses = $module->handleZip();
	echo $module->printUploadReport($statuses);
	
	// // diagnostics
	// echo "<pre>";
	// print_r($statuses);
	// echo "</pre>";
	// exit;
}