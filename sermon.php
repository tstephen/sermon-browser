<?php 
/*
Plugin Name: Sermon Browser
Plugin URI: http://www.4-14.org.uk/sermon-browser
Description: Add sermons to your Wordpress blog. Main coding by <a href="http://codeandmore.com/">Tien Do Xuan</a>. Design and additional coding
Author: Mark Barnes
Version: 0.31
Author URI: http://www.4-14.org.uk/

Copyright (c) 2008 Mark Barnes

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

// Localization and set-up
$sermon_domain = 'sermon-browser';
load_plugin_textdomain($sermon_domain, 'wp-content/plugins/sermon-browser');
require_once('dictionary.php'); // Template functions
include_once('filetypes.php'); // User-defined icons
include('frontend.php'); // Everything related to displaying sermons

// Assign global variables
global $url, $wordpressRealPath, $defaultSermonPath, $display_mode, $sermons_per_page, $display_url;
$url = get_bloginfo('wpurl');
$wordpressRealPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(__FILE__)))));
$defaultSermonPath = '/'.get_option('upload_path').'/sermons/';
$display_method = get_option('sb_display_method');
$defaultSermonURL = get_bloginfo('wpurl').'/'.get_option('upload_path').'/sermons/';
$books = array('Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalm', 'Proverbs', 'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi', 'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John', 'Jude', 'Revelation');
$sermons_per_page = get_option('sb_sermons_per_page');

if ($_POST['sermon'] == 1) sb_return_ajax_data(); // Return AJAX data if that is all that is required

// Add admin menu items and check to see whether install/upgrade is necessary
add_action('admin_menu', 'sb_add_pages');
sb_sermon_install();

// Add Sermons menu and sub-menus in admin
function sb_add_pages() {
	global $sermon_domain;
	add_menu_page(__('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 'edit_posts', __FILE__, 'sb_manage_sermons');
	add_submenu_page(__FILE__, __('Sermons', $sermon_domain), __('Sermons', $sermon_domain), 'edit_posts', __FILE__, 'sb_manage_sermons');
	add_submenu_page(__FILE__, __('New Sermon', $sermon_domain), __('New Sermon', $sermon_domain), 'publish_posts', 'sermon-browser/new_sermon.php', 'sb_new_sermon');
	add_submenu_page(__FILE__, __('Preachers', $sermon_domain), __('Preachers', $sermon_domain), 'manage_categories', 'sermon-browser/preachers.php', 'sb_manage_preachers');
	add_submenu_page(__FILE__, __('Series &amp; Services', $sermon_domain), __('Series &amp; Services', $sermon_domain), 'manage_categories', 'sermon-browser/manage.php', 'sb_manage_everything');
	add_submenu_page(__FILE__, __('Uploads', $sermon_domain), __('Uploads', $sermon_domain), 'upload_files', 'sermon-browser/uploads.php', 'sb_uploads');
	add_submenu_page(__FILE__, __('Options', $sermon_domain), __('Options', $sermon_domain), 'manage_options', 'sermon-browser/options.php', 'sb_options');
	add_submenu_page(__FILE__, __('Templates', $sermon_domain), __('Templates', $sermon_domain), 'manage_options', 'sermon-browser/templates.php', 'sb_templates');
	add_submenu_page(__FILE__, __('Help', $sermon_domain), __('Help', $sermon_domain), 'read', 'sermon-browser/help.php', 'sb_help');
}

// Installer
function sb_sermon_install () {
	global $books;
	//Attempt to set php.ini directives
	if (sb_return_kbytes(ini_get('upload_max_filesize'))<15360) ini_set('upload_max_filesize', '15M');
	if (sb_return_kbytes(ini_get('post_max_size'))<15360) ini_set('post_max_size', '15M');
	if (sb_return_kbytes(ini_get('memory_limit'))<49152) ini_set('memory_limit', '48M');
	if (intval(ini_get('max_input_time'))<600) ini_set('max_input_time','600');
	if (intval(ini_get('max_execution_time'))<600) ini_set('max_execution_time', '600');
	if (ini_get('file_uploads')<>'1') ini_set('file_uploads', '1');
	//Hack for people previously using 0.30
	for ($i=1; $i < count($books)+1; $i++) { 
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books VALUES ({$i}, '$books[$i]') ON DUPLICATE KEY UPDATE name='$books[$i]'");
	}
	// End hack
	// Only proceed with install if necessary
	if(get_option('sb_sermon_db_version') =='1.4') return;
	global $wpdb, $mdict, $sdict, $books;	
	global $wordpressRealPath, $defaultSermonPath, $defaultSermonURL, $defaultMultiForm, $defaultSingleForm, $defaultStyle;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$sermonUploadDir = $defaultSermonPath;
	if (!is_dir($wordpressRealPath.$sermonUploadDir)) {
		if (@mkdir($wordpressRealPath.$sermonUploadDir)) {
			@chmod($wordpressRealPath.$sermonUploadDir, 0777);          
		}
	}
	if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
		@chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
	}
	//Upgrade database from earlier versions
	switch (get_option('sb_sermon_db_version')) {
		case '1.0': // Also moves files from old default folder to new default folder
			$oldSermonPath = "/wp-content/plugins/sermonbrowser/files/";
			$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' ORDER BY name asc");	
			foreach ((array)$files as $file) {
				@chmod($wordpressRealPath.$oldSermonPath.$file->name, 0777);
				@rename($wordpressRealPath.$oldSermonPath.$file->name, $wordpressRealPath.$defaultSermonPath.$file->name);
			}
			$table_name = $wpdb->prefix . "sb_preachers";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name) {            
				  $wpdb->query("ALTER TABLE " . $table_name . " ADD `description` TEXT NOT NULL, ADD `image` VARCHAR( 255 ) NOT NULL ;");
			}
			update_option('sb_sermon_db_version', '1.1');		
		case '1.1':
   	        add_option('sb_sermon_style', base64_encode($defaultStyle));
			if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
				@chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
			}
   	        update_option('sb_sermon_db_version', '1.2');	
		case '1.2':
			$table_name = $wpdb->prefix . "sb_stuff";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name)
				  $wpdb->query("ALTER TABLE ".$table_name." ADD count INT(10) NOT NULL");
			$table_name = $wpdb->prefix . "sb_books_sermons";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name)
				  $wpdb->query("ALTER TABLE ".$table_name." ADD INDEX (sermon_id)");
			$table_name = $wpdb->prefix . "sb_sermons_tags";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name)
				  $wpdb->query("ALTER TABLE ".$table_name." ADD INDEX (sermon_id)");
			update_option('sb_sermon_db_version', '1.3');
		case '1.3':
			$table_name = $wpdb->prefix . "sb_series";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name)
				  $wpdb->query("ALTER TABLE ".$table_name." ADD page_id INT(10) NOT NULL");
			$table_name = $wpdb->prefix . "sb_sermons";
			if($wpdb->get_var("show tables like '$table_name'") == $table_name)
				  $wpdb->query("ALTER TABLE ".$table_name." ADD page_id INT(10) NOT NULL");
			add_option('sb_display_method', 'dynamic');
			add_option('sb_sermons_per_page', '15');
			if ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_books") == 0) {
				for ($i=0; $i < count($books); $i++) { 
					$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books VALUES (null, '$books[$i]')"); // Fix missing data bug from earlier versions
				}
			}
			add_option('sb_sermon_multi_output', base64_encode(strtr(stripslashes(get_option('sb_sermon_multi_form')), $mdict)));
			add_option('sb_sermon_single_output', base64_encode(strtr(stripslashes(get_option('sb_sermon_single_form')), $sdict)));
			add_option('sb_sermon_style_output', base64_encode(stripslashes(get_option('sb_sermon_style'))));
			add_option('sb_sermon_style_date_modified', strtotime('now'));
			update_option('sb_sermon_db_version', '1.4');
			return;
			break;
		default:
			update_option('sb_sermon_db_version', '1.4');
			break;
	}   	

	//Create default tables
   $table_name = $wpdb->prefix . "sb_preachers";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {            
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 30 ) NOT NULL ,
		`description` TEXT NOT NULL ,
		`image` VARCHAR( 255 ) NOT NULL,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name, description, image) VALUES ( 'C H Spurgeon', '', '' );";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name, description, image) VALUES ( 'Martyn Lloyd-Jones', '', '' );";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_series";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 255 ) NOT NULL ,
		`page_id` INT(10) NOT NULL,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name) VALUES ( 'Exposition of the Psalms' );";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name) VALUES ( 'Exposition of Romans' );";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_services";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`name` VARCHAR( 255 ) NOT NULL ,
		`time` VARCHAR( 5 ) NOT NULL , 
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
	  $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Sunday Morning', '10:30' );";
      dbDelta($sql);
      $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Sunday Evening', '18:00' );";
      dbDelta($sql);
      $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Midweek Meeting', '19:00' );";
      dbDelta($sql);
      $sql = "INSERT INTO " . $table_name . "(name, time) VALUES ( 'Special event', '20:00' );";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_sermons";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`title` VARCHAR( 255 ) NOT NULL ,
		`preacher_id` INT( 10 ) NOT NULL ,
		`date` DATE NOT NULL ,
		`service_id` INT( 10 ) NOT NULL ,
		`series_id` INT( 10 ) NOT NULL ,
		`start` TEXT NOT NULL ,
		`end` TEXT NOT NULL ,
		`description` TEXT ,
		`time` VARCHAR ( 5 ), 
		`override` TINYINT ( 1 ) ,	
		`page_id` INT(10) NOT NULL,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }

	$table_name = $wpdb->prefix . "sb_books_sermons";
   	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (		
		`id` INT(10) NOT NULL AUTO_INCREMENT,
		`book_name` VARCHAR( 30 ) NOT NULL ,		
		`chapter` INT(10) NOT NULL,
		`verse` INT(10) NOT NULL,
		`order` INT(10) NOT NULL,
		`type` VARCHAR ( 30 ), 
		`sermon_id` INT( 10 ) NOT NULL,
		INDEX (`sermon_id`),
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }

	$table_name = $wpdb->prefix . "sb_books";
   	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (		
		`id` INT(10) NOT NULL AUTO_INCREMENT,
		`name` VARCHAR( 30 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }
   
   $table_name = $wpdb->prefix . "sb_stuff";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
	  $sql = "CREATE TABLE " . $table_name . " (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
		`type` VARCHAR( 30 ) NOT NULL ,
		`name` TEXT NOT NULL ,
		`sermon_id` INT( 10 ) NOT NULL ,
		`count` INT( 10 ) NOT NULL ,
		PRIMARY KEY ( `id` )
		);";
      dbDelta($sql);
   }

	$table_name = $wpdb->prefix . "sb_tags";
	   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
		  $sql = "CREATE TABLE " . $table_name . " (
			`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
			`name` TEXT NOT NULL ,
			PRIMARY KEY ( `id` )
			);";
	      dbDelta($sql);
	   }
	
	$table_name = $wpdb->prefix . "sb_sermons_tags";
	   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {      
		  $sql = "CREATE TABLE " . $table_name . " (
			`id` INT( 10 ) NOT NULL AUTO_INCREMENT ,
			`sermon_id` INT( 10 ) NOT NULL ,
			`tag_id` INT( 10 ) NOT NULL ,
			INDEX (`sermon_id`),
			PRIMARY KEY ( `id` )
			);";
	      dbDelta($sql);
	   }
	$welcome_name = __('Delete', $sermon_domain);
	$welcome_text = __('Congratulations, you just completed the installation!', $sermon_domain);	
	add_option('sb_sermon_upload_dir', $sermonUploadDir);
	add_option('sb_sermon_upload_url', $defaultSermonURL);
	add_option('sb_podcast', get_bloginfo('url').sb_display_url().'?podcast');
	add_option('sb_display_method', 'dynamic');
	add_option('sb_sermons_per_page', '15');
	delete_option('sb_sermon_multi_form');
   	add_option('sb_sermon_multi_form', base64_encode(sb_default_multi_template()));
	delete_option('sb_sermon_single_form');
   	add_option('sb_sermon_single_form', base64_encode(sb_default_single_template()));
	delete_option('sb_sermon_style');
   	add_option('sb_sermon_style', base64_encode(sb_default_css()));
	add_option('sb_sermon_multi_output', base64_encode(strtr(stripslashes(get_option('sb_sermon_multi_form')), $mdict)));
	add_option('sb_sermon_single_output', base64_encode(strtr(stripslashes(get_option('sb_sermon_single_form')), $sdict)));
	add_option('sb_sermon_style_output', base64_encode(stripslashes(get_option('sb_sermon_style'))));
	for ($i=0; $i < count($books); $i++) { 
		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books VALUES (null, '$books[$i]');");
	}
	add_option('sb_sermon_db_version', '1.4');
}

// Tidy the textarea input
function sb_build_textarea($name, $html) {
	$out = '<textarea name="'.$name.'" cols="75" rows="15">';
	$out .= stripslashes(str_replace('\r\n', "\n", base64_decode($html))); 
	$out .= '</textarea>';
	echo $out;
}

// Display the options page and handle changes
function sb_options() {
	global $wpdb, $sermon_domain, $mdict, $sdict, $url, $display_method;
	global $wordpressRealPath, $defaultSermonPath, $defaultSermonURL;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_options'))
			wp_die(__("You do not have the correct permissions to edit the Sermon Browser options", $sermon_domain));
	//Reset options to default
	if ($_POST['resetdefault']) {
		$dir = $defaultSermonPath;
		update_option('sb_podcast', sb_display_url().'?podcast');
		update_option('sb_sermon_upload_dir', $defaultSermonPath);
		update_option('sb_sermon_upload_url', $defaultSermonURL);
		update_option('sb_display_method', 'dynamic');
		update_option('sb_sermons_per_page', '15');
	   	if (!is_dir($wordpressRealPath.$dir)) {
	      //Create that folder
	      if (@mkdir($wordpressRealPath.$dir)) {
	         //try CHMOD it to 777
	         @chmod($wordpressRealPath.$dir, 0777); 
	      }
	   	}
	    if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
	     @chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
		}	   	   	
	   	$checkSermonUpload = sb_checkSermonUploadable();
	   	switch ($checkSermonUpload) {
			case "unwriteable":
				echo '<div id="message" class="updated fade"><p><b>';
				_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain);
				echo '</b></div>';
				break;
			case "notexist":
				echo '<div id="message" class="updated fade"><p><b>';
				_e('Error: The upload folder you have specified does not exist.', $sermon_domain);
				echo '</b></div>';
				break;
			default: 
				echo '<div id="message" class="updated fade"><p><b>';
				_e('Default loaded successfully.', $sermon_domain);
				echo '</b></div>';
				break;
			}
		//sb_delete_all_pages();
	}
	// Rewrite posts/pages if display mode changes (reserved for version 0.40)
	// elseif ($_REQUEST['rewrite']=="true" && ($_REQUEST['method']="page" | $_REQUEST['method']="post")) sb_rewrite_all_pages ($_REQUEST['start']);
	// Save options
	elseif ($_POST['save']) {
		$dir = rtrim(str_replace("\\", "/", $_POST['dir']), "/")."/";
		update_option('sb_podcast', $_POST['podcast']);
		if (intval($_POST['perpage']) > 0) update_option('sb_sermons_per_page', $_POST['perpage']);
		update_option('sb_sermon_upload_dir', $dir);
		update_option('sb_sermon_upload_url', get_bloginfo('url').$dir);		
	   	if (!is_dir($wordpressRealPath.$dir)) {
	      if (@mkdir($wordpressRealPath.$dir)) {
	         @chmod($wordpressRealPath.$dir, 0777);
			}
	    }
		/* Reserved for version 0.40
		$new_display_method = $_REQUEST['displaymethod'];
		if ($display_method != $new_display_method) {
			sb_delete_all_pages();
			update_option('sb_display_method', $new_display_method);
			$display_method = $new_display_method;
			if ($display_method != "dynamic") {
				echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/options.php&rewrite=true&start=0&method={$new_display_method}&mode={$_REQUEST['mode']}';</script>";
			} else {
				echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/options.php';</script>";
			}
	   	}
		*/
		if(!is_dir($wordpressRealPath.$dir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
			@chmod($wordpressRealPath.$dir.'images', 0777);
		}	   	   	
	   	$checkSermonUpload = sb_checkSermonUploadable();
	   	switch ($checkSermonUpload) {
	   	case "unwriteable":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain);
			echo '</b></div>';
			break;
		case "notexist":
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Error: The upload folder you have specified does not exist.', $sermon_domain);
			echo '</b></div>';
			break;
		default: 
			echo '<div id="message" class="updated fade"><p><b>';
			_e('Options saved successfully.', $sermon_domain);
			echo '</b></div>';
			break;
	   }
	}
	//Uninstall plugin
	elseif ($_POST['uninstall']) {
		if ($_POST['wipe'] == 1) {
			$dir = $wordpressRealPath.get_option('sb_sermon_upload_dir');
			if ($dh = @opendir($dir)) {
				while (false !== ($file = readdir($dh))) {
					if ($file != "." && $file != "..") {	    		
						@unlink($dir.($file));
					}	
				}
				closedir($dh);
			}
		}
		$table_name = $wpdb->prefix."sb_preachers";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_series";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_services";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_sermons";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		$table_name = $wpdb->prefix."sb_stuff";
		if ($wpdb->get_var("show tables like '$table_name'") == $table_name) $wpdb->query("DROP TABLE $table_name");
		
		delete_option('sb_podcast');
		delete_option('sb_sermon_upload_dir');
		delete_option('sb_sermon_upload_url');
		delete_option('sb_sermon_single_form');
		delete_option('sb_sermon_multi_form');
		delete_option('sb_sermon_db_version');
		delete_option('sb_sermon_style');
		delete_option('sb_sermon_multi_output');
		delete_option('sb_sermon_single_output');
		delete_option('sb_sermon_style_output');
		delete_option('sb_sermon_style_date_modified');
		echo '<div id="message" class="updated fade"><p><b>'.__('Uninstall completed. Please deactivate the plugin.', $sermon_domain).'</b></div>';
	}
	//Display error messsages when problems in php.ini
	function sb_display_error ($message) {
		global $sermon_domain;
		return	'<tr><td align="right" style="color:#AA0000; font-weight:bold">'.__('Error', $sermon_domain).':</td>'.
				'<td style="color: #AA0000">'.$message.'</td></tr>';
	}
	//Display warning messsages when problems in php.ini
	function sb_display_warning ($message) {
		global $sermon_domain;
		return	'<tr><td align="right" style="color:#FF8C00; font-weight:bold">'.__('Warning', $sermon_domain).':</td>'.
				'<td style="color: #FF8C00">'.$message.'</td></tr>';
	}
	sb_check_sermon_tag();
	// HTML for options page
