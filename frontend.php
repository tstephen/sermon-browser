<?php 

// Required files
require_once('dictionary.php'); //Imports template tags
require_once('widget.php');

// Word list for URL building purpose
$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'page', 'sermon_id', 'book', 'stag', 'podcast');

// Hooks & filters
add_action('template_redirect', 'bb_hijack');
add_filter('wp_title', 'sb_page_title');
add_action('wp_head', 'bb_print_header');
add_filter('the_content', 'bb_sermons_filter');
add_action('widgets_init', 'widget_sermon_init');

// Get the URL of the sermons page
function sb_display_url() {
	global $sef, $wpdb, $isMe, $post;
	if (!$sef) {
		if ($isMe) $display_url=get_permalink( $post->ID );
		else {
			$pageid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_content = '[sermons]' AND post_status = 'publish' AND post_date < NOW();");
			$display_url = get_permalink($pageid);
		}
		if (substr($display_url, -1) == '/') $display_url=substr($display_url, 0, -1);
		$sef=$display_url;
	}
	return $sef;
}

//Modify page title
function sb_page_title($title) {
	global $wpdb;
	if ($_GET['sermon_id']) {
		$id = $_GET['sermon_id'];
		$sermon = $wpdb->get_row("SELECT m.id, m.title, m.date, m.start, m.end, p.id as pid, p.name as preacher, s.id as sid, s.name as service, ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id and m.id = $id");
		return $title.' ('.stripslashes($sermon->title).' - '.stripslashes($sermon->preacher).')';
	}
	else
		return $title;
}

//Fix for AudioPlayer v2
if (!function_exists('ap_insert_player_widgets') & function_exists('insert_audio_player')) {
	function ap_insert_player_widgets($params) {
		return insert_audio_player($params);
	}
}

//Download external webpage
function sb_download_page ($page_url) {
	if (function_exists(curl_init)) {
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $page_url);
		curl_setopt ($curl, CURLOPT_TIMEOUT, 2);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_HTTPHEADER, array('Accept-Charset: utf-8;q=0.7,*;q=0.7'));
		$contents = curl_exec ($curl);
		$content_type = curl_getinfo( $curl, CURLINFO_CONTENT_TYPE );
		curl_close ($curl);
		preg_match( '@([\w/+]+)(;\s+charset=(\S+))?@i', $content_type, $matches );
		if (isset($matches[3])) {$charset = $matches[3];}
			else {$charset = 'ISO-8859-1';} //Assume this charset for non-esv texts
		$blog_charset = get_option('blog_charset');
		if (strcasecmp($blog_charset, $charset)<>0) $contents = iconv ($charset, $blog_charset, $contents);
	}
	else
		{
		$handle = @fopen ($page_url, 'r');
		if ($handle) {
			stream_set_blocking($handle, TRUE );
			stream_set_timeout($handle, 2);
			$info = socket_get_status($handle);
			while (!feof($handle) && !$info['timed_out']) {
				$contents .= fread($handle, 8192);
				$info = socket_get_status($handle);
			}
		fclose($handle);
		}
	}
	return $contents;
}

//Tidy Bible reference
function sb_tidy_reference ($start, $end) {
	$r1 = $start['book'];
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	$r4 = $end['book'];
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	if (empty($start['book'])) {
		return '';
	}
	if ($start['book'] == $end['book']) {
		if ($start['chapter'] == $end['chapter']) {
			$reference = "$r1 $r2:$r3-$r6";
		}
		else $reference = "$r1 $r2:$r3-$r5:$r6";
	}	
	else $reference =  "$r1 $r2:$r3 - $r4 $r5:$r6";
	return $reference;
}

//Add ESV text
function add_esv_text ($start, $end) {
	// If you are experiencing errors, you should sign up for an ESV API key, and insert the name of your key in place of the letters IP in the URL below (.e.g. ...passageQuery?key=YOURAPIKEY&passage=...)
	$esv_url = 'http://www.esvapi.org/v2/rest/passageQuery?key=IP&passage='.urlencode(sb_tidy_reference ($start, $end)).'&include-headings=false&include-footnotes=false';
	return sb_download_page ($esv_url);
}

