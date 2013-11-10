<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.jefcbsubs
 *
 * @copyright   Copyright (C) 2013 Efthimios Mavrogeorgiadis. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * JEF integration to allow automatic installation of extensions upon payment of a plan with CBSubs
 *
 * @package     Joomla.Plugin
 * @subpackage  System.jefcbsubs
 * @since       2.5
 */
class PlgSystemJefcbsubs extends JPlugin
{
	public function onAfterInitialise()
	{
		// Make sure we're not in the backend, user is logged in,
		// and $_GET['sessid'] has no value
		// Note: $_GET['sessid'] is set when the user backend requests file to install
		$app = JFactory::getApplication();
		$sessid = $app->input->get('sessid', null);
		if ( !$app->isSite() || !JFactory::getUser()->id || !is_null( $sessid ) )
		{
			return;
		}

		// Load needed CB files
		global $_CB_framework;
		include_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php';
		include_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/library/cb/cb.database.php';
		include_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.class.php';
		include_once( $_CB_framework->getCfg( 'absolute_path' ) . '/components/com_comprofiler/plugin/user/plug_cbpaidsubscriptions/cbpaidsubscriptions.class.php');

		// Populate $activePlans with user plans that are still active
		$activePlans = array();
		$paidUserExtension =& cbpaidUserExtension::getInstance( $_CB_framework->myId() );
		$subs = $paidUserExtension->getActiveSubscriptions();
		foreach ( array_keys( $subs ) as $k ) {
			$plan = $subs[$k]->getPlan();
			$plan_id = $plan->get( 'id');
			$activePlans[$plan_id] = strtolower( $subs[$k]->get( 'status' ) ) == 'a' ? true : false;
		}

		// Do nothing if user has no active plans
		if ( !count( $activePlans ) )
		{
			return;
		}
		
		// Make sure the needed data is stored in $_SESSION
		$keys = array( 'installat', 'installapp', 'timestamp' );
		$session = JFactory::getSession();
		foreach ( $keys as $key )
		{
			$sessionvals[$key] = $session->get( 'jefreg.' . $key, null );
		}
		$sessionOK = $sessionvals['timestamp'] &&
			time() < $sessionvals['timestamp'] + 18 * 60 * 60 &&
			!is_null($sessionvals['installat']) &&
			!is_null($sessionvals['installapp']);
		if ( !$sessionOK )
		{
			return;
		}

		// Get JEFReg plugin parameters
		$plugin = JPluginHelper::getPlugin( 'system', 'jefreg' );
		$params = new JRegistry( $plugin->params );
		$files = $params->get( 'files', null );
		$files = preg_replace('/\s*=\s*>\s*/', '=>', $files);
		$files = preg_replace('/^\s*\**\s*/', '*', $files);
		$files = preg_split( '/\s+/', $files );

		// Match the $_POST value of the JED ID [installapp] with the extensions available on the server
		// as listed in the 'files' parameter of the JEFReg plugin
		$installfrom = '';
		foreach ( $files as $f )
		{
			// Check only lines that start with *cbsubs: in the 'files' parameter of the JEFReg plugin
			preg_match( '/^\*cbsubs:'.$sessionvals['installapp'].'=>(.+?)&cbsubsplan=(\d+).*?$/', trim( $f ), $matches );

			// Make sure the user has access to the requested product
			if ( array_key_exists( $matches[2], $activePlans ) && $activePlans[$matches[2]] )
			{
				$url = JRoute::_( $matches[1] . '&sessid=' . session_id() );
				$installfrom = '&installfrom=' . base64_encode( $url );
				break;
			}
		}

		// Do nothing if the product does not exist or the user has no access rights to it
		if ( !$installfrom )
		{
			return;
		}

		// Set $_SESSION values to NULL once we are ready to redirect to user backend
		foreach ( $keys as $key )
		{
			$session->set( 'jefreg.' . $key, null );
		}

		// Redirect back to user backend with installation information
		$app->redirect( $sessionvals['installat'].$installfrom );
	}
}
