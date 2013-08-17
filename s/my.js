var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-31015527-1']);
_gaq.push(['_trackPageview']);

(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();

var evalOrg, eval_org = new Class({
	refreshTimer: null,

	initialize: function()
	{
		this.richEditor();

		$$('h1').addEvent('click', function(){ window.location = '/'; });
		$$('dd, dt').addEvent('click', this._clickDt);

		if ($$('input[type=submit]').length == 1 && $$('input[type=submit]')[0].hasClass('busy'))
			this.refreshTimer = setInterval(this.refresh.bind(this), 1000);

		events.each(function(ev){ ev.bind(this)(); }, this);
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
			autofocus: true,
		});

		$$('.CodeMirror').each(function (el){
			el.addEvent('keydown', function(ev){
				if (event.ctrlKey && event.keyIdentifier == 'Enter')
					$$('input[type=submit]')[0].click();
			})
		}); 
	},

	drawPerformanceGraphs: function(data, chart, table)
	{
/*
		var r = Raphael('chart', 600, 450), fin2 = function () {
			var y = [], res = [];
			for (var i = this.bars.length; i--;) {
				y.push(this.bars[i].y);
				res.push(this.bars[i].value || "0");
			}
			this.flag = r.popup(this.bars[0].x, Math.min.apply(Math, y), res.join(", ")).insertBefore(this);
		},fout2 = function () {
			this.flag.animate({opacity: 0}, 300, function () {this.remove();});
		},
		txtattr = { font: "13px sans-serif" };

		var rS = [], rU = [], rM = [];
		$$('table tbody tr').each(function(tr){
			rS.push(parseFloat(tr.childNodes[1].textContent.split(' ').shift()));
			rU.push(parseFloat(tr.childNodes[2].textContent.split(' ').shift()));
			rM.push(parseInt(tr.childNodes[3].textContent.split(' ').shift().split(',').join('')));
		});
		var leftgutter = 30, bottomgutter = 20, width = 600, height = 450;
		r.drawGrid(leftgutter + X * .5 + .5, topgutter + .5, width - leftgutter - X, height - topgutter - bottomgutter, 10, 10, "#000");
		r.barchart(50, 50, 550, 400, [rS, rU], {stacked: true, type: "soft", axis: "0 0 1 1"}).hoverColumn(fin2, fout2);
	return;
*/
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
	}
});

window.addEvent('domready', function(){ evalOrg = new eval_org; });
