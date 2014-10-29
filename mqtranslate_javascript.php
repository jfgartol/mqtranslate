<?php // encoding: utf-8

/*  Copyright 2008  Qian Qin  (email : mail@qianqin.de)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// mqTranslate Javascript functions
// paco garcia
// creates the array of enabled languages in javascript
function qtrans_JS_array_langs() {
	global $q_config;
	$defLang=$q_config['default_language'];
	$jsLangs="";
	$jsLngs="";
	foreach ($q_config['enabled_languages'] as $lang) $jsLangs.=($jsLangs != "" ? "," : "")."'$lang'";
	foreach ($q_config['enabled_languages'] as $lang){
		$lang_name="";
		$flag="";
		if (isset($q_config['language_name'][$lang])) $lang_name=$q_config['language_name'][$lang];
		if (isset($q_config['flag'][$lang])) $flag=$q_config['flag'][$lang];
		$jsLngs.=($jsLngs != "" ? "," : "")."'$lang':{'name':'$lang_name','flag':'$flag'}";
	}
	// arrLngs={"es":{"name":"Español"},"en":{"name":"English"}};
	return "

arrLangs=[$jsLangs];
arrLngs={".$jsLngs."};
defLang='$defLang';
flagsLoc='".WP_CONTENT_URL.'/'.$q_config['flag_location']."';
";
}

function qtrans_initJS() {
	global $q_config;
	
	$cu = wp_get_current_user();

	$q_config['js']['qtrans_use'] = "";
	$q_config['js']['qtrans_save'] = "";

	$q_config['js']['qtrans_integrate_title'] = "
		qtrans_integrate_title = function() {
			var t = qt_getEl('title');";
	foreach($q_config['enabled_languages'] as $language) {
		if ($cu->has_cap('edit_users') || mqtrans_currentUserCanEdit($language) || mqtrans_currentUserCanView($language)){
			$q_config['js']['qtrans_integrate_title'].= "
			t.value = qtrans_integrate('".$language."',qt_getEl('qtrans_title_".$language."').value,t.value);";
		}
	}
	$q_config['js']['qtrans_integrate_title'].= "
		}
		";

	$q_config['js']['qtrans_tinyMCEOverload'] = "
		tinyMCE.get2 = tinyMCE.get;
		tinyMCE.get = function(id) {
			if(id=='content'&&this.get2('qtrans_textarea_'+id)!=undefined)
				return this.get2('qtrans_textarea_'+id);
			return this.get2(id);
		}
		
		";
	
	$q_config['js']['qtrans_wpActiveEditorOverload'] = "
		jQuery('.wp-editor-wrap').unbind('mousedown');
		jQuery('.wp-editor-wrap').mousedown(function(e){
			wpActiveEditor = 'qtrans_textarea_'+this.id.slice(3, -5);
		});
		";
	
	$q_config['js']['qtrans_updateTinyMCE'] = "
		(function() {
			for (var i in tinyMCEPreInit.qtInit) {
				var tmp = tinyMCEPreInit.qtInit[i];
				tmp.id = 'qtrans_textarea_'+tmp.id;
				tinyMCEPreInit.qtInit[tmp.id] = tmp;
				delete tinyMCEPreInit.qtInit[i];
				jQuery('#ed_toolbar').hide();
			}
			
			var hook = tinyMCEPreInit.mceInit['content'];
			if (hook){
				// Removing WPFullscreen plugin and button
				var p = hook.plugins.split(',').filter(function(element) { return (element != 'wpfullscreen'); });
				hook.plugins = p.join(',');
				p = hook.toolbar1.split(',').filter(function(element) { return (element != 'wp_fullscreen'); });
				hook.toolbar1 = p.join(',');
				
				hook.elements='hook-to-nothing';
				hook.selector = '#qtrans_textarea_content';
				delete tinyMCEPreInit.mceInit['content'];
				tinyMCEPreInit.mceInit['qtrans_textarea_content'] = hook;
				
				var wrap = jQuery('#wp-content-wrap');
				var html = '<div id=\"wp-qtrans_textarea_content-wrap\" class=\"' + wrap.prop('className') + '\"></div>';
				jQuery('body').append(html);
			}
		}());
	";
	
	$q_config['js']['qtrans_wpOnload'] = "
		jQuery(document).ready(function() {qtrans_editorInit();});
		";
		
	$q_config['js']['qtrans_editorInit'] = "
		";
	
	$q_config['js']['qtrans_hook_on_tinyMCE'] = "
		qtrans_hook_on_tinyMCE = function(id, initEditor) {
			tinyMCEPreInit.mceInit[id].setup = function(ed) {
				ed.on('SaveContent', function(e) {
					if (!ed.isHidden()) {
						e.content = e.content.replace( /<p>(<br ?\/?>|\u00a0|\uFEFF)?<\/p>/g, '<p>&nbsp;</p>' );
						if ( ed.getParam( 'wpautop', true ) )
							e.content = switchEditors.pre_wpautop(e.content);
						qtrans_save(e.content);
					}
				});
				ed.on('init', function(e) {
					var content_ifr = qt_getEl('content_ifr');
					if (!content_ifr) {
						content_ifr = jQuery('<div id=\"content_ifr\" style=\"display: none\"></div>').appendTo('body');
						setInterval(function() {
							content_ifr.css('height', jQuery('#qtrans_textarea_content_ifr').css('height'));
						}, 100);
					}
				});
			};
			
			if (initEditor)
				tinymce.init(tinyMCEPreInit.mceInit[id]);
		}
		";
	
	$q_config['js']['qtrans_get_active_language'] = "
	
		qtrans_get_active_language = function() {
	";
	foreach($q_config['enabled_languages'] as $language) {
		if ($cu->has_cap('edit_users') || mqtrans_currentUserCanEdit($language) || mqtrans_currentUserCanView($language)) {
			$q_config['js']['qtrans_get_active_language'].= "
					if(qt_getEl('qtrans_select_$language').className=='wp-switch-editor switch-tmce switch-html') return '$language';
				";
		}
	}
	$q_config['js']['qtrans_get_active_language'].= "
		}
		";

	$q_config['js']['qtrans_switch'] = "
		switchEditors.go = function(id, lang) {
			id = id || 'qtrans_textarea_content';
			lang = lang || 'toggle';
			
			if ( 'toggle' == lang ) {
				lang = ( ed && !ed.isHidden() ) ? 'html' : 'tmce';
			} else if( 'tinymce' == lang ) 
				lang = 'tmce';
		
			var inst = tinyMCE.get('qtrans_textarea_' + id);
			var vta = qt_getEl('qtrans_textarea_' + id);
			var ta = qt_getEl(id);
			var dom = tinymce.DOM;
			var wrap_id = 'wp-'+id+'-wrap';
			var wrap_id2 = 'wp-qtrans_textarea_content-wrap';
			
			// update merged content
			if(inst && ! inst.isHidden()) {
				tinyMCE.triggerSave();
			} else {
				qtrans_save(vta.value);
			}
			
			// check if language is already active
			if(lang!='tmce' && lang!='html' && qt_getEl('qtrans_select_'+lang).className=='wp-switch-editor switch-tmce switch-html') {
				return;
			}
			
			if(lang!='tmce' && lang!='html') {
				qt_getEl('qtrans_select_'+qtrans_get_active_language()).className='wp-switch-editor';
				qt_getEl('qtrans_select_'+lang).className='wp-switch-editor switch-tmce switch-html';
			}
	
			if(lang=='html') {
				if ( inst && inst.isHidden() )
					return false;
				if ( inst ) {
					vta.style.height = inst.getContentAreaContainer().offsetHeight + 20 + 'px';
					inst.hide();
				}
				
				dom.removeClass(wrap_id, 'tmce-active');
				dom.addClass(wrap_id, 'html-active');
				dom.removeClass(wrap_id2, 'tmce-active');
				dom.addClass(wrap_id2, 'html-active');
				setUserSetting( 'editor', 'html' );
			} else if(lang=='tmce') {
				if(inst && ! inst.isHidden())
					return false;
				if ( typeof(QTags) != 'undefined' )
					QTags.closeAllTags('qtrans_textarea_' + id);
				if ( tinyMCEPreInit.mceInit['qtrans_textarea_'+id] && tinyMCEPreInit.mceInit['qtrans_textarea_'+id].wpautop )
					vta.value = this.wpautop(qtrans_use(qtrans_get_active_language(),ta.value));
				if (inst) {
					inst.show();
				} else {
					qtrans_hook_on_tinyMCE('qtrans_textarea_'+id, true);
				}
				
				dom.removeClass(wrap_id, 'html-active');
				dom.addClass(wrap_id, 'tmce-active');
				dom.removeClass(wrap_id2, 'html-active');
				dom.addClass(wrap_id2, 'tmce-active');
				setUserSetting('editor', 'tinymce');
			} else {
				// switch content
				qtrans_assign('qtrans_textarea_'+id,qtrans_use(lang,ta.value));
			}
		}
		";
}
// 314
?>
