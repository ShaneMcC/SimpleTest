<?php
    /**
     *	Base include file for SimpleTest
     *	@package	SimpleTest
     *	@subpackage	WebTester
     *	@version	$Id$
     */
    
    /**
     * @ignore    Originally defined in simple_test.php
     */
    if (! defined('SIMPLE_TEST')) {
        define('SIMPLE_TEST', 'simpletest/');
    }
    require_once(SIMPLE_TEST . 'http.php');
    
    /**
     *    Represents a single security realm's identity.
	 *    @package SimpleTest
	 *    @subpackage WebTester
     */
    class SimpleRealm {
        var $_type;
        var $_root;
        var $_username;
        var $_password;
        
        /**
         *    Starts with the initial entry directory.
         *    @param string $type      Authentication type for this
         *                             realm. Only Basic authentication
         *                             is currently supported.
         *    @param SimpleUrl $url    Somewhere in realm.
         *    @access public
         */
        function SimpleRealm($type, $url) {
            $this->_type = $type;
            $this->_root = $url;
            $this->_username = false;
            $this->_password = false;
        }
        
        /**
         *    Adds another location to the realm.
         *    @param SimpleUrl $url    Somewhere in realm.
         *    @access public
         */
        function mergeUrl($url) {
        }
        
        /**
         *    Sets the identity to try within this realm.
         *    @param string $username    Username in authentication dialog.
         *    @param string $username    Password in authentication dialog.
         *    @access public
         */
        function setIdentity($username, $password) {
            $this->_username = $username;
            $this->_password = $password;
        }
        
        /**
         *    Accessor for current identity.
         *    @return string        Last succesful username.
         *    @access public
         */
        function getUsername() {
            return $this->_username;
        }
        
        /**
         *    Accessor for current identity.
         *    @return string        Last succesful password.
         *    @access public
         */
        function getPassword() {
            return $this->_password;
        }
        
        /**
         *    Test to see if the URL is within the directory
         *    tree of the realm.
         *    @param SimpleUrl $url    URL to test.
         *    @return boolean          True if subpath.
         *    @access public
         */
        function isWithin($url) {
            $stem = $this->_getSignificant($url);
            $root = $this->_getSignificant($this->_root);
            return (strpos($stem, $root) === 0);
        }
        
        /**
         *    Gets significant part of URL.
         *    @param SimpleUrl $url    Url to extract discrimitory path
         *                             information from.
         *    @access private
         */
        function _getSignificant($url) {
            return $url->getHost() . $url->getBasePath();
        }
    }
    
    /**
     *    Manages security realms.
	 *    @package SimpleTest
	 *    @subpackage WebTester
     */
    class SimpleAuthenticator {
        var $_realms;
        
        /**
         *    Starts with no realms set up.
         *    @access public
         */
        function SimpleAuthenticator() {
            $this->_realms = array();
        }
        
        /**
         *    Adds a new realm centered the current URL.
         *    Browsers vary wildly on their behaviour in this
         *    regard. Mozilla ignores the realm and presents
         *    only when challenged, wasting bandwidth. IE
         *    just carries on presenting until a new challenge
         *    occours. SimpleTest tries to follow the spirit of
         *    the original standards committee and treats the
         *    base URL as the root of a file tree shaped realm.
         *    @param SimpleUrl $url    Base of realm.
         *    @param string $type      Authentication type for this
         *                             realm. Only Basic authentication
         *                             is currently supported.
         *    @param string $realm     Name of realm.
         *    @access public
         */
        function addRealm($url, $type, $realm) {
            $this->_realms[$realm] = new SimpleRealm($type, $url);
        }
        
        /**
         *    Sets the current identity to be presented
         *    against that realm.
         *    @param string $realm       Name of realm.
         *    @param string $username    Username for realm.
         *    @param string $password    Password for realm.
         *    @access public
         */
        function setIdentityForRealm($realm, $username, $password) {
            if (isset($this->_realms[$realm])) {
                $this->_realms[$realm]->setIdentity($username, $password);
            }
        }
        
        /**
         *    Finds the name of the realm by comparing URLs.
         *    @param SimpleUrl $url        URL to test.
         *    @access private
         */
        function _findRealmFromUrl($url) {
            foreach ($this->_realms as $name => $realm) {
                if ($realm->isWithin($url)) {
                    return $name;
                }
            }
            return false;
        }
        
        /**
         *    Presents the appropriate headers for this location.
         *    @param SimpleHttpRequest $request  Request to modify.
         *    @param SimpleUrl $url              Base of realm.
         *    @access public
         */
        function addHeaders(&$request, $url) {
            if ($url->getUsername() && $url->getPassword()) {
                $username = $url->getUsername();
                $password = $url->getPassword();
            } elseif ($realm = $this->_findRealmFromUrl($url)) {
                $username = $this->_realms[$realm]->getUsername();
                $password = $this->_realms[$realm]->getPassword();
            } else {
                return;
            }
            $this->addBasicHeaders($request, $username, $password);
        }
        
        /**
         *    Presents the appropriate headers for this
         *    location for basic authentication.
         *    @param SimpleHttpRequest $request  Request to modify.
         *    @param string $username            Username for realm.
         *    @param string $password            Password for realm.
         *    @access public
         *    @static
         */
        function addBasicHeaders(&$request, $username, $password) {
            if ($username && $password) {
                $request->addHeaderLine(
                        'Authorization: Basic ' . base64_encode("$username:$password"));
            }
        }
    }
?>