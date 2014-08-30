var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-31015527-1']);
_gaq.push(['_trackPageview']);

(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();

HTMLCollection.prototype.forEach = function(cb) {
	for (var i = 0; i < this.length; i++) {
		cb(this.item(i));
	}
}

var evalOrg = {};
(function()
{
	"use strict"

	var self = this,
		refreshTimer,
		refreshCount = 0;

	this.initialize = function()
	{
		this.richEditor();
		this.showFeedbackButton();

		document.querySelector('h1').addEventListener('click', function(e){ window.location = '/'; });

		if ('output' == document.body.className)
			this.handleScriptOutput();

		if (document.querySelector('input[type=submit].busy'))
			refreshTimer = setInterval(this.refresh, 1000);

		// externalLinks; iterate by casting to Array
		Array.prototype.slice.call(document.querySelectorAll('a[href^="http://"]')).forEach(function (el){
			el.setAttribute('target', '_blank');
		});

		if ('undefined' != typeof perfData)
			this.drawPerformanceGraphs(perfData, document.getElementById('chart'), document.getElementById('data'));
	};

	this.richEditor = function()
	{
		var textarea = document.getElementsByTagName('textarea')[0];

		if (!textarea)
			return;

		LRTEditor.initialize(
			textarea,
			['FormPlugin', 'HighlightPlugin', 'MinimalPlugin', 'UndoPlugin'],
			{highlightCallback: function(el){ sh_highlightElement(el, sh_languages['php']); } }
		);

		if (!LRTEditor.element)
			return;

		LRTEditor.element.focus();

		LRTEditor.element.addEventListener('keydown', function(e){
			if (13 == e.keyCode && e.ctrlKey)
				document.forms[0].submit();
		});
	};

	this.showFeedbackButton = function()
	{
		var UserVoice = window.UserVoice || [];
		UserVoice.push(['showTab', 'classic_widget', {
			mode: 'full',
			primary_color: '#cc6d00',
			link_color: '#007dbf',
			default_mode: 'support',
			forum_id: 219058,
			support_tab_name: 'Report Bug',
			feedback_tab_name: 'Request Feature',
			tab_label: 'Bugs & Features',
			tab_color: '#cc6d00',
			tab_position: 'middle-right',
			tab_inverted: false
		}]);
	};

	this.handleScriptOutput = function()
	{
		document.getElementsByTagName('dt').forEach(function(el){
			el.addEventListener('click', function(e){ window.location.hash = '#'+ el.id; });
		});

		document.getElementsByTagName('dd').forEach(function(el){
			el.addEventListener('click', function(e){
				var node = e.target;
				while (node && node.tagName != 'DD')
					node = node.parentNode;

				if (node)
					window.location.hash = '#'+ node.previousSibling.id;
			});
		});
	};

	this.refresh = function()
	{
		refreshCount++;

		var xhr = new XMLHttpRequest();
		xhr.onload = _refresh;
		xhr.open('get', window.location.pathname);
		xhr.send();
	}

	var _refresh = function()
	{
		var r = this.responseText.match(/<div id="tab">([\s\S]*?)<\/div>/);
		if (!r)
			window.location.reload();

		document.getElementById('tab').innerHTML = r[1];

		self.handleScriptOutput();

		if (!this.responseText.match(/class="busy"/) || refreshCount > 42)
		{
			clearInterval(refreshTimer);
			document.querySelector('input[type=submit].busy').classList.remove('busy');
		}
	};

	this.drawPerformanceGraphs = function(data, chart, table)
	{
		data.unshift(['Version', 'System time', 'User time', 'Max. memory usage']);
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
			colors: ['#36c', '#3c6', '#f90']
		}, perfData = google.visualization.arrayToDataTable(data);

		new google.visualization.NumberFormat({fractionDigits: 3, suffix: ' s'}).format(perfData, 1);
		new google.visualization.NumberFormat({fractionDigits: 3, suffix: ' s'}).format(perfData, 2);
		new google.visualization.NumberFormat({fractionDigits: 3, suffix: ' MiB', groupingSymbol: '.'}).format(perfData, 3);

		var view = new google.visualization.DataView(perfData);

		var chart = new google.visualization.ComboChart(chart);
		chart.draw(perfData, options);

		if (!table)
			return;

		var table = new google.visualization.Table(table);
		table.draw(view, {sortColumn: 0});

		google.visualization.events.addListener(table, 'sort',
			function(event)
			{
				perfData.sort([{column: event.column, desc: !event.ascending}]);
				chart.draw(perfData, options);
			});
	};
}).apply(evalOrg);

window.addEventListener('load', function(){ evalOrg.initialize(); });
