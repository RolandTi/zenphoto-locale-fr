<?php
/**
 * admin-edit.php editing of albums.
 * @package zpcore\admin
 */
// force UTF-8 Ø

/* Don't put anything before this line! */
define('OFFSET_PATH', 1);

require_once(dirname(__FILE__) . '/admin-globals.php');
//require_once(dirname(__FILE__) . '/template-functions.php');
require_once(SERVERPATH . '/' . ZENFOLDER . '/' . PLUGIN_FOLDER . '/tag_suggest.php');

admin_securityChecks(ALBUM_RIGHTS, $return = currentRelativeURL());

if (isset($_GET['tab'])) {
	$subtab = sanitize($_GET['tab']);
} else {
	$subtab = '';
}

$_zp_admin_subalbum_nesting = 1;
$_zp_admin_album_nesting = 1;
define('ADMIN_IMAGES_STEP', 5); //	the step for imges per page
$_zp_admin_imagestab_imagecount = 10;
processEditSelection($subtab);

//check for security incursions
$album = NULL;
$allow = true;
if (isset($_GET['album'])) {
	$folder = sanitize_path($_GET['album']);
	$album = AlbumBase::newAlbum($folder, false, true);
	if ($album->exists) {
		$allow = $album->isMyItem(ALBUM_RIGHTS);
		if (!$allow) {
			if (isset($_GET['uploaded'])) { // it was an upload to an album which we cannot edit->return to sender
				redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-upload.php?uploaded=1');
			}
		}
	} else {
		$album = NULL;
		unset($_GET['album']);
	}
}
if (!zp_apply_filter('admin_managed_albums_access', $allow, $return)) {
	redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin.php?from=' . $return);
}

$tagsort = getTagOrder();
$mcr_errors = array();


if (isset($_GET['showthumbs'])) { // switch the display selector
	$how = sanitize($_GET['showthumbs']);
	setOption('album_tab_default_thumbs_' . (is_object($album) ? $album->name : ''), (int) ($how == 'no'));
}
if (isset($_GET['action'])) {
	$action = sanitize($_GET['action']);
	switch ($action) {
		/** reorder the tag list ***************************************************** */
		/*		 * *************************************************************************** */
		case 'savealbumorder':
			XSRFdefender('savealbumorder');
			if ($_POST['checkallaction'] == 'noaction') {
				$notify = postAlbumSort(NULL);
				if ($notify) {
					if ($notify === true) {
						$notify = '&saved';
					} else {
						$notify = '&saved' . $notify;
					}
					$_zp_gallery->setSortDirection(0);
					$_zp_gallery->setSortType('manual');
					$_zp_gallery->save();
				} else {
					$notify = '&noaction';
				}
			} else {
				$notify = processAlbumBulkActions();
				if (empty($notify)) {
					$notify = '&noaction';
				} else {
					$notify = '&bulkmessage=' . $notify;
				}
			}
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $notify);
			break;
		case 'savesubalbumorder':
			XSRFdefender('savealbumorder');
			if ($_POST['checkallaction'] == 'noaction') {
				$notify = postAlbumSort($album->getID());
				if ($notify) {
					if ($notify === true) {
						$notify = '&saved';
					} else {
						$notify = '&saved' . $notify;
					}
					$album = AlbumBase::newAlbum($folder);
					$album->setSortType('manual', 'album');
					$album->setSortDirection(false, 'album');
					$album->setLastchangeUser($_zp_current_admin_obj->getUser());
					$album->save();
				} else {
					$notify = '&noaction';
				}
			} else {
				$notify = processAlbumBulkActions();
				if (empty($notify)) {
					$notify = '&noaction';
				} else {
					$notify = '&bulkmessage=' . $notify;
				}
			}
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit&album=' . $folder . '&tab=subalbuminfo' . $notify);
			break;
		case 'sorttags':
			if (isset($_GET['pagenumber'])) {
				$pg = '&pagenumber=' . $_GET['pagenumber'];
				$tab = '&tab=imageinfo';
			} else {
				$pg = '';
				$tab = '';
			}
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit&album=' . $folder . $pg . '&tagsort=' . html_encode($tagsort) . $tab);
			break;

		/** clear the cache ********************************************************** */
		/*		 * *************************************************************************** */
		case "clear_cache":
			XSRFdefender('clear_cache');
			if (isset($_GET['album'])) {
				$album = sanitize_path($_GET['album']);
			} else {
				$album = sanitize_path($_POST['album']);
			}
			Gallery::clearCache(SERVERCACHE . '/' . $album);
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit&cleared&album=' . $album);
			break;
		case 'comments':
			XSRFdefender('albumedit');
			$album = AlbumBase::newAlbum($folder);
			$album->setCommentsAllowed(sanitize_numeric($_GET['commentson']));
			$album->setLastchangeUser($_zp_current_admin_obj->getUser());
			$album->save();
			$return = sanitize_path($r = $_GET['return']);
			if (!empty($return)) {
				$return = '&album=' . $return;
				if (strpos($r, '*') === 0) {
					$return .= '&tab=subalbuminfo';
				}
			}
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $return);
			break;

		/** Publish album  *********************************************************** */
		/*		 * *************************************************************************** */
    case "publish":
      XSRFdefender('albumedit');
      $album = AlbumBase::newAlbum($folder);
      $album->setPublished(sanitize_numeric($_GET['value']));
			if($album->hasPublishSchedule()) {
				$album->setPublishdate(date('Y-m-d H:i:s'));
			} else if($album->hasExpiration() || $album->hasExpired()) {
				$album->setExpiredate(null);
			}
			$album->setLastchangeUser($_zp_current_admin_obj->getUser());
      $album->save();
      $return = sanitize_path($r = $_GET['return']);
      if (!empty($return)) {
        $return = '&album=' . $return;
        if (strpos($r, '*') === 0) {
          $return .= '&tab=subalbuminfo';
        }
      }
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $return);
      break;

    /** Reset hitcounters ********************************************************** */
		/*		 * ***************************************************************************** */
    case "reset_hitcounters":
      XSRFdefender('hitcounter');
      $id = sanitize_numeric($_REQUEST['albumid']);
      $where = ' WHERE `id`=' . $id;
      $imgwhere = ' WHERE `albumid`=' . $id;
      $return = sanitize_path($r = $_GET['return']);
      if (!empty($return)) {
        $return = '&album=' . $return;
        if (strpos($r, '*') === 0) {
          $return .= '&tab=subalbuminfo';
        }
      }
      $_zp_db->query("UPDATE " . $_zp_db->prefix('albums') . " SET `hitcounter`= 0" . $where);
      $_zp_db->query("UPDATE " . $_zp_db->prefix('images') . " SET `hitcounter`= 0" . $imgwhere);
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $return . '&counters_reset');
      break;

