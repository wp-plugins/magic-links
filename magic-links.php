<?php
/*
Plugin Name: Magic Links
Plugin URI: http://raychow.info/2010/magic-links.html
Description: Show links in different styles.
Version: 1.0
Author: Ray Chow
Author URI: http://raychow.info/
*/

load_textdomain('magiclinks', dirname(__FILE__) . '/lang/' . get_locale() . '.mo');

function ml_get_options() {
	$default_options = array (
		'category_id' => '', 
		'time_span' => 30, 
		'order_by' => 'id', 
		'order' => 'ASC', 
		'show_invisible' => 0, 
		'show_no_comment' => 1, 
		'max_display' => 0, 
		'output_style' => 0, 
		'link_font_size' => 0, 
		'min_font_size' => 8, 
		'max_font_size' => 20, 
		'link_color' => 0, 
		'min_color' => '999999', 
		'max_color' => '000000', 
		'link_separator' => '&nbsp;-&nbsp;', 
		'target_blank' => 0, 
		'nofollow' => 0, 
		'schedule' => 'hourly');
	return get_option('ml_options', $default_options);
}

function ml_update_content(){
	global $wpdb;		
	$options = ml_get_options();
	if ($options['time_span']) {
		$cur_time_span = date('Y-m-d H:i:s', strtotime("- {$options['time_span']} days"));
		$cur_time_span_sql = "comment_date > '$cur_time_span' AND";
	}
	$links = get_bookmarks(array(
		'orderby' => (($options['order_by'] == 'comments_number') ? 'id' : $options['order_by']), 
		'order' => $options['order'], 
		'limit' => (($options['max_display'] == 0) ? -1 : $options['max_display']), 
		'category' => $options['category_id'], 
		'category_name' => null, 
		'hide_invisible' => !$options['show_invisible']));
	$first = 1;
	foreach ($links as $link) {
		preg_match('@^(?:http://|https://)(?:www.)?([^/]+)@i', $link->link_url, $link_matches);
		$result = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->comments WHERE $cur_time_span_sql comment_type = '' AND comment_approved = 1 AND comment_author_url LIKE '%$link_matches[1]%'");
		if ($first) {
			$min_comments_number = $result;
			$max_comments_number = $result;
			$first--;
		} else {
			if ($min_comments_number > $result) $min_comments_number = $result;
			if ($max_comments_number < $result) $max_comments_number = $result;
		}
		$link->comment_count = $result;
	}
	if ($options['order_by'] == 'comments_number') {
		if ($options['order'] == 'DESC') {
			function links_sort($a, $b) {
				if ($a->comment_count == $b->comment_count) return 0;
				return ($a->comment_count > $b->comment_count) ? -1 : 1;
			}
			uasort($links, 'links_sort');
		} else {
			function links_sort($a, $b) {
				if ($a->comment_count == $b->comment_count) return 0;
				return ($a->comment_count > $b->comment_count) ? 1 : -1;
			}
			if ($options['order_by'] == 'comments_number')	uasort($links, 'links_sort');
		}
	}
	foreach ($links as $link) {
		if (!$link->comment_count)
			if (!$options['show_no_comment'])
				continue;
		if (!$options['link_font_size']) {
			if ($max_comments_number == $min_comments_number) {
				$font_size = ($options['max_font_size'] + $options['min_font_size']) / 2;
			} else {
				$font_size = $options['min_font_size'] + ($link->comment_count - $min_comments_number) / ($max_comments_number - $min_comments_number) * ($options['max_font_size'] - $options['min_font_size']);
			}
			$font_size = intval($font_size);
			$font_size = "font-size: {$font_size}pt;";
		} else {
			$font_size = '';
		}
		switch ($options['link_color']) {
			case 1:
				$min_r = hexdec(substr($options['min_color'], 0, 2));
				$min_g = hexdec(substr($options['min_color'], 2, 2));
				$min_b = hexdec(substr($options['min_color'], 4, 2));
				$max_r = hexdec(substr($options['max_color'], 0, 2));
				$max_g = hexdec(substr($options['max_color'], 2, 2));
				$max_b = hexdec(substr($options['max_color'], 4, 2));
				if ($max_comments_number == $min_comments_number) {
					$link_color_span = 1;
				} else {
					$link_color_span = $max_comments_number - $min_comments_number;
				}
				$r = dechex(intval((($link->comment_count - $mincount) * ($max_r - $min_r) / $link_color_span) + $min_r));
				$g = dechex(intval((($link->comment_count - $mincount) * ($max_g - $min_g) / $link_color_span) + $min_g));
				$b = dechex(intval((($link->comment_count - $mincount) * ($max_b - $min_b) / $link_color_span) + $min_b));
				if (strlen($r) == 1)
					$r = "0$r";
				if (strlen($g) == 1)
					$g = "0$g";
				if (strlen($b) == 1)
					$b = "0$b";
				$color = "color: #$r$g$b;";
				break;
			case 2:
				$color = dechex(rand(0, 16777215));
				$color = "color: #$color;";
				break;
			default:
		}
		$rel = $link->link_rel;
		if ($options['nofollow']) $rel .= ' nofollow';
		$rel = "rel='$rel'";
		$target = ($link->link_target) ? "target='$link->link_target'" : '';
		if ($options['target_blank'])
			$target = 'target="_blank"';
		$content = "<a href='$link->link_url' title ='$link->link_description' $rel style='$font_size$color' $target>$link->link_name</a>";
		if ($options['output_style']) {
			$contents.= "<li>$content</li>";
		} else {
			$contents.= $content . stripslashes($options['link_separator']);
		}
	}
	if ($options['output_style']) {
		$contents = "<ul>$contents</ul>";
	} else {
		$contents = substr($contents, 0, -strlen($options['link_separator']));
	}
	$contents = "<div class='magic-links'>$contents</div>";
	update_option('ml_contents', $contents);
}

