<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Exception;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use SimpleXMLElement;
use Throwable;

/**
 * Using this class needs `composer require "simplesamlphp/simplesamlphp:^2.0"` in your application
 * The dependency is not included in this library, since it's not mandatory for other parts.
 */
class SamlAuth extends AuthManager
{
    // Must be set to a secure random string
    public $secret_salt;
    // A specific IdP can be configured to log in with. If not specified, the discovery service will be called.
    public $idp;
    // URL of the discovery service to be used. If not specified, the internal discovery service will be used with predefined IdPs.
    public $disco;
    // Refers to the proper SAML auth-source config element in the `config/saml/config/authsource.php` file
    public $authSource;
    /** @var string $idAttribute -- attribute name used to identify the user */
    public $idAttribute = 'eduPersonPrincipalName';

    /** @var Simple -- the SAML auth source */
    public $auth;

    /** @var array|null $attr -- The cached attributes of the logged-in user or null */
    private $_attributes = null;
    private static $_template;

    /**
     * Magic method for retrieving SAML attribute values
     *
     * @param string $attributeName
     * @param int|null $index -- which one from the value array or (null=) all of them.
     *
     * @return string|array|null -- null: attribute is not found
     */
    public function get(string $attributeName, int $index = null): array|string|null
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        if (array_key_exists($attributeName, $attributes = $this->auth->getAttributes())) {
            $value = $attributes[$attributeName];
            return ($index !== null) ? $value[$index] : $value;
        }
        return null;
    }

    /**
     * Initializes the object.
     *
     * @throws
     */
    public function init(): void
    {
        parent::init();
        if (!class_exists('\SimpleSAML\Auth\Simple') && !class_exists('\SimpleSAML_Auth_Simple')) {
            throw new Exception("SimpleSamlPHP cannot be found.");
        }
        if (!class_exists('\SimpleSAML\Auth\Simple')) {
            /** @noinspection PhpUndefinedClassInspection */
            $this->auth = new SimpleSAML_Auth_Simple($this->authSource);
        } else {
            $this->auth = new Simple($this->authSource);
        }
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
    public function requireAuth(array $params = []): bool
    {
        if ($this->auth != null) {
            $this->auth->requireAuth($params);
            return true;
        }
        return false;
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
        if ($this->auth != null && $this->auth->isAuthenticated()) {
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
                $_SESSION['uid'] = $this->uid = static::INVALID_USER;
                throw new Exception(
                    App::l('umvc', 'Required attribute {$attribute} is missing', ['attribute' => $this->idAttribute])
                );
            } else {
                $uid = $this->attributes[$this->idAttribute][0];
                // Prevent the user save error to cause an endless loop of errors
                if (isset($_SESSION['uid']) && $_SESSION['uid'] == static::INVALID_USER) {
                    return null;
                }
                return $this->login($uid, $this->attributes);
            }
        }
        $_SESSION['uid'] = $this->uid = null;
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
            $t = (static::$_template = new Template($globalConfig, 'status.php', 'attributes'));

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
     * @noinspection PhpDocMissingThrowsInspection
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
     * @param string $value
     *
     * @return string
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function formatValue(string $attributeName, string $value): string
    {
        if ($attributeName == 'jpegPhoto') {
            /** @noinspection PhpUnhandledExceptionInspection */
            return Html::tag('img', '', ['src' => 'data:image/jpeg;base64,' . $value]);
        }
        if (is_array($value)) {
            return '[' . implode(', ', array_map(function ($k, $v) use ($attributeName) {
                    return (is_integer($k) ? '' : $k . ': ') . self::formatValue($attributeName . '.' . $k, $v);
                }, array_keys($value), array_values($value))) . ']';
        }
        return $value;
    }

    public function getAttributes(): ?array
    {
        if (!$this->_attributes && $this->auth && $this->auth->isAuthenticated()) {
            $this->_attributes = $this->auth->getAttributes();
        }
        return $this->_attributes;
    }
}
