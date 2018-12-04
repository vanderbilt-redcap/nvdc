$("body").on("change", "#zip", function() {
	var filename = $(this).val();
	filename = filename.substr(filename.lastIndexOf("/")+1);
	filename = filename.substr(filename.lastIndexOf("\\")+1);
	$("label").html(filename);
	$("button").removeClass('btn-secondary');
	$("button").removeClass('btn-primary');
	$("button").addClass('btn-primary');
});