?>
	<form method="post">
	<div class="wrap">
		<h2><?php _e('Options', $sermon_domain) ?></h2>
		<br style="clear:both"/>
		<table border="0" class="widefat">
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Upload directory', $sermon_domain) ?>: </td>
				<td><input type="text" name="dir" value="<?php echo get_option('sb_sermon_upload_dir') ?>" style="width:100%" /></td>
			</tr>
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Public podcast feed', $sermon_domain) ?>: </td>
				<td><input type="text" name="podcast" value="<?php echo get_option('sb_podcast') ?>" style="width:100%" /></td>
			</tr>
			<tr>
				<td align="right"><?php _e('Private podcast feed', $sermon_domain) ?>: </td>
				<td><?php echo sb_display_url().'?podcast' ?></td>
			</tr>
			<?php /* Reserved for version 0.40 
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Display method', $sermon_domain) ?>: </td>
				<td><select name="displaymethod" id="displaymethod">
					<option value="post" <?php echo $display_method == "post" ? 'selected="selected"' : '' ?>><?php _e("Post for each sermon", $sermon_domain) ?></option>
					<option value="page" <?php echo $display_method == "page" ? 'selected="selected"' : '' ?>><?php _e("Page for each sermon", $sermon_domain) ?></option>
					<option value="dynamic" <?php echo $display_method == "dynamic" ? 'selected="selected"' : '' ?>><?php _e("Single dynamic page", $sermon_domain) ?></option>
					</select>
				</td>
			</tr>
			*/ ?>
			<tr>
				<td align="right" style="vertical-align:middle"><?php _e('Sermons per page', $sermon_domain) ?>: </td>
				<td><input type="text" name="perpage" value="<?php echo get_option('sb_sermons_per_page') ?>" /></td>
			</tr>
			<?php
				$checkSermonUpload = sb_checkSermonUploadable();
				if ($checkSermonUpload=="unwriteable") 
					echo sb_display_error (__('The upload folder is not writeable. You need to CHMOD the folder to 666 or 777.', $sermon_domain));
				elseif ($checkSermonUpload=="notexist") 
					sb_display_error (__('The upload folder you have specified does not exist.', $sermon_domain));
				$allow_uploads = ini_get('file_uploads');
				$max_filesize = sb_return_kbytes(ini_get('upload_max_filesize'));
				$max_post = sb_return_kbytes(ini_get('post_max_size'));
				$max_execution = ini_get('max_execution_time');
				$max_input = ini_get('max_input_time');
				$max_memory = sb_return_kbytes(ini_get('memory_limit'));
				if ($allow_uploads == '0') echo sb_display_error(__('Your php.ini file does not allow uploads. Please change file_uploads in php.ini.', $sermon_domain));
				if ($max_filesize < 15360) echo sb_display_warning(__('The maximum file size you can upload is only ', $sermon_domain).$max_filesize.__('k. Please change upload_max_filesize to at least 15M in php.ini.', $sermon_domain));
				if ($max_post < 15360) echo sb_display_warning(__('The maximum file size you send through the browser is only ', $sermon_domain).$max_post.__('k. Please change post_max_size to at least 15M in php.ini.', $sermon_domain));
				if ($max_execution < 600) echo sb_display_warning(__('The maximum time allowed for any script to run is only ', $sermon_domain).$max_execution.__(' seconds. Please change max_execution_time to at least 600 in php.ini.', $sermon_domain));
				if ($max_input < 600 && $max_input != -1) echo sb_display_warning(__('The maximum time allowed for an upload script to run is only ', $sermon_domain).$max_input.__(' seconds. Please change max_input_time to at least 600 in php.ini.', $sermon_domain));
				if ($max_memory < 16384) echo sb_display_warning(__('The maximum amount of memory allowed is only ', $sermon_domain).$max_memory.__('k. Please change memory_limit to at least 16M in php.ini.', $sermon_domain));
			?>
		</table>		
		<p class="submit"><input type="submit" name="resetdefault" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
	</div>	
	<div class="wrap">
		<h2><?php _e('Uninstall', $sermon_domain) ?></h2>
		<br style="clear:both"/>
		<table border="0" class="widefat">			
			<tr>
				<td><input type="checkbox" name="wipe" value="1"> <?php _e('Remove all files', $sermon_domain) ?></td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="uninstall" value="<?php _e('Uninstall', $sermon_domain) ?>" onclick="return confirm('Are you sure?')" /></p> 
	</div>
	</form>
	<script>
		jQuery("form").submit(function() {
			var yes = confirm("Are you sure ?");
			if(!yes) return false;
		});
	</script>
<?php 
}

// Display the templates page and handle changes
function sb_templates () {
	global $wordpressRealPath, $mdict, $sdict, $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_options'))
			wp_die(__("You do not have the correct permissions to edit the Sermon Browser templates", $sermon_domain));
	//Save templates or reset to default
	if ($_POST['save'] || $_POST['resetdefault']) {
		$multi = $_POST['multi'];
		$single = $_POST['single'];
		$style = $_POST['style'];
	    if($_POST['resetdefault']){
		    $multi = sb_default_multi_template();
		    $single = sb_default_single_template();
		    $style = sb_default_css();
		}
		update_option('sb_sermon_multi_form', base64_encode($multi));
		update_option('sb_sermon_single_form', base64_encode($single));
		update_option('sb_sermon_style', base64_encode($style));
		update_option('sb_sermon_multi_output', base64_encode(strtr(stripslashes($multi), $mdict)));
		update_option('sb_sermon_single_output', base64_encode(strtr(stripslashes($single), $sdict)));
		update_option('sb_sermon_style_output', base64_encode(stripslashes($style)));
		update_option('sb_sermon_style_date_modified', strtotime('now'));
		echo '<div id="message" class="updated fade"><p><b>';
		_e('Templates saved successfully.', $sermon_domain);
		echo '</b></p></div>';		
	}
	sb_check_sermon_tag();
	// HTML for templates page
	?>
	<form method="post">
	<div class="wrap">
		<h2><?php _e('Templates', $sermon_domain) ?></h2>
		<table border="0" class="widefat">
			<tr>
				<td align="right"><?php _e('Multi-sermons form', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('multi', get_option('sb_sermon_multi_form')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Single sermon form', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('single', get_option('sb_sermon_single_form')) ?>
				</td>
			</tr>
			<tr>
				<td align="right"><?php _e('Style', $sermon_domain) ?>: </td>
				<td>
					<?php sb_build_textarea('style', get_option('sb_sermon_style')) ?>
				</td>
			</tr>			
		</table>				
		<p class="submit"><input type="submit" name="resetdefault" value="<?php _e('Reset to defaults', $sermon_domain) ?>"  />&nbsp;<input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
	</div>		
	</form>
	<script>
		jQuery("form").submit(function() {
			var yes = confirm("Are you sure ?");
			if(!yes) return false;
		});
	</script>
<?php 
}

// Display the preachers page and handle changes
function sb_manage_preachers() {
	global $wpdb, $sermon_domain;
	global $wordpressRealPath;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_categories'))
			wp_die(__("You do not have the correct permissions to manage the preachers' database", $sermon_domain));
	$url = get_bloginfo('wpurl');
	if ($_GET['saved']) {
		echo '<div id="message" class="updated fade"><p><b>'.__('Preacher saved to database.', $sermon_domain).'</b></div>';
	}
	//Save changes
	if ($_POST['save']) {
		$name = mysql_real_escape_string($_POST['name']);
		$description = mysql_real_escape_string($_POST['description']);
		$error = false;
		$pid = (int) $_REQUEST['pid'];
		
		if (empty($_FILES['upload']['name'])) {
			$p = $wpdb->get_row("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = $pid");
			$filename = $p->image;
		} elseif ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			$prefix = '';
			$sermonUploadDir = get_option('sb_sermon_upload_dir');
			if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
				@chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
			}
			$dest = $wordpressRealPath.$sermonUploadDir.'images/'.$filename;
			if (@move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
				$filename = $prefix.mysql_real_escape_string($filename);
			}else {
			    $error = true;
			    echo '<div id="message" class="updated fade"><p><b>'.__('Could not save uploaded file. Please try again.', $sermon_domain).'</b></div>';
				@chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
			}
		} else {
		        $error = true;
		    	echo '<div id="message" class="updated fade"><p><b>'.__('Could not upload file. Please check the Options page for any errors or warnings.', $sermon_domain).'</b></div>';
		}
		
		if ($pid == 0) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$name', '$description', '$filename')");
		} else {
			$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$name', description = '$description', image = '$filename' WHERE id = $pid");
			if ($_POST['old'] != $filename) {
				@unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.mysql_real_escape_string($_POST['old']));
			}			
		}
		if(isset($_POST['remove'])){
		    $wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$name', description = '$description', image = '' WHERE id = $pid");
		    @unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.mysql_real_escape_string($_POST['old']));
		}
		if(!$error) echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/preachers.php&saved=true';</script>";
	}
	
	if ($_GET['act'] == 'kill') {
		$die = (int) $_GET['pid'];
		if($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons WHERE preacher_id = $die") > 0){
		    echo '<div id="message" class="updated fade"><p><b>'.__('You can\'t delete this preacher.', $sermon_domain).'</b></div>';
		}else {
		    $p = $wpdb->get_row("SELECT image FROM {$wpdb->prefix}sb_preachers WHERE id = $die");
		    @unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').'images/'.$p->image);
		    $wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $die");
		}
	}	
	
	if ($_GET['act'] == 'new' || $_GET['act'] == 'edit') {
		if ($_GET['act'] == 'edit') $preacher = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}sb_preachers WHERE id = ".(int) $_GET['pid']);
	sb_check_sermon_tag();
	//Display HTML
?>
	<div class="wrap">
		<h2><?php echo $_GET['act'] == 'new' ? __('Add', $sermon_domain) : __('Edit', $sermon_domain) ?> <?php _e('preacher', $sermon_domain) ?></h2>
		<br style="clear:both">
		<?php
			$checkSermonUpload = sb_checkSermonUploadable('images/');
			if ($checkSermonUpload == 'notexist') {
				if(!is_dir($wordpressRealPath.$sermonUploadDir.'images') && @mkdir($wordpressRealPath.$sermonUploadDir.'images')){
					@chmod($wordpressRealPath.$sermonUploadDir.'images', 0777);
				}
				$checkSermonUpload = sb_checkSermonUploadable('images/');
			}
			if ($checkSermonUpload != 'writeable') {
				echo '<div id="message" class="updated fade"><p><b>'.__("The images folder is not writeable. You won't be able to upload images.", $sermon_domain).'</b></div>';
			}
		?>
		<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="pid" value="<?php echo (int) $_GET['pid'] ?>">
		<fieldset>
			<table class="widefat">
				<tr>					
					<td>
						<strong><?php _e('Name', $sermon_domain) ?></strong>
						<div>							
							<input type="text" value="<?php echo stripslashes($preacher->name) ?>" name="name" size="60" style="width:400px;" />
						</div>
					</td>		
				</tr>
				<tr>
					<td>
						<strong><?php _e('Description', $sermon_domain) ?></strong>
						<div>
							<textarea name="description" cols="100" rows="5"><?php echo stripslashes($preacher->description) ?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<?php if ($_GET['act'] == 'edit'): ?>
						<div><img src="<?php echo $url ?><?php echo get_option('sb_sermon_upload_dir').'images/'.$preacher->image ?>"></div>
						<input type="hidden" name="old" value="<?php echo $preacher->image ?>">
						<?php endif ?>
						<strong><?php _e('Image', $sermon_domain) ?></strong>
						<div>
							<input type="file" name="upload">
							<label>Remove image&nbsp;<input type="checkbox" name="remove" value="true"></label>
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<p class="submit"><input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
		</form>
	</div>
<?php
		return;
	}
	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name asc");
?>
	<div class="wrap">
		<h2><?php _e('Preachers', $sermon_domain) ?> (<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/preachers.php&act=new"><?php _e('add new', $sermon_domain) ?></a>)</h2>
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Name', $sermon_domain) ?></div></th>				
				<th scope="col"><div style="text-align:center"><?php _e('Image', $sermon_domain) ?></div></th>				
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>
			<tbody>
				<?php foreach ((array) $preachers as $preacher): ?>
					<tr class="<?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>">
						<td><?php echo $preacher->id ?></td>
						<td><?php echo stripslashes($preacher->name) ?></td>
						<td><img src="<?php echo empty($preacher->image) ? '' : $url.get_option('sb_sermon_upload_dir').'images/'.$preacher->image ?>"></td>
						<td align="center">
							<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/preachers.php&act=edit&pid=<?php echo $preacher->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/preachers.php&act=kill&pid=<?php echo $preacher->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
						</td>
					</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	</div>
<?php
}

