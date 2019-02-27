<?php
$zipPath = $module->getModulePath() . "NVDC_attached_files.zip";
header('Content-Type: application/zip');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="NICU_Ventilator_Data_Files.zip"');
header('Content-length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
?>