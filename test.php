<?
$files = array($module->getModulePath() . "test.php");
//Getting the total size of the files array
$totalSize = 0;
foreach ($files as $file) {
	$totalSize += filesize($file);
}
$totalSize += 300; //I don't understand why, but the totalSize is always 300kb short of the correct size
header('Pragma: no-cache'); 
header('Content-Description: File Download'); 
header('Content-disposition: attachment; filename="myZip.zip"');
header('Content-Type: application/octet-stream');
header('Content-Length: ' . $totalSize);
header('Content-Transfer-Encoding: binary'); 
//Opening a zip stream
$files = implode(" ", $files);
if ($files){
	$fp = popen('zip -r -0 - ' . $files, 'r');
}

flush(); //Flushing the butter, pre streaming
while(!feof($fp)) {
	echo fread($fp, 8192);
}
//Closing the stream
if ($files){ 
	pclose($fp);
}

// $zipfilename = "zip_file_name.zip";

// if( isset( $files ) ) unset( $files );

// // $target = "/some/directory/of/files/you/want/to/zip";
// $target = $module->getModulePath();

// $d = dir( $target );

// while( false !== ( $entry = $d->read() ) )
// {
	// if( substr( $entry, 0, 1 ) != "." && !is_dir( $entry ) ) 
	// {
		// $files[] = $entry;
	// }
// }

// header( "Content-Type: application/x-zip" );
// header( "Content-Disposition: attachment; filename=\"$zipfilename\"" );

// $filespec = "";

// foreach( $files as $entry )
// {
	// $filespec .= "\"$entry\" ";
// }

// chdir( $target );

// $stream = popen( "zip -q - $filespec", "r" );

// if( $stream )
// {
	// fpassthru( $stream );
	// fclose( $stream );
// }

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