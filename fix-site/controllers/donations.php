<?php
/**
 * @version    SVN: <svn_id>
 * @package    JGive
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2015 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

// No direct access
defined('_JEXEC') or die();
/**
 * Donations form controller class.
 *
 * @package     JGive
 * @subpackage  com_jgive
 * @since       1.6.7
 */

class JgiveControllerDonations extends jgiveController
{
	/**
	 * Save donation order, this function called from backend too
	 *
	 * @return  string  The arguments to append to the redirect URL.
	 *
	 * @since   1.6
	 */
	public function save()
	{
		$model	= $this->getModel('donations');
		$post	= JRequest::get('post');

		$model->setState('request', $post);

		$result	= $model->changeOrderStatus();

		if ($result == 1)
		{
			$msg = JText::_('COM_JGIVE_SAVING_MSG');
		}
		elseif ($result == 3)
		{
			$msg = JText::_('COM_JGIVE_REFUND_SAVING_MSG');
		}
		else
		{
			$msg = JText::_('COM_JGIVE_ERROR_SAVING_MSG');
		}

		$link = 'index.php?option=com_jgive&view=donations&layout=my';

		// Added by sagar for custom project This is trigger  when status changed
		$dispatcher = JDispatcher::getInstance();
		JPluginHelper::importPlugin('system');

		// Call the plugin and get the result
		$result = $dispatcher->trigger('OnAfterJGivePaymentUpdate', array($post['id']));

		// Added by sagar for custom project This is trigger  when status changed
		$this->setRedirect($link, $msg);
	}

	/**
	 * Confirm payment
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function confirmpayment()
	{
		$model		= $this->getModel('donations');
		$session	= JFactory::getSession();
		$jinput		= JFactory::getApplication()->input;
		$order_id	= $session->get('JGIVE_order_id');

		// Clear JGIVE_order_id from seesion to place new order after click on donate
		$session->clear('JGIVE_order_id');

		$pg_plugin	= $jinput->get('processor');
		$response	= $model->confirmpayment($pg_plugin, $order_id);
	}

	/**
	 * Cancel order & redirect to my donations view
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function cancel()
	{
		$msg = JText::_('COM_JGIVE_PAYMENT_CANCEL_MSG');
		$link = 'index.php?option=com_jgive&view=donations&layout=my';
		$this->setRedirect($link, $msg);
	}

	/**
	 * Redirect to payment page, called when clicked on donate button on campaign details page from frontend
	 *
	 * @return  string  The arguments to append to the redirect URL.
	 *
	 * @since   1.6
	 */
	public function donate()
	{
		$post = JRequest::get('post');
		$jgiveFrontendHelper = new jgiveFrontendHelper;

		// Clear session order id for placing new order
		$session = JFactory::getSession();

		$session->clear('JGIVE_order_id');
		$session->clear('JGIVE_giveback_id');

		if (!empty($post['cid']))
		{
			$cid = $post['cid'];
		}

		// Check that this donation is giveback donation
		$input = JFactory::getApplication()->input;

		// Get giveback id
		$giveback_id = $input->get('giveback_id', '', 'INT');
		$itemid = $input->get('Itemid', '', 'INT');

		// If it is giveback donation then get campaign id from url
		if ($giveback_id)
		{
			$cid = $input->get('cid', '', 'INT');
		}

		if (!empty($cid))
		{
			$model = $this->getModel('donations');
			$model->setSessionCampaignId($cid, $giveback_id);
		}
		else
		{
			$cid = '';
		}

		$redirect = JRoute::_('index.php?option=com_jgive&view=donations&layout=payment&cid=' . $cid . '&Itemid=' . $itemid, false);
		$this->setRedirect($redirect);
	}

	/**
	 * Save order details, Called when clicked on donate button on payment form frontend
	 *
	 * @return   void
	 *
	 * @since   1.6
	 */
	public function confirm()
	{
		// Check token
		JSession::checkToken() or jexit('Invalid Token');

		$msg				 = '';
		$jgiveFrontendHelper = new jgiveFrontendHelper;
		$post 				= JRequest::get('post');

		if (!empty($post['cid']))
		{
			// Save donor details in session, so that those can be used in future donations in current session
			$model = $this->getModel('donations');
			$model->setSessionDonorData($post);

			// Add entry in orders table, send email etc
			$result = $model->addOrder($post);
		}

		$itemid = $jgiveFrontendHelper->getItemId('index.php?option=com_jgive&view=campaigns&layout=all');

		if ((int) $result != -1)
		{
			$redirect = JRoute::_('index.php?option=com_jgive&view=donations&layout=confirm&Itemid=' . $itemid, false);
		}
		else
		{
			// Already exist eamil
			if ((int) $result == -1)
			{
				$msg = JText::_('COM_JGIVE_ERR_CONFIG_SAV_LOGIN');
			}
			else
			{
				$msg = JText::_('COM_JGIVE_ERR_CONFIG_SAV');
			}

			$itemid = $jgiveFrontendHelper->getItemId('index.php?option=com_jgive&view=campaigns&layout=all');
			$redirect = JRoute::_('index.php?option=com_jgive&view=donations&layout=payment&Itemid=' . $itemid, false);
		}

		if (!empty($msg))
		{
			$this->setRedirect($redirect, $msg);
		}
		else
		{
			$this->setRedirect($redirect);
		}
	}

