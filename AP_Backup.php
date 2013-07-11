<?php

/**
 * Use this plugin to backup your MySQL, SQLite or PostgreSQL database. The backup
 * can be downloaded, stored on the server or even uploaded to an FTP server.
 *
 * Updated for FluxBB v1.4 by the FluxBB Team (fluxbb.org)
 *
 * Copyright (C) 2002-2005  Michael Dorman (michaeldorman@gmail.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Load the admin_plugin_backup.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_backup.php';

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

// Increase time limit... incase its a big file or something.
@set_time_limit(0);

function dump_header($handle, $table)
{
	global $lang_admin_plugin_backup;

	fwrite($handle, "\n");
	fwrite($handle, '--'."\n");
	fwrite($handle, '-- '.sprintf($lang_admin_plugin_backup['Table structure for'], escape_keyword($table))."\n");
	fwrite($handle, '--'."\n");

	fwrite($handle, 'DROP TABLE IF EXISTS '.escape_keyword($table).';'."\n");
}

function dump_data($handle, $table)
{
	global $db_type, $db, $lang_admin_plugin_backup;

	$result = $db->query('SELECT * FROM '.escape_keyword($table)) or error('Unable to fetch table data', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		fwrite($handle, "\n");
		fwrite($handle, '-- '."\n");
		fwrite($handle, '-- '.sprintf($lang_admin_plugin_backup['Table data for'], escape_keyword($table))."\n");
		fwrite($handle, '-- '."\n");

		while($cur_row = $db->fetch_row($result))
		{
			$cur_row = array_map(array($db, 'escape'), $cur_row);

			fwrite($handle, 'INSERT INTO '.escape_keyword($table).' VALUES (\''.implode('\', \'', $cur_row).'\');'."\n");
		}

		fwrite($handle, "\n");
	}
}

function escape_keyword($str)
{
	global $db_type;

	switch ($db_type)
	{
		case 'sqlite':
			return $str;
		case 'pgsql':
			return $str; // TODO
		case 'mysql':
		case 'mysql_innodb':
		case 'mysqli':
		case 'mysqli_innodb':
			return '`'.$str.'`';
		default:
			return $str;
	}

}

if (isset($_POST['make_backup']))
{
	// Make sure something something was entered
	if (!isset($_POST['method']) || ($_POST['method'] != 'download' && $_POST['method'] != 'filesystem' && $_POST['method'] != 'ftp'))
		message($lang_common['Bad request']);

	$now = time();
	$filename = $db_type.'_'.$now.'.sql';

	$handle = @fopen(FORUM_CACHE_DIR.$filename, 'wb');
	if (!$handle)
		error('Unable to write to cache directory. Please make sure PHP has write access to the directory \'cache\'', __FILE__, __LINE__);

	// Start of making the dump

	fwrite($handle, sprintf($lang_admin_plugin_backup['Header line'], format_time($now, false, null, null, false, true), $db_name, $db_type));

	switch($db_type)
	{
		case 'mysql':
		case 'mysql_innodb':
		case 'mysqli':
		case 'mysqli_innodb':
			$result = $db->query('SHOW TABLES LIKE \''.$db->prefix.'%\'') or error('Unable to fetch table list', __FILE__, __LINE__, $db->error());
			while ($cur_table = $db->fetch_row($result))
			{
				// Write a header
				dump_header($handle, $cur_table[0]);

				// Dump the table structure
				$_result = $db->query('SHOW CREATE TABLE '.escape_keyword($cur_table[0])) or error('Unable to fetch table structure', __FILE__, __LINE__, $db->error());
				while ($cur_row = $db->fetch_row($_result))
				{
					unset ($cur_row[0]);
					fwrite($handle, implode("\n", $cur_row)."\n");
				}

				// Dump the table data
				dump_data($handle, $cur_table[0]);
			}

			break;

		case 'sqlite':
			$result = $db->query('SELECT name, sql FROM sqlite_master WHERE type=\'table\' AND name LIKE \''.$db->prefix.'%\'') or error('Unable to fetch table list', __FILE__, __LINE__, $db->error());
			while ($cur_table = $db->fetch_row($result))
			{
				// Write a header
				dump_header($handle, $cur_table[0]);

				// Dump the table structure
				fwrite($handle, str_replace("\t", ' ', $cur_table[1])."\n");

				// Dump the table data
				dump_data($handle, $cur_table[0]);
			}

			break;

		case 'pgsql':
			$result = $db->query('SELECT tablename FROM pg_tables WHERE tableowner = current_user AND tablename LIKE \''.$db->prefix.'%\'') or error('Unable to fetch table list', __FILE__, __LINE__, $db->error());
			while ($cur_table = $db->fetch_row($result))
			{
				// Write a header
				dump_header($handle, $cur_table[0]);

				// Dump the table structure
				// TODO

				// Dump the table data
				dump_data($handle, $cur_table[0]);
			}

			break;

		default:
			message($lang_admin_plugin_backup['Unknown database']);
	}

	// End of Making the dump

	fclose($handle);

	switch ($_POST['method'])
	{
		case 'download': // TODO: Gzip?
			header('Content-type: text/x-sql');
			header('Content-disposition: attachment; filename="'.$filename.'"');
			header('Content-length: '.filesize(FORUM_CACHE_DIR.$filename));

			readfile(FORUM_CACHE_DIR.$filename);
			@unlink(FORUM_CACHE_DIR.$filename);

			exit;

		case 'filesystem':
			$dir = isset($_POST['dir']) ? trim($_POST['dir']) : false;
			if (!$dir)
				message($lang_admin_plugin_backup['No directory']);

			if (!is_dir($dir) || !is_writable($dir) || !rename(FORUM_CACHE_DIR.$filename, $dir.'/'.$filename))
				message($lang_admin_plugin_backup['Unable to write']);

			generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span><?php echo $lang_admin_plugin_backup['Backup Successful'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php printf($lang_admin_plugin_backup['Backup written'], pun_htmlspecialchars($dir.'/'.$filename)); ?></p>
				<p><a href="javascript: history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</div>
		</div>
	</div>
<?php

			break;

		case 'ftp': // TODO: sftp?
			$hostname = isset($_POST['host']) ? trim($_POST['host']) : '';
			$username = isset($_POST['user']) ? trim($_POST['user']) : '';
			$password1 = isset($_POST['password1']) ? trim($_POST['password1']) : '';
			$password2 = isset($_POST['password2']) ? trim($_POST['password2']) : '';

			if (empty($hostname))
				message($lang_admin_plugin_backup['No hostname error']);

			if (empty($username))
				message($lang_admin_plugin_backup['No username error']);

			if ($password1 != $password2)
				message($lang_admin_plugin_backup['Password mismatch error']);

			if (isset($_POST['ssl']) && $_POST['ssl'] == '1')
				$conn = @ftp_ssl_connect($hostname);
			else
				$conn = @ftp_connect($hostname);

			if (!$conn)
				message(sprintf($lang_admin_plugin_backup['Unable to connect'], pun_htmlspecialchars($hostname)));

			if (!ftp_login($conn, $username, $password1))
				message($lang_admin_plugin_backup['Unable to login']);

			if (!ftp_put($conn, FORUM_CACHE_DIR.$filename, $filename, FTP_ASCII))
				message($lang_admin_plugin_backup['Unable to put']);

			// Bit of cleanup
			@unlink(FORUM_CACHE_DIR.$filename);
			@ftp_close($conn);

			generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span><?php echo $lang_admin_plugin_backup['Backup Successful'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php printf($lang_admin_plugin_backup['Backup uploaded'], pun_htmlspecialchars($hostname)); ?></p>
				<p><a href="javascript: history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</div>
		</div>
	</div>
<?php

			break;

		default: // This shouldn't happen
			@unlink(FORUM_CACHE_DIR.$filename);
			message($lang_common['Bad request']);
	}
}
else
{
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_backup['FluxBB Backup'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_admin_plugin_backup['Info 1'] ?></p>
				<p><?php echo $lang_admin_plugin_backup['Info 2'] ?></p>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_plugin_backup['Backup Database'] ?></span></h2>
		<div class="box">
			<form id="backup" method="post" action="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_backup['Choose backup method'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php echo $lang_admin_plugin_backup['Download label'] ?></th>
									<td>
										<input type="radio" name="method" value="download" checked="checked" />&#160;<?php echo $lang_admin_plugin_backup['Download help'] ?>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_plugin_backup['Filesystem label'] ?></th>
									<td>
										<input type="radio" name="method" value="filesystem" />&#160;<?php echo $lang_admin_plugin_backup['Filesystem help'] ?>
										<span><?php echo $lang_admin_plugin_backup['Directory label'] ?>&#160;<input type="text" name="dir" size="50" value="<?php echo pun_htmlspecialchars(getcwd()); ?>" /></span>
										<span style="color:red"><?php echo $lang_admin_plugin_backup['Write access'] ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_plugin_backup['FTP label'] ?></th>
									<td>
										<input type="radio" name="method" value="ftp" />&#160;<?php echo $lang_admin_plugin_backup['FTP help'] ?>
										<span><?php echo $lang_admin_plugin_backup['Host label'] ?>&#160;<input type="text" name="host" size="25" /></span>
										<span><?php echo $lang_admin_plugin_backup['Username label'] ?>&#160;<input type="text" name="user" size="25" /></span>
										<span><?php echo $lang_admin_plugin_backup['Password1 label'] ?>&#160;<input type="password" name="password1" size="25" /></span>
										<span><?php echo $lang_admin_plugin_backup['Password2 label'] ?>&#160;<input type="password" name="password2" size="25" /></span>
<?php if (function_exists('ftp_ssl_connect')): ?>										<span><?php echo $lang_admin_plugin_backup['Use SSL label'] ?>&#160;<input type="radio" name="ssl" value="1" />&#160;<strong><?php echo $lang_admin_common['Yes'] ?></strong>&#160;&#160;&#160;<input type="radio" name="ssl" value="0" checked="checked" />&#160;<strong><?php echo $lang_admin_common['No'] ?></strong></span>
<?php endif; ?>									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<p><?php echo $lang_admin_plugin_backup['Creating note'] ?></p>
				<p class="submitend"><input type="submit" name="make_backup" value="<?php echo $lang_admin_plugin_backup['Create backup'] ?>" /></p>
			</form>
		</div>
	</div>
<?php

}
