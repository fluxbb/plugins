<?php

/**
 * The User Management plugin can be used to prune user accounts based on the
 * age of the account and the number of posts made by the users. Additionally,
 * the plugin can also be used to add new users.
 *
 * Copyright (C) 2005  Connor Dunn (Connorhd@mypunbb.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
    exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION',1.4);

if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
	require PUN_ROOT.'include/cache.php';

if (isset($_POST['prune']))
{
	// Make sure something something was entered
	if ((trim($_POST['days']) == '') || trim($_POST['posts']) == '')
		message('You need to set all settings!');
	if ($_POST['admods_delete']) {
		$admod_delete = 'group_id > 0';
	}
	else {
		$admod_delete = 'group_id > 3';
	}

	if ($_POST['verified'] == 1)
		$verified = '';
	elseif ($_POST['verified'] == 0)
		$verified = 'AND (group_id < 32000)';
	else
		$verified = 'AND (group_id = 32000)';

	$prune = ($_POST['prune_by'] == 1) ? 'registered' : 'last_visit';

	$user_time = time() - ($_POST['days'] * 86400);
	$result = $db->query('DELETE FROM '.$db->prefix.'users WHERE (num_posts < '.intval($_POST['posts']).') AND ('.$prune.' < '.intval($user_time).') AND (id > 2) AND ('.$admod_delete.')'.$verified, true) or error('Unable to delete users', __FILE__, __LINE__, $db->error());
	$users_pruned = $db->affected_rows();

	// Regenerate the users info cache
	generate_users_info_cache();

	message('Pruning complete. Users pruned '.$users_pruned.'.');
}
elseif (isset($_POST['add_user']))
{
	require PUN_ROOT.'lang/'.$pun_user['language'].'/prof_reg.php';
	require PUN_ROOT.'lang/'.$pun_user['language'].'/register.php';
	$username = pun_trim($_POST['username']);
	$email1 = strtolower(trim($_POST['email']));
	$email2 = strtolower(trim($_POST['email']));

	if ($_POST['random_pass'] == '1')
	{
		$password1 = random_pass(8);
		$password2 = $password1;
	}
	else
	{
		$password1 = trim($_POST['password']);
		$password2 = trim($_POST['password']);
	}

	// Convert multiple whitespace characters into one (to prevent people from registering with indistinguishable usernames)
	$username = preg_replace('#\s+#s', ' ', $username);

	// Validate username and passwords
	if (strlen($username) < 2)
		message($lang_prof_reg['Username too short']);
	else if (pun_strlen($username) > 25)	// This usually doesn't happen since the form element only accepts 25 characters
	    message($lang_common['Bad request']);
	else if (strlen($password1) < 4)
		message($lang_prof_reg['Pass too short']);
	else if ($password1 != $password2)
		message($lang_prof_reg['Pass not match']);
	else if (!strcasecmp($username, 'Guest') || !strcasecmp($username, $lang_common['Guest']))
		message($lang_prof_reg['Username guest']);
	else if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username))
		message($lang_prof_reg['Username IP']);
	else if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		message($lang_prof_reg['Username reserved chars']);
	else if (preg_match('#\[b\]|\[/b\]|\[u\]|\[/u\]|\[i\]|\[/i\]|\[color|\[/color\]|\[quote\]|\[quote=|\[/quote\]|\[code\]|\[/code\]|\[img\]|\[/img\]|\[url|\[/url\]|\[email|\[/email\]#i', $username))
		message($lang_prof_reg['Username BBCode']);

	// Check username for any censored words
	if ($pun_config['o_censoring'] == '1')
	{
		// If the censored username differs from the username
		if (censor_words($username) != $username)
			message($lang_register['Username censor']);
	}

	// Check that the username (or a too similar username) is not already registered
	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE username=\''.$db->escape($username).'\' OR username=\''.$db->escape(preg_replace('/[^\w]/', '', $username)).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());

	if ($db->num_rows($result))
	{
		$busy = $db->result($result);
		message($lang_register['Username dupe 1'].' '.pun_htmlspecialchars($busy).'. '.$lang_register['Username dupe 2']);
	}


	// Validate e-mail
	require PUN_ROOT.'include/email.php';

	if (!is_valid_email($email1))
		message($lang_common['Invalid e-mail']);

	// Check if someone else already has registered with that e-mail address
	$dupe_list = array();

	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE email=\''.$email1.'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		while ($cur_dupe = $db->fetch_assoc($result))
			$dupe_list[] = $cur_dupe['username'];
	}

	$timezone = '0';
	$language = isset($_POST['language']) ? $_POST['language'] : $pun_config['o_default_lang'];

	$email_setting = intval(1);

	// Insert the new user into the database. We do this now to get the last inserted id for later use.
	$now = time();

	$intial_group_id = ($_POST['random_pass'] == '0') ? $pun_config['o_default_user_group'] : PUN_UNVERIFIED;
	$password_hash = pun_hash($password1);

	// Add the user
	$db->query('INSERT INTO '.$db->prefix.'users (username, group_id, password, email, email_setting, timezone, language, style, registered, registration_ip, last_visit) VALUES(\''.$db->escape($username).'\', '.$intial_group_id.', \''.$password_hash.'\', \''.$email1.'\', '.$email_setting.', '.$timezone.' , \''.$language.'\', \''.$pun_config['o_default_style'].'\', '.$now.', \''.get_remote_address().'\', '.$now.')') or error('Unable to create user', __FILE__, __LINE__, $db->error());
	$new_uid = $db->insert_id();

	// Should we alert people on the admin mailing list that a new user has registered?
	if ($pun_config['o_regs_report'] == '1')
	{
		$mail_subject = 'Alert - New registration';
		$mail_message = 'User \''.$username.'\' registered in the forums at '.$pun_config['o_base_url']."\n\n".'User profile: '.$pun_config['o_base_url'].'/profile.php?id='.$new_uid."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)';

		pun_mail($pun_config['o_mailing_list'], $mail_subject, $mail_message);
	}

	// Must the user verify the registration or do we log him/her in right now?
	if ($_POST['random_pass'] == '1')
	{
		// Load the "welcome" template
		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/welcome.tpl'));

		// The first row contains the subject
		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = trim(substr($mail_tpl, $first_crlf));
		$mail_subject = str_replace('<board_title>', $pun_config['o_board_title'], $mail_subject);
		$mail_message = str_replace('<base_url>', $pun_config['o_base_url'].'/', $mail_message);
		$mail_message = str_replace('<username>', $username, $mail_message);
		$mail_message = str_replace('<password>', $password1, $mail_message);
		$mail_message = str_replace('<login_url>', $pun_config['o_base_url'].'/login.php', $mail_message);
		$mail_message = str_replace('<board_mailer>', sprintf($lang_common['Mailer'], $pun_config['o_board_title']), $mail_message);
		pun_mail($email1, $mail_subject, $mail_message);
	}

	// Regenerate the users info cache
	generate_users_info_cache();

	message('User Created');
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span>User management - v<?php echo PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p>This Plugin allows you to prune users a certain number of days old with less than a certain number of posts.</p>
				<p><strong>Warning: This has a permanent and instant effect. Use with extreme caution! It is recomended you make a backup before using this feature.</strong></p>
				<p>It also allows you to manually add users, this is useful for closed forum e.g. if you have disabled user registration in options.</p>
			</div>
		</div>
	</div>
	<div class="blockform">
		<h2 class="block2"><span>User Prune</span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>Settings</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
						<!--Thanks to wiseage for this function -->
							<tr>
								<th scope="row">Prune by</th>
								<td>
									<input type="radio" name="prune_by" value="1" checked="checked" />&nbsp;<strong>Registed date</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="prune_by" value="0" />&nbsp;<strong>Last Login</strong>
									<span>This decides if the minimum number of days is calculated since the last login or the registered date.</span>
								</td>
							</tr>
						<!--/Thanks to wiseage for this function -->
							<tr>
								<th scope="row">Minimum days since registration/last login</th>
								<td>
									<input type="text" name="days" value="28" size="25" tabindex="1" />
									<span>The minimum number of days before users are pruned by the setting specified above.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Maximum number of posts</th>
								<td>
									<input type="text" name="posts" value="1"  size="25" tabindex="1" />
									<span>Users with more posts than this won't be pruned. e.g. a value of 1 will remove users with no posts.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Delete admins and mods?</th>
								<td>
									<input type="radio" name="admods_delete" value="1" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="admods_delete" value="0" checked="checked" />&nbsp;<strong>No</strong>
									<span>If Yes, any affected Moderators and Admins will also be pruned.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">User status</th>
								<td>
									<input type="radio" name="verified" value="1" />&nbsp;<strong>Delete any</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="verified" value="0" checked="checked" />&nbsp;<strong>Delete only verified</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="verified" value="2" />&nbsp;<strong>Delete only unverified</strong>
									<span>Decideds if (un)verified users should be deleted.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
			<p class="submitend"><input type="submit" name="prune" value="Go!" tabindex="2" /></p>
			</form>
		</div>

		<h2 class="block2"><span>Add user</span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>Settings</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Username</th>
								<td>
									<input type="text" name="username" size="25" tabindex="3" />
								</td>
							</tr>
							<tr>
								<th scope="row">Email</th>
								<td>
									<input type="text" name="email" size="50" tabindex="3" />
								</td>
							</tr>
							<tr>
								<th scope="row">Generate random password?</th>
								<td>
									<input type="radio" name="random_pass" value="1" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="random_pass" value="0" checked="checked" />&nbsp;<strong>No</strong>
									<span>If Yes a random password will be generated and emailed to the above address.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Password</th>
								<td>
									<input type="text" name="password" size="25" tabindex="3" />
									<span>If you don't want a random password.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="add_user" value="Go!" tabindex="4" /></p>
			</form>
		</div>
	</div>

<?php
}
