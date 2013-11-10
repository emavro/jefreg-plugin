<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.jefreg
 *
 * @copyright   Copyright (C) 2013 Efthimios Mavrogeorgiadis. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

jimport( 'joomla.session.storage' );
JFormHelper::loadRuleClass('url');

/**
 * Support for JoomlaApps download after registration functionality
 *
 * @package     Joomla.Plugin
 * @subpackage  System.jefreg
 * @since       2.5
 */
class PlgSystemJefreg extends JPlugin
{
	var $_jefreg = array();
	var $_sessionvals = array();
	
	private function getJEFReg()
	{
		if (!count($this->_jefreg))
		{
			$app = JFactory::getApplication();
			$this->_jefreg = array(
				'installat'	=>	base64_decode($app->input->get('installat', null, 'base64')),
				'installapp'	=>	$app->input->get('installapp', null, 'int'),
				'commercial'	=>	0,
				'timestamp'	=>	time(),
			);
		}
		return $this->_jefreg;
	}
	
	private function setCommercialOn()
	{
		$session = JFactory::getSession();
		$session->set('jefreg.commercial', 1);
		$this->_jefreg['commercial'] = 1;
	}
	
	private function isCommercial()
	{
		$session = JFactory::getSession();
		return $session->set('jefreg.commercial');
	}
	
	private function getSessionValues()
	{
		if (!$this->_sessionvals)
		{
			$session = JFactory::getSession();
			$jefreg = $this->getJEFReg();
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
		$jefreg = $this->getJEFReg();
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
		$jefreg = $this->getJEFReg();
		$field = new SimpleXMLElement('<field></field>');
		$rule = new JFormRuleUrl;
		return $rule->test($field, $jefreg['installat']) &&
			$this->getInstallFrom($jefreg['installapp']);
	}
	
	private function isSessionOK()
	{
		$jefreg = $this->getSessionValues();
		return $jefreg['timestamp'] &&
			time() < $jefreg['timestamp'] + 18 * 60 * 60 &&
			!is_null($jefreg['installat']) &&
			!is_null($jefreg['installapp']) &&
			!$jefreg['commercial'];
	}
	
	private function getInstallFrom($appid)
	{
		$files = $this->params->get('files', null);
		$files = preg_replace('/\s*=\s*>\s*/', '=>', $files);
		$files = preg_replace('/^\s*\**\s*/', '*', $files);
		$files = preg_split('/\s+/', $files);
		$installfrom = '';
		foreach ($files as $f)
		{
			if (preg_match('/^'.$appid.'=>(.+)/', trim($f), $matches))
			{
				$installfrom = '&installfrom='.base64_encode($matches[1].'&sessid='.session_id());
			}
			elseif (preg_match('/^\*.*:'.$appid.'=>(.+)/', trim($f), $matches))
			{
				$this->setCommercialOn();
				$installfrom = $matches[1];
			}
		}
		return $installfrom;
	}
	
	public function onAfterRoute()
	{
		$app = JFactory::getApplication();
		if ( !$app->isSite() )
		{
			return;
		}
		
		$sessid = $app->input->get('sessid', null);
		if (!is_null($sessid))
		{
			$sesstore = & JSessionStorage::getInstance('database');
			$sesstore->destroy($sessid);
		}
	}
	
	public function onAfterInitialise()
	{
		$app = JFactory::getApplication();
		if (!$app->isSite())
		{
			return;
		}
		
		$sessid = $app->input->get('sessid', null);
		if (!is_null($sessid))
		{
			$sesstore = & JSessionStorage::getInstance('database');
			$sdata = $sesstore->read($sessid);
			session_decode($sdata);
			return;
		}
		
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
			if ($this->isCommercial())
			{
				$jefreg = $this->getSessionValues();
				$installfrom = $this->getInstallFrom($jefreg['installapp']);
				$app->redirect(JRoute::_($installfrom));
			}
			elseif ($entry)
			{
				$app->redirect(JRoute::_($entry));
			}
			else
			{
				$app->redirect(JRoute::_('index.php?option=com_users&view=login'));
			}
		}
		else
		{
			$jefreg = $this->getJEFReg();
			if ($jefreg['installat']) {
				$installfrom = '&installfrom='.base64_encode('Extension could not be found.');
				$app->redirect($jefreg['installat'].$installfrom);
			}
		}
	}
	
	public function onUserAfterLogin()
	{
		$app = JFactory::getApplication();
		$sessid = $app->input->get('sessid', null);
		if (!$app->isSite() || $this->isCommercial() || !is_null($sessid))
		{
			return;
		}

		$session = JFactory::getSession();
		$session->set('user', JFactory::getUser());
		$sesstore = JSessionStorage::getInstance('database');
		$sesstore->write(session_id(), session_encode());

		if ($this->isSessionOK())
		{
			$jefreg = $this->getSessionValues();
			$this->setSessionValuesNull();
			$installfrom = $this->getInstallFrom($jefreg['installapp']);
			$app->redirect($jefreg['installat'].$installfrom);
		}
	}
}
