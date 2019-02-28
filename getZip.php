<?php
$sid = session_id();
$sidHash8 = substr(hash('md5', session_id()), 0, 8);
$zipName = "NVDC_Files_$sidHash8.zip";
$zipFilePath = $module->getModulePath() . "/userZips/$zipName";
if (file_exists($zipFilePath)) {
	header('Content-Type: application/zip');
	header('Content-Description: File Transfer');
	header('Content-Disposition: attachment; filename="' . $zipName . '"');
	header('Content-length: ' . filesize($zipFilePath));
	readfile($zipFilePath);
}