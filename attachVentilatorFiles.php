<?php
if (empty($_FILES)) {
	// echo $module->printUploadForm();
	echo $module->test();
} else {
	$statuses = $module->handleZip();
	$module->printUploadReport($statuses);
}