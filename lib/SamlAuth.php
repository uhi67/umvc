<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Exception;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use SimpleXMLElement;

/**
 * Using this class needs `composer require "simplesamlphp/simplesamlphp:^2.0"` in your application
 * The dependency is not included in this library, since it's not mandatory for other parts.
 * @property-read array $attributes {@see AuthManager::getAttributes()}
 */
class SamlAuth extends AuthManager
{
    // Must be set to a secure random string
    public string $secret_salt;
    // A specific IdP can be configured to log in with. If not specified, the discovery service will be called.
    public ?string $idp = null;
    // URL of the discovery service to be used. If not specified, the internal discovery service will be used with predefined IdPs.
    public ?string $disco = null;
    // Refers to the proper SAML auth-source config element in the `config/saml/config/authsource.php` file
    public string $authSource;
    /** @var string $idAttribute -- attribute name used to identify the user */
    public string $idAttribute = 'eduPersonPrincipalName';

    /** @var Simple|null -- the SAML auth source */
    public ?Simple $auth = null;

    /** @var array|null $attr -- The cached attributes of the logged-in user or null */
    private ?array $_attributes = null;
    private static ?Template $_template = null;

    /**
     * Magic method for retrieving SAML attribute values
     *
     * @param string $name
     * @return mixed -- null: attribute is not found
     * @throws Exception
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name) ?? parent::__get($name) ;
    }

    /**
     * Initializes the object.
     *
     * @throws
     */
    public function init(): void
    {
        parent::init();
        if (!class_exists('\SimpleSAML\Auth\Simple')) {
            throw new Exception("SimpleSamlPHP cannot be found.");
        }
        $this->auth = new Simple($this->authSource);
    }

    /**
     * Manage already logged-in user
     *
     * @return UserInterface|null
     * @throws Exception
     */
    public function prepareUser(): ?UserInterface
    {
        // Check SAML login status
        $this->uid = App::$app->session->get($this->sessionUid) ?? null;
        $idAttributeValue = $this->isAuthenticated() ? $this->getAttribute($this->idAttribute, 0) : null;
        if(!$this->isAuthenticated() || $this->uid && $idAttributeValue != $this->uid) {
            if($this->isAuthenticated() && ENV_DEV) throw new Exception('SAML authentication: UID mismatch, close the browser.');
            // Auto logout
            $this->logout();
            return null;
        }

        if ($this->uid && $this->uid != static::INVALID_USER) {
            $user = $this->userModel::findUser($this->uid);
            if ($user) {
                return $this->_login($user);
            }
        }
        return null;
    }

    /**
     * Returns value of the configured id attribute of the user
     *
     * @return string|null
     * @throws Exception
     */
    public function getId(): ?string
    {
        if (!isset($this->attributes[$this->idAttribute])) {
            return null;
        }
        $value = $this->attributes[$this->idAttribute];
        if (is_array($value)) {
            $value = $value[0];
        }
        if ($this->idAttribute == 'eduPersonTargetedID') {
            $eptid = new SimpleXMLElement($value);
            $value = $eptid['NameQualifier'] . '!' . $eptid['SPNameQualifier'] . '!' . $eptid;
        }
        return $value;
    }

    /**
     * Returns true if the user is already authenticated by the external authenticator
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->auth->isAuthenticated();
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
    public function requireAuth(array $params = []): bool
    {
        $this->auth->requireAuth($params);
        return true;
    }

    /**
     * Logs out the user. Normally it will never return.
     * Returns false on error (if SimpleSaml is not initialized).
     * Does not return on the successful logout but redirects to the return URL or the current page
     *
     * Valid parameters:
     *
     *  - 'ReturnTo': The URL the user should be returned to after logout.
     *  - 'ReturnCallback': The function that should be called after logout.
     *  - 'ReturnStateParam': The parameter we should return the state in when redirecting.
     *  - 'ReturnStateStage': The stage the state array should be saved with.
     *
     * @param array|string|null $params -- return URL or parameter array
     * @return bool
     */
    public function logout(array|string $params = null): bool
    {
        parent::logout();
        if ($this->auth->isAuthenticated()) {
            $this->auth->logout($params);
        }
        return false;
    }

    /**
     * Returns idp entity identifier of the IdP identified the current user
     *
     * @return NULL|string
     */
    function getIdp(): ?string
    {
        $idp = null;
        if (method_exists($this->auth, 'getAuthData')) {
            $idp = $this->auth->getAuthData('saml:sp:IdP');
        } else {
            if (method_exists('SimpleSAML_Session', 'getInstance')) {
                // SimpleSamlPhp Older than 1.9 version
                /** @var Session $session */
                /** @noinspection PhpUndefinedMethodInspection */
                $session = Session::getInstance();
                /** @noinspection PhpUndefinedMethodInspection */
                $idp = $session->getIdP();
            }
        }
        return $idp;
    }

