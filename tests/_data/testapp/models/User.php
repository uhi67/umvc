<?php
namespace app\models;

use uhi67\umvc\UserInterface;
use ReflectionException;
use uhi67\umvc\App;
use uhi67\umvc\AppHelper;
use uhi67\umvc\Model;
use Exception;

/**
 * @property int $id                int(10) primary key, autoincrement
 * @property string $uid            varchar(128) unique
 * @property string $name    varchar(255)
 * @property string $created_at     timestamp
 */
class User extends Model implements UserInterface {
    /**
     * Must return the name of the table.
     * Defined explicitly, the default rule is not working on derived classes :-(
     *
     * @return string
     */
    public static function tableName() {
        return 'user';
    }

    /**
     * Must return the validation rules by field name.
     *
     * A rule may be a simple rule name, or [rule-name, args].
     * For more details, see {@see Model::rules()}
     *
     * @return array[]
     */
    public static function rules() {
        return [
            'uid' => ['unique', ['pattern', '/^[\w.-]+@[\w.-]+$/']],
        ];
    }

    public static function attributeLabels() {
        return [
            'id' => 'ID',
            'uid' => 'UID (EPPN)',
            'name' => 'Name',
            'created_at' => 'Created at',
        ];
    }

    /**
     * Return the UID (EPPN) scope of the user
     *
     * @return string
     * @throws Exception
     */
    public function getScope() {
        return AppHelper::substring_after($this->uid, '@');
    }

    /**
     * Return the user object associated to the given uid (e.g. a model instance)
     *
     * @throws Exception
     */
    public static function findUser($uid)
    {
        return static::getOne(['uid'=>$uid]);
    }

    /**
     * Create (and save) a new user object associated to the given uid and using the attributes provided
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function createUser($uid, $attributes)
    {
        $data = [
            'uid' => $uid,
            'name' => isset($attributes['displayName']) ? $attributes['displayName'][0] : $uid,
        ];
        $user = new User($data);
        if(!$user->save()) {
            App::log('error', "Insert of user '$uid' failed during login. ".$user->connection->lastError);
            // -1 indicates that SAML login is successful, but the application login failed. Prevents endless loop.
            App::addFlash("Login failed: user record cannot be created for ".$uid, 'error');
            return null;
        }
        return $user;
    }

    /**
     * Return the uid of the user object (used in session)
     *
     * @return mixed
     */
    public function getUserId()
    {
        return $this->uid;
    }

	public function updateUser($attributes) {
		return $this->uid;
	}
}
