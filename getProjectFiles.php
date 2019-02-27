<?php
if (isset($_POST['mrnList'])) {
	// generate a temporary filename for the zip file we're about to make for user and send to client
    ob_start();
	$zipName = tempnam(EDOC_PATH,"");
    echo("ok");
    $size = ob_get_length();
    header("Content-Encoding: none");
    header("Content-Length: {$size}");
    header("Connection: close");
    ob_end_flush();
    ob_flush();
    flush();
    if(session_id()) session_write_close();
	
	// now we start the process of actually creating the file
	$result = $module->makeZip($_POST['mrnList'], $zipName);
} elseif($_POST['checkForZip']) {
	$zipPath = $this->getModulePath() . "NVDC_attached_files.zip";
	if (file_exists($zipPath)) {
		exit('true');
	} else {
		exit('false');
	}
} elseif($_POST['getZip']) {
	$zipPath = $this->getModulePath() . "NVDC_attached_files.zip";
	header('Content-Type: application/zip');
	header('Content-Description: File Transfer');
	header('Content-Disposition: attachment; filename="NICU_Ventilator_Data_Files.zip"');
	header('Content-length: ' . filesize($zipPath));
	readfile($zipPath);
	unlink($zipPath);
} else {
	echo $module->printMRNForm();
}