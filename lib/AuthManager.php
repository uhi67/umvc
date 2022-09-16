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
 */
abstract class AuthManager extends Component {
    // If uid holds this value, the login partially failed
    const INVALID_USER = -1;

    /** @var string|null $uid -- The uid of the logged-in user or null (EPPN) */
    public $uid;
    /** @var string|UserInterface $userModel -- the model identifies a user */
    public $userModel;

    /**
     * Initializes the auth module.
     *
     * @return void
     * @throws Exception
     */
    public function init() {
        if(!$this->userModel || !is_a($this->userModel, UserInterface::class, true))
			throw new Exception("userModel must be a class implementing UserInterface. Given '$this->userModel'");
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
        // Manage logout request
        if(array_key_exists('logout', $_REQUEST)) {
            $this->logout();
        }

        // Manage login request
        if(array_key_exists('login', $_REQUEST)) {
            if(isset($_REQUEST['ReturnTo'])) {
                $returnTo = $_REQUEST['ReturnTo'];
            }
            else {
                $request = $_GET;
                unset($request['login']);
                $returnTo = $this->parent->createUrl($request);
            }
            $this->parent->user = $this->requireLogin($returnTo);
        }

        // Manage already logged-in user
        $this->uid = $_SESSION['uid'] ?? null;
        if($this->uid && $this->uid!=static::INVALID_USER) {
            $user = $this->userModel::findUser($this->uid);
            if($user) $this->login($user);
        }
    }

    /**
     * Makes the user logged in within the application.
     * Called automatically
     *
     * @param UserInterface|string $user -- user ID or User model
     * @return UserInterface|null -- the user object logged in on success or null on failure
     * @throws Exception
     */
    public function login($user) {
        if(is_string($user)) {
            if(!$this->userModel || !is_a($this->userModel, UserInterface::class, true)) throw new Exception('UserModel must be a class implementing UserInterface');
            $user = $this->userModel::findUser($user);
            if(!$user) return null;
        } else {
            if(!is_a($user, UserInterface::class)) throw new Exception('User object must implement UserInterface');
        }
        if(!$this->parent instanceof App) throw new Exception('AuthManager must be a component of the App');
        $this->parent->user = $user;
        if(!$user) return null;
        $_SESSION['uid'] = $user->getUserId();
        return $user;
    }

    /**
     * Must log out the user by the external authenticator.
     * May never return.
     *
     * Return false on error
     *
     * @return bool
     */
    public function logout() {
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
     * @param string|null $returnTo
     * @return UserInterface|null
     */
    abstract public function requireLogin($returnTo=null);
}