//Add Bible text to single sermon page
function add_bible_text ($start, $end, $version) {
	if ($version == "esv") {
		return add_esv_text ($start, $end);
	}
	else {
		global $books;
		$r1 = array_search($start['book'], $books)+1;
		$r2 = $start['chapter'];
		$r3 = $start['verse'];
		$r4 = array_search($end['book'], $books)+1;
		$r5 = $end['chapter'];
		$r6 = $end['verse'];
		if (empty($start['book'])) {
			return '';
		}
		$ls_url = 'http://api.seek-first.com/v1/BibleSearch.php?type=lookup&appid=seekfirst&startbooknum='.$r1.'&startchapter='.$r2.'&startverse='.$r3.'&endbooknum='.$r4.'&endchapter='.$r5.'&endverse='.$r6.'&version='.$version;
		$content = sb_download_page ($ls_url);
		//Clean up and re-format data
		if ($content != '') {
			$r1++;
			for (; $r4>=$r1; $r1++) {
				$searchstring = '<BookNum>'.$r1.'</BookNum>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, '</p><p><span class="chapter-num">'.$books[$r1-1].' 1:1</span>', $findpos, strlen($searchstring));
				$searchstring = '<Verse>1</Verse>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, '', $findpos, strlen($searchstring));
			}
			if ($r2 > 1) {$r2++;} else {$closepara = '</p><p>';}
			for (; $r5>=$r2; $r2++) {
				$searchstring = '<Chapter>'.$r2.'</Chapter>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, $closepara.'<span class="chapter-num">'.$r2.':1</span>', $findpos, strlen($searchstring));
				$searchstring = '<Verse>1</Verse>';
				$findpos = strpos($content, $searchstring);
				$content=substr_replace($content, '', $findpos, strlen($searchstring));
			}
			$patterns = array (
				'/<!--(.)*?-->/',
				'/<.?(Result|Results)>/',
				'/<ShortBook>(.*)<\/ShortBook>/',
				'/<Book>(.*)<\/Book>/',
				'/<Copyright>(.*)<\/Copyright>/',
				'/<VersionName>(.*)<\/VersionName>/',
				'/<Title>(.*)<\/Title>/',
				'/<TotalResults>(.*)<\/TotalResults>/',
				'/<BookNum>(.*)<\/BookNum>/',
				'/<Chapter>(.*)<\/Chapter>/',
				'/<Verse>/',
				'/<\/Verse>\n/',
				'/<Text>/',
				'/<\/Text>\n/',
				'/\n/',
			);
			$replace = array (
				'', '', '', '', '', '', '', '', '', '', '<span class="verse-num">', ' </span>', '', '', ' '
			);
			$content = preg_replace ($patterns, $replace, $content);
			while (strpos($content, '  ')!==FALSE) {
				$content = str_replace('  ', ' ', $content);
			}
			return '<div class="'.$version.'"><h2>'.sb_tidy_reference ($start, $end). '</h2><p>'.$content.' (<a href="http://biblepro.bibleocean.com/dox/default.aspx">'. strtoupper($version). '</a>)</p></div>';
		}
	}
}

//Print unstyled bible passage
function print_bible_passage ($start, $end) {
	$r1 = $start['book'];
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	$r4 = $end['book'];
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	if (empty($start['book'])) {
		return '';
	}
	if ($start['book'] == $end['book']) {
		if ($start['chapter'] == $end['chapter']) {
			$reference = "$r1 $r2:$r3-$r6";
		}
		else $reference = "$r1 $r2:$r3-$r5:$r6";
	}	
	else $reference =  "$r1 $r2:$r3 - $r4 $r5:$r6";
	echo "<p class='bible-passage'>".$reference."</p>";
}