//** DELETEIMAGE **************************************************************/
    /*     * *************************************************************************** */
    case 'deleteimage':
      XSRFdefender('delete');
      $albumname = sanitize_path($_REQUEST['album']);
      $imagename = sanitize_path($_REQUEST['image']);
      $album = AlbumBase::newAlbum($albumname);
      $image = Image::newImage($album, $imagename);
      if ($image->remove()) {
        $nd = 1;
				SearchEngine::clearSearchCache();
      } else {
        $nd = 2;
      }
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit&album=' . pathurlencode($albumname) . '&ndeleted=' . $nd);
      break;

    /** REFRESH IMAGE METADATA */
    case 'refresh':
      XSRFdefender('imagemetadata');
      $albumname = sanitize_path($_REQUEST['album']);
      $imagename = sanitize_path($_REQUEST['image']);
      $image = Image::newImage(NULL, array('folder' => $albumname, 'filename' => $imagename));
      $image->updateMetaData();
			$image->setLastchangeUser($_zp_current_admin_obj->getUser());
      $image->save();
      if (isset($_GET['album'])) {
        $return = pathurlencode(sanitize_path($_GET['album']));
      } else {
        $return = pathurlencode(sanitize_path(urldecode($_POST['album'])));
      }

      $return = '?page=edit&tab=imageinfo&album=' . $return . '&metadata_refresh';
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php' . $return);
      break;

    /** SAVE ********************************************************************* */
    /*     * *************************************************************************** */
    case "save":
      unset($folder);
      $returntab = '';
      XSRFdefender('albumedit');

      /** SAVE A SINGLE ALBUM OR IMAGE ****************************************************** */
      if (isset($_POST['album'])) {
        $folder = sanitize_path($_POST['album']);
        $album = AlbumBase::newAlbum($folder);
        $notify = '';
        $returnalbum = NULL;
        if (isset($_GET['singleimage'])) {
          $filename = sanitize($_POST["0-filename"]);
          // The file might no longer exist
          $image = Image::newImage($album, $filename);
          if ($image->exists) {
            $notify = processImageEdit($image, 0, false);
            $qs_albumsuffix = '';
            $returnalbum = html_encode(pathurlencode($album->name));
            $returntab = '&tab=imageinfo&singleimage='.html_encode($image->filename);
          } // if image exists
        } else {
          if (isset($_POST['savealbuminfo'])) {
            $notify = processAlbumEdit(0, $album, $returnalbum);
            $returntab = '&tagsort=' . $tagsort . '&tab=albuminfo';
          }
          if (isset($_POST['totalimages'])) {
            if (isset($_POST['checkForPostTruncation'])) {
							$returntab = '&tagsort=' . $tagsort . '&tab=imageinfo';
							$oldsort = checkAlbumimagesort(sanitize($_POST['oldalbumimagesort'], 3));
							$oldsort_status = checkAlbumimagesort(sanitize($_POST['oldalbumimagesort_status'], 3), 'albumimagesort_status');
							if (getOption('albumimagedirection')) {
								$oldsort = $oldsort . '_desc';
							}
							$newsort = checkAlbumimagesort(sanitize($_POST['albumimagesort'], 3));
							$newsort_status = checkAlbumimagesort(sanitize($_POST['albumimagesort_status'], 3), 'albumimagesort_status');
							if ($oldsort == $newsort && $oldsort_status == $newsort_status) {
								for ($i = 0; $i < $_POST['totalimages']; $i++) {
									$filename = sanitize($_POST["$i-filename"]);
									// The file might no longer exist
									$image = Image::newImage($album, $filename);
									if ($image->exists) {
										$notify .= processImageEdit($image, $i);
									} // if image exists
								}
							} else {
								if (strpos($newsort, '_desc')) {
									setOption('albumimagesort', substr($newsort, 0, -5));
									setOption('albumimagedirection', 'DESC');
								} else {
									setOption('albumimagesort', $newsort);
									setOption('albumimagedirection', '');
								}
								setOption('albumimagesort_status', $newsort_status);
								$notify = '&';
							}
							if (isset($_POST['ids'])) { //	process bulk actions, not individual image actions.
								$action = processImageBulkActions($album);
								if (!empty($action))
									$notify = '&bulkmessage=' . $action;
							}
						} else {
              $notify = '&post_error';
            }
          }
          if (!is_null($returnalbum)) {
            $folder = $returnalbum;
          }
          $qs_albumsuffix = '';
        }
        /** SAVE MULTIPLE ALBUMS ***************************************************** */
      } else if ($_POST['totalalbums']) {
        $notify = $rslt = '';
        for ($i = 1; $i <= sanitize_numeric($_POST['totalalbums']); $i++) {
          if ($i > 0) {
            $prefix = $i . "-";
          } else {
            $prefix = '';
          }
          $f = sanitize_path(trim(sanitize($_POST[$prefix . 'folder'])));
          $album = AlbumBase::newAlbum($f);
          $returnalbum = '';
          $rslt .= processAlbumEdit($i, $album, $returnalbum);
          if (!empty($rslt)) {
            $notify = $rslt;
          }
        }
        $qs_albumsuffix = '&massedit';
        if (isset($_GET['album'])) {
          $qs_albumsuffix = '&album=' . sanitize($_GET['album']) . $qs_albumsuffix;
        }
      }
      // Redirect to the same album we saved.
      if (isset($folder) && !empty($folder)) {
        $qs_albumsuffix .= '&album=' . pathurlencode($folder);
      }
      if (isset($_POST['pagenumber'])) {
        $pg = '&pagenumber=' . sanitize($_POST['pagenumber']);
      } else {
        $pg = '';
      }
      $msg = zp_apply_filter('edit_error', '');
      if ($msg) {
        $notify .= '&edit_error=' . $msg;
      }
      if ($notify == '&') {
        $notify = '';
      } else {
        if (empty($notify))
          $notify = '&saved';
      }
			redirectURL(FULLWEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $qs_albumsuffix . $notify . $pg . $returntab);
      break;

    /** DELETION ***************************************************************** */
		/*		 * ************************************************************************** */
		case "deletealbum":
			XSRFdefender('delete');
			if ($folder) {
				$album = AlbumBase::newAlbum($folder);
				if ($album->remove()) {
					$nd = 3;
					SearchEngine::clearSearchCache();
				} else {
					$nd = 4;
				}
				if (isset($_GET['return'])) {
					$albumdir = sanitize($_GET['return'], 3);
				} else {
					$albumdir = dirname($folder);
				}
				if ($albumdir != '/' && $albumdir != '.') {
					$albumdir = "&album=" . pathurlencode($albumdir);
				} else {
					$albumdir = '';
				}
			} else {
				$albumdir = '';
			}
			redirectURL(FULLWEBPATH . "/" . ZENFOLDER . "/admin-edit.php?page=edit" . $albumdir . "&ndeleted=" . $nd);
			break;
		case 'newalbum':
			XSRFdefender('newalbum');
			$name = sanitize($_GET['name']);
			$folder = sanitize($_GET['folder']);
			$seoname = seoFriendly($name);
			if (empty($folder) || $folder == '/' || $folder == '.') {
				$albumdir = '';
				$folder = $seoname;
			} else {
				$albumdir = "&album=" . pathurlencode($folder);
				$folder = $folder . '/' . $seoname;
			}
			$uploaddir = $_zp_gallery->albumdir . internalToFilesystem($folder);
			if (is_dir($uploaddir)) {
				if ($name != $seoname)
					$name .= ' (' . $seoname . ')';
				if (isset($_GET['albumtab'])) {
					if (empty($albumdir)) {
						$tab = '';
					} else {
						$tab = '&tab=subalbuminfo';
					}
				} else {
					$tab = '&tab=albuminfo';
				}
				redirectURL(FULLWEBPATH . "/" . ZENFOLDER . "/admin-edit.php?page=edit$albumdir&exists=" . urlencode($name) . $tab);
			} else {
				mkdir_recursive($uploaddir, FOLDER_MOD);
			}
			@chmod($uploaddir, FOLDER_MOD);

			$album = AlbumBase::newAlbum($folder);
			if ($album->exists) {
				$album->setTitle($name);
				$album->save();
				redirectURL(FULLWEBPATH . "/" . ZENFOLDER . "/admin-edit.php?page=edit" . "&album=" . pathurlencode($folder));
			} else {
				$AlbumDirName = str_replace(SERVERPATH, '', $_zp_gallery->albumdir);
				zp_error(gettext("The album couldn’t be created in the “albums” folder. This is usually a permissions problem. Try setting the permissions on the albums and cache folders to be world-writable using a shell:") . " <code>chmod 777 " . $AlbumDirName . '/' . CACHEFOLDER . '/' . "</code>, "
								. gettext("or use your FTP program to give everyone write permissions to those folders."));
			}
			break;
	} // end of switch
} else {
	if (isset($_GET['albumimagesort'])) {
		$newsort = checkAlbumimagesort(sanitize($_GET['albumimagesort'],3));
		$newsort_status = '';
		if(isset($_GET['albumimagesort_status'])) {
			$newsort_status = checkAlbumimagesort(sanitize($_GET['albumimagesort_status'],3),'albumimagesort_status');
		}
		if (strpos($newsort, '_desc')) {
			setOption('albumimagesort', substr($newsort, 0, -5), false);
			setOption('albumimagedirection', 'DESC', false);
		} else {
			setOption('albumimagesort', $newsort, false);
			setOption('albumimagedirection', '', false);
		}
		if(!empty($newsort_status)) {
			setOption('albumimagesort_status',$newsort_status, false);
		}
	}
}



/* NO Admin-only content between this and the next check. */

/* * ********************************************************************************* */
/** End Action Handling ************************************************************ */
/* * ********************************************************************************* */

// Print our header
if (isset($_GET['album']) && !isset($_GET['massedit'])) {
	$folder = sanitize_path($_GET['album']);
	if ($folder == '/' || $folder == '.') {
		$parent = '';
	} else {
		$parent = '&amp;album=' . $folder . '&amp;tab=subalbuminfo';
	}
	$album = AlbumBase::newAlbum($folder);
	$subtab = setAlbumSubtabs($album);
}
if (empty($subtab)) {
	if (isset($_GET['album'])) {
		$subtab = 'albuminfo';
	}
}

printAdminHeader('edit', $subtab);
datepickerJS();
codeblocktabsJS();

