<?php

/**
 * The Clear Cache plugin can be used to clear and reset the different PunBB
 * caches if you are uncomfortable with or unable to manually edit the files
 * in the cache directory.
 *
 * Copyright (C) 2002-2005  Neal Poole (smartys@gmail.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

require PUN_ROOT.'include/cache.php';

// If the "Regenerate all cache" button was clicked
if (isset($_POST['regen_all_cache']))
{

	// We re-generate it all
	generate_config_cache();
	generate_bans_cache();
	generate_quickjump_cache();

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Clear your cache</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Cache re-generated!</p>
				<p><a href="javascript: history.go(-1)">Go back</a></p>
			</div>
		</div>
	</div>
<?php

}

// If the "Regenerate ban cache" button was clicked
else if (isset($_POST['regen_ban_cache']))
{
	// We re-generate it
	generate_bans_cache();

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Clear your cache</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Ban cache re-generated!</p>
				<p><a href="javascript: history.go(-1)">Go back</a></p>
			</div>
		</div>
	</div>
<?php

}

// If the "Regenerate ranks cache" button was clicked
else if (isset($_POST['regen_ranks_cache']))
{

	// We re-generate it
	generate_ranks_cache();

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Clear your cache</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Ranks cache re-generated!</p>
				<p><a href="javascript: history.go(-1)">Go back</a></p>
			</div>
		</div>
	</div>
<?php

}

// If the "Regenerate config cache" button was clicked
else if (isset($_POST['regen_config_cache']))
{
	// We re-generate it
	generate_config_cache();

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Clear your cache</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Config cache re-generated!</p>
				<p><a href="javascript: history.go(-1)">Go back</a></p>
			</div>
		</div>
	</div>
<?php

}

// If the "Regenerate quickjump cache" button was clicked
else if (isset($_POST['regen_jump_cache']))
{
	// We re-generate it
	generate_quickjump_cache();

	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Clear your cache</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Quickjump cache re-generated!</p>
				<p><a href="javascript: history.go(-1)">Go back</a></p>
			</div>
		</div>
	</div>
<?php

}
else	// If not, we show the form
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="exampleplugin" class="plugin blockform">
		<h2><span>Re-generate your cache</span></h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin allows you to easily and simply re-generate your FluxBB cache files</p>

				<form id="regenerate" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>&amp;foo=bar">
					<p><input type="submit" name="regen_all_cache" value="Regenerate all cache files" tabindex="2" /></p>
					<p><input type="submit" name="regen_ban_cache" value="Regenerate ban cache" tabindex="3" /></p>
<?php

$forum_version = substr(FORUM_VERSION, 0, -2);
if ($forum_version == '1.4')
{
            echo '
					<p>
						<input type="submit" name="regen_ranks_cache" value="Regenerate ranks cache" tabindex="4" />
					</p>';

}

?>
					<p><input type="submit" name="regen_config_cache" value="Regenerate config cache" tabindex="5" /></p>
					<p><input type="submit" name="regen_jump_cache" value="Regenerate quickjump cache" tabindex="6" /></p>
				</form>

			</div>
		</div>
</div>
<?php

}

// Note that the script just ends here. The footer will be included by admin_loader.php.
