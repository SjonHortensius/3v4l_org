var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-31015527-1']);
_gaq.push(['_trackPageview']);

(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();

var isBusy, eval_org = new Class({
	refreshTimer: null,

	initialize: function()
	{
		isBusy = $$('input[type=submit]')[0].hasClass('busy');

		window.addEvent('domready', this.richEditor.bind(this));

		if (!isBusy)
			return;

		this.refreshTimer = setInterval(this.refresh.bind(this), 1000);
	},

	refresh: function()
	{
		new Request.HTML({
			url: window.location.pathname,
			method: 'GET',
			update: $('preview'),
			onSuccess: this._refresh.bind(this),
			filter: 'dl > *',
			update: $$('dl')[0],
		}).send();
	},

	_refresh: function(tree, elements, html)
	{
		if (!isBusy)
		{
			this.isBusy = false;
			clearInterval(this.refreshTimer);
			$$('input[type=submit]')[0].removeClass('busy');
		}
	},

	richEditor: function()
	{
		CodeMirror.fromTextArea($$('textarea')[0],{
			autofocus: true,
			autoClearEmptyLines: true,
			indentUnit: 4,
			lineNumbers: true,
			lineWrapping: true,
			matchBrackets: true,
			mode: 'application/x-httpd-php',
			readOnly: (isBusy ? 'nocursor' : false)
		});
	}
});

new eval_org;