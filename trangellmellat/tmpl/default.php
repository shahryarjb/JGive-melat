<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_jgive
 * @subpackage 	Trangell_Mellat
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');
?>
<div class="tjcpg-wrapper">
<form action="<?php echo $vars->action_url ?>"  method="post" id="paymentForm">
<input type="hidden" name="RefId" value="<?php echo $vars->refid; ?>" />
		<div class="form-actions">
			<input name='submit' type='submit' class="btn btn-success btn-large" value="<?php echo JText::_('پرداخت'); ?>" >
		</div>
	</div>
</form>
</div>

