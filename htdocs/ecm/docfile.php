<?php
/* Copyright (C) 2008-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file      	htdocs/ecm/docfile.php
 *	\ingroup   	ecm
 *	\brief     	Card of a file for ECM module
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmdirectory.class.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ecm.lib.php';

// Load traductions files
$langs->load("ecm");
$langs->load("companies");
$langs->load("other");
$langs->load("users");
$langs->load("orders");
$langs->load("propal");
$langs->load("bills");
$langs->load("contracts");
$langs->load("categories");

$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

if (!$user->rights->ecm->setup) accessforbidden();

// Get parameters
$socid = GETPOST("socid","int");

// Security check
if ($user->societe_id > 0)
{
    $action = '';
    $socid = $user->societe_id;
}

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="label";

$section=GETPOST("section");
if (! $section)
{
    dol_print_error('','Error, section parameter missing');
    exit;
}
$urlfile=GETPOST("urlfile");
if (! $urlfile)
{
    dol_print_error('',"ErrorParamNotDefined");
    exit;
}

// Load ecm object
$ecmdir = new EcmDirectory($db);
$result=$ecmdir->fetch(GETPOST("section",'alpha'));
if (! $result > 0)
{
    dol_print_error($db,$ecmdir->error);
    exit;
}
$relativepath=$ecmdir->getRelativePath();
$upload_dir = $conf->ecm->dir_output.'/'.$relativepath;

$fullpath=$conf->ecm->dir_output.'/'.$relativepath.$urlfile;

$file = new stdClass();
$file->section_id=$ecmdir->id;
$file->label=$urlfile;

$relativetodocument = 'ecm/'.$relativepath;		// $relativepath is relative to ECM dir, we need relative to document
$filepath=$relativepath.$file->label;
$filepathtodocument=$relativetodocument.$file->label;

// Try to load object from index
$object = new ECMFiles($db);
$result=$object->fetch(0, '', $filepathtodocument);
if (! ($result >= 0))
{
	dol_print_error($db, $object->error, $object->errors);
	exit;
}




/*
 * Actions
 */

if ($cancel)
{
    $action ='';
    if ($backtourl)
    {
        header("Location: ".$backtourl);
        exit;
    }
    else
    {
        header("Location: ".DOL_URL_ROOT.'/ecm/docfile.php?urlfile='.$urlfile.'&section='.$section);
        exit;
    }
}

// Rename file
if ($action == 'update')
{
    $error=0;

    $oldlabel=GETPOST('urlfile', 'alpha');
    $newlabel=GETPOST('label', 'alpha');
	$shareenabled = GETPOST('shareenabled', 'alpha');

    //$db->begin();

    $olddir=$ecmdir->getRelativePath(0);
    $olddir=$conf->ecm->dir_output.'/'.$olddir;
    $newdir=$olddir;

    $oldfile=$olddir.$oldlabel;
    $newfile=$newdir.$newlabel;

    //print $oldfile.' - '.$newfile;
    if ($newlabel != $oldlabel)
    {
        $result=dol_move($oldfile, $newfile);		// This include update of database
        if (! $result)
        {
            $langs->load('errors');
            setEventMessages($langs->trans('ErrorFailToRenameFile',$oldfile,$newfile), null, 'errors');
            $error++;
        }
    }

    // Now we update index of file
    $db->begin();

    if (! $error)
    {
		if (is_object($object))
		{
			if ($shareenabled)
			{
				require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
				$object->share = getRandomPassword(true);
			}
			else
			{
				$object->share = '';
			}
			$result = $object->update($user);
			if ($result < 0)
			{
				$error++;
				setEventMessages($object->error, $object->errors, 'errors');
			}
		}
    }

    if (!$error)
    {
        $db->commit();
        $urlfile=$newlabel;
    }
    else
    {
        $db->rollback();
    }
}



/*
 * View
 */

$form=new Form($db);

llxHeader();

$head = ecm_file_prepare_head($file);

if ($action == 'edit')
{
	print '<form name="update" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="section" value="'.$section.'">';
    print '<input type="hidden" name="urlfile" value="'.$urlfile.'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
}

dol_fiche_head($head, 'card', $langs->trans("File"), 0, 'generic');

print '<table class="border" width="100%">';
print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>';
$s='';
$tmpecmdir=new EcmDirectory($db);	// Need to create a new one
$tmpecmdir->fetch($ecmdir->id);
$result = 1;
$i=0;
while ($tmpecmdir && $result > 0)
{
	$tmpecmdir->ref=$tmpecmdir->label;
    $s=$tmpecmdir->getNomUrl(1).$s;
	if ($tmpecmdir->fk_parent)
	{
		$s=' -> '.$s;
		$result=$tmpecmdir->fetch($tmpecmdir->fk_parent);
	}
	else
	{
		$tmpecmdir=0;
	}
	$i++;
}

print img_picto('','object_dir').' <a href="'.DOL_URL_ROOT.'/ecm/index.php">'.$langs->trans("ECMRoot").'</a> -> ';
print $s;
print ' -> ';
if ($action == 'edit') print '<input type="text" name="label" class="quatrevingtpercent" value="'.$urlfile.'">';
else print $urlfile;
print '</td></tr>';
/*print '<tr><td class="tdtop">'.$langs->trans("Description").'</td><td>';
if ($action == 'edit')
{
	print '<textarea class="flat" name="description" cols="80">';
	print $ecmdir->description;
	print '</textarea>';
}
else print dol_nl2br($ecmdir->description);
print '</td></tr>';
print '<tr><td>'.$langs->trans("ECMCreationUser").'</td><td>';
$userecm=new User($db);
$userecm->fetch($ecmdir->fk_user_c);
print $userecm->getNomUrl(1);
print '</td></tr>';
*/
print '<tr><td>'.$langs->trans("ECMCreationDate").'</td><td>';
print dol_print_date(dol_filemtime($fullpath),'dayhour');
print '</td></tr>';
/*print '<tr><td>'.$langs->trans("ECMDirectoryForFiles").'</td><td>';
print '/ecm/'.$relativepath;
print '</td></tr>';
print '<tr><td>'.$langs->trans("ECMNbOfDocs").'</td><td>';
print count($filearray);
print '</td></tr>';
print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td>';
print dol_print_size($totalsize);
print '</td></tr>';
*/