// Display podcast
function bb_hijack() {
	global $wordpressRealPath;
	if (isset($_REQUEST['podcast'])) {
		$sermons = bb_get_sermons(array(
			'title' => $_REQUEST['title'],
			'preacher' => $_REQUEST['preacher'],
			'date' => $_REQUEST['date'],
			'enddate' => $_REQUEST['enddate'],
			'series' => $_REQUEST['series'],
			'service' => $_REQUEST['service'],
			'book' => $_REQUEST['book'],
			'tag' => $_REQUEST['stag'],
		),
		array(
			'by' => $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date',
			'dir' => $_REQUEST['dir'],
		),
		$_REQUEST['page'] ? $_REQUEST['page'] : 1, 
		1000000			
		);
		header('Content-Type: application/rss+xml');
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		include($wordpressRealPath.'/wp-content/plugins/sermonbrowser/podcast.php');
		die();
	}
}

// main entry
function bb_sermons_filter($content) {
	global $wpdb, $clr;
	global $wordpressRealPath, $isMe;
	if (!strstr($content, '[sermons]')) { 
	    $isMe = false; return $content;
    } else {
        $isMe = true;
    }
	ob_start();
	
	if ($_GET['sermon_id']) {
		$clr = true;
		$sermon = bb_get_single_sermon((int) $_GET['sermon_id']);
		include($wordpressRealPath.'/wp-content/plugins/sermonbrowser/single.php');
	} else {
		$clr = false;
		$sermons = bb_get_sermons(array(
			'title' => $_REQUEST['title'],
			'preacher' => $_REQUEST['preacher'],
			'date' => $_REQUEST['date'],
			'enddate' => $_REQUEST['enddate'],
			'series' => $_REQUEST['series'],
			'service' => $_REQUEST['service'],
			'book' => $_REQUEST['book'],
			'tag' => $_REQUEST['stag'],
		),
		array(
			'by' => $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date',
			'dir' => $_REQUEST['dir'],
		),
		$_REQUEST['page'] ? $_REQUEST['page'] : 1			
		);
		include($wordpressRealPath.'/wp-content/plugins/sermonbrowser/multi.php');		
	}			
	$content = str_replace('[sermons]', ob_get_contents(), $content);
	
	ob_end_clean();		
	
	return $content;
}

function bb_build_url($arr, $clear = false) {
	global $wl, $post, $sef, $wpdb;
	$sef = sb_display_url();
	$foo = array_merge((array) $_GET, (array) $_POST, $arr);
	foreach ($foo as $k => $v) {
		if (!$clear || in_array($k, array_keys($arr)) || !in_array($k, $wl)) {
			$bar[] = "$k=$v";
		}
	}
	if ($sef != "") return $sef.'?' . implode('&', $bar);
	return get_bloginfo('url') . '?' . implode('&', $bar);
}

function bb_print_header() {
	global $sermon_domain, $sermompage;
	$url = get_bloginfo('wpurl');
?>
	<link rel="alternate" type="application/rss+xml" title="<?php _e('Sermon podcast', $sermon_domain) ?>" href="<?php echo get_option('sb_podcast') ?>" />
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datepicker.css" type="text/css"/>
	<link rel="stylesheet" href="<?php echo $url ?>/wp-content/plugins/sermonbrowser/style.css" type="text/css"/>
	<script type="text/javascript" src="<?php echo $url ?>/wp-includes/js/jquery/jquery.js"></script>
	<script type="text/javascript" src="<?php echo $url ?>/wp-content/plugins/sermonbrowser/datePicker.js"></script>
<?php
}

