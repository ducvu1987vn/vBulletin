window.vBulletin=window.vBulletin||{};window.vBulletin.phrase=window.vBulletin.phrase||{};window.vBulletin.phrase.precache=window.vBulletin.phrase.precache||[];window.vBulletin.phrase.precache=$.merge(window.vBulletin.phrase.precache,["following","following_pending","following_remove","showing_x_subscribers","showing_x_subscriptions","unable_to_contact_server_please_try_again"]);(function(A){var B=[".subscriptions-widget"];if(!vBulletin.pageHasSelectors(B)){return false}A(document).ready(function(){var C=A(".subscriptions-widget .subscribeTabs");A(".ui-tabs-nav > li",C).removeClass("ui-state-disabled");C.tabs({select:function(H,I){D.hideFilterOverlay()},show:function(H,I){if(I.tab.hash=="#subscriptionsTab"){if(D){}}else{if(I.tab.hash=="#subscribersTab"){}}}});var F=A(".subscriptions-tab",C);var E=A(".subscription-list",F);var G=new vBulletin.pagination({context:F,onPageChanged:function(H,I){D.updatePageNumber(H);if(!I){D.applyFilters(false,false,false,true)}}});var D=new vBulletin.conversation.filter({context:F,onContentLoad:function(H){if(D.isFilterSelected("mostactive")){A(".subscription-list-header .last-activity .arrow .vb-icon",F).addClass("vb-icon-triangle-down-wide").removeClass("vb-icon-triangle-up-wide")}else{if(D.isFilterSelected("leastactive")){A(".subscription-list-header .last-activity .arrow .vb-icon",F).addClass("vb-icon-triangle-up-wide").removeClass("vb-icon-triangle-down-wide")}}A(".subscriptions-totalcount",F).html(vBulletin.phrase.get("showing_x_subscriptions",H.total))},pagination:G});C.off("click",".content .follow_button").on("click",".content .follow_button",actionSubscribeButton);A(".subscriptionsContainer").off("mouseenter mouseleave",".content .follow_button").on("mouseenter",".content .follow_button",function(){var H=A(this);if(!H.hasClass("subcribe_pending")&&H.hasClass("subscribed_button")){H.data("hover-timer",setTimeout(function(){H.toggleClass("subscribed_button unsubscribe_button").toggleClass("special secondary").find(".button-text-primary").text(vBulletin.phrase.get("following_remove"))},100))}}).on("mouseleave",".content .follow_button",function(){var H=A(this);clearTimeout(H.data("hoverTimer"));if(!H.hasClass("subcribe_pending")&&H.hasClass("unsubscribe_button")){H.toggleClass("subscribed_button unsubscribe_button").toggleClass("special secondary").find(".button-text-primary").text(vBulletin.phrase.get("following"))}});A(document).off("click",".subscription-list-header .last-activity .arrow").on("click",".subscription-list-header .last-activity .arrow",function(){var H=A(".vb-icon",this).hasClass("vb-icon-triangle-down-wide")?"leastactive":"mostactive";A(this).closest(".tab").find(".conversation-toolbar-wrapper .toolbar-filter-overlay .filter-options input[name=filter_sort][value="+H+"]").click()});A(document).off("click",".subscription-list-header .subscription-name .arrow").on("click",".subscription-list-header .subscription-name .arrow",function(){var H=A(".vb-icon",this),J=H.hasClass("vb-icon-triangle-down-wide")?true:false,I=A(".subscription-item.tr .subscription-name.td a",E);I.sort(function(L,K){if(J){return A(K).text().toUpperCase().localeCompare(A(L).text().toUpperCase())}else{return A(L).text().toUpperCase().localeCompare(A(K).text().toUpperCase())}});A.each(I,function(K,L){E.append(A(L).closest(".subscription-item.tr"))});if(J){H.removeClass("vb-icon-triangle-down-wide").addClass("vb-icon-triangle-up-wide")}else{H.removeClass("vb-icon-triangle-up-wide").addClass("vb-icon-triangle-down-wide")}})})})(jQuery);actionSubscribeButton=function(){var A=$(this);var E=parseInt(A.attr("data-follow-id"));var D=A.attr("data-type");if((D=="follow_members"||D=="follow_contents")&&E){var C="";if(A.hasClass("add")){C="add"}else{if(A.hasClass("delete")){C="delete"}}var B=vBulletin.getAjaxBaseurl()+"/profile/follow_button?do="+C+"&follower="+E+"&type="+D;$.ajax({url:B,dataType:"json",success:function(F){if(F==true||F=="1"){if(C=="delete"){if(A.attr("data-canusefriends")){A.addClass("add subscribe_button secondary").removeClass("delete subscribed_button unsubscribe_button special").find(".button-text-primary").text(vBulletin.phrase.get("follow"))}else{A.remove()}}else{A.addClass("delete subscribed_button special").removeClass("add subscribe_button secondary").find(".button-text-primary").text(vBulletin.phrase.get("following"))}}else{if(F.errors){console.log(F.errors);openAlertDialog({title:vBulletin.phrase.get("following"),message:vBulletin.phrase.get("unable_to_contact_server_please_try_again"),iconType:"error"})}else{if(F==2){A.addClass("subscribe_pending secondary").removeClass("special").prop("disabled",true).find(".button-text-primary").text(vBulletin.phrase.get("following_pending"))}}}},error:function(){openAlertDialog({title:vBulletin.phrase.get("following"),message:vBulletin.phrase.get("unable_to_contact_server_please_try_again"),iconType:"error"})}})}};