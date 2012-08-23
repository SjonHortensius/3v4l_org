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
		this.richEditor();

		$$('dd, dt').addEvent('click', this._clickDt);

		if ($$('input[type=submit]').length == 0 || !$$('input[type=submit]')[0].hasClass('busy'))
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
		$$('dd, dt').addEvent('click', this._clickDt);

		if (!html.match(/class="busy"/))
		{
			clearInterval(this.refreshTimer);
			$$('input[type=submit]')[0].removeClass('busy');
		}
	},

	_clickDt: function(e)
	{
		var dt = ('DT' == e.target.tagName) ? e.target : ('DD' == e.target.tagName ? e.target.getPrevious('dt') : e.target.getParent('dd').getPrevious('dt'));

		// Fix Firefox, will detect selecting text as click and this hash update removes the selection
		if (window.location.hash != '#'+dt.id)
			window.location.hash = '#'+dt.id;
	},

	richEditor: function()
	{
		if ($$('textarea').length == 0)
			return;

		CodeMirror.fromTextArea($$('textarea')[0],{
			autoClearEmptyLines: true,
			indentUnit: 4,
			lineNumbers: true,
			lineWrapping: true,
			matchBrackets: true,
			mode: 'application/x-httpd-php',
		});
	}
});

window.addEvent('domready', function(){ new eval_org; });