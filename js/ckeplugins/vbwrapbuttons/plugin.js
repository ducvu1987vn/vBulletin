CKEDITOR.dom.range.prototype.wrapSelectionWith = function(start, end)
{
	var startNode, endNode;
	var clone;

	// make sure we are including bold/italic/underline/etc tags
	this.enlarge(CKEDITOR.ENLARGE_ELEMENT);

	startNode = CKEDITOR.dom.element.createFromHtml(start, this.document);
	endNode = CKEDITOR.dom.element.createFromHtml(end, this.document);

	// insert closing tag
	clone = this.clone();
	clone.collapse();
	clone.insertNode(endNode);
	if (this.collapsed)
	{
		this.moveToPosition(endNode, CKEDITOR.POSITION_BEFORE_START);
	}

	// insert start tag
	clone = this.clone();
	clone.collapse(true);
	clone.insertNode(startNode);
	if (this.collapsed)
	{
		this.moveToPosition( startNode, CKEDITOR.POSITION_AFTER_END );
	}

	// reselect ranges
	if (!this.collapsed)
	{
		this.setEndBefore(endNode);
		this.setStartAfter(startNode);
	}
}

CKEDITOR.editor.prototype.getSelectedText = function()
{
	if (CKEDITOR.env.ie)
	{
		return this.getSelection().document.$.selection.createRange().text;
	}
	else
	{
		return this.getSelection().getNative();
	}
}

CKEDITOR.editor.prototype.wrapSelectionWith = function(start, end)
{
	if (CKEDITOR.env.ie)
	{
		this.focus();
	}

	var ranges = this.getSelection().getRanges();
	for (var i = 0; i < ranges.length; i++)
	{
		ranges[i].wrapSelectionWith(start, end);
	}
	this.getSelection().selectRanges(ranges);
}

// add plugin
CKEDITOR.plugins.add('vbwrapbuttons',
{
	init: function(editor)
	{
		var addButtonCommand = function(buttonName, buttonLabel, tagName, option, image)
		{
			editor.addCommand(buttonName.toLowerCase(),
			{
				exec: function(editor)
				{
					if (option == '1')
					{
						var optionString = prompt(buttonLabel.replace(/\%1\$s/,'[' + tagName + ']'));
						if (typeof optionString == 'string' && optionString.length > 0)
						{
							editor.wrapSelectionWith('[' + tagName + '=' + optionString + ']', '[/' + tagName + ']');
						}
						else
						{
							editor.wrapSelectionWith('[' + tagName + ']', '[/' + tagName + ']');
						}
					}
					else
					{
						editor.wrapSelectionWith('[' + tagName + ']', '[/' + tagName + ']');
					}
				}
			});

			var buttonDefinition = {label : buttonLabel, command : buttonName.toLowerCase()};
			if (image)
			{
				buttonDefinition.icon = image;
			}

			editor.ui.addButton(buttonName, buttonDefinition);
		};

		addButtonCommand('Quote', vBulletin.phrase.get('wrap_x_tags', 'QUOTE'), 'QUOTE', 0, window.pageData.baseurl + '/core/images/editor/quote.png');
		addButtonCommand('Code', vBulletin.phrase.get('wrap_x_tags', 'CODE'), 'CODE', 0, window.pageData.baseurl + '/core/images/editor/code.png');
		addButtonCommand('Html', vBulletin.phrase.get('wrap_x_tags', 'HTML'), 'HTML', 0, window.pageData.baseurl + '/core/images/editor/html.png');
		addButtonCommand('Php', vBulletin.phrase.get('wrap_x_tags', 'PHP'), 'PHP', 0, window.pageData.baseurl + '/core/images/editor/php.png');

		for (var bbcodetag in window.vBulletin.customBbcode)
		{
			if (!bbcodetag)
			{
				continue;
			}
			var bbcode = window.vBulletin.customBbcode[bbcodetag];
			var resource = bbcode.buttonimage;

			// By default addButtonCommand will end up appending ckeditor's basepath to any bbcode images that are relative
			// so we are preemptive here by adding baseHref
			//
			// If this is not a full or absolute path.
			if (resource.indexOf(':/') == -1 && resource.indexOf( '/' ) !== 0 )
			{
				resource = editor.config.baseHref + resource;
			}

			addButtonCommand(bbcode.title, vBulletin.phrase.get('wrap_x_tags', bbcodetag.toUpperCase()), bbcodetag.toUpperCase(), bbcode.twoparams, resource);
		}
	}
});