<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Kay Strobach <typo3@kay-strobach.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

if (!defined ("TYPO3_MODE"))    die ('Access denied.');

if (TYPO3_MODE == 'BE') {
	$TYPO3_CONF_VARS['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['stepOutput'][] =      'EXT:' . $_EXTKEY . '/Classes/Controller/InstallController.php:&tx_packageInstall_InstallController';
	$TYPO3_CONF_VARS['SC_OPTIONS']['ext/install/mod/class.tx_install.php']['additionalSteps'][] = 'EXT:' . $_EXTKEY . '/Classes/Controller/AdditionalStepsController.php:&tx_packageInstall_additionalstepscontroller';
		// FIX #35253
	$GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_install.php'] = t3lib_extMgm::extPath($_EXTKEY) . 'Classes/XClass/ux_t3lib_install.php';
}

?>