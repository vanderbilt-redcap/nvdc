<?php
namespace Vanderbilt\NVDC;

class NVDC extends \ExternalModules\AbstractExternalModule {
	function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
		# Purpose of this hook: When a user enters a vent_ecn, we try to get the associated vent_sn and put it in place
		# We can get the ECN-SN pairs from a file in the file repository that has comment "ECN-SN pairs"
		
		$pairs = [];
		$query = "SELECT * FROM redcap_edocs_metadata
			WHERE project_id=$project_id AND doc_name LIKE '%ECN-SN_pairs%'
			ORDER BY stored_date DESC
			LIMIT 1";
		if ($result = db_query($query)) {
			$record = db_fetch_assoc($result);
			$pairsFilename = EDOC_PATH . $record['stored_name'];
			# check to make sure file exists
			if(!file_exists($pairsFilename)){
				
				echo "<script>console.log('file doesn\'t exist at: $pairsFilename')</script>";
				return;
			}
			
			# iterate through file contents line by line, finding/storing ECN SN pairs
			$pairsText = file_get_contents($pairsFilename);
			foreach(preg_split("/((\r?\n)|(\r\n?))/", $pairsText) as $line){
				preg_match_all("/ECN:\s+(\d+).*SN:\s*(\w+-\d+)/", $line, $matches);
				if($ecn = $matches[1][0] and $sn = $matches[2][0]){
					$pairs[$ecn] = $sn;
				}
			}
			
			$pairs_json = json_encode($pairs);
			
			echo "
			<script>
			// this script makes it so that the data entry form automatically sets ventilator Serial Number when ventilator ECN is supplied
			console.log('hook here');
			const ECN_SN_PAIRS = JSON.parse('$pairs_json');
			$('body').on('change', '[name=vent_ecn]', function() {
				var ecn = $(this).val();
				if(ECN_SN_PAIRS.hasOwnProperty(ecn)) {
					var sn = ECN_SN_PAIRS[ecn];
					$(\"[name='vent_sn']\").val(sn);
					console.log('sn found');
				} else {
					console.log('sn not found');
				}
			})
			</script>";
		}
	}
	
	public function makeZip($mrnList) {
		// downloadFiles will send the user a .zip of all the alarm, log, and trends file for their NICU Ventilator Data project
		// optionally, supply a $mrnList to filter to only those MRNs
		$pid = $this->getProjectId();
		$filterLogic = "isnumber([alarm_file]) or isnumber([log_file]) or isnumber([trends_file])";
		$edocInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, $filterLogic);
		
		// function printMem($sectionNote) {
			// echo("(peak/usage): (" . round(memory_get_peak_usage()/1024/1024, 0) . 'MB/' . round(memory_get_usage()/1024/1024, 0) . "MB)\t" . $sectionNote . "\n");
		// }
		
		// get array of ids to help us build sql string query
		$edocIDs = [];
		$mrnDict = [];
		foreach ($edocInfo as $recordId => $record) {
			$arr = current($record);	# we use current instead of having to determine the event ID
			if (empty($mrnList) or in_array((string) $arr['mrn'], $mrnList)) {
				if ($arr['alarm_file']) {
					$edocIDs[] = $arr['alarm_file'];
					$mrnDict[$arr['alarm_file']] = $arr['mrn'];
				}
				if ($arr['log_file']) {
					$edocIDs[] = $arr['log_file'];
					$mrnDict[$arr['log_file']] = $arr['mrn'];
				}
				if ($arr['trends_file']) {
					$edocIDs[] = $arr['trends_file'];
					$mrnDict[$arr['trends_file']] = $arr['mrn'];
				}
			}
		}
		
		// query redcap_edocs_metadata to get file names/paths to add to zip
		$sql = "SELECT * FROM redcap_edocs_metadata WHERE project_id=$pid and doc_id in (" . implode(", ", $edocIDs) . ")";
		$query = db_query($sql);
		$edocPaths = [];
		while($row = db_fetch_assoc($query)) {
			$edocPaths[] = EDOC_PATH . $row['stored_name'];
		}
		
