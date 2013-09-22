<?php
/**
 * Joomla! Apps Hook Function
 *
 * @package    Joomla! Apps
 * @author     Efthimios Mavrogeorgiadis <admin@iota.gr>
 * @copyright  Copyright (c) Efthimios Mavrogeorgiadis 2013
 * @license    GNU General Public License version 2 or later
 * @version    1.0.0
 * @link       https://github.com/emavro/joomlaapps-plugin/tree/whmcs
 */

if (!defined("WHMCS"))
{
    die("This file cannot be accessed directly");
}

class Joomlaapps
{
    var $_joomlaapps = array();
    var $_sessionvals = array();
    var $_db = null;
    var $_admin = null;
    var $_clientid = null;
    
    public function getJoomlaapps()
    {
        if (!count($this->_joomlaapps))
        {
            $this->_joomlaapps = array(
                'installat'	=>	trim(base64_decode($_REQUEST['installat'])),
                'installapp'    =>	((int) $_REQUEST['installapp']),
                'pid'           =>	((int) $_REQUEST['pid']),
                'timestamp'	=>	time(),
            );
        }
        return $this->_joomlaapps;
    }
    
    public function getSessionValues()
    {
        if (!$this->_sessionvals)
        {
            $joomlaapps = $this->getJoomlaapps();
            foreach ($joomlaapps as $key => $value)
            {
                $this->_sessionvals[$key] = $_SESSION['joomlaapps.' . $key] ? $_SESSION['joomlaapps.' . $key] : null;
            }
        }
        return $this->_sessionvals;
    }
    
    public function setSessionValues($null = false)
    {
        $joomlaapps = $this->getJoomlaapps();
        foreach ($joomlaapps as $key => $value)
        {
            if ($null)
            {
                $value = null;
            }
            $_SESSION['joomlaapps.' . $key] = $value;
        }
    }
    
    public function setSessionValuesNull()
    {
        $this->setSessionValues(true);
    }
    
    public function isDataOK()
    {
        $joomlaapps = $this->getJoomlaapps();
        
        //http://daringfireball.net/2010/07/improved_regex_for_matching_urls
        $regex = '_^(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))$_iuS';

        return preg_match($regex, $joomlaapps['installat']) &&
            $this->isPIDValid($joomlaapps['pid']);
    }
    
    public function isSessionOK()
    {
        $joomlaapps = $this->getSessionValues();
        return $joomlaapps['timestamp'] &&
            time() < $joomlaapps['timestamp'] + 18 * 60 * 60 &&
            $joomlaapps['installat'] &&
            $joomlaapps['installapp'];
    }
    
    private function getDB()
    {
        if (is_null($this->_db))
        {
            include realpath("configuration.php");

            $this->_db = mysql_connect($db_host, $db_username, $db_password);
            mysql_select_db($db_name, $this->_db);
        }
        return $this->_db;
    }
    
    public function getAdmin()
    {
        if (is_null($this->_admin))
        {
            $query = "SELECT a.username FROM tbladmins AS a "
                ."JOIN tbladminroles AS b ON b.name = 'Full Administrator' AND b.id = a.roleid "
                ."ORDER BY a.id LIMIT 1";
            $result = mysql_query($query, $this->getDB());
            $this->_admin = mysql_fetch_object($result)->username;
        }
        return $this->_admin;
    }
    
    private function isPIDValid($pid)
    {
        $command = "getproducts";
        $adminuser = $this->getAdmin();
        $values["pid"] = (int) $pid;

        $results = localAPI($command, $values, $adminuser);
        if (
            is_array($results) &&
            array_key_exists('result', $results) &&
            trim(strtolower($results['result'])) == 'success' &&
            array_key_exists('totalresults', $results) &&
            $results['totalresults']
        )
        {
            return true;
        }
        return false;
    }
    
    public function getDownload($pid, $serviceid)
    {
        $query = "SELECT downloads FROM tblproducts WHERE id = " . (int) $pid;
        $result = mysql_query($query, $this->getDB());
        $downloads = unserialize(mysql_fetch_object($result)->downloads);
        if (is_array($downloads) && count($downloads))
        {
            return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . preg_replace('/\/[^\/]+?\.php.*/', '/dl.php?type=d&id=' . $downloads[0] . '&serviceid=' . $serviceid, $_SERVER['REQUEST_URI']);
        }
        return false;
    }
    
