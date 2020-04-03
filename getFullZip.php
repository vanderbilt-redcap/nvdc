<?php

$report_string = "";

$zipName = "NVDC_All_Files_".$module->getProjectId().".zip";
$zipFilePath = $module::ORI_PATH . $zipName;

// testing
$zip = new \ZipArchive();
echo("zip open result: " . print_r($zip->open($zipFilePath, \ZipArchive::CREATE), true) . "<br>");
echo("zip addFromString result: " . print_r($zip->addFromString("myfile.txt", "this is my text file contents"), true) . "<br>");
echo("zip close result: " . print_r($zip->close(), true) . "<br>");
exit();


if (file_exists($zipFilePath)) {
// if (false) {
	header('Content-Type: application/zip');
	header('Content-Description: File Transfer');
	header('Content-Disposition: attachment; filename="' . $zipName . '"');
	header('Content-length: ' . filesize($zipFilePath));
	readfile($zipFilePath);
} else {
	$report_string .= "The REDCap NVDC module didn't find the expected .zip archive. Attempting to generate .zip archive now." . "<br>";
	
	$current_pid = $_GET['pid'];
	$archives_created = $module->cron([$current_pid]);
	
	$_GET['pid'] = $current_pid;
	$mrnList = json_decode($module->checkForMRNs(), true);
	$edocs = $mrnList["edocs"];
	
	$edoc_count = count($edocs);
	
	$report_string .= "Found $edoc_count external documents to archive." . "<br>";
	if ($edoc_count > 0 ) {
		if (file_exists($zipFilePath)) {
		// if (false) {
			header('Content-Type: application/zip');
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename="' . $zipName . '"');
			header('Content-length: ' . filesize($zipFilePath));
			readfile($zipFilePath);
		} else {
			if ($archives_created[$current_pid] !== true) {
				$report_string .= print_r($archives_created[$current_pid], true) . "<br>";
			} else {
				$report_string .= "An error has occured -- the NVDC module successfully created the .zip archive, but it's not in the location expected." . "<br>";
			}
			exit($report_string);
		}
	} else {
		$report_string .= "There are 0 external documents associated with this project -- therefore, there is no .zip archive to download." . "<br>";
		$report_string .= "You will be able to download a .zip after attaching ventilator files." . "<br>";
		exit($report_string);
	}
}
