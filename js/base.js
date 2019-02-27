var NVDC = {};
NVDC.requestZip = function() {
	let mrns = $("[name=mrnList]").val().replace(/[^,\d]*/g, '').split(',');
	if (mrns.length <= 0) return;
	let jqxhr = $.post({
		url: window.location.href,
		data: {"mrnList": mrns},
		complete: function(response) {
			if (response.responseText == 'ok') {
				console.log('ok');
			}
		}
	});
}
NVDC.checkForZip() = function() {
	$.post({
		url: window.location.href,
		data: {"checkForZip": true},
		complete: function(response) {
			if (response.responseText == 'true') {
				window.location=
			}
		}
	});
}