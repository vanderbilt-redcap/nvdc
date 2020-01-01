<?php
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