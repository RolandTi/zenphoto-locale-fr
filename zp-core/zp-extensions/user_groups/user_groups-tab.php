<?php
/**
 * user_groups plugin--tabs
 * @author Stephen Billard (sbillard)
 * @package zpcore\plugins\usergroups
 */
define('OFFSET_PATH', 4);
require_once(dirname(dirname(dirname(__FILE__))) . '/admin-globals.php');

admin_securityChecks(NULL, currentRelativeURL());
define('USERS_PER_PAGE', max(1, getOption('users_per_page')));
if (isset($_GET['pagenumber'])) {
	$pagenumber = sanitize_numeric($_GET['pagenumber']);
} else {
	if (isset($_POST['pagenumber'])) {
		$pagenumber = sanitize_numeric($_POST['pagenumber']);
	} else {
		$pagenumber = 0;
	}
}

$admins = $_zp_authority->getAdministrators('all', 'basedata', 'user', 'asc');
if (isset($_GET['action'])) {
	$action = sanitize($_GET['action']);
	XSRFdefender($action);
	$themeswitch = false;
	switch ($action) {
		case 'deletegroup':
			$groupname = trim(sanitize($_GET['group']));
			$groupobj = Authority::newAdministrator($groupname, 0);
			$groupobj->remove();
			// clear out existing user assignments
			Authority::updateAdminField('group', NULL, array('`valid`>=' => '1', '`group`=' => $groupname));
			redirectURL(FULLWEBPATH . "/" . ZENFOLDER . '/' . PLUGIN_FOLDER . '/user_groups/user_groups-tab.php?page=users&tab=groups&deleted&pagenumber=' . $pagenumber);
		case 'savegroups':
			if (isset($_POST['checkForPostTruncation'])) {
				for ($i = 0; $i < $_POST['totalgroups']; $i++) {
					$groupname = trim(sanitize($_POST[$i . '-group']));
					if (!empty($groupname)) {
						$rights = 0;
						$group = Authority::newAdministrator($groupname, 0);
						if (isset($_POST[$i . '-initgroup']) && !empty($_POST[$i . '-initgroup'])) {
							$initgroupname = trim(sanitize($_POST[$i . '-initgroup'], 3));
							$initgroup = Authority::newAdministrator($initgroupname, 0);
							$rights = $initgroup->getRights();
							$group->setObjects(processManagedObjects($group->getID(), $rights));
							$group->setRights(NO_RIGHTS | $rights);
						} else {
							$rights = processRights($i);
							$group->setObjects(processManagedObjects($i, $rights));
							$group->setRights(NO_RIGHTS | $rights);
						}
						$group->set('other_credentials', trim(sanitize($_POST[$i . '-desc'], 3)));
						$group->setName(trim(sanitize($_POST[$i . '-type'], 3)));
						$group->setValid(0);
						$group->setLastChangeUser($_zp_current_admin_obj->getUser());
						zp_apply_filter('save_admin_custom_data', true, $group, $i, true);
						$group->save();

						if ($group->getName() == 'group') {
							//have to update any users who have this group designate.
							$groupname = $group->getUser();
							foreach ($admins as $admin) {
								if ($admin['valid']) {
									$hisgroups = explode(',', strval($admin['group']));
									if (in_array($groupname, $hisgroups)) {
										$user = Authority::newAdministrator($admin['user'], $admin['valid']);
										user_groups::merge_rights($user, $hisgroups);
										$user->setLastChangeUser($_zp_current_admin_obj->getUser());
										$user->save();
									}
								}
							}
							//user assignments: first clear out existing ones
							Authority::updateAdminField('group', NULL, array('`valid`>=' => '1', '`group`=' => $groupname));
							//then add the ones marked
							$target = 'user_' . $i . '-';
							foreach ($_POST as $item => $username) {
								$item = sanitize(postIndexDecode($item));
								if (strpos($item, $target) !== false) {
									$username = substr($item, strlen($target));
									$user = Authority::getAnAdmin(array('`user`=' => $username, '`valid`>=' => 1));
									$user->setRights($group->getRights());
									$user->setObjects($group->getObjects());
									$user->setGroup($groupname);
									$user->setCustomData($group->getCustomData());
									$user->setLastChangeUser($_zp_current_admin_obj->getUser());
									$user->save();
								}
							}
						}
					}
				}
				$notify = '&saved';
			} else {
				$notify = '&post_error';
			}
			redirectURL(FULLWEBPATH . "/" . ZENFOLDER . '/' . PLUGIN_FOLDER . '/user_groups/user_groups-tab.php?page=users&tab=groups&pagenumber=' . $pagenumber . $notify);
	}
}