if ((!isset($_GET['massedit']) && !isset($_GET['album'])) || $subtab == 'subalbuminfo') {
	printSortableHead();
}
if (isset($_GET['album']) && (empty($subtab) || $subtab == 'albuminfo') || isset($_GET['massedit'])) {
	$result = $_zp_db->getFields('albums');
	$dbfields = array();
	if ($result) {
		foreach ($result as $row) {
			$dbfields[] = "'" . $row['Field'] . "'";
		}
	}
	sort($dbfields);
	$albumdbfields = implode(',', $dbfields);
	$result = $_zp_db->getFields('images');
	$dbfields = array();
	if ($result) {
		foreach ($result as $row) {
			$dbfields[] = "'" . $row['Field'] . "'";
		}
	}
	sort($dbfields);
	$imagedbfields = implode(',', $dbfields);
	?>
	<script src="js/encoder.js"></script>
	<script src="js/tag.js"></script>
	<script>
						var albumdbfields = [<?php echo $albumdbfields; ?>];
						$(function() {
						$('.customalbumsort').tagSuggest({
						tags: albumdbfields
						});
						});
						var imagedbfields = [<?php echo $imagedbfields; ?>];
						$(function() {
						$('.customimagesort').tagSuggest({
						tags: imagedbfields
						});
						});
	</script>
	<?php
}
?>
<script>
					var deleteAlbum1 = "<?php echo gettext("Are you sure you want to delete this entire album?"); ?>";
					var deleteAlbum2 = "<?php echo gettext("Are you Absolutely Positively sure you want to delete the album? THIS CANNOT BE UNDONE!"); ?>";
					function newAlbum(folder, albumtab) {
        var album = prompt('<?php echo addslashes(gettext('New album name?')); ?>', '<?php echo addslashes(gettext('new album')); ?>');
									if (album) {
          launchScript('', ['action=newalbum', 'folder=' + folder, 'name=' + encodeURIComponent(album), 'albumtab=' + albumtab, 'XSRFToken=<?php echo getXSRFToken('newalbum'); ?>']);
        	}
					}
     	function newDynAlbum(folder, albumtab) {
        var album = prompt('<?php echo addslashes(gettext('New dynamic album name?')); ?>', '<?php echo addslashes(gettext('new dynamic album')); ?>');
									if (album) {
          launchScript('admin-dynamic-album.php', ['s=' + encodeURIComponent(album), 'folder=' + encodeURIComponent(folder), 'XSRFToken=<?php echo getXSRFToken('newalbum'); ?>']);
        	}
					}
	function confirmAction() {
  if ($('#checkallaction').val() == 'deleteall') {
    return confirm('<?php echo js_encode(gettext("Are you sure you want to delete the checked items?")); ?>');
  } else if ($('#checkallaction').val() == 'deleteallalbum') {
    if (confirm(deleteAlbum1)) {
      return confirm(deleteAlbum2);
    } else {
      return false;
    }
  } else {
    return true;
  }
	}
</script>

<?php
zp_apply_filter('texteditor_config', 'zenphoto');
Authority::printPasswordFormJS();

echo "\n</head>";
?>

