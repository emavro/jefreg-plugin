<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.jefreg
 *
 * @copyright   Copyright (C) 2013 Efthimios Mavrogeorgiadis. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Support for download after registration through Joomla! Extension Finder
 *
 * @package     Joomla.Plugin
 * @subpackage  System.jefreg
 * @since       1.5
 */
class plgSystemJefreg extends JPlugin
{
	var $_jefreg = array();
	var $_sessionvals = array();
	
	private function getJefreg()
	{
		if (!count($this->_jefreg))
		{
			$this->_jefreg = array(
				'installat'	=>	trim(base64_decode($_REQUEST['installat'])),
				'installapp'	=>	((int) $_REQUEST['installapp']),
				'timestamp'	=>	time(),
			);
		}
		return $this->_jefreg;
	}
	
	private function getSessionValues()
	{
		if (!$this->_sessionvals)
		{
			$session = JFactory::getSession();
			$jefreg = $this->getJefreg();
			foreach ($jefreg as $key => $value)
			{
				$this->_sessionvals[$key] = $session->get('jefreg.' . $key, null);
			}
		}
		return $this->_sessionvals;
	}
	
	private function setSessionValues($null = false)
	{
		$session = JFactory::getSession();
		$jefreg = $this->getJefreg();
		foreach ($jefreg as $key => $value)
		{
			if ($null)
			{
				$value = null;
			}
			$session->set('jefreg.' . $key, $value);
		}
	}
	
	private function setSessionValuesNull()
	{
		$this->setSessionValues(true);
	}
	
	private function isDataOK()
	{
		$jefreg = $this->getJefreg();
		
		//http://daringfireball.net/2010/07/improved_regex_for_matching_urls
		$regex = '_^(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))$_iuS';

		return preg_match($regex, $jefreg['installat']) &&
			$this->getInstallFrom($jefreg['installapp']);
	}
	
	private function isSessionOK()
	{
		$jefreg = $this->getSessionValues();
		return $jefreg['timestamp'] &&
			time() < $jefreg['timestamp'] + 18 * 60 * 60 &&
			$jefreg['installat'] &&
			$jefreg['installapp'];
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
				$jefreg = $this->getSessionValues();
				$this->setSessionValuesNull();
				$installfrom = $this->getInstallFrom($jefreg['installapp']);
				$app->redirect($jefreg['installat'].$installfrom);
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
			$jefreg = $this->getJefreg();
			if ($jefreg['installat']) {
				$installfrom = '&installfrom='.base64_encode('Extension could not be found.');
				$app->redirect($jefreg['installat'].$installfrom);
			}
		}
	}
	
	public function onLoginUser()
	{
		if ($this->isSessionOK())
		{
			$jefreg = $this->getSessionValues();
			$this->setSessionValuesNull();
			$installfrom = $this->getInstallFrom($jefreg['installapp']);
			$app = JFactory::getApplication();
			$app->redirect($jefreg['installat'].$installfrom);
		}
	}
}
