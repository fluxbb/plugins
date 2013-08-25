<?php

/**
 * Use Version Changer to update the PunBB version string if it, for some
 * reason, is out of sync with your installation. Please note that this plugin
 * does not update PunBB as such, it only changes the version number.
 *
 * Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);


if (isset($_POST['update_version']))
{
	if (trim($_POST['to_version']) == '')
	{
		// Display the admin navigation menu
		generate_admin_menu( $plugin );
		message('You must enter a version number to change to.');
	}

	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$db->escape(trim($_POST['to_version'])).'\' WHERE conf_name=\'o_cur_version\'') or error('Unable to update board version string', __FILE__, __LINE__, $db->error());

	// Regenerate the config cache
	require_once PUN_ROOT.'include/cache.php';
	generate_config_cache();

	redirect($_SERVER['REQUEST_URI'], 'Version updated.');

}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Version Changer</span></h2>
		<div class="box">
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>&amp;foo=bar">
				<div class="inform">
					<fieldset>
						<legend>Enter a version number and hit "Update"</legend>
						<div class="infldset">
							<p>This plugin allows you go update the FluxBB version string. Yes, that's it :)</p>
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">New version</th>
									<td>
										<input type="text" name="to_version" size="8" tabindex="1" />
										<span>The new version string.</span>
									</td>
								</tr>
							</table>
							<p class="submittop"><input type="submit" name="update_version" value="Update" tabindex="2" /></p>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php

}

// Note that the script just ends here. The footer will be included by admin_loader.php.
