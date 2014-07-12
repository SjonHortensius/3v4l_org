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