// pretty books
function bb_get_books($start, $end) {
	$r1 = '<a href="'.bb_get_book_link($start['book']).'">'.$start['book'].'</a>';
	$r2 = $start['chapter'];
	$r3 = $start['verse'];
	
	$r4 = '<a href="'.bb_get_book_link($end['book']).'">'.$end['book'].'</a>';
	$r5 = $end['chapter'];
	$r6 = $end['verse'];
	
	if (empty($start['book'])) {
		return '';
	}
	
	if ($start['book'] == $end['book']) {
		if ($start['chapter'] == $end['chapter']) {
		    if($start['verse'] == $end['verse']){
		        return "$r1 $r2:$r3";
		    }
			return "$r1 $r2:$r3-$r6";
		}
		return "$r1 $r2:$r3-$r5:$r6";
	}	
	return "$r1 $r2:$r3 - $r4 $r5:$r6";
}

function bb_podcast_url() {
	return str_replace(' ', '%20', bb_build_url(array('podcast' => 1)));
}

function bb_print_first_mp3($sermon) {
	$stuff = bb_get_stuff($sermon);
	foreach ((array) $stuff['Files'] as $file) {
		$ext = substr($file, strrpos($file, '.') + 1);
		if (strtolower($ext) == 'mp3') {
			echo str_replace(' ', '%20', get_option('sb_sermon_upload_url').$file);
			break;
		}
	}
}

function bb_print_sermon_link($sermon) {
	echo bb_build_url(array('sermon_id' => $sermon->id), true);
}

function bb_print_preacher_link($sermon) {
	global $clr;
	echo bb_build_url(array('preacher' => $sermon->pid), $clr);
}

function bb_print_series_link($sermon) {
	global $clr;	
	echo bb_build_url(array('series' => $sermon->ssid), $clr);
}

function bb_print_service_link($sermon) {
	global $clr;
	echo bb_build_url(array('service' => $sermon->sid), $clr);
}

function bb_get_book_link($book_name) {
	global $clr;
	return bb_build_url(array('book' => $book_name), $clr);
}

function bb_get_tag_link($tag) {
	global $clr;
	return bb_build_url(array('stag' => $tag), $clr);
}

function bb_print_tags($tags) {
	foreach ((array) $tags as $tag) {
		$out[] = '<a href="'.bb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	$tags = implode(', ', (array) $out);
	echo $tags;
}

function bb_print_tag_clouds() {
	global $wpdb;
	$rawtags = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_tags as t RIGHT JOIN {$wpdb->prefix}sb_sermons_tags as st ON t.id = st.tag_id");
	foreach ($rawtags as $tag) {
		$cnt[$tag->name]++;
	}
	
	$minfont = 10;
	$maxfont = 26;
	$fontrange = $maxfont - $minfont;
	$maxcnt = 0;
	$mincnt = 1000000;
	foreach ($cnt as $cur) {
		if ($cur > $maxcnt) $maxcnt = $cur;
		if ($cur < $mincnt) $minct = $cur; 
	}
	$cntrange = $maxcnt + 1 - $mincnt;
	
	$minlog = log($mincnt);
	$maxlog = log($maxcnt);
	$logrange = $maxlog == $minlog ? 1 : $maxlog - $minlog;
	arsort($cnt);
	
	foreach ($cnt as $tag => $count) {
		$size = $minfont + $fontrange * (log($count) - $minlog) / $logrange;
		$out[] = '<a style="font-size:'.(int) $size.'px" href="'.bb_get_tag_link($tag).'">'.$tag.'</a>';
	}
	echo implode(' ', $out);
}

function bb_print_next_page_link($limit = 15) {
	global $sermon_domain;
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current < bb_page_count($limit)) {
		$url = bb_build_url(array('page' => ++$current));
		echo '<a href="'.$url.'">'.__('Next page &raquo;', $sermon_domain).'</a>';
	}	
}

function bb_print_prev_page_link($limit = 15) {
	global $sermon_domain;
	$current = $_REQUEST['page'] ? (int) $_REQUEST['page'] : 1;
	if ($current > 1) {
		$url = bb_build_url(array('page' => --$current));
		echo '<a href="'.$url.'">'.__('&laquo; Previous page', $sermon_domain).'</a>';
	}	
}

