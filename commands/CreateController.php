<?php

namespace educalliance\umvc\commands;

use Exception;
use educalliance\umvc\App;
use educalliance\umvc\AppHelper;
use educalliance\umvc\ArrayHelper;
use educalliance\umvc\Command;

class CreateController extends Command
{
    /**
     * Creates a PHP file of a new model class based on the given existing database table.
     * Model name will be the camelized version of the table-name.
     *
     * CLI parameters:
     * - o=1: overwrite the existing model
     * - <table-name>: the name of the table to create the model from
     *
     * @return int
     * @throws Exception
     * @example create model o=1 tableName
     */
    public function actionModel(): int
    {
        $o = ArrayHelper::fetchValue($this->query, 'o');
        $tableName = array_shift($this->query);
        if (!$tableName) {
            echo "Missing table name\n";
            return 1;
        }
        $modelName = AppHelper::camelize($tableName);
        $nameSpace = 'app\models';
        $fileName = dirname(__DIR__, 4) . '/models/' . $modelName . '.php';
        if (class_exists($nameSpace . '\\' . $modelName) && !$o) {
            echo "Model $modelName already exists, skipping\n";
            return 2;
        }
        $db = $this->app->connection;
        $metadata = $db->tableMetadata($tableName);
        $foreign_keys = $db->getForeignKeys($tableName);
        $referrer_keys = $db->getReferrerKeys($tableName);
        $genPath = dirname(__DIR__) . '/def/gen';
        $columns = [];
        $references = [];
        $referrers = [];

        foreach ($metadata as $column => $data) {
            $columns[$column] = array_merge($data, [
                'php-name' => lcfirst(AppHelper::camelize($column)),
                'php-type' => $db->mapType($data['type']),
                'label' => ucwords(str_replace('_', ' ', $column)),
            ]);
        }
        if ($foreign_keys) {
            foreach ($foreign_keys as $constraint => $data) {
                $column_name = $data['column_name'];
                $references[$constraint] = array_merge($data, [
                    'name' => str_ends_with($column_name, '_id') ? substr(
                        $column_name,
                        0,
                        -3
                    ) : $column_name . '1',
                    'foreign_model' => AppHelper::camelize($data['foreign_table']),
                    'foreign_field' => lcfirst(AppHelper::camelize($data['foreign_column'])),
                ]);
            }
        }

        // referrers?
        if ($referrer_keys) {
            foreach ($referrer_keys as $constraint => $data) {
                $referrers[$constraint] = array_merge($data, [
                    'name' => lcfirst(AppHelper::camelize($data['remote_table'])),
                    'remote_model' => AppHelper::camelize($data['remote_table']),
                    'remote_field' => lcfirst(AppHelper::camelize($data['remote_column'])),
                ]);
            }
        }

        $result = $this->app->renderPhpFile($genPath. '/model.php', compact(
            'modelName',
            'tableName',
            'nameSpace',
            'columns',
            'references',
            'referrers'
        ));
        file_put_contents($fileName, $result);
        echo "Model $modelName created successfully in file '$fileName'\n";
        return App::EXIT_STATUS_OK;
    }
}