    /**
     * Manages the login process specific to this authenticator.
     *
     * Returns a valid user on successful login or null on failure.
     * Side effect: sets `$_SESSION['uid']` and `$this->uid`
     *
     * Valid parameters:
     * 'ErrorURL': A URL that should receive errors from the authentication.
     * 'KeepPost': If the current request is a POST request, keep the POST data until after the authentication.
     * 'ReturnTo': The URL the user should be returned to after authentication.
     * 'ReturnCallback': The function we should call after the user has finished authentication.
     *
     * @param array|string|null $params -- null to auto-detect, false to disable
     * @return UserInterface|null
     * @throws Exception
     */
    public function requireLogin(array|string $params = null): ?UserInterface
    {
        if (is_string($params)) {
            $params = ['ReturnTo' => $params];
        }
        if (!isset($params['ReturnTo'])) {
            $request = $_GET;
            unset($request['login']);
            $url = App::$app->createUrl($request);
            if ($url) {
                $params['ReturnTo'] = $url;
            }
        }
        $this->auth->requireAuth($params);

        // Phase 2
        if ($this->auth->isAuthenticated()) {
            if (!isset($this->attributes[$this->idAttribute])) {
                App::$app->session->set($this->sessionUid, $this->uid = static::INVALID_USER);
                throw new Exception(
                    App::l('umvc', 'Required attribute {$attribute} is missing', ['attribute' => $this->idAttribute])
                );
            } else {
                $uid = $this->getAttribute($this->idAttribute, 0);
                // Prevent the user save error to cause an endless loop of errors
                if (App::$app->session->get($this->sessionUid) == static::INVALID_USER) {
                    return null;
                }
                return $this->login($uid, $this->attributes);
            }
        }
        App::$app->session->set($this->sessionUid, $this->uid = null);
        return null;
    }

    /**
     * Translates a SAML attribute name
     *
     * @param string $attributeName
     * @param string $la -- ISO 639-1 language or ISO 3166-1 locale
     *
     * @return string -- translated name or original if translation is not found
     * @throws Exception
     */
    public static function translateAttributeName(string $attributeName, string $la): string
    {
        if (
            (!class_exists('\SimpleSAML\Configuration') && !class_exists('\SimpleSAML_Configuration')) ||
            (!class_exists('\SimpleSAML\XHTML\Template') && !class_exists('\SimpleSAML_XHTML_Template'))
        ) {
            return $attributeName;
        }
        if (static::$_template) {
            $t = static::$_template;
        } else {
            $globalConfig = Configuration::getInstance();
            $t = (static::$_template = new Template($globalConfig, 'status.php'));

            if (method_exists('\SimpleSAML\Locale\Language', 'setLanguage')) {
                $t->getTranslator()->getLanguage()->setLanguage(substr($la, 0, 2), false);
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $t->setLanguage(substr($la, 0, 2), false);
            }
        }
        if (method_exists('\SimpleSAML\Locale\Translate', 'getAttributeTranslation')) {
            $translated = $t->getTranslator()->getAttributeTranslation($attributeName);
        } else {
            $translated = $t->getAttributeTranslation($attributeName);
        }
        return $translated;
    }

    /**
     * Html-formats values of attribute, depending on attribute name
     *
     * @param string $attributeName
     * @param array|string $values -- multiple values can be passed in an array
     *
     * @return string
     * @throws Exception
     */
    public static function formatAttribute(string $attributeName, array|string $values): string
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        if (count($values) == 0) {
            return '';
        }
        if (count($values) == 1) {
            return static::formatValue($attributeName, $values[0]);
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        return Html::tag('ul', implode('', array_map(function ($v) use ($attributeName) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return Html::tag('li', static::formatValue($attributeName, $v));
        }, $values)));
    }

    /**
     * Html-formats a single value of the attribute, depending on the attribute name
     *
     * @param string $attributeName
     * @param string|array $value
     *
     * @return string
     * @throws Exception
     */
    public static function formatValue(string $attributeName, string|array $value): string
    {
        if ($attributeName == 'jpegPhoto') {
            /** @noinspection PhpUnhandledExceptionInspection */
            return Html::tag('img', '', ['src' => 'data:image/jpeg;base64,' . $value]);
        }
        if (is_array($value)) {
            return '[' . implode(', ', array_map(function ($k, $v) use ($attributeName) {
                    return (is_integer($k) ? '' : $k . ': ') . self::formatValue($attributeName . '.' . $k, $v);
                }, array_keys($value), array_values($value))) . ']';
        } else {
            return $value;
        }
    }

    public function getAttributes(): ?array
    {
        if (!$this->_attributes && $this->auth && $this->auth->isAuthenticated()) {
            $this->_attributes = $this->auth->getAttributes();
        }
        return $this->_attributes;
    }

    /**
     * Returns the SAML attribute value(s) by its name and optional index.
     *
     * @param string $idAttribute
     * @param int|null $index -- index of the attribute value, or null to return all values as an array
     * @return string|null|array -- single or multiple values. Null if attribute is not found.
     */
    public function getAttribute(string $idAttribute, ?int $index=null): null|string|array
    {
        if(!$this->getAttributes()) return null;
        return $index===null ? ($this->_attributes[$idAttribute] ?? null) : ($this->_attributes[$idAttribute][$index] ?? null);
    }
}
