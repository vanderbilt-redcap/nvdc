<?php
namespace Vanderbilt\NVDC;

class NVDC extends \ExternalModules\AbstractExternalModule {
	public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}
	
	// function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
		# when a user enters a vent_ecn, we try to get the associated vent_sn and put it in place
		// $("body").on("change", "[name=vent_ecn]", function() {
			
		// })
	// }
	
	public function downloadFiles($mrnList = ['all' => true]) {
		// # downloadFiles will send the user a .zip of all the alarm, log, and trends file for their NICU Ventilator Data project
		// # optionally, supply a $mrnList to filter to only those MRNs
		$pid = $this->getProjectId();
		$filterLogic = "isnumber([alarm_file]) or isnumber([log_file]) or isnumber([trends_file])";
		$edocInfo = \REDCap::getData($pid, 'array', NULL, array('mrn', 'alarm_file', 'log_file', 'trends_file'), NULL, NULL, NULL, NULL, NULL, $filterLogic);
		
		// // diagnostics
		// echo "<pre>";
		// print_r($thisMRNs);
		// echo "</pre>";
		// exit;
		
		// # get array of ids to help us build sql string query
		$edocIDs = [];
		$mrnDict = [];
		foreach ($edocInfo as $recordId => $record) {
			$arr = current($record);	# we use current instead of having to determine the event ID
			if ($mrnList['all'] or in_array((string) $arr['mrn'], $mrnList)) {
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
				$zip->addFromString($mrnDict[$row['doc_id']] . " " . $row['doc_name'], $edoc);
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
		} else {
			if ($mrnList['all']) {
				\Logging::logEvent($sql, "redcap_data", "DATA_EXPORT", $targetRid, "User downloaded files for all MRNs", "");
			} else {
				\Logging::logEvent($sql, "redcap_data", "DATA_EXPORT", $targetRid, "User downloaded files for MRNs: " . implode(', ', $mrnList), "");
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
		
		// echo "<pre>";
		# iterate through files uploaded and see if we can attach them to applicable record
		for ($i = 0; $i < $zip->numFiles; $i++) {
			# figure out this file's name, serial, date, type
			$fileInfo = $zip->statIndex($i);
			preg_match_all("/\/(.+\.csv|.+\.txt)/", $zip->getNameIndex($i), $matches);
			$filename = $matches[1][0];
			preg_match_all("/^(\w+)/", $filename, $matches);
			# $filetype should ideally be "Alarm", "Logbook", or "Trends"
			$filetype = $matches[1][0];
			preg_match_all("/\w{4}-\d{4}/", $filename, $matches);
			$ventSerial = $matches[0][0];
			preg_match_all("/\d+-\w+-\d+/", $filename, $matches);
			$downloadDate = \DateTime::createFromFormat("j-M-Y", $matches[0][0])->format("Y-m-j");
			if (!$filename or !$filetype or !$ventSerial or !$downloadDate) continue;
			
			# diagnostics:
			// echo "filename: $filename - filetype: $filetype - ventSerial: $ventSerial - downloadDate: $downloadDate\n";
			
			# add filenames for recognized files, add filename and status for unrecognized files
			$returnInfo[$i] = [];
			$returnInfo[$i]['filename'] = $filename;
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
			foreach ($statuses as $i => $arr) {
				if ($i == 'zipError') continue;
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
	
	public function removeAttachedFiles() {
		$pid = 30;
		$q1 = db_query("SELECT * FROM redcap_data
						INNER JOIN redcap_edocs_metadata ON redcap_data.value=redcap_edocs_metadata.doc_id
						WHERE redcap_data .project_id=$pid AND redcap_data.field_name in ('alarm_file', 'log_file', 'trends_file')");
		
		$edocIDs = [];
		while ($row = db_fetch_assoc($q1)) {
			unlink(EDOC_PATH . $row['stored_name']);
			$edocIDs[] = $row['doc_id'];
		}
		# delete associated redcap_edocs_metadata entries
		$q2 = db_query("DELETE FROM redcap_edocs_metadata WHERE project_id=30");
		$countDocDeleted = db_affected_rows($q2);
		
		# delete associated redcap_data entries
		$q3 = db_query("DELETE FROM redcap_data WHERE project_id=$pid AND field_name in ('alarm_file', 'log_file', 'trends_file')");
		$countFieldDeleted = db_affected_rows($q3);
		
		echo "Removed $countDocDeleted documents and $countFieldDeleted field entries.";
	}
}