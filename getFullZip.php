<?php
$zipName = "NVDC_All_Files_".$module->getProjectId().".zip";
$zipFilePath = $module::ORI_PATH . $zipName;
if (file_exists($zipFilePath)) {
    header('Content-Type: application/zip');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-length: ' . filesize($zipFilePath));
    readfile($zipFilePath);
} else {
	echo ("The REDCap NVDC module didn't find zipped files. Attempting to generate .zip archive now.<br>");
	
	$current_pid = $_GET['pid'];
	$archives_created = $module->cron([$current_pid]);
	
	$_GET['pid'] = $current_pid;
	$mrnList = json_decode($module->checkForMRNs(), true);
	$edocs = $mrnList["edocs"];
	
	$edoc_count = count($edocs);
	
	if ($edoc_count > 0 ) {
		if (file_exists($zipFilePath)) {
			header('Content-Type: application/zip');
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename="' . $zipName . '"');
			header('Content-length: ' . filesize($zipFilePath));
			readfile($zipFilePath);
		} else {
			if ($archives_created[$current_pid] !== true) {
				echo("The NVDC module failed to create the necessary .zip archive: " . $archives_created[$current_pid]) . "<br>";
			} else {
				echo("An error has occured -- the NVDC module successfully created the .zip archive, but it's not in the location expected.");
			}
		}
	} else {
		echo("There are 0 external documents associated with this project -- therefore, there is no .zip archive to download.<br>");
		echo("You will be able to download a .zip after attaching ventilator files.");
	}
}