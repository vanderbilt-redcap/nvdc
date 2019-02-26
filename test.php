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


header('Content-Type: application/octet-stream');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename=arch.zip');
$fpaths = [
	EDOC_PATH . "20181205095556_pid1158_nIwRKc.txt",
	EDOC_PATH . "20181205095556_pid1158_J7e8vU.txt"
];
$fp = popen("zip -r - " . implode(' ', $fpaths), 'r');
$bufferSize = 8192;
$buffer = '';
while (!feof($fp)) {
	$buffer = fread($fp, $bufferSize);
	echo $buffer;
}
pclose($fp);