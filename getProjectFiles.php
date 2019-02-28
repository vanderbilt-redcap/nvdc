<?php
ini_set("log_errors", 1);
ini_set("error_log", $module->getModulePath() . "/php-error.log");

if (isset($_POST['mrnList'])) {
    $message = $module->checkForMRNs($_POST['mrnList']);
	exit($message);
} elseif($_POST['makeZip']) {
	// exit(json_encode(['done' => true]));
	$edocs = json_decode($_POST['edocs'], true);
	// file_put_contents($module->getModulePath() . "aaa.txt", print_r($edocs, true));
	$module->makeZip($edocs);
	
	// log which mrns user downloaded files for046329804
	$mrns = [];
	foreach ($edocs as $edoc) {
		$mrns[] = $edoc['mrn'];
	}
	\Logging::logEvent($sql, "redcap_data", "DATA_EXPORT", $targetRid, "User downloaded files for MRNs: " . implode(', ', $mrns), "");
	
	// client may download now
	exit(json_encode(['done' => true]));
} else {
	echo $module->printMRNForm();
}