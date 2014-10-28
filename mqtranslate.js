qt_getEl=function(id){return document.getElementById(id);}

String.prototype.xsplit = function(_regEx){
	// Most browsers can do this properly, so let them — they'll do it faster
	if ('a~b'.split(/(~)/).length === 3) { return this.split(_regEx); }

	if (!_regEx.global){
		_regEx = new RegExp(_regEx.source, 'g' + (_regEx.ignoreCase ? 'i' : '')); 
	}

	// IE (and any other browser that can't capture the delimiter)
	// will, unfortunately, have to be slowed down
	var start = 0, arr=[];
	var result;
	while((result = _regEx.exec(this)) != null){
		arr.push(this.slice(start, result.index));
		if(result.length > 1) arr.push(result[1]);
		start = _regEx.lastIndex;
	}
	if(start < this.length) arr.push(this.slice(start));
	if(start == this.length) arr.push(''); //delim at the end
	return arr;
};

qtrans_isArray = function(obj) {
   return (obj.constructor.toString().indexOf('Array') == -1) ? false : true;
}

qt_addElement=function(tag,className,addText,childs){
	var elem = document.createElement(tag);
	if (className!="") elem.className = className;
	if (addText){
		var tN = document.createTextNode(addText);
		elem.appendChild(tN);
	}
	if (childs) for (var n in childs) elem.appendChild(childs[n]);
	return elem;
}

qtrans_split = function(text) {
	var split_regex = /(<!--.*?-->)/gi;
	var lang_begin_regex = /<!--:([a-z]{2})-->/gi;
	var lang_end_regex = /<!--:-->/gi;
	var morenextpage_regex = /(<!--more-->|<!--nextpage-->)+$/gi;
	var matches = null;
	var result = new Object;
	var matched = false;
	
	for (var n in arrLangs){result[arrLangs[n]] = '';}

	var blocks = text.xsplit(split_regex);
	if(qtrans_isArray(blocks)) {
		for (var i = 0;i<blocks.length;i++) {
			if((matches = lang_begin_regex.exec(blocks[i])) != null) {
				matched = matches[1];
			} else if(lang_end_regex.test(blocks[i])) {
				matched = false;
			} else {
				if(matched) {
					result[matched] += blocks[i];
				} else {
					for (var n in arrLangs){
						lang=arrLangs[n];
						result[lang] += blocks[i];
					}
				}
			}
		}
	}
	for (var i = 0;i<result.length;i++) {
		result[i] = result[i].replace(morenextpage_regex,'');
	}
	return result;
}

qtrans_use = function(lang, text) {
	var result = qtrans_split(text);
	return result[lang];
}

qtrans_integrate = function(lang, lang_text, text) {
	var texts = qtrans_split(text);
	var moreregex = /<!--more-->/i;
	var text = '';
	var max = 0;
	var morenextpage_regex = /(<!--more-->|<!--nextpage-->)+$/gi;
			
	texts[lang] = lang_text;
	for (var n in arrLngs){
		texts[n] = texts[n].split(moreregex);
		if(!qtrans_isArray(texts[n])) {
			texts[n] = [texts[n]];
		}
		if(max < texts[n].length) max = texts[n].length;
	}

	for(var i=0; i<max; i++) {
		if(i >= 1) text += '<!--more-->';

		for (var n in arrLngs){
			if(texts[n][i] && texts[n][i]!=''){
				text += '<!--:' + n + '-->' + texts[n][i] + '<!--:-->';
			}
		}
	}
	text = text.replace(morenextpage_regex,'');
	return text;
}

qtrans_save = function(text) {
	var ta = qt_getEl('content');
	ta.value = qtrans_integrate(qtrans_get_active_language(),text,ta.value);
	return ta.value;
}

qtrans_integrate_category = function() {
	var t = qt_getEl('cat_name');
	for (var n in arrLngs){
		qcatVal=qt_getEl('qtrans_category_'+n).value;
		if(qcatVal!='') t.value = qtrans_integrate(n,qcatVal,t.value);
	}
}

