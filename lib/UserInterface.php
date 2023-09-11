<?php

namespace uhi67\umvc;

/**
 * A data model can identify a logged-in user
 * The user is identified by uid (arbitrary data-type)
 *
 * Sample usage:
 * ```
 * class User extends Model implements UserInterface {
 *     ...
 * }
 * ```
 * @property-read string $userId
 */
interface UserInterface {
    /**
     * Must update the User object using the attributes provided by login process
     *
     * @param array $attributes
     * @return mixed|false -- uid on success, false on failure
     */
    public function updateUser($attributes);

    /**
     * Must return the user object associated to the given uid (e.g. a model instance)
     *
     * @param mixed $uid
     * @return UserInterface
     */
    public static function findUser($uid);

    /**
     * Must create (and save) a new user object associated to the given uid and using the attributes provided
     *
     * @param mixed $uid
     * @param array $attributes
     * @return UserInterface
     */
    public static function createUser($uid, $attributes);

    /**
     * Must return the uid of the user object (used in session)
     *
     * @return mixed
     */
    public function getUserId();
}
