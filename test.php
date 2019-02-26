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

// $descriptorspec = array(
    // 0 => array("pipe", "r"),
    // 1 => array("pipe", "w"),
    // 2 => array("file", "err.txt", "a")
// );
// // Create child and start process
// $child = array("process" => null, "pipes" => null);
// $child["process"] = proc_open("zip test.zip test.php", $descriptorspec, $child["pipes"]);
// echo(stream_get_contents($child['pipes'][0]) . "<br />");
// echo(stream_get_contents($child['pipes'][1]) . "<br />");
// echo(file_get_contents("err.txt") . "<br />");
// echo(gettype($child['process']) . "<br />");
// echo(gettype($child['pipes']) . "<br />");
// exit;

exec('zip test.zip test.php');
exit();