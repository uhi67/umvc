<?php

namespace uhi67\umvc;

use Exception;

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
 * @property-read array $attributes
 */
abstract class AuthManager extends Component
{
    // If uid holds this value, the login partially failed
    const INVALID_USER = -1;

    /** @var string|null $uid -- The uid of the logged-in user or null (EPPN) */
    public $uid;
    /** @var string|UserInterface $userModel -- the model identifies a user */
    public $userModel;
    /** @var bool $autoUrl -- true to detect and perform ?login and ?logout in the REQUEST URL automatically */
    public $autoUrl = true;

    /**
     * Initializes the auth module.
     *
     * @return void
     * @throws Exception
     */
    public function init()
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
    public function prepare()
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

    /**
     * Manage already logged-in user
     *
     * @return UserInterface|null
     * @throws Exception
     */
    public function prepareUser()
    {
        $this->uid = $_SESSION['uid'] ?? null;
        if ($this->uid && $this->uid != static::INVALID_USER) {
            $user = $this->userModel::findUser($this->uid);
            if ($user) {
                return $this->login($user);
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
    public function actionLogin($params = null)
    {
        if ($params === null) {
            $params = [];
        }
        if(!array_key_exists('ReturnTo', $params)) {
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
     * Makes the user logged in within the application.
     * Called automatically
     *
     * @param UserInterface|string $user -- user ID or User model
     * @return UserInterface|null -- the user object logged in on success or null on failure
     * @throws Exception
     */
    public function login($user)
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
        $this->parent->user = $userModel;
        if (!$userModel) {
            return null;
        }
        $_SESSION['uid'] = $userModel->getUserId();
        return $userModel;
    }

    /**
     * Must log out the user by the external authenticator.
     * May never return.
     *
     * Return false on error
     *
     * @param array|string|null $params -- used only in descendants
     * @return bool
     */
    public function logout($params = null): bool
    {
        $this->parent->user = null;
        $_SESSION['uid'] = null;
        $_SESSION = [];
        return true;
    }

    /**
     * Must return true if the user is already authenticated by the external authenticator
     *
     * @return bool
     */
    abstract public function isAuthenticated();

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
    abstract public function requireLogin($params = null);

    abstract public function getAttributes();
}