qtrans_integrate_tag = function() {
	var t = qt_getEl('name');
	for (var n in arrLangs){
		lang=arrLangs[n];
		if(qt_getEl('qtrans_tag_'+ lang).value!='')
			t.value = qtrans_integrate(lang,qt_getEl('qtrans_tag_'+lang).value,t.value);
	}
}

qtrans_assign = function(id, text) {
	var inst = tinyMCE.get(id);
	var ta = qt_getEl(id);
	if(inst && ! inst.isHidden()) {
		text = switchEditors.wpautop(text);
		inst.execCommand('mceSetContent', null, text);
	} else {
		ta.value = text;
	}
}

qtrans_integrate_link_category = function() {
	var t = qt_getEl('name');
	for (var n in arrLangs){
		lang=arrLangs[n];
		if(qt_getEl('qtrans_link_category_'+lang).value!='')
			t.value = qtrans_integrate(lang,qt_getEl('qtrans_link_category_'+lang).value,t.value);
	}
}

qtrans_editorInit = function() {
	qtrans_editorInit1();
	qtrans_editorInit2();
	jQuery('#qtrans_imsg').hide();
	qtrans_editorInit3();

	jQuery('#content').hide();
	tAreaCnt=jQuery('#qtrans_textarea_content');
	if ( getUserSetting( 'editor' ) == 'html' ) {
		tAreaCnt.show();
	} else {
		// Activate TinyMCE if it's the user's default editor
		tAreaCnt.show();
		// correct p for tinymce
		tAreaCnt.val(switchEditors.wpautop(tAreaCnt.val()))
		// let wp3.5 autohook take care of init
		qtrans_hook_on_tinyMCE('qtrans_textarea_content', false);
	}
}

qtrans_switch_postbox=function(parent, target, lang, focus) {
//	if(typeof(focus)==='undefined') focus = true;
	for (var n in arrLngs){
		jQuery('#'+target).val(qtrans_integrate(n, jQuery('#qtrans_textarea_'+target+'_'+n).val(), jQuery('#'+target).val()));
		jQuery('#'+parent+' .mqtranslate_lang_div').removeClass('switch-html');
		jQuery('#'+parent+' .mqtranslate_lang_div').removeClass('switch-tmce');
		if(lang!=false) jQuery('#qtrans_textarea_'+target+'_'+n).hide();
	}
	if(lang!=false) {
		jQuery('#qtrans_switcher_'+parent+'_'+lang).addClass('switch-tmce');
		jQuery('#qtrans_switcher_'+parent+'_'+lang).addClass('switch-html');
		jQuery('#qtrans_textarea_'+target+'_'+lang).show();
		if(focus) jQuery('#qtrans_textarea_'+target+'_'+lang).focus();
	}
}

function qtrans_createExcerptEditor(parent, language, id) {
	lang=arrLngs[language];
	parent='postexcerpt';
	jQuery('#'+parent+' .handlediv').after('<div class="mqtranslate_lang_div" id="'+id+'">'+
		'<img alt="'+language+'" title="'+lang.name+'" src="'+flagsLoc+lang.flag+'"/></div>'
	);
	jQuery('#'+id).click(function() {qtrans_switch_postbox(parent,'excerpt',language);});
	qtAreaId='qtrans_textarea_excerpt_'+language;
	jQuery('#excerpt').after('<textarea name="'+qtAreaId+'" id="'+qtAreaId+'"></textarea>');
	qtArea=jQuery('#'+qtAreaId);
	qtArea.attr('cols', jQuery('#excerpt').attr('cols'));
	qtArea.attr('rows', jQuery('#excerpt').attr('rows'));
	qtArea.attr('tabindex', jQuery('#excerpt').attr('tabindex'));
	qtArea.blur(function() {qtrans_switch_postbox(parent,'excerpt',false);});
	qtArea.val(qtrans_use(language,jQuery('#excerpt').val()));
}
