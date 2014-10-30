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

// deleted qtrans_createTextArea and qtrans_createTitlebarButton, joined both functions in qtrans_createExcerptEditor
*/

/* Modifications Hacks to get Wordpress work the way it should */

// modifys term form to support multilingual content
function qtrans_modifyTermForm($id, $name, $term) {
	global $q_config;
	echo "<script type=\"text/javascript\">\n// <![CDATA[\r\n";
	// ' workaround
	echo qtrans_JS_array_langs();
	$termname =(is_object($term)&&isset($term->name)) ? $term->name : "";

	// create input fields for each language
	foreach($q_config['enabled_languages'] as $language) {
		if(isset($_GET['action']) && $_GET['action']=='edit') {
			echo qtrans_insertTermInput2($id, $name, $termname, $language);
		} else {
			echo qtrans_insertTermInput($id, $name, $termname, $language);
		}
	}
	// hide real category text
	echo "if (ins != null) ins.style.display='none';\n";
	echo "// ]]>\n</script>\n";
}

function qtrans_modifyTermFormFor($term) {
	qtrans_modifyTermForm('name', __('Name'), $term);
	qtrans_modifyTermForm('tag-name', __('Name'), $term);
}

function qtrans_TinyMCE_init() {
	if (user_can_richedit())
	{
		global $q_config;
		echo "<script type=\"text/javascript\">\n// <![CDATA[\n";
		echo $q_config['js']['qtrans_updateTinyMCE'];
		echo "</script>\n";
	}
}

function isWordPressMajorVersionSupported() {
	$patterns = array('/(_|\-|\+)/', '/(\D+)/', '/\.{2,}/');
	$replacements = array('.', '.$1', '.');
	$wp = preg_replace($patterns, $replacements, $GLOBALS['wp_version']);
	$wp = array_slice(explode('.', $wp), 0, 2);
	$min = explode('.', QT_MIN_SUPPORTED_WP_MINOR_VERSION);
	$max = explode('.', QT_MAX_SUPPORTED_WP_MAJOR_VERSION);
	
	// Compare to minimum
	for ($i = 0; $i < 2; $i++)
	{
		if ($wp[$i] < $min[$i])
			return false;
		else if ($wp[$i] > $min[$i])
			break;
	}
	
	// Compare to maximum
	for ($i = 0; $i < 2; $i++)
	{
		if ($wp[$i] < $max[$i])
			return true;
		else if ($wp[$i] > $max[$i])
			return false;
	}
	
	return true;
}

