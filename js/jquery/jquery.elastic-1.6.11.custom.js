/**
*	@name							Elastic
*	@descripton						Elastic is jQuery plugin that grow and shrink your textareas automatically
*	@version						1.6.11
*	@requires						jQuery 1.2.6+
*
*	@author							Jan Jarfalk
*	@author-email					jan.jarfalk@unwrongest.com
*	@author-website					http://www.unwrongest.com
*
*	@licence						MIT License - http://www.opensource.org/licenses/mit-license.php
*/
/*
*  Modified for vBulletin:
*  - Replace twin DOM node instead of adding a new one for every elastic textarea
*  - Added namespace to events bound by the plugin to the textarea to allow us to programmatically trigger events specific to this plugin
*  - Added checking for CSS box-sizing in determining the actual height and width of the textarea
*/
(function($){ 
	jQuery.fn.extend({  
		elastic: function() {
		
			//	We will create a div clone of the textarea
			//	by copying these attributes from the textarea to the div.
			var mimics = [
				'paddingTop',
				'paddingRight',
				'paddingBottom',
				'paddingLeft',
				'fontSize',
				'lineHeight',
				'fontFamily',
				'width',
				'fontWeight',
				'border-top-width',
				'border-right-width',
				'border-bottom-width',
				'border-left-width',
				'borderTopStyle',
				'borderTopColor',
				'borderRightStyle',
				'borderRightColor',
				'borderBottomStyle',
				'borderBottomColor',
				'borderLeftStyle',
				'borderLeftColor'
				];
			
			return this.each( function() {

				// Elastic only works on textareas
				if ( this.type !== 'textarea' ) {
					return false;
				}
				
				//added for vBulletin to consider box-sizing in determining the actual height and width of the textarea
				var getTextareaComputedHeight = function() {
					return (
						boxSizing == 'border-box' ? 
							{
								height: $textarea.outerHeight(),
								width: $textarea.outerWidth()
							} 
						: 
						(	
							boxSizing == 'padding-box' ? 
								{
									height: $textarea.innerHeight(),
									width: $textarea.innerWidth()
								}
							: 
								{
									height: $textarea.height(),
									width: $textarea.width()
								}
						));
				}
					
			var $textarea	= jQuery(this),
				$twin		= jQuery('<div />').css({
					'position'		: 'absolute',
					'display'		: 'none',
					'word-wrap'		: 'break-word',
					'white-space'	:'pre-wrap'
				}).addClass('elastic-twin-div'), //added for vBulletin
				boxSizing 	= $textarea.css('box-sizing') || $textarea.css('-moz-box-sizing') || $textarea.css('-webkit-box-sizing') || 'content-box'; //added for vBulletin
				lineHeight	= parseInt($textarea.css('line-height'),10) || parseInt($textarea.css('font-size'),'10'),
				minheight	= getTextareaComputedHeight().height || lineHeight*3,
				maxheight	= parseInt($textarea.css('max-height'),10) || Number.MAX_VALUE,
				goalheight	= 0;
				
				// Opera returns max-height of -1 if not set
				if (maxheight < 0) { maxheight = Number.MAX_VALUE; }
					
				// Append the twin to the DOM
				// We are going to meassure the height of this, not the textarea.
				// Replace existing twin if any (added for vBulletin)
				var $existingTwin = $('> .elastic-twin-div', $textarea.parent());
				if ($existingTwin.length == 1) {
					$existingTwin.replaceWith($twin);
				}
				else {
					$twin.appendTo($textarea.parent());
				}
				
				// Copy the essential styles (mimics) from the textarea to the twin
				var i = mimics.length;
				while(i--){
					$twin.css(mimics[i].toString(),$textarea.css(mimics[i].toString()));
				}
				
				// Updates the width of the twin. (solution for textareas with widths in percent)
				function setTwinWidth(){
					var curatedWidth = getTextareaComputedHeight().width;
					if($twin.width() !== curatedWidth){
						$twin.css({'width': curatedWidth + 'px'});
						
						// Update height of textarea
						update(true);
					}
				}
				
				// Sets a given height and overflow state on the textarea
				function setHeightAndOverflow(height, overflow){
				
					//changed for vBulletin
					var curratedHeight = parseInt(height,10);
					if (getTextareaComputedHeight().height !== curratedHeight){
						$textarea.css({'height': curratedHeight + 'px','overflow':overflow});
					}
				}
				
				// This function will update the height of the textarea if necessary 
				function update(forced) {
					
					// Get curated content from the textarea.
					var textareaContent = $textarea.val().replace(/&/g,'&amp;').replace(/ {2}/g, '&nbsp;').replace(/<|>/g, '&gt;').replace(/\n/g, '<br />');
					
					// Compare curated content with curated twin.
					var twinContent = $twin.html().replace(/<br>/ig,'<br />');
					
					if(forced || textareaContent+'&nbsp;' !== twinContent){
					
						// Add an extra white space so new rows are added when you are at the end of a row.
						$twin.html(textareaContent+'&nbsp;');						
						
						// Change textarea height if twin plus the height of one line differs more than 3 pixel from textarea height
						if(Math.abs($twin.height() + lineHeight - getTextareaComputedHeight().height) > 3){
							
							var goalheight = $twin.height()+lineHeight;
							if(goalheight >= maxheight) {
								setHeightAndOverflow(maxheight,'auto');
							} else if(goalheight <= minheight) {
								setHeightAndOverflow(minheight,'hidden');
							} else {
								setHeightAndOverflow(goalheight,'hidden');
							}							
						}
						
					}
					
				}
				
				// Hide scrollbars
				$textarea.css({'overflow':'hidden'});
				
				// Update textarea size on keyup, change, cut and paste
				$textarea.bind('keyup.elastic change.elastic cut.elastic paste.elastic', function(){
					update(); 
				});
				
				// Update width of twin if browser or textarea is resized (solution for textareas with widths in percent)
				$(window).bind('resize.elastic', setTwinWidth);
				$textarea.bind('resize.elastic focus.elastic', setTwinWidth); //modified for vBulletin - added focus
				$textarea.bind('update.elastic', update);

				// Compact textarea on blur
				$textarea.bind('blur.elastic',function(){
					if($twin.height() < maxheight){
						if($twin.height() > minheight) {
							$textarea.height($twin.height());
						} else {
							$textarea.height(minheight);
						}
					}
				});
				
				// And this line is to catch the browser paste event
				$textarea.bind('input.elastic paste.elastic',function(e){ setTimeout( update, 250); });				
				
				// Run update once when elastic is initialized
				update();
				
			});
			
        } 
    }); 
})(jQuery);