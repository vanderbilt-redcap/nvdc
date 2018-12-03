<?php
if (empty($_FILES)) {
	echo $module->printUploadForm();
} else {
	$statuses = $module->handleZip();
	print_r($statuses);
}