function bb_print_file($name) {
	//global $filetypes, $default_file_icon;
	//$icon_url = get_bloginfo('wpurl').'/wp-content/plugins/sermonbrowser/icons/';
	$file_url = get_option('sb_sermon_upload_url').$name;
	/*$ext = substr($name, strrpos($name, '.') + 1);
	if (strtolower($ext) == 'mp3' && function_exists('ap_insert_player_widgets')) {
		echo '<div class="audioplayer">'.ap_insert_player_widgets('[audio:'.$file_url.$name.']').'</div>';
	} elseif (isset($filetypes[$ext]['icon'])) {
	    echo '<a href="'.$file_url.$name.'"><img class="sermon-icon" alt="'.$name.'" title="'.$name.'" src="'.$icon_url.$filetypes[$ext]['icon'].'"></a>';
	} else {
	    echo '<a href="'.$file_url.$name.'"><img class="sermon-icon" alt="'.$name.'" title="'.$name.'" src="'.$icon_url.$default_file_icon.'"></a>';
	}*/
	bb_print_url($file_url);
}

function bb_print_iso_date($sermon) {
	echo date('d M Y H:i:s O', strtotime($sermon->date.' '.$sermon->time));
}

function bb_print_url($url) {
	global $siteicons, $default_site_icon ,$filetypes;
	$icon_url = get_bloginfo('wpurl').'/wp-content/plugins/sermonbrowser/icons/';
	$uicon = $default_site_icon;
	foreach ($siteicons as $site => $icon) {
		if (strpos($url, $site) !== false) {
			$uicon = $icon;
			break;
		}
	}
	$pathinfo = pathinfo($url);
	$ext = $pathinfo['extension'];
	$uicon = isset($filetypes[$ext]['icon']) ? $filetypes[$ext]['icon'] : $uicon;
	if (strtolower($ext) == 'mp3' && function_exists('ap_insert_player_widgets')) {
	    echo '<div class="audioplayer">'.ap_insert_player_widgets('[audio:'.$url.']').'</div>';
	} else {
	    echo '<a href="'.$url.'"><img class="site-icon" alt="'.$url.'" title="'.$url.'" src="'.$icon_url.$uicon.'"></a>';
	}
    
}

function bb_print_code($code) {
	echo base64_decode($code);
}

function bb_print_preacher_description($sermon) {
	global $sermon_domain;
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . __('About', $sermon_domain).' '.stripslashes($sermon->preacher).': </span>';
		echo "<span class='description'>".stripslashes($sermon->preacher_description)."</span></div>";
	}
}

function bb_print_preacher_image($sermon) {
	if ($sermon->image) 
		echo "<img alt='".stripslashes($sermon->preacher)."' class='preacher' src='".get_bloginfo("wpurl").get_option("sb_sermon_upload_dir")."images/".$sermon->image."'>";
}

function bb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date > '$sermon->date' AND id <> $sermon->id ORDER BY date asc");
	if (!$next) return;
	echo '<a href="';
	bb_print_sermon_link($next);
	echo '">'.stripslashes($next->title).' &raquo;</a>';
}

function bb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date < '$sermon->date' AND id <> $sermon->id ORDER BY date desc");
	if (!$prev) return;
	echo '<a href="';
	bb_print_sermon_link($prev);
	echo '">&laquo; '.stripslashes($prev->title).'</a>';
}

function bb_print_sameday_sermon_link($sermon) {
	global $wpdb, $sermon_domain;
	$same = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE date = '$sermon->date' AND id <> $sermon->id");
	if (!$same) {
		_e('None', $sermon_domain);
		return;
	}
	foreach ($same as $cur) {
		echo '<a href="';
		bb_print_sermon_link($cur);
		echo '">'.stripslashes($cur->title).'</a>';
	}
}

