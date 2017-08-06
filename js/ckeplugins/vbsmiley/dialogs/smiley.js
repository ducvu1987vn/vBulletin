/*
Copyright (c) 2003-2011, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.dialog.add( 'smiley', function( editor )
{
	var config = editor.config,
		lang = editor.lang.smiley,
		smilies = window.vBulletin.smilieInfo,
		columns = config.smiley_columns || 8,
		i;

	/**
	 * Simulate "this" of a dialog for non-dialog events.
	 * @type {CKEDITOR.dialog}
	 */
	var dialog;
	var onClick = function( evt )
	{
		var target = evt.data.getTarget(),
			targetName = target.getName();

		if ( targetName == 'a' )
		{
			target = target.getChild( 0 );
		}
		else if ( targetName != 'img' )
		{
			return;
		}

		var src = target.getAttribute('cke_src'),
			title = target.getAttribute('title'),
			smilieid = target.getAttribute('smilieid');

		var img = editor.document.createElement( 'img',
		{
			attributes :
			{
				'src'                : src,
				'data-cke-saved-src' : src,
				'title'              : title,
				'alt'                : title,
				'width'              : target.$.width,
				'height'             : target.$.height,
				'smilieid'           : smilieid,
				'class'              : 'inlineimg'
			}
		});

		editor.insertElement( img );

		dialog.hide();
		evt.data.preventDefault();
	};

	var onKeydown = CKEDITOR.tools.addFunction( function( ev, element )
	{
		ev = new CKEDITOR.dom.event( ev );
		element = new CKEDITOR.dom.element( element );
		var relative, nodeToMove;

		var keystroke = ev.getKeystroke(),
			rtl = editor.lang.dir == 'rtl';
		switch ( keystroke )
		{
			// UP-ARROW
			case 38 :
				// relative is TR
				if ( ( relative = element.getParent().getParent().getPrevious() ) )
				{
					nodeToMove = relative.getChild( [element.getParent().getIndex(), 0] );
					nodeToMove.focus();
				}
				ev.preventDefault();
				break;
			// DOWN-ARROW
			case 40 :
				// relative is TR
				if ( ( relative = element.getParent().getParent().getNext() ) )
				{
					nodeToMove = relative.getChild( [element.getParent().getIndex(), 0] );
					if ( nodeToMove )
						nodeToMove.focus();
				}
				ev.preventDefault();
				break;
			// ENTER
			// SPACE
			case 32 :
				onClick( { data: ev } );
				ev.preventDefault();
				break;

			// RIGHT-ARROW
			case rtl ? 37 : 39 :
			// TAB
			case 9 :
				// relative is TD
				if ( ( relative = element.getParent().getNext() ) )
				{
					nodeToMove = relative.getChild( 0 );
					nodeToMove.focus();
					ev.preventDefault(true);
				}
				// relative is TR
				else if ( ( relative = element.getParent().getParent().getNext() ) )
				{
					nodeToMove = relative.getChild( [0, 0] );
					if ( nodeToMove )
						nodeToMove.focus();
					ev.preventDefault(true);
				}
				break;

			// LEFT-ARROW
			case rtl ? 39 : 37 :
			// SHIFT + TAB
			case CKEDITOR.SHIFT + 9 :
				// relative is TD
				if ( ( relative = element.getParent().getPrevious() ) )
				{
					nodeToMove = relative.getChild( 0 );
					nodeToMove.focus();
					ev.preventDefault(true);
				}
				// relative is TR
				else if ( ( relative = element.getParent().getParent().getPrevious() ) )
				{
					nodeToMove = relative.getLast().getChild( 0 );
					nodeToMove.focus();
					ev.preventDefault(true);
				}
				break;
			default :
				// Do not stop not handled events.
				return;
		}
	});

	// Build the HTML for the smiley images table.
	var labelId = CKEDITOR.tools.getNextId() + '_smiley_emtions_label';
	var html =
	[
		'<div>' +
		'<span id="' + labelId + '" class="cke_voice_label">' + lang.options +'</span>',
		'<table role="listbox" aria-labelledby="' + labelId + '" style="width:100%;height:100%" cellspacing="2" cellpadding="2"',
		CKEDITOR.env.ie && CKEDITOR.env.quirks ? ' style="position:absolute;"' : '',
		'><tbody>'
	];

	var size = -1; // don't count first bogus element
	$.each(smilies, function(i, elem) {
		size++;
	});

	var currentcol = 0;
	var lastcat = '';
	var count = 0;

	for (desc in smilies)
	{
		if (!desc)
		{
			continue;
		}
		var category = smilies[desc].c;
		var smilie = smilies[desc].s;
		var smilieid = smilies[desc].id;

		if (currentcol == 0)
		{
			html.push( '<tr>' );
		}

		if (category)
		{
			if (currentcol != 0)
			{
				html.push('<td colspan="' + (columns - currentcol) + '"></td>');
				html.push('</tr><tr>');
			}
			html.push('<td colspan="' + columns + '" class="smiley_category">' +  category + '</td></tr>');
			html.push('<tr>');
			currentcol = 0;
		}
		currentcol++;

		var smileyLabelId = 'cke_smile_label_' + smilieid + '_' + CKEDITOR.tools.getNextNumber();
		var src = (!smilie.match(/^https?:\/\//)) ? config.smiley_path + smilie : smilie;
		html.push(
			'<td class="cke_dark_background cke_centered" style="vertical-align: middle;">' +
				'<a href="javascript:void(0)" role="option"',
					' aria-posinset="' + ( count + 1 ) + '"',
					' aria-setsize="' + size + '"',
					' aria-labelledby="' + smileyLabelId + '"',
					' class="cke_smile cke_hand" tabindex="-1" onkeydown="CKEDITOR.tools.callFunction( ', onKeydown, ', event, this );">',
					'<img class="cke_hand" title="', desc, '"' +
						' cke_src="', CKEDITOR.tools.htmlEncode(src), '" alt="', desc, '"',
						' smilieid="', smilieid, '"',
						' src="', CKEDITOR.tools.htmlEncode(src), '"',
						// IE BUG: Below is a workaround to an IE image loading bug to ensure the image sizes are correct.
						( CKEDITOR.env.ie ? ' onload="this.setAttribute(\'width\', 2); this.removeAttribute(\'width\');" ' : '' ),
					'>' +
					'<span id="' + smileyLabelId + '" class="cke_voice_label">' + desc + '</span>' +
				'</a>',
 			'</td>' );

		if (currentcol == columns)
		{
			html.push( '</tr>' );
			currentcol = 0;
		}
		count++;
	}

	if (currentcol < columns)
	{
		html.push('<td colspan="' + (columns - currentcol) + '"></td>');
		html.push( '</tr>' );
	}

	html.push( '</tbody></table></div>' );

	var smileySelector =
	{
		type : 'html',
		id : 'smileySelector',
		html : html.join( '' ),
		onLoad : function( event )
		{
			dialog = event.sender;
		},
		focus : function()
		{
			var self = this;
			// IE need a while to move the focus (#6539).
			setTimeout( function ()
			{
				var firstSmilie = self.getElement().getElementsByTag( 'a' ).getItem( 0 );
				if (firstSmilie)
				{
					firstSmilie.focus();
				}
			}, 0 );
		},
		onClick : onClick,
		style : 'width: 100%; border-collapse: separate;'
	};

	return {
		onShow : function()
		{
			var page = this.parts.contents.getChildren().getItem(0);
			page.addClass('smiley_page');
		},
		title : editor.lang.smiley.title,
		minWidth : 270,
		minHeight : 120,
		contents : [
			{
				id : 'tab1',
				label : '',
				title : '',
				expand : true,
				padding : 0,
				elements : [
						smileySelector
					]
			}
		],
		buttons : [ CKEDITOR.dialog.cancelButton ]
	};
} );
