<?php

namespace uhi67\umvc;

use Exception;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Session;
use SimpleXMLElement;
use Throwable;

/**
 * Using this class needs `composer require "simplesamlphp/simplesamlphp:^1.19.2"`
 */
class SamlAuth extends AuthManager {
    // Must be set to a secure password
    public $admin_password;
    // Must be set to a secure random string
    public $secret_salt;
    // A specific IdP can be configured to login with. If not specified, the discovery service will be called.
    public $idp;
    // URL of the discovery service to be used. If not specified, the internal discovery service will be used with predefined IdPs.
    public $disco;
    // Refers to the proper SAML auth-source config element in the `config/saml/config/authsource.php` file
    public $authSource;
    public $idAttribute;

    /** @var Simple -- the SAML auth source */
    public $auth;
    /** @var array|null $attr -- The attributes of the logged-in user or null */
    public $attributes;

    /**
     * Magic method for retrieving SAML attribute values
     *
     * @param string $attributeName
     * @param int|null $index -- which one from the value array or (null=) all of them.
     *
     * @return string|array|null -- null: attribute is not found
     */
    public function get($attributeName, $index=null) {
        if(array_key_exists($attributeName, $attributes = $this->attributes)) {
            $value = $attributes[$attributeName];
            return ($index!==null) ? $value[$index] : $value;
        }
        return null;
    }

    /**
     * Initializes the object.
     *
     * @throws
     */
    public function init() {
        parent::init();
        if(!class_exists('\SimpleSAML\Auth\Simple') && !class_exists('\SimpleSAML_Auth_Simple')) {
            throw new Exception("SimpleSamlPHP cannot be found.");
        }
        if(!class_exists('\SimpleSAML\Auth\Simple')) {
            /** @noinspection PhpUndefinedClassInspection */
            $this->auth = new SimpleSAML_Auth_Simple($this->authSource);
        }
        else {
            $this->auth = new Simple($this->authSource);
        }
    }

    /**
     * Returns value of configured id attribute of the user
     * @return string
     * @throws Exception
     */
    public function getId() {
        if(!isset($this->attributes[$this->idAttribute])) return null;
        $value = $this->attributes[$this->idAttribute];
        if(is_array($value)) $value = $value[0];
        if($this->idAttribute == 'eduPersonTargetedID') {
            $eptid = new SimpleXMLElement($value);
            $value = $eptid['NameQualifier'] . '!' . $eptid['SPNameQualifier'] . '!' . (string)$eptid;
        }
        return $value;
    }

    /**
     * Returns true if the user is already authenticated by the external authenticator
     *
     * @return bool
     */
    public function isAuthenticated() {
        if ($this->auth != null) {
            return $this->auth->isAuthenticated();
        }
        return false;
    }

    /**
     * Initiate login
     *
     * Example params:
     *
     * - 'saml:idp' => url of idp to use (if authsource does not specify it)
     *
     * @param array $params
     * @return bool
     */
    public function requireAuth($params=[]) {
        if ($this->auth != null) {
            $this->auth->requireAuth($params);
            return true;
        }
        return false;
    }

    /**
     * Logs out the user. Normally it will never return.
     * Returns false on error (if SimpleSaml is not initialized)
     *
     * @return bool
     */
    public function logout() {
        parent::logout();
        if ($this->auth != null && $this->auth->isAuthenticated()) {
            $this->auth->logout();
        }
        return false;
    }

    /**
     * Returns idp entity identifier of the IdP identified the current user
     *
     * @return NULL|string
     */
    function getIdp() {
        $idp = null;
        if(method_exists($this->auth, 'getAuthData')) {
            $idp = $this->auth->getAuthData('saml:sp:IdP');
        }
        else if(method_exists('SimpleSAML_Session', 'getInstance')) {
            // SimpleSamlPhp Older than 1.9 version
            /** @var Session $session */
            $session = Session::getInstance();
            $idp = $session->getIdP();
        }
        return $idp;
    }

    /**
     * Manages the login process specific to this authenticator.
     *
     * Returns a valid user on successful login or null on failure.
     * Side-effect: sets `$_SESSION['uid']` and `$this->uid`
     *
     * @param string|null $returnTo
     * @return UserInterface|null
     * @throws Exception
     */
    public function requireLogin($returnTo=null) {
        $params = [];
        if($returnTo) {
            $params['ReturnTo'] = $returnTo;
        }
        else {
            $request = $_GET;
            unset($request['login']);
            $returnTo = App::$app->createUrl($request);
            if($returnTo) $params['ReturnTo'] = $returnTo;
        }
        $this->auth->requireAuth($params);

        // Phase 2
        if($this->auth->isAuthenticated()) {
            $this->attributes = $this->auth->getAttributes();
            if(!isset($this->attributes[$this->idAttribute])) {
                App::addFlash("Login failed: '$this->idAttribute' attribute is missing", 'failure');
                $_SESSION['uid'] = $this->uid = static::INVALID_USER;
                return null;
            }
            else {
                $uid = $this->attributes[$this->idAttribute][0];
                // Prevent the user save error to cause an endless loop of errors
                if(isset($_SESSION['uid']) && $_SESSION['uid']==static::INVALID_USER) return null;
                /** @var UserInterface $user */
                $user = $this->userModel::findUser($uid);
                if($user) {
                    $user->updateUser($this->attributes);
                    $this->login($user);
                    return $user;
                }
                else {
                    try {
                        $user = $this->userModel::createUser($uid, $this->attributes);
                        if(!$user) {
                            throw new Exception('User not created');
                        }
                        $_SESSION['uid'] = $this->uid = $user->getUserId();
                        return $user;
                    }
                    catch(Throwable $e) {
                        // -1 indicates that SAML login is successful, but the application login failed. Prevents endless loop.
                        $_SESSION['uid'] = $this->uid = static::INVALID_USER;
                        throw new Exception("Login failed: user record cannot be created", HTTP::HTTP_INTERNAL_SERVER_ERROR, $e);
                    }
                }
            }
        }
        $_SESSION['uid'] = $this->uid = null;
        return null;
    }
}
