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


// header('Content-Type: application/octet-stream');
// header('Content-Disposition: attachment; filename=arch.zip');
// $fpaths = [
	// EDOC_PATH . "20181205095556_pid1158_nIwRKc.txt",
	// EDOC_PATH . "20181205095556_pid1158_J7e8vU.txt"
// ];
// $fp = popen("zip -r - " . implode(' ', $fpaths), 'r');
// $bufferSize = 8192;
// $buffer = '';
// while (!feof($fp)) {
	// $buffer = fread($fp, $bufferSize);
	// echo $buffer;
// }
// pclose($fp);

// make sure to send all headers first

// use popen to execute a unix command pipeline
// and grab the stdout as a php stream
error_reporting(E_ALL);
$fpath1 = EDOC_PATH . "20181205095556_pid1158_nIwRKc.txt";
$desc = [
	0 => ['pipe', 'r'],
	// 1 => ['pipe', 'r'],
	2 => ['file', 'err.txt', 'a']
];
$fp = proc_open("zip file.zip ~/www/redcap/modules/nvdc_v1.0/test.php", $desc, $pipes);
echo "'\$fp': " . gettype($fp) . "<br />";
echo "'\$pipes': " . gettype($pipes) . "<br />";
echo "\$pipes[0]:" .  stream_get_contents($pipes[0]) . "<br />";
echo "php://stderr:" .  file_get_contents("php://stderr");
fclose($pipes[0]);
exit();

if (!$fp) {
	exit('error');
}

// pick a bufsize that makes you happy (8192 has been suggested).
header('Content-Type: application/octet-stream');
header('Content-disposition: attachment; filename="file.zip"');
$bufsize = 8192;
$buff = '';
while( !feof($fp) ) {
   $buff = fread($fp, $bufsize);
   echo $buff;
}
pclose($fp);