printAdminHeader('users');
$background = '';
?>
<script src="<?php echo WEBPATH . '/' . ZENFOLDER; ?>/js/sprintf.js"></script>
<?php
echo '</head>' . "\n";
?>

<body>
	<?php printLogoAndLinks(); ?>
	<div id="main">
		<?php printTabs(); ?>
		<div id="content">
			<?php
			if (isset($_GET['post_error'])) {
				echo '<div class="errorbox">';
				echo "<h2>" . gettext('Error') . "</h2>";
				echo gettext('The form submission is incomplete. Perhaps the form size exceeds configured server or browser limits.');
				echo '</div>';
			}
			if (isset($_GET['deleted'])) {
				echo '<div class="messagebox fade-message">';
				echo "<h2>" . gettext('Deleted') . "</h2>";
				echo '</div>';
			}
			if (isset($_GET['saved'])) {
				echo '<div class="messagebox fade-message">';
				echo "<h2>" . gettext('Saved') . "</h2>";
				echo '</div>';
			}
			$subtab = printSubtabs();
			?>
			<div id="tab_users" class="tabbox">
				<?php
				zp_apply_filter('admin_note', 'users', $subtab);
				switch ($subtab) {
					case 'groups':
						$adminlist = $admins;
						$users = array();
						$groups = array();
						$list = array();
						foreach ($adminlist as $user) {
							if ($user['valid']) {
								$users[] = $user['user'];
							} else {
								$groups[] = $user;
								$list[] = $user['user'];
							}
						}
						$max = floor((count($list) - 1) / USERS_PER_PAGE);
						if ($pagenumber > $max) {
							$pagenumber = $max;
						}
						$rangeset = getPageSelector($list, USERS_PER_PAGE);
						$groups = array_slice($groups, $pagenumber * USERS_PER_PAGE, USERS_PER_PAGE);
						$albumlist = array();
						foreach ($_zp_gallery->getAlbums() as $folder) {
							$alb = AlbumBase::newAlbum($folder);
							$name = $alb->getTitle();
							$albumlist[$name] = $folder;
						}
						?>
						<p>
							<?php
							echo gettext("Set group rights and select one or more albums for the users in the group to manage. Users with <em>User admin</em> or <em>Manage all albums</em> rights can manage all albums. All others may manage only those that are selected.");
							?>
						</p>
						<form class="dirty-check" action="?action=savegroups&amp;tab=groups" method="post" autocomplete="off" onsubmit="return checkSubmit()" >
							<?php XSRFToken('savegroups'); ?>
							<p class="buttons">
								<button type="submit"><img src="../../images/pass.png" alt="" /><strong><?php echo gettext("Apply"); ?></strong></button>
								<button type="reset"><img src="../../images/reset.png" alt="" /><strong><?php echo gettext("Reset"); ?></strong></button>
							</p>
							<br class="clearall" /><br />
							<input type="hidden" name="savegroups" value="yes" />
							<input type="hidden" name="pagenumber" value="<?php echo $pagenumber; ?>" />
							<table class="bordered">
								<tr>
									<th>
										<span style="font-weight: normal">
											<a href="javascript:toggleExtraInfo('','user',true);"><?php echo gettext('Expand all'); ?></a>
											|
											<a href="javascript:toggleExtraInfo('','user',false);"><?php echo gettext('Collapse all'); ?></a>
										</span>
									</th>
									<th>
										<?php printPageSelector($pagenumber, $rangeset, PLUGIN_FOLDER . '/user_groups/user_groups-tab.php', array('page' => 'users', 'tab' => 'groups')); ?>
									</th>
									<th></th>
								</tr>

								<?php
								$id = 0;
								$groupselector = $groups;
								$groupselector[''] = array('id' => -1, 'user' => '', 'name' => 'group', 'rights' => ALL_RIGHTS ^ MANAGE_ALL_ALBUM_RIGHTS, 'valid' => 0, 'other_credentials' => '');
								foreach ($groupselector as $key => $user) {		
									$groupuser = $user['user'];
									$groupobj = new Administrator($groupuser, 0);
									$groupid = $groupobj->getID();
									$rights = $groupobj->getRights();
									$grouptype = $groupobj->getName();
									$desc = implode('', $groupobj->getCredentials());
									if ($grouptype == 'group') {
										$kind = gettext('group');
									} else {
										$kind = gettext('template');
									}
									if ($background) {
										$background = "";
									} else {
										$background = "background-color:#ECF1F2;";
									}
									?>
									<tr id="user-<?php echo $id; ?>">
										<td style="border-top: 4px solid #D1DBDF;<?php echo $background; ?>" valign="top">
											<?php
											if (empty($groupuser)) {
												?>
												<em>
													<label><input type="radio" name="<?php echo $id; ?>-type" value="group" checked="checked" onclick="javascrpt:toggle('users<?php echo $id; ?>');
															toggleExtraInfo('<?php echo $id; ?>', 'user', true);" /><?php echo gettext('group'); ?></label>
													<label><input type="radio" name="<?php echo $id; ?>-type" value="template" onclick="javascrpt:toggle('users<?php echo $id; ?>');
															toggleExtraInfo('<?php echo $id; ?>', 'user', true);" /><?php echo gettext('template'); ?></label>
												</em>
												<br />
												<input type="text" size="35" id="group-<?php echo $id ?>" name="<?php echo $id ?>-group" value=""
															 onclick="toggleExtraInfo('<?php echo $id; ?>', 'user', true);" />
															 <?php
														 } else {
															 ?>
												<span class="userextrashow">
													<em><?php echo $kind; ?></em>:
													<a href="javascript:toggleExtraInfo('<?php echo $id; ?>','user',true);" title="<?php echo $groupuser; ?>" >
														<strong><?php echo $groupuser; ?></strong>
													</a>
												</span>
												<span style="display:none;" class="userextrahide">
													<em><?php echo $kind; ?></em>:
													<a href="javascript:toggleExtraInfo('<?php echo $id; ?>','user',false);" title="<?php echo $groupuser; ?>" >
														<strong><?php echo $groupuser; ?></strong>
													</a>
												</span>
												<input type="hidden" id="group-<?php echo $id ?>" name="<?php echo $id ?>-group" value="<?php echo html_encode($groupuser); ?>" />
												<input type="hidden" name="<?php echo $id ?>-type" value="<?php echo html_encode($grouptype); ?>" />
												<?php
											}
											?>
											<input type="hidden" name="<?php echo $id ?>-confirmed" value="1" />
											<span class="userextrainfo" style="display:none" >
												<br /><br />
												<?php
												printAdminRightsTable($id, '', '', $rights);
												$custom = zp_apply_filter('edit_admin_custom_data', '', $groupobj, $id, $background, true, '');
												if ($custom) {
													$custom = preg_replace('~</*tr[^>]*>~i', '', $custom);
													$custom = preg_replace('~</*td[^>]*>~i', '', $custom);
													echo $custom;
												}
												?>
											</span>
										</td>
										<td style="border-top: 4px solid #D1DBDF;<?php echo $background; ?>" valign="top">
											<span class="userextrainfo" style="display:none;" >
												<?php
												if (empty($groupuser) && !empty($groups)) {
													?>
													<?php echo gettext('clone:'); ?>
													<br />
													<select name="<?php echo $id; ?>-initgroup" onchange="javascript:$('#hint<?php echo $id; ?>').html(this.options[this.selectedIndex].title);">
														<option title=""></option>
														<?php
														foreach ($groups as $user) {
															$hint = '<em>' . html_encode($desc) . '</em>';
															if ($groupuser == $user['user']) {
																$selected = ' selected="selected"';
															} else {
																$selected = '';
															}
															?>
															<option<?php echo $selected; ?> title="<?php echo $hint; ?>"><?php echo $user['user']; ?></option>
															<?php
														}
														?>
													</select>
													<span class="hint<?php echo $id; ?>" id="hint<?php echo $id; ?>"></span><br /><br />
													<?php
												}
												?>
												<?php echo gettext('description:'); ?>
												<br />
												<textarea name="<?php echo $id; ?>-desc" cols="40" rows="4"><?php echo html_encode($desc); ?></textarea>

												<br /><br />
												<div id="users<?php echo $id; ?>" <?php if ($grouptype == 'template') echo ' style="display:none"' ?>>
													<h2 class="h2_bordered_edit"><?php echo gettext("Assign users"); ?></h2>
													<div class="box-tags-unpadded">
														<?php
														$members = array();
														if (!empty($groupuser)) {
															foreach ($adminlist as $user) {
																if ($user['valid'] && $user['group'] == $groupuser) {
																	$members[] = $user['user'];
																}
															}
														}
														?>
														<ul class="shortchecklist">
															<?php generateUnorderedListFromArray($members, $users, 'user_' . $id . '-', false, true, false); ?>
														</ul>
													</div>
												</div>
												<?php
												printManagedObjects('albums', $albumlist, '', $groupobj, $id, $kind, array());
												if (extensionEnabled('zenpage')) {
													$pagelist = array();
													$pages = $_zp_zenpage->getPages(false);
													foreach ($pages as $page) {
														if (!$page['parentid']) {
															$pagelist[get_language_string($page['title'])] = $page['titlelink'];
														}
													}
													printManagedObjects('pages', $pagelist, '', $groupobj, $id, $kind, NULL);
													$newslist = array();
													$categories = $_zp_zenpage->getAllCategories(false);
													foreach ($categories as $category) {
														$newslist[get_language_string($category['title'])] = $category['titlelink'];
													}
													printManagedObjects('news', $newslist, '', $groupobj, $id, $kind, NULL);
												}
												?>
											</span>
										</td>
										<td style="border-top: 4px solid #D1DBDF;<?php echo $background; ?>" valign="top">
											<?php
											if (!empty($groupuser)) {
												$msg = gettext('Are you sure you want to delete this group?');
												?>
												<a href="javascript:if(confirm(<?php echo "'" . $msg . "'"; ?>)) { launchScript('',['action=deletegroup','group=<?php echo addslashes($groupuser); ?>','XSRFToken=<?php echo getXSRFToken('deletegroup') ?>']); }"
													 title="<?php echo gettext('Delete this group.'); ?>" style="color: #c33;">
													<img src="../../images/fail.png" style="border: 0px;" alt="Delete" />
												</a>
												<?php
											}
											?>
										</td>
									</tr>

									<?php
									$id++;
								}
								?>
								<tr>
									<th>
										<span style="font-weight: normal">
											<a href="javascript:toggleExtraInfo('','user',true);"><?php echo gettext('Expand all'); ?></a>
											|
											<a href="javascript:toggleExtraInfo('','user',false);"><?php echo gettext('Collapse all'); ?></a>
										</span>
									</th>
									<th>
										<?php printPageSelector($pagenumber, $rangeset, PLUGIN_FOLDER . '/user_groups/user_groups-tab.php', array('page' => 'users', 'tab' => 'groups')); ?>
									</th>
									<th></th>
								</tr>
							</table>
							<p class="buttons">
								<button type="submit"><img src="../../images/pass.png" alt="" /><strong><?php echo gettext("Apply"); ?></strong></button>
								<button type="reset"><img src="../../images/reset.png" alt="" /><strong><?php echo gettext("Reset"); ?></strong></button>
							</p>
							<input type="hidden" name="totalgroups" value="<?php echo $id; ?>" />
							<input type="hidden" name="checkForPostTruncation" value="1" />
						</form>
						<script>
							function checkSubmit() {
								newgroupid = <?php echo ($id - 1); ?>;
								var c = 0;
		<?php
		foreach ($users as $name) {
			?>
									c = 0;
									for (i = 0; i <= newgroupid; i++) {
										if ($('#user_' + i + '-<?php echo postIndexEncode($name); ?>').prop('checked'))
											c++;
									}
									if (c > 1) {
										alert('<?php echo sprintf(gettext('User %s is assigned to more than one group.'), $name); ?>');
										return false;
									}
			<?php
		}
		?>
								newgroup = $('#group-' + newgroupid).val().replace(/^\s+|\s+$/g, "");
								if (newgroup == '')
									return true;
								if (newgroup.indexOf('?') >= 0 || newgroup.indexOf('&') >= 0 || newgroup.indexOf('"') >= 0 || newgroup.indexOf('\'') >= 0) {
									alert('<?php echo gettext('Group names may not contain “?”, “&”, or quotation marks.'); ?>');
									return false;
								}
								for (i = newgroupid - 1; i >= 0; i--) {
									if ($('#group-' + i).val() == newgroup) {
										alert(sprintf('<?php echo gettext('The group “%s” already exists.'); ?>', newgroup));
										return false;
									}
								}
								return true;
							}
						</script>
						<br class="clearall" />
						<?php
						break;
				}
				?>
			</div>

		</div>
	</div>
</body>
</html>