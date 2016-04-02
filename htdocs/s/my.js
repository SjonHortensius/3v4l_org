(function(i,s,o,g,r,a,m){
	i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-31015527-1', 'auto');
ga('send', 'pageview');

NodeList.prototype.forEach =
 HTMLCollection.prototype.forEach =
 DOMTokenList.prototype.forEach = function (cb){
	Array.prototype.forEach.call(this, cb);
}

HTMLSelectElement.prototype.getSelected = function(){
	var s = [];
	for (var i = 0; i < this.length; i++) {
		if (this[i].selected)
			s.push(this[i].value);
	}
	return s;
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
		document.querySelectorAll('a[href^="http"]').forEach(function (el){
			el.setAttribute('target', '_blank');
		});

		document.body.classList.forEach(function(c){
			if ('function' == typeof this[ 'handle'+c.ucFirst() ])
				this[ 'handle'+c.ucFirst() ]();
		}.bind(this));
	};

	this.richEditor = function()
	{
		if (document.getElementsByName('code').length > 0)
			return;

		var code = document.getElementsByTagName('code')[0];
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

		ace.config.set('basePath', 'https://cdn.jsdelivr.net/ace/1.1.9/min/')
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

		this.editor.on('change', function(){
			document.querySelector('input[type=submit]').removeAttribute('disabled');
		});

		if (document.getElementById('archived_1'))
			document.getElementById('archived_1').addEventListener('change', function(){
				document.querySelector('input[type=submit]').removeAttribute('disabled');
			});
	};

	this.handleScript = function()
	{
		if ('undefined' != typeof refreshTimer)
			return;

		this.richEditor();

		if (document.querySelector('input[type=submit].busy'))
			refreshTimer = setInterval(this.refresh, 1000);

		document.body.addEventListener('keydown', function(e){
			if (13 != e.keyCode || !e.ctrlKey)
				return;

			// Trigger submitEvent manually
			var event = new Event('submit', {
				'view': window,
				'bubbles': true,
				'cancelable': true
			});
			// None of the handlers called preventDefault.
			if (document.forms[0].dispatchEvent(event))
				document.forms[0].submit();
		}.bind(this));

		localTime(function(el, d){
			el.innerHTML = ' @ '+ d.toString().split(' ').slice(0,5).join(' ');
		}, 'input + time');
	};

	this.handleRfc = function()
	{
		return this.handleOutput();
	};

	this.handleOutput = function()
	{
		document.getElementsByTagName('dt').forEach(function(el){
			el.addEventListener('click', function(e){ window.location.hash = '#'+ el.id; });
		});
/*
		document.querySelectorAll('a[href^="/assert"][data-hash]').forEach(function (el){
			el.addEventListener('click', function(e){
				//FIXME xhr submit
				e.preventDefault();
			});
		});
*/	};

	this.refresh = function()
	{
		refreshCount++;

		var xhr = new XMLHttpRequest();
		xhr.onload = _refresh;
		xhr.open('get', window.location.pathname);
		xhr.send();
	};

	var _refresh = function()
	{
		var tab = document.getElementById('tab');
		var t = this.responseText.match(/<ul id="tabs"[^>]*>([\s\S]*?)<\/ul>/);
		var r = this.responseText.match(/<div id="tab"[^>]*>([\s\S]*?)<\/div>/);
		if (!t || !r)
			window.location.reload();

		document.getElementById('tabs').innerHTML = t[1];

		if (document.body.classList.contains('output') && window.DOMParser)
			self._refreshOutput(tab, r[1]);
		else
		{
			tab.innerHTML = r[1];

			document.body.classList.forEach(function(c){
				if ('function' == typeof this[ 'handle'+c.ucFirst() ])
					this[ 'handle'+c.ucFirst() ]();
			}.bind(self));
		}

		if (!this.responseText.match(/class="busy"/) || refreshCount > 42)
		{
			clearInterval(refreshTimer);
			document.querySelector('input[type=submit].busy').classList.remove('busy');
			document.querySelector('#tabs.busy').classList.remove('busy');
		}
	};

	this._refreshOutput = function(tab, html)
	{
		var p = new DOMParser, doc = p.parseFromString(html, 'text/html'),
			dl = tab.getElementsByTagName('dl')[0],
			o = dl.getElementsByTagName('dt'),
			n = doc.getElementsByTagName('dt');

		n.forEach(function(ndt, i){
			if (o[i] && ndt.textContent == o[i].textContent)
				return;

			var ndd = document.importNode(ndt.nextSibling, true),
				ndt = document.importNode(ndt, true);

			ndt.addEventListener('click', function(e){ window.location.hash = '#'+ ndt.id; });

			if (o[i] && ndd.textContent == o[i].nextSibling.textContent)
			{
//console.log('update', o[i].textContent, 'to', ndt.textContent);
				dl.replaceChild(ndt, o[i]);
			}
			else
			{
//console.log('insert', ndt, o[i]?'before':'at the end', o[i]);
				if (!o[i])
				{
					dl.appendChild(ndt);
					dl.appendChild(ndd);
				}
				else
				{
					dl.insertBefore(ndt, o[i]);
					dl.insertBefore(ndd, ndt.nextSibling);
				}
			}
		});
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

	var localTime = function(cb, sel)
	{
		sel = sel || 'time';
		cb = cb || function(){};

		document.querySelectorAll(sel).forEach(function (el){
			var d = new Date(el.getAttribute('datetime'));
			el.setAttribute('title', d.toString());
			cb(el, d);
		});
	};

	this.handleIndex = function()
	{
		localTime();
	};

	this.handleLast = function()
	{
		localTime(function(el, d){
			function pad(d){ return ('0'+d).slice(-2); };
			el.innerHTML = pad(d.getHours()) +':'+ pad(d.getMinutes()) +':'+ pad(d.getSeconds());
		});
	};

	this.handlePerf = function()
	{
		if (!perfAggregates)
			return false

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

	this.handleBughunt = function()
	{
		if (0 == document.forms.length)
			return;

		document.forms[0].addEventListener('submit', function(e){
			e.preventDefault();

			var url = '/bughunt/'
				+ document.getElementById('versions').getSelected().join('+')
				+ '/'+ document.getElementById('controls').getSelected().join('+');
			window.location.href = url;
		});
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

	this.handleQuick = function()
	{
		document.querySelector('button[name=versions]').addEventListener('click', function(e){
			document.forms[0].action = 'new';
			var title = prompt('Please enter an optional title for this script');
			if (title != 'undefined' && title != 'false')
				document.querySelector('input[name=title]').value = title;
		});

		document.getElementById('version').addEventListener('change', function(){
			document.querySelector('input[type=submit]').removeAttribute('disabled');

			document.forms[0].action = 'quick/'+ document.getElementById('version').value;
		});
	};

	var btcAmountReceived = function()
	{
		return 0;
		// https://blockchain.info/q/getreceivedbyaddress/xyz / 100000000
	};
}).apply(evalOrg);

window.addEventListener('load', function(){ evalOrg.initialize(); });