add_action('ml_update_event', 'ml_update_content');

ml_get_options();

if (!wp_next_scheduled('ml_update_event')){
	wp_schedule_event( time(), $options['schedule'], 'ml_update_event');
}

function ml_update_deactivation(){
	delete_option('ml_contents');
	wp_clear_scheduled_hook('ml_update_event');
}

register_deactivation_hook(basename(__FILE__), 'ml_update_deactivation');

function ml_insert(){
	$output = get_option('ml_contents');
	if (!$output) {
		ml_update_content();
		$output = get_option('ml_contents');
	}
	return $output;
}

add_shortcode('MAGIC-LINKS', 'ml_insert');
add_filter('widget_text', 'do_shortcode');

function ml_add_setting_page() {
	add_options_page('Magic Links', 'Magic Links', 8, __FILE__, 'ml_setting_page');
}

add_action('admin_menu', 'ml_add_setting_page');

function ml_add_schedule_options() {
	return array(
		'ml_minutely' => array('interval' => 60, 'display' => 'Once Minutely'),
		'ml_every2minutes' => array('interval' => 120, 'display' => 'Every 2 Minutes'),
		'ml_every5minutes' => array('interval' => 300, 'display' => 'Every 5 Minutes'),
		'ml_every10minutes' => array('interval' => 600, 'display' => 'Every 10 Minutes'),
		'ml_3timeshourly' => array('interval' => 1200, 'display' => '3 Times Hourly'),
		'ml_twicehourly' => array('interval' => 1800, 'display' => 'Twice Hourly'),
		'ml_every2hours' => array('interval' => 7200, 'display' => 'Every 2 Hours'),
		'ml_every4hours' => array('interval' => 14400, 'display' => 'Every 4 Hours'),
		'ml_3timesdaily' => array('interval' => 28800, 'display' => '3 Times Daily'),
		'ml_every2days' => array('interval' => 172800, 'display' => 'Every 2 Days'),
		'ml_weekly' => array('interval' => 604800, 'display' => 'Once Weekly'),
		'ml_monthly' => array('interval' => 2592000, 'display' => 'Once Monthly'),
	);
}
add_filter('cron_schedules', 'ml_add_schedule_options');