	/**
	 * Get pament gateway on confirm payment view frontend.
	 *
	 * @param   string   $pg_plugin  Plugin name.
	 * @param   Integer  $order_id   Order_id.
	 *
	 * @return   string  Payment gateway HTML.
	 *
	 * @since   1.6
	 */
	public function getHTML($pg_plugin, $order_id = null)
	{
		// Sleep to show animated ajax image
		sleep(1);

		$model = $this->getModel('donations');
		$session = JFactory::getSession();

		$order_id = $order_id ? $order_id : $session->get('JGIVE_order_id');

		// All module function
		$html = $model->getHTML($pg_plugin, $order_id);

		if (!empty($html[0]))
		{
			return $html[0];
		}
	}

	/**
	 * Retry payment gateway on confirm payment view frontend.
	 *
	 * @return  json.
	 *
	 * @since   1.6
	 */
	public function retryPayment()
	{
		$input = JFactory::getApplication()->input;
		$getdata = $input->get;

		$pg_plugin = $getdata->get('gateway_name', '', 'STRING');
		$order_id = $getdata->get('order', '', 'STRING');

		$payment_getway_form = $this->getHTML($pg_plugin, $order_id);
		echo json_encode($payment_getway_form);
		jexit();
	}

	/**
	 * Payment gateways notification URL expect stripe
	 * Collect data given by payment gateway to confrim order payment
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function processPayment()
	{
		$mainframe	= JFactory::getApplication();
		$jinput		= JFactory::getApplication()->input;
		$session 	= JFactory::getSession();

		if ($session->has('payment_submitpost'))
		{
			$post = $session->get('payment_submitpost');
			$session->clear('payment_submitpost');
		}
		else
		{
			$post = JRequest::get('post');
		}

		/**
		 * post = json_decode('{"mc_gross":"15.00","protection_eligibility":"Eligible","address_status":"confirmed",
		 * "payer_id":"KYCMB66E86NJ6","tax":"0.00","address_street":"1 Main St","payment_date":"04:16:38 Mar 27,
		 * 2014 PDT","payment_status":"Completed","charset":"windows-1252","address_zip":"95131","first_name":"amol",
		 * "mc_fee":"0.74","address_country_code":"US","address_name":"amol Gh","notify_version":"3.7","custom":"JGOID-0003",
		 * "payer_status":"verified","business":"sagar_c-facilitator@tekdi.net","address_country":"United States",
		 * "address_city":"San Jose","quantity":"0","verify_sign":"AVkS-tHU2h7x-Z4rkFdc48Ls6tx5ANPzfbfIz4YQdiPMX9wrdypUO0sn",
		 * "payer_email":"amol_g@tekdi.net","txn_id":"59H512502M183111N","payment_type":"instant","last_name":"Gh",
		 * "address_state":"CA","receiver_email":"sagar_c-facilitator@tekdi.net","payment_fee":"0.74","receiver_id":"MKR5A5SU2W9VL",
		 * "txn_type":"web_accept","item_name":"Techjoomla Test campaign","mc_currency":"USD","item_number":"",
		 * "residence_country":"US","test_ipn":"1","transaction_subject":"JGOID-0003","payment_gross":"15.00","ipn_track_id":"2cfb5c3ec55f",
		 * "main_response":1}',true);
		 *
		 *
		 * Do not delete : Sample stripe response
		 *
		 * $post = json_decode('{"order_id":"JGOID-00030","client":"com_jgive","payment_type":"recurring",
		 * "user_firstname":"Amol","user_id":"481","user_email":"amol_g@tekdi.net","item_name":"Test Campaign",
		 * "payment_description":"COM_JGIVE_PAYMENT_DESCRIPTION",
		 * "submiturl":"\/j33\/index.php\/all-campaigns?controller=donations&amp;task=confirmpayment&amp;
		 * processor=stripe&amp;
		 * order_id=JGOID-00030",
		 * "return":"http:\/\/amolg.tekdi.net\/j33\/index.php?option=com_jgive&view=donations&
		 * layout=details&donationid=29&processor=stripe&email=",
		 * "cancel_return":"http:\/\/amolg.tekdi.net\/j33\/index.php?option=com_jgive&view=donations&layout=details
		 * &donationid=29&processor=stripe&email=",
		 * "notify_url":"http:\/\/amolg.tekdi.net\/j33\/index.php?option=com_jgive&controller=donations&task=processPayment
		 * &processor=stripe&order_id=JGOID-00030",
		 * "url":"http:\/\/amolg.tekdi.net\/j33\/index.php?option=com_jgive&controller=donations&task=processPayment
		 * &processor=stripe&order_id=JGOID-00030",
		 * "campaign_promoter":"amol_g@tekdi.net","currency_code":"USD","amount":"649","is_recurring":"1",
		 * "recurring_frequency":"DAY","recurring_count":"5","country_code":"IN",
		 * "adaptiveReceiverList":null,"plan":{"id":"JGOID-00030","interval":"day","name":"Recurring donation plan",
		 * "created":1403700952,"amount":64900,"currency":"usd","object":"plan","livemode":false,"interval_count":1,
		 * "trial_period_days":null,"metadata":{"__PHP_Incomplete_Class_Name":"Stripe_AttachedObject"},
		 * "statement_description":null},"subscription":{"id":"cus_4HpbSRwp2r79Ou",
		 * "object":"customer","created":1403700960,"livemode":false,
		 * "description":null,"email":null,"delinquent":false,"metadata":{"__PHP_Incomplete_Class_Name":"Stripe_AttachedObject"},
		 * "subscriptions":{"__PHP_Incomplete_Class_Name":"Stripe_List"},"discount":null,"account_balance":0,"currency":"usd",
		 * "cards":{"__PHP_Incomplete_Class_Name":"Stripe_List"},"default_card":"card_104Hpb4BzsK63fcAZb7snd6k"}}',true);
		*/

