<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.jefhikashop
 *
 * @copyright   Copyright (C) 2013 Efthimios Mavrogeorgiadis. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * JEF integration to allow automatic installation of extensions upon payment of a plan with HikaShop
 *
 * @package     Joomla.Plugin
 * @subpackage  System.jefhikashop
 * @since       2.5
 */
class PlgSystemJefhikashop extends JPlugin
{
	public function onAfterInitialise()
	{
		$app = JFactory::getApplication();
		$sessid = $app->input->get('sessid', null);
		if ( !$app->isSite() || !JFactory::getUser()->id || !is_null( $sessid ) )
		{
			return;
		}

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

		$plugin = JPluginHelper::getPlugin( 'system', 'jefreg' );
		$params = new JRegistry( $plugin->params );
		$files = $params->get( 'files', null );
		$files = preg_replace('/\s*=\s*>\s*/', '=>', $files);
		$files = preg_replace('/^\s*\**\s*/', '*', $files);
		$files = preg_split( '/\s+/', $files );
		$url = '';
		$productid = 0;
		foreach ( $files as $f )
		{
			if ( preg_match( '/^\*hikashop:'.$sessionvals['installapp'].'=>(.+?)[\?&]hikashopprod=(\d+).*?$/', trim( $f ), $matches ) )
			{
				$productid = $matches[2];
				break;
			}
		}
		if ( !$productid )
		{
			return;
		}

		include_once(rtrim(JPATH_ADMINISTRATOR) . '/components/com_hikashop/helpers/helper.php');

		$prodClass = hikashop_get('class.product');
		$product = $prodClass->get($productid);

		$config =& hikashop_config();
		$order_status_for_download = '"'.str_replace(',', '","', $config->get('order_status_for_download','confirmed,shipped')).'"';
		$download_time_limit = $config->get('download_time_limit',0);
		$deadline = '';
		if ($download_time_limit) {
			$deadline = ' AND (a.order_created + '.$download_time_limit.') >= UNIX_TIMESTAMP(NOW())';
		}

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select(array('a.order_id', 'c.file_id', 'd.file_pos'))
			->from($db->quoteName(hikashop_table('order'), 'a'))
			->join('RIGHT', $db->quoteName(hikashop_table('order_product'), 'b') . ' ON (' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('b.order_id') . ') AND (' . $db->quoteName('b.product_id') . ' = ' . $productid . ')')
			->join('RIGHT', $db->quoteName(hikashop_table('file'), 'c') . ' ON (' . $db->quoteName('c.file_ref_id') . ' = ' . $productid . ')')
			->join('LEFT', $db->quoteName(hikashop_table('download'), 'd') . ' ON (' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('d.order_id') . ' AND ' . $db->quoteName('c.file_id') . ' = ' . $db->quoteName('d.file_id') . ')')
			->where($db->quoteName('a.order_user_id') . ' = '.hikashop_loadUser().' AND ' . $db->quoteName('a.order_status') . ' IN ('.$order_status_for_download.')'.$deadline);
		$db->setQuery($query);
		$files = $db->loadObjectList();
		if (!count($files)) {
			return;
		}

		$file_pos = '';
		if($files[0]->file_pos > 0) {
			$file_pos = '&file_pos='.$file->file_pos;
		}
		
		$installfrom = '&installfrom='.base64_encode(JRoute::_(JURI::base().'index.php?option='.HIKASHOP_COMPONENT.'&ctrl=order&task=download&file_id='.$files[0]->file_id.'&order_id='.$files[0]->order_id.$file_pos.'&sessid='.session_id()));

		foreach ( $keys as $key )
		{
			$session->set( 'jefreg.' . $key, null );
		}
		$app->redirect( $sessionvals['installat'].$installfrom );
	}
}