		// if empty zip, say no files found
		if (empty($edocPaths)) {
			if (empty($mrnList)) {
				exit("<pre>The NVDC module couldn't find any alarm, logbook, or trends files attached to records in this project.</pre>");
			} else {
				echo "<pre>";
				echo "The NVDC module couldn't find any alarm, logbook, or trends files attached to records in this project for the specified MRNs:\n";
				foreach ($mrnList as $key => $mrn) {
					if (!$key=='all') {
						echo "$mrn\n";
					}
				}
				exit("</pre>");
			}
		} else {
			if ($mrnList['all']) {
				\Logging::logEvent($sql, "redcap_data", "DATA_EXPORT", $targetRid, "User downloaded files for all MRNs", "");
			} else {
				\Logging::logEvent($sql, "redcap_data", "DATA_EXPORT", $targetRid, "User downloaded files for MRNs: " . implode(', ', $mrnList), "");
			}
		}
		
		// # create zip file, open it
		// $zip = new \ZipArchive();
		// $fullpath = tempnam(EDOC_PATH,"");
		// $zip->open($fullpath, \ZipArchive::CREATE);
		
		
		
		$zipFileName = "NICU_Ventilator_Data_Files.zip";
		header('Content-Type: application/octet-stream');
		header('Content-Description: File Transfer');
		header('Content-Disposition: attachment; filename='.$zipFileName);
		// $fp = popen('tar cf - file1 ');
		readfile($fullpath);
		unlink($fullpath);
		// printMem("after zip unlink");
		
