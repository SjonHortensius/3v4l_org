NodeList.prototype.forEach =
 HTMLCollection.prototype.forEach =
 DOMTokenList.prototype.forEach = function (cb){
	Array.prototype.forEach.call(this, cb);
};

HTMLSelectElement.prototype.getSelected = function(){
	var s = [];
	for (var i = 0; i < this.length; i++) {
		if (this[i].selected)
			s.push(this[i].value);
	}
	return s;
};

String.prototype.ucFirst = function(){
	return this.charAt(0).toUpperCase() + this.slice(1);
};

function $(s){
	return document.querySelector(s);
}
function $$(s){
	return document.querySelectorAll(s);
}

var evalOrg = {};
(function()
{
	"use strict";

	var self = this,
		refreshTimer,
		refreshCount = 0,
		perfAggregates = undefined;
	this.editor = undefined;

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

		$$('.alert').forEach(function (el){
			el.addEventListener('touchstart', function(){ el.remove(); });
		});
	};

	var isMobile = function()
	{
		return navigator.userAgent.match(/(Android|BlackBerry|iPhone|iPad|iPod|Opera Mini|IEMobile)/);
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

		// Disable ace for mobile-devices; see https://github.com/ajaxorg/ace/issues/37
		if (isMobile())
			return;

		ace.config.set('basePath', 'https://cdn.jsdelivr.net/ace/1.2.6/min/');
		// Use a shim to keep ff happy
		ace.config.set('workerPath', '/s/');
		ace.require('ace/ext/language_tools');
		this.editor = ace.edit(code);
		this.editor.setTheme('ace/theme/chrome');
		this.editor.setShowPrintMargin(false);
		this.editor.setOption('maxLines', Infinity);
		this.editor.session.setMode('ace/mode/php');
		this.editor.session.setUseWrapMode(true);
		this.editor.setOptions({
			enableBasicAutocompletion: true,
			enableLiveAutocompletion: false,
		});

		if ($('input[type=submit]'))
			$('input[type=submit]').setAttribute('disabled', 'disabled');

		$('#newForm').addEventListener('submit', function(){
			textarea.value = this.editor.getValue();
		}.bind(this));

		this.editor.on('change', function(){
			if ($('input[type=submit][disabled]'))
				$('input[type=submit]').removeAttribute('disabled');
		});

		if ($('#archived_1[data-ran-archived=""'))
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

		if ($('#tabs.busy'))
			refreshTimer = setInterval(this.refresh, 1000);

		document.body.addEventListener('keydown', function(e){
			if (13 == e.keyCode && e.altKey)
				return this.preview();

			// ignore ctrl/cmd+s
			if (83 == e.which && (e.ctrlKey || e.metaKey))
			{
				e.preventDefault();
				return false;
			}

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

		// Attempt to reload a preview
		window.onpopstate = previewStateLoad.bind(this);

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
			el.addEventListener('click', function(){ window.location.hash = '#'+ el.id; });
		});

		if (window.location.hash == '#spoiler')
			$('#tab').classList.add('spoiler');

		outputAddExpander();
/*
		$$('a[href^="/assert"][data-hash]').forEach(function (el){
			el.addEventListener('click', function(e){
				//FIXME xhr submit
				e.preventDefault();
			});
		});
*/	};

	var outputAddExpander = function()
	{
		if (document.body.classList.contains('touch') || $('#expand'))
			return;

		var hasOverflow = false;
		$$('dd').forEach(function(dd){
			hasOverflow = hasOverflow || dd.scrollHeight>dd.clientHeight;
			hasOverflow = hasOverflow || dd.scrollWidth>dd.clientWidth;
		});

		if (!hasOverflow)
			return;

		var a = document.createElement('a');
		a.setAttribute('id', 'expand');
		a.setAttribute('title', 'expand output');
		a.addEventListener('click', outputExpand);
		var i = document.createElement('i');
		i.classList.add('icon-resize-full', 'expand');
		a.appendChild(i);
		$('div#tab').insertBefore(a, $('div#tab').firstChild);
	};

	var outputExpand = function()
	{
		$('dl').classList.toggle('expand');
		$('a#expand i').classList.toggle('icon-resize-full');
		$('a#expand i').classList.toggle('icon-resize-small');
	};

	this.enablePreview = function()
	{
		if (!$('input[type=submit]'))
			return $('div #version').parentNode.remove();

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
		// for people submitting from existing pages; prevent them sharing the original input
		history.pushState({code: this.editor.getValue(), version: $('#version').value}, 'preview', '/#preview');

		var xhr = new XMLHttpRequest();
		xhr.onload = _refreshOutput;
		xhr.open('post', '/new');
		xhr.setRequestHeader('Accept', 'application/json');

		var data = new FormData($('#previewForm'));
		if (this.editor)
			data.append('code', this.editor.getValue());
		else
			data.append('code', $('textarea[name=code]').value);
		xhr.send(data);

		return false;
	};

	var previewStateLoad = function(e)
	{
		// Not every hash change means there is a valid state to pop
		if (!e.state || !e.state.code)
			return;

		this.editor.setValue(e.state.code);
		$('#version').value = e.state.version;

		// get results by resubmit / replaceState storing output ?
	};

	this.refresh = function()
	{
		if (!$('#tabs.busy') || refreshCount > 42)
		{
			window.clearInterval(refreshTimer);
			return $('#tabs').classList.remove('busy');
		}

		refreshCount++;

		var xhr = new XMLHttpRequest();
		xhr.onload = _refresh;
		xhr.open('get', window.location.pathname);

		if (document.body.classList.contains('output'))
		{
			xhr.open('get', window.location.pathname+'.json');
			xhr.setRequestHeader('Accept', 'application/json');
			xhr.onload = _refreshOutput;
		}

		xhr.send();
	};

	var _refresh = function()
	{
		var tab = $('#tab');
		var t = this.responseText.match(/<ul id="tabs"[^>]*>([\s\S]*?)<\/ul>/);
		var r = this.responseText.match(/<div id="tab"[^>]*>([\s\S]*?)<\/div>/);
		if (!t || !r)
			return window.setTimeout(window.location.reload.bind(window.location), 200);

		// We need input.state on #tabs, so use outer
		$('#tabs').outerHTML = t[0];

		tab.innerHTML = r[1];

		document.body.classList.forEach(function(c){
			if ('function' == typeof this[ 'handle'+c.ucFirst() ])
				this[ 'handle'+c.ucFirst() ]();
		}.bind(self));
	};

	var _refreshOutput = function()
	{
		try
		{
			var r = JSON.parse(this.responseText);
		}
		catch (e)
		{
			return window.setTimeout(window.location.reload.bind(window.location), 200);
		}

		if (!$('#tabs'))
		{
			// Used when submitting a preview from a non-script page such as the homepage
			$$('#newForm ~ div, #newForm ~ ul').forEach(function(div){
				div.parentNode.removeChild(div);
			});

			var ul = document.createElement('ul'); ul.setAttribute('id', 'tabs');
			var tab = document.createElement('div'); tab.setAttribute('id', 'tab');
			tab.appendChild(document.createElement('dl'));

			$('#newForm').parentNode.insertBefore(tab, $('#newForm').nextSibling);
			$('#newForm').parentNode.insertBefore(ul, tab);
		}
		else if (r.script.state != 'busy')
			$('#tabs').classList.remove('busy');

		// Update tab enabled/disabled state
		$$('#tabs li').forEach(function (li) {
			var a = li.firstChild, tab;

			if (li.classList.contains('disabled'))
			{
				tab = a.getAttribute('id');

				if (r.script.tabs[tab])
				{
//console.log('enabling', tab);
					li.classList.remove('disabled');
					li.removeAttribute('title');

					a.setAttribute('href', '/' + r.script.short + '/' + tab + '#output');
				}
			}
			else
			{
				var aParts = a.getAttribute('href').split('/');
				tab = (2 == aParts.length) ? 'output' : aParts[2].split('#')[0];

				if ('undefined' != typeof r.script.tabs[tab] && !r.script.tabs[tab])
				{
//console.log('disabling', tab);
					li.classList.add('disabled');
					li.setAttribute('title', 'not available');

					a.removeAttribute('href');
					a.setAttribute('id', tab);
				}
			}
		});

		var tab = $('#tab'), dl = tab.getElementsByTagName('dl')[0],
			o = dl.getElementsByTagName('dt');

		r.output.forEach(function (n, i) {
			var nvText = 'Output for ' + n.versions;

			if (o[i] && o[i].textContent == nvText)
				return;

			var ndt = document.createElement('dt'),
				ndd = document.createElement('dd');

			ndt.appendChild(document.createTextNode(nvText));
			ndt.setAttribute('id', 'v' + n.versions.replace(/,/g, ' ').split(' ').shift().replace(/\./g, ''));
			ndt.addEventListener('click', function(){ window.location.hash = '#'+ ndt.id; });

			// json output is also html encoded
			ndd.innerHTML = n.output;

			if (o[i] && n.output == o[i].nextSibling.innerHTML)
			{
//console.log('update', o[i].textContent, 'to', nvText);
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

		// When using preview on normal output we need to remove existing results
		while (o.length > r.output.length)
		{
			o[r.output.length].nextSibling.remove();
			o[r.output.length].remove();
		}

		outputAddExpander();
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
			function pad(d){ return ('0'+d).slice(-2); }
			el.innerHTML = pad(d.getHours()) +':'+ pad(d.getMinutes()) +':'+ pad(d.getSeconds());
		});
	};

	this.handlePerf = function()
	{
		if (!$('table[data-aggregates]'))
			return false;

		perfAggregates = JSON.parse($('table[data-aggregates]').dataset['aggregates']);
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

		var haveOperand = JSON.parse($('dfn[data-have-operands]').dataset['haveOperands']);
		$('select[name=operation]').addEventListener('change', function(e){
			$('input[name=operand]').classList.toggle('noOperand', (-1 == haveOperand.indexOf(e.target.value)));
		});

		if ($('div#tagCloud'))
			this.handleTagcloud();
		else
			this.localTime(function(el, d){
				function pad(n){ return ('0'+n).slice(-2); }
				el.innerHTML = d.getFullYear() +'-'+ pad(1+d.getMonth()) +'-'+ pad(d.getDate()) +' '+ pad(d.getHours()) +':'+ pad(d.getMinutes()) +':'+ pad(d.getSeconds());
			});
	};

	this.handleBughunt = function()
	{
		if ($('#bughuntForm'))
		{
			$('#bughuntForm').addEventListener('submit', function (e) {
				e.preventDefault();

				window.location.href = '/bughunt/'
					+ $('#versions').getSelected().join('+')
					+ '/' + $('#controls').getSelected().join('+');
			});
		}
		else
		{
			this.localTime(function(el, d){
				function pad(d){ return ('0'+d).slice(-2); }
				el.innerHTML = d.getFullYear() +'-'+ pad(1+d.getMonth()) +'-'+ pad(d.getDate()) +' '+ pad(d.getHours()) +':'+ pad(d.getMinutes()) +':'+ pad(d.getSeconds());
			});
		}
	};

	this.handleTagcloud = function()
	{
		var tc = $('div#tagCloud');
		var fill = d3.scale.category20();
		var fontSize = d3.scale.linear().range([10, 100]).domain([tc.dataset['min'], tc.dataset['max']]);

		function draw(words) {
			d3.select('div#tagCloud').append('svg')
				.attr('width', 1022)
				.attr('height', 600)
				.append('g')
				.attr('transform', 'translate(511,300)')
				.selectAll('text')
				.data(words)
				.enter().append('text')
				.style('font-size', function(d) { return d.size + 'px'; })
				.style('fill', function(d, i) { return fill(i); })
				.attr('text-anchor', 'middle')
				.attr('transform', function(d) {
					return 'translate(' + [d.x, d.y] + ')rotate(' + d.rotate + ')';
				})
				.text(function(d) { return d.text; });
		}

		var layout = d3.layout.cloud()
			.size([1022, 600])
			.padding(5)
			.rotate(0)
			.font('Impact')
			.fontSize(function(d) { return fontSize(+d.size); })
			.on('end', draw)
			.words(JSON.parse(tc.dataset['words']))
			.start();

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

	this.handleVersions = function()
	{
		tableSorter.initialize();
	};

	this.handleStats = function()
	{
		this.localTime(false, 'tbody td:nth-child(2)');
	}
}).apply(evalOrg);

// Possibility to apply css before onload gets fired (which is after parsing ace.js)
document.body.classList.add('js');
if ('ontouchstart' in window)
	document.body.classList.add('touch');

window.addEventListener('load', function(){ evalOrg.initialize(); });