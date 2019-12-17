$(function() {
	$("#loader").hide();
})

var NVDC = {};
NVDC.sendMRNs = function() {
	let mrns = $("[name=mrnList]").val().replace(/[^,\d]*/g, '').split(',');
	let startRecord = $('[name=startRecord]').val();
	let endRecord = $('[name=endRecord]').val();
	if (mrns.length <= 0) return;
	$("#noteHolder").empty()
	let jqxhr = $.post({
		url: window.location.href,
		data: {"mrnList": mrns, "startRecord": startRecord, "endRecord": endRecord},
		dataType: 'json',
		beforeSend: function (request, settings) {
			$("button").prop('disabled', true);
		},
		complete: function(response) {
		//	 console.log(response);
			data = JSON.parse(response.responseText);
			if (typeof data.message !== "undefined") {
				if (typeof data.edocs !== "undefined") {
					// spin loader and ask server to make zip
					$("#noteHolder").empty().append("<span id='userNote'>" + data.message + "</span>");
					$("#loader").fadeIn(200);
					NVDC.requestZip(data.edocs);
				} else {
					// display diagnostic message -- either no mrns found or no attached files found
					$("#noteHolder").empty().append("<span id='userNote'>" + data.message + "</span>");
					$("button").prop('disabled', false);
				}
			} else {
				// error occured
				$("#noteHolder").empty().append("<span id='userNote'>An error occured on the server. Please reload this page and try again. If that fails, contact your REDCap Administrator.</span>");
			}
		}
	});
}
NVDC.requestZip = function(edocs) {
	console.log(edocs);
	let jqxhr = $.post({
		url: window.location.href,
		data: {"makeZip": true, "edocs": JSON.stringify(edocs)},
		dataType: 'json',
		timeout: (1000 * 60 * 15),	// 15 minute timeout
		complete: function(response) {
		//	 console.log(response);
			data = JSON.parse(response.responseText);
			if (data.done == true) {
				$("#loader").fadeOut(200, function() {
					$("button").prop('disabled', false);
					let dlAddress = window.location.href.replace('getProjectFiles', 'getZip');
					$("#noteHolder").empty().append("<span id='userNote'>Your download is ready, please click the link below.</span>");
					$("#noteHolder").append("<br /><a download href='" + dlAddress + " '>Download Files</a>");
				})
			} else {
				// error occured
				$("#noteHolder").empty().append("<span id='userNote'>An error occured during the .zip creation. Please reload this page and try again. If that fails, contact your REDCap Administrator.</span>");
			}
		}
	})
}





// NVDC.checkForZip = function() {
	// $.post({
		// url: window.location.href,
		// data: {"checkForZip": true},
		// complete: function(response) {
			// data = JSON.parse(response.responseText);
			// if (data.) {
				// $("#loader").fadeOut(200, function() {
					// $("button").prop('disabled', false);
					// let dlAddress = window.location.href.replace('getProjectFiles', 'getZip');
					// $("#noteHolder").empty().append("<span id='userNote'>Your download is ready, please click the link below.</span>");
					// $("#noteHolder").append("<br /><a download href='" + dlAddress + " '>Download Files</a>");
				// })
			// } else {
				// // else keep waiting
				// setTimeout(NVDC.checkForZip, 5000);
			// }
		// }
	// });
// }