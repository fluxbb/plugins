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

		case 'ftp':
/*
		if (!isset($_POST['host'], $_POST['user'], $_POST['password']))
			message('You forgot to define either a host, username or password.');

		if (isset($_POST['ssl']) && $_POST['ssl'] == 1 && function_exists('ftp_ssl_connect'))
			$connect_type = 'ftp_ssl_connect';
		else
			$connect_type = 'ftp_connect';

		if (!$conn = $connect_type($_POST['host']))
			message('I was unable to connect to the host specified.');

		if (!ftp_login($conn, $_POST['user'], $_POST['password']))
			message('I was unable to login with the username and password specified');

		//Gotta create a temporary file to upload this...
		$tmp = tempnam('','');
		$handle = fopen($tmp, 'w+');
		fwrite($handle, $dump);
		fclose($handle);

		if(!ftp_put($conn, $filename, $tmp, FTP_ASCII))
			message('I was unable to upload the file, perhaps you\'re out of space or do not have adaquete permissions?');

		//Bit of cleanup
		unlink($tmp);
		ftp_close($conn);

		message('A backup has successfully been uploaded to "'.pun_htmlspecialchars($_POST['host']).'".');
*/
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
	<div id="exampleplugin" class="blockform">
		<h2><span>FluxBB Backup</span></h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin allows you to quickly and easily make backups of your database data locally, to your computer or to an external FTP server.</p>
				<p>Making regular backups is very important, as it allows you to quickly and easily restore your site to its former self if you make a mistake or get hacked. Its recommended you make backups every 24 hours and before you make any major changes to the site (installing the latest FluxBB version, installing a mod, etc).</p>
			</div>
		</div>

		<h2 class="block2"><span>Backup Database</span></h2>
		<div class="box">
			<form id="backup" method="post" action="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
				<div class="inform">
					<fieldset>
						<legend>Choose backup method</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Download</th>
								<td>
									<span><input type="radio" name="method" value="download" checked="checked" />&nbsp;&nbsp;Download the database backup to your local harddrive.</span>
								</td>
							</tr>
							<tr>
								<th scope="row">Filesystem</th>
								<td>
									<span><input type="radio" name="method" value="filesystem" />&nbsp;&nbsp;Save it onto the servers filesystem.</span>
									Directory: <input type="text" name="dir" size="25" value="<?php echo getcwd(); ?>" /> <span style="color: red; display:inline;">Write access must be avalaible to this directory</span>
								</td>
							</tr>
							<tr>
								<th scope="row">FTP</th>
								<td>
									<span><input type="radio" name="method" value="ftp" />&nbsp;&nbsp;Save this backup to an <acronym title="File Transfer Protocol">FTP</acronym> server.</span>
									<span>Host: <input type="text" name="host" size="25" style="margin-left: 3em;" /></span>
									<span>Username: <input type="text" name="user" size="25" /></span>
									<span>Password:<input type="text" name="password" size="25" style="margin-left: .7em;" /></span>
									<?php echo function_exists('ftp_ssl_connect') ? '<span><acronym title="Secure Socket Layer">SSL</acronym>? <input type="checkbox" name="ssl" value="1" style="margin-left: 3.2em;"/> <strong>Yes</strong></span>' : '' ?>
								</td>
							</tr>
						</table>
						<p class="topspace">Creating your backup may take a while, especially on large forums. Don't be suprised if it takes several minutes for the next page to appear.</p>
						<div class="fsetsubmit"><input type="submit" name="make_backup" value="Create backup" tabindex="1" /></div>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php

}