		// exit("</pre>");
	}
	
	public function printMakeZipReport($message) {
		$html = file_get_contents($this->getUrl("html" . DIRECTORY_SEPARATOR . "base.html"));
		$html = str_replace("STYLESHEET_FILEPATH", $this->getUrl("css" . DIRECTORY_SEPARATOR . "attachStyles.css"), $html);
		$html = str_replace("JS_FILEPATH", $this->getUrl("js" . DIRECTORY_SEPARATOR . "nvdc.js"), $html);
		$html = str_replace("{TITLE}", "Get Files By MRN", $html);
		$body = "<div class='container'>
			<div class='row justify-content-center pt-5 pb-3'>
				<h3>Get Files By MRN</h3>
			</div>
			<div class='row justify-content-center'>
				<p>$message</p>
			</div>
		</div>";
		$html = str_replace("{BODY}", $body, $html);
		
		return $html;
	}
	
	public function handleZip() {
		ini_set('memory_limit', '4G');
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
		
		$zipInfo = $_FILES['zip'];
		$zip = new \ZipArchive();
		$zip->open($zipInfo['tmp_name']);
		$pid = $this->getProjectId();
		$eid = $this->getFirstEventId();
		$names = [
			"Alarm" => "alarm_file",
			"Logbook" => "log_file",
			"Trends" => "trends_file"
		];
		if ($zip === true) {
			$returnInfo['zipError'] = "ERROR: REDCap couldn't open the uploaded zip file. Please try to re-archive the ventilator files and upload again.";
			return $returnInfo;
		}
		
		# iterate through files uploaded and see if we can attach them to applicable record
		for ($i = 0; $i < $zip->numFiles; $i++) {
			# figure out this file's name, serial, date, type
			$returnInfo[$i] = [];
			$filename = $zip->getNameIndex($i);
			if(strpos($filename, '/') !== false){
				$filename = substr($filename, strrpos($filename, '/') + 1);
			}
			$returnInfo[$i]['filename'] = $filename;
			
			# $filetype must be "Alarm", "Logbook", or "Trends"
			preg_match_all("/^(\w+)/", $filename, $matches);
			if(gettype($matches[1][0]) == 'string' && in_array($matches[1][0], ['Alarm', 'Logbook', 'Trends'])) {
				$filetype = $matches[1][0];
			} else {
				$returnInfo[$i]['status'] = "Attachment failed. File name must start with 'Alarm', 'Logbook', or 'Trends'.";
				continue;
			}
			
			# must be able to find a valid serial number (e.g., XXXX-####)
			preg_match_all("/\w{4}-\d{4}/", $filename, $matches);
			if(gettype($matches[0][0]) == 'string') {
				$ventSerial = $matches[0][0];
			} else {
				$returnInfo[$i]['status'] = "Attachment failed. File name must include a valid ventilator Serial Number (format: 4 letters, dash, 4 numbers, like XXXX-0000).\n" . print_r($matches, true);
				continue;
			}
			
			# must be able to find date
			preg_match_all("/\d+-[[:alpha:]]+-\d+/", $filename, $matches);
			if(gettype($matches[0][0]) == 'string') {
				$downloadDate = \DateTime::createFromFormat("j-M-Y", $matches[0][0])->format("Y-m-d");
			} else {
				$returnInfo[$i]['status'] = "Attachment failed. File name must include a valid date (example: January 1st, 2020 should be written as: 01-Jan-2020).\n" . print_r($matches, true);
				continue;
			}
			
			# diagnostics:
			// echo "filename: $filename - filetype: $filetype - ventSerial: $ventSerial - downloadDate: $downloadDate\n";
			
			if (!in_array($filetype, array("Alarm", "Logbook", "Trends"))) {
				if (!isset($uploaded[$i])) $uploaded[$i] = [];
				$name = "";
				$j = 1;
				while ($name === "") {
					$guess = "unrecognized_$j";
					if (!isset($uploaded[$i][$guess])) {
						$name = $guess;
					}
					$j++;
				}
				$returnInfo[$i]['status'] = "The NVDC module was unable to recognize this file. The first word of an uploaded file should be 'Alarm', 'Logbook', or 'Trends'.";
			} else {
				# go ahead and set status in case we do not find record, but we intend to overwrite
				$returnInfo[$i]['status'] = "<pre class='uploadStatusMessage'>The NVDC module couldn't find a record having a matching:\n\t[date_vent] = $downloadDate\n\t[vent_sn] = $ventSerial\nthat didn't already have a value for field [" . $names[$filetype] . "]</pre>";
				
				# see if we can find a record to upload to
				$filterLogic = "[vent_sn]='$ventSerial' and [date_vent]='$downloadDate' and ([" . $names[$filetype] . "] = \"\")";
				$recordsInfo = \REDCap::getData($pid, 'array', NULL, array('date_vent', 'vent_sn', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, $filterLogic);
				// exit("<pre>" . print_r($recordsInfo, true) . "\n$ventSerial\n$downloadDate</pre>");
				$targetRid = 0;
				foreach ($recordsInfo as $rid => $entry) {
					if ($rid > $targetRid) $targetRid = $rid;
				}
				if ($targetRid > 0) {
					$fileContents = $zip->getFromIndex($i);
					$tmpFilename = APP_PATH_TEMP . "tmp_NVDC_file" . substr($filename, -4);
					file_put_contents($tmpFilename, $fileContents);
					$tmpFileInfo = array(
						"name" => $filename,
						"tmp_name" => $tmpFilename,
						"size" => filesize($tmpFilename)
					);
					$edocID = \Files::uploadFile($tmpFileInfo, $pid);
					
					# insert to db
					$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
							  VALUES ($pid, $eid, '$targetRid', '" . $names[$filetype] . "', '$edocID', null)";
					$query = db_query($sql);
					if (db_affected_rows($query) == 0) {
						$returnInfo[$i]['status'] = "Record with empty file fields found for this file (record_id=$targetRid), but error occured in attaching file. Please try again.";
					} else {
						$returnInfo[$i]['status'] = "File successfully attached to record ID: " . $targetRid;
						\Logging::logEvent($sql, "redcap_data", "DOC_UPLOAD", $targetRid, "Added $filetype file to record $targetRid", "[{$names[$filetype]}] = $edocID");
					}
				}
			}
		}
		
		$zip->close();
		unlink($_FILES['zip']['tmp_name']);
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
				<p>Enter a comma-separated list of MRNs to get files for those patients.</p>
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
		# old html:
		// <div class='row justify-content-center'>
				// <p>Example folder stucture:</p>
			// </div>
// <div class='row justify-content-center'><pre class='col-4'>folder.zip/
	// [mrn1]/
		// Alarm file.txt
		// Logbook file.txt
	// [mrn2]/
		// Trends file.csv
		// Logbook file.txt</pre></div>
		// <div class='row justify-content-center'>
		
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
				<ul>
					<li>The NVDC module will attach each uploaded file to the record that has a matching [vent_sn], [date_vent], and does not already have a file of the same type attached.</li>
					<li>In the event multiple matching records are found, the module will attach the file to the record with the highest record_id.</li>
					<li>Files that are not .csv or .txt format will be ignored.</li>
					<li>File names must start with 'Alarm', 'Logbook', or 'Trends'.</li>
					<li>File names must include a ventilator serial number formatted as 4 letters, a dash, and for numbers (e.g., XXXX-####)</li>
					<li>File names must include a ventilator date formatted as dd-mmm-yyyy (e.g., 15-Dec-2019)</li>
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
		// function getMRNsection($statuses, $mrn) {
			// $fileCount = count($statuses["$mrn"]) + 1;
			// $html = "<th rowspan='$fileCount'>
						// <span>$mrn</span>
					// </th>";
			// foreach ($statuses[$mrn] as $filetype => $info) {
				// $class = (substr($info['status'], 0, 26) == "File successfully attached") ? "good" : "warn";
				// $html .= "<tr class='$class'>
						// <td>" . $info['filename'] . "</td>
						// <td>" . $info['status'] . "</td>
					// </tr>";
			// }
			// return $html;
		// }
		
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
						<th>Filename</th>
						<th>Status</th>
					</thead>
					<tbody>";
			for($i = 0; $i <= count($statuses)-1; $i++) {
				$arr = $statuses[$i];
				
				$body .= "
						<tr>
							<td>" . $arr['filename'] . "</td>
							<td>" . $arr['status'] . "</td>
						</tr>";
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
	
	private function removeAttachedFiles() {
		$pid = $this->getProjectId();
		$q1 = db_query("SELECT * FROM redcap_data
						INNER JOIN redcap_edocs_metadata ON redcap_data.value=redcap_edocs_metadata.doc_id
						WHERE redcap_data .project_id=$pid AND redcap_data.field_name in ('alarm_file', 'log_file', 'trends_file')");
		
		$edocIDs = [];
		while ($row = db_fetch_assoc($q1)) {
			unlink(EDOC_PATH . $row['stored_name']);
			$edocIDs[] = $row['doc_id'];
		}
		# delete associated redcap_edocs_metadata entries
		$q2 = db_query("DELETE FROM redcap_edocs_metadata WHERE project_id=$pid");
		$countDocDeleted = db_affected_rows($q2);
		
		# delete associated redcap_data entries
		$q3 = db_query("DELETE FROM redcap_data WHERE project_id=$pid AND field_name in ('alarm_file', 'log_file', 'trends_file')");
		$countFieldDeleted = db_affected_rows($q3);
		
		echo "Removed $countDocDeleted documents and $countFieldDeleted field entries.";
	}
	
	private function printRandomPage() {
		$str = "";
		$domain = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
		$length = strlen($domain);
		for ($i = 0; $i < 9999 ; $i++) {
			for ($j = 0; $j < 100; $j++) {
				$str .= $domain[rand(0, $length - 1)];
			}
			$str .= "\n";
		}
		return $str;
	}
	
	private function attachFakeFiles() {
		$pid = $this->getProjectId();
		$eid = $this->getFirstEventId();
		$sqlValues1 = [];
		for ($rid = 2; $rid < 101; $rid++) {
			foreach (['Alarm', 'Logbook', 'Trends'] as $i => $typeName) {
				$fileContents = $this->printRandomPage();
				$tmpFilename = APP_PATH_TEMP . "tmp_$typeName" . "_NVDC_file_$rid.txt";
				file_put_contents($tmpFilename, $fileContents);
				$tmpFileInfo = array(
					"name" => $typeName . " ASCN-0001 25-Feb-2019 16_06_02.txt",
					"tmp_name" => $tmpFilename,
					"size" => filesize($tmpFilename)
				);
				$edocID = \Files::uploadFile($tmpFileInfo, $pid);
				$sqlValues1[] = [$pid, $eid, $rid, '"' . ['alarm_file', 'log_file', 'trends_file'][$i] . '"', $edocID];
			}
		}
		
		# insert to db
		// $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) 
		//		  VALUES ($pid, $eid, '$targetRid', '" . $names[$filetype] . "', '$edocID', null)";
		$query = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES";
		foreach ($sqlValues1 as $arr) {
			$query .= " (" . implode(', ', $arr) . "),";
		}
		$query = substr($query, 0, -1);
		echo ("<pre>$query\n");
		// exit();
		$result = db_query($query);
		echo "\n";
		echo $conn->connect_errno;
		echo ("</pre>");
	}
}