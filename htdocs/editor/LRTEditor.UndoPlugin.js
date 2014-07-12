LRTEditor.UndoPlugin = new Class({
	editor: null,
	revisions: [],
	undoIndex: null,
	ignoreInput: false,

	initialize: function(editor)
	{
		this.editor = editor;

		this.editor.addEvent('keyup', this.onKeyup.bind(this));
		this.editor.addEvent('keydown', this.onKeydown.bind(this));
		this.editor.addEvent('input', this.onInput.bind(this));
	},

	onKeydown: function(e)
	{
		if (0 == this.revisions.length)
		{
			this.editor.stripHtml();
			this.revisions.push({html: this.editor.element.innerHTML, selection: this.editor.selection});
			this.editor.highlight();
		}

		// input event doesn't contain actual keys; store them here
		this.ignoreInput = ('z' == e.key && e.control || 'y' == e.key && e.control)
	},

	onInput: function(e)
	{
		if (this.ignoreInput)
		{
			// This change was triggered by us
			this.ignoreInput = false;
			return;
		}

		if (this.undoIndex)
		{
			while (this.undoIndex < this.revisions.length-1)
				this.revisions.pop()

			this.undoIndex = null;
		}

		this.editor.stripHtml();

		this.revisions.push({html: this.editor.element.innerHTML, selection: this.editor.selection});
console.log('stored revision', this.revisions.length, this.undoIndex);
		if (this.revisions.length > 30)
			this.revisions.shift();
	},

	onKeyup: function(e)
	{
		if ('z' == e.key && e.control)
		{
			if (this.undoIndex == null)
				this.undoIndex = this.revisions.length-1;

			this.undoIndex--;

			if (this.undoIndex < 0)
				return;
		}
		else if ('y' == e.key && e.control && this.undoIndex != null)
		{
			this.undoIndex++;

			if (this.undoIndex > this.revisions.length-1)
			{
				this.undoIndex--;
				return;
			}
		}
		else
			return;

console.log("index: "+this.undoIndex, "undoLength: "+this.revisions.length);

		this.editor.element.innerHTML = this.revisions[ this.undoIndex ].html;
		this.editor.selection = this.revisions[ this.undoIndex ].selection;

		this.editor.highlight();
	},
});
