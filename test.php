<?php
// $module->removeAttachedFiles();
// $module->attachFakeFiles();

// example file names:
// Alarm history Export 7 days Infinity Acute Care System Workstation Critical Care Build 7016 ASEB-0069 01-Feb-2019 16_06_02.txt
// Logbook Export 7 days Infinity Acute Care System Workstation Critical Care Build 7016 ASCN-0001 01-Feb-2019 16_19_34.txt
// Trends Export 7 days Infinity Acute Care System Workstation Critical Care Build 7016 ASCN-0001 01-Feb-2019 16_19_41.csv	

// Alarm ASCN-0001 25-Feb-2019 16_06_02.txt
// Logbook ASCN-0001 25-Feb-2019 16_06_02.txt
// Trends ASCN-0001 25-Feb-2019 16_06_02.txt

// error_reporting(E_ALL);
// $windows = strpos(php_uname(), "Windows") !== false;
// $path1 = "\"C:\\\Program Files\\7-Zip\\7z.exe\"";
// $path2 = "C:\\xampp\\htdocs\\redcap\\modules\\nvdc_v1.0\\";
// $path3 = "~/www/redcap/modules/nvdc_v1.0/test.zip";
// $path4 = "~/www/redcap/modules/nvdc_v1.0/test.php";
// $cmd = $windows ? "$path1 a -tzip $path2" . "test.zip $path2" . "test.php" : "zip $path3 $path4";
// $pathErr = $windows ? "C:\\xampp\\htdocs\\redcap\\modules\\nvdc_v1.0\\err.txt" : "~/www/redcap/modules/nvdc_v1.0/err.txt";
// $spec = [
	// 0 => ["pipe", 'r'],
	// 1 => ["pipe", 'w'],
	// 2 => ["file", $pathErr, 'a']
// ];
// $process = proc_open($cmd, $spec, $pipes);
// echo "\$process: ";
// var_dump($process);
// echo "<br />";
// echo "<br />";
// echo "\$pipes[1]: ";
// echo stream_get_contents($pipes[1]);
// fclose($pipes[1]);
// echo "<br />";
// echo "<br />";
// echo("err.txt: " . file_get_contents("err.txt"));
// echo "<br />";
// echo "<br />";
// echo("stderr: " . file_get_contents("php://stderr"));
// $returnVal = proc_close($process);
// echo "<br />";
// echo "<br />";
// echo("\$returnVal: " . print_r($returnVal, true));

error_reporting(E_ALL);
$modPath = "~/www/redcap/modules/nvdc_v1.0/";
exit(file_get_contents($module->getUrl("test.php"));
$command = "zip -r - ~/www/redcap/modules/nvdc_v1.0/test.php";
$spec = [
	0 => ["pipe", 'r'],
	1 => ["pipe", 'w'],
	2 => ["file", $modPath . "err.txt", 'a']
];
$process = proc_open($command, $spec, $pipes);
header('Content-Type: application/octet-stream');
// header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename=NICU_Ventilator_Data_Files.zip');
echo stream_get_contents($pipes[1]);
proc_close($process);