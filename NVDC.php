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
		$filterLogic = "isnumber([alarm_file]) or isnumber([log_file]) or isnumber([trends_file])";
		$edocInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, $filterLogic);
		
		// // diagnostics
		// echo "<pre>";
		// print_r($edocInfo);
		// echo "</pre>";
		// exit;
		
		// # get array of ids to help us build sql string query
		$edocIDs = [];
		foreach ($edocInfo as $recordId => $record) {
			$arr = current($record);	# we use current instead of having to determine the event ID
			if ($mrnList['all'] or in_array((string) $arr['mrn'], $mrnList)) {
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
				echo "<pre>";
				echo "The NVDC module couldn't find any alarm, logbook, or trends files attached to records in this project for the specified MRNs:\n";
				foreach ($mrnList as $key => $mrn) {
					if (!$key=='all') {
						echo "$mrn\n";
					}
				}
				echo "</pre>";
				$zip->close();
				unlink($fullpath);
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
				$returnInfo['zipError'] = "ERROR: REDCap cannot upload the ventilator data files because the zip file is " . round($zip_size/1024/1024/1024, 3) . " GB which exceeds the 2 GB limit.";
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
			preg_match_all("/(\d+)\/(.+\.csv|.+\.txt)/", $zip->getNameIndex($i), $matches);
			$mrn = $matches[1][0];
			$filename = $matches[2][0];
			preg_match_all("/^(\w+)/", $filename, $matches);
			# $filetype should ideally be "Alarm", "Logbook", or "Trends"
			$filetype = $matches[1][0];
			if (!$mrn or !$filename or !$filetype) continue;
			
			// // diagnostics
			// echo "fileInfo: " . print_r($fileInfo, true) . "\n";
			// echo "mrn: $mrn\n";
			// echo "filename: $filename\n";
			// echo "filetype: $filetype\n\n";
			
			# add filenames for recognized files, add filename and status for unrecognized files
			if (!in_array($filetype, array("Alarm", "Logbook", "Trends"))) {
				// echo "filetype not recognized: $filetype\n";
				if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
				$name = "";
				$j = 1;
				while ($name === "") {
					$guess = "unrecognized_$j";
					if (!isset($uploaded[$mrn][$guess])) {
						$name = $guess;
					}
					$j++;
				}
				$uploaded[$mrn][$name] = array();
				$uploaded[$mrn][$name]['filename'] = $filename;
				$uploaded[$mrn][$name]['status'] = "The NVDC module was unable to recognize this file. The first word of an uploaded file should be 'Alarm', 'Logbook', or 'Trends'.";
			} else {
				if ($filetype == "Alarm") {
					if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
					$uploaded[$mrn]["alarm_file"] = array();
					$uploaded[$mrn]["alarm_file"]['filename'] = $filename;
					$uploaded[$mrn]["alarm_file"]['zip_index'] = $i;
				}
				if ($filetype == "Logbook") {
					if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
					$uploaded[$mrn]["log_file"] = array();
					$uploaded[$mrn]["log_file"]['filename'] = $filename;
					$uploaded[$mrn]["log_file"]['zip_index'] = $i;
				}
				if ($filetype == "Trends") {
					if (!isset($uploaded[$mrn])) $uploaded[$mrn] = [];
					$uploaded[$mrn]["trends_file"] = array();
					$uploaded[$mrn]["trends_file"]['filename'] = $filename;
					$uploaded[$mrn]["trends_file"]['zip_index'] = $i;
				}
			}
		}
		
		# look for appropriate records per mrn
		$pid = $this->getProjectId();
		$eid = $this->getFirstEventId();
		foreach($uploaded as $mrn => $files) {
			$recordsInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'date_vent', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, "[mrn]='$mrn'");
			
			// // for diagnostics/testing:
			// $uploaded[$mrn]['existingRecords'] = $recordsInfo;
			
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
			// // for diagnostics/testing:
			// $uploaded[$mrn]['targetRid'] = $targetRid;
			
			if ($targetRid === 0) {
				foreach ($uploaded[$mrn] as $filetype => $info) {
					if (!isset($info['status'])) {
						$uploaded[$mrn][$filetype]['status'] = "File not uploaded because the NVDC module couldn't find a record for this MRN that didn't already have files attached.";
					}
				}
			} else {
				# have target record id, so let's upload files
				foreach ($files as $filetype => $info) {
					# skip if status already set
					if (isset($info['status'])) {
						continue;
					}
					# a little confusing, but we have to save file to disk FROM the zip so we can Files::uploadFile
					$path = $mrn . "/" . $info['filename'];
					$fileContents = $zip->getFromIndex($info['zip_index']);
					$tmpFilename = APP_PATH_TEMP . "tmp_vent_file" . substr($info['filename'], -4);
					file_put_contents($tmpFilename, $fileContents);
					$tmpFileInfo = array(
						"name" => $info['filename'],
						"tmp_name" => $tmpFilename,
						"size" => filesize($tmpFilename)
					);
					$edocID = \Files::uploadFile($tmpFileInfo, $pid);
					// // for diagnostics/testing:
					// $docHash = \Files::docIdHash($edocID);
					// $uploaded[$mrn][$filetype]['edocID'] = $edocID;
					$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
							  VALUES ($pid, $eid, '$targetRid', '$filetype', '$edocID', null)";
					$query = db_query($sql);
					if (db_affected_rows($query) == 0) {
						$uploaded[$mrn][$filetype]['status'] = "Record with empty file fields found for this file, but error occured in attaching file. Please try again.";
					} else {
						$uploaded[$mrn][$filetype]['status'] = "File successfully attached to record ID: " . $targetRid;
						//Do logging of new record creation
						\Logging::logEvent($sql,"redcap_data","insert",$targetRid,"record = $targetRid","Create record");
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
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "attachStyles.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Get Files By MRN", $html);
		$body = "<div class='container'>
			<div class='row justify-content-center pt-5 pb-3'>
				<h3>Get Files By MRN</h3>
			</div>
			<div class='row justify-content-center'>
				<p>Enter one MRN or a list of comma-separated MRNs to get files for.</p>
			</div>
			<form method='post'>
				<div class='form-group'>
					<label for='mrnList'>MRN(s)</label>
					<input type='text' class='form-control mb-2' name='mrnList' aria-describedby='mrnHelp' placeholder='012345678, 123456789'>
				</div>
				<div>
					<button type='submit' class='btn btn-primary'>Submit</button>
				</div>
			</form>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
	
	public function printUploadForm() {
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "attachStyles.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Attach Ventilator Files", $html);
		$body = "<div class='col-6 container'>
			<div class='row justify-content-center pt-5'>
				<h3>Upload Ventilator Files</h3>
			</div>
			<div class='row justify-content-center pt-3'>
				<p>Select a zip archive of ventilator files to upload.</p>
			</div>
			<div class='row justify-content-center'>
				<p>Note: The NVDC module will try to upload to the latest record that has no attached alarm, log, or trends files.</p>
			</div>
			<div class='row justify-content-center'>
				<p>Example folder stucture:</p>
			</div>
<div class='row justify-content-center'><pre class='col-4'>folder.zip/
	[mrn1]/
		Alarm file.txt
		Logbook file.txt
	[mrn2]/
		Trends file.csv
		Logbook file.txt</pre></div>
			<div class='row justify-content-center'>
				<ul>
					<li>Files that are not .csv or .txt format will be ignored.</li>
					<li>You may choose the filenames but they all should start with 'Alarm', 'Logbook', or 'Trends'.</li>
					<li>Use the relevant MRN as folder names for the files included.</li>
				</ul>
			</div>
			<div class='row justify-content-center'>
				<form class='col-6' enctype='multipart/form-data' method='post'>
					<div class='custom-file'>
						<input type='file' name='zip' class='custom-file-input' id='zip'>
						<label class='custom-file-label' for='zip'>Choose zip file</label>
					</div>
					<div class='row justify-content-center '>
						<button class='mt-4 px-5 btn btn-lg btn-secondary' type='submit'>Upload</button>
					</div>
				</form>
			</div>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
	
	public function printUploadReport($statuses) {
		function getMRNsection($statuses, $mrn) {
			$fileCount = count($statuses["$mrn"]) + 1;
			$html = "<th rowspan='$fileCount'>
						<span>$mrn</span>
					</th>";
			foreach ($statuses[$mrn] as $filetype => $info) {
				$class = (substr($info['status'], 0, 26) == "File successfully attached") ? "good" : "warn";
				$html .= "<tr class='$class'>
						<td>" . $info['filename'] . "</td>
						<td>" . $info['status'] . "</td>
					</tr>";
			}
			return $html;
		}
		
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "attachStyles.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Attach Ventilator Files", $html);
		
		$body = "";
		if (!$statuses['zipError']) {
			$body = "
			<div class='container pt-5'>
				<h3 class='py-2'>Ventilator File Upload Report</h3>
				<table class='col table'>
					<thead>
						<th>MRN</th>
						<th>Filename</th>
						<th>Status</th>
					</thead>
					<tbody>";
			foreach ($statuses['fileStatuses'] as $mrn => $files) {
				$body .= getMRNsection($statuses['fileStatuses'], $mrn);
			}
			$body .="</tbody>
				</table>
			</div>";
		} else {
			$body = "<div class='container pt-5'>
				<h3 class='py-2'>Ventilator File Upload Report</h3>
				<p>" . $statuses['zipError'] . "</p>
			</div>";
		}
		
		$html = str_replace("{BODY}", $body, $html);
		return $html;
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
		
		function getMRNsection($statuses, $mrn) {
			$fileCount = count($statuses["$mrn"]) + 1;
			$html = "<th rowspan='$fileCount'>
						<span>$mrn</span>
					</th>";
			foreach ($statuses[$mrn] as $filetype => $info) {
				$class = (substr($info['status'], 0, 26) == "File successfully attached") ? "good" : "warn";
				$html .= "<tr class='$class'>
						<td>" . $info['filename'] . "</td>
						<td>" . $info['status'] . "</td>
					</tr>";
			}
			return $html;
		}
		
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "attachStyles.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Attach Ventilator Files", $html);
		$body = "
		<div class='container pt-5'>
			<h3 class='py-2'>Ventilator File Upload Report</h3>
			<table class='col table'>
				<thead>
					<th>MRN</th>
					<th>Filename</th>
					<th>Status</th>
				</thead>
				<tbody>";
		foreach ($statuses as $mrn => $files) {
			$body .= getMRNsection($statuses, $mrn);
		}
		$body .="</tbody>
			</table>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
}