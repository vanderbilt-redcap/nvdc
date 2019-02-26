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

<?php
error_reporting(E_ALL);
// make sure to send all headers first
// Content-Type is the most important one (probably)
//
header('Content-Type: application/octet-stream');
header('Content-disposition: attachment; filename="file.zip"');

// use popen to execute a unix command pipeline
// and grab the stdout as a php stream
// (you can use proc_open instead if you need to 
// control the input of the pipeline too)
//
$fname = $module->getModulePath() . "test.php";
$fp = popen("zip -r - $fname", 'r');

echo gettype($fp);
echo "<br />";
echo (is_resource($fp));
echo "<br />";
var_dump($fp);
pclose($fp);
exit();

// pick a bufsize that makes you happy (8192 has been suggested).
$bufsize = 8192;
$buff = '';
while( !feof($fp) ) {
   $buff = fread($fp, $bufsize);
   echo $buff;
}
pclose($fp);