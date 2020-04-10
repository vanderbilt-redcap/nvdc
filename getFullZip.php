<?php
$result = $this->updateZip();
if ($result === false) {
	\REDCap::email("carl.w.reed@vumc.org", "carl.w.reed@vumc.org", "NVDC getFullZip.php failure", "NVDC getFullZip.php failed:\r\n" . file_get_contents("/ori/redcap_plugins/nvdc/log.txt"));
	echo("The NVDC module has run into an unexpected error and cannot validate the project's zip archive. REDCap has sent an email to a REDCap DataCore developer notifying them of this issue.");
} else {
	$zipName = "NVDC_All_Files_".$module->getProjectId().".zip";
	// $zipFilePath = $this->getModulePath() . "/userZips/$zipName";
	$zipFilePath = $module::ORI_PATH . $zipName;
	if (file_exists($zipFilePath)) {
		header('Content-Type: application/zip');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename="' . $zipName . '"');
		header('Content-length: ' . filesize($zipFilePath));
		readfile($zipFilePath);
	}
}