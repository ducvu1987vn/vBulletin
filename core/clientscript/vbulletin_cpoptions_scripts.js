/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 5.0.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
var multi_input=new Array();function vB_Multi_Input(B,D,A){this.varname=B;this.count=D;this.cpstylefolder=A;this.add=function(){var E=document.createElement("div");E.id="multi_input_container_"+this.varname+"_"+this.count;E.appendChild(document.createTextNode((this.count+1)+" "));E.appendChild(this.create_input(this.count+1));fetch_object("multi_input_fieldset_"+this.varname).appendChild(E);this.append_buttons(this.count++);return false};this.create_input=function(){var E=document.createElement("input");E.type="text";E.size=40;E.className="bginput";E.name="setting["+this.varname+"]["+this.count+"]";E.id="multi_input_"+this.varname+"_"+this.count;E.tabIndex=1;return E};this.create_button=function(G,F,I){var E=document.createElement("a");E.varname=this.varname;E.index=G;E.moveby=I;E.href="#";E.onclick=function(){return multi_input[this.varname].move(this.index,this.moveby)};var H=document.createElement("img");H.src="../cpstyles/"+this.cpstylefolder+"/move_"+F+".gif";H.alt="";H.border=0;E.appendChild(H);return E};this.append_buttons=function(E){var F=fetch_object("multi_input_container_"+this.varname+"_"+E);F.varname=this.varname;F.index=E;F.appendChild(document.createTextNode(" "));F.appendChild(this.create_button(E,"down",1));F.appendChild(document.createTextNode(" "));F.appendChild(this.create_button(E,"up",-1))};this.fetch_input=function(E){return fetch_object("multi_input_"+this.varname+"_"+E)};this.move=function(F,H){var G,E=new Array();for(G=0;G<this.count;G++){E[G]=this.fetch_input(G).value}if(F==0&&H<0){for(G=0;G<this.count;G++){this.fetch_input(G).value=(G==(this.count-1)?E[0]:E[G+1])}}else{if(F==(this.count-1)&&H>0){for(G=0;G<this.count;G++){this.fetch_input(G).value=(G==0?E[this.count-1]:E[G-1])}}else{this.fetch_input(F).value=E[F+H];this.fetch_input(F+H).value=E[F]}}E=null;return false};for(var C=0;C<this.count;C++){this.append_buttons(C)}};