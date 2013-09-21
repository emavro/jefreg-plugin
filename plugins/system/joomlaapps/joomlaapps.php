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
		$version = new JVersion;
		if ($version->isCompatible('3.0.0'))
		{
			jimport('joomla.form.rule.url');
		}
		else
		{
			JLoader::register('JFormRuleUrl', JPATH_PLATFORM . '/joomla/form/rules/url.php');
		}
		$session = JFactory::getSession();
		$joomlaapps = $this->getJoomlaapps();
		$field = new SimpleXMLElement('<field></field>');
		$rule = new JFormRuleUrl;
		return $rule->test($field, $joomlaapps['installat']) &&
			$this->getInstallFrom($joomlaapps['installapp']);
	}
	
	private function isSessionOK()
	{
		$joomlaapps = $this->getSessionValues();
		return $joomlaapps['timestamp'] &&
			time() < $joomlaapps['timestamp'] + 18 * 60 * 60 &&
			!is_null($joomlaapps['installat']) &&
			!is_null($joomlaapps['installapp']);
	}
	
	private function getInstallFrom($appid)
	{
		$files = $this->params->get('files', null);
		$files = preg_replace('/\s*=\s*>\s*/', '=>', $files);
		$files = preg_split('/\s+/', $files);
		$installfrom = '';
		foreach ($files as $f)
		{
			if (preg_match('/^'.$appid.'=>(.+)/', trim($f), $matches))
			{
				$installfrom = '&installfrom='.base64_encode($matches[1]);
			}
		}
		return $installfrom;
	}
	
	public function onAfterInitialise()
	{
		$app = JFactory::getApplication();
		if ($this->isDataOK())
		{
			$this->setSessionValues();
			if(JFactory::getUser()->id && $this->isSessionOK())
			{
				$joomlaapps = $this->getSessionValues();
				$this->setSessionValuesNull();
				$installfrom = $this->getInstallFrom($joomlaapps['installapp']);
				$app->redirect($joomlaapps['installat'].$installfrom);
			}
			$app->redirect(JRoute::_('index.php?option=com_users&view=login'));
		}
		else
		{
			$joomlaapps = $this->getJoomlaapps();
			if ($joomlaapps['installat']) {
				$installfrom = '&installfrom='.base64_encode('Extension could not be found.');
				$app->redirect($joomlaapps['installat'].$installfrom);
			}
		}
	}
	
	public function onUserLogin()
	{
		if ($this->isSessionOK())
		{
			$joomlaapps = $this->getSessionValues();
			$this->setSessionValuesNull();
			$installfrom = $this->getInstallFrom($joomlaapps['installapp']);
			$app = JFactory::getApplication();
			$app->setUserState('users.login.form.return', $joomlaapps['installat'].$installfrom);
		}
	}
}