print '<tr><td>'.$langs->trans("HashOfFileContent").'</td><td>';
$object = new EcmFiles($db);
//$filenametosearch=basename($filepath);
//$filedirtosearch=basedir($filepath);
$object->fetch(0, '', $filepathtodocument);
if (! empty($object->label))
{
	print $object->label;
}
else
{
	print img_warning().' '.$langs->trans("FileNotYetIndexedInDatabase");
}
print '</td></tr>';

// Define $urlwithroot
$urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',trim($dolibarr_main_url_root));
$urlwithroot=$urlwithouturlroot.DOL_URL_ROOT;		// This is to use external domain name found into config file
//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

print '<tr><td>'.$langs->trans("DirectDownloadInternalLink").'</td><td>';
$modulepart='ecm';
$forcedownload=1;
$rellink='/document.php?modulepart='.$modulepart;
if ($forcedownload) $rellink.='&attachment=1';
if (! empty($object->entity)) $rellink.='&entity='.$object->entity;
$rellink.='&file='.urlencode($filepath);
$fulllink=$urlwithroot.$rellink;
print img_picto('','object_globe.png').' ';
if ($action != 'edit') print '<input type="text" class="quatrevingtpercent" id="downloadinternallink" name="downloadinternellink" value="'.dol_escape_htmltag($fulllink).'">';
else print $fulllink;
if ($action != 'edit') print ' <a href="'.$fulllink.'">'.$langs->trans("Download").'</a>';
print '</td></tr>';

print '<tr><td>';
if ($action != 'edit') print $langs->trans("DirectDownloadLink");
else print $langs->trans("FileSharedViaALink");
print '</td><td>';
if (! empty($object->share))
{
	if ($action != 'edit')
	{
		$modulepart='ecm';
		$forcedownload=1;
		$rellink='/document.php?modulepart='.$modulepart;
		if ($forcedownload) $rellink.='&attachment=1';
		if (! empty($object->entity)) $rellink.='&entity='.$object->entity;
		//$rellink.='&file='.urlencode($filepath);		// No need of name of file for public link, we will use the hash
		$fulllink=$urlwithroot.$rellink;
		//if (! empty($object->ref))       $fulllink.='&hashn='.$object->ref;			// Hash of file path
		//elseif (! empty($object->label)) $fulllink.='&hashc='.$object->label;		// Hash of file content
		if (! empty($object->share))  $fulllink.='&hashp='.$object->share;			// Hash for public share
		print img_picto('','object_globe.png').' ';
		if ($action != 'edit') print '<input type="text" class="quatrevingtpercent" id="downloadlink" name="downloadexternallink" value="'.dol_escape_htmltag($fulllink).'">';
		else print $fulllink;
		if ($action != 'edit') print ' <a href="'.$fulllink.'">'.$langs->trans("Download").'</a>';
	}
	else
	{
		print '<input type="checkbox" name="shareenabled"'.($object->share?' checked="checked"':'').' /> ';
	}
}
else
{
	if ($action != 'edit')
	{
		print '<span class="opacitymedium">'.$langs->trans("FileNotShared").'</span>';
	}
	else
	{
		print '<input type="checkbox" name="shareenabled"'.($object->share?' checked="checked"':'').' /> ';
	}
}
print '</td></tr>';

print '</table>';

print ajax_autoselect('downloadinternallink');
print ajax_autoselect('downloadlink');

dol_fiche_end();

if ($action == 'edit')
{
    print '<div class="center">';
    print '<input type="submit" class="button" name="submit" value="'.$langs->trans("Save").'">';
    print ' &nbsp; &nbsp; ';
    print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
    print '</div>';

    print '</form>';
}


// Confirmation de la suppression d'une ligne categorie
if ($action == 'delete_file')
{
    print $form->formconfirm($_SERVER["PHP_SELF"].'?section='.urlencode($_GET["section"]), $langs->trans('DeleteFile'), $langs->trans('ConfirmDeleteFile',$urlfile), 'confirm_deletefile', '', 1, 1);

}

if ($action != 'edit')
{
	// Actions buttons
	print '<div class="tabsAction">';

    if ($user->rights->ecm->setup)
    {
        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=edit&section='.$section.'&urlfile='.urlencode($urlfile).'">'.$langs->trans('Edit').'</a>';

        //print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=cancel&section='.$section.'&urlfile='.urlencode($urlfile).'&backtourl='.urlencode($backtourl).'">'.$langs->trans('Cancel').'</a>';
    }
/*
	if ($user->rights->ecm->setup)
	{
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=delete_file&section='.$section.'&urlfile='.urlencode($urlfile).'">'.$langs->trans('Delete').'</a>';
	}
	else
	{
		print '<a class="butActionRefused" href="#" title="'.$langs->trans("NotAllowed").'">'.$langs->trans('Delete').'</a>';
	}
*/
	print '</div>';
}


// End of page
llxFooter();
$db->close();