		$pg_plugin = $jinput->get('processor', '', 'STRING');
		$model = $this->getModel('donations');

		$order_id = '';

		$order_id = $jinput->get('order_id', '', 'STRING');

		if ($pg_plugin == '2checkout')
		{
			$order_id = $post['vendor_order_id'];
		}
		elseif ($order_id == '')
		{
			if (isset($post['order_id']))
			{
				$order_id = $post['order_id'];
			}
		}

		if (!$order_id)
		{
			$mainframe->redirect(Juri::root());
		}

		if (empty($post) || empty($pg_plugin))
		{
			//JFactory::getApplication()->enqueueMessage(JText::_('COM_JGIVE_SOME_ERROR_OCCURRED'), 'error');

			//return;
		}

		$this->storelog($pg_plugin, $post);

		$response = $model->processPayment($post, $pg_plugin, $order_id);

		if (!empty($response['msg']))
		{
			$response['msg'] = trim($response['msg']);
		}

		if (!empty($response['msg']))
		{
			$mainframe->redirect($response['return'], $response['msg']);
		}
		else
		{
			$mainframe->redirect($response['return']);
		}
	}

	/**
	 * Stripe notification URL
	 * Collect data given by stripe to confrim order payment
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function stripeProcessPayment()
	{
		$mainframe	= JFactory::getApplication();
		$jinput		= JFactory::getApplication()->input;
		$post		= JRequest::get('post');
		$pg_plugin 	= $jinput->get('processor', '', 'STRING');
		$model		= $this->getModel('donations');
		$order_id 	= '';

		$order_id = $jinput->get('order_id', '', 'STRING');

		if (empty($order_id))
		{
			if (isset($post['order_id']))
			{
				$order_id = $post['order_id'];
			}
		}

		if ($pg_plugin == 'stripe')
		{
			// Retrieve the request's body and parse it as JSON
			$body = @file_get_contents('php://input');

			// Grab the event information
			$post = $event_json = json_decode($body, true);

			if ($event_json['type'] == 'invoice.payment_succeeded')
			{
				// Get plan id as order ID
				if (isset($event_json['data']['object']['lines']['data'][0]['plan']['id']))
				{
					$order_id = $event_json['data']['object']['lines']['data'][0]['plan']['id'];
				}
			}
			elseif (empty($order_id))
			{
				return 'undefined order id';
			}
		}

		if (empty($post) || empty($pg_plugin) )
		{
			JFactory::getApplication()->enqueueMessage(JText::_('COM_JGIVE_SOME_ERROR_OCCURRED'), 'error');

			return;
		}

		$this->storelog($pg_plugin, $post);

		$response = $model->processPayment($post, $pg_plugin, $order_id);

		if (!empty($response['msg']))
		{
			$mainframe->redirect($response['return'], $response['msg']);
		}
		else
		{
			$mainframe->redirect($response['return']);
		}
	}

	/**
	 * Collect data required to log & call plugin function to log it.
	 *
	 * @param   string  $name  Sender's user id
	 * @param   array   $data  Participants array
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function storelog($name, $data)
	{
		$data['main_response'] = 1;
		$data1 = array();
		$data1['raw_data'] = $data;
		$data1['JT_CLIENT'] = "com_jgive";

		$dispatcher = JDispatcher::getInstance();
		JPluginHelper::importPlugin('payment', $name);
		$data = $dispatcher->trigger('onTP_Storelog', array($data1));

		return;
	}

	/**
	 * Check user entered email already exist in db for new user registration.
	 *
	 * @return  string  Message for user.
	 *
	 * @since   1.6
	 */
	public function chkmail()
	{
		$jinput = JFactory::getApplication()->input;
		$email = $jinput->get('email', '', 'STRING');
		$model = $this->getModel('donations');
		$status = $model->checkMailExists($email);
		$e[] = $status;

		if ($status == 1)
		{
			$e[] = JText::_('COM_JGIVE_MAIL_EXISTS');
		}

		echo json_encode($e);
		jexit();
	}

	/**
	 * Validate user login.
	 *
	 * @return  string  The arguments to append to the redirect URL.
	 *
	 * @since   1.6
	 */
	public function login_validate()
	{
		$input = JFactory::getApplication()->input;
		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$redirect_url = JRoute::_('index.php?option=com_jgive&view=donations&layout=payment');
		$json = array();

		if ($user->id)
		{
			$json['redirect'] = $redirect_url;
		}

		if (!$json)
		{
			require_once JPATH_SITE . '/components/com_jgive/helpers/user.php';
			$userHelper = new UserHelper;

			// Login the user.

			if (!$userHelper->login(array('username' => $app->input->getString('email'), 'password' => $app->input->getString('password'))))
			{
				$json['error']['warning'] = JText::_('COM_JGIVE_CHECKOUT_ERROR_LOGIN');
			}
		}

		$json['redirect'] = $redirect_url;
		echo json_encode($json);
		$app->close();
	}

	/**
	 * Save order information.
	 *
	 * @params  void
	 * @return  string  The seleted payment gateway HTML.
	 *
	 * @since   1.7
	 */
	public function placeOrder()
	{
		$redirect_url = JRoute::_('index.php?option=com_jgive&view=donations');
		$input = JFactory::getApplication()->input;
		$post = $input->post;
		$model = $this->getModel('donations');

		$model->setSessionDonorData($post);
		$res = $model->addOrder($post);

		$session = JFactory::getSession();

		if ($session->get('JGIVE_order_id'))
		{
			$payment_plg = $session->get('payment_plg');
			$itemid = $input->get('Itemid', 0);
			$orderid = $session->get('JGIVE_order_id');
			$data['success_msg'] = JText::_('COM_JGIVE_ORDER_CREATED_SUCCESS');
			$data['success'] = 1;
			$data['order_id'] = $orderid;
			$data['orderHTML'] = $this->getorderHTML($orderid);
		}
		else
		{
			$data['success_msg'] = JText::_('COM_JGIVE_ORDER_CREATED_FAILED');
			$data['success'] = 0;
			$data['redirect_uri'] = $redirect_url;
			echo json_encode($data);
			jexit();
		}

		$data['gatewayhtml'] = $this->getHTML($post->get('gateways', '', 'STRING'));
		echo json_encode($data);
		jexit();
	}

	/**
	 * Gets the order preview before placing it
	 *
	 * @param   integer  $order_id  The primary key id for the order table.
	 *
	 * @return  string  The order preview in HTML
	 *
	 * @since   1.6
	 */
	public function getorderHTML($order_id)
	{
		$donationsHelper = new donationsHelper;
		$this->donation_details = $donationsHelper->getSingleDonationInfo($order_id);
		$params = JComponentHelper::getParams('com_jgive');
		$this->currency_code = $params->get('currency');
		$this->pstatus = $donationsHelper->getPStatusArray();
		$this->donations_site = 1;
		$this->retryPayment_show = 1;
		$this->retryPayment = new StdClass;
		$this->retryPayment->status = '';
		$this->retryPayment->msg = '';

		$jgiveFrontendHelper = new jgiveFrontendHelper;
		$billpath = $jgiveFrontendHelper->getViewpath('donations', 'details');

		ob_start();
			include $billpath;
			$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}
}
