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
	 * Populate/Return $_jefreg with $_POST data
	 *
	 * @return  array  $_POST data
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
	 * Populate/Return $_sessionvals with $_POST data as stored in $_SESSION
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
	 * @param   boolean	$null	If set to TRUE, all jefreg $_SESSION values are set to NULL
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
	 * Set $_SESSION values to NULL once we are ready to redirect to user backend
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
		
		// Use JFormRuleUrl to check if $_POST['installat'] is valid URL
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
	 * Get the URL the user backend should access to directly retrieve the installation/update file
	 *
	 * @param   integer	$appid	Extension JED ID
	 *
	 * @return  string	URL pointing to package, XML file, or direct download script
	 *
	 */
	private function getInstallFrom($appid)
	{
		// Basic clean up of 'files' parameter as set in plugin options
		$files = $this->params->get('files', null);
		$files = preg_replace('/\s*=\s*>\s*/', '=>', $files);
		$files = preg_replace('/^\s*\**\s*/', '*', $files);
		$files = preg_split('/\s+/', $files);

		// Match the $_POST value of the JED ID [$appid] with the extensions available on the server
		// as listed in the 'files' parameter of the plugin
		$installfrom = '';
		foreach ($files as $f)
		{
			// Check for file that is available after registration
			if (preg_match('/^'.$appid.'=>(.+)/', trim($f), $matches))
			{
				$installfrom = '&installfrom='.base64_encode($matches[1].'&sessid='.session_id());
			}
			// Check for file that is available after purchase
			elseif (preg_match('/^\*.*:'.$appid.'=>(.+)/', trim($f), $matches))
			{
				// If found, set commercial flag ON
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
		
		// Remove $_SESSION data from the database only if $_GET['sessid'] has a value
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
		// Make sure we're not in the backend
		$app = JFactory::getApplication();
		if (!$app->isSite())
		{
			return;
		}

		// Retrieve $_SESSION data from the database if $_GET['sessid'] has a value		
		// Note: $_GET['sessid'] is set when the user backend requests file to install
		$sessid = $app->input->get('sessid', null);
		if (!is_null($sessid))
		{
			$sesstore = & JSessionStorage::getInstance('database');
			$sdata = $sesstore->read($sessid);
			session_decode($sdata);
			return;
		}
		
		// Check if $_POST data is what we expect it to be
		if ($this->isDataOK())
		{
			// Copy $_POST data to $_SESSION
			$this->setSessionValues();

			// If user is already logged in and our $_SESSION values are OK,
			// redirect back to user backend with installation information
			if(JFactory::getUser()->id && $this->isSessionOK())
			{
				$jefreg = $this->getSessionValues();
				$this->setSessionValuesNull();
				$installfrom = $this->getInstallFrom($jefreg['installapp']);
				$app->redirect($jefreg['installat'].$installfrom);
			}

			// Get the optional 'entry' parameter from the plugin options
			$entry = $this->params->get('entry', null);

			// If the extension is commercial, redirect to the URL submitted by the developer on JED/JEF
			if ($this->isCommercial())
			{
				$jefreg = $this->getSessionValues();
				$installfrom = $this->getInstallFrom($jefreg['installapp']);
				$app->redirect(JRoute::_($installfrom));
			}
			// If the 'entry' parameter has been set, redirect there
			elseif ($entry)
			{
				$app->redirect(JRoute::_($entry));
			}
			// If the 'entry' parameter has not been set, redirect to default login page
			else
			{
				$app->redirect(JRoute::_('index.php?option=com_users&view=login'));
			}
		}
		else
		{
			// If $_POST data fails validation testing, redirect to user backend with error message
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
		// Make sure we're not in the backend, the requested extension is not commercial,
		// and $_GET['sessid'] has no value
		// Note: $_GET['sessid'] is set when the user backend requests file to install
		$app = JFactory::getApplication();
		$sessid = $app->input->get('sessid', null);
		if (!$app->isSite() || $this->isCommercial() || !is_null($sessid))
		{
			return;
		}

		// Make sure user login data is stored in $_SESSION and database
		$session = JFactory::getSession();
		$session->set('user', JFactory::getUser());
		$sesstore = JSessionStorage::getInstance('database');
		$sesstore->write(session_id(), session_encode());

		// If $_SESSION values are OK, redirect back to user backend with installation information
		if ($this->isSessionOK())
		{
			$jefreg = $this->getSessionValues();
			$this->setSessionValuesNull();
			$installfrom = $this->getInstallFrom($jefreg['installapp']);
			$app->redirect($jefreg['installat'].$installfrom);
		}
	}
}
