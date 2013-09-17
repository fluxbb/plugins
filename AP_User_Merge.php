<?php

/**
 * The User Merge plugins allows administrators to merge two user accounts.
 *
 * Copyright (C) 20058  Terrell Russell (punbb@terrellrussell.com)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
  exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);

// --------------------------------------------------------------------
// Pluralize
function pluralize($count, $singular, $plural = false)
  {return ($count == 1 ? $singular : ($plural ? $plural : $singular . 's'));}
// --------------------------------------------------------------------

// Confirm & Pre-Flight Page

if (isset($_POST['confirm_users']))
{
  // Make sure message body was entered
  if (trim($_POST['userid_to_wipe']) == '')
    message('You must select a user to wipe away!');

  // Make sure message subject was entered
  if (trim($_POST['userid_to_remain']) == '')
    message('You must select a user to be merged into!');

  // Make sure the users are different
  if (trim($_POST['userid_to_wipe']) == trim($_POST['userid_to_remain']))
    message('You must select two different users!');

  // setup userids
  $userid_to_wipe   = trim($_POST['userid_to_wipe']);
  $userid_to_remain = trim($_POST['userid_to_remain']);

  // get the usernames and realnames from the passed userids
  $sql = "SELECT username, realname, email FROM ".$db->prefix."users WHERE id='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not get username_to_wipe', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $username_to_wipe = $row['username'];
  $realname_to_wipe = $row['realname'];
  $email_to_wipe    = $row['email'];
  $sql = "SELECT username, realname, email FROM ".$db->prefix."users WHERE id='".$userid_to_remain."'";
  $result = $db->query($sql) or error('Could not get username_to_remain', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $username_to_remain = $row['username'];
  $realname_to_remain = $row['realname'];
  $email_to_remain    = $row['email'];

  // forums - last_poster(u)
  $sql = "SELECT count(*) as forums_count FROM ".$db->prefix."forums WHERE last_poster='".$username_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the forums table', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['forums'] = $row['forums_count'];

  // online - user_id(id)
  $sql = "SELECT count(*) as online_count FROM ".$db->prefix."online WHERE user_id='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the online table', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['online'] = $row['online_count'];

  // posts - poster_id(id), edited_by(u)
  $sql = "SELECT count(*) as posts1_count FROM ".$db->prefix."posts WHERE poster_id='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the posts table (1)', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['posts1'] = $row['posts1_count'];
  $sql = "SELECT count(*) as posts2_count FROM ".$db->prefix."posts WHERE edited_by='".$username_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the posts table (2)', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['posts2'] = $row['posts2_count'];

  // reports - reported_by(id), zapped_by(id)
  $sql = "SELECT count(*) as reports1_count FROM ".$db->prefix."reports WHERE reported_by='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the reports table (1)', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['reports1'] = $row['reports1_count'];
  $sql = "SELECT count(*) as reports2_count FROM ".$db->prefix."reports WHERE zapped_by='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the reports table (2)', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['reports2'] = $row['reports2_count'];

  // subscriptions - user_id(id)
  $sql = "SELECT count(*) as subscriptions_count FROM ".$db->prefix."subscriptions WHERE user_id='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the subscriptions table', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['subscriptions'] = $row['subscriptions_count'];

  // topics - poster(u), last_poster(u)
  $sql = "SELECT count(*) as topics1_count FROM ".$db->prefix."topics WHERE poster='".$username_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the topics table (1)', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['topics1'] = $row['topics1_count'];
  $sql = "SELECT count(*) as topics2_count FROM ".$db->prefix."topics WHERE last_poster='".$username_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the topics table (2)', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['topics2'] = $row['topics2_count'];

  // users - id(id)
  $sql = "SELECT count(*) as users_count FROM ".$db->prefix."users WHERE id='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not read the users table', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $counts['users'] = $row['users_count'];

  // generate display names
  $wipe_display = "[<strong>".pun_htmlspecialchars($username_to_wipe)."</strong>]";
  if (pun_htmlspecialchars($realname_to_wipe)!=""){ $wipe_display .= " ".pun_htmlspecialchars($realname_to_wipe);}
  if (pun_htmlspecialchars($email_to_wipe)!=""){ $wipe_display .= " &lt;".pun_htmlspecialchars($email_to_wipe)."&gt";}
  $remain_display = "[<strong>".pun_htmlspecialchars($username_to_remain)."</strong>]";
  if (pun_htmlspecialchars($realname_to_remain)!=""){ $remain_display .= " ".pun_htmlspecialchars($realname_to_remain);}
  if (pun_htmlspecialchars($email_to_remain)!=""){ $remain_display .= " &lt;".pun_htmlspecialchars($email_to_remain)."&gt";}

  // Display the admin navigation menu
  generate_admin_menu($plugin);



?>
  <div id="exampleplugin" class="blockform">
    <h2><span>User Merge - Confirm</span></h2>
    <div class="box">
      <div class="inbox">
        <p>Please confirm your user selections here.<br /><br />If something is not correct, please <a href="javascript: history.go(-1)">Go Back</a>.</p>
      </div>
    </div>

    <h2 class="block2"><span>Confirm Users (Step 2 of 3)</span></h2>
    <div class="box">
      <form id="usermerge" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
        <div class="inform">
          <input type="hidden" name="userid_to_wipe" value="<?php echo pun_htmlspecialchars($userid_to_wipe) ?>" />
          <input type="hidden" name="userid_to_remain" value="<?php echo pun_htmlspecialchars($userid_to_remain) ?>" />
          <fieldset>
            <legend>User to be Merged and then Deleted</legend>
            <div class="infldset">
              <p><?php echo $wipe_display ?></p>
            </div>
          </fieldset>
        </div>
        <div class="inform">
          <fieldset>
            <legend>User to be Merged Into</legend>
            <div class="infldset">
              <p><?php echo $remain_display ?></p>
            </div>
          </fieldset>
        </div>
        <div class="inform">
          <input type="hidden" name="userid_to_wipe" value="<?php echo pun_htmlspecialchars($userid_to_wipe) ?>" />
          <input type="hidden" name="userid_to_remain" value="<?php echo pun_htmlspecialchars($userid_to_remain) ?>" />
          <fieldset>
            <legend>Effects of this Merge</legend>
            <div class="infldset">
              <p>[ <strong><?php echo $counts['forums'] ?></strong> ] 'forums' <?php echo pluralize($counts['forums'],"entry","entries") ?> to be updated</p>
              <p>[ <strong><?php echo $counts['online'] ?></strong> ] 'online' user <?php echo pluralize($counts['online'],"entry","entries") ?> to be updated</p>
              <p>[ <strong><?php echo $counts['posts1'] ?></strong> ] 'posts' <?php echo pluralize($counts['posts1'],"entry","entries") ?> to be updated</p>
              <p>[ <strong><?php echo $counts['reports1'] ?></strong> ] 'reports' <?php echo pluralize($counts['reports1'],"entry","entries") ?> to be updated</p>
              <p>[ <strong><?php echo $counts['subscriptions'] ?></strong> ] 'subscriptions' <?php echo pluralize($counts['subscriptions'],"entry","entries") ?> to be updated</p>
              <p>[ <strong><?php echo $counts['topics1'] ?></strong> ] 'topics' <?php echo pluralize($counts['topics1'],"entry","entries") ?> to be updated</p>
              <p>[ <strong><?php echo $counts['users'] ?></strong> ] 'users' <?php echo pluralize($counts['users'],"entry","entries") ?> to be deleted</p>
            </div>
          </fieldset>
        </div>
        <div class="fsetsubmit"><input type="submit" name="merge_the_users" value="Confirmed - Merge Them." tabindex="3" /></div>
        <p class="topspace">Please hit this button only once. Patience is key.</p>
      </form>
    </div>
  </div>
<?php

}

// --------------------------------------------------------------------

// Merge the Users

else if (isset($_POST['merge_the_users']))
{

  // setup userids
  $userid_to_wipe   = trim($_POST['userid_to_wipe']);
  $userid_to_remain = trim($_POST['userid_to_remain']);

  // get the usernames and realnames from the passed userids
  $sql = "SELECT username, realname FROM ".$db->prefix."users WHERE id='".$userid_to_wipe."'";
  $result = $db->query($sql) or error('Could not get username_to_wipe', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $username_to_wipe = $row['username'];
  $realname_to_wipe = $row['realname'];
  $sql = "SELECT username, realname FROM ".$db->prefix."users WHERE id='".$userid_to_remain."'";
  $result = $db->query($sql) or error('Could not get username_to_remain', __FILE__, __LINE__, $db->error());
  $row = $db->fetch_assoc($result);
  $username_to_remain = $row['username'];
  $realname_to_remain = $row['realname'];

  // forums - update last_poster(u)
  $sql = "UPDATE ".$db->prefix."forums SET last_poster='$username_to_remain' WHERE last_poster='$username_to_wipe'";
  $result = $db->query($sql) or error('Could not update the forums table', __FILE__, __LINE__, $db->error());


  // forums - update moderators
  $sql = "SELECT id, moderators FROM ".$db->prefix."forums";
  $result = $db->query($sql) or error('Could not get moderators from forums table', __FILE__, __LINE__, $db->error());
  while ($cur_forum = $db->fetch_assoc($result))
  {
    $cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : array();
    if (in_array($userid_to_wipe, $cur_moderators))
    {
      $username = array_search($userid_to_wipe, $cur_moderators);
      unset($cur_moderators[$username]);
      $cur_moderators[$username_to_remain] = $userid_to_remain;
      ksort($cur_moderators);
      $cur_moderators = (!empty($cur_moderators)) ? '\''.$db->escape(serialize($cur_moderators)).'\'' : 'NULL';
      $sql = "UPDATE ".$db->prefix."forums SET moderators=".$cur_moderators." WHERE id=".$cur_forum['id'];
      $result = $db->query($sql) or error('Could not update the moderators', __FILE__, __LINE__, $db->error());
    }
  }

  // online - delete where user_id(id)
  $sql = "UPDATE ".$db->prefix."online SET user_id='$userid_to_remain', ident='$username_to_remain' WHERE user_id='$userid_to_wipe'";
  $result = $db->query($sql) or error('Could not update the online table', __FILE__, __LINE__, $db->error());

  // posts - update poster(u), poster_id(id), edited_by(u)
  $sql = "UPDATE ".$db->prefix."posts SET poster='$username_to_remain', poster_id='$userid_to_remain' WHERE poster_id='$userid_to_wipe'";
  $result = $db->query($sql) or error('Could not update the posts table (1)', __FILE__, __LINE__, $db->error());
  $sql = "UPDATE ".$db->prefix."posts SET edited_by='$username_to_remain' WHERE edited_by='$username_to_wipe'";
  $result = $db->query($sql) or error('Could not update the posts table (2)', __FILE__, __LINE__, $db->error());

  // reports - update reported_by(id), zapped_by(id)
  $sql = "UPDATE ".$db->prefix."reports SET reported_by='$userid_to_remain' WHERE reported_by='$userid_to_wipe'";
  $result = $db->query($sql) or error('Could not update the reports table (1)', __FILE__, __LINE__, $db->error());
  $sql = "UPDATE ".$db->prefix."reports SET zapped_by='$userid_to_remain' WHERE zapped_by='$userid_to_wipe'";
  $result = $db->query($sql) or error('Could not update the reports table (2)', __FILE__, __LINE__, $db->error());

  // subscriptions - update where user_id(id)
  $sql = "UPDATE ".$db->prefix."subscriptions SET user_id='$userid_to_remain' WHERE user_id='$userid_to_wipe'";
  $result = $db->query($sql) or error('Could not update the subscriptions table', __FILE__, __LINE__, $db->error());

  // topics - update poster(u), last_poster(u)
  $sql = "UPDATE ".$db->prefix."topics SET poster='$username_to_remain' WHERE poster='$username_to_wipe'";
  $result = $db->query($sql) or error('Could not update the topics table (1)', __FILE__, __LINE__, $db->error());
  $sql = "UPDATE ".$db->prefix."topics SET last_poster='$username_to_remain' WHERE last_poster='$username_to_wipe'";
  $result = $db->query($sql) or error('Could not update the topics table (2)', __FILE__, __LINE__, $db->error());

  // users - find by id(id), delete them (this should be the last step)
  $sql = "DELETE FROM ".$db->prefix."users WHERE id='$userid_to_wipe'";
  $result = $db->query($sql) or error('Could not update the users table', __FILE__, __LINE__, $db->error());

  // Display the admin navigation menu
  generate_admin_menu($plugin);

?>
  <div class="block">
    <h2><span>User Merge - Merge Complete</span></h2>
    <div class="box">
      <div class="inbox">
        <p>The merge is complete.</p>
      </div>
    </div>
    <h2 class="block2"><span>Results (Step 3 of 3)</span></h2>
    <div class="box">
      <div class="inbox">
        <p>[<strong><?php echo $username_to_remain ?></strong>] has been given credit for all of [<strong><?php echo $username_to_wipe ?></strong>]'s posts.</p>
        <p>[<strong><?php echo $username_to_wipe ?></strong>] has been deleted.</p>
      </div>
    </div>
  </div>
<?php

}

// --------------------------------------------------------------------

// Display the Main Page

else
{

 // Get all user accounts except Guest
 $sql = "SELECT id, username, realname, email FROM ".$db->prefix."users WHERE id!='1' ORDER BY username, realname";
 $result = $db->query($sql) or error('Could not get all users', __FILE__, __LINE__, $db->error());
  while($row = $db->fetch_assoc($result))
  {
    $usernames[$row['id']] = $row['username'];
    $realnames[$row['id']] = $row['realname'];
    $emails[$row['id']]    = $row['email'];
  }

  // Display the admin navigation menu
  generate_admin_menu($plugin);

?>
  <div id="exampleplugin" class="blockform">
    <h2><span>User Merge</span></h2>
    <div class="box">
      <div class="inbox">
        <p>This plugin allows the Administrator to merge two existing user accounts into one.</p>
        <p>There will be a confirmation page after this one - to make sure you have not made any mistakes.</p>
      </div>
    </div>

    <h2 class="block2"><span>User Selection (Step 1 of 3)</span></h2>
    <div class="box">
      <form id="usermerge" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
        <div class="inform">
          <fieldset>
            <legend>User to be Merged and then Deleted</legend>
            <div class="infldset">
              <table class="aligntop" cellspacing="0">
                <tr>
                  <td>
                    <select name="userid_to_wipe" tabindex="3">
<?php
    foreach($usernames as $userid=>$username)
    {
      if ($pun_user['id'] != $userid)
      {
        $display = "[".pun_htmlspecialchars($username)."]";
        if (pun_htmlspecialchars($realnames[$userid])!=""){ $display .= " ".pun_htmlspecialchars($realnames[$userid]);}
        if (pun_htmlspecialchars($emails[$userid])!=""){ $display .= " &lt;".pun_htmlspecialchars($emails[$userid])."&gt";}
        echo "                      ".'<option value="'.$userid.'">'.$display.'</option>'."\n";
      }
    }
?>
                    </select>
                    <span>Select the user you wish to merge into the user below.</span>
                  </td>
                </tr>
              </table>
            </div>
          </fieldset>
        </div>
        <div class="inform">
          <fieldset>
            <legend>User to be Merged Into</legend>
            <div class="infldset">
              <table class="aligntop" cellspacing="0">
                <tr>
                  <td>
                    <select name="userid_to_remain" tabindex="3">
<?php
    foreach($usernames as $userid=>$username)
    {
      $display = "[".pun_htmlspecialchars($username)."]";
      if (pun_htmlspecialchars($realnames[$userid])!=""){ $display .= " ".pun_htmlspecialchars($realnames[$userid]);}
      if (pun_htmlspecialchars($emails[$userid])!=""){ $display .= " &lt;".pun_htmlspecialchars($emails[$userid])."&gt";}
      echo "                      ".'<option value="'.$userid.'">'.$display.'</option>'."\n";
    }
?>
                    </select>
                    <span>Select the user that will inherit the above user's posts.</span>
                  </td>
                </tr>
              </table>
            </div>
          </fieldset>
        </div>
        <div class="fsetsubmit"><input type="submit" name="confirm_users" value="Continue to Confirmation Page" tabindex="3" /></div>
      </form>
    </div>
  </div>
<?php

}

// --------------------------------------------------------------------

// Note that the script just ends here. The footer will be included by admin_loader.php.
