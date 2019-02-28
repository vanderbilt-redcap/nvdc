$(function() {
	$("#loader").hide();
})

var NVDC = {};
NVDC.requestZip = function() {
	let mrns = $("[name=mrnList]").val().replace(/[^,\d]*/g, '').split(',');
	if (mrns.length <= 0) return;
	$("#noteHolder").empty()
	let jqxhr = $.post({
		url: window.location.href,
		data: {"mrnList": mrns},
		dataType: 'json',
		beforeSend: function (request, settings) {
			$("button").prop('disabled', true);
			$("#loader").fadeIn(200, NVDC.checkForZip);
			$("#noteHolder").empty().append("<span id='userNote'>Preparing download of attached files from records of given MRNs...</span>");
		},
		complete: function(response) {
			data = JSON.parse(response.responseText);
			if (data.download == true) {
				// nothing
			} else if (typeof data.message !== "undefined") {
				$("#noteHolder").empty().append("<span id='userNote'>" + data.message + "</span>");
			} else {
				$("#noteHolder").empty().append("<span id='userNote'>An error occured on the server. Please reload and try again. If that fails, contact your REDCap Administrator.</span>");
			}
		}
	});
}
NVDC.checkForZip = function() {
	$.post({
		url: window.location.href,
		data: {"checkForZip": true},
		complete: function(response) {
			if (response.responseText == 'true') {
				$("#loader").fadeOut(200, function() {
					$("button").prop('disabled', false);
					let dlAddress = window.location.href.replace('getProjectFiles', 'getZip');
					$("#noteHolder").empty().append("<span id='userNote'>Your download is ready, please click the link below.</span>");
					$("#noteHolder").append("<br /><a download href='" + dlAddress + " '>Download Files</a>");
				})
			} else {
				// else keep waiting
				setTimeout(NVDC.checkForZip, 5000);
			}
		}
	});
}