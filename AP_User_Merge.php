<?php
/**
 * Copyright (C) 2014 StrongholdNation (http://www.strongholdnation.co.uk)
 * based on code by Terrell Russell copyright (C) 2005-2008 Terrell Russell
 * based on code by FluxBB copyright (C) 2008-2012 FluxBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
  exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

// Load the admin_plugin_example.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_merge.php';

$uid_merge = isset($_POST['to_merge']) ? intval($_POST['to_merge']) : '0';
$uid_stay = isset($_POST['to_stay']) ? intval($_POST['to_stay']) : '0';

if (isset($_POST['confirm_merge'], $_POST['form_sent']))
{
	if ($uid_merge == '0')
		message($lang_admin_merge['no merge user from']);

	if ($uid_stay == '0')
		message($lang_admin_merge['no merge user to']);

	if ($uid_merge == $uid_stay)
		message($lang_admin_merge['merge users same']);

	$result = $db->query("SELECT username, email, group_id, num_posts FROM ".$db->prefix."users WHERE id=".$uid_merge) or error('Unable to get data', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
		$user_merge = $db->fetch_assoc($result);
	else
		message($lang_common['Bad request']);

	$result = $db->query("SELECT username, email, group_id, num_posts FROM ".$db->prefix."users WHERE id=".$uid_stay) or error('Unable to get data', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
		$user_stay = $db->fetch_assoc($result);
	else
		message($lang_common['Bad request']);
	
	$db->query('UPDATE '.$db->prefix.'bans SET username = \''.$db->escape($user_stay['username']).'\', email = \''.$db->escape($user_stay['email']).'\' WHERE username = \''.$db->escape($user_merge['username']).'\' OR email = \''.$db->escape($user_merge['email']).'\'') or error('Unable to update bans', __FILE__, __LINE__, $db->error());
	
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';
		
	generate_bans_cache();

	$db->query('UPDATE '.$db->prefix.'forum_subscriptions SET user_id = '.$uid_stay.' WHERE user_id='.$uid_merge) or error('Unable to update forum subscriptions', __FILE__, __LINE__, $db->error());

	$db->query('DELETE FROM '.$db->prefix.'online WHERE user_id='.$uid_merge) or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'posts SET poster_id = '.$uid_stay.', poster = \''.$db->escape($user_stay['username']).'\' WHERE poster_id='.$uid_merge) or error('Unable to update posts', __FILE__, __LINE__, $db->error());
		
	$db->query('UPDATE '.$db->prefix.'posts SET edited_by = \''.$db->escape($user_stay['username']).'\' WHERE edited_by = \''.$db->escape($user_merge['username']).'\'') or error('Unable to update posts', __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'reports SET reported_by = '.$uid_stay.' WHERE reported_by='.$uid_merge) or error('Unable to update reports', __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'search_cache SET ident = \''.$db->escape($user_stay['username']).'\' WHERE ident = \''.$db->escape($user_merge['username']).'\'') or error('Unable to update search cache', __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'topics SET poster = \''.$db->escape($user_stay['username']).'\' WHERE poster=\''.$db->escape($user_merge['username']).'\'') or error('Unable to update topics', __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'topics SET last_poster = \''.$db->escape($user_stay['username']).'\' WHERE last_poster=\''.$db->escape($user_merge['username']).'\'') or error('Unable to update topics', __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'topic_subscriptions SET user_id = '.$uid_stay.' WHERE user_id='.$uid_merge) or error('Unable to update topic subscriptions', __FILE__, __LINE__, $db->error());	

	// If the group IDs are different we go for the newer one
	if ($user_merge['group_id'] != $user_stay['group_id'])
		$user_merge['group_id'] = $user_stay['group_id'];

	$result = $db->query('SELECT g_moderator FROM '.$db->prefix.'groups WHERE g_id = '.$user_merge['group_id']) or error('Unable to get group ID', __FILE__, __LINE__, $db->error());	
	$new_group_mod = $db->result($result);

		$password = random_pass(12);
		$db->query('UPDATE '.$db->prefix.'users SET group_id='.$user_merge['group_id'].', num_posts = '.($user_stay['num_posts'] + $user_merge['num_posts']).', password = \''.$db->escape(pun_hash($password)).'\' WHERE id='.$uid_stay) or error('Unable to update users', __FILE__, __LINE__, $db->error());
	
		// So if we're not an admin, are we a moderator? If not, remove them from all the forums they (might) have moderated.
		if ($user_merge['group_id'] != PUN_ADMIN && $new_group_mod != '1')
		{
			$result = $db->query('SELECT id, moderators FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());

			while ($cur_forum = $db->fetch_assoc($result))
			{
				$cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();
				if (in_array($uid_stay, $cur_moderators))
				{
					$username = array_search($uid_stay, $cur_moderators);
					unset($cur_moderators[$username]);
					$cur_moderators = (!empty($cur_moderators)) ? '\''.$db->escape(serialize($cur_moderators)).'\'' : 'NULL';

					$db->query('UPDATE '.$db->prefix.'forums SET moderators='.$cur_moderators.' WHERE id='.$cur_forum['id']) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
				}
			}
		}

		require PUN_ROOT.'include/email.php';

		// Send out merged emails
		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/account_merged_full.tpl'));
		// The first row contains the subject
		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = trim(substr($mail_tpl, $first_crlf));

		$mail_message = str_replace('<username>', $user_merge['username'], $mail_message);
		$mail_message = str_replace('<password>', $password, $mail_message);
		$mail_message = str_replace('<admin>', $pun_user['username'], $mail_message);
		$mail_message = str_replace('<merged_user>', $user_stay['username'], $mail_message);
		$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);
		pun_mail($user_merge['email'], $mail_subject, $mail_message);

		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/account_merged.tpl'));
		// The first row contains the subject
		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = trim(substr($mail_tpl, $first_crlf));

		$mail_message = str_replace('<username>', $user_stay['username'], $mail_message);
		$mail_message = str_replace('<password>', $password, $mail_message);
		$mail_message = str_replace('<admin>', $pun_user['username'], $mail_message);
		$mail_message = str_replace('<merged_user>', $user_merge['username'], $mail_message);
		$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);
		pun_mail($user_stay['email'], $mail_subject, $mail_message);
		delete_avatar($uid_merge);

		//Finally, the very last thing we do is delete the old user..	
		$db->query('DELETE FROM '.$db->prefix.'users WHERE id='.$uid_merge) or error('Unable to delete old user', __FILE__, __LINE__, $db->error());

		generate_users_info_cache();
		redirect('admin_loader.php?plugin='.$plugin, $lang_admin_merge['users merged redirect']);
}
else if (isset($_POST['form_sent']))
{
	if ($uid_merge == $uid_stay)
		message($lang_common['Bad request']);

	$result = $db->query("SELECT username FROM ".$db->prefix."users WHERE id=".$uid_merge) or error('Unable to get data', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
		$merge_user = pun_htmlspecialchars($db->result($result));
	else
		message($lang_common['Bad request']);

	$result = $db->query("SELECT username FROM ".$db->prefix."users WHERE id=".$uid_stay) or error('Unable to get data', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
		$stay_user = pun_htmlspecialchars($db->result($result));
	else
		message($lang_common['Bad request']);

generate_admin_menu($plugin);
?>
<div id="exampleplugin" class="blockform">
	<h2 class="block2"><span><?php echo $lang_admin_merge['confirm merge 2']; ?></span></h2>
	<div class="box">
		<form id="usermerge" method="post" action="admin_loader.php?plugin=<?php echo $plugin; ?>">
			<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
				<input type="hidden" name="to_merge" value="<?php echo $uid_merge; ?>" />
				<input type="hidden" name="to_stay" value="<?php echo $uid_stay; ?>" />
				<fieldset>
					<legend><?php echo $lang_admin_merge['merge legend']; ?></legend>
					<div class="infldset">
						<p><?php echo $merge_user; ?></p>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_admin_merge['merge legend 2']; ?></legend>
					<div class="infldset">
						<p><?php echo $stay_user; ?></p>
					</div>
				</fieldset>
			</div>
			<div class="fsetsubmit"><input type="submit" name="confirm_merge" value="<?php echo $lang_admin_merge['merge submit']; ?>" tabindex="3" /></div>
			<p class="topspace"><?php echo $lang_admin_merge['merge message']; ?></p>
		</form>
	</div>
</div>
<?php
}
else
{
	$user_result = $db->query('SELECT u.id, u.username, g.g_title FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id = g.g_id WHERE u.id !=1 ORDER BY u.id ASC') or error('Unable to fetch forum users', __FILE__, __LINE__, $db->error());	
	while ($result = $db->fetch_assoc($user_result))
		$options[] = '<option value="'.$result['id'].'">'.pun_htmlspecialchars($result['username'].' <'.$result['g_title'].'>').'</option>';

// Display the admin navigation menu
generate_admin_menu($plugin);
?>
<div id="exampleplugin" class="blockform">
	<h2 class="block2"><span><?php echo $lang_admin_merge['confirm merge 1']; ?></span></h2>
	<div class="box">
		<form id="usermerge" method="post" action="admin_loader.php?plugin=<?php echo $plugin; ?>">
			<input type="hidden" name="form_sent" value="1" />
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_admin_merge['user merge legend']; ?></legend>
					<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<td>
									<select name="to_merge" tabindex="3">
									<?php echo implode('', $options); ?>
									</select>
									<span><?php echo $lang_admin_merge['merge legend']; ?></span>
								</td>
							</tr>
						</table>
					</div>
				</fieldset>
			</div>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_admin_merge['merge legend 2']; ?></legend>
					<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<td>
									<select name="to_stay" tabindex="3">
									<?php echo implode('', $options); ?>
									</select>
									<span><?php echo $lang_admin_merge['merge help']; ?></span>
								</td>
							</tr>
						</table>
					</div>
				</fieldset>
			</div>
			<div class="fsetsubmit"><input type="submit" name="submit" value="<?php echo $lang_admin_merge['continue']; ?>" tabindex="3" /></div>
		</form>
	</div>
</div>
<?php
}

// Note that the script just ends here. The footer will be included by admin_loader.php.