// Display services & series page and handle changes
function sb_manage_everything() {
	global $wpdb, $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_categories'))
			wp_die(__("You do not have the correct permissions to manage the series and services database", $sermon_domain));
	
	$url = get_bloginfo('wpurl');
	
	$series = $wpdb->get_results("SELECT ss.*, m.id AS mid FROM {$wpdb->prefix}sb_series AS ss LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON ss.id = m.series_id ORDER BY ss.name asc");	
	$services = $wpdb->get_results("SELECT s.*, m.id AS mid FROM {$wpdb->prefix}sb_services AS s LEFT OUTER JOIN {$wpdb->prefix}sb_sermons AS m ON s.id = m.service_id ORDER BY s.name asc");
	
	$toManage = array(
		'Series' => array('data' => $series),
		'Services' => array('data' => $services),
	);
	sb_check_sermon_tag();
?>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/fat.js"></script>
	<script type="text/javascript">
		//<![CDATA[
		function updateClass(type) {
			jQuery('.' + type + ':visible').each(function(i) {
				jQuery(this).removeClass('alternate');
				if (++i % 2 == 0) {
					jQuery(this).addClass('alternate');
				}
			});
		}		
		function createNewServices(s) {
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("New service's name - default time?", "Service's name @ 18:00");							
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {sname: s, sermon: 1}, function(r) {				
					if (r) {
						sz = s.match(/(.*?)@(.*)/)[1];
						t = s.match(/(.*?)@(.*)/)[2];
						jQuery('#Services-list').append('\
							<tr style="display:none" class="Services" id="rowServices' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Services' + r + '">' + sz + '</td>\
								<td style="text-align:center">' + t + '</td>\
								<td style="text-align:center">\
									<a id="linkServices' + r + '" href="javascript:renameServices(' + r + ', \'' + sz + '\')">Edit</a> | <a onclick="return confirm(\'Are you sure?\');" href="javascript:deleteServices(' + r + ')">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#rowServices' + r).fadeIn(function() {
							updateClass('Services');
						});
					};
				});	
			}
		}
		function createNewSeries(s) {
			var ss = prompt("New series' name?", "Series' name");
			if (ss != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#Series-list').append('\
							<tr style="display:none" class="Series" id="rowSeries' + r + '">\
								<th style="text-align:center" scope="row">' + r + '</th>\
								<td id="Series' + r + '">' + ss + '</td>\
								<td style="text-align:center">\
									<a id="linkSeries' + r + '" href="javascript:renameSeries(' + r + ', \'' + ss + '\')">Rename</a> | <a onclick="return confirm(\'Are you sure?\');" href="javascript:deleteSeries(' + r + ')">Delete</a>\
								</td>\
							</tr>\
						');
						jQuery('#rowSeries' + r).fadeIn(function() {
							updateClass('Series');
						});
					};
				});	
			}
		}
		function deleteSeries(id) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {ssname: 'dummy', ssid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowSeries' + id).fadeOut(function() {
						updateClass('Series');
					});
				};
			});			
		}
		function deleteServices(id) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {sname: 'dummy', sid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#rowServices' + id).fadeOut(function() {
						updateClass('Services');
					});
				};
			});			
		}
		function renameSeries(id, old) {
			var ss = prompt("New series' name?", old);
			if (ss != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {ssid: id, ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#Series' + id).text(ss);
						jQuery('#linkSeries' + id).attr('href', 'javascript:renameSeries(' + id + ', "' + ss + '")');
						Fat.fade_element('Series' + id);
					};
				});	
			}
		}
		function renameServices(id, old) {
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("New service's name - default time?", old);								
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {sid: id, sname: s, sermon: 1}, function(r) {
					if (r) {
						sz = s.match(/(.*?)@(.*)/)[1];
						t = s.match(/(.*?)@(.*)/)[2];						
						jQuery('#Services' + id).text(sz);
						jQuery('#time' + id).text(t);
						jQuery('#linkServices' + id).attr('href', 'javascript:renameServices(' + id + ', "' + s + '")');
						Fat.fade_element('Services' + id);
						Fat.fade_element('time' + id);
					};
				});	
			}
		}
		//]]>
	</script>
	<a name="top"></a>
<?php
	foreach ($toManage as $k => $v) {
		$i = 0;
?>
	<a name="manage-<?php echo $k ?>"></a>
	<div class="wrap">
		<h2><?php echo $k ?> (<a href="javascript:createNew<?php echo $k ?>()"><?php _e('add new', $sermon_domain) ?></a>)</h2> 
		<br style="clear:both">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Name', $sermon_domain) ?></div></th>
				<?php echo $k == 'Services' ? '<th scope="col"><div style="text-align:center">'.__('Default time', $sermon_domain).'</div></th>' : '' ?>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>	
			<tbody id="<?php echo $k ?>-list">
				<?php if (is_array($v['data'])): ?>
					<?php $cheat = array() ?>
					<?php foreach ($v['data'] as $item): ?>
					<?php if (!in_array($item->id, $cheat)): ?>
						<?php $cheat[] = $item->id ?>
						<tr class="<?php echo $k ?> <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="row<?php echo $k ?><?php echo $item->id ?>">
							<th style="text-align:center" scope="row"><?php echo $item->id ?></th>
							<td id="<?php echo $k ?><?php echo $item->id ?>"><?php echo stripslashes($item->name) ?></td>
							<?php echo $k == 'Services' ? '<td style="text-align:center" id="time'.$item->id.'">'.$item->time.'</td>' : '' ?>
							<td style="text-align:center">
								<a id="link<?php echo $k ?><?php echo $item->id ?>" href="javascript:rename<?php echo $k ?>(<?php echo $item->id ?>, '<?php echo $item->name ?><?php echo $k == 'Services' ? ' @ '.$item->time : '' ?>')"><?php echo $k == 'Services' ? __('Edit', $sermon_domain) : __('Rename', $sermon_domain) ?></a> <?php if ($item->mid == ""): ?>| <a onclick="return confirm('Are you sure?');" href="javascript:delete<?php echo $k ?>(<?php echo $item->id ?>)"><?php _e('Delete', $sermon_domain) ?></a><?php else: ?> | <a href="javascript:alert('<?php switch ($k) { 
									case "Services": 
										_e('Some sermons are currently assigned to that service. You can only delete services that are not used in the database.', $sermon_domain); 
										break; 
									case "Series": 
										_e('Some sermons are currently in that series. You can only delete series that are empty.', $sermon_domain); 
										break; 
									case "Preachers": 
										_e('That preacher has sermons in the database. You can only delete preachers who have no sermons in the database.', $sermon_domain); 
										break;
									}?>')"><?php _e('Delete', $sermon_domain) ?></a><?php endif ?>
							</td>
						</tr>
					<?php endif ?>
					<?php endforeach ?>
				<?php endif ?>				
			</tbody>			
		</table>
		<br style="clear:both">
		<div style="text-align:right"><a href="#top">Top &dagger;</a></div>
	</div>	
<?php 
	}
}

// Display upload page and handle changes
function sb_uploads() {
	global $wpdb, $filetypes, $sermon_domain, $sermons_per_page;
	global $wordpressRealPath;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('upload_files'))
			wp_die(__("You do not have the correct permissions to upload sermons", $sermon_domain));
	// sync
	sb_scan_dir();
	
	$url = get_bloginfo('wpurl');
	
	if ($_POST['save']) {
		if ($_FILES['upload']['error'] == UPLOAD_ERR_OK) {
			$filename = basename($_FILES['upload']['name']);
			$prefix = '';
			$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').$prefix.$filename;
			if($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = '$filename'") == 0) {
			    if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
				    $filename = $prefix.mysql_real_escape_string($filename);
				    $query = "INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '$filename', 0, 0);";
				    $wpdb->query($query);
				    echo '<div id="message" class="updated fade"><p><b>'.__('Files saved to database.', $sermon_domain).'</b></div>';
			    }
			} else {
			    echo '<div id="message" class="updated fade"><p><b>'.__($filename. ' already exists.', $sermon_domain).'</b></div>';
			}
		}
	}	
	
	$unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT 0, {$sermons_per_page};");
	$linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT 0, {$sermons_per_page};");
    
	//Removes missing attachments from the database
	if($_POST['clean']) {
	    $unlinked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name;");
	    $linked = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name;");
	    $wanted = array(-1);
	    foreach ((array) $unlinked as $k => $file) {
		    if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)) {
			    $wanted[] = $file->id;
			    unset($unlinked[$k]);
	    	}
	    }
	    foreach ((array) $linked as $k => $file) {		
		    if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)) {			
			    $wanted[] = $file->id;
		    	unset($unlinked[$k]);
		    }
	    }
	    $wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id IN (".implode(', ', (array) $wanted).")");
	}
	
	$cntu = $wpdb->get_row("SELECT COUNT(*) as cntu FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ", ARRAY_A);
	$cntu = $cntu['cntu'];		
	$cntl = $wpdb->get_row("SELECT COUNT(*) as cntl FROM {$wpdb->prefix}sb_stuff WHERE sermon_id <> 0 AND type = 'file' ", ARRAY_A);
	$cntl = $cntl['cntl'];		
	sb_check_sermon_tag();
?>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/fat.js"></script>
	<script>
		function rename(id, old) {
			var f = prompt("<?php _e('New file name?', $sermon_domain) ?>", old);
			if (f != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/uploads.php', {fid: id, oname: old, fname: f, sermon: 1}, function(r) {
					if (r) {
						if (r == 'renamed') {
							jQuery('#' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#link' + id).attr('href', 'javascript:rename(' + id + ', "' + f + '")');
							Fat.fade_element(id);
							jQuery('#s' + id).text(f.substring(0,f.lastIndexOf(".")));
							jQuery('#slink' + id).attr('href', 'javascript:rename(' + id + ', "' + f + '")');
							Fat.fade_element('s' + id);
						} else {
							alert('<?php _e('The script is unable to rename your file.', $sermon_domain) ?>');
						}
					};
				});	
			}
		}
		function kill(id, f) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/uploads.php', {fname: f, fid: id, del: 1, sermon: 1}, function(r) {
				if (r) {
					if (r == 'deleted') {
						jQuery('#file' + id).fadeOut(function() {
							jQuery('.file:visible').each(function(i) {
								jQuery(this).removeClass('alternate');
								if (++i % 2 == 0) {
									jQuery(this).addClass('alternate');
								}
							});
						});
						jQuery('#sfile' + id).fadeOut(function() {
							jQuery('.file:visible').each(function(i) {
								jQuery(this).removeClass('alternate');
								if (++i % 2 == 0) {
									jQuery(this).addClass('alternate');
								}
							});
						});
					} else {
						alert('<?php _e('The script is unable to delete your file.', $sermon_domain) ?>');
					}
				};
			});	
		}
		function fetchU(st) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/uploads.php', {fetchU: st + 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-u').html(r);					
					if (st >= <?php echo $sermons_per_page ?>) {
						x = st - <?php echo $sermons_per_page ?>;
						jQuery('#uleft').html('<a href="javascript:fetchU(' + x + ')">&laquo; <?php _e('Previous', $sermon_domain) ?></a>');
					} else {
						jQuery('#uleft').html('');
					}
					if (st + <?php echo $sermons_per_page ?> <= <?php echo $cntu ?>) {
						y = st + <?php echo $sermons_per_page ?>;
						jQuery('#uright').html('<a href="javascript:fetchU(' + y + ')"><?php _e('Next', $sermon_domain) ?> &raquo;</a>');
					} else {
						jQuery('#uright').html('');
					}
				};
			});	
		}
		function fetchL(st) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/uploads.php', {fetchL: st + 1, sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-l').html(r);					
					if (st >= <?php echo $sermons_per_page ?>) {
						x = st - <?php echo $sermons_per_page ?>;
						jQuery('#left').html('<a href="javascript:fetchL(' + x + ')">&laquo; <?php _e('Previous', $sermon_domain) ?></a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + <?php echo $sermons_per_page ?> <= <?php echo $cntl ?>) {
						y = st + <?php echo $sermons_per_page ?>;
						jQuery('#right').html('<a href="javascript:fetchL(' + y + ')"><?php _e('Next', $sermon_domain) ?> &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});	
		}
		function findNow() {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/uploads.php', {search: jQuery('#search').val(), sermon: 1}, function(r) {
				if (r) {
					jQuery('#the-list-s').html(r);										
				};
			});	
		}
	</script>
	<a name="top"></a>
	<div class="wrap">
		<h2><?php _e('Upload Files', $sermon_domain) ?></h2>		
        <?php
		$checkSermonUpload = sb_checkSermonUploadable();
		if ($checkSermonUpload == 'writeable') {
		?>	
		<br style="clear:both">
		<form method="post" enctype="multipart/form-data">
			<table width="100%" cellspacing="2" cellpadding="5" class="widefat">
			<tbody>
			<tr>
				<th valign="top" scope="row"><?php _e('File to upload', $sermon_domain) ?>: </th>
				<td><input type="file" size="40" value="" name="upload"/></td>
			</tr>		
			<tr>
				<th scope="row">&nbsp;</th>
				<td class="submit"><input type="submit" name="save" value="<?php _e('Upload', $sermon_domain) ?> &raquo;" /></td>
			</tr>
			</tbody>
			</table>
		</form>
        <?php
		} else {
		?>
        <p style="color:#FF0000"><?php _e('Upload is disabled. Please check your folder setting in Options.', $sermon_domain);?></p>
        <?php
		}
		?>
	</div>	
	<div class="wrap">
		<h2><?php _e('Unlinked files', $sermon_domain) ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
				<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
				</tr>
			</thead>	
			<tbody id="the-list-u">
				<?php if (is_array($unlinked)): ?>
					<?php foreach ($unlinked as $file): ?>								
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th width="10%" style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td width="50%" id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td width="20%" style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td width="20%" style="text-align:center">
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return confirm('Do you really want to delete <?php echo str_replace("'", '', $file->name) ?>?');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a> 
							</td>
						</tr>
					<?php endforeach ?>			
				<?php endif ?>
			</tbody>			
		</table>
		<br style="clear:both">
		<div class="navigation">
			<div class="alignleft" id="uleft"></div>
			<div class="alignright" id="uright"></div>
		</div>
	</div>
	<a name="linked"></a>
	<div class="wrap">
		<h2><?php _e('Linked files', $sermon_domain) ?></h2>
		<br style="clear:both">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Sermon', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>	
			<tbody id="the-list-l">
				<?php if (is_array($linked)): ?>
					<?php foreach ($linked as $file): ?>
						<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="file<?php echo $file->id ?>">
							<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
							<td id="<?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
							<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
							<td><?php echo stripslashes($file->title) ?></td>
							<td style="text-align:center">
                            <script type="text/javascript" language="javascript">
                            function deletelinked_<?php echo $file->id;?>(filename, filesermon) {
								if (confirm('Do you really want to delete '+filename+'?')) {
									return confirm('This file is linked to the sermon called ['+filesermon+']. Are you sure you want to delete it?');
								}
								return false;
							}
                            </script>
								<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return deletelinked_<?php echo $file->id;?>('<?php echo str_replace("'", '', $file->name) ?>', '<?php echo str_replace("'", '', $file->title) ?>');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a> 
							</td>
						</tr>
					<?php endforeach ?>			
				<?php endif ?>
			</tbody>			
		</table>
		<br style="clear:both">
		<div class="navigation">
			<div class="alignleft" id="left"></div>
			<div class="alignright" id="right"></div>
		</div>
	</div>	
	<a name="search"></a>
	<div class="wrap">
		<h2><?php _e('Search for files', $sermon_domain) ?></h2>
		<form id="searchform" name="searchform">
			<p>
				<input type="text" size="30" value="" id="search" />
				<input type="submit" class="button" value="<?php _e('Search', $sermon_domain) ?> &raquo;" onclick="javascript:findNow();return false;" />
			</p>
		</form>
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File name', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('File type', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Sermon', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>	
			<tbody id="the-list-s">	
				<tr>
					<td><?php _e('Search results will appear here.', $sermon_domain) ?></td>			
				</tr>
			</tbody>			
		</table>
		<br style="clear:both">
	</div>
	<script>
		<?php if ($cntu > $sermons_per_page): ?>
			jQuery('#uright').html('<a href="javascript:fetchU(<?php echo $sermons_per_page ?>)">Next &raquo;</a>');
		<?php endif ?>
		<?php if ($cntl > $sermons_per_page): ?>
			jQuery('#right').html('<a href="javascript:fetchL(<?php echo $sermons_per_page ?>)">Next &raquo;</a>');
		<?php endif ?>
	</script>
	<?php
		if ($checkSermonUpload == 'writeable') {
		?>	
		<div class="wrap">
			<h2><?php _e('Clean up', $sermon_domain) ?></h2>
			<form method="post" >
				<p><?php _e('Pressing the button below scans every sermon in the database, and removes missing attachments. Use with caution!', $sermon_domain) ?></p>
				<input type="submit" name="clean" value="<?php _e('Clean up missing files', $sermon_domain) ?>" />
			</form>
		</div>
		<?php
	}
}

