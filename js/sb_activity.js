window.vBulletin=window.vBulletin||{};window.vBulletin.phrase=window.vBulletin.phrase||{};window.vBulletin.phrase.precache=window.vBulletin.phrase.precache||[];window.vBulletin.phrase.precache=$.merge(window.vBulletin.phrase.precache,["blog_memberblogLabel","edit_conversation","forum","you_have_a_pending_edit_unsaved"]);(function(A){var B=[".forum-activity-stream-widget",".bloghome-widget",".search-widget"];if(!vBulletin.pageHasSelectors(B)){return false}A(document).ready(function(){vBulletin.conversation=vBulletin.conversation||{};var J=vBulletin.conversation.$activityStreamWidget=A(".activity-stream-widget"),C=vBulletin.conversation.$activityStreamTab=A("#activity-stream-tab"),G=vBulletin.conversation.$subscribedTab=A("#subscribed-tab"),H=vBulletin.conversation.$activityStreamList=A(".conversation-list",C),Q=vBulletin.conversation.$subscribedList=A(".conversation-list",G),S,T,N,F,V,P,O=A(".conversation-list",J),M,U;var E=J.find(".widget-tabs-nav .ui-tabs-nav > li"),L=E.filter(".ui-tabs-selected"),K=L.index(),W;if(K==-1){K=0;L=E.first()}W=L.find("> a").attr("href");var I=function(X){var Y=(J.offset().top+(J.outerHeight()-parseFloat(J.css("border-bottom-width")))-X.height());return Y};E.removeClass("ui-state-disabled");J.tabs({selected:K,select:function(Z,a){if(P){P.hideFilterOverlay()}if(N){N.hideFilterOverlay()}var Y=J.find(".widget-tabs-panel .ui-tabs-panel:visible");var X=Y.find(".list-item-body-wrapper.edit-post .edit-conversation-container");if(X.length>0){openAlertDialog({title:vBulletin.phrase.get("edit_conversation"),message:vBulletin.phrase.get("you_have_a_pending_edit_unsaved"),iconType:"warning",onAfterClose:function(){vBulletin.animateScrollTop(X.closest(".list-item").offset().top,{duration:"slow"})}});return false}},show:function(Y,Z){if(Z.tab.hash=="#memberblog-tab"){function b(c){vBulletin.AJAX({url:vBulletin.getAjaxBaseurl()+"/ajax/render/blogmember_tab",data:{from:c||1},error_phrase:"unable_to_contact_server_please_try_again",success:function(e){A(Z.panel).html(e);var d=A(".toolbar-pagenav",Z.panel);if(d.length>0){vBulletin.pagination({context:d,onPageChanged:b})}}})}if(P){P.toggleNewConversations(false)}if(N){N.toggleNewConversations(false)}var X=A(".conversation-list",Z.panel);if(!X.hasClass("dataLoaded")){if(W==Z.tab.hash&&!U){U=true;return false}A(".conversation-empty",Z.panel).addClass("hide");b()}return }else{if(Z.tab.hash=="#activity-stream-tab"){if(N){N.toggleNewConversations(false)}if(!P){F=A(".conversation-toolbar-wrapper.scrolltofixed-floating",C);V=false;P=vBulletin.conversation.activityStreamFilter=new vBulletin.conversation.filter({context:C,autoCheck:A(".toolbar-filter-overlay input[type=radio][value=conversations_on]",J).is(":checked"),scrollToTop:J,onContentLoad:function(){if(!V){V=new vBulletin.scrollToFixed({element:F,limit:I(F)})}V.updateLimit(I(F));vBulletin.truncatePostContent(H);vBulletin.conversation.processPostContent(H)}});if(W==Z.tab.hash){vBulletin.truncatePostContent(H);vBulletin.conversation.processPostContent(H);P.lastFilters={filters:P.getSelectedFilters(A(".toolbar-filter-overlay",C))}}}else{if(typeof P.lastFilters!="undefined"&&A(".conversation-empty:not(.hide)",Z.panel).length>0){delete P.lastFilters}}P.applyFilters(false,true)}else{if(Z.tab.hash=="#subscribed-tab"){if(P){P.toggleNewConversations(false)}if(!N){S=A(".conversation-toolbar-wrapper.scrolltofixed-floating",G);T=new vBulletin.scrollToFixed({element:S,limit:I(S)});N=vBulletin.conversation.subscribedFilter=new vBulletin.conversation.filter({context:G,scrollToTop:J,onContentLoad:function(){T.updateLimit(I(S));vBulletin.truncatePostContent(Q);vBulletin.conversation.processPostContent(Q)}});if(W==Z.tab.hash){vBulletin.truncatePostContent(Q);vBulletin.conversation.processPostContent(Q);N.lastFilters={filters:N.getSelectedFilters(A(".toolbar-filter-overlay",G))}}}else{if(typeof N.lastFilters!="undefined"&&A(".conversation-empty:not(.hide)",Z.panel).length>0){delete N.lastFilters}}N.applyFilters(false,true)}else{if(Z.tab.hash=="#forum-tab"){if(P){P.toggleNewConversations(false)}if(N){N.toggleNewConversations(false)}var a=A(Z.panel);if(a.hasClass("dataLoaded")){if(W==Z.tab.hash){vBulletin.markreadcheck()}return false}else{if(W==Z.tab.hash&&!M){M=true;return false}}A(".conversation-empty",Z.panel).addClass("hide");A.post(vBulletin.getAjaxBaseurl()+"/ajax/render/display_Forums_tab",function(c){a.html(c).addClass(function(){var d=A(".conversation-empty",this);if(d.length==0){return"dataLoaded"}else{d.removeClass("hide");return""}})},"json").error(function(e,d,c){console.log("/ajax/render/display_Forums_tab failed. Error: "+c);openAlertDialog({title:vBulletin.phrase.get("forum"),message:vBulletin.phrase.get("unable_to_contact_server_please_try_again"),iconType:"error"})}).complete(function(){vBulletin.markreadcheck()})}}}}}});vBulletin.truncatePostContent(".search-widget");var D=0;J.find(".ui-tabs-nav li").each(function(){D+=A(this).width()});var R=J.find(".ui-tabs-nav").width();if(D>J.find(".ui-tabs-nav").width()){J.find(".widget-tabs-nav, .module-title").height(J.find(".ui-tabs-nav").height())}J.off("click",".list-item-poll .view-more-ctrl").on("click",".list-item-poll .view-more-ctrl",function(Y){var X=A(this).closest("form.poll");var Z=X.find("ul.poll");A(this).addClass("hide");Z.css("max-height","none").find("li.hide").slideDown(100,function(){X.find(".action-buttons").removeClass("hide").next(".view-less-ctrl").removeClass("hide");vBulletin.animateScrollTop(X.offset().top,{duration:"fast"})});return false});J.off("click",".list-item-poll .view-less-ctrl").on("click",".list-item-poll .view-less-ctrl",function(Y){var X=A(this).closest("form.poll");vBulletin.conversation.limitVisiblePollOptionsInAPost(X,3);X.find("ul.poll").css("max-height","").find("li.hide").slideUp(100);return false});O.off("click",".editCtrl").on("click",".editCtrl",function(X){vBulletin.conversation.editPost.apply(this,[X,P])});O.off("click",".post-history").on("click",".post-history",vBulletin.conversation.showPostHistory);O.off("click",".ipAddress").on("click",".ipAddress",vBulletin.conversation.showIp);O.off("click",".voteCtrl").on("click",".voteCtrl",function(X){if(A(X.target).closest(".bubble-flyout").length==1){vBulletin.conversation.showWhoVoted.apply(X.target,[X])}else{vBulletin.conversation.votePost.apply(this,[X])}return false});O.off("click",".flagCtrl").on("click",".flagCtrl",vBulletin.conversation.flagPost);O.off("click",".commentCtrl").on("click",".commentCtrl",vBulletin.conversation.toggleCommentBox);O.off("click",".comment-entry-box .post-comment-btn").on("click",".comment-entry-box .post-comment-btn",function(X){vBulletin.conversation.postComment.apply(this,[X,function(){P.updatePageNumber(1).applyFilters(false,true)}])});vBulletin.conversation.bindEditFormEventHandlers("all")})})(jQuery);