// Modifys TinyMCE to edit multilingual content
function qtrans_modifyRichEditor($old_content) {
	global $q_config;
	$init_editor = true;
	if (!isWordPressMajorVersionSupported() 
			&& !(isset($_REQUEST['mqtranslateincompatiblemessage']) && $_REQUEST['mqtranslateincompatiblemessage']=="shown"))
	{
		if (!defined('QT_DISPLAYED_INCOMPATIBLE_MESSAGE'))
		{
			echo '<div class="error" id="qtrans_imsg"><p>'.__('The mqTranslate Editor has disabled itself because it hasn\'t been tested with your Wordpress version yet. This is done to prevent Wordpress from malfunctioning. To remove this message permanently, please update mqTranslate to the <a href="http://wordpress.org/plugins/mqtranslate/" target="_blank">corresponding version</a>.', 'mqtranslate').'</p></div>';
			define('QT_DISPLAYED_INCOMPATIBLE_MESSAGE', true);
		}
		$init_editor = false;
	}
	// save callback hook
	
	preg_match("/<textarea[^>]*id=\"([^\"]+)\"/",$old_content,$matches);
	$id = $matches[1];
	preg_match("/cols=\"([^\"]+)\"/",$old_content,$matches);
	$cols = $matches[1];
	// don't do anything if not editing the content
	if($id!="content") return $old_content;
	
	// don't do anything to the editor if it's not rich
	if (!user_can_richedit()) {
		//echo '<p class="updated">'.__('The mqTranslate Editor could not be loaded because WYSIWYG/TinyMCE is not activated in your profile.').'</p>';
		return $old_content;
	}
	
	$content = "";
	$content_append = "";
	
	// create editing field for selected languages
	$cookie = (int) get_user_setting( 'ed_size' );
	$str_height = ($cookie) ? "height: {$cookie}px; " : '';
	$qt_textarea = '<textarea class="wp-editor-area" id="qtrans_textarea_'.$id.'" name="qtrans_textarea_'.$id.'" tabindex="2" cols="'.$cols.'" style="'.$str_height.'display:none" onblur="qtrans_save(this.value);"></textarea>';
	$old_content = preg_replace('#(<textarea[^>]*>.*</textarea>)#', '$1'.$qt_textarea, $old_content);
	
	// do some crazy js to alter the admin view
	$content .="<script type=\"text/javascript\">\n// <![CDATA[\n";
	// include needed js functions
	$content .= $q_config['js']['qtrans_integrate_title'];
	$content .= $q_config['js']['qtrans_get_active_language'];
	$content .= $q_config['js']['qtrans_hook_on_tinyMCE'];
	
	$content .="function qtrans_editorInit1() {\n";
	$content .= $q_config['js']['qtrans_switch'];

	$cu = wp_get_current_user();

	// insert language, visual and html buttons
	$el = qtrans_getSortedLanguages();
	foreach($el as $language) {
		if ($cu->has_cap('edit_users') || mqtrans_currentUserCanEdit($language) || mqtrans_currentUserCanView($language))
			$content .= qtrans_insertTitleInput($language);
	}
	$el = qtrans_getSortedLanguages(true);
	foreach($el as $language) {
		if ($cu->has_cap('edit_users') || mqtrans_currentUserCanEdit($language) || mqtrans_currentUserCanView($language))
			$content .= qtrans_createEditorToolbarButton($language, $id);
	}
	
	$content = apply_filters('mqtranslate_toolbar', $content);
	
	// hide old title bar
	$content .= "qt_getEl('titlediv').style.display='none';\n";
	
	$content .="}\n";
	$content .="// ]]>\n</script>\n";
	
	$content_append .="<script type=\"text/javascript\">\n// <![CDATA[\n";
	$content_append .="function qtrans_editorInit2() {\n";
	
	// show default language tab
	$content_append .="qt_getEl('qtrans_select_".$q_config['default_language']."').className='wp-switch-editor switch-tmce switch-html';\n";
	// show default language
	$content_append .="var text = qt_getEl('".$id."').value;\n";
	// when TinyMCE is displayed as the default editor, remove the automatically added paragraphs before initializing mqTranslate
	$content_append .="if(getUserSetting( 'editor' ) == 'tinymce') { var text = switchEditors.pre_wpautop(text); }";
	$content_append .="qtrans_assign('qtrans_textarea_".$id."',qtrans_use('".$q_config['default_language']."',text));\n";
	
	$content_append .="}\n";

	$content_append .="function qtrans_editorInit3() {\n";
	// make tinyMCE and mediauploader get the correct data
	$content_append .=$q_config['js']['qtrans_tinyMCEOverload'];
	$content_append .=$q_config['js']['qtrans_wpActiveEditorOverload'];
	$content_append .="}\n";
	if($init_editor) {
		$content_append .=$q_config['js']['qtrans_wpOnload'];
	} else {
		$content_append .="var qtmsg = qt_getEl('qtrans_imsg');\n";
		$content_append .="var et = qt_getEl('wp-".$id."-editor-tools');\n";
		$content_append .="et.parentNode.insertBefore(qtmsg, et);\n";
	}
	$content_append = apply_filters('mqtranslate_modify_editor_js', $content_append);
	$content_append .="// ]]>\n</script>\n";
	
	return $content.$old_content.$content_append;
}

function qtrans_modifyExcerpt() {
	global $q_config;
	echo "<script type=\"text/javascript\">\n// <![CDATA[
	modify_excerpt();
// ]]>\n</script>\n";
}

function qtrans_insertTermInput($id,$name,$term,$language){
	global $q_config;
	$html ="
		var il = document.getElementsByTagName('input');
		var d =  document.createElement('div');
		var l = document.createTextNode('".$name." (".$q_config['language_name'][$language].")');
		var ll = document.createElement('label');
		var i = document.createElement('input');
		var ins = null;
		for(var j = 0; j < il.length; j++) {
			if(il[j].id=='".$id."') {
				ins = il[j];
				break;
			}
		}
		i.type = 'text';
		i.id = i.name = ll.htmlFor ='qtrans_term_".$language."';
	";
	if(isset($q_config['term_name'][$term][$language])) {
	$html .="
		i.value = '".addslashes(htmlspecialchars_decode($q_config['term_name'][$term][$language], ENT_NOQUOTES))."';
		";
	} else {
	$html .="
		if (ins != null)
			i.value = ins.value;
		";
	}
	if($language == $q_config['default_language']) {
		$html .="
			i.onchange = function() { 
				var il = document.getElementsByTagName('input');
				var ins = null;
				for(var j = 0; j < il.length; j++) {
					if(il[j].id=='$id') {
						ins = il[j];
						break;
					}
				}
				if (ins != null)
					ins.value = qt_getEl('qtrans_term_$language').value;
			};
			";
	}
	$html .="
		if (ins != null)
			ins = ins.parentNode;
		d.className = 'form-field form-required';
		ll.appendChild(l);
		d.appendChild(ll);
		d.appendChild(i);
		if (ins != null)
			ins.parentNode.insertBefore(d,ins);
		";
	return $html;
}