//Count download stats for sermon
function sb_sermon_stats($sermonid) {
	global $wpdb;
	$stats = $wpdb->get_var("SELECT SUM(count) FROM ".$wpdb->prefix."sb_stuff WHERE sermon_id=".$sermonid);
	if ($stats > 0) return $stats;
}

// Displays Sermons page
function sb_manage_sermons() {
	global $wpdb, $sermon_domain, $sermons_per_page;
	//Security check
	if (function_exists('current_user_can')&&!(current_user_can('edit_posts')|current_user_can('publish_posts')))
		wp_die(__("You do not have the correct permissions to edit sermons", $sermon_domain));
	$url = get_bloginfo('wpurl');
	sb_check_sermon_tag();
	if ($_GET['saved']) {
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon saved to database.', $sermon_domain).'</b></div>';
	}
	
	if ($_GET['mid']) {
		//Security check
		if (function_exists('current_user_can')&&!current_user_can('edit_posts'))
			wp_die(__("You do not have the correct permissions to delete sermons", $sermon_domain));
		$mid = (int) $_GET['mid'];
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons WHERE id = $mid;");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = $mid;");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = $mid;");
		$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = $mid AND type = 'file';");
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type <> 'file';");
		echo '<div id="message" class="updated fade"><p><b>'.__('Sermon removed from database.', $sermon_domain).'</b></div>';
	}
	
	$cnt = $wpdb->get_row("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons", ARRAY_A);
	$cnt = $cnt['COUNT(*)'];		
			
	$sermons = $wpdb->get_results("SELECT m.id, m.title, m.date, p.name as pname, s.name as sname, ss.name as ssname FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where (m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id) ORDER BY m.date desc, s.time desc LIMIT 0, {$sermons_per_page};");	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name;");	
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name;");
?>	
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script>
		function fetch(st) {
			jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {fetch: st + 1, sermon: 1, title: jQuery('#search').val(), preacher: jQuery('#preacher option[@selected]').val(), series: jQuery('#series option[@selected]').val() }, function(r) {
				if (r) {
					jQuery('#the-list').html(r);					
					if (st >= <?php echo $sermons_per_page ?>) {
						x = st - <?php echo $sermons_per_page ?>;
						jQuery('#left').html('<a href="javascript:fetch(' + x + ')">&laquo; Previous</a>');
					} else {
						jQuery('#left').html('');
					}
					if (st + <?php echo $sermons_per_page ?> <= <?php echo $cnt ?>) {
						y = st + <?php echo $sermons_per_page ?>;
						jQuery('#right').html('<a href="javascript:fetch(' + y + ')">Next &raquo;</a>');
					} else {
						jQuery('#right').html('');
					}
				};
			});	
		}
	</script>	
	<div class="wrap">
		<h2>Filter</h2>
			<form id="searchform" name="searchform">			
			<fieldset>
				<legend><?php _e('Title', $sermon_domain) ?></legend>
				<input type="text" size="17" value="" id="search" />
			</fieldset>				
			<fieldset>
				<legend><?php _e('Preacher', $sermon_domain) ?></legend>				
				<select id="preacher">
					<option value="0"></option>
					<?php foreach ($preachers as $preacher): ?>
						<option value="<?php echo $preacher->id ?>"><?php echo htmlspecialchars(stripslashes($preacher->name), ENT_QUOTES) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset>	
			<fieldset>
				<legend><?php _e('Series', $sermon_domain) ?></legend>
				<select id="series">
					<option value="0"></option>
					<?php foreach ($series as $item): ?>
						<option value="<?php echo $item->id ?>"><?php echo htmlspecialchars(stripslashes($item->name), ENT_QUOTES) ?></option>
					<?php endforeach ?>
				</select>
			</fieldset>							
			<input type="submit" class="button" value="<?php _e('Filter', $sermon_domain) ?> &raquo;" style="float:left;margin:14px 0pt 1em; position:relative;top:0.35em;" onclick="javascript:fetch(0);return false;" />
			</form>
		<br style="clear:both">
		<h2><?php _e('Sermons', $sermon_domain) ?></h2>		
		<br style="clear:both">
		<table class="widefat">
			<thead>
			<tr>
				<th scope="col"><div style="text-align:center"><?php _e('ID', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Title', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Preacher', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Date', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Service', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Series', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Stats', $sermon_domain) ?></div></th>
				<th scope="col"><div style="text-align:center"><?php _e('Actions', $sermon_domain) ?></div></th>
			</tr>
			</thead>	
			<tbody id="the-list">
				<?php if (is_array($sermons)): ?>
					<?php foreach ($sermons as $sermon): ?>					
					<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
						<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
						<td><?php echo stripslashes($sermon->title) ?></td>
						<td><?php echo stripslashes($sermon->pname) ?></td>
						<td><?php echo $sermon->date ?></td>
						<td><?php echo stripslashes($sermon->sname) ?></td>
						<td><?php echo stripslashes($sermon->ssname) ?></td>
						<td><?php echo sb_sermon_stats($sermon->id) ?></td>
						<td style="text-align:center">
							<?php //Security check
									if (function_exists('current_user_can')&&current_user_can('edit_posts')) { ?>
									<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Delete', $sermon_domain); ?></a>
							<?php } else { ?>&nbsp;<?php } ?>
						</td>
					</tr>
					<?php endforeach ?>
				<?php endif ?>				
			</tbody>			
		</table>
		<div class="navigation">
			<div class="alignleft" id="left"></div>
			<div class="alignright" id="right"></div>
		</div>
	</div>	
	<script>
		<?php if ($cnt > $sermons_per_page): ?>
			jQuery('#right').html('<a href="javascript:fetch(<?php echo $sermons_per_page ?>)">Next &raquo;</a>');
		<?php endif ?>
	</script>
<?php 
}

// Displays new/edit sermon page
function sb_new_sermon() {
	global $wpdb, $books, $sermon_domain;
	global $wordpressRealPath, $allowedposttags;
	//Security check
	if (function_exists('current_user_can')&&!(current_user_can('edit_posts')|current_user_can('publish_posts')))
		wp_die(__("You do not have the correct permissions to edit or create sermons", $sermon_domain));
	include_once ($wordpressRealPath.'/wp-includes/kses.php');
	// sync
	sb_scan_dir();
	
	$url = get_bloginfo('wpurl');

	if ($_POST['save'] && $_POST['title']) {		
		// prepare
		$title = mysql_real_escape_string($_POST['title']);
		$preacher_id = (int) $_POST['preacher'];
		$service_id = (int) $_POST['service'];
		$series_id = (int) $_POST['series'];
		$time = mysql_real_escape_string($_POST['time']);
		for ($foo = 0; $foo < count($_POST['start']); $foo++) { 
			if (!empty($_POST['start']['chapter'][$foo]) && !empty($_POST['end']['chapter'][$foo]) && !empty($_POST['start']['verse'][$foo]) && !empty($_POST['end']['verse'][$foo])) {
				$startz[] = array(
					'book' => $_POST['start']['book'][$foo],
					'chapter' => $_POST['start']['chapter'][$foo],
					'verse' => $_POST['start']['verse'][$foo],					
				);
				$endz[] = array(
					'book' => $_POST['end']['book'][$foo],
					'chapter' => $_POST['end']['chapter'][$foo],
					'verse' => $_POST['end']['verse'][$foo],					
				);
			}
		}
		$start = mysql_real_escape_string(serialize($startz));
		$end = mysql_real_escape_string(serialize($endz));
		$date = date('Y-m-d', strtotime($_POST['date']));
		$description = mysql_real_escape_string(wp_kses($_POST['description'], $allowedposttags));
		$override = $_POST['override'] == 'on' ? 1 : 0;
		// edit or not edit
		if (!$_GET['mid']) { // new
			//Security check
			if (function_exists('current_user_can')&&!current_user_can('publish_posts'))
				wp_die(__("You do not have the correct permissions to create sermons", $sermon_domain));
			$query1 = "INSERT INTO {$wpdb->prefix}sb_sermons VALUES (null, '$title', '$preacher_id', '$date', '$service_id', '$series_id', '$start', '$end', '$description', '$time', '$override', 0)";
			$wpdb->query($query1);				
			$id = $wpdb->insert_id;				
		} else { // edit
			//Security check
			if (function_exists('current_user_can')&&!current_user_can('edit_posts'))
				wp_die(__("You do not have the correct permissions to edit sermons", $sermon_domain));
			$mid = (int) $_GET['mid'];
			$query1 = "UPDATE {$wpdb->prefix}sb_sermons SET title = '$title', preacher_id = '$preacher_id', date = '$date', series_id = '$series_id', start = '$start', end = '$end', description = '$description', time = '$time', service_id = '$service_id', override = '$override' WHERE id = $mid;";
			$wpdb->query($query1);
			$queryz = "UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = 0 WHERE sermon_id = $mid AND type = 'file' ;";
			$wpdb->query($queryz);
			$queryz2 = "DELETE FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type <> 'file'; ";
			$wpdb->query($queryz2);
			$id = $mid;
		}	
		// deal with books
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_books_sermons WHERE sermon_id = $id;");	
		foreach ($startz as $i => $st) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, '{$st['book']}', '{$st['chapter']}', '{$st['verse']}', $i, 'start', $id);");
		}
		foreach ($endz as $i => $ed) {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_books_sermons VALUES(null, '{$ed['book']}', '{$ed['chapter']}', '{$ed['verse']}', $i, 'end', $id);");
		}
		// now files
		foreach ($_POST['file'] as $uid => $file) {
			if ($_FILES['upload']['error'][$uid] == UPLOAD_ERR_OK) {
				$filename = basename($_FILES['upload']['name'][$uid]);
				$prefix = '';
				$dest = $wordpressRealPath.get_option('sb_sermon_upload_dir').$prefix.$filename;
				if ($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_stuff WHERE name = '$filename'") == 0 && move_uploaded_file($_FILES['upload']['tmp_name'][$uid], $dest)) {
					$filename = $prefix.mysql_real_escape_string($filename);
					$queryz = "INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '$filename', $id, 0);";
					$wpdb->query($queryz);					
				} else {
				    echo '<div id="message" class="updated fade"><p><b>'.__($filename. ' already exists.', $sermon_domain).'</b></div>';
				    $error = true;
				}
			} elseif ($file != 0) {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET sermon_id = $id WHERE id = $file;");
			} 				
		}
		// then URLs
		foreach ((array) $_POST['url'] as $urlz) {
			if (!empty($urlz)) {
				$urlz = mysql_real_escape_string($urlz);
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'url', '$urlz', $id, 0);");
			}			
		}
		// embed code next
		foreach ((array) $_POST['code'] as $code) {
			if (!empty($code)) {
				$code = base64_encode(stripslashes($code));
				$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES(null, 'code', '$code', $id, 0)");
			}
		}
		// tags
		$tags = explode(',', $_POST['tags']);
		$wpdb->query("DELETE FROM {$wpdb->prefix}sb_sermons_tags WHERE sermon_id = $id;");
		foreach ($tags as $tag) {
			$clean_tag = trim(mysql_real_escape_string($tag));
			$wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}sb_tags VALUES (null, '$clean_tag')");
			$tag_id = $wpdb->insert_id;
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_sermons_tags VALUES (null, $id, $tag_id)");
		}
		// everything is fine, get out of here!
		if(!$error) echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/sermon.php&saved=true';</script>";
	}		
	
	// load existing data
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY name asc");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY name asc");
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY name asc");
	$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = 0 AND type = 'file' ORDER BY name asc");	
	
	// sync
	$wanted[] = -1;
	foreach ((array) $files as $k => $file) {
		if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$file->name)) {
			$wanted[] = $file->id;
			unset($files[$k]);
		}
	}
	
	// these following code is for js
	foreach ($services as $service) {
		$serviceId[] = $service->id;
		$deftime[] = $service->time;
	}
	
	for ($lol = 0; $lol < count($serviceId); $lol++) { 
		$timeArr .= "timeArr[{$serviceId[$lol]}] = '$deftime[$lol]';"; 
	}	

	if ($_GET['mid']) {
		$mid = (int) $_GET['mid'];
		$curSermon = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}sb_sermons WHERE id = $mid");
		$files = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_stuff WHERE sermon_id IN (0, $mid) AND type = 'file' ORDER BY name asc");
		$startArr = unserialize($curSermon->start);
		$endArr = unserialize($curSermon->end);
		$rawtags = $wpdb->get_results("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = $mid ORDER BY t.name asc");
		$tags = array();
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		$tags = implode(', ', (array) $tags);
	}
