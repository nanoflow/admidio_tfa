<?php
/**
 ***********************************************************************************************
 * phpinfo
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'phpinfo.php')
{
    exit('This page may not be called directly!');
}

require_once(__DIR__ . '/common.php');
require(__DIR__ . '/login_valid.php');

// only administrators are allowed to view phpinfo
if (!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// show php info page
phpinfo();
