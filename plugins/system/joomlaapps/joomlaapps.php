<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.joomlaapps
 *
 * @copyright   Copyright (C) 2013 Efthimios Mavrogeorgiadis. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Support for JoomlaApps download after registration functionality
 *
 * @package     Joomla.Plugin
 * @subpackage  System.joomlaapps
 * @since       3.1
 */
class PlgSystemJoomlaapps extends JPlugin
{
	var $_joomlaapps = array();
	var $_sessionvals = array();
	
	private function getJoomlaapps()
	{
		if (!count($this->_joomlaapps))
		{
			$app = JFactory::getApplication();
			$this->_joomlaapps = array(
				'installat'	=>	base64_decode($app->input->get('installat', null, 'base64')),
				'installapp'	=>	$app->input->get('installapp', null, 'int'),
				'timestamp'	=>	time(),
				'product'	=>	addslashes(base64_decode($app->input->get('product', '', 'base64'))),
				'release'	=>	preg_replace('/[^\d\.]/', '', base64_decode($app->input->get('release', '', 'base64'))),
				'dev_level'	=>	(int) base64_decode($app->input->get('dev_level', '', 'base64')),
			);
		}
		return $this->_joomlaapps;
	}
	
	private function getSessionValues()
	{
		if (!$this->_sessionvals)
		{
			$session = JFactory::getSession();
			$joomlaapps = $this->getJoomlaapps();
			foreach ($joomlaapps as $key => $value)
			{
				$this->_sessionvals[$key] = $session->get('joomlaapps.' . $key, null);
			}
		}
		return $this->_sessionvals;
	}
	
	private function setSessionValues($null = false)
	{
		$session = JFactory::getSession();
		$joomlaapps = $this->getJoomlaapps();
		foreach ($joomlaapps as $key => $value)
		{
			if ($null)
			{
				$value = null;
			}
			$session->set('joomlaapps.' . $key, $value);
		}
	}
	
	private function setSessionValuesNull()
	{
		$this->setSessionValues(true);
	}
	
	private function isDataOK()
	{
		jimport('joomla.form.rule.url');
		$session = JFactory::getSession();
		$joomlaapps = $this->getJoomlaapps();
		$field = new SimpleXMLElement('<field></field>');
		$rule = new JFormRuleUrl;
		return $rule->test($field, $joomlaapps['installat']) &&
			is_int($joomlaapps['installapp']);
	}
	
	private function isSessionOK()
	{
		$joomlaapps = $this->getSessionValues();
		return $joomlaapps['timestamp'] &&
			time() < $joomlaapps['timestamp'] + 18 * 60 * 60 &&
			!is_null($joomlaapps['installat']) &&
			!is_null($joomlaapps['installapp']);
	}
	
	private function getInstallFrom() {
		$joomlaapps = $this->getSessionValues();
		$this->setSessionValuesNull();
		$files = $this->params->get('files', null);
		$files = preg_replace('/\s*=\s*>\s*/', '=>', $files);
		$files = preg_split('/\s+/', $files);
		$installfrom = '';
		foreach ($files as $f) {
			if (preg_match('/^'.$joomlaapps['installapp'].'=>(.+)/', trim($f), $matches)) {
				$installfrom = '&installfrom='.base64_encode($matches[1]);
			}
		}
		return array($joomlaapps, $installfrom);
	}
	
	public function onAfterInitialise()
	{
		if ($this->isDataOK())
		{
			$this->setSessionValues();
			if(JFactory::getUser()->id && $this->isSessionOK())
			{
				$array = $this->getInstallFrom();
				$joomlaapps = $array[0];
				$installfrom = $array[1];
				$app = JFactory::getApplication();
				$app->redirect($joomlaapps['installat'].$installfrom);
			}
			$app = JFactory::getApplication();
			$app->redirect(JRoute::_('index.php?option=com_users&view=login'));
		}
	}
	
	public function onUserLogin()
	{
		if ($this->isSessionOK()) {
			$array = $this->getInstallFrom();
			$joomlaapps = $array[0];
			$installfrom = $array[1];
			$app = JFactory::getApplication();
			$app->setUserState('users.login.form.return', $joomlaapps['installat'].$installfrom);
		}
	}
}