?>
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermon-browser/style.php" type="text/css">
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermon-browser/datepicker.css" type="text/css">
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermon-browser/datePicker.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermon-browser/64.js"></script>
	<script type="text/javascript">		
		var timeArr = new Array();
		<?php echo $timeArr ?>		
		function createNewPreacher(s) {
			if (jQuery('*[@selected]', s).text() != 'Create new preacher') return;
			var p = prompt("New preacher's name?", "Preacher's name");
			if (p != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {pname: p, sermon: 1}, function(r) {
					if (r) {
						jQuery('#preacher option:first').before('<option value="' + r + '">' + p + '</option>');
						jQuery("#preacher option[@value='" + r + "']").attr('selected', 'selected');				
					};
				});	
			}
		}
		function createNewService(s) {
			if (jQuery('*[@selected]', s).text() != 'Create new service') {
				if (!jQuery('#override')[0].checked) {
					jQuery('#time').val(timeArr[jQuery('*[@selected]', s).attr('value')]).attr('disabled', 'disabled');
				}
				return;			
			}
			var s = 'lol';
			while ((s.indexOf('@') == -1) || (s.match(/(.*?)@(.*)/)[2].match(/[0-9]{1,2}:[0-9]{1,2}/) == null)) {
				s = prompt("New service's name - default time?", "Service's name @ 18:00");					
				if (s == null) { break;	}
			}
			if (s != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {sname: s, sermon: 1}, function(r) {
					if (r) {
						jQuery('#service option:first').before('<option value="' + r + '">' + s.match(/(.*?)@/)[1] + '</option>');	
						jQuery("#service option[@value='" + r + "']").attr('selected', 'selected');					
						jQuery('#time').val(s.match(/(.*?)@\s*(.*)/)[2]);
					};
				});	
			}
		}
		function createNewSeries(s) {
			if (jQuery('*[@selected]', s).text() != 'Create new series') return;
			var ss = prompt("New series' name?", "Series' name");
			if (ss != null) {
				jQuery.post('<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php', {ssname: ss, sermon: 1}, function(r) {
					if (r) {
						jQuery('#series option:first').before('<option value="' + r + '">' + ss + '</option>');			
						jQuery("#series option[@value='" + r + "']").attr('selected', 'selected');	
					};
				});	
			}
		}
		function addPassage() {
			var p = jQuery('#passage').clone();	
			p.attr('id', 'passage' + gpid);
			jQuery('tr:first td:first', p).prepend('[<a href="javascript:removePassage(' + gpid++ + ')">x</a>] ');
			jQuery("input", p).attr('value', '');
			jQuery('.passage:last').after(p);
		}
		function removePassage(id) {
			jQuery('#passage' + id).remove();
		}
		function syncBook(s) {
			var slc = jQuery('*[@selected]', s).text();
			jQuery('.passage').each(function(i) {
				if (this == jQuery(s).parents('.passage')[0]) {
					jQuery('.end').each(function(j) {
						if (i == j) {
							jQuery("option[@value='" + slc + "']", this).attr('selected', 'selected');
						}
					});
				}
			});			
		}		
		
		function addFile() {
			var f = jQuery('#choosefile').clone();
			f.attr('id', 'choose' + gfid);
			jQuery(".choosefile", f).attr('name', 'choose' + gfid);	
			jQuery("td", f).css('display', 'none');
			jQuery("td:first", f).css('display', '');
			jQuery('th', f).prepend('[<a href="javascript:removeFile(' + gfid++ + ')">x</a>] ');
			jQuery("option[@value='0']", f).attr('selected', 'selected');
			jQuery("input", f).val('');
			jQuery('.choose:last').after(f);
					
		}
		function removeFile(id) {
			jQuery('#choose' + id).remove();
		}
		function doOverride(id) {
			var chk = jQuery('#override')[0].checked;
			if (chk) {
				jQuery('#time').removeClass('gray').attr('disabled', false);
			} else {
				jQuery('#time').addClass('gray').val(timeArr[jQuery('*[@selected]', jQuery("select[@name='service']")).attr('value')]).attr('disabled', 'disabled');
			}
		}
		var gfid = 0;
		var gpid = 0;
		
		function chooseType(id, type){
			jQuery("#"+id + " td").css("display", "none");
			jQuery("#"+id + " ."+type).css("display", "");
			jQuery("#"+id + " td input").val('');
			jQuery("#"+id + " td select").val('0');
		}
	</script>
	<?php sb_check_sermon_tag(); ?>
	<div class="wrap">
		<h2><?php echo $_GET['mid'] ? 'Edit Sermon' : 'Add Sermon' ?></h2>
		<form method="post" enctype="multipart/form-data">
		<fieldset>
			<table class="widefat">
				<tr>
					<td>
						<strong><?php _e('Title', $sermon_domain) ?></strong>
						<div>
							<input type="text" value="<?php echo stripslashes($curSermon->title) ?>" name="title" size="60" style="width:400px;" />
						</div>
					</td>	
					<td>
						<strong><?php _e('Tags (comma separated)', $sermon_domain) ?></strong>
						<div>
							<input type="text" name="tags" value="<?php echo stripslashes($tags) ?>" style="width:400px" />
						</div>
					</td>				
				</tr>
				<tr>					
					<td>
						<strong><?php _e('Preacher', $sermon_domain) ?></strong><br/>
						<select id="preacher" name="preacher" onchange="createNewPreacher(this)">
							<?php if (count($preachers) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>								
								<?php foreach ($preachers as $preacher): ?>
									<option value="<?php echo $preacher->id ?>" <?php echo $preacher->id == $curSermon->preacher_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($preacher->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newPreacher"><?php _e('Create new preacher', $sermon_domain) ?></option>
						</select>
					</td>
					<td>
						<strong><?php _e('Series', $sermon_domain) ?></strong><br/>
						<select id="series" name="series" onchange="createNewSeries(this)">
							<?php if (count($series) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>
								<?php foreach ($series as $item): ?>
									<option value="<?php echo $item->id ?>" <?php echo $item->id == $curSermon->series_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($item->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newSeries"><?php _e('Create new series', $sermon_domain) ?></option>
						</select>
					</td>					
				</tr>
				<tr>
					<td>
						<strong><?php _e('Date', $sermon_domain) ?></strong> (yyyy-mm-dd)
						<div>
							<input type="text" id="date" name="date" value="<?php echo $curSermon->date ?>" />
						</div>
					</td>
					<td rowspan="3">
						<strong><?php _e('Description', $sermon_domain) ?></strong>
						<div>
							<textarea name="description" cols="50" rows="7"><?php echo stripslashes($curSermon->description) ?></textarea>
						</div>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php _e('Service', $sermon_domain) ?></strong><br/>
						<select id="service" name="service" onchange="createNewService(this)">
							<?php if (count($services) == 0): ?>
								<option value="" selected="selected"></option>
							<?php else: ?>
								<?php foreach ($services as $service): ?>
									<option value="<?php echo $service->id ?>" <?php echo $service->id == $curSermon->service_id ? 'selected="selected"' : '' ?>><?php echo htmlspecialchars(stripslashes($service->name), ENT_QUOTES) ?></option>
								<?php endforeach ?>
							<?php endif ?>
							<option value="newService"><?php _e('Create new service', $sermon_domain) ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<strong><?php _e('Time', $sermon_domain) ?></strong>
						<div>
							<input type="text" name="time" value="<?php echo $curSermon->time ?>" id="time" <?php echo !$curSermon->override ? 'disabled="disabled" class="gray"' : '' ?> /> 
							<input type="checkbox" name="override" style="width:30px" id="override" onchange="doOverride()" <?php echo !$curSermon->override ? '' : 'checked="checked"' ?>> <?php _e('Override default time', $sermon_domain) ?> 
						</div>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<strong><?php _e('Bible passage', $sermon_domain) ?></strong> (<a href="javascript:addPassage()"><?php _e('add more', $sermon_domain) ?></a>)
					</td>
				</tr>
				<tr>
					<td><?php _e('From', $sermon_domain) ?></td>
					<td><?php _e('To', $sermon_domain) ?></td>
				</tr>
				<tr id="passage" class="passage">					
					<td>
						<table>
							<tr>
								<td>
									<select name="start[book][]" onchange="syncBook(this)" class="start1">
										<option value=""></option>
										<?php foreach ($books as $book): ?>
											<option value="<?php echo $book ?>"><?php echo $book ?></option>
										<?php endforeach ?>
									</select>
								</td>
								<td><input type="text" style="width:60px;" name="start[chapter][]" value="" class="start2" /><br /></td>
								<td><input type="text" style="width:60px;" name="start[verse][]" value="" class="start3" /><br /></td>
							</tr>
						</table>
					</td>
					<td>
						<table>
							<tr>								
								<td>
									<select name="end[book][]" class="end">
										<option value=""></option>
										<?php foreach ($books as $book): ?>
											<option value="<?php echo $book ?>"><?php echo $book ?></option>
										<?php endforeach ?>
									</select>
								</td>
								<td><input type="text" style="width:60px;" name="end[chapter][]" value="" class="end2" /><br /></td>
								<td><input type="text" style="width:60px;" name="end[verse][]" value="" class="end3" /><br /></td>
							</tr>
						</table>						
					</td>					
				</tr>
				<tr>
					<td colspan="2">
						<strong><?php _e('Attachments', $sermon_domain) ?></strong> (<a href="javascript:addFile()"><?php _e('add more', $sermon_domain) ?></a>)
					</td>
				</tr>
				<tr >
					<td colspan="2">
						<table>
							<tr id="choosefile" class="choose">
								<th>
								<select class="choosefile" name="choosefile" onchange="chooseType(this.name, this.value);">
								<option value="filelist"><?php _e('Choose existing file:', $sermon_domain) ?></option>
								<option value="newupload"><?php _e('Upload a new one:', $sermon_domain) ?></option>
								<option value="newurl"><?php _e('Enter an URL:', $sermon_domain) ?></option>
								<option value="newcode"><?php _e('Enter embed code:', $sermon_domain) ?></option>
								</select>
								</th>
								<td class="filelist">
									<select id="file" name="file[]">									
									<?php echo count($files) == 0 ? '<option value="0">No files found</option>' : '<option value="0"></option>' ?>
									<?php foreach ($files as $file): ?>										
										<option value="<?php echo $file->id ?>"><?php echo $file->name ?></option>
									<?php endforeach ?>									
									</select>									
								</td>
								<td class="newupload" style="display:none"><input type="file" size="50" name="upload[]"/></td>
								<td class="newurl" style="display:none"><input type="text" size="50" name="url[]"/></td>
								<td class="newcode" style="display:none"><input type="text" size="92" name="code[]"/></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</fieldset>
		<p class="submit"><input type="submit" name="save" value="<?php _e('Save', $sermon_domain) ?> &raquo;" /></p> 
		</form>
	</div>			
	<script type="text/javascript">
		jQuery.datePicker.setDateFormat('ymd','-');
		jQuery('#date').datePicker({startDate:'01/01/1970'});
		<?php if (empty($curSermon->time)): ?>
			jQuery('#time').val(timeArr[jQuery('*[@selected]', jQuery("select[@name='service']")).attr('value')]);
		<?php endif ?>
		<?php if ($mid): ?>
			stuff = new Array();
			type = new Array();
			start1 = new Array();
			start2 = new Array();
			start3 = new Array();
			end1 = new Array();
			end2 = new Array();
			end3 = new Array();
			
			<?php 
				$assocFiles = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type = 'file' ORDER BY name asc;");
				$assocURLs = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type = 'url' ORDER BY name asc;");
				$assocCode = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE sermon_id = $mid AND type = 'code' ORDER BY name asc;");	
				$r = false;
			?>
			
			<?php for ($lolz = 0; $lolz < count($assocFiles); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push(<?php echo $assocFiles[$lolz]->id ?>);
				type.push('file');
			<?php endfor ?>
			
			<?php for ($lolz = 0; $lolz < count($assocURLs); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push('<?php echo $assocURLs[$lolz]->name ?>');
				type.push('url');
			<?php endfor ?>

			<?php for ($lolz = 0; $lolz < count($assocCode); $lolz++): ?>
				<?php $r = true ?>
				addFile();
				stuff.push('<?php echo $assocCode[$lolz]->name ?>');
				type.push('code');
			<?php endfor ?>
			
			<?php if ($r): ?>
			jQuery('.choose:last').remove();
			<?php endif ?>
			
			<?php for ($lolz = 0; $lolz < count($startArr); $lolz++): ?>
				<?php if ($lolz != 0): ?>
					addPassage();
				<?php endif ?>
				start1.push("<?php echo $startArr[$lolz]['book'] ?>");
				start2.push("<?php echo $startArr[$lolz]['chapter'] ?>");
				start3.push("<?php echo $startArr[$lolz]['verse'] ?>");
				end1.push("<?php echo $endArr[$lolz]['book'] ?>");
				end2.push("<?php echo $endArr[$lolz]['chapter'] ?>");
				end3.push("<?php echo $endArr[$lolz]['verse'] ?>");
			<?php endfor ?>
			
			jQuery('.choose').each(function(i) {
				switch (type[i]) {
					case 'file':
						jQuery("option[@value='filelist']", this).attr('selected', 'selected');
						jQuery('.filelist', this).css('display','');
						jQuery("option[@value='" + stuff[i] + "']", this).attr('selected', 'selected');
						break;
					case 'url':
						jQuery('td', this).css('display', 'none');
						jQuery("option[@value='newurl']", this).attr('selected', 'selected');
						jQuery('.newurl ', this).css('display','');			
						jQuery(".newurl input", this).val(stuff[i]);
						break;
					case 'code':
						jQuery('td', this).css('display', 'none');
						jQuery("option[@value='newcode']", this).attr('selected', 'selected');
						jQuery('.newcode', this).css('display','');
						jQuery(".newcode input", this).val(Base64.decode(stuff[i]));
						break;
				}
			});		
			
			jQuery('.start1').each(function(i) {
				jQuery("option[@value='" + start1[i] + "']", this).attr('selected', 'selected');
			});	
			
			jQuery('.end').each(function(i) {
				jQuery("option[@value='" + end1[i] + "']", this).attr('selected', 'selected');
			});		
			
			jQuery('.start2').each(function(i) {
				jQuery(this).val(start2[i]);
			});	
			
			jQuery('.start3').each(function(i) {
				jQuery(this).val(start3[i]);
			});	
			
			jQuery('.end2').each(function(i) {
				jQuery(this).val(end2[i]);
			});	
			
			jQuery('.end3').each(function(i) {
				jQuery(this).val(end3[i]);
			});				
		<?php endif ?>		
	</script>
<?php 
}

// Find new files uploaded by FTP
function sb_scan_dir() {
	global $wpdb;
	global $wordpressRealPath;
	
	$files = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE type = 'file';");
	$bnn = array();
	foreach ($files as $file) {
		$bnn[] = $file->name;
	}
	
	$dir = $wordpressRealPath.get_option('sb_sermon_upload_dir');	
	if ($dh = @opendir($dir)) {
		while (false !== ($file = readdir($dh))) {
	    	if ($file != "." && $file != ".." && !is_dir($dir.$file) && !in_array($file, $bnn)) {	    		
	    		$wpdb->query("INSERT INTO {$wpdb->prefix}sb_stuff VALUES (null, 'file', '$file', 0, 0);");
	       	}	
		}
	   	closedir($dh);
	}
}

// Check to see if upload folder is writeable
function sb_checkSermonUploadable($foldername = "") {
	global $wordpressRealPath;
	$sermonUploadDir = $wordpressRealPath.get_option('sb_sermon_upload_dir').$foldername;
	if (is_dir($sermonUploadDir)) {
		//Dir exist
		$fp = @fopen($sermonUploadDir.'sermontest.txt', 'w');
		if ($fp) {
			//Delete this test file
			fclose($fp);
			unset($fp);
			@unlink($sermonUploadDir.'sermontest.txt');
			return 'writeable';			
		} else {
			return 'unwriteable';
		}
	} else {
		return 'notexist';
	}
	return false;
}

// Displays the help page
function sb_help() {
global $sermon_domain;
sb_check_sermon_tag();
?>	
	<style>div.wrap h3, div.wrap h4, div.wrap h5 {margin-bottom: 0; margin-top: 2em} div.wrap p {margin-left: 2em; margin-top: 0.5em} div.wrap h3 {border-top: 1px solid #555555; padding-top: 0.5em}</style>
	<div class="wrap">
		<h2><?php _e('Help page', $sermon_domain) ?></h2>
		<h3>Screencasts</h3>
		<p>If you need help with using Sermon Browser for the first time, these five minute screencast tutorials should be your first port of call:</p>
		<ul>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-1.html" target="_blank">Installation and Overview</a></li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-2.html" target="_blank">Basic Options</a></li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-3.html" target="_blank">Preachers, Series and Services</a></li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-4.html" target="_blank">Entering a new sermon</a></li>
			<li><a href="http://www.4-14.org.uk/sermonbrowser-tutorial/tutorial-5.html" target="_blank">Editing a sermon and adding embedded video</a></li>
		</ul>
		<h4>Template tags</h4>
		<p>If you want to change the way SermonBrowser displays on your website, you'll need to edit the templates and/or CSS file. Check out <a href="#templatetags">this guide to the template tags</a>.</p>
		<h3>Frequently asked questions</h3>
		<ul>
			<li><a href="#nosermons">I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they?</a></li>
			<li><a href="#chmod">What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean?</a></li>
			<li><a href="#uploaderrors">SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening?</a></li>
			<li><a href="#audioplayer">Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites?</a></li>
			<li><a href="#differentversions">How do I change the Bible version from the ESV?</a></li>
			<li><a href="#chipmunk">When using the 1pixelout audio player, my pastor sounds like a chipmunk! What's going on?</a>
			<li><a href="#sidebar">How do I get recent sermons to display in my sidebar?</a></li>
			<li><a href="#diskspace">My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do?</a></li>
			<li><a href="#videos">How do I upload videos to SermonBrowser?</a></li>
			<li><a href="#poweredby">Can I turn off the "Powered by Sermonbrowser" link?</a></li>
			<li><a href="#publicprivate">What is the difference between the public and private podcast feeds?</a></li>
			<li><a href="#differentpodcasts">On the sermons page, what is the difference between subscribing to <b>full</b> podcast, and subscribing to a <b>custom</b> podcast?</a></li>
			<li><a href="#itunes">Why doesn't iTunes recognise the podcast links?</a></li>
			<li><a href="#sortorder">Can I change the default sort order of the sermons?</a></li>
			<li><a href="#pagenotfound">Why do I get a page not found error when I click on my podcast feed?</a></li>
			<li><a href="#changedisplay">Can I change the way sermons are displayed?</a></li>
			<li><a href="#changesearchform">The search form is too big/too small for my layout. How do I make it narrower/wider?</a></li>
			<li><a href="#bibletextmissing">Why is sometimes the Bible text missing?</a></li>
			<li><a href="#exceededquota">Why does my sermon page say I have exceeded my quota for ESV lookups?</a></li>
			<li><a href="#icons">How can I change the icons that Sermon Browser uses, or add new icons?</a></li>
		</ul>
		<hr style="width: 50%">
		<h4 id="nosermons">I've activated the plugin, and entered in a few sermons, but they are not showing up to my website users. Where are they?</h4>
		<p>SermonBrowser only displays your sermons where you choose. You need to create the page/post where you want the sermons to appear (or edit an existing one), and add <b>[sermons]</b> to the page/post. You can also add some explantory text if you wish. If you do so, the text will appear on <i>all</i> your sermons pages. If you want your text to only appear on the list of sermons, not on individual sermon pages, you need to edit the SermonBrowser templates (see below).</p>
		<h4 id="chmod">What does the error message "Error: The upload folder is not writeable. You need to CHMOD the folder to 666 or 777." mean?</h4>
		<p>SermonBrowser tries to set the correct permissions on your folders for you, but sometimes restrictions mean that you have to do it yourself. You need to make sure that SermonBrowser is able to write to your sermons upload folder (usually /wp-content/uploads/sermons/). <a href="http://samdevol.com/wordpress-troubleshooting-permissions-chmod-and-paths-oh-my/" target="_blank">This tutorial</a> explains how to use the free FileZilla FTP software to do this.</p>
		<h4 id="uploaderrors">SermonBrowser spends a long time attempting to upload files, but the file is never uploaded. What's happening?</h4>
		<p>The most likely cause is that you're reaching either the maximum filesize that can be uploaded, or the maximum time a PHP script can run for. <a href="http://articles.techrepublic.com.com/5100-10878_11-5272345.html" target="_blank">Editing your php.ini</a> may help overcome these problems - but if you're on shared hosting, it's possible your host has set maximum limits you cannot change. If that's the case, you should upload your files via FTP. This is generally a better option than using your browser, particularly if you have several files to upload. If you do edit your php.ini file, these settings should be adequate:</p>
		<p style="font-family:monospace">file_uploads = On<br />
		upload_max_filesize = 15M<br />
		post_max_size = 15M<br />
		max_execution_time = 600<br/>
		max_input_time = 600<br />
		memory_limit = 16M<br /></p>
		<h4 id="audioplayer">Why are my MP3 files are appearing as an icon, rather than as a player, as I've seen on other SermonBrowser sites?</h4>
		<p>You need to install and activate the <a href="http://www.1pixelout.net/code/audio-player-wordpress-plugin/">1pixelout audio player</a> plugin. You can also customise the plugin so that its colours match your site.</p>
		<h4 id="differentversions">How do I change the Bible version from the ESV?</h4>
		<p>Five Bible versions are supported by Sermon Browser: the English Standard Version, American Standard Version, King James Version, Young's Literal Transaltion and the World English Bible. To change to one of these other versions, go to Options, and edit the single template. Replace [esvtext] with [asvtext], [kjvtext], [ylttext] or [webtext]. Thanks go to <a href="http://www.crosswaybibles.org/" target="_blank">Crossway</a> for providing access to the ESV, and <a href="http://www.lstones.com/" target="_blank">Living Stones Ministries</a> for the other versions.</p>
		<p>If you're desperate to use other versions not currently supported, you can manage it using other Wordpress plugins (albeit with reduced functionality).  However, if you're desperate to use other versions, you can manage it using other Wordpress plugins (albeit with reduced functionality). The <a href="http://wordpress.org/extend/plugins/ebibleicious/">eBibleicious</a> plugin allows for NASB, MSG, KJV, NKJV, ESV, HCSB, and NCV (use it in 'snippet' mode). However, there are three disadvantages. (1) To use it, you'll need to register for an API key (although it is free). (2) It uses Javascript so search engines won't see the Bible text, and nor will users with javascript turned off. (3) Most importantly, it only shows a maximum of four verses (the ESV shows up to 500 verses!).
		<p>You can also use the <a href="http://www.logos.com/reftagger">RefTagger</a> plugin, though this shows even fewer verses. Even worse (for our purposes) the bible passage only shows when you hover over a special link with your mouse. It does, however, provide an even longer list of translations. Please be aware that both RefTagger and eBibleicious will add bible text to bible references across your whole website, not just your sermons pages.</p>
		<p>To use either of these alternatives, just download, install and activate them as you would for any other plugin. Check their settings (make sure you enter get an API key if you're using eBiblicious). You then need to make one change to your SermonBrowser options. In the <i>Single Sermon form</i>, look for <b>[esvtext]</b> and replace it with <b>[biblepassage]</b>. (By default it's right at the end of the code.)</p>
		<h4 id="chipmunk">When using the 1pixelout audio player, my pastor sounds like a chipmunk! What's going on?</h4>
		<p>This 'feature' is caused by a well-known bug in Adobe flash. In order for the files to play correctly, when they are saved, the sample rate needs to be set at a multiple of 11.025kHz (i.e. 11.025, 22.05 or 44.1).</p>
		<h4 id="sidebar">How do I get recent sermons to display in my sidebar?</h4>
		<p>If your WordPress theme supports widgets, just go to Design and choose <a href="widgets.php">Widgets</a>. There you easily can add the Sermons widget to your sidebar. If your theme doesn't support widgets, you'll need to edit your theme manually. Usually, you'll be editing a file called <b>sidebar.php</b>, but your theme may give it a different name. Add the following code:</p>
		<p style="font-family:monospace">&lt;?php if (function_exists('display_sermons')) display_sermons(array('display_preacher' => 0, 'display_passage' => 1, 'preacher' => 0, 'service' => 0, 'series' => 0, 'limit' => 5)) ?></code>
		<p>Each of the numbers in that line can be changed. <b>display_preacher</b> and <b>display_passage</b> affect what is displayed (0 is off, 1 is on). <b>preacher</b>, <b>service</b> and <b>series</b> allow you to limit the output to a particular preacher, service or series. Simply change the number of the ID of the preacher/services/series you want to display. You can get the ID from the Preachers page, or the Series & Services page. 0 shows all preachers/services/series. <b>limit</b> is simply the maximum number of sermons you want displayed.</p>
		<h4 id="diskspace">My host only allows me a certain amount of disk space, and I have so many sermons uploaded, I've run out of space! What can I do?</h4>
		<p>You could, of course, change your host to someone a little more generous! I use <a href="http://www.vortechhosting.com/shared/windows.php">VortechHosting</a> for low traffic sites (5Gb of disk space for less than $10 a month), and <a href="https://www.liquidweb.com/cart/content/vps/">LiquidWeb VPS</a> for higher traffic sites (20Gb disk space for $60 a month). You should also make sure you encode your sermons at a medium to high compression. Usually, 22.05kHz, 48kbps mono is more than adequate (you could probably go down to 32kbps for even higher compression). 48kbps means every minute of recording takes up 360kb of disk space, so a thirty minute sermon will just over 10Mb. At this setting, 5Gb would be enough for over 450 sermons.</p>
		<p>If you can't change your host, you can still use SermonBrowser. You'll just have to upload your sermon files to another site - preferably a free one! We recommend <a href="http://www.odeo.com/" target="blank">Odeo</a>. If you want to use Odeo's audio player on your website, copy the embed code they give you, and when you add your sermon to SermonBrowser, select "Enter embed code:" and paste it in. If you want to use the standard 1pixelout audio player, copy the "Download MP3" link Odeo give you, and when you add your sermon to SermonBrowser, select "Enter an URL" and paste it in.</p>
		<h4 id="videos">How do I upload videos to SermonBrowser?</h4>
		<p>You can't - but you can upload videos to other sites, then embed them in your sermons. You can use any site that allows you to embed your video in other websites, including <a href="http://www.youtube.com/">YouTube</a>, but we recommend <a href="http://video.google.com/videouploadform">GoogleVideo</a> as the most suitable for sermons. That's because most video-sharing sites are designed for relatively short clips of 10 minutes or so, but GoogleVideo will accept videos of any length - and there are no quotas for the maximum size of a video, nor the number of videos you can store. Once your video is uploaded and available on Google Video, you can copy the embed code it gives you, edit your sermon, select "Enter embed code" and paste it in.</p>
		<h4 id="poweredby">Can I turn off the "Powered by Sermonbrowser" link?</h4>
		<p>The link is there so that people from other churches who listen to your sermons can find out about SermonBrowser themselves. But if you'd like to remove the link, just remove <b>[creditlink]</b> from the templates in SermonBrowser Options</a>.</p>
		<h4 id="publicprivate">What is the difference between the public and private podcast feeds?</h4>
		<p>In SermonBrowser options, you are able to change the address of the public podcast feed. This is the feed that is shown on your sermons page, and is usually the same as your private feed. However, if you use a service such as <a href="http://www.feedburner.com/" target="_blank">FeedBurner</a>, you can use your public feed to send data to feedburner, and change your private feed to your Feedburner address. If you do not use a service like Feedburner, just make sure your public and private feeds are the same.</p> 
		<h4 id="differentpodcasts">On the sermons page, what is the difference between subscribing to our podcast, and subscribing to a podcast for this search?</h4>
		<p>The link called <strong>subscribe to full podcast</strong> gives a podcast of <em>all</em> sermons that you add to your site through SermonBrowser. But it may be that some people may just want to subscribe to a feed for certain speakers, or for a certain service. If they wish to do this, they should set the search filters and perform their search, then click on the <strong>Subscribe to custom podcast </strong>link. This will give them a podcast according to the filter they selected. You could also copy this link, and display it elsewhere on the site - for example to provide separate feeds for morning and evening services.</p>
		<h4 id="iTunes">Why doesn't iTunes recognise the podcast links?</h4>
		<p>iTunes requires its own special links that are slightly different from other podcasting software. If you would like to display these links, you need to edit your template and add the tags [itunes_podcast] and [itunes_podcast_for_search].</p>
		<h4 id="sortorder">Can I change the default sort order of the sermons?</h4>
		<p>Unfortunately not. Unless the viewer specified otherwise, Sermonbrowser always displays the most recent sermons at the top.</p>
		<h4 id="pagenotfound">Why do I get a page not found error when I click on my podcast feed?</h4>
		<p>You've probably changed the address of your public feed. Try changing it back to the same value as your private feed in Sermon Options.</p>
		<h4 id="changedisplay">Can I change the way sermons are displayed?</h4>
		<p>Yes, definately, although you need to know a little HTML and/or CSS. SermonBrowser has a powerful templating function, so you can exclude certain parts of the output (e.g. if you don't want the links to other sermons preached on the same day to be displayed). To edit the templates, go to SermonBrowser Options. Below is a reference for all the <a href="templatetags">template tags</a> you need. If you just want to change the way the output looks, without changing what is displayed, you need to edit the CSS stylesheet, also in SermonBrowser Options. (See one example, below).</p>
		<h4 id="changesearchform">The search form is too big/too small for my layout. How do I make it narrower/wider?</h4>
		<p>The search form is set to roughly 500 pixels, which should be about right for most WordPress templates. To change it, look for a line in the CSS stylesheet that begins <b>table.sermonbrowser td.field input</b>, and change the width specified after it. To make the form narrower, reduce the width. To make it bigger, increase the width. You'll also need to change the width of the date fields on the line below, which should be 20 pixels smaller.</p>
		<h4 id="bibletextmissing">Why is sometimes the Bible text missing?</h4>
		<p>This usually happens for one of three reasons: (1) If the website providing the service is down. If you can't see Genesis 1 in the <a href="http://www.esvapi.org/v2/rest/passageQuery?key=IP&amp;passage=Gen+1&amp;include-headings=false">ESV</a> or <a href="http://api.seek-first.com/v1/BibleSearch.php?type=lookup&appid=seekfirst&startbooknum=1&startchapter=1&startverse=1&endbooknum=1&endchapter=1&endverse=30&version=KJV">the other versions</a>then the problem is with those websites. They're rarely down for long. (2) If you specify an invalid bible passage (e.g. Romans 22). If this is the case your sermon page will display <em>ERROR: No results were found for your search.</em> (3) If your webhost has disabled <strong>allow_url_fopen</strong> and cURL. Some cheaper webhosts have these essential features switched off. If they have, you won't be able to use this facility.</p>
		<h4 id="exceededquota">Why does my sermon page say I have exceeded my quota for ESV lookups?</h4>
		<p>The ESV website only allows 5,000 lookups per day from each IP address. That should be enough for most users of SermonBrowser. However, if you are using a shared host, there will be hundreds (perhaps thousands) of other websites on the same IP address as you. If any are also using the ESV API, they also get counted towards that total. If you are using less than 5,000 lookups per day (i.e. you are having less than 5,000 pageviews of your sermon pages), and you receive the error message you'll need to do two things in order to continue to display the text. (1) Sign up for an <a href="http://www.esvapi.org/signup">ESV API key</a>. (2) Edit frontend.php (one of the SermonBrowser files). Look for line 66, and replace <i>&hellip;passageQuery?key=<b>IP</b>&passage=&hellip;</i> with <i>&hellip;passageQuery?key=<b>YOURAPIKEY</b>&passage=&hellip;</i>.</p>
		<p>If you <i>are</i> having more than 5,000 page views per day, then this won't help. Instead, leave a message in the <a href="http://www.4-14.org.uk/sermon-browser#comments">SermonBrowser comments</a> explaining your problem. SermonBrowser could probably be modified to provide a caching mechanism to reduce the likelihood of this error occurring, if there is demand.</p>
		<h4 id="icons">How can I change the file icons that Sermon Browser uses, or add new icons?</h4>
		<p>You'll need to edit the <b>filetypes.php</b> file that comes with Sermon Browser. The icon is chosen on the basis of the file extension (or in the case of URLs the file extension then the site address). If you do create new icons for other filetypes, consider sending them to the author so they can be included in future versions of the plugin.</p>
		<h3 id="templatetags">Template tags</h3>
		<p>If you want to change the output of Sermon Browser, you'll need to edit the templates. You'll need to understand the basics of HTML and CSS, and to know the special SermonBrowser template tags. There are two templates, one (called "results page") is used to produce the search results on the main sermons page. The other template (called sermon page) is used to produce the page for single sermon. Most tags can be used in both templates, but some are specific.</p>
		<h4>Results page only</h4>
		<ul>
			<li><b>[filters_form]</b> - The search form which allows filtering by preacher, series, date, etc. <i>multi-sermons page only</i></li>
			<li><b>[tag_cloud]</b> - A tag cloud of all sermon browser tags</i></li>
			<li><b>[sermons_count]</b> - The number of sermons which match the current search critera. </li>
			<li><b>[sermons_loop][/sermons_loop]</b> - These two tags should be placed around the output for one sermon. (That is all of the tags that return data about sermons should come between these two tags.)</li>
			<li><b>[first_passage]</b> - The main bible passage for this sermon</li>
			<li><b>[previous_page]</b> - Displays the link to the previous page of search results (if needed)</li>
			<li><b>[next_page]</b> - Displays the link to the next page of search results (if needed)</li>
			<li><b>[podcast]</b> - Link to the podcast of all sermons</li>
			<li><b>[podcast_for_search]</b> - Link to the podcast of sermons that match the current search</li>
			<li><b>[itunes_podcast]</b> - iTunes (itpc://) link to the podcast of all sermons</li>
			<li><b>[itunes_podcast_for_search]</b> - iTunes (itpc://) link to the podcast of sermons that match the current search</li>
			<li><b>[podcasticon]</b> - Displays the icon used for the main podcast</li>
			<li><b>[podcasticon_for_search]</b> - Displays the icon used for the custom podcast</li>
		</ul>
		<h4>Both results page and sermon page</h4>
		<ul>
			<li><b>[sermon_title]</b> - The title of the sermon</li>
			<li><b>[preacher_link]</b> - The name of the preacher (hyperlinked to his search results)</li>
			<li><b>[series_link]</b> - The name of the series (hyperlinked to search results)</li>
			<li><b>[service_link]</b> - The name of the service (hyperlinked to search results)</li>
			<li><b>[date]</b> - The date of the sermon</li>
			<li><b>[files_loop][/files_loop]</b> - These two tags should be placed around the [file] tag if you want to display all the files linked with to sermon. They are not needed if you only want to display the first file.</li>
			<li><b>[file]</b> - Displays the files and external URLs</li>
			<li><b>[file_with_download]</b> - As above, but also adds a download link if the AudioPlayer is displayed</li>
			<li><b>[embed_loop][/embed_loop]</b> - These two tags should be placed around the [embed] tag if you want to display all the embedded objects linked to this sermon. They are not needed if you only want to display the first embedded object.</li>
			<li><b>[embed]</b> - Displays an embedded object (e.g. video)</li>
			<li><b>[editlink]</b> - displays an "Edit Sermon" link if currently logged-in user has edit rights.</li>
			<li><b>[creditlink]</b> - displays a "Powered by Sermon Browser" link.</li>
		</ul>
		<h4>Sermon page only</h4>
		<ul>
			<li><b>[preacher_description]</b> - The description of the preacher.</li>
			<li><b>[preacher_image]</b> - The photo of the preacher.</li>
			<li><b>[sermon_description]</b> - The description of the sermon</li>
			<li><b>[passages_loop][/passages_loop]</b> - These two tags should be placed around the [passage] tag if you want to display all the passages linked with to sermon.</li>
			<li><b>[passage]</b> - Displays the reference of the bible passage with the book name hyperlinked to search results.</li>
			<li><b>[next_sermon]</b> - Displays a link to the next sermon preached (excluding ones preached on the same day)</li>
			<li><b>[prev_sermon]</b> - Displays a link to the previous sermon preached</li>
			<li><b>[sameday_sermon]</b> - Displays a link to other sermons preached on that day</li>
			<li><b>[tags]</b> - Displays the tags for that sermons</li>
			<li><b>[esvtext]</b> - Displays the full text of the ESV Bible for all passages linked to that sermon.</li>
			<li><b>[asvtext]</b> - Displays the full text of the ASV Bible for all passages linked to that sermon.</li>
			<li><b>[kjvtext]</b> - Displays the full text of the KJV Bible for all passages linked to that sermon.</li>
			<li><b>[ylttext]</b> - Displays the full text of the YLT Bible for all passages linked to that sermon.</li>
			<li><b>[webtext]</b> - Displays the full text of the WEB Bible for all passages linked to that sermon.</li>
			<li><b>[biblepassage]</b> - Displays the reference of the bible passages for that sermon. Useful for utilising other bible plugins (see <a href="#otherversions">FAQ</a>).</li>
	</div>
	</form>
<?php 
}

// Returns data for ajax calls
function sb_return_ajax_data () {
	// Throughout this plugin, p stands for preacher, s stands for service and ss stands for series
	global $wpdb, $sermons_per_page;
	if ($_POST['pname']) { // preacher
		$pname = mysql_real_escape_string($_POST['pname']);
		if ($_POST['pid']) {
			$pid = (int) $_POST['pid'];
			if ($_POST['del']) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = $pid;");
			} else {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_preachers SET name = '$pname' WHERE id = $pid;");				
			}
			echo 'done';
		} else {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, '$pname', '', '');");
			echo $wpdb->insert_id;
		} 		
	} elseif ($_POST['sname']) { // service
		$sname = mysql_real_escape_string($_POST['sname']);
		list($sname, $stime) = split('@', $sname);
		$sname = trim($sname);
		$stime = trim($stime);
		if ($_POST['sid']) {
			$sid = (int) $_POST['sid'];
			if ($_POST['del']) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_services WHERE id = $sid;");
			} else {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_services SET name = '$sname', time = '$stime' WHERE id = $sid;");
				$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET time = '$stime' WHERE override = 0 AND service_id = $sid;");
			}			
			echo 'done';
		} else {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_services VALUES (null, '$sname', '$stime');");
			echo $wpdb->insert_id;
		}		
	} elseif ($_POST['ssname']) { // series
		$ssname = mysql_real_escape_string($_POST['ssname']);
		if ($_POST['ssid']) {
			$ssid = (int) $_POST['ssid'];
			if ($_POST['del']) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}sb_series WHERE id = $ssid;");
			} else {
				$wpdb->query("UPDATE {$wpdb->prefix}sb_series SET name = '$ssname' WHERE id = $ssid;");
			}				
			echo 'done';
		} else {
			$wpdb->query("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, '$ssname', 0);");
			echo $wpdb->insert_id;
		}
	} elseif ($_POST['fname']) { // file		
		$fname = mysql_real_escape_string($_POST['fname']);
		if ($_POST['fid']) {
			$fid = (int) $_POST['fid'];
			$oname = mysql_real_escape_string($_POST['oname']);			
			if ($_POST['del']) {
				if (!file_exists($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname) || unlink($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname)) {
					$wpdb->query("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id = $fid;");
					echo 'deleted';
				} else {
					echo 'failed';
				}				
			} else {				
				$oname = mysql_real_escape_string($_POST['oname']);	
				if ( !is_writable($wordpressRealPath.get_option('sb_sermon_upload_dir').$fname) && rename($wordpressRealPath.get_option('sb_sermon_upload_dir').$oname, $wordpressRealPath.get_option('sb_sermon_upload_dir').$fname)) {
					$wpdb->query("UPDATE {$wpdb->prefix}sb_stuff SET name = '$fname' WHERE id = $fid;");
					echo 'renamed';
				} else {		
					echo 'failed';				
				}
			}				
		}
	} elseif ($_POST['fetch']) { // ajax pagination (sermon browser)		
		$st = (int) $_POST['fetch'] - 1;
		if (!empty($_POST['title'])) {
			$cond = "and m.title LIKE '%" . mysql_real_escape_string($_POST['title']) . "%' ";
		}
		if ($_POST['preacher'] != 0) {
			$cond .= 'and m.preacher_id = ' . (int) $_POST['preacher'] . ' ';
		}
		if ($_POST['series'] != 0) {
			$cond .= 'and m.series_id = ' . (int) $_POST['series'] . ' ';
		}
		$m = $wpdb->get_results("SELECT m.id, m.title, m.date, p.name as pname, s.name as sname, ss.name as ssname FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id {$cond} ORDER BY m.date desc, s.time desc LIMIT {$st}, {$sermons_per_page};");
		$cnt = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id $cond;");
		?>
		<?php foreach ($m as $sermon): ?>					
			<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
				<th style="text-align:center" scope="row"><?php echo $sermon->id ?></th>
				<td><?php echo stripslashes($sermon->title) ?></td>
				<td><?php echo stripslashes($sermon->pname) ?></td>
				<td><?php echo stripslashes($sermon->date) ?></td>
				<td><?php echo stripslashes($sermon->sname) ?></td>
				<td><?php echo stripslashes($sermon->ssname) ?></td>
				<td><?php echo sb_sermon_stats($sermon->id) ?></td>
				<td style="text-align:center">
					<a href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/new_sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Edit', $sermon_domain) ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo $url ?>/wp-admin/admin.php?page=sermon-browser/sermon.php&mid=<?php echo $sermon->id ?>"><?php _e('Delete', $sermon_domain) ?></a>
				</td>
			</tr>
		<?php endforeach ?>
		<script type="text/javascript">
		<?php if($cnt<$sermons_per_page || $cnt <= $st+$sermons_per_page): ?>
			jQuery('#right').css('display','none');
		<?php elseif($cnt > $st+$sermons_per_page): ?>
			jQuery('#right').css('display','');
		<?php endif ?>
		</script>
		<?php
	} elseif ($_POST['fetchU'] || $_POST['fetchL'] || $_POST['search']) { // ajax pagination (uploads)
		if ($_POST['fetchU']) {
			$st = (int) $_POST['fetchU'] - 1;
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT {$st}, {$sermons_per_page};");
		} elseif ($_POST['fetchL']) {
			$st = (int) $_POST['fetchL'] - 1;
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT {$st}, {$sermons_per_page}");
		} else {
			$s = mysql_real_escape_string($_POST['search']);
			$abc = $wpdb->get_results("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.name LIKE '%{$s}%' AND f.type = 'file' ORDER BY f.name;");
		}		
	?>
	<?php if (count($abc) >= 1): ?>
		<?php foreach ($abc as $file): ?>
			<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="<?php echo $_POST['fetchU'] ? '' : 's' ?>file<?php echo $file->id ?>">
				<th style="text-align:center" scope="row"><?php echo $file->id ?></th>
				<td id="<?php echo $_POST['fetchU'] ? '' : 's' ?><?php echo $file->id ?>"><?php echo substr($file->name, 0, strrpos($file->name, '.')) ?></td>
				<td style="text-align:center"><?php echo isset($filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name']) ? $filetypes[substr($file->name, strrpos($file->name, '.') + 1)]['name'] : strtoupper(substr($file->name, strrpos($file->name, '.') + 1)) ?></td>
				<td><?php echo stripslashes($file->title) ?></td>
				<td style="text-align:center">
					<script type="text/javascript" language="javascript">
					function deletelinked_<?php echo $file->id;?>(filename, filesermon) {
						if (confirm('Do you really want to delete '+filename+'?')) {
							if (filesermon != '') {
								return confirm('This file is linked to the sermon called ['+filesermon+']. Are you sure you want to delete it?');
							}	
							return true;
						}
						return false;
					}
					</script>
					<a id="link<?php echo $file->id ?>" href="javascript:rename(<?php echo $file->id ?>, '<?php echo $file->name ?>')"><?php _e('Rename', $sermon_domain) ?></a> | <a onclick="return deletelinked_<?php echo $file->id;?>('<?php echo str_replace("'", '', $file->name) ?>', '<?php echo str_replace("'", '', $file->title) ?>');" href="javascript:kill(<?php echo $file->id ?>, '<?php echo $file->name ?>');"><?php _e('Delete', $sermon_domain) ?></a>
				</td>
			</tr>
		<?php endforeach ?>	
	<?php else: ?>
		<tr>
			<td><?php _e('No results', $sermon_domain) ?></td>
		</tr>
	<?php endif ?>
	<?php		
	}
	die();
}

function sb_check_sermon_tag() {
	if (sb_display_url() == "") echo '<div id="message" class="updated"><p><b>'.__('You must create a post or page that includes the code [sermons] in order for your sermons to be displayed on your site.', $sermon_domain).'</b></div>';
}

//Processing for php.ini values
function sb_return_kbytes($val) {
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	switch($last) {
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
	}
   return intval($val);
}

//Default template for search results
function sb_default_multi_template () {
$multi = <<<HERE
<div class="sermon-browser">
	<h2>Filters</h2>		
	[filters_form]
   	<div style="clear:both">
		<table class="podcastcustom">
			<tr>
				<td class="podcasticon" rowspan="2"><a href="[podcast_for_search]">[podcasticon_for_search]</a></td>
			</tr>
			<tr>
				<td>
					<p class="podcasttitle"><a href="[podcast_for_search]">Subscribe to custom podcast</a></p>
					<p class="description">(new sermons that match <b>this search</b>)</p>
				</td>
			</tr>
		</table>
		<table class="podcastall">
			<tr>
				<td class="podcasticon" rowspan="2"><a href="[podcast]">[podcasticon]</a></td>
			</tr>
			<tr>
				<td>
					<p class="podcasttitle"><a href="[podcast]">Subscribe to full podcast</a></p>
					<p class="description">(<b>all</b> new sermons)</p>
				</td>
			</tr>
		</table>
	</div>
	<h2>Sermons ([sermons_count])</h2>   	
   	<div class="floatright">[next_page]</div>
   	<div class="floatleft">[previous_page]</div>
	<table class="sermons">
	[sermons_loop]	
		<tr>
			<td class="sermon-title">[sermon_title]</td>
		</tr>
		<tr>
			<td class="sermon-passage">[first_passage] (Part of the [series_link] series).</td>
		</tr>
		<tr>
			<td class="files">[files_loop][file][/files_loop]</td>
		</tr>
		<tr>
			<td class="embed">[embed_loop][embed][/embed_loop]</td>
		</tr>
		<tr>
			<td class="preacher">Preached by [preacher_link] on [date] ([service_link]). [editlink]</td>
		</tr>
   	[/sermons_loop]
	</table>
   	<div class="floatright">[next_page]</div>
   	<div class="floatleft">[previous_page]</div>
   	[creditlink]
</div>
HERE;
	return $multi;
}

//Default template for single sermon page
function sb_default_single_template () {
$single = <<<HERE
<div class="sermon-browser-results">
	<h2>[sermon_title] <span class="scripture">([passages_loop][passage][/passages_loop])</span> [editlink]</h2>
	[preacher_image]<span class="preacher">[preacher_link], [date]</span><br />
	Part of the [series_link] series, preached at a [service_link] service<br />
	<div class="sermon-description">[sermon_description]</div>
	<p class="sermon-tags">Tags: [tags]</p>
	[files_loop]
		[file_with_download]
	[/files_loop]
	[embed_loop]
		<br />[embed]<br />
	[/embed_loop]
	[preacher_description]
	<table class="nearby-sermons">
		<tr>
			<th class="earlier">Earlier:</th>
			<th>Same day:</th>
			<th class="later">Later:</th>
		</tr>
		<tr>
			<td class="earlier">[prev_sermon]</td>
			<td>[sameday_sermon]</td>
			<td class="later">[next_sermon]</td>
		</tr>
	</table>
	[esvtext]
   	[creditlink]
</div>
HERE;
	return $single;
}

//Default CSS
function sb_default_css () {
$css = <<<HERE
.sermon-browser h2 {
	clear: both;
}

div.sermon-browser table.sermons {
	width: 100%;
	clear:both;
}

div.sermon-browser table.sermons td.sermon-title {
	font-weight:bold;
	font-size: 140%;
	padding-top: 2em;
}

div.sermon-browser table.sermons td.sermon-passage {
	font-weight:bold;
	font-size: 110%;
}

div.sermon-browser table.sermons td.preacher {
	border-bottom: 1px solid #444444;
}

div.sermon-browser table.sermons td.files img {
	border: none;
	margin-right: 24px;
}

table.sermonbrowser td.fieldname {
	font-weight:bold;
	padding-right: 10px;
	vertical-align:bottom;
}

table.sermonbrowser td.field input, table.sermonbrowser td.field select{
	width: 170px;
}

table.sermonbrowser td.field  #date, table.sermonbrowser td.field #enddate {
	width: 150px;
}

table.sermonbrowser td {
	white-space: nowrap;
	padding-top: 5px;
	padding-bottom: 5px;
}

table.sermonbrowser td.rightcolumn {
	padding-left: 10px;
}

div.sermon-browser div.floatright {
	float: right
}

div.sermon-browser div.floatleft {
	float: left
}

img.sermon-icon , img.site-icon {
	border: none;
}

table.podcastall {
	float:left;
	border: 2px solid #FC9328;
	background: #fff0c8 url(icons/podcast_background.png) repeat-x;
	padding: 0.3em;
	font-size: 1em;
}

table.podcastall img.podcasticon, table.podcastcustom img.podcasticon {
	float:left;
	margin-right: 1em;
	border: none;
}

table.podcastall p.podcasttitle a{
	color: #FC9328;
	font-weight: bold;
	font-size:125%;
}

table.podcastall p, table.podcastcustom p {
	margin: 0 !important;
	padding: 0 !important;
}

table.podcastcustom {
	float:right;
	border: 2px solid #b83ee5;
	background: #fce4ff url(icons/podcast_custom_background.png) repeat-x;
	padding: 0.3em;
	font-size: 1em;
}

table.podcastcustom p.podcasttitle  a{
	color: #b83ee5;
	font-weight: bold;
	font-size:125%;
}

td.podcasticon {
	vertical-align: top;
}

div.sermon-browser-results span.preacher {
	font-size: 120%;
}

div.sermon-browser-results span.scripture {
	font-size: 80%;
}

div.sermon-browser-results img.preacher {
	float:right;
	margin-left: 1em;
}

div.sermon-browser-results div.preacher-description {
	margin-top: 0.5em;
}

div.sermon-browser-results div.preacher-description span.about {
	font-weight: bold;
	font-size: 120%;
}

span.chapter-num {
	font-weight: bold;
	font-size: 150%;
}

span.verse-num {
	vertical-align:super;
	line-height: 1em;
	font-size: 65%;
}

div.esv span.small-caps {
	font-variant: small-caps;
}

div.sermon-browser #poweredbysermonbrowser {
	text-align:center;
}
div.sermon-browser-results #poweredbysermonbrowser {
	text-align:right;
}

table.nearby-sermons {
	width: 100%;
	clear:both;
}

table.nearby-sermons td, table.nearby-sermons th {
	text-align: center;
}

table.nearby-sermons .earlier {
	padding-right: 1em;
	text-align: left;
}

table.nearby-sermons .later {
	padding-left: 1em;
	text-align:right;
}

table.nearby-sermons td {
	width: 33%;
	vertical-align: top;
}

ul.sermon-widget {
	list-style-type:none;
	margin:0;
	padding: 0;
}

ul.sermon-widget li {
	list-style-type:none;
	margin:0;
	padding: 0.25em 0;
}

ul.sermon-widget li span.sermon-title {
	font-weight:bold;
}

p.audioplayer_container {
	display:inline !important;
}

div.sb_edit_link {
	display:inline;
}
h2 div.sb_edit_link {
	font-size: 80%;
}
HERE;
   return $css;
}

/* Code below reserved for version 0.40

function sb_default_excerpt_template () {
$multi = <<<HERE
<div class="sermon-browser">
	<table class="sermons">
	[sermons_loop]	
		<tr>
			<td class="sermon-title">[sermon_title]</td>
		</tr>
		<tr>
			<td class="sermon-passage">[first_passage] (Part of the [series_link] series).</td>
		</tr>
		<tr>
			<td class="files">[files_loop][file][/files_loop]</td>
		</tr>
		<tr>
			<td class="embed">[embed_loop][embed][/embed_loop]</td>
		</tr>
		<tr>
			<td class="preacher">Preached by [preacher_link] on [date] ([service_link]).</td>
		</tr>
   	[/sermons_loop]
	</table>
</div>
HERE;
	return $multi;
}

function sb_get_single_form () {
	global $single_form;
	if (!$single_form) $single_form = base64_decode(get_option('sb_sermon_single_form'));
	return $single_form;
}

function sb_create_page($sermon_id, $mass_update = FALSE) {
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('publish_posts'))
		wp_die(__("You do not have the correct permissions to create sermons", $sermon_domain));
	global $wordpressRealPath, $wpdb, $display_method, $sermon_domain, $mdict, $sdict;
	//Create page content (extract)
	$sermons = sb_get_sermons(array('id'=>$sermon_id), array('by'=>'m.date', 'dir'=>'asc'),1, 99999);
	ob_start();
	eval('?>'.strtr(sb_default_excerpt_template(), $mdict));
	$post_excerpt = ob_get_contents();
	//Create page content (post body)
	ob_end_clean();
	$sermon = sb_get_single_sermon($sermon_id);
	ob_start();
	$single_form = get_single_form();
	eval('?>'.strtr(base64_decode(get_option('sb_sermon_single_form')), $sdict));
	$post_content = ob_get_contents();
	ob_end_clean();
	//Check to see if page exists
	$sermon_page = $wpdb->get_results("SELECT sermons.page_id, sermons.series_id FROM {$wpdb->prefix}sb_sermons AS sermons WHERE sermons.id = '{$sermon_id}'");
	$series_id = $sermon_page[0]->series_id;
	$sermon_page = $sermon_page[0]->page_id;
	//Add data to array
	if ($sermon_page!=0) $new_post->ID = $sermon_page;
	$tags = $sermon["Tags"];
	$sermon = $sermon["Sermon"];
	$tags[] = $sermon->series;
	$tags[] = $sermon->preacher; // Think about creating authors for preachers.
	$tags[] = $sermon->service;
	$start = $sermon->start;
	if ($start) foreach ($start as $passage) {
		$tags[] = $passage['book'];
	}
	$end = $sermon->end;
	if ($end) foreach ($end as $passage) {
		$tags[] = $passage['book'];
	}
	$tags = array_filter(array_unique($tags));
	$new_post->post_title = stripslashes($sermon->title);
	$new_post->post_content = $post_content;
	$new_post->post_status = 'publish';
	$new_post->post_page_template = '_wp_page_template';
	$new_post->post_date =  $sermon->date;
	get_currentuserinfo();
	$new_post->post_author = $userdata->ID;
	$new_post->post_type = $display_method;
	$new_post->tags_input = $tags;
	$new_post->post_excerpt = $post_excerpt;
	if ($display_method == "page") {
		//Check to see if series page exists, and create/re-write it
		$series_page=sb_create_series_page($series_id, !$mass_update);
		$new_post->post_parent = $series_page;
	} else {
		$category_id = wp_create_category (__("Sermons", $sermon_domain));
		$new_post->post_category = array($category_id);
	}
	//Write page
	$page_id = wp_insert_post($new_post);
	//Update sermon table with page id
	$table_name = $wpdb->prefix . "sb_sermons";
	$wpdb->query("UPDATE {$table_name} SET page_id = '{$page_id}' WHERE id={$sermon->id}");
	return $page_id;
}

function sb_create_series_page($series_id, $rewrite_series = TRUE) {
	global $wpdb, $wordpressRealPath;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('publish_posts'))
		wp_die(__("You do not have the correct permissions to create series pages", $sermon_domain));
	//Check to see if page exists
	$table_name = $wpdb->prefix . "sb_series";
	$series_page = intval($wpdb->get_var("SELECT page_id FROM {$table_name} WHERE id = '{$series_id}'"));
	//Decide what to do
	if ($series_page!=0 AND $rewrite_series) {
		$new_post->ID = $series_page;
	}
	elseif ($series_page!=0 AND !$rewrite_series) {
		return $series_page;
	}
	//Create page content
	$sermons = sb_get_sermons(array('series'=>$series_id), array('by'=>'m.date', 'dir'=>'asc'),1, 99999);
	ob_start();
	eval('?><div class="sermon-browser-series">
		<ul>
		<?php foreach ($sermons as $sermon): ?><?php $stuff = sb_get_stuff($sermon) ?>	
			<li><a class="sermon-title" href="<?php sb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a> 
			<span class="passage">(<?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo sb_get_books($foo[0], $bar[0]) ?>)</span><br\>
			Preached by <a class="preacher" href="<?php sb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a> on <?php echo date("j F Y", strtotime($sermon->date)) ?> (<a href="<?php sb_print_service_link($sermon) ?>"><?php echo stripslashes($sermon->service) ?></a>).</li>
		<?php endforeach ?>
		</ul>
		<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>
	</div>');
	$result = ob_get_contents();
	ob_end_clean();
	//Add data to array
	$new_post->post_title = $sermon->series;
	$new_post->post_content = $result;
	$new_post->post_status = 'publish';
	$new_post->post_type = 'page';
	$new_post->post_parent = 5;
	$new_post->post_date =  $sermon->date;
	get_currentuserinfo();
	$new_post->post_author = $userdata->ID;
	//Write page
	$page_id = wp_insert_post($new_post);
	//Update series table with page id
	$table_name = $wpdb->prefix . "sb_series";
	$wpdb->query("UPDATE {$table_name} SET page_id = '{$page_id}' WHERE id={$series_id}");
	sb_reorder_pages ($series_id);
	return $page_id;
}

function sb_reorder_pages ($series_id) {
	global $wpdb;
	$page_ids = $wpdb->get_results("SELECT page_id FROM {$wpdb->prefix}sb_sermons WHERE series_id='{$series_id}' ORDER BY date ASC");
	$page_order = 0;
	foreach ($page_ids as $page_id) {
		$wpdb->query("UPDATE {$wpdb->prefix}posts SET menu_order = '{$page_order}' WHERE id = {$page_id->page_id}");
		$page_order++;
	}
}

function sb_rewrite_all_pages ($start_page = 0, $limit = 20, $mode = "") {
	global $wpdb, $url, $display_method, $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_options'))
		wp_die(__("You do not have the correct permissions to rewrite the sermons pages", $sermon_domain));
	define('WP_IMPORTING', true); //Server will get overwhelmed if pinging on every post. Setting WP_IMPORTING to true turns pinging off.
	if ($mode =="") $mode = $_REQUEST['mode'];
	if ($mode =="") $mode = "sermons";
	$start_page = intval($start_page);
	if ($mode != "series") {
		// Create sermons pages
		$sermons = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS id, title FROM {$wpdb->prefix}sb_sermons ORDER BY id ASC LIMIT {$start_page}, {$limit}");
		$record_count = $wpdb->get_var("SELECT FOUND_ROWS()");
		$i = $start_page;
		foreach ($sermons as $sermon) {
			$i++;
			echo '<p style="margin: 0 0 0 17px">'.__('Writing', $sermon_domain).": ".stripslashes($sermon->title)." (".$i."/".$record_count.")</p>";
			flush();
			ob_flush();
			sb_create_page ($sermon->id, TRUE);
		}
		if ($i < $record_count) echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/options.php&rewrite=true&start={$i}&method={$display_method}';</script>";
		$start_page = 0;
	}
	if ($display_method == "page") {
		// Create series pages
		$i = $start_page;
		$limit = round($limit/4);
		if ($limit < 3) $limit = 2;
		$series = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS ss.id, ss.name FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id  LIMIT {$start_page}, {$limit}");
		$record_count = $wpdb->get_var("SELECT FOUND_ROWS()");
		foreach ($series as $one_series) {
			$i++;
			echo '<p style="margin: 0 0 0 17px">'.__('Writing', $sermon_domain).": ".stripslashes($one_series->name)." (".$i."/".$record_count.")</p>";
			flush();
			ob_flush();
			sb_create_series_page ($one_series->id, TRUE);
		}
		if ($i < $record_count) echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/options.php&rewrite=true&start={$i}&method={$display_method}&mode=series';</script>";
	}
	echo "<script>document.location = '$url/wp-admin/admin.php?page=sermon-browser/options.php';</script>";
}

function sb_delete_all_pages() {
	global $wpdb, $sermon_domain;
	//Security check
	if (function_exists('current_user_can')&&!current_user_can('manage_options'))
		wp_die(__("You do not have the correct permissions to delete all the sermon pages", $sermon_domain));
	//Delete all series pages
	$i=0;
	$series = $wpdb->get_results("SELECT ss.page_id FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id");
	echo '<p style="margin: 0 0 0 17px">'.__('Deleting series pages', $sermon_domain).' ';
	foreach ($series as $one_series) {
		wp_delete_post($one_series->page_id);
		$wpdb->query("UPDATE {$wpdb->prefix}sb_series SET page_id = 0 WHERE page_id = {$one_series->page_id}");				
		$i++;
		if (($i % 10)==0) {
			echo ".";
			flush();
			ob_flush();
		}
	}
	echo '</p>';
	//Delete all sermon pages
	$sermons = $wpdb->get_results("SELECT page_id FROM {$wpdb->prefix}sb_sermons");
	echo '<p style="margin: 0 0 0 17px">'.__('Deleting sermon pages', $sermon_domain).' ';
	foreach ($sermons as $sermon) {
		wp_delete_post($sermon->page_id);
		$wpdb->query("UPDATE {$wpdb->prefix}sb_sermons SET page_id = 0 WHERE page_id = {$sermon->page_id}");				
		$i++;
		if (($i % 10)==0) {
			echo ".";
			flush();
			ob_flush();
		}
	}
	echo '</p>';
	//Delete empty tags
	if (function_exists('get_terms')) {
		echo '<p style="margin: 0 0 0 17px">'.__('Deleting empty tags', $sermon_domain).' ';
		$tags = get_terms('post_tag', array('hide_empty' => false));
		foreach ($tags as $tag)
			if ($tag->count == 0) wp_delete_term($tag->term_id, 'post_tag');
		echo '</p>';
	}
}

*/
?>