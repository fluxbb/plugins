<?php

/**
 * The PunBB Shell plugins allows administrators to execute shell commands from
 * within the PunBB admin interface (use only if you know what you're doing).
 *
 * Copyright (C) 2006  Kristoffer Jansson (jansson@punres.org)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

// Display the admin navigation menu
generate_admin_menu($plugin);


?>


	<div id="exampleplugin" class="plugin blockform">
		<h2><span>PunBB Shell</span></h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin can run commands on the server from your PunBB admin like a shell.</p>
				<p>
					This plugin is for people that have hosts with messed up users and permissions and you
					need to work with your files as the webserver user. Does not work if PHP is running in
					safe mode.
				</p>
				<p>
					<span style="COLOR: #FF0000; FONT-WEIGHT: bold">Caution!</span> This plugin allows the user
					to do whatever he/she wants with your PunBB files that your webserver can. For security
					reasons, do not use this plugin on production sites. <strong>I take no responsibility for
					what you or someone else might cause with this plugin.</strong> If you can't handle it,
					don't.
				</p>
				<br />
				<p>Written by <a href="http://www.addressthatissue.com/">Kristoffer Jansson</a></p>
			</div>
		</div>
	</div>

<?php

// If the "Show text" button was clicked
if (isset($_POST['cmd']))
{
	// Make sure something something was entered
	if (trim($_POST['cmd']) == '')
		$result = 'No command given.';

	if(!$result)
	{
		$cmd = $_POST['cmd'];
		$os = strtolower($_ENV["OS"]);
		if(strpos($cmd, '%pun%') !== false)
		{
			$punbb_path = dirname($_SERVER["SCRIPT_FILENAME"]);
			$cmd = str_replace('%pun%', $punbb_path.'/', $cmd);
		}
		if(strpos($os, 'windows') !== false)
			$cmd = str_replace('/', '\\', $cmd);
		else
			$cmd = str_replace('\\', '/', $cmd);
		$result = shell_exec($cmd);
	}
?>
	<div class="block">
		<h2 class="block2"><span>Result</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Command: <?php echo $cmd ?></p>
				<pre>
<?php if($result == '') echo 'No output from shell.'; else echo $result; ?>
				</pre>
			</div>
		</div>
	</div>
<?php

}

?>
	<div class="blockform">
		<h2 class="block2"><span>Command</span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>Enter a command and hit "Run"</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Command<div><input type="submit" name="run" value="Run" tabindex="2" /></div></th>
								<td>
									<input type="text" name="cmd" size="50" tabindex="1"<?php if(isset($cmd)) echo ' value="'.$cmd.'"'; ?> />
									<span>Use %pun% to get PunBB's root.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
