<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_jgive
 * @subpackage 	Trangell_Mellat
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/trangellmellat/helper.php';
if (!class_exists ('checkHack')) {
	require_once( dirname(__FILE__) . '/trangellmellat/trangell_inputcheck.php');
}

class PlgPaymentTrangellMellat extends JPlugin
{

	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		// Set the language in the class
		$config = JFactory::getConfig();
	}

	public function buildLayoutPath($layout)
	{
		$layout = trim($layout);

		if (empty($layout))
		{
			$layout = 'default';
		}

		$app = JFactory::getApplication();
		$core_file = dirname(__FILE__) . '/' . $this->_name . '/' . 'tmpl' . '/' . $layout . '.php';
	
			return  $core_file;
	}

	public function buildLayout($vars, $layout = 'default' )
	{
		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath($layout);
		include $layout;
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	public function onTP_GetInfo($config)
	{
		if (!in_array($this->_name, $config))
		{
			return;
		}

		$obj = new stdClass;
		$obj->name = $this->params->get('plugin_name');
		$obj->id = $this->_name;

		return $obj;
	}

	public function onTP_GetHTML($vars) {
		$app	= JFactory::getApplication();
		$Amount = round($vars->amount,0);
		$dateTime = JFactory::getDate();
			
		$fields = array(
			'terminalId' => $this->params->get('melatterminalId'),
			'userName' => $this->params->get('melatuser'),
			'userPassword' => $this->params->get('melatpass'),
			'orderId' => time(),
			'amount' => $Amount,
			'localDate' => $dateTime->format('Ymd'),
			'localTime' => $dateTime->format('His'),
			'additionalData' => '',
			'callBackUrl' => $vars->notify_url,
			'payerId' => 0,
			);
			
			try {
				$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
				$response = $soap->bpPayRequest($fields);
				
				$response = explode(',', $response->return);
				if ($response[0] != '0') { // if transaction fail
					$msg = plgPaymentTrangellMellatHelper::getGateMsg($response[0]); 
					$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
					$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				}
				else { // if success
					$vars->refid = $response[1];
					$vars->action_url = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';
					$html = $this->buildLayout($vars);
					return $html;
				}
		}
		catch(\SoapFault $e) {
			$msg= plgPaymentTrangellMellatHelper::getGateMsg('error'); 
			$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
			$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

	}

	public function onTP_Processpayment($data, $vars = array()) {
		$app	= JFactory::getApplication();		
		$jinput = $app->input;
		$ResCode = $jinput->post->get('ResCode', '1', 'INT'); 
		$SaleOrderId = $jinput->post->get('SaleOrderId', '1', 'INT'); 
		$SaleReferenceId = $jinput->post->get('SaleReferenceId', '1', 'INT'); 
		$RefId = $jinput->post->get('RefId', 'empty', 'STRING'); 
		if (checkHack::strip($RefId) != $RefId )
			$RefId = "illegal";
		$CardNumber = $jinput->post->get('CardHolderPan', 'empty', 'STRING'); 
		if (checkHack::strip($CardNumber) != $CardNumber )
			$CardNumber = "illegal";
			

		if (
			checkHack::checkNum($ResCode) &&
			checkHack::checkNum($SaleOrderId) &&
			checkHack::checkNum($SaleReferenceId) 
		){
			if ($ResCode != '0') {
				$msg= plgPaymentTrangellMellatHelper::getGateMsg($ResCode); 
				$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				return false;
			}
			else {
				$fields = array(
					'terminalId' => $this->params->get('melatterminalId'),
					'userName' => $this->params->get('melatuser'),
					'userPassword' => $this->params->get('melatpass'),
					'orderId' => $SaleOrderId, 
					'saleOrderId' =>  $SaleOrderId, 
					'saleReferenceId' => $SaleReferenceId
				);
				try {
					$soap = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
					$response = $soap->bpVerifyRequest($fields);

					if ($response->return != '0') {
						$msg= plgPaymentTrangellMellatHelper::getGateMsg($response->return); 
						$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						return false;
					}
					else {	
						$response = $soap->bpSettleRequest($fields);
						if ($response->return == '0' || $response->return == '45') {
							
							$msg= plgPaymentTrangellMellatHelper::getGateMsg($response->return); 
							JFactory::getApplication()->enqueueMessage('<h2>'.$msg.'</h2>'.'<h3>'. $SaleReferenceId .'شماره پیگری ' .'</h3>', 'Message');
							
							plgPaymentTrangellMellatHelper::saveComment(
									$this->params->get('plugin_name'), str_replace('JGOID-','',$vars->order_id),
										$SaleReferenceId .'شماره پیگری ' 
										. '  '. ' شماره کارت ' . $CardNumber
								);
							$result                 = array(
							'transaction_id' => '',
							'order_id' => $vars->order_id,
							'status' => 'C',
							'total_paid_amt' => $vars->amount,
							'raw_data' => '',
							'error' => '',
							'return' => $vars->return
							);

							return $result;
						}
						else {
							$msg= plgPaymentTrangellMellatHelper::getGateMsg($response->return); 
							$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
							return false;
						}
					}
				}
				catch(\SoapFault $e)  {
					$msg= plgPaymentTrangellMellatHelper::getGateMsg('error'); 
					$app	= JFactory::getApplication();
					$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
					$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
					return false;
				}
			}
		}
		else {
			$msg= plgPaymentTrangellMellatHelper::getGateMsg('hck2'); 
			$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=trangellmellat&order_id='.$vars->order_id,false);
			$app->redirect($link, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			return false;	
		}
	}


	public function onTP_Storelog($data)
	{
		$log_write = $this->params->get('log_write', '0');

		if ($log_write == 1)
		{
			$log = plgPaymentTrangellMellatHelper::Storelog($this->_name, $data);
		}
	}
}
