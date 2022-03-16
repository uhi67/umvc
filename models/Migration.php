<?php

namespace uhi67\umvc\models;

/**
 * @property string $name
 * @property int $applied
 */
class Migration extends \uhi67\umvc\Model {

    /**
     * Must return an array of primary key fields
     *
     * @return array of fieldnames
     */
    public static function primaryKey() {
        return ['name'];
    }
    /**
     * Must return the autoincrement fields.
     * Returns empty array if modell has no autoincrement fields.
     *
     * @return array
     */
    public static function autoIncrement() {
        return [];
    }
}
