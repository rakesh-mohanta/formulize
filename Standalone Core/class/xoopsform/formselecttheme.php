<?php
/**
* Creates a form attribute which is able to select a theme
*
* @copyright	http://www.xoops.org/ The XOOPS Project
* @copyright	XOOPS_copyrights.txt
* @copyright	http://www.impresscms.org/ The ImpressCMS Project
* @license	LICENSE.txt
* @package	XoopsForms
* @since	XOOPS
* @author	http://www.xoops.org The XOOPS Project
* @author	modified by UnderDog <underdog@impresscms.org>
* @version	$Id: formselecttheme.php 8662 2009-05-01 09:04:30Z pesianstranger $
*/

if (!defined('ICMS_ROOT_PATH')) {
	die("ImpressCMS root path not defined");
}

/**
 * @package     kernel
 * @subpackage  form
 * 
 * @author	    Kazumi Ono	<onokazu@xoops.org>
 * @copyright	copyright (c) 2000-2003 XOOPS.org
 */
/**
 * lists of values
 */
include_once ICMS_ROOT_PATH."/class/xoopslists.php";
/**
 * base class
 */
include_once ICMS_ROOT_PATH."/class/xoopsform/formselect.php";

/**
 * A select box with available themes
 * 
 * @package     kernel
 * @subpackage  form
 * 
 * @author	    Kazumi Ono	<onokazu@xoops.org>
 * @copyright	copyright (c) 2000-2003 XOOPS.org
 */
class XoopsFormSelectTheme extends XoopsFormSelect
{
	/**
	 * Constructor
	 * 
	 * @param	string	$caption	
	 * @param	string	$name
	 * @param	mixed	$value	Pre-selected value (or array of them).
	 * @param	int		$size	Number or rows. "1" makes a drop-down-list
	 */
	function XoopsFormSelectTheme($caption, $name, $value = null, $size = 1)
	{
		$this->XoopsFormSelect($caption, $name, $value, $size);
		$this->addOptionArray(XoopsLists::getThemesList());
	}
}
?>