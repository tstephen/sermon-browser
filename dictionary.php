<?php 

$mdict = array(
	'[filters_form]' => '<?php sb_print_filters() ?>',
	'[tag_cloud]' => '<?php sb_print_tag_clouds() ?>',
	'[sermons_count]' => '<?php echo $record_count ?>',
	'[sermons_loop]' => '<?php foreach ($sermons as $sermon): ?><?php $stuff = sb_get_stuff($sermon) ?>',
	'[/sermons_loop]' => '<?php endforeach ?>',
	'[sermon_title]' => '<a href="<?php sb_print_sermon_link($sermon) ?>"><?php echo stripslashes($sermon->title) ?></a>',
	'[preacher_link]' => '<a href="<?php sb_print_preacher_link($sermon) ?>"><?php echo stripslashes($sermon->preacher) ?></a>',
	'[series_link]' => '<a href="<?php sb_print_series_link($sermon) ?>"><?php echo stripslashes($sermon->series) ?></a>',
	'[service_link]' => '<a href="<?php sb_print_service_link($sermon) ?>"><?php echo stripslashes($sermon->service) ?></a>',	
	'[date]' => '<?php echo date("j F Y", strtotime($sermon->date)) ?>',
	'[first_passage]' => '<?php $foo = unserialize($sermon->start); $bar = unserialize($sermon->end); echo sb_get_books($foo[0], $bar[0]) ?>',
	'[files_loop]' => '<?php foreach ((array) $stuff["Files"] as $file): ?>',
	'[/files_loop]' => '<?php endforeach ?>',
	'[file]' => '<?php sb_print_url($file) ?>',
	'[file_with_download]' => '<?php sb_print_url_link($file) ?>',
	'[embed_loop]' => '<?php foreach ((array) $stuff["Code"] as $code): ?>',
	'[/embed_loop]' => '<?php endforeach ?>',
	'[embed]' => '<?php sb_print_code($code) ?>',
	'[next_page]' => '<?php sb_print_next_page_link() ?>',
	'[previous_page]' => '<?php sb_print_prev_page_link() ?>',	
	'[podcast_for_search]' => '<?php echo sb_podcast_url() ?>',
	'[podcast]' => '<?php echo get_option("sb_podcast") ?>',
	'[itunes_podcast]' => '<?php echo str_replace("http://", "itpc://", get_option("sb_podcast")) ?>',
	'[itunes_podcast_for_search]' => '<?php echo str_replace("http://", "itpc://", sb_podcast_url()) ?>',
	'[podcasticon]' => '<img alt="Subscribe to full podcast" title="Subscribe to full podcast" class="podcasticon" src="<?php echo get_bloginfo("wpurl") ?>/wp-content/plugins/sermon-browser/icons/podcast.png"/>',
	'[podcasticon_for_search]' => '<img alt="Subscribe to custom podcast" title="Subscribe to custom podcast" class="podcasticon" src="<?php echo get_bloginfo("wpurl") ?>/wp-content/plugins/sermon-browser/icons/podcast_custom.png"/>',
	'[editlink]' => '<?php sb_edit_link($sermon->id) ?>',
	'[creditlink]' => '<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>',
);

$sdict = array(
	'[sermon_title]' => '<?php echo stripslashes($sermon["Sermon"]->title) ?>',
	'[sermon_description]' => '<?php echo stripslashes($sermon["Sermon"]->description) ?>',
	'[preacher_link]' => '<a href="<?php sb_print_preacher_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->preacher) ?></a>',
	'[preacher_description]' => '<?php sb_print_preacher_description($sermon["Sermon"]) ?>',
	'[preacher_image]' => '<?php sb_print_preacher_image($sermon["Sermon"]) ?>',
	'[series_link]' => '<a href="<?php sb_print_series_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->series) ?></a>',
	'[service_link]' => '<a href="<?php sb_print_service_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->service) ?></a>',
	'[date]' => '<?php echo date("j F Y", strtotime($sermon["Sermon"]->date)) ?>',
	'[passages_loop]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): ?>',
	'[/passages_loop]' => '<?php endfor ?>',
	'[passage]' => '<?php echo sb_get_books($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]) ?>',
	'[files_loop]' => '<?php foreach ((array) $sermon["Files"] as $file): ?>',
	'[/files_loop]' => '<?php endforeach ?>',
	'[file]' => '<?php sb_print_url($file) ?>',
	'[file_with_download]' => '<?php sb_print_url_link($file) ?>',
	'[embed_loop]' => '<?php foreach ((array) $sermon["Code"] as $code): ?>',
	'[/embed_loop]' => '<?php endforeach ?>',
	'[embed]' => '<?php sb_print_code($code) ?>',
	'[next_sermon]' => '<?php sb_print_next_sermon_link($sermon["Sermon"]) ?>',
	'[prev_sermon]' => '<?php sb_print_prev_sermon_link($sermon["Sermon"]) ?>',
	'[sameday_sermon]' => '<?php sb_print_sameday_sermon_link($sermon["Sermon"]) ?>',
	'[tags]' => '<?php sb_print_tags($sermon["Tags"]) ?>',
	'[esvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "esv"); endfor ?>',
	'[kjvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "kjv"); endfor ?>',
	'[asvtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "asv"); endfor ?>',
	'[ylttext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "asv"); endfor ?>',
	'[webtext]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo sb_add_bible_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i], "asv"); endfor ?>',
	'[biblepassage]' => '<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): sb_print_bible_passage ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]); endfor ?>',
	'[editlink]' => '<?php sb_edit_link($_GET["sermon_id"]) ?>',
	'[creditlink]' => '<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>',
);


?>