window.vBulletin=window.vBulletin||{};window.vBulletin.phrase=window.vBulletin.phrase||{};window.vBulletin.phrase.precache=window.vBulletin.phrase.precache||[];window.vBulletin.phrase.precache=$.merge(window.vBulletin.phrase.precache,["email_addresses_must_match","passwords_must_match","signature","signature_saved","usersetting_signatures_link","usersetting_signature_errorsaving"]);window.vBulletin.options=window.vBulletin.options||{};window.vBulletin.options.precache=window.vBulletin.options.precache||[];window.vBulletin.options.precache=$.merge(window.vBulletin.options.precache,["ctMaxChars"]);(function(C){window.vBulletin=window.vBulletin||{};var D=[".profile-settings-widget"];if(!vBulletin.pageHasSelectors(D)){return false}var A="usersettings";vBulletin.usersettings=vBulletin.usersettings||{};vBulletin.usersettings.signature;vBulletin.usersettings.updatePreview=function(E){C.ajax({url:vBulletin.getAjaxBaseurl()+"/profile/previewSignature",type:"post",data:{signature:E},dataType:"json",success:function(F){if(F){if(F.errors){openAlertDialog({title:vBulletin.phrase.get("usersetting_signatures_link"),message:vBulletin.phrase.get("usersetting_signature_errorsaving")+": "+vBulletin.phrase.get(F.errors[0]),iconType:"error"});C(".list-item[data-node-id="+nodeid+"] .voteCtrl").removeClass("disabled")}else{C(".editSignaturePreview").html(F).slideDown()}}},complete:function(){C("body").css("cursor","default")}})};var B;vBulletin.usersettings.init=function(){if(C("#ckeditor-signature").length>0){vBulletin.usersettings.signature=C("#ckeditor-signature").val();C(document).off("click",".signatureUploadButton").on("click",".signatureUploadButton",function(F){var E=C(this).closest("form").find('textarea[name="text"]').attr("id");window.openUploadDialog(E)});C(".signaturePreview").off("click").on("click",function(F){F.preventDefault();C(".editSignaturePreview").hide();var E;if(vBulletin.ckeditor.editorExists("ckeditor-signature")){E=vBulletin.ckeditor.getEditor("ckeditor-signature").getData()}else{E=C("#ckeditor-signature").val()}if(E){if(E!=vBulletin.usersettings.signature){vBulletin.usersettings.updatePreview(E)}else{C(".editSignaturePreview").slideDown()}}});C(".editSignatureOverlay").off("click").on("click",function(E){E.preventDefault();C("#editSignatureDialog").dialog({title:vBulletin.phrase.get("usersetting_signatures_link"),autoOpen:false,modal:true,resizable:false,closeOnEscape:false,showCloseButton:false,width:500,dialogClass:"dialog-container input-dialog",create:function(){function F(){if(vBulletin.ckeditor.editorExists("ckeditor-signature")){vBulletin.ckeditor.getEditor("ckeditor-signature").setData(vBulletin.usersettings.signature)}else{C("#ckeditor-signature").val(vBulletin.usersettings.signature)}C("#editSignatureDialog").dialog("close")}C("button.cancel",this).off("click").on("click",function(G){openConfirmDialog({title:vBulletin.phrase.get("cancel_edit"),message:vBulletin.phrase.get("all_changes_made_will_be_lost_would_you_like_to_continue"),iconType:"warning",onClickYes:F})});C("button.submitSignature",this).off("click").on("click",function(I){var G;if(vBulletin.ckeditor.editorExists("ckeditor-signature")){G=vBulletin.ckeditor.getEditor("ckeditor-signature").getData()}else{G=C("#ckeditor-signature").val()}var H=[];C('#newTextForm input[name|="filedataids[]"]').each(function(){H.push(C(this).val())});C.ajax({url:vBulletin.getAjaxBaseurl()+"/ajax/api/user/saveSignature",type:"post",data:{signature:G,filedataids:H},dataType:"json",success:function(J){if(J){if(J.errors){openAlertDialog({title:vBulletin.phrase.get("signature"),message:vBulletin.phrase.get("error_saving_signature")+": "+vBulletin.phrase.get(J.errors[0]),iconType:"error"})}else{vBulletin.usersettings.signature=J;C(".editSignaturePreview").html(J);C(".userSignatureIcon").qtip({content:J});openAlertDialog({title:vBulletin.phrase.get("signature"),message:vBulletin.phrase.get("signature_saved"),iconType:"success"});F()}}},complete:function(){C("body").css("cursor","default")}})});vBulletin.ckeditor.initEditor("ckeditor-signature",{success:function(){var G=vBulletin.ckeditor.getEditor("ckeditor-signature");G.setData("",function(){G.insertHtml(vBulletin.usersettings.signature)})},error:function(){},hideLoadingDialog:true})},close:function(){}});C("#editSignatureDialog").dialog("open");return false});C(".userSignatureIcon").each(function(){C(this).qtip({content:vBulletin.usersettings.signature,position:{my:"left top",at:"middle right"},style:{classes:"ui-tooltip-shadow ui-tooltip-light ui-tooltip-rounded ui-tooltip-signature"}})})}};C(document).ready(function(){C(".settingsTabs form").trigger("reset");vBulletin.usersettings.init();var E=window.vBulletin.options.get("ctMaxChars");C(".profileSettings_reset").off("click").on("click",resetFormFields);setSelectedOption(C(".month_dropDown"),C("#bd_month"));C(".month_dropDown").selectBox().change(function(){C("#bd_day").val(C(".day_dropDown option:selected").val());updateDaySelectBox(C(".day_dropDown"),C(".month_dropDown").val())});C(".user_birth_year").off("blur").on("blur",function(){C("#bd_day").val(C(".day_dropDown option:selected").val());updateDaySelectBox(C(".day_dropDown"),C(".month_dropDown").val())});C("#profileSettings_form").ajaxForm({dataType:"json",beforeSubmit:function(L,K,J){if(C(".user_title").length&&(C(".user_title").val().length>E)){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("please_enter_user_title_with_at_least_x_characters",E),iconType:"warning"});return false}return true},success:function(L,M,N,K){if(L&&L.response&&L.response.errors){var O=[];for(var J in L.response.errors){if(L.response.errors[J][0]!="exception_trace"&&L.response.errors[J][0]!="errormsg"){O.push(vBulletin.phrase.get(L.response.errors[J]))}}openAlertDialog({title:vBulletin.phrase.get("error"),message:O.join("<br />"),iconType:"warning"})}else{window.location.reload(true)}}});setSelectedOption(C(".day_dropDown"),C("#bd_day"));setSelectedOption(C(".birthAge_dropDown"),C("#dob_display_selected"));setIMSelectedOption(C("select.im_dropDown"));C(".birthAge_dropDown").selectBox();C("select.im_dropDown").selectBox().off("change").on("change",updateProviders);createDaySelectBox(C(".day_dropDown"));B=C(".settingsTabs");var H=C(".tabs-list li.ui-tabs-selected",B);C(".ui-tabs-nav > li",B).removeClass("ui-state-disabled");B.tabs({selected:C(".settingsTabs .tabs-list li").index(H),show:function(J,K){C("select.custom-dropdown",K.panel).selectBox()}});var G=0;C("a.new_screename").off("click").on("click",function(L){L.preventDefault();var K=C(this);var N=K.parents(".settings_right");if(N.find("select.im_dropDown").length<=5){var J=K.parent();var M='<div class="im_wrapper stretch left">';M+='<input type="text" name="user_screennames[]" class="user_screenname textbox left" value="" />';M+='<select name="user_im_providers[]" class="user_im_'+G+' custom-dropdown added_dropDown im_dropDown left">';M+=getAvailableIMs(N);M+="</select>";M+='<div class="remove-screen-name vb-icon vb-icon-x-round left"></div>';M+="</div>";C(J).before(M);C(".user_im_"+G).selectBox();N.find(".im_wrapper:last .user_screenname").focus();G++;if(N.find("select.im_dropDown").length==6){K.hide()}updateProviders()}});C(document).off("click",".im_wrapper .remove-screen-name").on("click",".im_wrapper .remove-screen-name",function(){var L=C(this);var J=L.parents(".settings_right").children(".im_wrapper").length;var K=L.parents(".settings_right").find(".setting_desc a.new_screename");if(J==1){L.parent().children(".user_screenname").val("")}else{if(J>1){L.parent().children("select.im_dropDown").selectBox("destroy");L.parent().remove()}}updateProviders();K.show()});C("#new_useremail").off("focus."+A).on("focus."+A,function(J){J.preventDefault();C("#new_email_container").addClass("isActive").slideDown("3000")});C("#user_newpass").off("focus."+A).on("focus."+A,function(J){J.preventDefault();C("#new_pass_container").addClass("isActive").slideDown("3000")});C("select.ppp_dropDown").selectBox();C("select.timezone_dropDown").selectBox();C("select.dst_dropDown").selectBox();C("select.sow_dropDown").selectBox();C("select.lang_dropDown").selectBox();C("select.skin_dropDown").selectBox();C("#settingsErrorClose").off("click").on("click",function(){C("#settingsErrorDialog").dialog("close")});C(".accountSettings_reset").off("click").on("click",resetFormFields);C("#accountSettings_form").ajaxForm({dataType:"json",beforeSubmit:function(L,J,K){return I()},success:function(L,M,N,K){if(L&&L.response&&L.response.errors){var O=[];for(var J in L.response.errors){if(L.response.errors[J][0]!="exception_trace"&&L.response.errors[J][0]!="errormsg"){O.push(vBulletin.phrase.get(L.response.errors[J]))}}openAlertDialog({title:vBulletin.phrase.get("error"),message:O.join("<br />"),iconType:"warning"})}else{window.location.reload(true)}}});var I=function(){var J=C("#user_newpass"),R=C("#user_newpass2"),S=J.val(),Q=R.val();if(S||Q){var O=C("#user_currentpass");if(O.val()==""){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("enter_current_password"),iconType:"error",onAfterClose:function(){O[0].focus()}});return false}if(S!=Q){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("passwords_must_match"),iconType:"error",onAfterClose:function(){J[0].focus()}});return false}}else{J.removeAttr("name");R.removeAttr("name")}var P=C("#new_useremail"),K=C("#new_useremail2"),M=P.val(),N=K.val();if(M||N){var O=C("#user_currentpass");if(O.val()==""){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("enter_current_password"),iconType:"error",onAfterClose:function(){O[0].focus()}});return false}if(M!=N){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("email_addresses_must_match"),iconType:"error",onAfterClose:function(){P[0].focus()}});return false}var L=/^[a-z0-9_\-]+(\.[_a-z0-9\-]+)*@([_a-z0-9\-]+\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)$/;if(!L.test(P.val())){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("invalid_email_address"),iconType:"error",onAfterClose:function(){P[0].focus()}});return false}}else{P.removeAttr("name");K.removeAttr("name")}return true};C("#accountSettings_form input:checkbox[name=enable_pm]").off("change").on("change",function(){if(C(this).prop("checked")){C("#pm_controls :input").prop("disabled",false);C("#general_pm").prop("checked",true)}else{C("#pm_controls :input").prop("disabled",true);C("#general_pm").prop("checked",false)}});F();C("select.custom-dropdown").selectBox();C(".privacySettings_save").off("click").on("click",function(){C("#privacySettings_form").submit()});C(".privacySettings_reset").off("click").on("click",resetFormFields);C("#follower_request").off("change").on("change",function(){if(C(this).attr("checked")=="checked"){C("#general_followrequest").attr("checked",true)}});C(".notificationSettings_reset").off("click").on("click",resetFormFields);C(".notificationSettings_save").off("click").on("click",function(){C("#notificationSettings_form").submit()});C("#general_pm").off("change").on("change",function(){if(C(this).prop("checked")){C("#userpm_"+pageData.userid).prop("checked",true);C("#userpm_"+pageData.userid).trigger("change")}});C("#general_followrequest").off("change").on("change",function(){if(!C(this).prop("checked")){C("#follower_request").prop("checked",false)}});function F(){var K=C("#ignorelist_container").val();var J=new vBulletin_Autocomplete(C("#ignorelist_container"),{apiClass:"user",minLength:C("#minuserlength").val(),maxItems:C("#maxitems").val()});if(K){K=K.split(",");C.each(K,function(L,M){M=C.trim(M);J.addElement(M)})}}});createDaySelectBox=function(E){var G="";var I=C(".month_dropDown").val();var H=C("#bd_year").val();var J=new Date(H,I,0);if(C("#bd_day").val()==""||C("#bd_day").val()==null){G+="<option name='day' value='' selected='selected'></option>"}for(var F=1;F<=J.getDate();F++){F=(F<10)?("0"+F):F;if(F==C("#bd_day").val()){G+="<option name='day' value='"+F+"' selected='selected'>"+F+"</option>"}else{G+="<option name='day' value='"+F+"'>"+F+"</option>"}}E.html(G);E.selectBox("destroy").selectBox()};updateDaySelectBox=function(E,I){var H=(C(".user_birth_year").val()!="")?C(".user_birth_year").val():0;var J=new Date(H,I,0);J=J.getDate();var G="";for(var F=1;F<=J;F++){F=(F<10)?("0"+F):F;G+="<option name='day' value='"+F+"'>"+F+"</option>"}updateSelectBox(E,G)};updateSelectBox=function(E,F,H){F=(F)?F:E.html();var G=E.attr("class").split(" ");E.selectBox("destroy");E.removeData("selectBoxControl");E.removeData("selectBoxSettings");E.html(F);if(C("#"+G[0]).val()!=undefined){E.val(C("#"+G[0]).val())}E.selectBox()};setSelectedOption=function(F,E){F.val(E.val())};setIMSelectedOption=function(E){var F="";C.each(E,function(J,H){var I=C(H).attr("class").split(" ");F=C(H).parent().children(":input[type=hidden]."+I[0]).val();C(H).val(F)});var G="";C.each(E,function(I,H){G=C(H).parents(".settings_right").find(".im_wrapper select.im_dropDown option:selected");C.each(C(G),function(J,K){if(C(H).val()!=C(K).val()){C(H).find("option[value= "+C(K).val()+"]").remove()}});updateProviders()})};getAvailableIMs=function(F){var G={AIM:"aim","Google Talk":"google","Skype&trade;":"skype","Windows Live Messenger":"msn","Yahoo! Messenger":"yahoo",ICQ:"icq"};var E="";C.each(G,function(H,I){if(F.find("select.im_dropDown option:selected[value= "+I+"]").length==0){E+="<option name='im_provider' value='"+I+"'> "+H+"</option>"}});return E};updateImSelectBox=function(E,F){E.selectBox("destroy");E.removeData("selectBoxControl");E.removeData("selectBoxSettings");E.html(F);E.selectBox()};updateProviders=function(){var E={AIM:"aim","Google Talk":"google","Skype&trade;":"skype","Windows Live Messenger":"msn","Yahoo! Messenger":"yahoo",ICQ:"icq"};C.each(C("select.im_dropDown"),function(H,F){var I=C(F).val();var G="";C.each(E,function(J,K){if(C("select.im_dropDown option:selected[value= "+K+"]").length==0){G+="<option name='im_provider' value='"+K+"'>"+J+"</option>"}else{if(K==I){G+="<option name='im_provider' value='"+K+"' selected='selected'>"+J+"</option>"}}});updateImSelectBox(C(F),G)})};resetFormFields=function(){var E=C(this.form);setTimeout(function(){C("input",E).trigger("change");C("select",E).each(function(){updateSelectBox(C(this))})},100)}})(jQuery);