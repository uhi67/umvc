<?php /** @noinspection PhpIllegalPsrClassPathInspection */

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
 *
 * @property-read mixed $id
 */
interface UserInterface {
    /**
     * Must update the User object using the attributes provided by login process
     *
     * @param array $attributes
     * @return mixed|false -- uid on success, false on failure
     */
    public function updateUser(array $attributes): mixed;

    /**
     * Must return the user object associated to the given uid (e.g. a model instance)
     *
     * @param mixed $uid
     * @return UserInterface|null
     */
    public static function findUser(mixed $uid): ?UserInterface;

    /**
     * Must create (and save) a new user object associated to the given uid and using the attributes provided
     *
     * @param mixed $uid
     * @param array $attributes
     * @return UserInterface|null -- null if user cannot be created
     */
    public static function createUser(mixed $uid, array $attributes): UserInterface|null;

    /**
     * Must return the uid of the user object (used in session)
     *
     * @return mixed
     */
    public function getUserId(): mixed;
}
