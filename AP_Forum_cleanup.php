<?php

/**
 * This plugin lets forum administrators do a little spring cleaning in the
 * forum database. It can be used to clean out the database after a spam attack.
 * Additionally, it can be used to synchronize user post counts, topic post
 * counts, last post data etc. The plugin requires MySQL 4.0.4 or later.
 *
 * Copyright (C) 2005  Connor Dunn (Connorhd@mypunbb.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
    exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION',1.0);

if (isset($_POST['cleanup']))
{
	//delete all users and posts from specified ips, then perform all other cleanup tasks except resetting post counts since that might not be needed or wanted.
	@set_time_limit(0);
	$ip = "'".implode("','", array_values(explode(' ', $_POST['ip_addys'])))."'";
	$db->query('DELETE FROM '.$db->prefix.'posts WHERE poster_ip IN('.$ip.')') or error('Could not delete posts', __FILE__, __LINE__, $db->error());
	$db->query('DELETE FROM '.$db->prefix.'users WHERE registration_ip IN('.$ip.')') or error('Could not delete users', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_posts SELECT t.forum_id, count(*) as posts FROM '.$db->prefix.'posts as p LEFT JOIN '.$db->prefix.'topics as t on p.topic_id=t.id GROUP BY t.forum_id') or error('Creating posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'forums, '.$db->prefix.'forum_posts SET num_posts=posts WHERE id=forum_id') or error('Could not update post counts', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_topics SELECT forum_id, count(*) as topics FROM '.$db->prefix.'topics GROUP BY forum_id') or error('Creating topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'forums, '.$db->prefix.'forum_topics SET num_topics=topics WHERE id=forum_id') or error('Could not update topic counts', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'topic_posts SELECT topic_id, count(*)-1 as replies FROM '.$db->prefix.'posts GROUP BY topic_id') or error('Creating topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'topics, '.$db->prefix.'topic_posts SET num_replies=replies WHERE id=topic_id') or error('Could not update topic counts', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_last SELECT p.posted AS n_last_post, p.id AS n_last_post_id, p.poster AS n_last_poster, t.forum_id FROM '.$db->prefix.'posts AS p LEFT JOIN '.$db->prefix.'topics AS t ON p.topic_id=t.id ORDER BY p.posted DESC') or error('Creating last posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_lastb SELECT * FROM '.$db->prefix.'forum_last WHERE forum_id > 0 GROUP BY forum_id') or error('Creating last posts tableb failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'forums, '.$db->prefix.'forum_lastb SET last_post_id=n_last_post_id, last_post=n_last_post, last_poster=n_last_poster WHERE id=forum_id') or error('Could not update last post', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'topic_last SELECT posted AS n_last_post, id AS n_last_post_id, poster AS n_last_poster, topic_id FROM '.$db->prefix.'posts ORDER BY posted DESC') or error('Creating last posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'topic_lastb SELECT * FROM '.$db->prefix.'topic_last WHERE topic_id > 0 GROUP BY topic_id') or error('Creating last posts tableb failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'topics, '.$db->prefix.'topic_lastb SET last_post_id=n_last_post_id, last_post=n_last_post, last_poster=n_last_poster WHERE id=topic_id') or error('Could not update last post', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'orph_topic SELECT t.id as o_id FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'posts AS p ON p.topic_id = t.id WHERE p.id IS NULL') or error('Creating orphaned topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('DELETE '.$db->prefix.'topics FROM '.$db->prefix.'topics, '.$db->prefix.'orph_topic WHERE o_id=id') or error('Could not delete topics', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'orph_posts SELECT p.id as o_id FROM '.$db->prefix.'posts p LEFT JOIN '.$db->prefix.'topics t ON p.topic_id=t.id WHERE t.id IS NULL') or error('Creating orphaned posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('DELETE '.$db->prefix.'posts FROM '.$db->prefix.'posts, '.$db->prefix.'orph_posts WHERE o_id=id') or error('Could not delete posts', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'orph_topics SELECT t.id as o_id FROM '.$db->prefix.'topics as t LEFT JOIN '.$db->prefix.'forums as f ON t.forum_id=f.id WHERE f.id is NULL') or error('Creating orphaned topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('DELETE '.$db->prefix.'topics FROM '.$db->prefix.'topics, '.$db->prefix.'orph_topics WHERE o_id=id') or error('Could not delete topics', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'Forums cleansed');
}
if (isset($_POST['forum_post_sync']))
{
	// synchronise forum posts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_posts SELECT t.forum_id, count(*) as posts FROM '.$db->prefix.'posts as p LEFT JOIN '.$db->prefix.'topics as t on p.topic_id=t.id GROUP BY t.forum_id') or error('Creating posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'forums, '.$db->prefix.'forum_posts SET num_posts=posts WHERE id=forum_id') or error('Could not update post counts', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_topics SELECT forum_id, count(*) as topics FROM '.$db->prefix.'topics GROUP BY forum_id') or error('Creating topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'forums, '.$db->prefix.'forum_topics SET num_topics=topics WHERE id=forum_id') or error('Could not update topic counts', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'Forums synchronised');
}
elseif (isset($_POST['topic_post_sync']))
{
	// synchronise topic posts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'topic_posts SELECT topic_id, count(*)-1 as replies FROM '.$db->prefix.'posts GROUP BY topic_id') or error('Creating topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'topics, '.$db->prefix.'topic_posts SET num_replies=replies WHERE id=topic_id') or error('Could not update topic counts', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'Topics synchronised');
}
elseif (isset($_POST['user_post_sync']))
{
	// synchronise user posts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'user_posts SELECT poster_id, count(*)as posts FROM '.$db->prefix.'posts GROUP BY poster_id') or error('Creating posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'users, '.$db->prefix.'user_posts SET num_posts=posts WHERE id=poster_id') or error('Could not update post counts', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'User post counts synchronised');
}
elseif (isset($_POST['forum_last_post']))
{
	// synchronise forum last posts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_last SELECT p.posted AS n_last_post, p.id AS n_last_post_id, p.poster AS n_last_poster, t.forum_id FROM '.$db->prefix.'posts AS p LEFT JOIN '.$db->prefix.'topics AS t ON p.topic_id=t.id ORDER BY p.posted DESC') or error('Creating last posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'forum_lastb SELECT * FROM '.$db->prefix.'forum_last WHERE forum_id > 0 GROUP BY forum_id') or error('Creating last posts tableb failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'forums, '.$db->prefix.'forum_lastb SET last_post_id=n_last_post_id, last_post=n_last_post, last_poster=n_last_poster WHERE id=forum_id') or error('Could not update last post', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'Forum last posts synchronised');
}
elseif (isset($_POST['topic_last_post']))
{
	// synchronise topic last posts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'topic_last SELECT posted AS n_last_post, id AS n_last_post_id, poster AS n_last_poster, topic_id FROM '.$db->prefix.'posts ORDER BY posted DESC') or error('Creating last posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'topic_lastb SELECT * FROM '.$db->prefix.'topic_last WHERE topic_id > 0 GROUP BY topic_id') or error('Creating last posts tableb failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'topics, '.$db->prefix.'topic_lastb SET last_post_id=n_last_post_id, last_post=n_last_post, last_poster=n_last_poster WHERE id=topic_id') or error('Could not update last post', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'Topic last posts synchronised');
}
elseif (isset($_POST['delete_orphans']))
{
	// Clear orphans
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'orph_topic SELECT t.id as o_id FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'posts AS p ON p.topic_id = t.id WHERE p.id IS NULL') or error('Creating orphaned topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('DELETE '.$db->prefix.'topics FROM '.$db->prefix.'topics, '.$db->prefix.'orph_topic WHERE o_id=id') or error('Could not delete topics', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'orph_posts SELECT p.id as o_id FROM '.$db->prefix.'posts p LEFT JOIN '.$db->prefix.'topics t ON p.topic_id=t.id WHERE t.id IS NULL') or error('Creating orphaned posts table failed', __FILE__, __LINE__, $db->error());
	$db->query('DELETE '.$db->prefix.'posts FROM '.$db->prefix.'posts, '.$db->prefix.'orph_posts WHERE o_id=id') or error('Could not delete posts', __FILE__, __LINE__, $db->error());
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'orph_topics SELECT t.id as o_id FROM '.$db->prefix.'topics as t LEFT JOIN '.$db->prefix.'forums as f ON t.forum_id=f.id WHERE f.id is NULL') or error('Creating orphaned topics table failed', __FILE__, __LINE__, $db->error());
	$db->query('DELETE '.$db->prefix.'topics FROM '.$db->prefix.'topics, '.$db->prefix.'orph_topics WHERE o_id=id') or error('Could not delete topics', __FILE__, __LINE__, $db->error());
	redirect('admin_loader.php?plugin=AP_Forum_cleanup.php', 'Orphans deleted');
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);
	$mysql_version = $db->query('SELECT VERSION()');
	if (version_compare($db->result($mysql_version), "4.0.4", ">"))
	{
?>
	<div class="block">
		<h2><span>Forum cleanup - v<?php echo PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin is used to cleanup the mess made by spammers and edits to the database that have put things out of sync.</p>
			</div>
		</div>
	</div>
	<div class="block">
		<h2 class="block2"><span>Complete spam cleanup</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature is intended to clean up the mess after a spam attack, how it works is you put in one or more IP addresses (separated by spaces) and it deletes all users and posts with that IP, then performs the rest of the cleanup operations.
					</p>
					<table class="aligntop" cellspacing="0">
						<tr>
							<th scope="row">IP Addresses</th>
							<td>
								<input type="text" name="ip_addys" size="50" maxlength="255" /><br />
								<span>Enter a list of one or more IP addresses separated by spaces to be removed from the forum (note it is also recommended you ban these IP addresses from the bans section of admin).</span>
							</td>
						</tr>
					</table>
				</div>
				<p class="submitend">
					<input type="submit" name="cleanup" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
		<h2 class="block2"><span>Synchronise forum post/topic counts</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature works out the number of posts/topics each forum currently has and resets their post/topic counts, useful if you edited the db.
					</p>
				</div>
				<p class="submitend">
					<input type="submit" name="forum_post_sync" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
		<h2 class="block2"><span>Synchronise topic reply counts</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature works out the number of replies each topic currently has and resets their reply counts, useful if you edited the db.
					</p>
				</div>
				<p class="submitend">
					<input type="submit" name="topic_post_sync" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
		<h2 class="block2"><span>Synchronise user post counts</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature works out the number of posts each user currently has and resets their post counts, useful if you deleted alot of posts and want to sync the user post count.
					</p>
				</div>
				<p class="submitend">
					<input type="submit" name="user_post_sync" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
		<h2 class="block2"><span>Synchronise forum last post</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature resets the last post section of all forums, useful to clean up the mess after a spammer or db edit.
					</p>
				</div>
				<p class="submitend">
					<input type="submit" name="forum_last_post" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
		<h2 class="block2"><span>Synchronise topic last post</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature resets the last post section of all topics, useful to clean up the mess after a spammer or db edit.
					</p>
				</div>
				<p class="submitend">
					<input type="submit" name="topic_last_post" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
		<h2 class="block2"><span>Delete orphans</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inbox">
					<p>
						This feature deletes all posts whos topic has been deleted, and inversely all topics whos have no posts and all topics whos forum has been deleted, useful after database edits.
					</p>
				</div>
				<p class="submitend">
					<input type="submit" name="delete_orphans" value="Go!" tabindex="4" />
				</p>
			</form>
		</div>
	</div>

<?php
	}
	else
		echo "Sorry this script requires Mysql 4.0.4 or above, i may support older versions in future scripts";
}
?>
