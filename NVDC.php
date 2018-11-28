<?php
namespace Vanderbilt\NVDC;

class NVDC extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function downloadFiles($mrnList = ['all' => true]) {
		# downloadFiles will send the user a .zip of all the alarm, log, and trends file for their NICU Ventilator Data project
		# optionally, supply a $mrnList to filter to only those MRNs
		$pid = $this->getProjectId();
		$edocInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'alarm_file', 'log_file', 'trends_file'));
		
		# get array of ids to help us build sql string query
		$edocIDs = [];
		foreach ($edocInfo as $recordId => $record) {
			$arr = current($record);	# we use current instead of having to determine the event ID
			if ($mrnList['all'] || in_array($arr['mrn'], $mrnList)) {
				if ($arr['alarm_file']) {
					$edocIDs[] = $arr['alarm_file'];
				}
				if ($arr['log_file']) {
					$edocIDs[] = $arr['log_file'];
				}
				if ($arr['trends_file']) {
					$edocIDs[] = $arr['trends_file'];
				}
			}
		}
		
		# create zip file, open it
		$zip = new \ZipArchive();
		$fullpath = tempnam(EDOC_PATH,"");
		$zip->open($fullpath, \ZipArchive::CREATE);
		
		# query redcap_edocs_metadata to get file names/paths to add to zip
		$sql = "SELECT * FROM redcap_edocs_metadata WHERE project_id=$pid and doc_id in (" . implode(", ", $edocIDs) . ")";
		$query = db_query($sql);
		while($row = db_fetch_assoc($query)) {
			$edoc = file_get_contents(EDOC_PATH . $row['stored_name']);
			if ($edoc) {
				$zip->addFromString($row['doc_name'], $edoc);
			}
		}
		
		# close and send!
		$zip->close();
		$zipFileName = "NICU_Ventilator_Data_Files.zip";
		header("Content-disposition: attachment; filename=$zipFileName");
		header('Content-type: application/zip');
		readfile($fullpath);
		unlink($fullpath);
	}
	
	public function handleZip() {
		echo "<pre>";
		# find the uploaded zip archive in $_FILES and upload mrn folder > alarm/log/trends files to appropriate record
		$zip_size = 0;
		if (isset($_FILES['zip'])) {
			$zip_size = $_FILES['zip']['size'];
			if (($zip_size/1024/1024) > maxUploadSizeEdoc()) {
				unlink($_FILES['zip']);
				return "ERROR: REDCap cannot upload your .zip file. The chosen file is " . round(($zip_size/1024/1024)) . "MB in size. The maximum file size for uploads is " . round(maxUploadSizeEdoc()) . "MB.";
			}
			if ($_FILES['myfile']['error'] != UPLOAD_ERR_OK) {
				return "ERROR: There was an error uploading your .zip file, please try again.";
			}
		} else {
			return "ERROR: no files uploaded";
		}
		
		# $uploaded[] first level is mrn => array, second level is 'alarm/log/trends' => filename
		$uploaded = [];
		$fileInfo = current($_FILES);
		$zip = new \ZipArchive();
		$zip->open($fileInfo['tmp_name']);
		if ($zip === true) {
			return "ERROR: REDCap couldn't open the chosen zip archive";
		}
		for ($i = 0; $i < $zip->numFiles; $i++) {
			preg_match_all("/^(\d+)\/(.+\.csv|.+\.txt)/", $zip->getNameIndex($i), $matches);
			$mrn = $matches[1][0];
			$filename = $matches[2][0];
			preg_match_all("/^(\w+)/", $filename, $matches);
			$filetype = $matches[1][0];		# should either be "Alarm", "Logbook", or "Trends"
			
			if ($filetype == "Alarm") {
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$uploaded[$mrn]["alarm_file"] = $filename;
			}
			if ($filetype == "Logbook") {
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$uploaded[$mrn]["log_file"] = $filename;
			}
			if ($filetype == "Trends") {
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$uploaded[$mrn]["trends_file"] = $filename;
			}
		}
		
		# look for appropriate records per mrn
		$pid = $this->getProjectId();							# 8-digit, mm/dd/yyyy, id, id, id
		$mrn = key($uploaded);
		$recordsInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'date_vent', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, "[mrn]='$mrn'");
		
		# upload files to the oldest record that has no files attached
		$targetRid = false;
		$oldestDate = new \DateTime("01/01/0000");
		foreach ($recordsInfo as $rid => $entry) {
			$record = current($entry);
			$date_vent = new \DateTime($record['date_vent']);
			if ($date_vent->getTimestamp() > $oldestDate->getTimestamp() && !$record['alarm_file'] && !$record['log_file'] && !$record['trends_file']) {
				$oldestDate = $date_vent;
				$targetRid = $rid;
			}
		}
		
		if (!$targetRid) {
			return "ERROR: unable to find a record for MRN $mrn that doesn't have files already attached.";
		}
		
		echo (string) ($zip->getFromName($mrn . "/" . $uploaded[$mrn]['alarm_file']));
		// echo $uploaded[$mrn]['alarm_file'];
		// print_r($uploaded);
		
		echo "</pre>";
	}
	
	public function printMRNForm() {
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("{STYLESHEET}", $this->getUrl("css" . DIRECTORY_SEPARATOR . "stylesheet.css"), $html);
		$html = str_replace("{TITLE}", "Get Files By MRN", $html);
		
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
	
	public function printUploadForm() {
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("{STYLESHEET}", $this->getUrl("css" . DIRECTORY_SEPARATOR . "stylesheet.css"), $html);
		$html = str_replace("{TITLE}", "Attach Ventilator Files", $html);
		$body = "<div class='container'>
			<p>Select a .zip archive of ventilator files to upload.</p>
			<form enctype='multipart/form-data' method='post'>
				<div class='custom-file'>
					<input type='file' name='zip' class='custom-file-input' id='zip'>
					<label class='custom-file-label' for='zip'>Choose .zip</label>
				</div>
				<button type='submit'>Upload</button>
			</form>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
}