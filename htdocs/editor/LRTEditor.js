var LRTEditor = new Class({
	Implements: Events,
	element: null,
	plugins: {},
	_stopPropagation: {},
	selection: null,

	initialize: function(el, plugins)
	{
		this.element = el;

		plugins.each(function(p){
			this.plugins[p] = new LRTEditor[p](this);
		}.bind(this));

		this.element.addEvent('keydown', this._propagate.bind(this));
		this.element.addEvent('keyup', this._propagate.bind(this));
		this.element.addEvent('input', this._propagate.bind(this));

		this.highlight();
	},

	_propagate: function(e)
	{
		this.selection = this.saveSelection();
console.log(e.type);
		try
		{
			this.fireEvent(e.type, e);
		}
		catch (ex)
		{
			if (ex != this._stopPropagation)
				throw ex;

			return;
		}

		if ('input' == e.type)
			this.reformat();

		this.restoreSelection(this.selection);
	},

	stripHtml: function(el)
	{
		if (!el)
			el = this.element;

		el.set('text', el.textContent);
	},

	highlight: function()
	{
		sh_highlightElement(this.element, sh_languages['php']);

		this.element.innerHTML = '<span class="line">'+ this.element.innerHTML.replace(/\n/g, '\n</span><span class="line">') +'</span>';
	},

	reformat: function()
	{
		this.stripHtml();

		this.highlight();
	},

	traverseText: function(n, c)
	{
		if (n.nodeType == 3)
			c(n);
		else
			for (var i = 0, len = n.childNodes.length; i<len; ++i)
				this.traverseText(n.childNodes[i], c);
	},

	saveSelection: function()
	{
		var offset = 0, start = 0, end = 0, found = false, stop = {};
		var processText = function(n)
		{
			if (!found && n == range.startContainer)
			{
				start = offset + range.startOffset;
				found = true;
			}

			if (found && n == range.endContainer)
			{
				end = offset + range.endOffset;
				throw stop;
			}

			offset += n.length;
		}

		var sel = window.getSelection(), range = sel.getRangeAt(0);

		if (sel.rangeCount)
		{
			try
			{
				this.traverseText(this.element, processText);
			}
			catch (ex)
			{
				if (ex != stop)
					throw ex;
			}
		}

		return {
			start: start,
			end: end
		};
	},

	restoreSelection: function(sel)
	{
		var offset = 0, range = document.createRange(), found = false, stop = {};
		range.collapse(this.element, 0);

		var processText = function(n)
		{
			var nextOffset = offset + n.length;

			if (!found && sel.start >= offset && sel.start <= nextOffset)
			{
				range.setStart(n, sel.start - offset);
				found = true;
			}

			if (found && sel.end >= offset && sel.end <= nextOffset)
			{
				range.setEnd(n, sel.end - offset);
				throw stop;
			}

			offset = nextOffset;
		}

		try
		{
			this.traverseText(this.element, processText);
		}
		catch (ex)
		{
			if (ex != stop)
				throw ex;

			window.getSelection().removeAllRanges();
			window.getSelection().addRange(range);
		}
	},
});

LRTEditor.MinimalPlugin = new Class({
	editor: null,

	initialize: function(editor){
		this.editor = editor;

		this.editor.addEvent('keydown', this.onKeydown.bind(this));
	},

	onKeydown: function(e)
	{
		var range = window.getSelection().getRangeAt(0);

		if ('tab' == e.key && !e.shift && !e.alt)
		{
			// Not supported (yet?)
			if (this.editor.selection.start != this.editor.selection.end)
				return e.preventDefault();

			range.insertNode(document.createTextNode("\t"));

			this.editor.selection.start++;
			this.editor.selection.end++;
		}
		else if ('enter' == e.key)
		{
			range.deleteContents();
			range.insertNode(document.createTextNode("\n"));
			this.editor.selection.start += 2;
			this.editor.selection.end = this.editor.selection.start;
		}
		else
			return;

		this.editor.reformat();

		// Trigger input event since we changed content. Add delay so _propagate can restoreSelection first
		e.preventDefault();
		e.type='input';
		this.editor.element.fireEvent('input', e, 10);
	},
});