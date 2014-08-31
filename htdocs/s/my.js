var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-31015527-1']);
_gaq.push(['_trackPageview']);

(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();

NodeList.prototype.forEach = HTMLCollection.prototype.forEach = function(cb) {
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

		var pageHandler = 'handle'+ document.body.className[0].toUpperCase()+document.body.className.substr(1);
		if ('function' == typeof this[ pageHandler ])
			this[ pageHandler ]();
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

	var perfAddHeader = function(el, name, sum)
	{
		var m, td, i = document.createElement('i');

		i.className = 'icon-';

		el.addEventListener('click', function(e){
			el.classList.toggle('open');
			var row = el;

			while (row = row.nextSibling)
			{
				if (row.classList.contains('header'))
					break;

				row.classList.toggle('hack');
			}
		});

		el.className = 'header';
		el.appendChild(td = document.createElement('td')); td.textContent = name; td.insertBefore(i, td.firstChild);
		el.appendChild(td = document.createElement('td')); td.appendChild(document.createTextNode((sum.system / sum.count).toFixed(3)));
		el.appendChild(td = document.createElement('td')); td.appendChild(document.createTextNode((sum.user / sum.count).toFixed(3)));
		el.appendChild(td = document.createElement('td')); td.appendChild(document.createTextNode((sum.memory / sum.count).toFixed(2)));

		if (!sum.success)
			el.setAttribute('data-unsuccessful', '1');

		['system', 'user', 'memory'].forEach(function(type, index){
			m = document.createElement('meter');
			m.setAttribute('value', el.childNodes[1+index].textContent);

			for (var k in perfAggregates[type])
				m.setAttribute(k, perfAggregates[type][k]);

			el.childNodes[1+index].appendChild(m);
		});
	};

	this.handlePerf = function()
	{
		if (!perfAggregates)
			return setTimeout('this.handlePerf', 100);

		var version, previous, header, sum = {count: 0, system: 0, user: 0, memory: 0, success: 0}, nextIsHeader = false;
		document.querySelector('#tab table tbody').childNodes.forEach(function (tr){
			if (nextIsHeader)
			{
				nextIsHeader = false;
				return;
			}
			version = tr.firstChild.textContent.substr(0,4).replace(/\.$/, '');

			sum.count++;
			sum.system += parseFloat(tr.childNodes[1].textContent);
			sum.user   += parseFloat(tr.childNodes[2].textContent);
			sum.memory += parseFloat(tr.childNodes[3].textContent);
			sum.success = sum.success || !tr.hasAttribute('data-unsuccessful');

			if (version != previous)
			{
				if (previous)
				{
					perfAddHeader(header, previous, sum);
					sum = {count:0, system: 0, user: 0, memory: 0, success: 0};
				}

				header = tr.parentNode.insertBefore(document.createElement('tr'), tr);
				nextIsHeader = true;
			}

			['system', 'user', 'memory'].forEach(function(type, index){
				var m = document.createElement('meter');
				m.setAttribute('value', tr.childNodes[1+index].textContent);

				for (var k in perfAggregates[type])
					m.setAttribute(k, perfAggregates[type][k]);

				tr.childNodes[1+index].appendChild(m);
			});

			previous = version;
		});

		// Process last entry
		perfAddHeader(header, previous, sum);
	};
}).apply(evalOrg);

window.addEventListener('load', function(){ evalOrg.initialize(); });