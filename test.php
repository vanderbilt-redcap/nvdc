<?

if (function_exists('exec')) echo "exec is enabled<br />";
if (function_exists('popen')) echo "popen is enabled<br />";
if (function_exists('proc_open')) echo "proc_open is enabled<br />";
exit();

// $fname = $module->getModulePath() . "test.php";
$fp = popen("zip - /app001/www/redcap/modules/nvdc_v1.0/test.php 2>&1", 'r');
if (is_resource($fp)) {
	header("Content-Type: application/zip");
	header("Content-Disposition: attachment; filename=\"test.zip\"");
	echo fpassthru($fp);
	fclose($fp);
	exit();
} elseif ($fp === false) {
	fclose($fp);
	echo "\$fp was FALSE";
} else {
	var_dump($fp);
	echo "<br />";
	echo "not a resource";
	echo "<br />";
	echo fclose($fp);
}

// $module->removeAttachedFiles();
// $module->attachFakeFiles();

// example file names:
// Alarm history Export 7 days Infinity Acute Care System Workstation Critical Care Build 7016 ASEB-0069 01-Feb-2019 16_06_02.txt
// Logbook Export 7 days Infinity Acute Care System Workstation Critical Care Build 7016 ASCN-0001 01-Feb-2019 16_19_34.txt
// Trends Export 7 days Infinity Acute Care System Workstation Critical Care Build 7016 ASCN-0001 01-Feb-2019 16_19_41.csv	

// Alarm ASCN-0001 25-Feb-2019 16_06_02.txt
// Logbook ASCN-0001 25-Feb-2019 16_06_02.txt
// Trends ASCN-0001 25-Feb-2019 16_06_02.txt
?>