function ml_widget() {
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;

	function ml_widget_output($args) {
		extract($args);
		echo $before_widget;
		$widget_options = get_option('ml_widget');
		$title = $widget_options['title'];
		if (empty($title))
			$title = __('Blogroll');
		echo $before_title . $title . $after_title;
		$output = get_option('ml_contents');
		if (!$output) {
			ml_update_content();
			$output = get_option('ml_contents');
		}
		echo $output;
		echo $after_widget;
	}

	register_sidebar_widget('Magic Links', 'ml_widget_output');
	
	function ml_widget_option() {
		$widget_options = get_option('ml_widget');
		if ($_POST["ml_submit"]) {
			$widget_options['title'] = htmlspecialchars(stripslashes($_POST["ml_title"]));
			update_option('ml_widget', $widget_options);
		}
		$title = attribute_escape($widget_options['title']);
?>
		<p>
			<label for="ml-title">
				<?php _e('Title:'); ?>
				<input id="ml-title" name="ml_title" type="text" class="widefat" value="<?php echo $title; ?>" />
			</label>
		</p>
		<input type="hidden" id="ml_submit" name="ml_submit" value="1" />
<?php
	}
	
	register_widget_control('Magic Links', 'ml_widget_option');
}

add_action('plugins_loaded', 'ml_widget');

