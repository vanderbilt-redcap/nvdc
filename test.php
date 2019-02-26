<?
// $fname = $module->getModulePath() . "test.php";
$fp = popen("zip - /app001/www/redcap/modules/nvdc_v1.0/test.php", 'r');
if (is_resource($fp)) {
	header("Content-Type: application/zip");
	header("Content-Disposition: attachment; filename=\"test.zip\"");
	echo fpassthru($fp);
	fclose($fp);
	exit();
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