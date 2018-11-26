<?php
#
# getProjectFiles will send the user a .zip of all the alarm, log, and trends file for their NICU Ventilator Data project
#
$pid = $module->getProjectId();
$edocInfo = \REDCap::getData($pid, 'array', NULL, array('alarm_file', 'log_file', 'trends_file'));

# get array of ids to help us build sql string query
$edocIDs = [];
foreach ($edocInfo as $recordId => $record) {
	$IDs = current($record);
	foreach ($IDs as $name => $id) {
		$edocIDs[] = $id;
	}
}

# create zip file, open it
$zip = new \ZipArchive();
$fullpath = tempnam(EDOC_PATH,"");
$zip->open($fullpath, \ZipArchive::CREATE);

# query redcap_edocs_metadata to get file names/paths to add to zip
$sql = "SELECT * FROM redcap_edocs_metadata WHERE project_id=$pid and doc_id in (" . implode(", ", $edocIDs) . ")";
$query = db_query($sql);
// echo "<pre>";
while($row = db_fetch_assoc($query)) {
	// print_r($row);
	$edoc = file_get_contents(EDOC_PATH . $row['stored_name']);
	if ($edoc) {
		$zip->addFromString($row['doc_name'], $edoc);
	}
}
// echo "</pre>";

# close and send!
$zip->close();
$zipFileName = "NICU_Ventilator_Data_Files.zip";
header("Content-disposition: attachment; filename=$zipFileName");
header('Content-type: application/zip');
readfile($fullpath);
unlink($fullpath);