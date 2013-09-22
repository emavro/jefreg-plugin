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
 * @since       1.5
 */
class plgSystemJoomlaapps extends JPlugin
{
	var $_joomlaapps = array();
	var $_sessionvals = array();
	
	private function getJoomlaapps()
	{
		if (!count($this->_joomlaapps))
		{
			$this->_joomlaapps = array(
				'installat'	=>	trim(base64_decode($_REQUEST['installat'])),
				'installapp'	=>	((int) $_REQUEST['installapp']),
				'timestamp'	=>	time(),
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
		$session = JFactory::getSession();
		$joomlaapps = $this->getJoomlaapps();
		
		//http://daringfireball.net/2010/07/improved_regex_for_matching_urls
		$regex = '_^(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))$_iuS';

		return preg_match($regex, $joomlaapps['installat']) &&
			$this->getInstallFrom($joomlaapps['installapp']);
	}
	
	private function isSessionOK()
	{
		$joomlaapps = $this->getSessionValues();
		return $joomlaapps['timestamp'] &&
			time() < $joomlaapps['timestamp'] + 18 * 60 * 60 &&
			$joomlaapps['installat'] &&
			$joomlaapps['installapp'];
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
			$entry = $this->params->get('entry', null);
			if ($entry)
			{
				$app->redirect($entry);
			}
			else
			{
				$app->redirect(JRoute::_('index.php?option=com_user&view=login'));
			}
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
	
	public function onLoginUser()
	{
		if ($this->isSessionOK())
		{
			$joomlaapps = $this->getSessionValues();
			$this->setSessionValuesNull();
			$installfrom = $this->getInstallFrom($joomlaapps['installapp']);
			$app = JFactory::getApplication();
			$app->redirect($joomlaapps['installat'].$installfrom);
		}
	}
}
