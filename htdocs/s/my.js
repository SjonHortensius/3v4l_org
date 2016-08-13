if ("undefined" == typeof ga)
{
	(function(i,s,o,g,r,a,m){
		i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
}

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

function $(s){
	return document.querySelector(s);
}
function $$(s){
	return document.querySelectorAll(s);
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
		$$('a[href^="http"]').forEach(function (el){
			el.setAttribute('target', '_blank');
		});

		document.body.classList.forEach(function(c){
			if ('function' == typeof this[ 'handle'+c.ucFirst() ])
				this[ 'handle'+c.ucFirst() ]();
		}.bind(this));

		// Allow #key=value pairs to specify defaults for certain form inputs
		if (document.location.hash.length>1 && document.location.hash.match(/^#[a-z0-9.&=\-_]+$/i))
		{
			document.location.hash.substr(1).split('&').forEach(function(p){
				p = p.split('=');

				// beware of injections
				if (p[0].match(/^[a-z]+$/i) && $('select#'+p[0]+' option[value="'+p[1]+'"]'))
					$('select#'+p[0]).value = p[1];
			});
		}
	};

	this.richEditor = function()
	{
		if (this.editor)
			return false;

		var code = $('code');
		var textarea = $('textarea[name=code]');

		//FIXME
		if (document.body.classList.contains('index') && textarea.value.length > code.textContent.length)
			code.appendChild(document.createTextNode(textarea.value));

		textarea.value = code.textContent;

		// Disable ace for touch-devices; see https://github.com/ajaxorg/ace/issues/37
		if (document.body.classList.contains('touch'))
		{
			code.style.display = 'none';
			// remove display=none, shows textarea
			textarea.removeAttribute('style');
			$('input[type=submit]').removeAttribute('disabled');
			return;
		}

		textarea.style.display = 'none';

		ace.config.set('basePath', 'https://cdn.jsdelivr.net/ace/1.2.3/min/')
		// Use a shim to keep ff happy
		ace.config.set('workerPath', '/s/');
		this.editor = ace.edit(code);
		this.editor.setTheme('ace/theme/chrome');
		this.editor.setShowPrintMargin(false);
		this.editor.setOption('maxLines', Infinity);
		this.editor.session.setMode('ace/mode/php');
		this.editor.session.setUseWrapMode(true);

		if ($('input[type=submit]'))
			$('input[type=submit]').setAttribute('disabled', 'disabled');

		$('#newForm').addEventListener('submit', function(e){
			textarea.value = this.editor.getValue();
		}.bind(this));

		this.editor.on('change', function(){
			if ($('input[type=submit]'))
				$('input[type=submit]').removeAttribute('disabled');
		});

		if ($('#archived_1'))
			$('#archived_1').addEventListener('change', function(){
				$('input[type=submit]').removeAttribute('disabled');
			});
	};

	this.handleScript = function()
	{
		if ('undefined' != typeof refreshTimer)
			return;

		this.richEditor();
		this.enablePreview();

		if ($('input[type=submit].busy'))
			refreshTimer = setInterval(this.refresh, 1000);

		document.body.addEventListener('keydown', function(e){
			if (13 == e.keyCode && e.altKey)
				return this.preview();

			if (13 != e.keyCode || !e.ctrlKey)
				return;

			// Trigger submitEvent manually
			var event = new Event('submit', {
				'view': window,
				'bubbles': true,
				'cancelable': true
			});
			// None of the handlers called preventDefault.
			if ($('#newForm').dispatchEvent(event))
				$('#newForm').submit();
		}.bind(this));

		this.localTime(function(el, d){
			el.innerHTML = ' @ '+ d.toString().split(' ').slice(0,5).join(' ');
		}, 'input + time');
	};

	this.handleRfc = function()
	{
		return this.handleOutput();
	};

	this.handleOutput = function()
	{
		$$('dt').forEach(function(el){
			el.addEventListener('click', function(e){ window.location.hash = '#'+ el.id; });
		});

		var hasOverflow = false;
		$$('dd').forEach(function(dd){
			hasOverflow = hasOverflow || dd.scrollHeight>dd.clientHeight;
			hasOverflow = hasOverflow || dd.scrollWidth>dd.clientWidth;
		});

		if (!document.body.classList.contains('touch') && hasOverflow)
		{
			var a = document.createElement('a');
			a.setAttribute('id', 'expand');
			a.setAttribute('title', 'expand output');
			a.addEventListener('click', outputExpand);
			var i = document.createElement('i');
			i.classList.add('icon-resize-full', 'expand');
			a.appendChild(i);
			$('div#tab').insertBefore(a, $('div#tab').firstChild);
		}
/*
		$$('a[href^="/assert"][data-hash]').forEach(function (el){
			el.addEventListener('click', function(e){
				//FIXME xhr submit
				e.preventDefault();
			});
		});
*/	};

	var outputExpand = function(e)
	{
		var i = $('a#expand i'), doExpand;

		if (i.classList.contains('icon-resize-full'))
		{
			doExpand = true;
			i.classList.remove('icon-resize-full');
			i.classList.add('icon-resize-small')
		} else {
			doExpand = false;
			i.classList.remove('icon-resize-small');
			i.classList.add('icon-resize-full')
		}

		$$('dd').forEach(function(dd){
			if (doExpand)
				dd.style.maxHeight = '50em';
			else
				dd.removeAttribute('style');
		});
	};

	this.enablePreview = function()
	{
		if (!$('div #version') || !$('input[type=submit]'))
			return;

		var p = document.createElement('form');
		p.setAttribute('id', 'previewForm');

		var b = document.createElement('button');
		b.setAttribute('name', 'version');
		b.setAttribute('type', 'button');
		b.setAttribute('title', 'shortcut: alt+enter');
		b.appendChild(document.createTextNode('preview in'));
		b.addEventListener('click', this.preview.bind(this));
		p.appendChild(b);

		// Move existing select from #options to after submit button, remove original parent
		var d = $('div #version').parentNode;
		p.appendChild($('#version'));
		d.parentNode.removeChild(d);

		$('input[type=submit]').parentNode.insertBefore(p, null);
		$('#version').removeAttribute('disabled');
	};

	this.preview = function()
	{
		window.location.hash = '#version='+ $('#version').value;

		var xhr = new XMLHttpRequest();
		xhr.submittedData
		xhr.onload = _preview;
		xhr.open('post', '/new');

		var data = new FormData($('#previewForm'));
		if (this.editor)
			data.append('code', this.editor.getValue());
		else
			data.append('code', $('textarea[name=code]').value);
		xhr.send(data);

		return false;
	};

	var _preview = function()
	{
		$$('#newForm ~ div, #newForm ~ ul').forEach(function(div){
			div.parentNode.removeChild(div);
		});

		//fixme - this is functional but it all sucks

		var t = this.responseText.match(/<ul id="tabs"[^>]*>([\s\S]*?)<\/ul>/);
		var r = this.responseText.match(/<div id="tab"[^>]*>([\s\S]*?)<\/div>/);
		if (!t || !r)
		{
			t = ['', '<li class="active"><a>Error</a></li>'];
			r = this.responseText.match(/<body[^>]*>([\s\S]*?)<\/body>/);
		}

		if (!r)
			return;

		var ul = document.createElement('ul'); ul.setAttribute('id', 'tabs');
		var tab = document.createElement('div'); tab.setAttribute('id', 'tab');

		ul.innerHTML = t[1];
		tab.innerHTML = r[1];
		$('#newForm').parentNode.insertBefore(tab, $('#newForm').nextSibling);
		$('#newForm').parentNode.insertBefore(ul, tab);
	};

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
		var tab = $('#tab');
		var t = this.responseText.match(/<ul id="tabs"[^>]*>([\s\S]*?)<\/ul>/);
		var r = this.responseText.match(/<div id="tab"[^>]*>([\s\S]*?)<\/div>/);
		if (!t || !r)
			window.location.reload();

		$('#tabs').innerHTML = t[1];

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
			$('input[type=submit].busy').classList.remove('busy');
			$('#tabs.busy').classList.remove('busy');
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
				m.setAttribute('optimum', perfAggregates[type]['low']/2);

			el.childNodes[1+index].appendChild(m);
		});
	};

	this.localTime = function(cb, sel)
	{
		sel = sel || 'time';
		cb = cb || function(){};

		$$(sel).forEach(function (el){
			var d = new Date(el.getAttribute('datetime'));
			el.setAttribute('title', d.toString());
			cb(el, d);
		});
	};

	this.handleIndex = function()
	{
		if (!this.editor)
			return false;

		this.editor.focus();
		this.editor.gotoLine(this.editor.session.getLength());

		this.localTime();
	};

	this.handleLast = function()
	{
		this.localTime(function(el, d){
			function pad(d){ return ('0'+d).slice(-2); };
			el.innerHTML = pad(d.getHours()) +':'+ pad(d.getMinutes()) +':'+ pad(d.getSeconds());
		});
	};

	this.handlePerf = function()
	{
		if (!perfAggregates)
			return false

		var version, previous, header, sum = {count: 0, system: 0, user: 0, memory: 0, success: 0};

		// Don't use foreach or cache n.length
		var n = $('#tab table tbody').childNodes;
		for (var i=0, tr=n[i]; i<n.length; tr=n[++i])
		{
			// We modify the Node we traverse, not so smart...
			if (tr.querySelector('meter'))
				continue;

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
				m.setAttribute('optimum', perfAggregates[type]['low']/2);

				for (var k in perfAggregates[type])
					m.setAttribute(k, perfAggregates[type][k]);

				tr.childNodes[1+index].appendChild(m);
			});

			previous = version;
		}

		// Process last entry
		perfAddHeader(header, previous, sum);
	};

	this.handleSearch = function()
	{
		$('#searchForm').addEventListener('submit', function(e){
			e.preventDefault();

			var url = '/search/'+ encodeURIComponent($('#operation').value);
			if ($('#operand').value.length > 0)
				url += '/'+ encodeURIComponent($('#operand').value);
			window.location.href = url;
		});

		$('select[name=operation]').addEventListener('change', function(e){
			$('input[name=operand]').classList.toggle('noOperand', (-1 == haveOperand.indexOf(e.target.value)));
		});

		if ($('svg'))
			this.handleTagcloud();
	};

	this.handleBughunt = function()
	{
		if (!$('#bughuntForm'))
			return false;

		$('#bughuntForm').addEventListener('submit', function(e){
			e.preventDefault();

			var url = '/bughunt/'
				+ $('#versions').getSelected().join('+')
				+ '/'+ $('#controls').getSelected().join('+');
			window.location.href = url;
		});
	};

	this.handleTagcloud = function()
	{
		var ns = 'http://www.w3.org/1999/xlink', svgNs = 'http://www.w3.org/2000/svg';
		$('svg').setAttribute('xmlns:xlink', ns);

		$$('g text').forEach(function (el){
			var w = document.createElementNS(svgNs, 'a');
			w.setAttributeNS(ns, 'xlink:href', '/search/INIT_FCALL/'+ el.textContent);
			w.setAttributeNS(ns, 'target', '_top');
			w.appendChild(el.cloneNode(true));
			el.parentNode.replaceChild(w, el);
		});
	};

	var btcAmountReceived = function()
	{
		return 0;
		// https://blockchain.info/q/getreceivedbyaddress/xyz / 100000000
	};
}).apply(evalOrg);

// Possibility to apply css before onload gets fired (which is after parsing ace.js)
document.body.classList.add('js');
if ("ontouchstart" in window)
	document.body.classList.add('touch');

window.addEventListener('load', function(){ evalOrg.initialize(); });