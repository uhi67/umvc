<?php

namespace uhi67\umvc\models;

use uhi67\umvc\Model;

/**
 * @property string $name
 * @property int $applied
 */
class Migration extends Model {

    /**
     * Must return an array of primary key fields
     *
     * @return array of field names
     */
    public static function primaryKey() {
        return ['name'];
    }
    /**
     * Must return the autoincrement fields.
     * Returns empty array if model has no autoincrement fields.
     *
     * @return array
     */
    public static function autoIncrement() {
        return [];
    }
}
