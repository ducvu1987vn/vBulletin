window.vBulletin=window.vBulletin||{};window.vBulletin.phrase=window.vBulletin.phrase||{};window.vBulletin.phrase.precache=window.vBulletin.phrase.precache||[];window.vBulletin.phrase.precache=$.merge(window.vBulletin.phrase.precache,["error","error_adding_search_tips_code_x","invalid_json_string","invalid_search_syntax","invalid_server_response_please_try_again","search_tag_cloud","please_select_past_date","please_select_valid_date_range","content_type_Text","content_type_Gallery","content_type_Link","content_type_Photo","content_type_Poll","content_type_PrivateMessage","content_type_Video","save_as_search_module","saved_search_module_as_x","search_module_already_exists","search_module_not_found","please_enter_search_module_name","error_saving_search_module_x"]);(function(A){var B=[".search-fields-widget",".search-results-widget","#search-config-dialog"];if(!vBulletin.pageHasSelectors(B)){return false}window.vBulletin.search=window.vBulletin.search||{};window.vBulletin.search.SearchControl=function(J){var C=A(J),F=false,D=false,K=function(){var O=C.find(".searchSwitchToAdvanced");var T=C.find(".searchSwitchToForm");var L=O.is(":visible");var Q=T.is(":visible");if(L==Q){O.toggle(true);T.toggle(false);C.find(".form_row.form-row-json").addClass("hide-imp").nextAll().show()}O.off("click").on("click",function(){var U=I();if(!U){return false}var V=JSON.stringify(U);C.find(".searchFields_searchJSON").val(V);C.find(".form_row.form-row-json").removeClass("hide-imp").nextAll().hide();O.toggle(false);T.toggle(true)});T.off("click").on("click",function(){if(H(C.find(".searchFields_searchJSON").val())){O.toggle(true);T.toggle(false);C.find(".form_row.form-row-json").addClass("hide-imp").nextAll().show()}});var P=C.find(".advSearchForm");if(P.length>0){P.submit(G)}var S=C.find(".searchSubmitBtn");if(S.length>0){S.off("click").on("click",G)}var R=C.find(".searchResetBtn");if(R.length>0){R.off("click").on("click",function(){H("{}")})}var M="#"+C.attr("id")+"-searchFields_tag";F=new vBulletin.tagEditor.instance(M,true);var N=C.find(".searchFields_author");if(N.length>0){D=new vBulletin_Autocomplete(N,{apiClass:"user",containerClass:"entry-field clearfix"})}C.find(".searchFields_keywords").off("keydown").on("keydown",function(U){if(U.which==13){if(P.length>0){P.submit()}}});C.find(".searchFields_last_visit").off("click").on("click",function(U){C.find(".datefield").prop("disabled",A(this).prop("checked"));if(A(this).is(":checked")){C.find(".searchFields_from_date").datepicker("disable");C.find(".searchFields_to_date").datepicker("disable")}else{C.find(".searchFields_from_date").datepicker("enable");C.find(".searchFields_to_date").datepicker("enable")}});C.find(".searchFields_channel_param").off("click").on("click",function(U){if(A(this).prop("checked")){C.find(".searchFields_channel").selectBox("disable")}else{C.find(".searchFields_channel").selectBox("enable")}});C.find(".search-tips").off("click").on("click",function(U){A(".search-tips-dialog").first().dialog({title:vBulletin.phrase.get("search_tips"),autoOpen:false,modal:true,resizable:false,closeOnEscape:true,showCloseButton:true,width:500,dialogClass:"dialog-container search-tips-dialog-container dialog-box"}).dialog("open");U.stopPropagation();return false});C.find(".searchFields_channel").selectBox();C.find(".searchFields_order_field").selectBox();C.find(".searchFields_order_direction").selectBox()},G=function(M){if(!C.find(".searchAdvancedFields").is(":visible")){var L=I();if(!L){return false}if(!L.author&&!L.channel&&!L.keywords&&!L.tag&&!L.type&&!L.date&&!L.last_visit){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("error_no_criteria"),iconType:"warning"});return false}C.find(".searchFields_searchJSON").val(JSON.stringify(L));C.find("form.advSearchForm").append(A("<input>").attr("name","humanverify[input]").attr("type","hidden").val(A('div.humanverify [name="humanverify[input]"]').val())).append(A("<input>").attr("name","humanverify[hash]").attr("type","hidden").val(A('div.humanverify [name="humanverify[hash]"]').val()))}},I=function(){var P={};if(C.find(".searchSwitchToForm").is(":visible")){P=JSON.parse(C.find(".searchFields_searchJSON").val()||"{}");if(P.length==0){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("invalid_json_string"),iconType:"warning"});return }return P}C.find("input[placeholder].placeholder").each(function(){if(A(this).val()==A(this).attr("placeholder")){A(this).val("")}});var n=C.find(".searchFields_keywords");if(n.length>0&&A.trim(n.val()).length>0){P.keywords=A.trim(n.val())}var L=C.find(".searchFields_title_only");if(L.length>0&&L.prop("checked")){P.title_only=1}var j=C.find(".searchFields_starter_only");if(j.length>0&&j.prop("checked")){P.starter_only=1}var O=C.find(".searchFields_myFriends");var c=C.find(".searchFields_iFollow");if(O.length>0&&O.prop("checked")){P.author="myFriends"}else{if(c.length>0&&c.prop("checked")){P.author="iFollow"}else{if(D){var m=D.getLabels();if(m.length>0){P.author=m}}}}var W=C.find(".tag-input");if(W.length>0){var N=C.find(".tag-input").val();if(N.length>0){var Z=N.split(",");if(Z.length>0){P.tag=Z}}}var i=C.find(".searchFields_last_visit");if(i.length>0&&i.prop("checked")){P.last_visit=1}else{var M={};var R=C.find(".searchFields_from_days");if(R.length>0&&A.trim(R.val()).length>0){M.from=A.trim(R.val())}else{var g=C.find(".searchFields_from_date");var f=new Date();var h=f.getTime();if(g.length>0&&A.trim(g.val()).length>0){var X=Date.parse(g.val());if(X>h){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("please_select_past_date"),iconType:"warning"});return false}M.from=A.trim(g.val())}var a=C.find(".searchFields_to_date");if(a.length>0&&A.trim(a.val()).length>0){var V=Date.parse(a.val());if(V>h){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("please_select_past_date"),iconType:"warning"});return false}if(X&&X>V){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("please_select_valid_date_range"),iconType:"warning"});return false}M.to=A.trim(a.val())}}if(!A.isEmptyObject(M)){P.date=M}}var b=C.find(".searchFields_featured");if(b.length>0&&b.prop("checked")){P.featured=1}var U=C.find(".searchFields_my_following");if(U.length>0&&U.prop("checked")){P.my_following=1}var Y=[];C.find(".searchFields_type:checked").each(function(o,p){Y.push(A(p).val())});if(A(Y).length>0){P.type=Y}var e=C.find(".searchFields_channel_param");if(e.length>0&&e.prop("checked")){P.channel={param:"channelid"}}else{var k=C.find("select.searchFields_channel");if(k.length>0){var S=k.val();if(S){P.channel=S}}}var Q=C.find(".searchFields_order_field");var T=C.find(".searchFields_order_direction");if(Q.length>0&&T.length>0&&Q.val()!=""){P.sort=Q.val();if(T.val()!=""){P.sort={};P.sort[Q.val()]=T.val()}}var l=C.find(".searchFields_exclude");if(l.length>0&&A.trim(l.val()).length>0){P.exclude=A.trim(l.val()).split(",")}var d=C.find(".searchFields_exclude_type");if(d.length>0&&A.trim(d.val()).length>0){P.exclude_type=A.trim(d.val()).split(",")}return P},H=function(e,i,t,R){var P=e;if(typeof e=="string"){try{e=JSON.parse(e||"{}");if(e.length==0){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("invalid_json_string"),iconType:"warning"});return false}}catch(p){openAlertDialog({title:vBulletin.phrase.get("error"),message:vBulletin.phrase.get("invalid_json_string"),iconType:"warning"});return false}}else{P=JSON.stringify(e)}var L=C.find(".searchFields_keywords");if(L.length>0){L.val(e.keywords||"");delete e.keywords}var h=C.find(".searchFields_title_only");if(h){h.prop("checked",e.title_only?true:false);delete e.title_only}var N=C.find(".searchFields_myFriends");var d=false;if(N.length>0){if(e.author&&e.author=="myFriends"){d=true;delete e.author}N.prop("checked",d)}var c=C.find(".searchFields_iFollow");var j=false;if(c.length>0){if(e.author&&e.author=="iFollow"){j=true;delete e.author}c.prop("checked",j)}var U=C.find(".searchFields_author");if(U.length>0){var Y=false;if(A.isArray(e.author)){Y=e.author}else{if(e.exactname){Y=[e.author]}}if(Y){D.setElements(Y);delete e.author;delete e.exactname}}var W=C.find(".searchFields_starter_only");if(W.length>0){W.prop("checked",e.starter_only?true:false);delete e.starter_only}var n=C.find(".tag-input");if(n.length>0){var m=e.tag||[];n.val(m);F.setTags(m);C.find(".tag-list span").html(m.join(", "));delete e.tag}var O=C.find(".searchFields_last_visit");var Q=false;if(e.last_visit||(e.date&&e.date.from&&e.date.from=="lastVisit")){Q=true}if(O.length>0){O.prop("checked",Q);C.find(".datefield").prop("disabled",Q);if(e.last_visit){delete e.last_visit}if(e.date&&e.date.from&&e.date.from=="lastVisit"){delete e.date["from"]}}var q=C.find(".searchFields_from_date");var v=C.find(".searchFields_to_date");var M=C.find(".searchFields_from_days");if(!Q){if(q.length>0){var f="";if(e.date&&e.date.from){f=e.date.from;delete e.date["from"]}q.val(f)}if(v.length>0){var s="";if(e.date&&e.date.to){s=e.date.to;delete e.date["to"]}v.val(s)}if(M.length>0){var f="";if(e.date&&e.date.from){f=e.date.from;delete e.date["from"]}M.val(f)}if(A.isEmptyObject(e.date)){delete e.date}}if(q.length>0){q.datepicker(O.is(":checked")?"disable":"enable")}if(v.length>0){v.datepicker(O.is(":checked")?"disable":"enable")}var l=C.find(".searchFields_featured");if(l.length>0){l.prop("checked",e.featured?true:false);delete e.featured}var V=C.find(".searchFields_my_following");if(V.length>0){V.prop("checked",e.my_following?true:false);delete e.my_following}var r=C.find(".searchFields_type_container");if(r.length>0){if(i){var X=r.find(".field-desc");X.prevAll().remove();A.each(i,function(x,w){if(x!="vBForum_PrivateMessage"){X.before(A('<label><input type="checkbox" name="searchFields[type][]" class="searchFields_type" value="'+x+'" /><span>'+vBulletin.phrase.get("content_type_"+w["class"])+"</span></label>"))}})}C.find(".searchFields_type").val(e.type||[]);delete e.type;delete e.exclude_type}var o=C.find(".searchFields_channel_param");var a=false;if(o.length>0){if(typeof e.channel=="object"&&e.channel.param){a=true;delete e.channel}o.prop("checked",a)}var k=C.find(".searchFields_channel");if(k.length>0){var u=false;if(o.length>0){k.prop("disabled",a)}if(t){k.children().remove();E(t,k);u=true}if(!a){k.val(e.channel||"");delete e.channel;u=true}if(u){resetSelectBox(k)}}var b=C.find("select.searchFields_order_direction");var S=C.find("select.searchFields_order_field");var g="";var Z="";if(e.sort){if(typeof e.sort=="string"){g=e.sort}else{if(typeof e.sort=="object"){A.each(e.sort,function(w,x){g=w;Z=x})}}delete e.sort}if(b.length>0){b.val(Z);resetSelectBox(b)}if(S.length>0){S.val(g);resetSelectBox(S)}if(!A.isEmptyObject(e)){var T=C.find(".searchSwitchToAdvanced");if(T.length>0){C.find(".form_row.form-row-json").removeClass("hide-imp").nextAll().hide();T.hide();C.find(".searchSwitchToForm").show();C.find(".searchFields_searchJSON").val(P)}return false}return true},E=function(L,M){A.each(L,function(O,N){M.append(A("<option></option>").val(O).html(str_repeat("&nbsp;",N.depth*3).concat(N.htmltitle)));if(N.channels){E(N.channels,M)}})};this.load=function(){return I()};this.set=function(O,M,L,N){return H(O,M,L,N)};K()};A(document).ready(function(){var C=A("#advancedSearchFields"),I=A(".search-results-widget");vBulletin.truncatePostContent(I);if(C.length>0){var D=new vBulletin.search.SearchControl(C);searchJSONStr=C.find(".searchFields_searchJSON").val();if(searchJSONStr.length>0&&searchJSONStr!=C.find(".searchFields_searchJSON").attr("placeholder")){D.set(searchJSONStr,false,false,true)}C.find(".searchFields_from_date").datepicker({showOn:"both",gotoCurrent:true,maxDate:(new Date()),buttonImage:vBulletin.getAjaxBaseurl()+"/images/calendar-blue.png",buttonImageOnly:true,onSelect:function(N,M){C.find(".searchFields_to_date").datepicker("option","minDate",new Date(N))}});C.find(".searchFields_to_date").datepicker({showOn:"both",gotoCurrent:true,maxDate:(new Date()),buttonImage:vBulletin.getAjaxBaseurl()+"/images/calendar-blue.png",buttonImageOnly:true,onSelect:function(P,O){var M=new Date();var N=new Date(P);if(M.getTime()>N.getTime()){M=N}var Q=C.find(".searchFields_from_date");Q.datepicker("option","maxDate",M)}})}var J=A(".sort-controls",I);if(J.length>0){J.find(".searchFields_order_field").selectBox().change(function(){var N=K.find(".searchResults_searchJSON").val()||"{}";var M=JSON.parse(N);M.sort={};M.sort[A(this).val()]=J.find(".searchFields_order_direction").selectBox().val();K.find(".searchResults_searchJSON").val(JSON.stringify(M));A(".resultSearchForm").submit()});J.find(".searchFields_order_direction").selectBox().change(function(){var N=K.find(".searchResults_searchJSON").val()||"{}";var M=JSON.parse(N);M.sort={};M.sort[J.find(".searchFields_order_field").selectBox().val()]=A(this).val();K.find(".searchResults_searchJSON").val(JSON.stringify(M));A(".resultSearchForm").submit()});J.removeClass("invisible")}var K=A(".search-controls",I);if(K.length>0){var H=false;var L=false;K.find(".resultPopupControl").off("click").on("click",function(M){if(A(this).hasClass("open")){A(this).removeClass("open");A(this).siblings(".PopupContent").hide()}else{K.find(".resultPopupControl.open").each(function(O,N){A(N).siblings(".PopupContent").hide();A(N).removeClass("open")});A(this).addClass("open");A(this).siblings(".PopupContent").show()}M.stopPropagation();return false});var E=K.find(".searchFields_author");if(E.length>0){H=new vBulletin_Autocomplete(E,{apiClass:"user",containerClass:"entry-field clearfix"});K.find(".search-controls-members .removable-element .element-text").each(function(N,M){H.addElement(A(M).html())})}if(K.find("#searchResultTagEditor").length>0){L=new vBulletin.tagEditor.instance("#searchResultTagEditor",true);K.find(".search-controls-tags .removable-element .element-text").each(function(N,M){L.addTag(A(M).html())})}K.find(".searchPopupBody button").off("click").on("click",function(O){var P=K.find(".searchResults_searchJSON").val()||"{}";var M=JSON.parse(P);var N=A(this).val();switch(N){case"keywords":M.keywords=K.find(".searchFields_keywords").val();M.title_only=K.find(".searchFields_title_only").is(":checked");break;case"author":M.author=H.getLabels();M.starter_only=K.find(".searchFields_starter_only").is(":checked");break;case"tags":M.tag=L.getTags();break;default:return ;break}K.find(".searchResults_searchJSON").val(JSON.stringify(M));A(".resultSearchForm").submit()});K.find(".removable-element .element-x").off("click").on("click",function(Q){var R=K.find(".searchResults_searchJSON").val()||"{}";var N=JSON.parse(R);var P=A(this).parents(".search-control-popup");if(P.hasClass("search-controls-members")){A(this).parents(".removable-element").remove();var M=[];P.find(".removable-element .element-text").each(function(T,S){M.push(A(S).html())});N.author=M;if(M.length==0){delete N.author}}if(P.hasClass("search-controls-tags")){A(this).parents(".removable-element").remove();var O=[];P.find(".removable-element .element-text").each(function(T,S){O.push(A(S).html())});N.tag=O;if(O.length==0){delete N.tag}}K.find(".searchResults_searchJSON").val(JSON.stringify(N));A(".resultSearchForm").submit()})}var G=A(".search-stats",I);if(G.length>0){}A("html").off("click.searchpopup").on("click.searchpopup",function(M){var N=A(M.target);if(K.length>0&&N.closest(".PopupContent").length==0&&N.closest(".ui-autocomplete").length==0&&K.find(".resultPopupControl.open").length>0&&(A(".tag-editor-container").length==0||A(".tag-editor-container").dialog("isOpen")!==true)){K.find(".resultPopupControl.open").siblings(".PopupContent").hide();K.find(".resultPopupControl.open").removeClass("open")}});if(vBulletin.inlinemod&&typeof vBulletin.inlinemod.init=="function"){vBulletin.inlinemod.init(I)}I.off("click",".post-controls .ipAddress").on("click",".post-controls .ipAddress",vBulletin.conversation.showIp);I.off("click",".post-controls .voteCtrl").on("click",".post-controls .voteCtrl",function(M){if(A(M.target).closest(".bubble-flyout").length==1){vBulletin.conversation.showWhoVoted.apply(M.target,[M])}else{vBulletin.conversation.votePost.apply(this,[M])}return false});I.off("click",".post-controls .editCtrl").on("click",".post-controls .editCtrl",vBulletin.conversation.editPost);I.off("click",".post-controls .flagCtrl").on("click",".post-controls .flagCtrl",vBulletin.conversation.flagPost);I.off("click",".post-controls .commentCtrl").on("click",".post-controls .commentCtrl",vBulletin.conversation.toggleCommentBox);I.off("click",".comment-entry-box .post-comment-btn").on("click",".comment-entry-box .post-comment-btn",function(M){vBulletin.conversation.postComment.apply(this,[M,function(N){location.reload()}])});var F=(typeof I.data("keywords")!="undefined")?I.data("keywords").toString():"";if(F.length>0){A(F.split(" ")).each(function(N,M){A(".post-header",I).highlight(M);if(!A(".searchFields_title_only",I).attr("checked")){A(".post-content",I).highlight(M)}})}})})(jQuery);function resetSelectBox(A){A.selectBox("destroy");A.removeData("selectBoxControl");A.removeData("selectBoxSettings");A.selectBox()};