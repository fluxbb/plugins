<?php

/**
 * This plugin can be used by administrators to modify the author (poster and
 * poster_id) of a given post.
 *
 * Copyright (C) 2006  guardian34 (publicbox@fmguy.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);


function update_post_author($post_id, $user_id, $username)
{
	global $db;

	// Get topic id
	$result = $db->query('SELECT topic_id FROM '.$db->prefix.'posts WHERE id='.$post_id) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	$topic_id = $db->result($result);

	// Get topic post id
	$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id.' ORDER BY posted LIMIT 1') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	$topic_post_id = $db->result($result);

	// Update post
	$db->query('UPDATE '.$db->prefix.'posts SET poster=\''.$username.'\', '.'poster_id='.$user_id.' WHERE id = '.$post_id) or error('Unable to update post info', __FILE__, __LINE__, $db->error());
	if ($db->affected_rows($result) < 1)
		return 0;

	// Try to update "topic post"
	if ($post_id == $topic_post_id)
		$db->query('UPDATE '.$db->prefix.'topics SET poster = \''.$username.'\' WHERE id = '.$topic_id) or error('Unable to update topic info', __FILE__, __LINE__, $db->error());

	// Try to update last_poster
	$db->query('UPDATE '.$db->prefix.'topics SET last_poster = \''.$username.'\' WHERE last_post_id = '.$post_id) or error('Unable to update topic info', __FILE__, __LINE__, $db->error());

	return 1;
}


if (isset($_POST['update_post']) || isset($_POST['update_user']))
{
	if (isset($_POST['update_post']))
	{
		// Make sure post ids were entered
		if (trim($_POST['post_ids']) == '')
			message('You didn\'t specify any post ids!');

		// Get array of post ids
		$posts = explode(',', $_POST['post_ids']);
	}
	else if (isset($_POST['update_user']))
	{
		// Make sure old user id was entered
		if (trim($_POST['old_user_id']) == '')
			message('You didn\'t specify an old user id!');

		// Get array of post ids
		$posts = array();
		$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE poster_id='.intval($_POST['old_user_id'])) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());

		while ($cur_post = $db->fetch_assoc($result))
			$posts[] = $cur_post['id'];
	}

	// Make sure user id was entered
	if (trim($_POST['new_user_id']) == '')
		message('You didn\'t specify a new user id!');

	$new_user_id = intval($_POST['new_user_id']);
	$num_updated = 0;

	// Get name of new user
	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE id='.$new_user_id) or error('Unable to fetch username', __FILE__, __LINE__, $db->error());
	$new_username = $db->result($result);
	if ($new_username == '')
		message('User id '.$new_user_id.' wasn\'t found.');


	// Update all posts
	foreach ($posts as $cur_post_id)
	{
		$num_updated += update_post_author($cur_post_id, $new_user_id, $new_username);
	}


	// Get all forums
	$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

	// Update all forums
	while ($cur_forum = $db->fetch_assoc($result))
		update_forum($cur_forum['id']);


	redirect($_SERVER['REQUEST_URI'], 'Changed author to "'.pun_htmlspecialchars($new_username).'" for '.$num_updated.' post(s).');
}
else if (isset($_POST['sync_post_counts']))
{
	// Synchronize user post counts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'post_counts SELECT poster_id, count(*) as new_num FROM '.$db->prefix.'posts GROUP BY poster_id') or error('Creating temporary table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'users SET num_posts=0') or error('Could not reset post counts', __FILE__, __LINE__, $db->error()); // Zero posts
	$db->query('UPDATE '.$db->prefix.'users, '.$db->prefix.'post_counts SET num_posts=new_num WHERE id=poster_id') or error('Could not update post counts', __FILE__, __LINE__, $db->error());


	redirect($_SERVER['REQUEST_URI'], 'Post counts synchronized');
}


if (isset($_POST['menu']))
{
	$user_field = array('<td>', "\t\t\t\t\t\t\t\t\t".'<select name="">');

	$result = $db->query('SELECT id, username FROM '.$db->prefix.'users WHERE id > 1 ORDER BY username ASC') or error('Unable to get user list', __FILE__, __LINE__, $db->error());

	while ($cur_user = $db->fetch_assoc($result))
		$user_field[] = "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_user['id'].'">'.pun_htmlspecialchars($cur_user['username']).'</option>';

	$user_field = implode("\n", $user_field);
}
else
	$user_field = '<td><input type="text" name="" size="16" /></td>';


// Display the admin navigation menu
generate_admin_menu($plugin);
?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Author Update - v2.1</span></h2>
		<div class="box">
			<div class="inbox">
				<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
					<p>This plugin modifies the author of one or more posts.
					<input type="submit" style="float: right;" tabindex="1" <?php
						echo (isset($_POST['menu'])) ? 'name="menu_" value="Use Text Fields"' : 'name="menu" value="Use Menus"'; ?> />
					</p>
				</form>
			</div>
		</div>
	</div>

	<div class="blockform">
		<h2 class="block2"><span>Individual Posts</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>IDs</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Post ID(s):</th>
									<td><input type="text" name="post_ids" size="16" /> &nbsp;<i>(Use commas for multiple values)</i></td>
								</tr>
								<tr>
									<th scope="row">New User ID:</th>
									<?php echo str_replace('name=""', 'name="new_user_id"', $user_field); ?>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="update_post" value="Update" /></p>
			</form>
		</div>
	</div>

	<div class="blockform">
		<h2 class="block2"><span>All Posts by Certain User</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>IDs</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Old User ID:</th>
									<?php echo str_replace('name=""', 'name="old_user_id"', $user_field); ?>
								</tr>
								<tr>
									<th scope="row">New User ID:</th>
									<?php echo str_replace('name=""', 'name="new_user_id"', $user_field); ?>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="update_user" value="Update" /></p>
			</form>
		</div>
	</div>

	<div class="blockform">
		<h2 class="block2"><span>Synchronize User Post Counts</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<p class="submitend"><input type="submit" name="sync_post_counts" value="Synchronize" /></p>
			</form>
		</div>
	</div>