function ml_setting_page() {
	if ($_POST['ml_submit']) {
		$options['category_id'] = $_POST['category_id'];
		$options['time_span'] = (int)$_POST['time_span'];
		$options['order_by'] = $_POST['order_by'];
		$options['order'] = $_POST['order'];
		$options['show_invisible'] = $_POST['show_invisible'];
		$options['show_no_comment'] = $_POST['show_no_comment'];
		$options['max_display'] = (int)$_POST['max_display'];
		$options['output_style'] = $_POST['output_style'];
		$options['link_font_size'] = $_POST['link_font_size'];
		$options['min_font_size'] = (int)$_POST['min_font_size'];
		$options['max_font_size'] = (int)$_POST['max_font_size'];
		$options['link_color'] = $_POST['link_color'];
		$pattern = "/^[0-9a-fA-F]{1,6}/";
		preg_match($pattern, $_POST['min_color'], $color_matches);
		$options['min_color'] = $color_matches[0];
		preg_match($pattern, $_POST['max_color'], $color_matches);
		$options['max_color'] = $color_matches[0];
		$options['link_separator'] = htmlspecialchars(stripslashes($_POST['link_separator']));
		$options['target_blank'] = $_POST['target_blank'];
		$options['nofollow'] = $_POST['nofollow'];
		$options['schedule'] = $_POST['schedule'];
		update_option('ml_options', $options);
?>
<div class="updated"><p><strong><?php _e('Settings saved.', 'magiclinks'); ?></strong></p></div>
<?php
	}
	$options = ml_get_options();
	ml_update_deactivation();
	wp_schedule_event(time(), $options['schedule'], 'ml_update_event');
	ml_update_content();
	if ($_POST['ml_update']) {
?>
<div class="updated"><p><strong><?php _e('Cache updated.', 'magiclinks'); ?></strong></p></div>
<?php
	}
?>
<div class="wrap">
	<h2><?php _e('Magic Links Settings', 'magiclinks'); ?></h2>
	<form id="form1" name="form1" method="post" action="">
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="category_id"><?php _e('Link Categories', 'magiclinks'); ?></label></th>
				<td>
					<select name="category_id" id="category_id">
						<?php
							$output = '<option value="" ' . ((!$options['category_id']) ? ' selected="selected"' : '') . '>' . __('All categories', 'magiclinks') . '</option>\n';
							$categories = get_terms('link_category', array("hide_empty" => 1));
							foreach ($categories as $category) {
								$output .= '<option value="' . esc_attr($category->term_id) . '"' . (($category->term_id == $options['category_id']) ? " selected='selected'" : '') . '>' . sanitize_term_field('name', $category->name, $category->term_id, 'link_category', 'display') . "</option>\n";
							}
							echo $output;
						?>
					</select>
				</td>
			</tr>
	    <tr valign="top">
				<th scope="row"><label for="time_span"><?php _e('Statistical Scope', 'magiclinks'); ?></label></th>
				<td>
					<input name="time_span" type="text" id="time_span" value="<?php echo $options['time_span'] ?>" class="small-text" /> <?php _e('Day(s)', 'magiclinks'); ?>
					<span class="description"><?php _e('Fill 0 if you want to statistical of all time.', 'magiclinks'); ?></span>
				</td>
	    </tr>
	    <tr valign="top">
				<th scope="row"><?php _e('Sort order', 'magiclinks'); ?></th>
				<td>
					<select name="order_by" id="order_by">
						<option <?php if ($options['order_by'] == 'id' || $options['order_by'] == '') echo 'selected="selected"'; ?> value="id"><?php _e('Order by Link ID', 'magiclinks'); ?></option>
						<option <?php if ($options['order_by'] == 'name') echo 'selected="selected"'; ?> value="name"><?php _e('Order by Name', 'magiclinks'); ?></option>
						<option <?php if ($options['order_by'] == 'url') echo 'selected="selected"'; ?> value="url"><?php _e('Order by Address', 'magiclinks'); ?></option>						
						<option <?php if ($options['order_by'] == 'rating') echo 'selected="selected"'; ?> value="rating"><?php _e('Order by Rating', 'magiclinks'); ?></option>
						<option <?php if ($options['order_by'] == 'length') echo 'selected="selected"'; ?> value="length"><?php _e('Order by Length', 'magiclinks'); ?></option>
						<option <?php if ($options['order_by'] == 'comments_number') echo 'selected="selected"'; ?> value="comments_number"><?php _e('Order by Comments Number', 'magiclinks'); ?></option>
						<option <?php if ($options['order_by'] == 'rand') echo 'selected="selected"'; ?> value="rand"><?php _e('Random', 'magiclinks'); ?></option>
					</select>
					<select name="order" id="order">
						<option <?php if ($options['order'] == 'ASC' || $options['order'] == '') echo 'selected="selected"'; ?> value="ASC"><?php _e('Ascending', 'magiclinks'); ?></option>
						<option <?php if ($options['order'] == 'DESC') echo 'selected="selected"'; ?> value="DESC"><?php _e('Descending', 'magiclinks'); ?></option>
				</td>
	    </tr>
	    <tr valign="top">
	      <th scope="row"><label><?php _e('Filtering Options', 'magiclinks'); ?></label></th>
	      <td>
	      	<fieldset>
	      		<p>
	      			<label for="show_invisible">
								<input type="checkbox" <?php if ($options['show_invisible']) echo 'checked="checked"'; ?> id="show_invisible" name="show_invisible" value="1"><?php _e('Display private links', 'magiclinks'); ?>
							</label>
						</p>
						<p>
	      			<label for="show_no_comment">
								<input type="checkbox" <?php if ($options['show_no_comment']) echo 'checked="checked"'; ?> id="show_no_comment" name="show_no_comment" value="1"><?php _e('Display links owned by who have no comment', 'magiclinks'); ?>
							</label>
						</p>
						<p>
							<label for="max_display">
								<?php printf (__('Number of links to display: %s', 'magiclinks'), '<input name="max_display" type="text" id="max_display" value="' . $options['max_display'] . '" class="small-text" />'); ?>
								<span class="description"><?php _e('Fill 0 if you want to display all links.', 'magiclinks'); ?></span>
	      	</fieldset>
	      </td>
	    </tr>
	    <tr valign="top">
	      <th scope="row"><?php _e('Output Style', 'magiclinks'); ?></th>
	      <td id="front-static-pages">
	      	<fieldset>
						<p>
							<label>
								<input type="radio" <?php if (!$options['output_style']) echo 'checked="checked"'; ?> value="0" name="output_style"><?php _e('Tag cloud', 'magiclinks'); ?>
							</label>
							<ul>
								<li>
									<label for="link_separator">
										<?php _e('Link Separator:', 'magiclinks'); ?> <input type="text" class="regular-text" value="<?php echo $options['link_separator']; ?>" id="link_separator" name="link_separator">
									</label>
								</li>
							</ul>
						</p>
						<p>
							<label>
								<input type="radio" <?php if ($options['output_style']) echo 'checked="checked"'; ?> value="1" name="output_style"><?php _e('Unordered list', 'magiclinks'); ?>
							</label>
						</p>
	      	</fieldset>
	      </td>
	    </tr>
	    <tr valign="top">
	      <th scope="row"><?php _e('Font Size', 'magiclinks'); ?></th>
	      <td id="front-static-pages">
	      	<fieldset>
						<p>
							<label>
								<input type="radio" <?php if ($options['link_font_size']) echo 'checked="checked"'; ?> value="1" name="link_font_size"><?php _e('Default', 'magiclinks'); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="radio" <?php if (!$options['link_font_size']) echo 'checked="checked"'; ?> value="0" name="link_font_size"><?php _e('Adjusted by comments number (select below)', 'magiclinks'); ?>
							</label>
						</p>
						<ul>
							<li>
								<label for="min_font_size">
									<?php _e('Least comments:', 'magiclinks'); ?> <input name="min_font_size" type="text" id="min_font_size" value="<?php echo $options['min_font_size'] ?>" class="small-text" /> pt
								</label>
							</li>
							<li>
								<label for="max_font_size">
									<?php _e('Most comments:', 'magiclinks'); ?> <input name="max_font_size" type="text" id="max_font_size" value="<?php echo $options['max_font_size'] ?>" class="small-text" /> pt
								</label>
							</li>
						</ul>
	      	</fieldset>
	      </td>
	    </tr>
	    <tr valign="top">
	      <th scope="row"><?php _e('Font Color', 'magiclinks'); ?></th>
	      <td id="front-static-pages">
	      	<fieldset>
						<p>
							<label>
								<input type="radio" <?php if (!$options['link_color']) echo 'checked="checked"'; ?> value="0" name="link_color"><?php _e('Default', 'magiclinks'); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="radio" <?php if ($options['link_color'] == 1) echo 'checked="checked"'; ?> value="1" name="link_color"><?php _e('Adjusted by comments number (select below)', 'magiclinks'); ?>
							</label>
						</p>
						<ul>
							<li>
								<label for="min_color">
									<?php _e('Least comments:', 'magiclinks'); ?> #<input name="min_color" type="text" id="min_color" value="<?php echo $options['min_color'] ?>" size="6" />
								</label>
							</li>
							<li>
								<label for="max_color">
									<?php _e('Most comments:', 'magiclinks'); ?> #<input name="max_color" type="text" id="max_color" value="<?php echo $options['max_color'] ?>" size="6" />
								</label>
							</li>
						</ul>
						<p>
							<label>
								<input type="radio" <?php if ($options['link_color'] == 2) echo 'checked="checked"'; ?> value="2" name="link_color"><?php _e('Random', 'magiclinks'); ?>
							</label>
						</p>
	      	</fieldset>
	      </td>
	    </tr>
	    <tr valign="top">
		    <th scope="row"><?php _e('Link Behavior', 'magiclinks'); ?></th>
		    <td>
		    	<fieldset>
		    		<p>
		    			<label for="target_blank">
								<input type="checkbox" <?php if ($options['target_blank']) echo 'checked="checked"'; ?> id="target_blank" name="target_blank" value="1"><?php _e('Force links to open in a new window', 'magiclinks'); ?>
								<span class="description"><?php _e("Deselect it if you want to use links' <a href=\"link-manager.php\">setting</a>.", 'magiclinks'); ?></span>
							</label>
						</p>
						<p>
		    			<label for="nofollow">
								<input type="checkbox" <?php if ($options['nofollow']) echo 'checked="checked"'; ?> id="nofollow" name="nofollow" value="1"><?php _e('nofollow', 'magiclinks'); ?>
							</label>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="schedule"><?php _e('Update Recurrance', 'magiclinks'); ?></label></th>
				<td>
					<select name="schedule" id="schedule">
						<option value="ml_minutely" <?php if ($options['schedule'] == 'ml_minutely') echo 'selected="selected"'; ?>><?php _e('Minutely', 'magiclinks'); ?></option>
						<option value="ml_every2minutes" <?php if ($options['schedule'] == 'ml_every2minutes') echo 'selected="selected"'; ?>><?php _e('Every 2 Minutes', 'magiclinks'); ?></option>
						<option value="ml_every5minutes" <?php if ($options['schedule'] == 'ml_every5minutes') echo 'selected="selected"'; ?>><?php _e('Every 5 Minutes', 'magiclinks'); ?></option>
						<option value="ml_every10minutes" <?php if ($options['schedule'] == 'ml_every10minutes') echo 'selected="selected"'; ?>><?php _e('Every 10 Minutes', 'magiclinks'); ?></option>
						<option value="ml_3timeshourly" <?php if ($options['schedule'] == 'ml_3timeshourly') echo 'selected="selected"'; ?>><?php _e('3 Times Hourly', 'magiclinks'); ?></option>
						<option value="ml_twicehourly" <?php if ($options['schedule'] == 'ml_twicehourly') echo 'selected="selected"'; ?>><?php _e('Twice Hourly', 'magiclinks'); ?></option>
						<option value="hourly" <?php if ($options['schedule'] == 'hourly' || $options['schedule'] == '') echo 'selected="selected"'; ?>><?php _e('Hourly', 'magiclinks'); ?></option>
						<option value="ml_every2hours" <?php if ($options['schedule'] == 'ml_every2hours') echo 'selected="selected"'; ?>><?php _e('Every 2 Hours', 'magiclinks'); ?></option>
						<option value="ml_every4hours" <?php if ($options['schedule'] == 'ml_every4hours') echo 'selected="selected"'; ?>><?php _e('Every 4 Hours', 'magiclinks'); ?></option>
						<option value="ml_3timesdaily" <?php if ($options['schedule'] == 'ml_3timesdaily') echo 'selected="selected"'; ?>><?php _e('3 Times Daily', 'magiclinks'); ?></option>
						<option value="twicedaily" <?php if ($options['schedule'] == 'twicedaily') echo 'selected="selected"'; ?>><?php _e('Twice Daily', 'magiclinks'); ?></option>
						<option value="daily" <?php if ($options['schedule'] == 'daily') echo 'selected="selected"'; ?>><?php _e('Daily', 'magiclinks'); ?></option>
						<option value="ml_every2days" <?php if ($options['schedule'] == 'ml_every2days') echo 'selected="selected"'; ?>><?php _e('Every 2 Days', 'magiclinks'); ?></option>
						<option value="ml_weekly" <?php if ($options['schedule'] == 'ml_weekly') echo 'selected="selected"'; ?>><?php _e('Weekly', 'magiclinks'); ?></option>
						<option value="ml_monthly" <?php if ($options['schedule'] == 'ml_monthly') echo 'selected="selected"'; ?>><?php _e('Monthly', 'magiclinks'); ?></option>
					</select>
					<span class="description"><?php _e('Next update time:', 'magiclinks'); ?> <code><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), wp_next_scheduled('ml_update_event') + get_option('gmt_offset') * 3600); ?><?php _e('(Local time)', 'magiclinks'); ?></code></span><br />
					<span class="description"><?php _e('The changes take effect immediately, but time may not be the latest.', 'magiclinks'); ?></span>
				</td>
			</tr>
	  </table>
	  <p class="submit"><input class="button-primary" type="submit" name="ml_submit" value="<?php _e('Save Changes', 'magiclinks'); ?>" /> <input type="submit" name="ml_update" value="<?php _e('Update Cache', 'magiclinks'); ?>" /></p>
	</form>
</div>
<?php
}
?>