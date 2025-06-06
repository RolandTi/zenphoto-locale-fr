<script>
/* tinyMCEPopup.requireLangPack(); */

var ZenpageDialog = function(id,imgurl,thumburl,sizedimage,imgname,imgtitle,albumtitle,fullimage,type, wm_thumb, wm_img,video,imgdesc,albumdesc) {
		var imglink = '';
		var includetype = '';
		var imagesize = '';
		var linkpart1 = '';
		var linkpart2 = '';
		var linktype = '';
		var textwrap = '';
		var textwrap_float = '';
		var infowrap1 = '';
		var infowrap2 = '';
		var titlewrap = '';
		var descwrap = '';
		var cssclass = '';
		var albumname = '<?php if(isset($_GET["album"]))  { echo html_encode(sanitize($_GET["album"])); } else { $_GET["album"] = ""; } ?>';
		var webpath = '<?php echo WEBPATH; ?>'
		//var modrewrite = '<?php echo MOD_REWRITE; ?>';
		//var modrewritesuffix = '<?php echo getOption("mod_rewrite_image_suffix"); ?>';
		var plainimgtitle = imgtitle.replace(/'/g, "&#039;");;
		var plainalbumtitle = albumtitle.replace(/'/g, "&#039;");;
		var player = '';

		var stopincluding = false;
		// getting the image size checkbox values
		if($('#thumbnail').prop('checked')) {
			cssclass ='zenpage_thumb';
		}
		if($('#customthumb').prop('checked')) {
			imagesize = '&amp;s='+$('#cropsize').val()+'&amp;cw='+$('#cropwidth').val()+'&amp;ch='+$('#cropheight').val()+'&amp;t=true';
			if (wm_thumb) {
				imagesize += '&amp;wmk='+wm_thumb;
			}
			cssclass ='zenpage_customthumb';
		}
		if($('#sizedimage').prop('checked')) {
			cssclass ='zenpage_sizedimage';
		}
		if($('#customsize').prop('checked')) {
			if(video) {
				alert("<?php echo gettext('It is not possible to a include a custom size %s.'); ?>".replace(/%s/,imgname));
				stopincluding = true;
			}
			imagesize = '&amp;s='+$('#size').val();
			if (wm_img) {
				imagesize += '&amp;wmk='+wm_img;
			}
			cssclass ='zenpage_customimage';
		}
		if($('#fullimage').prop('checked')) {
			if(video) {
				alert("<?php echo gettext('It is not possible to a include a full size %s.'); ?>".replace(/%s/,imgname));
				stopincluding = true;
			}
			imagesize = '';
			cssclass ='zenpage_fullimage';
		}

		// getting the text wrap checkbox values
		// Customize the textwrap variable if you need specific CSS
		if($('#nowrap').prop('checked')) {
			textwrap = 'class=\''+cssclass+'\'';
			textwrap_title = '';
			textwrap_title_add = '';
		}
		if($('#rightwrap').prop('checked')) {
			// we don't need (inline) css attached to the image/link it they are wrapped for the title, the div does the wrapping!
			if($('#showtitle:checked').val() != 1 && $('#showdesc:checked').val() != 1) {
				textwrap_float = ' style=\'float: left;\'';
			}
			textwrap = 'class=\''+cssclass+'_left\''+textwrap_float;
			textwrap_title = ' style=\'float: left;\' ';
			textwrap_title_add = '_left';
		}
		if($('#leftwrap').prop('checked')) {
			// we don't need (inline) css attached to the image/link it they are wrapped for the title, the div does the wrapping!
			if($('#showtitle:checked').val() != 1 && $('#showdesc:checked').val() != 1) {
				textwrap_float = ' style=\'float: right;\'';
			}
			textwrap = 'class=\''+cssclass+'_right\''+textwrap_float;
			textwrap_title = ' style=\'float: right;\' ';
			textwrap_title_add = '_right';
		}
		// getting the link type checkbox values
		if($('#imagelink').prop('checked')) {
			linkpart1 = '<a href=\''+webpath+'/index.php?album='+albumname+'&amp;image='+imgname+'\' title=\''+plainimgtitle+'\' class=\'zenpage_imagelink\'>';
			linkpart2 = '</a>';
		}
		if($('#fullimagelink').prop('checked')) {
				linkpart1 = '<a href=\''+fullimage+'\' title=\''+plainimgtitle+'\' class=\'zenpage_fullimagelink\' rel=\'colorbox\'>';
				linkpart2 = '</a>';
		}
		if($('#albumlink').prop('checked')) {
			linkpart1 = '<a href=\''+webpath+'/index.php?album='+albumname+'\' title=\''+plainalbumtitle+'\' class=\'zenpage_albumlink\'>';
			linkpart2 = '</a>';
		}
		if($('#customlink').prop('checked')) {
			linkpart1 = '<a href=\''+$('#linkurl').val()+'\' title=\''+linktype+'\' '+textwrap+' class=\'zenpage_customlink\'>';
			linkpart2 = '</a>';
		}
		// getting the include type checkbox values
		if($('#image').prop('checked')) {
			if($('#fullimage').prop('checked')) {
				includetype = '<img src=\''+fullimage+'\' alt=\''+plainimgtitle+'\' '+textwrap+' loading=\'lazy\' />';
			} else if ($('#thumbnail').prop('checked')) {
				includetype = '<img src=\''+thumburl+'\' alt=\''+plainimgtitle+'\' '+textwrap+' loading=\'lazy\' />';
			} else {
				includetype = '<img src=\''+imgurl+imagesize+'\' alt=\''+plainimgtitle+'\' '+textwrap+' loading=\'lazy\' />';
			}
			if($('#showtitle').prop('checked') || $('#imagedesc').prop('checked') || $('#albumdesc').prop('checked')) {
				infowrap1 = '<div class=\'zenpage_wrapper'+textwrap_title_add+'\''+textwrap_title+'>';
				infowrap2 = '</div>';
			}
			if($('#showtitle').prop('checked')) {
				if($('#albumlink').prop('checked')) {
					titlewrap = '<div class=\'zenpage_title\'>'+albumtitle+'</div>';
				} else {
					titlewrap = '<div class=\'zenpage_title\'>'+imgtitle+'</div>';
				}
			}
			if($('#imagedesc').prop('checked')) {
				descwrap = '<div class=\'zenpage_desc\'>'+imgdesc.replace(/'/g, "&#039;")+'</div>';
			}
			if($('#albumdesc').prop('checked')) {
				descwrap = '<div class=\'zenpage_desc\'>'+albumdesc.replace(/'/g, "&#039;")+'</div>';
			}
			infowrap2 = titlewrap+descwrap+infowrap2;
		}
		if($('#imagetitle').prop('checked')) {
			includetype = imgtitle;
		}
		if($('#albumtitle').prop('checked')) {
			includetype = albumtitle;
		}
		if($('#customtext').prop('checked')) {
			includetype = $('#text').val();
		}

		// building the final item to include
		switch (type) {
			case 'zenphoto':
				if($('#sizedimage').prop('checked')) {
					if(video == 'video' || video == 'audio') {
						player = '[MEDIAPLAYER '+albumname+' '+imgname+' '+id+']';
						imglink = infowrap1+player+infowrap2;
					} else {
						imglink = infowrap1+linkpart1+sizedimage+linkpart2+infowrap2
					}
				} else {
					imglink = infowrap1+linkpart1+includetype+linkpart2+infowrap2;
				}
				break;
			case 'pages':
				imglink = '<a href=\''+webpath+'/index.php?p=pages&amp;title='+imgname+'\' title=\''+plainimgtitle+'\'>'+imgtitle+'</a>';
				break;
			case 'articles':
				imglink = '<a href=\''+webpath+'/index.php?p=news&amp;title='+imgname+'\' title=\''+plainimgtitle+'\'>'+imgtitle+'</a>';
				break;
			case 'categories':
				imglink = '<a href=\''+webpath+'/index.php?p=news&amp;category='+imgname+'\' title=\''+plainimgtitle+'\'>'+imgtitle+'</a>';
				break;
		}
		if(!stopincluding) {
			parent.tinymce.EditorManager.activeEditor.execCommand('mceInsertContent', false, imglink);
		}
	}
</script>