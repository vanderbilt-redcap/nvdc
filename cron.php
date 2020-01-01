<?php

$projectList = $module->framework->getProjectsWithModuleEnabled();

foreach ($projectList as $project_id) {
    $_GET['pid'] = $project_id;

    $mrnList = json_decode($module->checkForMRNs(), true);

    $edocs = $mrnList["edocs"];

    $zipPath = $module::ORI_PATH;

    $module->createZipFile($zipPath . "NVDC_All_Files_" . $project_id.".zip", $edocs);
}
unset($_GET['pid']);