<div class="sermon-browser-results">
	<h2><?php echo stripslashes($sermon["Sermon"]->title) ?> <span class="scripture">(<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): ?><?php echo bb_get_books($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]) ?> <?php endfor ?>)</span></h2>
	<span class="preacher"><a href="<?php bb_print_preacher_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->preacher) ?></a>, <?php echo date("j F Y", strtotime($sermon["Sermon"]->date)) ?></span><br />
	Part of the <a href="<?php bb_print_series_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->series) ?></a> series, preached at a <a href="<?php bb_print_service_link($sermon["Sermon"]) ?>"><?php echo stripslashes($sermon["Sermon"]->service) ?></a> service<br />
	Tags: <?php bb_print_tags($sermon["Tags"]) ?><br />
	<?php foreach ((array) $sermon["Files"] as $file): ?>	
		<?php bb_print_file($file) ?>  
	<?php endforeach ?>
	<?php foreach ((array) $sermon["URLs"] as $url): ?>
		<?php bb_print_url($url) ?>  
	<?php endforeach ?>
	<?php foreach ((array) $sermon["Code"] as $code): ?>
		<br /><?php bb_print_code($code) ?><br />
	<?php endforeach ?>
	<br />	
	<table class="nearby-sermons">
		<tr>
			<th class="earlier">Earlier:</th>
			<th>Same day:</th>
			<th class="later">Later:</th>
		</tr>
		<tr>
			<td class="earlier"><?php bb_print_prev_sermon_link($sermon["Sermon"]) ?></td>
			<td><?php bb_print_sameday_sermon_link($sermon["Sermon"]) ?></td>
			<td class="later"><?php bb_print_next_sermon_link($sermon["Sermon"]) ?></td>
		</tr>
	</table>
	<?php for ($i = 0; $i < count($sermon["Sermon"]->start); $i++): echo add_esv_text ($sermon["Sermon"]->start[$i], $sermon["Sermon"]->end[$i]); endfor ?>
   	<div id="poweredbysermonbrowser">Powered by <a href="http://www.4-14.org.uk/sermon-browser">Sermon Browser</a></div>
</div>