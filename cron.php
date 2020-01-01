<?php

$projectList = $module->framework->getProjectsWithModuleEnabled();
$zipPath = $module::ORI_PATH;

foreach ($projectList as $project_id) {
    $_GET['pid'] = $project_id;

    $mrnList = json_decode($module->checkForMRNs(), true);

    $edocs = $mrnList["edocs"];

/*if (!is_dir($zipPath)) {
    mkdir($zipPath,0755,true);
}
chmod($zipPath,0755);*/
    $module->createZipFile($zipPath . "NVDC_All_Files_" . $project_id.".zip", $edocs);
}
unset($_GET['pid']);

delete_files($zipPath);

/*
 * php delete function that deals with directories recursively
 */
function delete_files($target) {
    if(is_dir($target)){
        $files = glob( $target . '*', GLOB_MARK ); //GLOB_MARK adds a slash to directories returned

        foreach( $files as $file ){
            delete_files( $file );
        }

        rmdir( $target );
    } elseif(is_file($target)) {
        unlink( $target );
    }
}