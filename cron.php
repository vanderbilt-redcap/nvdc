<?php

$projectList = $module->framework->getProjectsWithModuleEnabled();
echo "<pre>";
print_r($projectList);
echo "</pre>";
foreach ($projectList as $project_id) {
    $_GET['pid'] = $project_id;

    $mrnList = json_decode($module->checkForMRNs(), true);
echo "<pre>";
print_r($mrnList);
echo "</pre>";
    $edocs = $mrnList["edocs"];
echo "<pre>";
print_r($edocs);
echo "</pre>";
    $zipPath = $module::ORI_PATH;
echo "Zip Path: $zipPath<br/>";
if (!is_dir($zipPath)) {
    mkdir($zipPath,0755,true);
}
chmod($zipPath,0755);
    $module->createZipFile($zipPath . "NVDC_All_Files_" . $project_id.".zip", $edocs);
}
unset($_GET['pid']);