function qtrans_insertTermInput2($id,$name,$term,$language){
	global $q_config;
	$lang_name=$q_config['language_name'][$language];
	$html ="
/* {$lang_name} */
var ll = qt_addElement('label','','".$name." (".$lang_name.")');
var i = document.createElement('input');
i.type = 'text';
i.id = i.name = ll.htmlFor ='qtrans_term_".$language."';
var th = qt_addElement('th','','',[ll]);
th.scope = 'row';
th.vAlign = 'top';
var td = qt_addElement('td','','',[i]);
var tr = qt_addElement('tr','form-field form-required','',[th,td]);
var ins = qt_getEl('".$id."');
";
	if(isset($q_config['term_name'][$term][$language])) {
		$html .="
i.value = '".addslashes(htmlspecialchars_decode($q_config['term_name'][$term][$language], ENT_QUOTES))."';
";
	} else {
		$html .="
i.value = ins.value;
";
	}
	if($language == $q_config['default_language']) {
		$html .="
i.onchange = function() { 
	var il = document.getElementsByTagName('input');
	var ins = null;
	for(var j = 0; j < il.length; j++) {
		if(il[j].id=='".$id."') {
			ins = il[j];
			break;
		}
	}
	ins.value = qt_getEl('qtrans_term_".$language."').value;
};
";
	}
	$html .="
ins = ins.parentNode.parentNode;
ins.parentNode.insertBefore(tr,ins);
		";
	return $html;
}

function qtrans_insertTitleInput($language){
	global $q_config;

	$cu = wp_get_current_user();
	$editable = ($cu->has_cap('edit_users') || mqtrans_currentUserCanEdit($language));

	$title = __("Title", 'mqtranslate')." (".$q_config['language_name'][$language].")";
	if (!$editable)
		$title .= ' - ' . __('Read only', 'mqtranslate');

	$html ="
	
		var td = qt_getEl('titlediv');
		var qtd = qt_addElement('div','postarea',null,null);
		var h = qt_addElement('h3','qtrans_title','{$title}');
		var tw = document.createElement('div');
		var ti = document.createElement('input');
		var slug = qt_getEl('edit-slug-box');
		
		ti.type = 'text';
		ti.id = 'qtrans_title_$language';
		ti.tabIndex = '1';
		ti.value = qtrans_use('$language', qt_getEl('title').value);
		ti.onchange = qtrans_integrate_title;
		ti.className = 'qtrans_title_input';
		ti.disabled = " . (($editable) ? 'false' : 'true') . "
		tw.className = 'qtrans_title_wrap';

		tw.appendChild(ti);
		qtd.appendChild(h);
		qtd.appendChild(tw);";
	if($q_config['default_language'] == $language)
		$html.="if(slug) qtd.appendChild(slug);";
	$html.="
		td.parentNode.insertBefore(qtd,td);
		
		";
	return $html;
}

function qtrans_createEditorToolbarButton($language, $id, $js_function = 'switchEditors.go', $label = ''){
	global $q_config;
	
	$cu = wp_get_current_user();
	$editable = ($cu->has_cap('edit_users') || mqtrans_currentUserCanEdit($language));
	
	$title = (($label==='')?$q_config['language_name'][$language]:$label);
	if (!$editable)
		$title .= ' - ' . __('Read only', 'mqtranslate');
	
	$html = "
		var bc = qt_getEl('wp-".$id."-editor-tools');
		var mb = qt_getEl('wp-".$id."-media-buttons');
		var ls = document.createElement('a');
		var l = document.createTextNode('{$title}');
		ls.id = 'qtrans_select_$language';
		ls.className = 'wp-switch-editor';
		ls.onclick = function() { ".$js_function."('$id','$language'); };
		ls.appendChild(l);
		bc.insertBefore(ls,mb);
		";
	return $html;
}
?>
