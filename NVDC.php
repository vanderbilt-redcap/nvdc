<?php
namespace Vanderbilt\NVDC;

class NVDC extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	public function downloadFiles($mrnList = ['all' => true]) {
		// # downloadFiles will send the user a .zip of all the alarm, log, and trends file for their NICU Ventilator Data project
		// # optionally, supply a $mrnList to filter to only those MRNs
		$pid = $this->getProjectSettings()['system-project']['value'];
		$edocInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'alarm_file', 'log_file', 'trends_file'));
		
		// # get array of ids to help us build sql string query
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
		
		// # create zip file, open it
		$zip = new \ZipArchive();
		$fullpath = tempnam(EDOC_PATH,"");
		$zip->open($fullpath, \ZipArchive::CREATE);
		
		// # query redcap_edocs_metadata to get file names/paths to add to zip
		$sql = "SELECT * FROM redcap_edocs_metadata WHERE project_id=$pid and doc_id in (" . implode(", ", $edocIDs) . ")";
		$query = db_query($sql);
		
		while($row = db_fetch_assoc($query)) {
			$edoc = file_get_contents(EDOC_PATH . $row['stored_name']);
			if ($edoc) {
				$zip->addFromString($row['doc_name'], $edoc);
			}
		}
		
		// # if empty zip, say no files found
		if ($zip->numFiles == 0) {
			if ($mrnList['all']) {
				echo "<pre>The NVDC module couldn't find any alarm, logbook, or trends files attached to records in this project.</pre>";
				exit;
			} else {
				echo "<pre>The NVDC module couldn't find any alarm, logbook, or trends files attached to records in this project for the specified MRNs.</pre>";
				exit;
			}
		}
		
		// # close and send!
		$zip->close();
		$zipFileName = "NICU_Ventilator_Data_Files.zip";
		header("Content-disposition: attachment; filename=$zipFileName");
		header('Content-type: application/zip');
		readfile($fullpath);
		unlink($fullpath);
	}
	
	public function handleZip() {
		$returnInfo = [];
		$returnInfo['zipError'] = "";
		# find the uploaded zip archive in $_FILES and upload mrn folder > alarm/log/trends files to appropriate record
		if (isset($_FILES['zip'])) {
			$zip_size = $_FILES['zip']['size'];
			if ($zip_size > 2*1024*1024*1024) {		# is zip file bigger than 2 GB?
				unlink($_FILES['zip']['tmp_name']);
				$returnInfo['zipError'] = "ERROR: REDCap cannot upload the ventilator data files because the zip file is " . $zip_size/1024/1024/1024 . " GB which exceeds the 2 GB limit.";
				return $returnInfo;
			}
			if ($_FILES['myfile']['error'] != UPLOAD_ERR_OK) {
				$returnInfo['zipError'] = "ERROR: There was an error uploading your zip file, please try again.";
				return $returnInfo;
			}
		}
		
		# $uploaded[] first level is mrn => array, second level is 'alarm/log/trends' => filename
		$uploaded = [];
		$zipInfo = $_FILES['zip'];
		$zip = new \ZipArchive();
		$zip->open($zipInfo['tmp_name']);
		if ($zip === true) {
			$returnInfo['zipError'] = "ERROR: REDCap couldn't open the uploaded zip file. Please try to re-archive the ventilator files and upload again.";
			return $returnInfo;
		}
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$fileInfo = $zip->statIndex($i);
			preg_match_all("/^(\d+)\/(.+\.csv|.+\.txt)/", $zip->getNameIndex($i), $matches);
			$mrn = $matches[1][0];
			$filename = $matches[2][0];
			preg_match_all("/^(\w+)/", $filename, $matches);
			$filetype = $matches[1][0];		# should either be "Alarm", "Logbook", or "Trends"
			
			if ($filetype == "Alarm") {
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$uploaded[$mrn]["alarm_file"] = array();
				$uploaded[$mrn]["alarm_file"]['filename'] = $filename;
			}
			if ($filetype == "Logbook") {
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$uploaded[$mrn]["log_file"] = array();
				$uploaded[$mrn]["log_file"]['filename'] = $filename;
			}
			if ($filetype == "Trends") {
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$uploaded[$mrn]["trends_file"] = array();
				$uploaded[$mrn]["trends_file"]['filename'] = $filename;
			}
		}
		
		# look for appropriate records per mrn
		$pid = $this->getProjectId();
		$eid = $this->getFirstEventId();
		foreach($uploaded as $mrn => $files) {
			$recordsInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'date_vent', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, "[mrn]='$mrn'");
			$uploaded[$mrn]['existingRecords'] = $recordsInfo;
			
			# upload files to the oldest record that has no files attached
			$targetRid = 0;
			$oldestDate = new \DateTime("01/01/0000");
			foreach ($recordsInfo as $rid => $entry) {
				$record = current($entry);
				$date_vent = new \DateTime($record['date_vent']);
				
				if ($date_vent->getTimestamp() > $oldestDate->getTimestamp() and !$record['alarm_file'] and !$record['log_file'] and !$record['trends_file']) {
					$oldestDate = $date_vent;
					$targetRid = $rid;
				}
			}
			$uploaded[$mrn]['targetRid'] = $targetRid;
			
			if ($targetRid === 0) {
				foreach (array('alarm_file', 'log_file', 'trends_file') as $filetype) {
					$uploaded[$mrn][$filetype]['status'] = "File not uploaded because the NVDC module couldn't find a record for this MRN that didn't already have files attached.";
				}
			} else {
				# have target record id, so let's upload files
				foreach ($files as $filetype => $info) {
					# a little confusing, but we have to save file to disk FROM the zip so we can Files::uploadFile
					$fileContents = $zip->getFromName($mrn . "/" . $info['filename']);
					$tmpFilename = APP_PATH_TEMP . "tmp_vent_file" . substr($info['filename'], -4);
					file_put_contents($tmpFilename, $fileContents);
					$tmpFileInfo = array(
						"name" => $info['filename'],
						"tmp_name" => $tmpFilename,
						"size" => filesize($tmpFilename)
					);
					$edocID = \Files::uploadFile($tmpFileInfo, $pid);
					$docHash = \Files::docIdHash($edocID);
					$uploaded[$mrn][$filetype]['edocID'] = $edocID;
					$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
							  VALUES ($pid, $eid, '$targetRid', '$filetype', '$edocID', null)";
					$query = db_query($sql);
					if (db_affected_rows($query) == 0) {
						$uploaded[$mrn][$filetype]['status'] = "Record with empty file fields found for this file, but error occured in attaching file. Please try again.";
					} else {
						$uploaded[$mrn][$filetype]['status'] = "File successfully attached to record ID: " . $targetRid;
						//Do logging of new record creation
						\Logging::logEvent($sql,"redcap_data","insert",$targetRid,"$table_pk = $targetRid","Create record");
						// Do logging of file upload
						\Logging::logEvent($sql,"redcap_data","doc_upload",$targetRid,"$filetype = $edocID","Upload document");
					}
				}
			}
		}
		$zip->close();
		unlink($_FILES['zip']['tmp_name']);
		$returnInfo['fileStatuses'] = $uploaded;
		return $returnInfo;
	}
	
	public function printMRNForm() {
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "stylesheet.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
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
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "stylesheet.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Attach Ventilator Files", $html);
		$body = "<div class='container'>
			<p>Select a zip archive of ventilator files to upload.</p>
			<form enctype='multipart/form-data' method='post'>
				<div class='custom-file'>
					<input type='file' name='zip' class='custom-file-input' id='zip'>
					<label class='custom-file-label' for='zip'>Choose zip file</label>
				</div>
				<button type='submit'>Upload</button>
			</form>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
	
	public function printUploadReport($statuses) {
		echo "<pre>";
		print_r($statuses);
		echo "</pre>";
	}
	
	public function removeAttachedFiles() {
		$pid = $this->getProjectSettings()['system-project']['value'];
		$q1 = db_query("SELECT * FROM redcap_data
						INNER JOIN redcap_edocs_metadata ON redcap_data.value=redcap_edocs_metadata.doc_id
						WHERE redcap_data .project_id=17 AND redcap_data.field_name in ('alarm_file', 'log_file', 'trends_file')");
		
		$edocIDs = [];
		while ($row = db_fetch_assoc($q1)) {
			unlink(EDOC_PATH . $row['stored_name']);
			$edocIDs[] = $row['doc_id'];
		}
		# delete associated redcap_edocs_metadata entries
		$q2 = db_query("DELETE FROM redcap_edocs_metadata WHERE doc_id in (" . implode(", ", $edocIDs) . ")");
		$countDocDeleted = db_affected_rows($q2);
		
		# delete associated redcap_data entries
		$q3 = db_query("DELETE FROM redcap_data WHERE project_id=$pid AND field_name in ('alarm_file', 'log_file', 'trends_file')");
		$countFieldDeleted = db_affected_rows($q3);
		
		echo "Removed $countDocDeleted documents and $countFieldDeleted field entries.";
	}
	
	public function test() {
		$statuses = [];
		$statuses['046466244'] = [];
		$statuses['046466244']['alarm_file'] = array(
			'filename' => "Alarm history Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_12_47.txt",
			'status' => "File successfully attached to record ID: 135"
		);
		$statuses['046466244']['log_file'] = array(
			'filename' => "Logbook Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_12_42.txt",
			'status' => "File successfully attached to record ID: 135"
		);
		$statuses['046466244']['trends_file'] = array(
			'filename' => "Trends Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_12_51.csv",
			'status' => "Record with empty file fields found for this file, but error occured in attaching file. Please try again."
		);
		$statuses['046466910'] = [];
		$statuses['046466910']['alarm_file'] = array(
			'filename' => "Alarm history Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_13_47.txt",
			'status' => "File successfully attached to record ID: 183"
		);
		$statuses['046466910']['log_file'] = array(
			'filename' => "Logbook Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_13_42.txt",
			'status' => "File successfully attached to record ID: 183"
		);
		$statuses['046466910']['trends_file'] = array(
			'filename' => "Trends Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_13_51.csv",
			'status' => "File successfully attached to record ID: 183"
		);
		$statuses['046466911'] = [];
		$statuses['046466911']['alarm_file'] = array(
			'filename' => "Alarm history Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_14_47.txt",
			'status' => "File not uploaded because the NVDC module couldn't find a record for this MRN that didn't already have files attached."
		);
		$statuses['046466911']['log_file'] = array(
			'filename' => "Logbook Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_14_42.txt",
			'status' => "File not uploaded because the NVDC module couldn't find a record for this MRN that didn't already have files attached."
		);
		$statuses['046466911']['trends_file'] = array(
			'filename' => "Trends Export 7 days Infinity Acute Care System Workstation Critical Care  Build 7016  ASCN-0001 15-Oct-2018 16_14_51.csv",
			'status' => "File not uploaded because the NVDC module couldn't find a record for this MRN that didn't already have files attached."
		);
		
		function getMRNSection($mrn) {
			$fileCount = count($statuses[$mrn]);
			$html = "<div class='row'>
				<div class='col-2'>
					<h5>MRN:</h5><span>046466244</span>
					<h5>File Type:</h5><span>alarm_file</span>
				</div>
				<table class='col-10 table'>
					<tbody>
						<tr>
							<th scope='row'>Filename:</th>
							<td>" . $statuses['046466244']['alarm_file']['filename'] . "</td>
						</tr>
						<tr>
							<th scope='row'>Status:</th>
							<td>" . $statuses['046466244']['alarm_file']['status'] . "</td>
						</tr>
					</tbody>
				</table>
			</div>";
		}
		
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "attachStyles.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Attach Ventilator Files", $html);
		$body = "<div class='container pt-5'>
			<h3 class='py-2'>Ventilator File Upload Report</h3>
			<div class='row'>
				<table class='col table'>
					<thead>
						<th>MRN</th>
						<th>Filename</th>
						<th>Status</th>
					</thead>
					<tbody>
						<th rowspan='4'>
							<span>046466244</span>
						</th>
						<tr>
							<td>" . $statuses['046466244']['alarm_file']['filename'] . "</td>
							<td>" . $statuses['046466244']['alarm_file']['status'] . "</td>
						</tr>
						<tr>
							<td>" . $statuses['046466244']['log_file']['filename'] . "</td>
							<td>" . $statuses['046466244']['log_file']['status'] . "</td>
						</tr>
						<tr>
							<td>" . $statuses['046466244']['trends_file']['filename'] . "</td>
							<td>" . $statuses['046466244']['trends_file']['status'] . "</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
}