<?php
/**
 * Joomla! Extension Finder Hook Function
 *
 * @package    JEFReg
 * @author     Efthimios Mavrogeorgiadis <admin@iota.gr>
 * @copyright  Copyright (c) Efthimios Mavrogeorgiadis 2013
 * @license    GNU General Public License version 2 or later
 * @version    1.0.0
 * @link       http://www.iota.gr/
 */

if (!defined("WHMCS"))
{
    die("This file cannot be accessed directly");
}

class JEFReg
{
    var $_jefreg = array();
    var $_sessionvals = array();
    var $_db = null;
    var $_admin = null;
    var $_clientid = null;
    
    public function getJEFReg()
    {
        if (!count($this->_jefreg))
        {
            $this->_jefreg = array(
                'installat'	=>	trim(base64_decode($_REQUEST['installat'])),
                'installapp'    =>	((int) $_REQUEST['installapp']),
                'pid'           =>	((int) $_REQUEST['pid']),
                'timestamp'	=>	time(),
            );
        }
        return $this->_jefreg;
    }
    
    public function getSessionValues()
    {
        if (!$this->_sessionvals)
        {
            $jefreg = $this->getJEFReg();
            foreach ($jefreg as $key => $value)
            {
                $this->_sessionvals[$key] = $_SESSION['jefreg.' . $key] ? $_SESSION['jefreg.' . $key] : null;
            }
        }
        return $this->_sessionvals;
    }
    
    public function setSessionValues($null = false)
    {
        $jefreg = $this->getJEFReg();
        foreach ($jefreg as $key => $value)
        {
            if ($null)
            {
                $value = null;
            }
            $_SESSION['jefreg.' . $key] = $value;
        }
    }
    
    public function setSessionValuesNull()
    {
        $this->setSessionValues(true);
    }
    
    public function isDataOK()
    {
        $jefreg = $this->getJEFReg();
        
        //http://daringfireball.net/2010/07/improved_regex_for_matching_urls
        $regex = '_^(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))$_iuS';

        return preg_match($regex, $jefreg['installat']) &&
            $this->isPIDValid($jefreg['pid']);
    }
    
    public function isSessionOK()
    {
        $jefreg = $this->getSessionValues();
        return $jefreg['timestamp'] &&
            time() < $jefreg['timestamp'] + 18 * 60 * 60 &&
            $jefreg['installat'] &&
            $jefreg['installapp'];
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

function initiateJEFReg($vars)
{
    $japps = new JEFReg;
    if ($japps->isDataOK())
    {
        $japps->setSessionValues();
        $jefreg = $japps->getSessionValues();
        header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . preg_replace('/cart\.php.*/', '', $_SERVER['REQUEST_URI']). 'cart.php?a=add&pid=' . $jefreg['pid']);
    }
    else
    {
        $jefreg = $japps->getJEFReg();
        if ($jefreg['installat']) {
            $installfrom = '&installfrom='.base64_encode('Extension could not be found.');
            header('Location: ' . $jefreg['installat'].$installfrom);
        }
    }
}

function isProductBought($vars)
{
    $japps = new JEFReg;
    $clientid = $japps->getClientID($vars);
    if ($clientid && $japps->isSessionOK())
    {
        $installfrom = false;
        $jefreg = $japps->getSessionValues();

        $command = "getclientsproducts";
        $adminuser = $japps->getAdmin();
        $values["clientid"] = $clientid;
        $values["pid"] = $jefreg['pid'];
 
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
                        $v['pid'] == $jefreg['pid'] &&
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
                                        $installfrom = $japps->getDownload($jefreg['pid'], $v['id']);
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
        
        if ($installfrom && $jefreg['installat'])
        {
            $japps->setSessionValuesNull();
            header('Location: ' . $jefreg['installat'].$installfrom);
        }
    }
}

add_hook("ClientAreaPage",1,"initiateJEFReg");
add_hook("ClientAreaPage",2,"isProductBought");
