<?php
if (empty($_FILES)) {
	echo $module->printUploadForm();
} else {
	echo $module->handleZip();
}