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

		$$('h1').addEvent('click', function(){ window.location = '/'; });

		$$('dd, dt').addEvent('click', this._clickDt);

		if (typeof perfData != 'undefined')
			this.drawPerformanceGraphs();

		if ($$('input[type=submit]').length == 0 || !$$('input[type=submit]')[0].hasClass('busy'))
			return;

		this.refreshTimer = setInterval(this.refresh.bind(this), 1000);
	},

	refresh: function()
	{
		new Request.HTML({
			url: window.location.pathname,
			method: 'GET',
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
	},

	drawPerformanceGraphs: function()
	{
		var options =
		{
			seriesType: 'steppedArea',
			isStacked: true,
			series: {2: {type: 'line', targetAxisIndex: 1}},
			chartArea: {width: '75%', height: '75%'},
			vAxes: [
				{minValue: 0, format: '#.### s'},
				{minValue: 0, format: '#.### MiB'},
			],
		};

		new google.visualization.NumberFormat({fractionDigits: 3, suffix: ' s'}).format(perfData, 1);
		new google.visualization.NumberFormat({fractionDigits: 3, suffix: ' s'}).format(perfData, 2);
		new google.visualization.NumberFormat({fractionDigits: 3, suffix: ' MiB', groupingSymbol: '.'}).format(perfData, 3);

		var view = new google.visualization.DataView(perfData);

		var table = new google.visualization.Table(document.getElementById('perf_data'));
		table.draw(view, {sortColumn: 0});

		var chart = new google.visualization.ComboChart(document.getElementById('perf_chart'));
		chart.draw(perfData, options);

		google.visualization.events.addListener(table, 'sort',
			function(event)
			{
				perfData.sort([{column: event.column, desc: !event.ascending}]);
				chart.draw(perfData, options);
			});
	}
});

window.addEvent('domready', function(){ new eval_org; });