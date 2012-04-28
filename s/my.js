var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-31015527-1']);
_gaq.push(['_trackPageview']);

(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();

function init()
{
	var isBusy = document.getElementsByTagName('input')[0].className == 'busy';
	CodeMirror.fromTextArea(document.getElementById('code'),{
		lineNumbers: true,
		matchBrackets: true,
		mode: 'application/x-httpd-php',
		indentUnit: 4,
		indentWithTabs: true,
		enterMode: 'keep',
		tabMode: 'shift',
		autofocus: true,
		autoClearEmptyLines: true,
		lineWrapping: true,
		readOnly: (isBusy ? 'nocursor' : false),
	});

	if (isBusy)
		setTimeout('window.location.reload()', 1000);
}

window.onload = init;