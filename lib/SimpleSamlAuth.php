<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace educalliance\umvc;

use Exception;
use SimpleSAML\Auth\Simple;
use SimpleXMLElement;

/**
 * Simplified SAML Auth manager without an Application user. Based only on the SAML session.
 *
 * Using this class needs `composer require "simplesamlphp/simplesamlphp:^2.0"` in your application
 * The dependency is not included in this library, since it's not mandatory for other parts.
 */
class SimpleSamlAuth extends AuthManager
{
    public ?string $idp = null;
    // URL of the discovery service to be used. If not specified, the internal discovery service will be used with predefined IdPs.
    public ?string $disco = null;
    // Refers to the proper SAML auth-source config element in the `config/saml/config/authsource.php` file
    public string $authSource;
    /** @var Simple|null -- the SAML auth source */
    public ?Simple $auth = null;
    /** @var array|null $attr -- The cached attributes of the logged-in user or null */
    private ?array $_attributes = null;
    public string $idAttribute = 'user';

    /**
     * Initializes the object.
     *
     * @throws
     */
    public function init(): void
    {
        $this->userModel = null;
        if (!class_exists('\SimpleSAML\Auth\Simple') && !class_exists('\SimpleSAML_Auth_Simple')) {
            throw new Exception("SimpleSamlAuth: The reuired SimpleSamlPHP is not installed. Run `composer require simplesamlphp/simplesamlphp:^2.0` to install it.");
        }
        $this->auth = new Simple($this->authSource);
    }

    public function prepare(): void
    {
    }

    public function __get($name): mixed {
        return $this->getAttribute($name) ?? parent::__get($name);
    }

    /**
     * Magic method for retrieving SAML attribute values
     *
     * @param string $attributeName
     * @param int|null $index -- which one from the value array or (null=) all of them. Unused if the attribute value is not an array.
     *
     * @return string|array|null -- null: attribute is not found
     */
    public function getAttribute(string $attributeName, int $index = null): array|string|null
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        if ($value = $this->attributes[$attributeName]??null) {
            return is_array($value) ? (($index !== null) ? $value[$index] : $value) : $value;
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
        if ($this->auth->isAuthenticated()) {
            $this->auth->logout($params);
        }
        return false;
    }

    /**
     * @param array|string|null $params
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
        return null;
    }

    public function getAttributes()
    {
        if (!$this->_attributes && $this->auth && $this->auth->isAuthenticated()) {
            $this->_attributes = $this->auth->getAttributes();
        }
        return $this->_attributes;
    }
}
