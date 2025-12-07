<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

use Exception;
use Throwable;

/**
 * Component to handle user login and logout.
 *
 * Configure your derived class in the main config like this:
 *
 * ```
 * 'components' => [
 *     'auth' => [
 *          DerivedAuth::class,
 *          'userModel' => User::class,
 *          ...
 *      ],
 *      ...
 * ],
 * ```
 * @property-read array $attributes -- cached value of the external attributes
 */
abstract class AuthManager extends Component
{
    // If uid holds this value, the login partially failed
    const INVALID_USER = -1;

    /** @var string|null $uid -- The uid of the logged-in user or null (EPPN) */
    public ?string $uid = null;
    /** @var string|UserInterface $userModel -- the model identifies a user */
    public string|UserInterface $userModel;
    /** @var bool $autoUrl -- true to detect and perform ?login and ?logout in the REQUEST URL automatically */
    public bool $autoUrl = true;
    /** @var string $sessionUid -- the session key to store the uid */
    public string $sessionUid = 'uid';

    /**
     * Initializes the auth module.
     *
     * @return void
     * @throws Exception
     */
    public function init(): void
    {
        if (!$this->userModel || !is_a($this->userModel, UserInterface::class, true)) {
            throw new Exception(
                "userModel must be a class implementing Model and UserInterface. Given '$this->userModel'"
            );
        }
    }

    /**
     * Initializes the already logged-in user.
     * Manages login and logout requests.
     *
     * @return void
     * @throws Exception
     */
    public function prepare(): void
    {
        if ($this->autoUrl) {
            // Manage logout request
            if (array_key_exists('logout', $_REQUEST)) {
                $this->logout();
            }

            // Manage login request
            if (array_key_exists('login', $_REQUEST)) {
                $this->actionLogin();
            }
        }

        $this->prepareUser();
    }

    static public function requiredComponents(): array
    {
        return ['db', 'session'];
    }

    /**
     * Manage already logged-in user
     *
     * @return UserInterface|null
     * @throws Exception
     */
    public function prepareUser(): ?UserInterface
    {
        $this->uid = App::$app->session->get($this->sessionUid) ?? null;
        if ($this->uid && $this->uid != static::INVALID_USER) {
            $user = $this->userModel::findUser($this->uid);
            if ($user) {
                return $this->_login($user);
            }
        }
        return null;
    }

    /**
     * Login action with user-defined return URL
     *
     * Usage example (in a Controller):
     *
     * ```
     * public function actionLogin() {
     *     $this->app->auth->actionLogin('/');
     * }
     * ```
     *
     * Valid parameters:
     * 'ErrorURL': A URL that should receive errors from the authentication.
     * 'KeepPost': If the current request is a POST request, keep the POST data until after the authentication.
     * 'ReturnTo': The URL the user should be returned to after authentication.
     * 'ReturnCallback': The function we should call after the user has finished authentication.
     *
     * @param array|string|null $params -- params or return URL after login or null if none
     * @return UserInterface|null
     * @throws Exception
     */
    public function actionLogin(array|string $params = null): ?UserInterface
    {
        if ($params === null) {
            $params = [];
        }
        if (!array_key_exists('ReturnTo', $params)) {
            if (isset($_REQUEST['ReturnTo'])) {
                $params['ReturnTo'] = $_REQUEST['ReturnTo'];
            } else {
                $request = $_GET;
                unset($request['login']);
                $params['ReturnTo'] = $this->parent->createUrl($request);
            }
        }
        $this->parent->user = $this->requireLogin($params);
        return $this->prepareUser();
    }

    /**
     * @param UserInterface|string $uid -- A user object or a login id
     * @param array $attributes -- login attributes
     * @param bool $canCreate
     * @return ?UserInterface
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    public function login(UserInterface|string $uid, array $attributes = [], bool $canCreate = true): ?UserInterface
    {
        if ($uid instanceof UserInterface) {
            return $this->_login($uid);
        }
        $user = $this->userModel::findUser($uid);
        if ($user) {
            if (App::$app->session->get($this->sessionUid) != $user->getUserId()) {
                if (!$user->updateUser($attributes)) {
                    throw new Exception("User record cannot be saved ($uid)");
                }
            }
            return $this->_login($user);
        } else {
            try {
                $user = $this->userModel::createUser($uid, $attributes);
                if (!$user) {
                    throw new Exception("User not created");
                }
                return $this->_login($user);
            } catch (Throwable $e) {
                // -1 indicates that SAML login is successful, but the application login failed. Prevents endless loops.
                $this->uid = static::INVALID_USER;
                App::$app->session->set($this->sessionUid, $this->uid);
                throw new Exception(
                    "User record cannot be created ($uid)", HTTP::HTTP_INTERNAL_SERVER_ERROR, $e
                );
            }
        }
    }

    /**
     * Makes the user logged in within the application.
     * Called automatically
     *
     * @param string|UserInterface $user -- user ID or User model
     * @return UserInterface|null -- the user object logged in on success or null on failure
     * @throws Exception
     */
    protected function _login(UserInterface|string $user): ?UserInterface
    {
        if (is_string($user)) {
            if (!$this->userModel || !is_a($this->userModel, UserInterface::class, true)) {
                throw new Exception('UserModel must be a class implementing UserInterface');
            }
            $userModel = $this->userModel::findUser($user);
            if (!$userModel) {
                return null;
            }
        } else {
            if (!is_a($user, UserInterface::class)) {
                throw new Exception('User object must implement UserInterface');
            }
            $userModel = $user;
        }
        if (!$this->parent instanceof App) {
            throw new Exception('AuthManager must be a component of the App');
        }
        $this->uid = $userModel->getUserId();
        return $this->parent->login($userModel);
    }

    /**
     * Must log out the user by the external authenticator.
     * May never return.
     *
     * This implementation can be used to log out the user internally from the application.
     *
     * Return false on error
     *
     * @param array|string|null $params -- used only in descendants
     * @return bool
     */
    public function logout(array|string $params = null): bool
    {
        $this->parent->user = null;
        App::$app->session->set($this->sessionUid, null);
        App::$app->session->empty();
        return true;
    }

    /**
     * Must return true if the user is already authenticated by the external authenticator
     *
     * @return bool
     */
    abstract public function isAuthenticated(): bool;

    /**
     * Must manage the login process
     *
     * Valid parameters:
     * 'ErrorURL': A URL that should receive errors from the authentication.
     * 'KeepPost': If the current request is a POST request, keep the POST data until after the authentication.
     * 'ReturnTo': The URL the user should be returned to after authentication.
     * 'ReturnCallback': The function we should call after the user has finished authentication.
     *
     * @param array|string|null $params -- return URL or parameter array
     * @return UserInterface|null
     */
    abstract public function requireLogin(array|string $params = null): ?UserInterface;

    abstract public function getAttributes();
}
