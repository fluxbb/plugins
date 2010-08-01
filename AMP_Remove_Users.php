<?php

/**
 * This plugin is for administrators to remove multiple users along with their
 * posts by the selection of checkbox.
 *
 * Copyright (C) 2007  Praveen V Nair (http://www.ninethsense.com/content/view/57/51/)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

##
##
##  A few notes of interest for aspiring plugin authors:
##
##  1. If you want to display a message via the message() function, you
##     must do so before calling generate_admin_menu($plugin).
##
##  2. Plugins are loaded by admin_loader.php and must not be
##     terminated (e.g. by calling exit()). After the plugin script has
##     finished, the loader script displays the footer, so don't worry
##     about that. Please note that terminating a plugin by calling
##     message() or redirect() is fine though.
##
##  3. The action attribute of any and all <form> tags and the target
##     URL for the redirect() function must be set to the value of
##     $_SERVER['REQUEST_URI']. This URL can however be extended to
##     include extra variables (like the addition of &amp;foo=bar in
##     the form of this example plugin).
##
##  4. If your plugin is for administrators only, the filename must
##     have the prefix "AP_". If it is for both administrators and
##     moderators, use the prefix "AMP_". This example plugin has the
##     prefix "AMP_" and is therefore available for both admins and
##     moderators in the navigation menu.
##
##  5. Use _ instead of spaces in the file name.
##
##  6. Since plugin scripts are included from the PunBB script
##     admin_loader.php, you have access to all PunBB functions and
##     global variables (e.g. $db, $pun_config, $pun_user etc).
##
##  7. Do your best to keep the look and feel of your plugins' user
##     interface similar to the rest of the admin scripts. Feel free to
##     borrow markup and code from the admin scripts to use in your
##     plugins. If you create your own styles they need to be added to
##     the "base_admin" style sheet.
##
##  8. Plugins must be released under the GNU General Public License or
##     a GPL compatible license. Copy the GPL preamble at the top of
##     this file into your plugin script and alter the copyright notice
##     to refrect the author of the plugin (i.e. you).
##
##


// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

//
// The rest is up to you!
//

// If the "Show text" button was clicked
	generate_admin_menu($plugin);
if (isset($_REQUEST['btnSubmit']))
{
	$userids = "";
	foreach ($_REQUEST as $key => $value) {
		if (substr($key,0,3) == 'chk') {
			if (strlen($userids) == 0) $userids .= "id = "; else $userids .=  " OR id = ";
			$userids .= substr($key,3,strlen($key));
		}
	}

	$db->query("DELETE FROM " . $db->prefix . 'posts WHERE ' . str_replace("id","poster_id",$userids) .";", true) or error('Unable to delete users', __FILE__, __LINE__, $db->error());
	$result = $db->query("DELETE FROM " . $db->prefix . 'users WHERE ' .$userids . ";", true) or error('Unable to delete users', __FILE__, __LINE__, $db->error());


	$users_pruned = $db->affected_rows();

	// Display the admin navigation menu
//	generate_admin_menu($plugin);

?>
	<div class="block">
		<h2><span>Removed!</span></h2>
		<div class="box">
			<div class="inbox">
<?php
	echo 'Removal complete. Users removed: '.$users_pruned.'.';
?>

			</div>
		</div>
	</div><br />
<?php

}
/*
else	// If not, we show the "Show text" form
{
*/
	// Display the admin navigation menu
//	generate_admin_menu($plugin);

?>
<script language="javascript">
	checked = false;
	function SelectAll() {
		if (checked == false){checked = true}else{checked = false}
		for (var i = 0; i < document.getElementById('removeusersform').elements.length; i++) {
	  		document.getElementById('removeusersform').elements[i].checked = checked;
		}
		EnableSubmit(0);
	}

	function EnableSubmit(s) {
		if (s != 0) document.getElementById('chkSelectAll').checked = false;
		for (var i = 0; i < document.getElementById('removeusersform').elements.length; i++) {
	  		if (document.getElementById('removeusersform').elements[i].checked == true) {
				document.getElementById("btnSubmit").disabled = false;
				return;
			}
		}
		document.getElementById("btnSubmit").disabled = true;
	}
</script>
	<div id="exampleplugin" class="blockform">
		<h2><span>Remove Users </span>v1.0</h2>
		<div class="box">
			<div class="inbox">
				<p>This plugin simply removes multiple users with posts. Useful for 'spam' cleanup. </p>
				<p>Programmed by Praveen (ninethsense@yahoo.co.uk) </p>
			</div>
		</div>

		<h2 class="block2">Manage</h2>
		<div class="box">
			<form id="removeusersform" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>" onsubmit="return confirm('Are you sure you want to remove these users along with their posts?')">
				<div class="inform">
<table cellpadding="0" cellspacing="1" border="0" style="width:500px">
<tr><th >Username</th><th style="width:50px;">Posts #</th></th><th>Email</th><th style="width:30px"><input type='checkbox' onclick="SelectAll()" id="chkSelectAll"></th></tr>
<?php
		$result = $db->query('SELECT id, group_id, username, num_posts, email FROM '.$db->prefix.'users ORDER BY id') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	if ($db->num_rows($result))
	{
		$cnt = 0;
		while ($cur_dupe = $db->fetch_assoc($result)) {

			if ($cnt == 0) {
				$col = "#CFD7F1" ;
				$cnt = 1;
			} else {
				$col = "#DDE2F2";
				$cnt = 0;
			}
			if ($cur_dupe['username'] == "Guest") continue;
			if ($cur_dupe['group_id'] == 1) $chk = ""; else $chk = "<input type='checkbox' name='chk{$cur_dupe['id']}'  id='chk{$cur_dupe['id']}' onclick='EnableSubmit(1);'>";
			echo "<tr bgcolor='$col' width='200'><td>" . $cur_dupe['username'] . "</td><td align='center'>" . $cur_dupe['num_posts'] . "</td><td>" . $cur_dupe['email'] . "</td><td align='center'>$chk</td></tr>";
			}
	}
?>
</table>
<input type="submit" name="btnSubmit" id="btnSubmit" value="Remove Users" disabled="disabled" />
				</div>
			</form>
		</div>
	</div>
<?php
/*
}
*/

// Note that the script just ends here. The footer will be included by admin_loader.php.