    public function getClientID($vars)
    {
        if (is_null($this->_clientid))
        {
            if (
                array_key_exists('loggedin', $vars) &&
                $vars['loggedin'] &&
                array_key_exists('loggedinuser', $vars) &&
                is_array($vars['loggedinuser']) &&
                array_key_exists('userid', $vars['loggedinuser']) &&
                is_int($vars['loggedinuser']['userid'] + 0)
            )
            {
                $this->_clientid = $vars['loggedinuser']['userid'];
            }
        }
        return $this->_clientid;
    }
}

function initiateJoomlaapps($vars)
{
    $japps = new Joomlaapps;
    if ($japps->isDataOK())
    {
        $japps->setSessionValues();
        $joomlaapps = $japps->getSessionValues();
        header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . preg_replace('/cart\.php.*/', '', $_SERVER['REQUEST_URI']). 'cart.php?a=add&pid=' . $joomlaapps['pid']);
    }
    else
    {
        $joomlaapps = $japps->getJoomlaapps();
        if ($joomlaapps['installat']) {
            $installfrom = '&installfrom='.base64_encode('Extension could not be found.');
            header('Location: ' . $joomlaapps['installat'].$installfrom);
        }
    }
}

function isProductBought($vars)
{
    $japps = new Joomlaapps;
    $clientid = $japps->getClientID($vars);
    if ($clientid && $japps->isSessionOK())
    {
        $installfrom = false;
        $joomlaapps = $japps->getSessionValues();

        $command = "getclientsproducts";
        $adminuser = $japps->getAdmin();
        $values["clientid"] = $clientid;
        $values["pid"] = $joomlaapps['pid'];
 
        $results = localAPI($command, $values, $adminuser);
        if (
            is_array($results) &&
            array_key_exists('result', $results) &&
            trim(strtolower($results['result'])) == 'success' &&
            array_key_exists('totalresults', $results) &&
            $results['totalresults'] &&
            array_key_exists('products', $results) &&
            is_array($results['products']) &&
            count($results['products'])
        )
        {
            foreach ($results['products'] as $key => $value)
            {
                foreach ($value as $v)
                {
                    if (
                        array_key_exists('id', $v) &&
                        is_int($v['id'] + 0) &&
                        array_key_exists('pid', $v) &&
                        $v['pid'] == $joomlaapps['pid'] &&
                        array_key_exists('nextduedate', $v) &&
                        (
                            $v['nextduedate'] == '0000-00-00' ||
                            time() <= strtotime($v['nextduedate'] . ' 23:00:00')
                        ) &&
                        array_key_exists('status', $v) &&
                        trim(strtolower($v['status'])) == 'active' &&
                        array_key_exists('orderid', $v) &&
                        is_int($v['orderid'] + 0)
                    )
                    {
                        $command = "getorders";
                        $adminuser = $japps->getAdmin();
                        $values["id"] = $v['orderid'];
                 
                        $results = localAPI($command, $values, $adminuser);
                        if (
                            is_array($results) &&
                            array_key_exists('result', $results) &&
                            trim(strtolower($results['result'])) == 'success' &&
                            array_key_exists('totalresults', $results) &&
                            $results['totalresults'] &&
                            array_key_exists('orders', $results) &&
                            is_array($results['orders']) &&
                            count($results['orders'])
                        )
                        {
                            foreach ($results['orders'] as $okey => $ovalue)
                            {
                                foreach ($ovalue as $ov)
                                {
                                    if (
                                        array_key_exists('id', $ov) &&
                                        $ov['id'] == $v['orderid'] &&
                                        array_key_exists('status', $ov) &&
                                        trim(strtolower($ov['status'])) == 'active' &&
                                        array_key_exists('paymentstatus', $ov) &&
                                        trim(strtolower($ov['paymentstatus'])) == 'paid'
                                    )
                                    {
                                        $installfrom = $japps->getDownload($joomlaapps['pid'], $v['id']);
                                        if ($installfrom)
                                        {
                                            $installfrom = '&installfrom='.base64_encode($installfrom);
                                        }
                                        break 4;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if ($installfrom && $joomlaapps['installat'])
        {
            $japps->setSessionValuesNull();
            header('Location: ' . $joomlaapps['installat'].$installfrom);
        }
    }
}

add_hook("ClientAreaPage",1,"initiateJoomlaapps");
add_hook("ClientAreaPage",2,"isProductBought");
