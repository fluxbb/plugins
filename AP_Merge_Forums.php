<?php

/**
 * Use this plugin to merge all topics and posts from one forum into another.
 *
 * Copyright (C) 2005  Steven Fackler (sfackler@san.rr.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

// If the "Run Merge" button was clicked
if (isset($_POST['run_merge']))
{
	// Get the variables
	$forum1 = intval($_POST['forum1']);
	$forum2 = intval($_POST['forum2']);

	// Make sure a forum was specified.
	if (trim($forum1) == '')
		message('You never specified a forum to merge from.');

	if (trim($forum2) == '')
		message('You never specified a forum to merge to.');

	//Make sure the forum specified exists
	$result = $db->query("SELECT * FROM ".$db->prefix."forums WHERE id=".$forum1);
	if ($db->num_rows($result) == '0')
		message('The forum you specified to merge from does not exist.');

	$result = $db->query("SELECT * FROM ".$db->prefix."forums WHERE id=".$forum2);
	if ($db->num_rows($result) == '0')
		message('The forum you specified to merge to does not exist.');

	//Make sure the forums being merged aren't the same
	if ($forum1 == $forum2)
		message('The forums you specified are the same.');

	//Run the update query.
	$db->query("UPDATE ".$db->prefix."topics set forum_id=".$forum2." where forum_id=".$forum1);

	//Delete the old forum
	$db->query("DELETE FROM ".$db->prefix."forums WHERE id = ".$forum1);

	//Update the forum last post, etc.
	update_forum($forum2);

	// Display the admin navigation menu
	generate_admin_menu($plugin);

	?>
	<div class="block">
		<h2><span>Forum Merge Plugin</span></h2>
		<div class="box">
			<div class="inbox">
				<p>Forums Merged.</p>
			</div>
		</div>
	</div>
<?php

}
else	// If not, we show the "Show text" form
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div id="mergeplugin" class="blockform">
		<h2><span>Merge Forums Plugin</span></h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin merges two forums into one.  It then deletes the old forum.</p>
			</div>
		</div>

		<div class="box">
			<form id="merge" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
				<div class="inform">
					<fieldset>
						<legend>Select the forum you want and the one you want to merge to.</legend>
						<div class="infldset">
						<table class="aligntop" cellspacing="0">
							<tr>
								<th scope="row">Merge Forums<div><input type="submit" name="run_merge" value="Merge" tabindex="3" /></div></th>
								<td>
									<select name="forum1">
									<?php
									$categories_result = $db->query("SELECT id, cat_name FROM ".$db->prefix."categories WHERE 1=1 ORDER BY id ASC");
									$forums_result = $db->query("SELECT id, forum_name, cat_id FROM ".$db->prefix."forums WHERE 1=1 ORDER BY cat_id ASC");
									$cat_now = 0;

									while ($forums = $db->fetch_assoc($forums_result))
									{
										//Check if it is a new cat
										if ($forums['cat_id'] != $cat_now)
										{
											$categories = $db->fetch_assoc($categories_result);
											echo "<option value='blargh' disabled='disabled'>".pun_htmlspecialchars($categories['cat_id'])."</option>";
											$cat_now = $categories['id'];
										}
										echo "<option value='".pun_htmlspecialchars($forums['id'])."'>".pun_htmlspecialchars($forums['forum_name'])."</option>";
									}
									?>
									</select>
									<span>Select the forum you want to merge from here.</span>
								</td>
								<td>
									<select name="forum2">
									<?php
									$categories_result = $db->query("SELECT id, cat_name FROM ".$db->prefix."categories WHERE 1=1 ORDER BY id ASC");
									$forums_result = $db->query("SELECT id, forum_name, cat_id FROM ".$db->prefix."forums WHERE 1=1 ORDER BY cat_id ASC");
									$cat_now = 0;

									while ($forums = $db->fetch_assoc($forums_result))
									{
										//Check if it is a new cat
										if ($forums['cat_id'] != $cat_now)
										{
											$categories = $db->fetch_assoc($categories_result);
											echo "<option value='blargh' disabled='disabled'>".pun_htmlspecialchars($categories['cat_id'])."</option>";
											$cat_now = $categories['id'];
										}
										echo "<option value='".pun_htmlspecialchars($forums['id'])."'>".pun_htmlspecialchars($forums['forum_name'])."</option>";
									}
									?>
									</select>
									<span>Select the forum you want to merge to here.</span>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php

}
