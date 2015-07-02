(function(i,s,o,g,r,a,m){
	i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-31015527-1', 'auto');
ga('send', 'pageview');

NodeList.prototype.forEach = HTMLCollection.prototype.forEach = function(cb) {
	for (var i = 0; i < this.length; i++) {
		cb(this.item(i));
	}
}

String.prototype.ucFirst = function(){
	return this.charAt(0).toUpperCase() + this.slice(1);
}

var evalOrg = {};
(function()
{
	"use strict"

	var self = this,
		editor,
		refreshTimer,
		refreshCount = 0;

	this.initialize = function()
	{
		this.richEditor();

		if (document.querySelector('input[type=submit].busy'))
			refreshTimer = setInterval(this.refresh, 1000);

		document.querySelectorAll('a[href^="http"]').forEach(function (el){
			el.setAttribute('target', '_blank');
		});

		if (document.forms.length > 0)
		{
			document.body.addEventListener('keydown', function(e){
				if (13 != e.keyCode || !e.ctrlKey)
					return;

				document.getElementsByName('code')[0].value = this.editor.getValue();
				// Won't trigger submit-event!
				document.forms[0].submit();
			}.bind(this));
		}

		var pageHandler = 'handle'+ document.body.classList[0].ucFirst();
		if ('function' == typeof this[ pageHandler ])
			this[ pageHandler ]();
	};

	this.richEditor = function()
	{
		var code = document.getElementsByTagName('code')[0];

		if (!code)
			return;

		var textarea = document.createElement('textarea');
		textarea.name = 'code';
		textarea.value = code.textContent;
		code.parentNode.insertBefore(textarea, code);

		// Disable ace for touch-devices; see https://github.com/ajaxorg/ace/issues/37
		if ("ontouchstart" in window)
		{
			code.style.display = 'none';
			return;
		}

		textarea.style.display = 'none';

		ace.config.set('basePath', 'http://cdn.jsdelivr.net/ace/1.1.9/min/')
		this.editor = ace.edit(code);
		this.editor.setTheme('ace/theme/chrome');
		this.editor.setShowPrintMargin(false);
		this.editor.setOption('maxLines', Infinity);
		this.editor.session.setMode('ace/mode/php');
		this.editor.session.setUseWrapMode(true);

		if (document.body.classList.contains('index'))
		{
			this.editor.focus();
			this.editor.gotoLine(this.editor.session.getLength());
		}

		if (document.querySelector('input[type=submit]'))
			document.querySelector('input[type=submit]').setAttribute('disabled', 'disabled');

		document.forms[0].addEventListener('submit', function(e){
			textarea.value = this.editor.getValue();
		}.bind(this));

		code.addEventListener('keydown', function(e){
			document.querySelector('input[type=submit]').removeAttribute('disabled');
		});
	};

	this.handleRfc = function()
	{
		return this.handleOutput();
	},

	this.handleQuick = function()
	{
		document.querySelector('button[name=versions]').addEventListener('click', function(e){
			document.forms[0].action = '/new';
			var title = prompt('Please enter an optional title for this script');
			if (title != 'undefined' && title != 'false')
				document.querySelector('input[name=title]').value = title;
		});
	},

	this.handleOutput = function()
	{
		document.getElementsByTagName('dt').forEach(function(el){
			el.addEventListener('click', function(e){ window.location.hash = '#'+ el.id; });
		});

		document.querySelectorAll('a[href^="/assert"][data-hash]').forEach(function (el){
			el.addEventListener('click', function(e){
				//FIXME xhr submit
				e.preventDefault();
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

		var t = this.responseText.match(/<ul id="tabs".*?>([\s\S]*?)<\/ul>/);
		if (t)
			document.getElementById('tabs').innerHTML = t[1];

		var pageHandler = 'handle'+ document.body.className.ucFirst();
		if ('function' == typeof self[ pageHandler ])
			self[ pageHandler ]();

		if (!this.responseText.match(/class="busy"/) || refreshCount > 42)
		{
			clearInterval(refreshTimer);
			document.querySelector('input[type=submit].busy').classList.remove('busy');
			document.querySelector('#tabs.busy').classList.remove('busy');
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

				row.classList.toggle('open');
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
			m.setAttribute('value', sum[type] / sum.count);

			for (var k in perfAggregates[type])
				m.setAttribute(k, perfAggregates[type][k]);

			el.childNodes[1+index].appendChild(m);
		});
	};

	this.handlePerf = function()
	{
		if (!perfAggregates)
			return setTimeout('this.handlePerf', 100);

		var version, previous, header, sum = {count: 0, system: 0, user: 0, memory: 0, success: 0};
		document.querySelector('#tab table tbody').childNodes.forEach(function (tr){
			// We modify the Node we traverse, not so smart...
			if (tr.querySelector('meter'))
				return;

			version = tr.firstChild.textContent.substr(0,4).replace(/\.$/, '');

			if (version != previous)
			{
				if (previous)
				{
					perfAddHeader(header, previous, sum);
					sum = {count:0, system: 0, user: 0, memory: 0, success: 0};
				}

				header = tr.parentNode.insertBefore(document.createElement('tr'), tr);
			}

			sum.count++;
			sum.system += parseFloat(tr.childNodes[1].textContent);
			sum.user   += parseFloat(tr.childNodes[2].textContent);
			sum.memory += parseFloat(tr.childNodes[3].textContent);
			sum.success = sum.success || !tr.hasAttribute('data-unsuccessful');

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

	this.handleSearch = function()
	{
		document.forms[0].addEventListener('submit', function(e){
			e.preventDefault();

			var url = '/search/'+ document.getElementById('operation').value;
			if (document.getElementById('operand').value.length > 0)
				url += '/'+ document.getElementById('operand').value;
			window.location.href = url;
		});

		document.querySelector('select[name=operation]').addEventListener('change', function(e){
			document.querySelector('input[name=operand]').classList.toggle('noOperand', (-1 == haveOperand.indexOf(e.target.value)));
		});

		if (document.querySelector('svg'))
			this.handleTagcloud();
	};

	this.handleTagcloud = function()
	{
		var ns = 'http://www.w3.org/1999/xlink', svgNs = 'http://www.w3.org/2000/svg';
		document.querySelector('svg').setAttribute('xmlns:xlink', ns);

		document.querySelectorAll('g text').forEach(function (el){
			var w = document.createElementNS(svgNs, 'a');
			w.setAttributeNS(ns, 'xlink:href', '/search/DO_FCALL/'+ el.textContent);
			w.setAttributeNS(ns, 'target', '_top');
			w.appendChild(el.cloneNode(true));
			el.parentNode.replaceChild(w, el);
		});
	};

	var btcAmountReceived = function()
	{
		return 0;
		// https://blockchain.info/q/getreceivedbyaddress/3DJhjy98RiQRc7751B4PPugMkG3BGVogrX / 100000000
	};
}).apply(evalOrg);

window.addEventListener('load', function(){ evalOrg.initialize(); });