function bb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row("SELECT m.id, m.title, m.date, m.start, m.end, p.id as pid, p.name as preacher, p.image as image, p.description as preacher_description, s.id as sid, s.name as service, ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s, {$wpdb->prefix}sb_series as ss where m.preacher_id = p.id and m.service_id = s.id and m.series_id = ss.id and m.id = $id");
	$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $id ORDER BY id desc");	
	$rawtags = $wpdb->get_results("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = $sermon->id ORDER BY t.name asc");
	foreach ($rawtags as $tag) {
		$tags[] = $tag->name;
	}
	foreach ($stuff as $cur) {
		${$cur->type}[] = $cur->name;
	}
	$sermon->start = unserialize($sermon->start);
	$sermon->end = unserialize($sermon->end);
	return array(		
		'Sermon' => $sermon,
		'Files' => $file,
		'URLs' => $url,
		'Code' => $code,
		'Tags' => $tags,
	);
}

function bb_print_sermons_count() {
	echo bb_count_sermons(array(
		'title' => $_REQUEST['title'],
		'preacher' => $_REQUEST['preacher'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'series' => $_REQUEST['series'],
		'service' => $_REQUEST['service'],
		'book' => $_REQUEST['book'],
		'tag' => $_REQUEST['stag'],
	));
}

function bb_page_count($limit = 15) {
	$total = bb_count_sermons(array(
		'title' => $_REQUEST['title'],
		'preacher' => $_REQUEST['preacher'],
		'date' => $_REQUEST['date'],
		'enddate' => $_REQUEST['enddate'],
		'series' => $_REQUEST['series'],
		'service' => $_REQUEST['service'],	
		'book' => $_REQUEST['book'],		
		'tag' => $_REQUEST['stag'],		
	));
	return ceil($total / $limit);
}

function bb_count_sermons($filter) {
	global $wpdb, $sermoncount;
	if (!$sermoncount) {
		$default_filter = array(
			'title' => '',
			'preacher' => 0,
			'date' => '',
			'enddate' => '',
			'series' => 0,
			'service' => 0,
			'book' => '',
			'tag' => '',
		);	
		$filter = array_merge($default_filter, $filter);	
		if ($filter['title'] != '') {
			$cond = "AND (m.title LIKE '%" . mysql_real_escape_string($filter['title']) . "%' OR m.description LIKE '%" . mysql_real_escape_string($filter['title']). "%' OR t.name LIKE '%" . mysql_real_escape_string($filter['title']) . "%') ";
		}
		if ($filter['preacher'] != 0) {
			$cond .= 'AND m.preacher_id = ' . (int) $filter['preacher'] . ' ';
		}
		if ($filter['date'] != '') {
			$cond .= 'AND m.date >= "' . mysql_real_escape_string($filter['date']) . '" ';
		}
		if ($filter['enddate'] != '') {
			$cond .= 'AND m.date <= "' . mysql_real_escape_string($filter['enddate']) . '" ';
		}
		if ($filter['series'] != 0) {
			$cond .= 'AND m.series_id = ' . (int) $filter['series'] . ' ';
		}
		if ($filter['service'] != 0) {
			$cond .= 'AND m.service_id = ' . (int) $filter['service'] . ' ';
		}		
		if ($filter['book'] != '') {
			$cond .= 'AND bs.book_name = "' . mysql_real_escape_string($filter['book']) . '" ';
		} else {
			$bs = "AND bs.order = 0 AND bs.type= 'start' ";
		}
		if ($filter['tag'] != '') {
		$cond .= "AND t.name LIKE '%" . mysql_real_escape_string($filter['tag']) . "%' ";
		}
		$query = "SELECT COUNT(*) 
			FROM {$wpdb->prefix}sb_sermons as m 
			LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id 
			LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id 
			LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id 
			LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id $bs 
			LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name 
			LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id 
			LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id 
			WHERE 1 = 1 $cond ";
		$sermoncount = $wpdb->get_var($query);
	}
	return $sermoncount;
}

function bb_get_sermons($filter, $order, $page = 1, $limit = 15) {
	global $wpdb;
	$default_filter = array(
		'title' => '',
		'preacher' => 0,
		'date' => '',
		'enddate' => '',
		'series' => 0,
		'service' => 0,
		'book' => '',
		'tag' => '',
	);
	$default_order = array(
		'by' => 'm.date',
		'dir' => 'desc',
	);
	$bs = '';
	$filter = array_merge($default_filter, $filter);
	$order = array_merge($default_order, $order);
	
	$page = (int) $page;
	if ($filter['title'] != '') {
		$cond = "AND (m.title LIKE '%" . mysql_real_escape_string($filter['title']) . "%' OR m.description LIKE '%" . mysql_real_escape_string($filter['title']). "%' OR t.name LIKE '%" . mysql_real_escape_string($filter['title']) . "%') ";
	}
	if ($filter['preacher'] != 0) {
		$cond .= 'AND m.preacher_id = ' . (int) $filter['preacher'] . ' ';
	}
	if ($filter['date'] != '') {
		$cond .= 'AND m.date >= "' . mysql_real_escape_string($filter['date']) . '" ';
	}
	if ($filter['enddate'] != '') {
		$cond .= 'AND m.date <= "' . mysql_real_escape_string($filter['enddate']) . '" ';
	}
	if ($filter['series'] != 0) {
		$cond .= 'AND m.series_id = ' . (int) $filter['series'] . ' ';
	}
	if ($filter['service'] != 0) {
		$cond .= 'AND m.service_id = ' . (int) $filter['service'] . ' ';
	}	
	if ($filter['book'] != '') {
		$cond .= 'AND bs.book_name = "' . mysql_real_escape_string($filter['book']) . '" ';
	} else {
		$bs = "AND bs.order = 0 AND bs.type= 'start' ";
	}
	if ($filter['tag'] != '') {
		$cond .= "AND t.name LIKE '%" . mysql_real_escape_string($filter['tag']) . "%' ";
	}
	$offset = $limit * ($page - 1);
	if ($order['by'] == 'm.date' ) {
	    if(!isset($order['dir'])) $order['dir'] = 'desc';
	    $order['by'] = 'm.date '.$order['dir'].', s.time';
	}
	if ($order['by'] == 'b.id' ) {
	    $order['by'] = 'b.id '.$order['dir'].', bs.chapter '.$order['dir'].', bs.verse';
	}
	$query = "SELECT DISTINCT m.id, m.title, m.description, m.date, m.time, m.start, m.end, p.id as pid, p.name as preacher, p.description as preacher_description, p.image, s.id as sid, s.name as service, ss.id as ssid, ss.name as series 
		FROM {$wpdb->prefix}sb_sermons as m 
		LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id 
		LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id 
		LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id 
		LEFT JOIN {$wpdb->prefix}sb_books_sermons as bs ON bs.sermon_id = m.id $bs 
		LEFT JOIN {$wpdb->prefix}sb_books as b ON bs.book_name = b.name 
		LEFT JOIN {$wpdb->prefix}sb_sermons_tags as st ON st.sermon_id = m.id 
		LEFT JOIN {$wpdb->prefix}sb_tags as t ON t.id = st.tag_id 
		WHERE 1 = 1 $cond ORDER BY ". $order['by'] . " " . $order['dir'] . " LIMIT " . $offset . ", " . $limit;
		
	return $wpdb->get_results($query);
}

function bb_get_stuff($sermon) {
	global $wpdb;
	$stuff = $wpdb->get_results("SELECT f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = $sermon->id ORDER BY id desc");
	foreach ($stuff as $cur) {
		${$cur->type}[] = $cur->name;
	}
	return array(		
		'Files' => $file,
		'URLs' => $url,
		'Code' => $code,
	);
}

function bb_print_filters() {
	global $wpdb, $sermon_domain, $books;
	
	$url = get_bloginfo('wpurl');
	
	$preachers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_preachers ORDER BY id;");	
	$series = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_series ORDER BY id;");
	$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sb_services ORDER BY id;");
	
	$sb = array(		
		'Title' => 'm.title',
		'Preacher' => 'preacher',
		'Date' => 'm.date',
		'Passage' => 'b.id',
	);
	
	$di = array(
		'Ascending' => 'asc',
		'Descending' => 'desc',
	);
	
	$csb = $_REQUEST['sortby'] ? $_REQUEST['sortby'] : 'm.date';
	$cd = $_REQUEST['dir'] ? $_REQUEST['dir'] : 'desc';	
?>	
	<form method="post" id="sermon-filter">
		<div style="clear:both">
			<table class="sermonbrowser">
				<tr>
					<td class="fieldname"><?php _e('Preacher', $sermon_domain) ?></td>
					<td class="field"><select name="preacher" id="preacher">
							<option value="0" <?php echo $_REQUEST['preacher'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($preachers as $preacher): ?>
							<option value="<?php echo $preacher->id ?>" <?php echo $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo $preacher->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
					<td class="fieldname rightcolumn"><?php _e('Services', $sermon_domain) ?></td>
					<td class="field"><select name="service" id="service">
							<option value="0" <?php echo $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($services as $service): ?>
							<option value="<?php echo $service->id ?>" <?php echo $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo $service->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldname"><?php _e('Book', $sermon_domain) ?></td>
					<td class="field"><select name="book">
							<option value=""><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($books as $book): ?>
							<option value="<?php echo $book ?>" <?php echo $_REQUEST['book'] == $book ? 'selected=selected' : '' ?>><?php echo $book ?></option>
							<?php endforeach ?>
						</select>
					</td>
					<td class="fieldname rightcolumn"><?php _e('Series', $sermon_domain) ?></td>
					<td class="field"><select name="series" id="series">
							<option value="0" <?php echo $_REQUEST['series'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', $sermon_domain) ?></option>
							<?php foreach ($series as $item): ?>
							<option value="<?php echo $item->id ?>" <?php echo $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo $item->name ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td class="fieldname"><?php _e('Start date', $sermon_domain) ?></td>
					<td class="field"><input type="text" name="date" id="date" value="<?php echo mysql_real_escape_string($_REQUEST['date']) ?>" /></td>
					<td class="fieldname rightcolumn"><?php _e('End date', $sermon_domain) ?></td>
					<td class="field"><input type="text" name="enddate" id="enddate" value="<?php echo mysql_real_escape_string($_REQUEST['enddate']) ?>" /></td>
				</tr>
				<tr>
					<td class="fieldname"><?php _e('Keywords', $sermon_domain) ?></td>
					<td class="field" colspan="3"><input style="width: 98.5%" type="text" id="title" name="title" value="<?php echo mysql_real_escape_string($_REQUEST['title']) ?>" /></td>
				</tr>
				<tr>
					<td class="fieldname"><?php _e('Sort by', $sermon_domain) ?></td>
					<td class="field"><select name="sortby" id="sortby">
							<?php foreach ($sb as $k => $v): ?>
							<option value="<?php echo $v ?>" <?php echo $csb == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
							<?php endforeach ?>
						</select>
					</td>
					<td class="fieldname rightcolumn"><?php _e('Direction', $sermon_domain) ?></td>
					<td class="field"><select name="dir" id="dir">
							<?php foreach ($di as $k => $v): ?>
							<option value="<?php echo $v ?>" <?php echo $cd == $v ? 'selected="selected"' : '' ?>><?php _e($k, $sermon_domain) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td colspan="3">&nbsp;</td>
					<td class="field"><input type="submit" class="filter" value="<?php _e('Filter &raquo;', $sermon_domain) ?>">			</td>
				</tr>
			</table>
			<input type="hidden" name="page" value="1">
		</div>
	</form>
	<script type="text/javascript">
		jQuery.datePicker.setDateFormat('ymd','-');
		jQuery('#date').datePicker({startDate:'01/01/1970'});
		jQuery('#enddate').datePicker({startDate:'01/01/1970'});
	</script>
<?php
}

?>