<?php

/**
 * The Global topic plugin is used to create the same topic in any number of
 * forums simultaneously.
 *
 * Copyright (C) 2005  Connor Dunn (Connorhd@mypunbb.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION',1.1);

// Load the viewforum.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';

if (isset($_POST['post']))
{
	// Do Post
	require PUN_ROOT.'include/search_idx.php';
	if (empty($_POST['subject']) || empty($_POST['message']))
		message('Missing Fields');
	if (!isset($_POST['forums']))
		message('No Forums Selected');

	$now = time();
	$i=0;
	$_POST['message'] = pun_linebreaks(pun_trim($_POST['message']));

	while($i < count($_POST['forums']))
	{
		$db->query('INSERT INTO '.$db->prefix.'topics (poster, subject, posted, last_post, last_poster, forum_id, sticky, closed) VALUES(\''.$db->escape($pun_user['username']).'\', \''.$db->escape($_POST['subject']).'\', '.$now.', '.$now.', \''.$db->escape($pun_user['username']).'\', '.$_POST['forums'][$i].', '.$_POST['sticky'].', '.$_POST['close'].')') or error('Unable to create topic', __FILE__, __LINE__, $db->error());
		$new_tid = $db->insert_id();
		$db->query('INSERT INTO '.$db->prefix.'posts (poster, poster_id, poster_ip, message, hide_smilies, posted, topic_id) VALUES(\''.$db->escape($pun_user['username']).'\', '.$pun_user['id'].', \''.get_remote_address().'\', \''.$db->escape($_POST['message']).'\', \'0\', '.$now.', '.$new_tid.')') or error('Unable to create post', __FILE__, __LINE__, $db->error());
		$new_pid = $db->insert_id();
		$db->query('UPDATE '.$db->prefix.'topics SET last_post_id='.$new_pid.' WHERE id='.$new_tid) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

		update_search_index('post', $new_pid, $_POST['message'], $_POST['subject']);
		update_forum($_POST['forums'][$i]);
		$i++;
	}

	redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Added');
}
elseif (isset($_POST['update']))
{
	if (empty($_POST['subject']) || empty($_POST['message']))
		message('Missing Fields');

	$_POST['message'] = pun_linebreaks(pun_trim($_POST['message']));

	$db->query('UPDATE '.$db->prefix.'topics SET subject=\''.$db->escape($_POST['subject']).'\' WHERE subject=\''.$db->escape($_POST['old_subject']).'\' AND posted='.$db->escape($_POST['old_posted'])) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

	$result = $db->query('SELECT p.id FROM '.$db->prefix.'posts as p LEFT JOIN '.$db->prefix.'topics as t ON t.id=p.topic_id WHERE t.subject=\''.$db->escape($_POST['subject']).'\' AND t.posted='.$db->escape($_POST['old_posted'])) or error('Unable to get post ids', __FILE__, __LINE__, $db->error());

	while ($cur_post = $db->fetch_assoc($result))
		$db->query('UPDATE '.$db->prefix.'posts SET message=\''.$db->escape($_POST['message']).'\' WHERE id='.$cur_post['id']) or error('Unable to update post', __FILE__, __LINE__, $db->error());

	redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Updated');
}
elseif (isset($_GET['action'])) //looks like we're doing something to a global topic
{
	switch ($_GET['action'])
	{
		case 'delete':
			$db->query('DELETE FROM '.$db->prefix.'topics WHERE subject=\''.$db->escape($_GET['subject']).'\' AND posted=\''.$db->escape($_GET['posted']).'\'') or error('Unable to delete topic', __FILE__, __LINE__, $db->error());
			redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Removed');
		break;
		case 'stick':
			$db->query('UPDATE '.$db->prefix.'topics SET sticky=1 WHERE subject=\''.$db->escape($_GET['subject']).'\' AND posted=\''.$db->escape($_GET['posted']).'\'') or error('Unable to update topic', __FILE__, __LINE__, $db->error());
			redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Stuck');
		break;
		case 'unstick':
			$db->query('UPDATE '.$db->prefix.'topics SET sticky=0 WHERE subject=\''.$db->escape($_GET['subject']).'\' AND posted=\''.$db->escape($_GET['posted']).'\'') or error('Unable to update topic', __FILE__, __LINE__, $db->error());
			redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Unstuck');
		break;
		case 'open':
			$db->query('UPDATE '.$db->prefix.'topics SET closed=0 WHERE subject=\''.$db->escape($_GET['subject']).'\' AND posted=\''.$db->escape($_GET['posted']).'\'') or error('Unable to update topic', __FILE__, __LINE__, $db->error());
			redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Opened');
		break;
		case 'close':
			$db->query('UPDATE '.$db->prefix.'topics SET closed=1 WHERE subject=\''.$db->escape($_GET['subject']).'\' AND posted=\''.$db->escape($_GET['posted']).'\'') or error('Unable to update topic', __FILE__, __LINE__, $db->error());
			redirect('admin_loader.php?plugin=AMP_Global_topic.php', 'Topic(s) Closed');
		break;
		case 'edit':
			// Display the admin navigation menu
			generate_admin_menu($plugin);
			$result = $db->query('SELECT p.message FROM '.$db->prefix.'posts as p LEFT JOIN '.$db->prefix.'topics as t ON t.id=p.topic_id WHERE t.subject=\''.$db->escape($_GET['subject']).'\' AND t.posted=\''.$db->escape($_GET['posted']).'\' LIMIT 0,1', true) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
			$cur_post = $db->fetch_assoc($result);
?>
	<div class="blockform">
		<h2><span>Edit Topic</span></h2>
		<div class="box">
			<form id="post" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
			<input type="hidden" name="old_subject" value="<?php echo $_GET['subject'] ?>" />
			<input type="hidden" name="old_posted" value="<?php echo $_GET['posted'] ?>" />
				<div class="inform">
					<fieldset>
						<legend>Post Settings</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Subject</th>
								<td>
									<input type="text" name="subject" size="60" maxlength="70" tabindex="1" value="<?php echo $_GET['subject'] ?>" />
									<span>The subject of the topic.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Message</th>
								<td>
									<textarea name="message" rows="15" cols="70" tabindex="1"><?php echo pun_htmlspecialchars($cur_post['message']); ?></textarea>
									<span>The message of the topic.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
			<p class="submitend"><input type="submit" name="update" value="Go!" tabindex="2" /></p>
			</form>
		</div>
	</div>
<?php
		break;
	}
}
else	// If not, we show the form
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span>Global topic - v<?php echo PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p>This Plugin allows you to add a topic to multiple forums.</p>
			</div>
		</div>
	</div>
	<div class="blockform">
		<h2 class="block2"><span>Add Topic</span></h2>
		<div class="box">
			<form id="post" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>Post Settings</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Subject</th>
								<td>
									<input type="text" name="subject" size="60" maxlength="70" tabindex="1" />
									<span>The subject of the topic.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Forums to post in.</th>
								<td>
									<select name="forums[]" multiple="multiple" size="5">
<?php
		$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id=1) WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

		$cur_category = 0;
		while ($cur_forum = $db->fetch_assoc($result))
		{
			if ($cur_forum['cid'] != $cur_category)	// A new category since last iteration?
			{
				if ($cur_category)
					echo '</optgroup>';

				echo '<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">';
				$cur_category = $cur_forum['cid'];
			}
			if (!$cur_forum['redirect_url'])
				echo '<option value="'.$cur_forum['fid'].'" selected="selected">'.pun_htmlspecialchars($cur_forum['forum_name']).'</option>';
		}

		echo '</optgroup>';
?>
									</select>
									<span>Select the forums to post in here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Message</th>
								<td>
									<textarea name="message" rows="15" cols="70" tabindex="1"></textarea>
									<span>The message of the topic.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Make Sticky?</th>
								<td>
									<input type="radio" name="sticky" value="1" checked="checked" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="sticky" value="0" />&nbsp;<strong>No</strong>
									<span>If Yes, topic(s) will be sticky.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Close?</th>
								<td>
									<input type="radio" name="close" value="1" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="close" value="0" checked="checked" />&nbsp;<strong>No</strong>
									<span>If Yes, topic(s) will be closed.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
			<p class="submitend"><input type="submit" name="post" value="Go!" tabindex="2" /></p>
			</form>
		</div>
	</div>
</div>
	<div id="vf" class="blocktable">
		<h2 class="block2"><span>Manage Global Topics</span></h2>
		<div class="box">
			<div class="inbox">
				<table cellspacing="0">
				<colgroup>
					<col class="tcl" />
					<col class="tc2" style="width: 15%"/>
					<col class="tc3" />
					<col class="tc3" />
					<col class="tcr" />
				</colgroup>
				<tbody>
<?php
//Find topics with the same subject and posted time (chances are they were made by this plugin)
$resultg = $db->query('SELECT * FROM '.$db->prefix.'topics GROUP BY subject, posted HAVING count( id ) >1') or error('Unable to fetch topic list', __FILE__, __LINE__, $db->error());

// If there are topics in this forum.
if ($db->num_rows($resultg))
{
	while ($cur_global = $db->fetch_assoc($resultg))
	{
?>
					<tr>
						<th class="tcl">
							<strong><a href="admin_loader.php?plugin=AMP_Global_topic.php&action=edit&subject=<?php echo $cur_global['subject'] ?>&posted=<?php echo $cur_global['posted'] ?>"><?php echo $cur_global['subject'] ?></a></strong>
						</th>
						<th class="tc2">
							Forum
						</th>
						<th class="tc3">
							<?php echo $lang_common['Replies'] ?>
						</th>
						<th class="tc3">
							<?php echo $lang_forum['Views'] ?>
						</th>
						<th class="tcr">
							<a href="admin_loader.php?plugin=AMP_Global_topic.php&action=delete&subject=<?php echo $cur_global['subject'] ?>&posted=<?php echo $cur_global['posted'] ?>">Delete</a> | <a href="admin_loader.php?plugin=AMP_Global_topic.php&action=stick&subject=<?php echo $cur_global['subject'] ?>&posted=<?php echo $cur_global['posted'] ?>">Stick</a>/<a href="admin_loader.php?plugin=AMP_Global_topic.php&action=unstick&subject=<?php echo $cur_global['subject'] ?>&posted=<?php echo $cur_global['posted'] ?>">Unstick</a> | <a href="admin_loader.php?plugin=AMP_Global_topic.php&action=close&subject=<?php echo $cur_global['subject'] ?>&posted=<?php echo $cur_global['posted'] ?>">Close</a>/<a href="admin_loader.php?plugin=AMP_Global_topic.php&action=open&subject=<?php echo $cur_global['subject'] ?>&posted=<?php echo $cur_global['posted'] ?>">Open</a>
						</th>
					</tr>
<?php
		$result = $db->query('SELECT t.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.forum_id, f.forum_name FROM '.$db->prefix.'topics as t LEFT JOIN '.$db->prefix.'forums as f ON t.forum_id=f.id WHERE subject=\''.$db->escape($cur_global['subject']).'\' AND posted='.$db->escape($cur_global['posted']).' ORDER BY sticky DESC, '.(($cur_forum['sort_by'] == '1') ? 'posted' : 'last_post').' DESC LIMIT 0, 50') or error('Unable to get topics', __FILE__, __LINE__, $db->error());

		while ($cur_topic = $db->fetch_assoc($result))
		{
		$icon_text = $lang_common['Normal icon'];
		$item_status = '';
		$icon_type = 'icon';

		if ($cur_topic['moved_to'] == null)
			$last_post = '<a href="viewtopic.php?pid='.$cur_topic['last_post_id'].'#p'.$cur_topic['last_post_id'].'">'.format_time($cur_topic['last_post']).'</a> <span class="byuser">'.$lang_common['by'].'&nbsp;'.pun_htmlspecialchars($cur_topic['last_poster']).'</span>';
		else
			$last_post = '&nbsp;';

		if ($pun_config['o_censoring'] == '1')
			$cur_topic['subject'] = censor_words($cur_topic['subject']);

		if ($cur_topic['moved_to'] != 0)
			$subject = $lang_forum['Moved'].': <a href="viewtopic.php?id='.$cur_topic['moved_to'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].'&nbsp;'.pun_htmlspecialchars($cur_topic['poster']).'</span>';
		else if ($cur_topic['closed'] == '0')
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].'&nbsp;'.pun_htmlspecialchars($cur_topic['poster']).'</span>';
		else
		{
			$subject = '<a href="viewtopic.php?id='.$cur_topic['id'].'">'.pun_htmlspecialchars($cur_topic['subject']).'</a> <span class="byuser">'.$lang_common['by'].'&nbsp;'.pun_htmlspecialchars($cur_topic['poster']).'</span>';
			$icon_text = $lang_common['Closed icon'];
			$item_status = 'iclosed';
		}

		if (!$pun_user['is_guest'] && $cur_topic['last_post'] > $pun_user['last_visit'] && $cur_topic['moved_to'] == null)
		{
			$icon_text .= ' '.$lang_common['New icon'];
			$item_status .= ' inew';
			$icon_type = 'icon inew';
			$subject = '<strong>'.$subject.'</strong>';
			$subject_new_posts = '<span class="newtext">[&nbsp;<a href="viewtopic.php?id='.$cur_topic['id'].'&amp;action=new" title="'.$lang_common['New posts info'].'">'.$lang_common['New posts'].'</a>&nbsp;]</span>';
		}
		else
			$subject_new_posts = null;

		if ($cur_topic['sticky'] == '1')
		{
			$subject = '<span class="stickytext">'.$lang_forum['Sticky'].': </span>'.$subject;
			$item_status .= ' isticky';
			$icon_text .= ' '.$lang_forum['Sticky'];
		}

		$num_pages_topic = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

		if ($num_pages_topic > 1)
			$subject_multipage = '[ '.paginate($num_pages_topic, -1, 'viewtopic.php?id='.$cur_topic['id']).' ]';
		else
			$subject_multipage = null;

		// Should we show the "New posts" and/or the multipage links?
		if (!empty($subject_new_posts) || !empty($subject_multipage))
		{
			$subject .= '&nbsp; '.(!empty($subject_new_posts) ? $subject_new_posts : '');
			$subject .= !empty($subject_multipage) ? ' '.$subject_multipage : '';
		}

?>
				<tr<?php if ($item_status != '') echo ' class="'.trim($item_status).'"'; ?>>
					<td class="tcl">
						<div class="intd">
							<div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo trim($icon_text) ?></div></div>
								<div class="tclcon">
								<?php echo $subject."\n" ?>
							</div>
						</div>
					</td>
					<td class="tc2"><?php echo $cur_topic['forum_name'] ?></td>
					<td class="tc3"><?php echo ($cur_topic['moved_to'] == null) ? $cur_topic['num_replies'] : '&nbsp;' ?></td>
					<td class="tc3"><?php echo ($cur_topic['moved_to'] == null) ? $cur_topic['num_views'] : '&nbsp;' ?></td>
					<td class="tcr"><?php echo $last_post ?></td>
				</tr>
<?php

		}
	}
}
else
{

?>
					<tr>
						<td class="tcl" colspan="4"><?php echo $lang_forum['Empty forum'] ?></td>
					</tr>
<?php

}

?>
				</tbody>
				</table>
			</div>
		</div>
<?php

}

?>
