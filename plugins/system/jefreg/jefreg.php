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
 * Support for JEF (Install from Web) download after registration/purchase functionality
 *
 * @package     Joomla.Plugin
 * @subpackage  System.jefreg
 * @since       2.5
 */
class PlgSystemJefreg extends JPlugin
{
	var $_jefreg = array();		// $_POST data
	var $_sessionvals = array();	// $_SESSION data
	
	/**
	 * Populate $_jefreg with $_POST data
	 *
	 * @return  array  POST data
	 *
	 */
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
	
	/**
	 * Set the commercial flag ON
	 *
	 * @return  void
	 *
	 */
	private function setCommercialOn()
	{
		$session = JFactory::getSession();
		$session->set('jefreg.commercial', 1);
		$this->_jefreg['commercial'] = 1;
	}
	
	/**
	 * Check the commercial flag
	 *
	 * @return  boolean
	 *
	 */
	private function isCommercial()
	{
		$session = JFactory::getSession();
		return $session->get('jefreg.commercial');
	}
	
	/**
	 * Get the $_POST data as stored in $_SESSION
	 *
	 * @return  array  $_POST data as stored in $_SESSION
	 *
	 */
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
	
	/**
	 * Copy $_POST data to $_SESSION
	 *
	 * @return  void
	 *
	 */
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
	
	/**
	 * Set $_SESSION values to NULL once user has registered
	 *
	 * @return  void
	 *
	 */
	private function setSessionValuesNull()
	{
		$this->setSessionValues(true);
	}
	
	/**
	 * Check if $_POST data is what we expect it to be
	 *
	 * @return  boolean  true = OK, false = NOT OK
	 *
	 */
	private function isDataOK()
	{
		$jefreg = $this->getJEFReg();
		$field = new SimpleXMLElement('<field></field>');
		$rule = new JFormRuleUrl;
		return $rule->test($field, $jefreg['installat']) &&
			$this->getInstallFrom($jefreg['installapp']);
	}
	
	/**
	 * Check if $_SESSION contains the data we need
	 *
	 * @return  boolean  true = OK, false = NOT OK
	 *
	 */
	private function isSessionOK()
	{
		$jefreg = $this->getSessionValues();
		return $jefreg['timestamp'] &&
			time() < $jefreg['timestamp'] + 18 * 60 * 60 &&
			!is_null($jefreg['installat']) &&
			!is_null($jefreg['installapp']) &&
			!$jefreg['commercial'];
	}
	
	/**
	 * Get the URL the user's backend should access to directly retrieve the installation/update file
	 *
	 * @param   integer	$appid	Extension JED ID
	 *
	 * @return  string	URL pointing to package, XML file, or direct download script
	 *
	 */
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
	
	/**
	 * Remove $_SESSION data from the database
	 *
	 * @return  void
	 *
	 */
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
	
	/**
	 * Entry point to handle original $_POST data
	 *
	 * @return  void
	 *
	 */
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
	
	/**
	 * Redirect user after login
	 *
	 * @return  void
	 *
	 */
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
