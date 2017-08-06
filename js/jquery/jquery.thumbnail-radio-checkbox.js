/*!======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
(function( $ ){
	var namespace = 'thumbnailRadioCheckbox',
		parentContainerClass = 'radio-checkbox-thumbnail-container',
		thumbnailClass = 'radio-checkbox-thumbnail',
		selectedClass = 'selected',
		disabledClass = 'disabled';

	var defaultSettings = {
		thumbnailClass:'',
		preselect:null,
		select:null,
		unselect:null
	};
	
	var methods = {
	    init : function( options ) {
			var self = this;
			
			if ( typeof options === 'object') {
				self.settings = $.extend({}, defaultSettings, options);
			}

	    	self.each(function() {		    	

			    var $radioCheckbox = $(this);

			    //hide the radio button
				$radioCheckbox.hide();

				$('<div></div>')  //parent container
					.addClass(parentContainerClass)
					.addClass(function(){
						return ($radioCheckbox.attr('type') == 'radio') ? getRadioGroupName.apply($radioCheckbox) : '';
					})
					.insertBefore($radioCheckbox)
					.append(
						$('<div></div>') //thumbnail container
							.attr('id', ($radioCheckbox.attr('id') ? 'thumbnail-' + $radioCheckbox.attr('id') : ''))
							.addClass(thumbnailClass + ' ' + self.settings.thumbnailClass)
							.addClass(function(){
								return ($radioCheckbox.attr('type') == 'radio') ? 'has-hover' : 'no-hover';
							})
							.bind('mousedown.' + namespace, function(){
								return (typeof self.settings.preselect === 'function') ? self.settings.preselect($('img', this), $radioCheckbox) : true;
							})
							.bind('click.' + namespace, function(){
															
								//don't do anything if radio button or checkbox is disabled or if radio button is already checked
								if ($(this).hasClass(disabledClass) || ($radioCheckbox.attr('type') == 'radio' && $(this).hasClass(selectedClass))) {
									return;
								}
										
								if ($radioCheckbox.attr('type') == 'checkbox') {				
									$(this).toggleClass(selectedClass);
									if ($(this).hasClass(selectedClass)){
										$radioCheckbox.attr('checked', 'checked');
										if (typeof self.settings.select === 'function') self.settings.select($('img', this), $radioCheckbox);
									}
									else {
										$radioCheckbox.removeAttr('checked');
										if (typeof self.settings.unselect === 'function') self.settings.unselect($('img', this), $radioCheckbox);										
									}
								}
								else { //radio button
									$radioCheckbox.attr('checked', 'checked');
									
									//unselect previous radio button thumbnail in the group and select current thumbnail
									$('.' + parentContainerClass + '.' + getRadioGroupName.apply($radioCheckbox) + ' > div.' + selectedClass).removeClass(selectedClass);
									$(this).addClass(selectedClass);

									//call user-defined unselect/select handler
									if (typeof self.settings.unselect === 'function') self.settings.unselect($('img', this), $radioCheckbox);
									if (typeof self.settings.select === 'function') self.settings.select($('img', this), $radioCheckbox);
								}								
							})
							//the thumbnail image itself
							.append(
								$('<img />').attr({
									src: $radioCheckbox.attr('data-thumbnail'),
									alt: $radioCheckbox.next('label').text(),									
									title:''
								}).css({
									width:($radioCheckbox.attr('data-thumbnail-width')) ? $radioCheckbox.attr('data-thumbnail-width')+'px':'auto',
									height:($radioCheckbox.attr('data-thumbnail-height')) ? $radioCheckbox.attr('data-thumbnail-height')+'px':'auto'
								})
							)
							//insert elements that need to be inside the thumbnail container along side the img
							//designated by the special CSS class 'radio-checkbox-inside-thumbnail'
							.append($radioCheckbox.nextAll('.radio-checkbox-inside-thumbnail'))

					)
					//remove custom attributes of radio button/checkbox and then append it and the rest of the adjacent elements
					.append($radioCheckbox.removeAttr('data-thumbnail').removeAttr('data-thumbnail-width').removeAttr('data-thumbnail-height'), $radioCheckbox.nextAll());

				//highlight default thumbnail based on default selected radio button or checkbox
				//and then call user-defined select handler
				if ($radioCheckbox.is(':checked')) {
					$radioCheckbox.prev().addClass(selectedClass);
					if (typeof self.settings.select === 'function') self.settings.select($('img', $radioCheckbox.prev()), $radioCheckbox);
				}

				//disabled thumbnail
				if ($radioCheckbox.is(':disabled')) {
					$radioCheckbox.prev().addClass(disabledClass);
				}
			});
	    	return this;
		},
	    enable : function($radioCheckboxes, enabled) {
	    	$radioCheckboxes.each(function(){
	    		var $radioCheckbox = $(this);
		    	if (enabled) {
		    		$radioCheckbox.prev().removeClass(disabledClass);
					$radioCheckbox.removeAttr('disabled');
		    	}
		    	else {
		    		$radioCheckbox.prev().addClass(disabledClass);
					$radioCheckbox.attr('disabled', 'disabled');
		    	}
			});
	    	return this;
		},
		check : function($radioCheckbox, isChecked) {
			var $container = $radioCheckbox.prev();
			var $thumbnail = $('img', $container);
			if (!isChecked) {
				$container.removeClass(selectedClass);
				$radioCheckbox.removeAttr('checked');
				if (typeof this.settings.unselect === 'function') { 
					this.settings.unselect($thumbnail, $radioCheckbox);
				}
			}
			else { //check radio button or checkbox
				if ($radioCheckbox.attr('type') == 'radio') {
					//remove highlight of previously selected radio button thumbnail
					$('.' + parentContainerClass + '.' + getRadioGroupName.apply($radioCheckbox) + ' > div.' + selectedClass).removeClass(selectedClass);
					if (typeof this.settings.unselect === 'function') this.settings.unselect($thumbnail, $radioCheckbox);
				}
				$container.addClass(selectedClass);
				$radioCheckbox.attr('checked', 'checked');
				if (typeof this.settings.select === 'function') {
					this.settings.select($thumbnail, $radioCheckbox);
				}
			}

	    	return this;
		},
		option : function() {
			if (arguments.length >= 2){
    			this.settings[arguments[0]] = arguments[1];
    			return this;
	    	}
	    	else if (arguments.length === 1){
	    		return this.settings[arguments[0]];
	    	}
	    	return this;
		}
    };
	
	function getRadioGroupName(){
		return 'radio-checkbox-' + this.attr('name').replace(/\[|\]/g, '');
	}
	
	$.fn.thumbnailRadioCheckbox = function() {
		var arg1 = arguments[0];
		
		this.settings = this.settings || defaultSettings;
		
		if ( methods[arg1] ) {
			return methods[ arg1 ].apply( this, Array.prototype.slice.call( arguments, 1 ));
		} else if ( typeof arg1 === 'object' || ! arg1 ) {
			return methods.init.apply( this, arguments );
		} else {
			$.error( 'Method ' +  arg1 + ' does not exist.' );
		}
	};  
})( jQuery );