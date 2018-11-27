<?php
namespace Vanderbilt\NVDC;

class NVDC extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function downloadFiles($mrnList = []) {
		# downloadFiles will send the user a .zip of all the alarm, log, and trends file for their NICU Ventilator Data project
		# optionally, supply a $mrnList to filter to only those MRNs
		$pid = $this->getProjectId();
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
	}
	
	public function getMRNForm() {
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("{STYLESHEET}", $this->getUrl("css" . DIRECTORY_SEPARATOR . "stylesheet.css"), $html);
		$html = str_replace("{TITLE}", "Get Files By MRN", $html);
		
		// $page = $this->getUrl("getFilesByMRN.php");
		$_GET['pid'] = $this->getProjectId();
		$_GET['prefix'] = "nicu_ventilator_data_capture";
		$_GET['page'] = "getFilesByMRN";
		$body = "<div class='container'>
			<p>Enter one MRN or a list of comma-separated MRNs to retrieve files for</p>
			<form method='post'>
				<input type='text' name='mrnList'>
				<input type='submit' value='Submit'>
			</form>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
}