<body>

	<?php printLogoAndLinks(); ?>
	<div id="main">
		<?php printTabs(); ?>
		<div id="content">
			<?php
			$showthumb = !getOption('album_tab_default_thumbs_' . (is_object($album) ? $album->name : ''));
			if ($showthumb) {
				$thumbshow = 'no';
				$thumbmsg = gettext('Show thumbnail stand-in');
			} else {
				$thumbshow = 'yes';
				$thumbmsg = gettext('Show album thumb');
			}
			$checkarray_base = array(
							gettext('*Bulk actions*')			 => 'noaction',
							gettext('Delete')							 => 'deleteall',
							gettext('Set to published')		 => 'showall',
							gettext('Set to unpublished')	 => 'hideall',
							gettext('Add tags')						 => 'addtags',
							gettext('Clear tags')					 => 'cleartags',
							gettext('Change owner')				 => 'changeowner'
			);
			if (extensionEnabled('comment_form')) {
				$checkarray_base[gettext('Disable comments')] = 'commentsoff';
				$checkarray_base[gettext('Enable comments')] = 'commentson';
			}
			if (extensionEnabled('hitcounter')) {
				$checkarray_base[gettext('Reset hitcounter')] = 'resethitcounter';
			}
			$checkarray_albums = array_merge($checkarray_base, array(
							gettext('Delete')								 => 'deleteallalbum',
							gettext('Add tags to images')		 => 'alltags',
							gettext('Clear tags of images')	 => 'clearalltags'));
			$checkarray_images = array_merge($checkarray_base, array(
							gettext('Delete')	 => 'deleteall',
							gettext('Move')		 => 'moveimages',
							gettext('Copy')		 => 'copyimages')
			);
			$checkarray_images = zp_apply_filter('bulk_image_actions', $checkarray_images);
			$checkarray_albums = zp_apply_filter('bulk_album_actions', $checkarray_albums);

			/** EDIT ***************************************************************************
			 *
			 *  ********************************************************************************
			 */
			if (isset($_GET['album']) && !isset($_GET['massedit'])) {
				/** SINGLE ALBUM ******************************************************************* */
				// one time generation of this list.
				$_zp_admin_mcr_albumlist = $_zp_gallery->getAllAlbumsFromDB(false, NULL, UPLOAD_RIGHTS, true, false, true);
				$oldalbumimagesort = getOption('albumimagesort');
				$oldalbumimagesort_status = getOption('albumimagesort_status');
				$direction = getOption('albumimagedirection');
				if ($album->isDynamic()) {
					$subalbums = array();
					$allimages = array();
				} else {
					$subalbums = getNestedAlbumList($album, $_zp_admin_subalbum_nesting);
					$allimages = $album->getImages(0, 0, $oldalbumimagesort, $direction);
					if($oldalbumimagesort_status !== 'all') {
						$allimages_edit = array();
						foreach($allimages as $filename) {
							$imgobj = Image::newImage($album, $filename);
							if($oldalbumimagesort_status == 'published' && $imgobj->isPublished()) {
								$allimages_edit[] = $filename;
							} 
							if($oldalbumimagesort_status == 'unpublished' && !$imgobj->isPublished()) {
								$allimages_edit[] = $filename;
							}
						}
						if(!empty($allimages_edit)) {
							$allimages = $allimages_edit;
						}
					} 
					if (!($album->albumSubRights() & MANAGED_OBJECT_RIGHTS_EDIT)) {
						$allimages = array();
						$requestor = $_zp_current_admin_obj->getUser();
						$albumowner = $album->getOwner();
						if ($albumowner == $requestor) {
							$retunNull = '`owner` IS NULL OR ';
						} else {
							$retunNull = '';
						}
						$status = '';
						if($oldalbumimagesort_status == 'published') {
							$status = ' (`show` = 1)';
						}
						if($oldalbumimagesort_status == 'unpublished') {
							$status = ' (`show` = 0)';
						}
						$sql = 'SELECT * FROM ' . $_zp_db->prefix('images') . ' WHERE (`albumid`=' . $album->getID() . ') AND (' . $retunNull . ' `owner`="' . $requestor . '")' . $status . ' ORDER BY `' . $oldalbumimagesort . '` ' . $direction;
						$result = $_zp_db->query($sql);
						if ($result) {
							while ($row = $_zp_db->fetchAssoc($result)) {
								$allimages[] = $row['filename'];
							}
							$_zp_db->freeResult($result);
						}
					}
				}
				$allimagecount = count($allimages);
				if (isset($_GET['tab']) && $_GET['tab'] == 'imageinfo' && isset($_GET['image'])) { // directed to an image
					$target_image = urldecode(sanitize($_GET['image']));
					$imageno = array_search($target_image, $allimages);
					if ($imageno !== false) {
						$pagenum = ceil(($imageno + 1) / $_zp_admin_imagestab_imagecount);
					}
				} else {
					$target_image = '';
				}
				if (!isset($pagenum)) {
					if (isset($_GET['subpage'])) {
						$pagenum = max(intval($_GET['subpage']), 1);
						if (($pagenum - 1) * $_zp_admin_imagestab_imagecount >= $allimagecount)
							$pagenum--;
					} else {
						if(isset($_GET['nopagination'])) {
							$pagenum = 0;
						} else {
							$pagenum = 1;
						}
					}
				}
				if ($pagenum == 0 || isset($_GET['singleimage'])) {
					$images = $allimages;
				} else {
					$images = array_slice($allimages, ($pagenum - 1) * $_zp_admin_imagestab_imagecount, $_zp_admin_imagestab_imagecount);
				}
				$totalimages = count($images);

				if (isset($_GET['singleimage'])) {
					$parent = $album->name;
				} else {
					$parent = dirname($album->name);
				}
				if (($parent == '/') || ($parent == '.') || empty($parent)) {
					$parent = '';
				} else {
					$parent = "&amp;album=" . pathurlencode($parent);
				}

				if (isset($_GET['metadata_refresh'])) {
					echo '<div class="messagebox fade-message">';
					echo "<h2>" . gettext("Image metadata refreshed.") . "</h2>";
					echo '</div>';
				}

				if ($album->getParent()) {
					$link = getAlbumBreadcrumbAdmin($album);
				} else {
					$link = '';
				}
				$alb = removeParentAlbumNames($album);
				?>
				<h1><?php printf(gettext('Edit Album: <em>%1$s%2$s</em>'), $link, $alb); ?></h1>
				<?php
				$subtab = printSubtabs();
				if ($subtab == 'albuminfo') {
					?>
					<!-- Album info box -->
					<div id="tab_albuminfo" class="tabbox">
						<?php 
						consolidatedEditMessages('albuminfo');
						printStatusNotes($album);
						?>
						<form class="dirty-check" name="albumedit1" id="form_albumedit" autocomplete="off" action="?page=edit&amp;action=save<?php echo "&amp;album=" . pathurlencode($album->name); ?>" method="post">
							<?php XSRFToken('albumedit'); ?>
							<input type="hidden" name="album"	value="<?php echo $album->name; ?>" />
							<input type="hidden"	name="savealbuminfo" value="1" />
							<?php printAlbumEditForm(0, $album); ?>
						</form>
						<br class="clearall" />
						<hr />
					</div>
					<?php
				} else if ($subtab == 'subalbuminfo' && !$album->isDynamic()) {
					?>
					<!-- Subalbum list goes here -->
					<?php
					if (count($subalbums) > 0) {
						$enableEdit = $album->albumSubRights() & MANAGED_OBJECT_RIGHTS_EDIT;
						?>
						<div id="tab_subalbuminfo" class="tabbox">
							<?php
							printEditDropdown('subalbuminfo', array('1', '2', '3', '4', '5'), $_zp_admin_subalbum_nesting);
							?>
							<form class="dirty-check" action="?page=edit&amp;album=<?php echo pathurlencode($album->name); ?>&amp;action=savesubalbumorder&amp;tab=subalbuminfo" method="post" name="sortableListForm" id="sortableListForm" onsubmit="return confirmAction();" autocomplete="off">
								<?php XSRFToken('savealbumorder'); ?>
								<p>
									<?php
									$sorttype = strtolower($album->getSortType('album'));
									if ($sorttype != 'manual') {
										if ($album->getSortDirection('album')) {
											$dir = gettext(' descending');
										} else {
											$dir = '';
										}
										$sortNames = getSortByOptions('albums');
										$sortNames = array_flip($sortNames);
										if(array_key_exists($sorttype, $sortNames)) {
											$sorttype = $sortNames[$sorttype];
										} 
									} else {
										$dir = '';
									}
									printf(gettext('Current sort: <em>%1$s%2$s</em>. '), $sorttype, $dir);
									?>
								</p>
								<p>
									<?php echo gettext('Drag the albums into the order you wish them displayed.'); ?>
								</p>
								<p class="notebox">
									<?php echo gettext('<strong>Note:</strong> Dragging an album under a different parent will move the album. You cannot move albums under a <em>dynamic</em> album.'); ?>
								</p>
								<?php
								if ($enableEdit) {
									?>
									<p>
										<?php printf(gettext('Select an album to edit its description and data, or <a href="?page=edit&amp;album=%s&amp;massedit">mass-edit</a> all first level subalbums.'), pathurlencode($album->name)); ?>
									</p>
									<?php
								}
								consolidatedEditMessages('subalbuminfo');
								?>
								<span class="buttons">
									<a href="<?php echo WEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $parent; ?>">
										<img	src="images/arrow_left_blue_round.png" alt="" />
										<strong><?php echo gettext("Back"); ?></strong>
									</a>
									<?php
									if ($enableEdit) {
										?>
										<button class="serialize buttons" type="submit">
											<img src="images/pass.png" alt="" />
											<strong><?php echo gettext("Apply"); ?></strong>
										</button>
										<div class="floatright">
											<button type="button" title="<?php echo addslashes(gettext('New subalbum')); ?>" onclick="javascript:newAlbum('<?php echo pathurlencode($album->name); ?>', false);">
												<img src="images/folder.png" alt="" />
												<strong><?php echo gettext('New subalbum'); ?></strong>
											</button>
           <?php if(!$album->isDynamic()) { ?>
            	<button type="button" title="<?php echo addslashes(gettext('New dynamic subalbum')); ?>" onclick="javascript:newDynAlbum('<?php echo pathurlencode($album->name); ?>', false);">
												<img src="images/folder.png" alt="" />
												<strong><?php echo gettext('New dynamic subalbum'); ?></strong>
											</button>
           <?php } ?>
										</div>
										<?php
									}
									?>
								</span>
								<br class="clearall" /><br />
								<div class="bordered">
									<div class="headline" style="text-align: left;"><?php echo gettext("Edit this album"); ?>
										<?php
										if ($enableEdit) {
											printBulkActions($checkarray_albums);
										}
										?>
									</div>
									<div class="subhead">
										<label class="buttons" style="float: left">
											<a href="admin-edit.php?page=edit&amp;album=<?php echo html_encode(pathurlencode($album->name)); ?>&amp;tab=subalbuminfo&amp;showthumbs=<?php echo $thumbshow ?>" title="<?php echo addslashes(gettext('Thumbnail generation may be time consuming on slow servers or when there are a lot of images.')); ?>">
												<?php echo $thumbmsg; ?>
											</a>
										</label>
										<?php
										if ($enableEdit) {
											?>
											<label style="float: right"><?php echo gettext("Check All"); ?> <input type="checkbox" name="allbox" id="allbox" onclick="checkAll(this.form, 'ids[]', this.checked);" />
											</label>
											<?php
										}
										?>
									</div>

									<ul class="page-list">
										<?php
										printNestedAlbumsList($subalbums, $showthumb, $album);
										?>
									</ul>

								</div>
								<br class="clearall" /><br class="clearall" />
								<?php printAlbumLegend(); ?>
								<span id="serializeOutput"></span>
								<input name="update" type="hidden" value="Save Order" />
								<br />
								<span class="buttons">
									<a href="<?php echo WEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $parent; ?>">
										<img	src="images/arrow_left_blue_round.png" alt="" />
										<strong><?php echo gettext("Back"); ?></strong>
									</a>
									<button class="serialize buttons" type="submit">
										<img src="images/pass.png" alt="" />
										<strong><?php echo gettext("Apply"); ?></strong>
									</button>
									<div class="floatright">
										<button type="button" title="<?php echo addslashes(gettext('New subalbum')); ?>" onclick="javascript:newAlbum('<?php echo pathurlencode($album->name); ?>', false);">
											<img src="images/folder.png" alt="" />
											<strong><?php echo gettext('New subalbum'); ?></strong>
										</button>
											<?php if(!$album->isDynamic()) { ?>
            									<button type="button" title="<?php echo addslashes(gettext('New dynamic subalbum')); ?>" onclick="javascript:newDynAlbum('<?php echo pathurlencode($album->name); ?>', false);">
												<img src="images/folder.png" alt="" />
												<strong><?php echo gettext('New dynamic subalbum'); ?></strong>
											</button>
										<?php } ?>
									</div>
								</span>
							</form>
							<br class="clearall" />
						</div><!-- subalbum -->
						<?php
					}
					?>
					<?php
				} else if ($subtab == 'imageinfo') {
					$singleimage = NULL;
					if (isset($_GET['singleimage'])) {
						$simage = sanitize($_GET['singleimage']);
						$imageno = array_search($simage, $images);
						if ($imageno !== false) {
							$pagenum = ceil(($imageno + 1) / $_zp_admin_imagestab_imagecount);
						}
						$parent .= '&amp;tab=imageinfo&amp;subpage='.$pagenum.'&amp;image='.html_encode($simage).'#IT';
						if (array_search($simage, $images) !== false) {
							$allimagecount = 1;
							$singleimage = $simage;
							$images = array($simage);
						}
					}
					?>
					<!-- Images List -->
					<div id="tab_imageinfo" class="tabbox">
						<?php
						$albumHeritage = array();
						$t = explode('/', $album->name);
						While (!empty($t)) {
							$name = implode('/', $t);
							array_pop($t);
							$albumHeritage[' ' . str_repeat('» ', count($t)) . basename($name)] = $name;
						}
						consolidatedEditMessages('imageinfo');
						if (!$singleimage) {
							$numsteps = ceil(max($allimagecount, $_zp_admin_imagestab_imagecount) / ADMIN_IMAGES_STEP);
							if ($numsteps) {
								$steps = array();
								for ($i = 1; $i <= $numsteps; $i++) {
									$steps[] = $i * ADMIN_IMAGES_STEP;
								}
								?>
								<div style="padding-bottom:10px;">
									<?php printEditDropdown('imageinfo', $steps, $_zp_admin_imagestab_imagecount); ?>
								</div>
								<br style='clear:both'/>
								<?php
							}
						}
						if ($allimagecount) {
					     if ($singleimage) { ?>
								<form class="dirty-check" name="albumedit2"	id="form_imageedit" action="?page=edit&amp;action=save<?php echo "&amp;album=" . html_encode(pathurlencode($album->name)); ?>&amp;singleimage=<?php html_encode($singleimage); ?>&amp;nopagination" method="post" autocomplete="off">
					        <?php } else {  ?>
					          	<form class="dirty-check" name="albumedit2"	id="form_imageedit" action="?page=edit&amp;action=save<?php echo "&amp;album=" . html_encode(pathurlencode($album->name)); ?>" method="post" autocomplete="off">
					        	<input type="hidden" name="subpage" value="<?php echo html_encode($pagenum); ?>" />
					        <?php } ?>
				        		<?php XSRFToken('albumedit'); ?>
								<input type="hidden" name="album"	value="<?php echo $album->name; ?>" />
								<input type="hidden" name="totalimages" value="<?php echo $totalimages; ?>" />
								<input type="hidden" name="tagsort" value="<?php echo html_encode($tagsort); ?>" />
								<input type="hidden" name="oldalbumimagesort" value="<?php echo html_encode($oldalbumimagesort); ?>" />
								<input type="hidden" name="oldalbumimagesort_status" value="<?php echo html_encode($oldalbumimagesort_status); ?>" />
        				<!-- <input type="hidden" name="albumimagesort" value="" /> -->

								<?php $totalpages = ceil(($allimagecount / $_zp_admin_imagestab_imagecount)); ?>
								<table class="bordered">
									<?php
									if (!$singleimage) {
										?>
										<tr>
											<td><?php echo gettext("Click on the image to change the thumbnail cropping."); ?>	</td>

											<td align="right">
												<?php
												$sort = getSortByOptions('images-edit');
												ksort($sort, SORT_LOCALE_STRING);
												if ($direction)
													$oldalbumimagesort = $oldalbumimagesort . '_desc';
												echo '<select id="albumimagesort" name="albumimagesort" onchange="this.form.submit()">';
												generateListFromArray(array($oldalbumimagesort), $sort, false, true);
												echo '</select>';
												$sort_status = getSortByStatusOptions();
												echo '<select id="albumimagesort_status" name="albumimagesort_status" onchange="this.form.submit()">';
												generateListFromArray(array($oldalbumimagesort_status), $sort_status, false, true);
												echo '</select>';
												?>
				
											</td>
										</tr>
										<?php
										if ($allimagecount != $totalimages) { // need pagination links
											?>
											<tr>
												<td colspan="4" class="bordered" id="imagenav"><?php adminPageNav($pagenum, $totalpages, 'admin-edit.php', '?page=edit&amp;tagsort=' . html_encode($tagsort) . '&amp;album=' . html_encode(pathurlencode($album->name)), '&amp;tab=imageinfo'); ?>
												</td>
											</tr>
											<?php
										}
									}
									?>
									<tr>
										<td colspan="4">
											<p class="buttons">
												<a href="<?php echo WEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $parent; ?>">
													<img	src="images/arrow_left_blue_round.png" alt="" />
													<strong><?php echo gettext("Back"); ?></strong>
												</a>
												<button type="submit">
													<img src="images/pass.png" alt="" />
													<strong><?php echo gettext("Apply"); ?></strong>
												</button>
												<button type="reset">
													<img src="images/fail.png" alt="" />
													<strong><?php echo gettext("Reset"); ?></strong>
												</button>
											</p>

											<?php if (!$singleimage) printBulkActions($checkarray_images, true); ?>


										</td>
									</tr>
									<?php
									$currentimage = 0;
									if ($_zp_graphics->imageCanRotate()) {
										$disablerotate = '';
									} else {
										$disablerotate = ' disabled="disabled"';
									}
									$target_image_nr = '';
									$thumbnail = $album->get('thumb');
									foreach ($images as $filename) {
										$image = Image::newImage($album, $filename);
										?>

										<tr <?php echo ($currentimage % 2 == 0) ? "class=\"alt\"" : ""; ?>>
											<?php
											if ($target_image == $filename) {
												$placemark = 'id="IT" ';
												$target_image_nr = $currentimage;
											} else {
												$placemark = '';
											}
											?>
											<td colspan="4">
												<input type="hidden" name="<?php echo $currentimage; ?>-filename"	value="<?php echo $image->filename; ?>" />
												<table style="border:none" class="formlayout" id="image-<?php echo $currentimage; ?>">
													<?php if(checkSchedulePublishingNotes($image)) { ?>
														<tr>
															<td colspan="5"><?php printStatusNotes($image); ?></td>
														</tr>
													<?php } ?>
													<tr>
														<td valign="top" rowspan="17" style="border-bottom:none;">
															<div style="width: 135px;" <?php echo $placemark; ?>>
																<?php
																if ($image->isPhoto()) {
																	?>
																	<a href="<?php echo html_encode(pathurlencode($image->getFullImageURL())); ?>" class="colorbox adminedit_fullimagelink">
																		<img src="images/magnify.png" alt="" class="adminedit_fullimage-icon" />
																	<?php
																}
																printAdminThumb($image, 'large-uncropped', '', 'thumb_img-'.$currentimage, '', $image->filename);
																if ($image->isPhoto()) {
																	?>
																	</a>
																	<?php
																}
																?>
																<p class="buttons clearfix">
																	<a href="<?php echo $image->getLink(); ?>"><img src="images/view.png" alt=""/><strong><?php echo gettext('View image'); ?></strong></a>
																<p>
															</div>
															<p>
																<?php echo gettext('<strong>Filename:</strong>'); ?>
																<br />
																<?php
																echo $image->filename;
																?>
															</p>
															<p><?php echo gettext('<strong>Image id:</strong>'); ?> <?php echo $image->getID(); ?></p>
															<p><?php echo gettext("<strong>Dimensions:</strong>"); ?><br /><?php echo $image->getWidth(); ?> x  <?php echo $image->getHeight() . ' ' . gettext('px'); ?></p>
															<p><?php echo gettext("<strong>Size:</strong>"); ?><br /><?php echo byteConvert($image->getImageFootprint()); ?></p>
														</td>
														<td align="left" valign="top"><?php echo gettext("Owner:"); ?></td>
														<td style="width:100%;">
															<?php
															if (zp_loggedin(MANAGE_ALL_ALBUM_RIGHTS)) {
																?>
																<select name="<?php echo $currentimage; ?>-owner">
																	<?php echo admin_album_list($image->getOwner()); ?>
																</select>
																<?php
															} else {
																echo $image->getOwner();
															}
															?>
														</td>
														<td style="padding-left: 1em; text-align: left; border-bottom:none;" rowspan="14" valign="top">
															<h2 class="h2_bordered_edit"><?php echo gettext("General"); ?></h2>
															<div class="box-edit">
																<?php 
																	if($image->hasPublishSchedule()) {
																		$publishlabel = '<span class="scheduledate">' . gettext('Publishing scheduled') . '</span>';
																	} else {
																		$publishlabel = gettext("Published");
																	}
																?>
																<label class="checkboxlabel">
																	<input type="checkbox" id="Visible-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-Visible" value="1" <?php if ($image->get('show', false)) echo ' checked="checked"'; ?> />
																	<?php echo $publishlabel; ?>
																</label>
																<?php if(extensionEnabled('comment_form')) { ?>
																	<label class="checkboxlabel">
																		<input type="checkbox" id="allowcomments-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-allowcomments" value="1" <?php
																		if ($image->getCommentsAllowed()) {
																			echo ' checked="checked"';
																		}
																		?> />
																					 <?php echo gettext("Comments enabled"); ?>
																	</label>
																<?php
																}
																if (extensionEnabled('hitcounter')) {
																	$hc = $image->get('hitcounter');
																	if (empty($hc)) {
																		$hc = '0';
																	}
																	?>
																	<label class="checkboxlabel">
																		<input type="checkbox" name="reset_hitcounter<?php echo $currentimage; ?>"<?php if (!$hc) echo ' disabled="disabled"'; ?> />
																		<?php echo sprintf(ngettext("Reset hitcounter (%u hit)", "Reset hitcounter (%u hits)", $hc), $hc); ?>
																	</label>
																	<?php
																}
																if (extensionEnabled('rating')) {
																	$tv = $image->get('total_value');
																	$tc = $image->get('total_votes');

																	if ($tc > 0) {
																		$hc = $tv / $tc;
																		?>
																		<label class="checkboxlabel">
																			<input type="checkbox" id="reset_rating-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-reset_rating" value="1" />
																			<?php printf(ngettext('Reset rating (%u star)', 'Reset rating (%u stars)', $hc), $hc); ?>
																		</label>
																		<?php
																	} else {
																		?>
																		<label class="checkboxlabel">
																			<input type="checkbox" id="reset_rating-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-reset_rating" value="1" disabled="disabled"/>
																			<?php echo gettext('Reset rating (unrated)'); ?>
																		</label>
																		<?php
																	}
																}
																$publishdate = $image->getPublishDate();
																$expirationdate = $image->getExpireDate();
																?>
																<script>
																					$(function() {
																					$("#publishdate-<?php echo $currentimage; ?>,#expirationdate-<?php echo $currentimage; ?>").datepicker({
																					dateFormat: 'yy-mm-dd',
																									showOn: 'button',
																									buttonImage: '../zp-core/images/calendar.png',
																									buttonText: '<?php echo gettext("calendar"); ?>',
																									buttonImageOnly: true
																					});
																									$('#publishdate-<?php echo $currentimage; ?>').change(function() {
																					var today = new Date();
																									var pub = $('#publishdate-<?php echo $currentimage; ?>').datepicker('getDate');
																									if (pub.getTime() > today.getTime()) {
																					$(".scheduledpublishing-<?php echo $currentimage; ?>").html('<br /><?php echo addslashes(gettext('Future publishing date.')); ?>');
																					} else {
																					$(".scheduledpublishing-<?php echo $currentimage; ?>").html('');
																					}
																					});
																									$('#expirationdate-<?php echo $currentimage; ?>').change(function() {
																					var today = new Date();
																									var expiry = $('#expirationdate-<?php echo $currentimage; ?>').datepicker('getDate');
																									if (expiry.getTime() > today.getTime()) {
																					$(".expire<-<?php echo $currentimage; ?>").html('');
																					} else {
																					$(".expire-<?php echo $currentimage; ?>").html('<br /><?php echo addslashes(gettext('Expired!')); ?>');
																					}
																					});
																					});
																</script>
																<br class="clearall" />
																<hr />
																<p>
																	<label for="publishdate-<?php echo $currentimage; ?>"><?php echo gettext('Publish date'); ?> <small>(YYYY-MM-DD)</small></label>
																	<br /><input value="<?php echo $publishdate; ?>" type="text" size="20" maxlength="30" name="publishdate-<?php echo $currentimage; ?>" id="publishdate-<?php echo $currentimage; ?>" />
																	<strong class="scheduledpublishing-<?php echo $currentimage; ?>">
																		<?php
																		if ($image->hasPublishSchedule()) {
																			echo '<br><span class="scheduledate">' . gettext('Future publishing date.') . '</span>';
																		}
																		?>
																	</strong>
																	<br /><br />
																	<label for="expirationdate-<?php echo $currentimage; ?>"><?php echo gettext('Expiration date'); ?> <small>(YYYY-MM-DD)</small></label>
																	<br /><input value="<?php echo $expirationdate; ?>" type="text" size="20" maxlength="30" name="expirationdate-<?php echo $currentimage; ?>" id="expirationdate-<?php echo $currentimage; ?>" />
																	<strong class="expired expire-<?php echo $currentimage; ?>">
																		<?php
																		if ($image->hasExpiration()) {
																			echo '<br><span class="expiredate">' . gettext('Expiration set') . '</span>';
																		}
																		if ($image->hasExpired()) {
																			echo '<br><span class="expired">' . gettext('Expired!') . '</span>';
																		}
																		?>
																	</strong>
																</p>
																<?php	printLastChangeInfo($image); ?>
															</div>

															<h2 class="h2_bordered_edit"><?php echo gettext("Utilities"); ?></h2>
															<div class="box-edit">
																<!-- Move/Copy/Rename this image -->
																<label class="checkboxlabel">
																	<input type="radio" id="move-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-MoveCopyRename" value="move"
																				 onclick="toggleMoveCopyRename('<?php echo $currentimage; ?>'
																																					 , 'move');"  /> <?php echo gettext("Move"); ?>
																</label>
																<label class="checkboxlabel">
																	<input type="radio" id="copy-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-MoveCopyRename" value="copy"
																				 onclick="toggleMoveCopyRename('<?php echo $currentimage; ?>'
																																					 , 'copy');"  /> <?php echo gettext("Copy"); ?>
																</label>
																<label class="checkboxlabel">
																	<input type="radio" id="rename-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-MoveCopyRename" value="rename"
																				 onclick="toggleMoveCopyRename('<?php echo $currentimage; ?>',
																																					 'rename');"  /> <?php echo gettext("Rename File"); ?>
																</label>
																<label class="checkboxlabel">
																	<input type="radio" id="Delete-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-MoveCopyRename" value="delete"
																				 onclick="toggleMoveCopyRename('<?php echo $currentimage; ?>', '');
																																					 deleteConfirm('Delete-<?php echo $currentimage; ?>', '<?php echo $currentimage; ?>', '<?php echo addslashes(gettext("Are you sure you want to select this image for deletion?")); ?>')" /> <?php echo gettext("Delete image") ?>
																</label>
																<br class="clearall" />
																<div id="movecopydiv-<?php echo $currentimage; ?>" style="padding-top: .5em; padding-left: .5em; display: none;">
																	<?php echo gettext("to"); ?>:
																	<select id="albumselectmenu-<?php echo $currentimage; ?>"
																					name="<?php echo $currentimage; ?>-albumselect" onchange="">
																						<?php
																						foreach ($_zp_admin_mcr_albumlist as $fullfolder => $albumtitle) {
																							$singlefolder = $fullfolder;
																							$saprefix = "";
																							$selected = "";
																							if ($album->name == $fullfolder) {
																								$selected = " selected=\"selected\" ";
																							}
																							// Get rid of the slashes in the subalbum, while also making a subalbum prefix for the menu.
																							while (strstr($singlefolder, '/') !== false) {
																								$singlefolder = substr(strstr($singlefolder, '/'), 1);
																								$saprefix = "–&nbsp;" . $saprefix;
																							}
																							echo '<option value="' . $fullfolder . '"' . "$selected>" . $saprefix . $singlefolder . "</option>\n";
																						}
																						?>
																	</select>
																	<br /><p class="buttons"><a href="javascript:toggleMoveCopyRename('<?php echo $currentimage; ?>', '');"><img src="images/reset.png" alt="" /><?php echo gettext("Cancel"); ?></a>
																	</p>
																</div>
																<div id="renamediv-<?php echo $currentimage; ?>" style="padding-top: .5em; padding-left: .5em; display: none;">
																	<?php echo gettext("to"); ?>:
																	<input name="<?php echo $currentimage; ?>-renameto" type="text" value="<?php echo $image->filename; ?>" /><br />
																	<br /><p class="buttons"><a	href="javascript:toggleMoveCopyRename('<?php echo $currentimage; ?>', '');"><img src="images/reset.png" alt="" /><?php echo gettext("Cancel"); ?></a>
																	</p>
																</div>
																<span class="clearall" ></span>
																<div id="deletemsg<?php echo $currentimage; ?>"	style="padding-top: .5em; padding-left: .5em; color: red; display: none">
																	<?php echo gettext('Image will be deleted when changes are applied.'); ?>
																	<p class="buttons"><a	href="javascript:toggleMoveCopyRename('<?php echo $currentimage; ?>', '');"><img src="images/reset.png" alt="" /><?php echo gettext("Cancel"); ?></a></p>
																</div>
																<span class="clearall" ></span>

																<?php
																if ($image->isPhoto()) {
																	printImageRotationSelector($image, $currentimage);
																}
																?>
																<br class="clearall" />
																<hr />
																<div class="button buttons tooltip" title="<?php printf(gettext('Refresh %s metadata'), $image->filename); ?>">
																	<a href="admin-edit.php?action=refresh&amp;album=<?php echo html_encode(pathurlencode($album->name)); ?>&amp;image=<?php echo urlencode($image->filename); ?>&amp;subpage=<?php echo $pagenum; ?>&amp;tagsort=<?php echo html_encode($tagsort); ?>&amp;XSRFToken=<?php echo getXSRFToken('imagemetadata'); ?>" class="js_confirm_metadata_refresh_<?php echo $currentimage; ?>">
																		<img src="images/cache.png" alt="" /><?php echo gettext("Refresh Metadata"); ?>
																	</a>
																	<br class="clearall" />
																</div>
																<script>
																	$( document ).ready(function() {
																		var element = '.js_confirm_metadata_refresh_<?php echo $currentimage; ?>';
																		var message = '<?php echo js_encode(gettext('Refreshing metadata will overwrite existing data. This cannot be undone!')); ?>';
																		confirmClick(element, message);
																	});
																</script>
																<?php
																if (($image->isPhoto() || !is_null($image->objectsThumb)) && getOption('thumb_crop')) {
																	?>
																	<div class="button buttons tooltip" title="<?php printf(gettext('crop %s'), $image->filename); ?>">
																		<a href="admin-thumbcrop.php?a=<?php echo html_encode(pathurlencode($album->name)); ?>&amp;i=<?php echo urlencode($image->filename); ?>&amp;subpage=<?php echo $pagenum; ?>&amp;tagsort=<?php echo html_encode($tagsort); ?>" >
																			<img src="images/shape_handles.png" alt="" /><?php echo gettext("Crop thumbnail"); ?>
																		</a>
																		<br class="clearall" />
																	</div>
																	<?php
																}
																echo zp_apply_filter('edit_image_utilities', '<!--image-->', $image, $currentimage, $pagenum, $tagsort); //pass space as HTML because there is already a button shown for cropimage
																?>
																<span class="clearall" ></span>
															</div>
														</td>
														<td class = "bulk_checkbox">
															<?php
															if (!$singleimage) {
																?>
																<div class = "page-list_icon">
																	<input class = "checkbox" type = "checkbox" name = "ids[]" value = "<?php echo $image->getName(); ?>" onclick = "triggerAllBox(this.form, 'ids[]', this.for
																																			m.allbox);" />
																</div>
																<?php
															}
															?>
														</td>
													</tr>
													<tr>
														<td align = "left" valign = "top"><?php echo gettext("Title:");
															?></td>
														<td><?php print_language_string_list($image->getTitle('all'), $currentimage . '-title', false, NULL, '', '100%'); ?>
													</tr>

													<tr>
														<td align="left" valign="top"><?php echo gettext("Description:"); ?></td>
														<td><?php print_language_string_list($image->getDesc('all'), $currentimage . '-desc', true, NULL, 'texteditor', '100%'); ?></td>
													</tr>

													<?php
													if ($album->albumSubRights() & MANAGED_OBJECT_RIGHTS_EDIT) {
														?>
														<tr>
															<td align="left" valign="top"><span class="nowrap"><?php echo gettext("Set as thumbnail for:"); ?></span></td>
															<td>
																<select name="album_thumb-<?php echo $currentimage; ?>" >
																	<option value=""></option>
																	<?php generateListFromArray(array(), $albumHeritage, false, true); ?>
																</select>
															</td>
														</tr>
														<?php
													}
													?>

													<tr align="left" valign="top">
														<td valign="top"><?php echo gettext("Date:"); ?></td>
														<td>
															<script>
																								$(function() {
																								$("#datepicker_<?php echo $currentimage; ?>").datepicker({
																								dateFormat: 'yy-mm-dd',
																												showOn: 'button',
																												buttonImage: 'images/calendar.png',
																												buttonText: '<?php echo gettext('calendar'); ?>',
																												buttonImageOnly: true
																								});
																								});
															</script>
															<input type="text" id="datepicker_<?php echo $currentimage; ?>" size="20" name="<?php echo $currentimage; ?>-date"
																		 value="<?php
																		 $d = $image->getDateTime();
																		 if ($d != '0000-00-00 00:00:00') {
																			 echo $d;
																		 }
																		 ?>" />
														</td>
													</tr>

													<?php
													$current = $image->getWatermark();
													?>
													<tr>
														<td align="left" valign="top" width="150"><?php echo gettext("Image watermark:"); ?> </td>
														<td>
															<select id="image_watermark-<?php echo $currentimage; ?>" name="<?php echo $currentimage; ?>-image_watermark" onclick="javascript:toggleWMUse(<?php echo $currentimage; ?>);">
																<option value="<?php echo NO_WATERMARK; ?>" <?php if ($current == NO_WATERMARK) echo ' selected="selected"' ?> style="background-color:LightGray"><?php echo gettext('*no watermark'); ?></option>
																<option value="" <?php if (empty($current)) echo ' selected="selected"' ?> style="background-color:LightGray"><?php echo gettext('*default'); ?></option>
																<?php
																$watermarks = getWatermarks();
																generateListFromArray(array($current), $watermarks, false, false);
																?>
															</select>
															<span id="WMUSE_<?php echo $currentimage; ?>" style="display:<?php
															if ($current == '')
																echo 'none';
															else
																echo 'inline';
															?>">
																			<?php $wmuse = $image->getWMUse(); ?>
																<label><input type="checkbox" value="1" id="wm_image-<?php echo $currentimage; ?>" name="wm_image-<?php echo $currentimage; ?>" <?php if ($wmuse & WATERMARK_IMAGE) echo 'checked="checked"'; ?> /><?php echo gettext('image'); ?></label>
																<label><input type="checkbox" value="1" id="wm_thumb-<?php echo $currentimage; ?>" name="wm_thumb-<?php echo $currentimage; ?>" <?php if ($wmuse & WATERMARK_THUMB) echo 'checked="checked"'; ?> /><?php echo gettext('thumb'); ?></label>
																<label><input type="checkbox" value="1" id="wm_full-<?php echo $currentimage; ?>" name="wm_full-<?php echo $currentimage; ?>" <?php if ($wmuse & WATERMARK_FULL) echo 'checked="checked"'; ?> /><?php echo gettext('full image'); ?></label>
															</span>
														</td>
													</tr>
             <?php if (!$singleimage) { ?>
             	<tr>
														<td valign="top"><?php echo gettext("Tags:"); ?></td>
														<td>
                <?php
                  $imagetags = $image->getTags();
                  if(count($imagetags) != 0) {
                    echo implode(', ',$imagetags);
                  } else {
                    echo gettext('No tags assigned');
                  }
                ?>
														</td>
													</tr>
            	<?php
             }
													if ($singleimage) { ?>
													<tr>
														<td valign="top"><?php echo gettext("Tags:"); ?></td>
														<td>
															<div class="box-edit-unpadded">
																<?php tagSelector($image, 'tags_' . $currentimage . '-', false, $tagsort, true, 1); ?>
															</div>
														</td>
													</tr>
              <?php
														$custom = zp_apply_filter('edit_image_custom_data', '', $image, $currentimage);
														if (empty($custom)) {
															?>
															<tr>
																<td valign="top"><?php echo gettext("Custom data:"); ?></td>
																<td><?php print_language_string_list($image->getCustomData('all'), $currentimage . '-custom_data', true, NULL, 'texteditor_imagecustomdata', '100%'); ?></td>
															</tr>
															<?php
														} else {
															echo $custom;
														}
														?>
														<tr>
															<td valign="top"><?php echo gettext("Location:"); ?></td>
															<td><?php print_language_string_list($image->getLocation('all'), $currentimage . '-location', false, NULL, '', '100%'); ?>
															</td>
														</tr>

														<tr>
															<td valign="top"><?php echo gettext("City:"); ?></td>
															<td><?php print_language_string_list($image->getCity('all'), $currentimage . '-city', false, NULL, '', '100%'); ?>
															</td>
														</tr>

														<tr>
															<td valign="top"><?php echo gettext("State:"); ?></td>
															<td><?php print_language_string_list($image->getState('all'), $currentimage . '-state', false, NULL, '', '100%'); ?>
															</td>
														</tr>

														<tr>
															<td valign="top"><?php echo gettext("Country:"); ?></td>
															<td><?php print_language_string_list($image->getCountry('all'), $currentimage . '-country', false, NULL, '', '100%'); ?>
															</td>
														</tr>

														<tr>
															<td valign="top"><?php echo gettext("Credit:"); ?></td>
															<td><?php print_language_string_list($image->getCredit('all'), $currentimage . '-credit', false, NULL, '', '100%'); ?>
															</td>
														</tr>

														<tr>
															<td valign="top"><?php echo gettext("Copyright:"); ?></td>
															<td><?php print_language_string_list($image->getCopyright('all'), $currentimage . '-copyright', false, NULL, '', '100%'); ?>
															</td>
														</tr>
														<?php
													}
													if ($image->hasMetadata()) {
														?>
														<tr>
															<td valign="top"><?php echo gettext("Metadata:"); ?></td>
															<td>
																<?php
																$exif = $image->getMetaData(false);
																if (false !== $exif) {
																	?>
																	<div class="metadata_container">
																		<table class="metadata_table" >
																		<?php
																		foreach ($exif as $field => $value) {
																			$label = $_zp_exifvars[$field][2];
																			$value = getImageMetadataValue($_zp_exifvars[$field][6], $value, $field);
																			echo '<tr><td class="metadata_tag">' . $label . ': </td> <td>' . $value . '</td></tr>'. "\n";
																		}
																		?>
																		</table>
																	</div>		
																	<?php
																}
																?>
															</td>
														</tr>
														<?php
													}
													if ($singleimage) {
														?>
														<tr valign="top">
															<td class="topalign-nopadding"><br /><?php echo gettext("Codeblocks:"); ?></td>
															<td>
																<br />
																<?php printCodeblockEdit($image, $currentimage); ?>
															</td>
														</tr>
														<?php
													} else {
														?>
														<tr>
															<td colspan="2" style="border-bottom:none;">
																<a href="<?php echo WEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit&tab=imageinfo&album=' . $album->name . '&singleimage=' . $image->filename.'&pagenumber='.$pagenum; ?>"><img src="images/options.png" /> <?php echo gettext('Edit all image data'); ?></a>
															</td>
														</tr>
														<?php
													}
													?>
												</table>
											</td>
										</tr>

										<?php
										$currentimage++;
									}
									?>
									<tr <?php echo ($currentimage % 2 == 0) ? "class=\"alt\"" : ""; ?>>
										<td colspan="4">

											<p class="buttons">
												<a href="<?php echo WEBPATH . '/' . ZENFOLDER . '/admin-edit.php?page=edit' . $parent; ?>">
													<img	src="images/arrow_left_blue_round.png" alt="" />
													<strong><?php echo gettext("Back"); ?></strong>
												</a>
												<button type="submit">
													<img src="images/pass.png" alt="" />
													<strong><?php echo gettext("Apply"); ?></strong>
												</button>
												<button type="reset">
													<img src="images/fail.png" alt="" />
													<strong><?php echo gettext("Reset"); ?></strong>
												</button>
											</p>

										</td>
									</tr>
									<?php
									if ($allimagecount != $totalimages && !$singleimage) { // need pagination links
										?>
										<tr>
											<td colspan="4" class="bordered" id="imagenavb"><?php adminPageNav($pagenum, $totalpages, 'admin-edit.php', '?page=edit&amp;album=' . html_encode(pathurlencode($album->name)), '&amp;tab=imageinfo'); ?>
											</td>
										</tr>
										<?php
									}
									if (!empty($target_image)) {
										?>
										<script>
																							toggleExtraInfo('<?php echo $target_image_nr; ?>', 'image', true);
										</script>
										<?php
									}
									?>

								</table>
								<input type="hidden" name="checkForPostTruncation" value="1" />
							</form>

							<?php
						}
						?>
					</div><!-- images -->
					<?php
				}

				if ($subtab != "albuminfo") {
					?>
					<!-- page trailer -->

					<?php
				}
				/*				 * * MULTI-ALBUM ************************************************************************** */
			} else if (isset($_GET['massedit'])) {
				// one time generation of this list.
				$_zp_admin_mcr_albumlist = $_zp_gallery->getAllAlbumsFromDB(false, NULL, UPLOAD_RIGHTS, true, false, true);
				$albumdir = "";
				if (isset($_GET['album'])) {
					$folder = sanitize_path($_GET['album']);
					$album = AlbumBase::newAlbum($folder);
					if ($album->isMyItem(ALBUM_RIGHTS)) {
						$albums = $album->getAlbums();
						$pieces = explode('/', $folder);
						$albumdir = "&album=" . pathurlencode($folder) . '&tab=subalbuminfo';
					} else {
						$albums = array();
					}
				} else {
					$albumsprime = $_zp_gallery->getAlbums();
					$albums = array();
					foreach ($albumsprime as $folder) { // check for rights
						$album = AlbumBase::newAlbum($folder);
						if ($album->isMyItem(ALBUM_RIGHTS)) {
							$albums[] = $folder;
						}
					}
				}
				?>
				<h1>
					<?php echo gettext("Edit All Albums in"); ?> <?php
					if (!isset($_GET['album'])) {
						echo gettext("Gallery");
					} else {
						echo "<em>" . html_encode($album->name) . "</em>";
					}
					?>
				</h1>
				<?php consolidatedEditMessages('massedit'); ?>
				<form class="dirty-check" name="albumedit" id="form_albumedit-multi" autocomplete="off"	action="?page=edit&amp;action=save<?php echo $albumdir ?>" method="POST">
					<?php XSRFToken('albumedit'); ?>
					<input type="hidden" name="totalalbums" value="<?php echo sizeof($albums); ?>" />
					<span class="buttons">
						<a href="<?php echo WEBPATH . '/' . ZENFOLDER; ?>/admin-edit.php?page=edit">
							<img	src="images/arrow_left_blue_round.png" alt="" /><strong><?php echo gettext("Back"); ?></strong>
						</a>
						<button type="submit">
							<img	src="images/pass.png" alt="" /><strong><?php echo gettext("Apply"); ?></strong>
						</button>
						<button type="reset" onclick="javascript:$('.deletemsg').hide();" >
							<img	src="images/fail.png" alt="" /><strong><?php echo gettext("Reset"); ?></strong>
						</button>
					</span>
					<br class="clearall" /><br />
					<div class="outerbox">
						<?php
						$currentalbum = 1;
						foreach ($albums as $folder) {
							$album = AlbumBase::newAlbum($folder);
							echo "\n<!-- " . $album->name . " -->\n";
							?>
							<div class="innerbox<?php if ($currentalbum % 2) echo '_dark'; ?>" style="padding: 15px;">
								<?php
								printAlbumEditForm($currentalbum, $album, false);
								$currentalbum++;
								?>
							</div>
							<?php
						}
						?>
					</div>
					<br class="clearall" /><br />
					<span class="buttons">
						<a href="<?php echo WEBPATH . '/' . ZENFOLDER; ?>/admin-edit.php?page=edit">
							<img	src="images/arrow_left_blue_round.png" alt="" /><strong><?php echo gettext("Back"); ?></strong>
						</a>
						<button type="submit">
							<img	src="images/pass.png" alt="" /><strong><?php echo gettext("Apply"); ?></strong>
						</button>
						<button type="reset" onclick="javascript:$('.deletemsg').hide();" >
							<img	src="images/fail.png" alt="" /><strong><?php echo gettext("Reset"); ?></strong>
						</button>
					</span>
				</form>
				<?php
				/*				 * * EDIT ALBUM SELECTION ******************************************************************** */
			} else { /* Display a list of albums to edit. */
				?>
				<h1><?php echo gettext("Edit Gallery"); ?></h1>
				<?php
				$albums = getNestedAlbumList(NULL, $_zp_admin_album_nesting);
				if (count($albums) > 0) {
					if (zp_loggedin(ADMIN_RIGHTS) && (count($albums)) > 1) {
						$sorttype = strtolower($_zp_gallery->getSortType());
						if ($sorttype != 'manual') {
							if ($_zp_gallery->getSortDirection()) {
								$dir = gettext(' descending');
							} else {
								$dir = '';
							}
							$sortNames = getSortByOptions('albums');
							if(array_key_exists($sorttype, $sortNames)) {
								$sorttype = $sortNames[$sorttype];
							} 
						} else {
							$dir = '';
						}
						?>
						<p>
							<?php printf(gettext('Current sort: <em>%1$s%2$s</em>.'), $sorttype, $dir); ?>
						</p>
						<p>
							<?php echo gettext('Drag the albums into the order you wish them displayed.'); ?>
						</p>
						<p class="notebox">
							<?php echo gettext('<strong>Note:</strong> Dragging an album under a different parent will move the album. You cannot move albums under a <em>dynamic</em> album.'); ?>
						</p>
						<?php
					}
					?>
					<p>
						<?php
						echo gettext('Select an album to edit its description and data, or <a href="?page=edit&amp;massedit">mass-edit</a> all gallery level albums.');
						?>
					</p>

					<?php
					consolidatedEditMessages('');
					printEditDropdown('', array('1', '2', '3', '4', '5'), $_zp_admin_album_nesting);
					?>
					<form class="dirty-check" action="?page=edit&amp;action=savealbumorder" method="post" name="sortableListForm" id="sortableListForm" onsubmit="return confirmAction();" autocomplete="off">
									<?php XSRFToken('savealbumorder'); ?>
						<p class="buttons">
							<?php
							if ($_zp_admin_album_nesting > 1 || zp_loggedin(MANAGE_ALL_ALBUM_RIGHTS)) {
								?>
								<button class="serialize buttons" type="submit"><img src="images/pass.png" alt="" /><strong><?php echo gettext("Apply"); ?></strong></button>
								<?php
							}
							if (zp_loggedin(MANAGE_ALL_ALBUM_RIGHTS)) {
								?>
								<button type="button" onclick="javascript:newAlbum('', false);"><img src="images/folder.png" alt="" /><strong><?php echo gettext('New album'); ?></strong></button>
         <button type="button" title="<?php echo addslashes(gettext('New dynamic album')); ?>" onclick="javascript:newDynAlbum('', false);">
												<img src="images/folder.png" alt="" />
												<strong><?php echo gettext('New dynamic album'); ?></strong>
									</button>
        <?php
							}
							?>
						</p>
						<br class="clearall" /><br />
						<div class="bordered">
							<div class="headline"><?php echo gettext("Edit this album"); ?>
								<?php printBulkActions($checkarray_albums); ?>
							</div>
							<div class="subhead">
								<label class="buttons" style="float: left">
									<a href="admin-edit.php?showthumbs=<?php echo $thumbshow ?>" title="<?php echo gettext('Thumbnail generation may be time consuming on slow servers or when there are a lot of images.'); ?>">
										<?php echo $thumbmsg; ?>
									</a>
								</label>
								<label style="float: right">
									<?php echo gettext("Check All"); ?> <input type="checkbox" name="allbox" id="allbox" onclick="checkAll(this.form, 'ids[]', this.checked);" />
								</label>
							</div>

							<ul class="page-list">
								<?php printNestedAlbumsList($albums, $showthumb, NULL); ?>
							</ul>

						</div>
						<div>
							<?php printAlbumLegend(); ?>
						</div>

						<span id="serializeOutput"></span>
						<input name="update" type="hidden" value="Save Order" />
						<p class="buttons">
							<?php
							if ($_zp_admin_album_nesting > 1 || zp_loggedin(MANAGE_ALL_ALBUM_RIGHTS)) {
								?>
								<button class="serialize buttons" type="submit"><img src="images/pass.png" alt="" /><strong><?php echo gettext("Apply"); ?></strong></button>
								<?php
							}
							if (zp_loggedin(MANAGE_ALL_ALBUM_RIGHTS)) {
								?>
								<button type="button" onclick="javascript:newAlbum('', false);"><img src="images/folder.png" alt="" /><strong><?php echo gettext('New album'); ?></strong></button>
          <button type="button" title="<?php echo addslashes(gettext('New dynamic subalbum')); ?>" onclick="javascript:newDynAlbum('', false);">
												<img src="images/folder.png" alt="" />
												<strong><?php echo gettext('New dynamic album'); ?></strong>
											</button>
        <?php
							}
							?>
						</p>

					</form>
					<br class="clearall" />

					<?php
				} else {
					echo gettext("There are no albums for you to edit.");
					if (zp_loggedin(MANAGE_ALL_ALBUM_RIGHTS)) {
						?>
						<p class="buttons">
							<button type="button" onclick="javascript:newAlbum('', false);"><img src="images/folder.png" alt="" /><strong><?php echo gettext('New album'); ?></strong></button>
       <button type="button" title="<?php echo addslashes(gettext('New dynamic album')); ?>" onclick="javascript:newDynAlbum('', false);">
										<img src="images/folder.png" alt="" />
										<strong><?php echo gettext('New dynamic album'); ?></strong>
							</button>
      </p>
						<?php
					}
				}
			}
			?>
		</div><!-- content -->
	</div><!-- main -->
	<?php printAdminFooter(); ?>
</body>
<?php
// to fool the validator
echo "\n</html>";
?>
