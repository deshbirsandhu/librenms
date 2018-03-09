<?php
/**
 * Schema.php
 *
 * Class for querying the schema
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\DB;

use LibreNMS\Config;
use Symfony\Component\Yaml\Yaml;

class Schema
{
    private static $relationship_blacklist = [
        'devices_perms',
        'bill_perms',
        'ports_perms',
    ];

    private static $cache_file = 'misc/db_schema.yaml';

    private $schema;

    private function __construct($schema)
    {
        $this->schema = $schema;
    }

    /**
     * Get the primary key column(s) for a table
     *
     * @param string $table
     * @return string|array if a single column just the name is returned, otherwise an array of column names
     */
    public function getPrimaryKey($table)
    {
        $schema = $this->getSchema();

        $columns = $schema[$table]['Indexes']['PRIMARY']['Columns'];

        if (count($columns) == 1) {
            return reset($columns);
        }

        return $columns;
    }

    /**
     * Load the schema definition array and return it
     * Cached in misc/db_schema.json
     *
     * @param bool $cache
     * @return Schema
     */
    public static function load($cache = true)
    {
        if ($cache) {
            $schema = Yaml::parse(file_get_contents(self::getCacheFileName()));
        } else {
            $schema = self::loadFromDb();
        }

        return new static($schema);
    }

    public function toArray()
    {
        return $this->schema;
    }

    public function save()
    {
        $yaml = Yaml::dump($this->schema, 3, 2);

        return file_put_contents(self::getCacheFileName(), $yaml);
    }

    public static function getCacheFileName()
    {
        return Config::get('install_dir') . '/' . self::$cache_file;
    }

    /**
     * Search for the relationship path from $start to $target
     *
     * If this returns true, they are directly related.
     * If it returns false, no relation could be found.
     *
     * @param string $start
     * @param string $target
     * @return array|bool tables to reach the target table through relationships
     */
    public function findRelationshipPath($start, $target = 'devices')
    {
        d_echo("Searching for target: $target, starting with $start\n");

        if ($start == $target) {
            // um, yeah, we found it...
            return [$target];
        }

        $path = $this->findPathRecursive([$start], $target);

        if ($path === false) {
            return $path;
        }

        if (count($path) == 1) {
            return true;
        }

        return $path;
    }

    /**
     * Get an array of tables with directly related tables.
     * Relations are guessed by column names
     *
     *
     * @return array
     */
    public function getTableRelationships()
    {
        if (!isset($this->relationships)) {
            $schema = $this->getSchema();

            $relations = array_column(array_map(function ($table, $data) {
                $columns = array_column($data['Columns'], 'Field');

                $related = array_filter(array_map(function ($column) use ($table) {
                    $guess = $this->getTableFromKey($column);
                    if ($guess != $table) {
                        return $guess;
                    }

                    return null;
                }, $columns));

                return [$table, $related];
            }, array_keys($schema), $schema), 1, 0);

            // filter out blacklisted tables
            $this->relationships = array_diff_key($relations, array_flip(self::$relationship_blacklist));
        }

        return $this->relationships;
    }

    /**
     * Try to figure out the table based on the given key name
     *
     * @param string $key
     * @return string|null
     */
    public function getTableFromKey($key)
    {
        if (ends_with($key, '_id')) {
            // hardcoded
            if ($key == 'app_id') {
                return 'applications';
            }

            // try to guess assuming key_id = keys table
            $guessed_table = substr($key, 0, -3);

            if (!ends_with($guessed_table, 's')) {
                if (ends_with($guessed_table, 'x')) {
                    $guessed_table .= 'es';
                } else {
                    $guessed_table .= 's';
                }
            }

            if (array_key_exists($guessed_table, $this->getSchema())) {
                return $guessed_table;
            }
        }

        return null;
    }

    /**
     * Test if table contains the given column
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function columnExists($table, $column)
    {
        $schema = $this->getSchema();

        $fields = array_column($schema[$table]['Columns'], 'Field');

        return in_array($column, $fields);
    }

    /**
     * Check if the database schema is up to date.
     *
     * @return bool
     */
    public static function isCurrent()
    {
        $current = self::getDbVersion();

        $schemas = self::listSchemaFiles();
        end($schemas);
        $latest = key($schemas);

        return $current >= $latest;
    }

    /**
     * Get the current database schema, will return 0 if there is no schema.
     *
     * @return int
     */
    public static function getDbVersion()
    {
        return (int)@dbFetchCell('SELECT version FROM `dbSchema` ORDER BY version DESC LIMIT 1');
    }

    /**
     * Get an array of the schema files.
     * schema_version => full_file_name
     *
     * @return mixed
     */
    public static function listSchemaFiles()
    {
        // glob returns an array sorted by filename
        $files = glob(Config::get('install_dir') . '/sql-schema/*.sql');

        // set the keys to the db schema version
        return array_reduce($files, function ($array, $file) {
            $array[basename($file, '.sql')] = $file;
            return $array;
        }, array());
    }

    /**
     * Recursively search for relationship paths
     *
     * @param array $tables
     * @param string $target
     * @param array $history
     * @return array|bool
     */
    private function findPathRecursive(array $tables, $target, $history = [])
    {
        $relationships = $this->getTableRelationships();

        d_echo("Starting Tables: " . json_encode($tables) . PHP_EOL);
        if (!empty($history)) {
            $tables = array_diff($tables, $history);
            d_echo("Filtered Tables: " . json_encode($tables) . PHP_EOL);
        }

        foreach ($tables as $table) {
            $table_relations = $relationships[$table];
            $path = [$table];
            d_echo("Searching $table: " . json_encode($table_relations) . PHP_EOL);

            if (!empty($table_relations)) {
                if (in_array($target, $relationships[$table])) {
                    d_echo("Found in $table\n");
                    return $path; // found it
                } else {
                    $recurse = $this->findPathRecursive($relationships[$table], $target,
                        array_merge($history, $tables));
                    if ($recurse) {
                        $path = array_merge($recurse, $path);
                        return $path;
                    }
                }
            } else {
                $relations = array_keys(array_filter($relationships, function ($related) use ($table) {
                    return in_array($table, $related);
                }));

                d_echo("Dead end at $table, searching for relationships " . json_encode($relations) . PHP_EOL);
                $recurse = $this->findPathRecursive($relations, $target, array_merge($history, $tables));
                if ($recurse) {
                    $path = array_merge($recurse, $path);
                    return $path;
                }
            }
        }

        return false;
    }

    /**
     * Dump the database schema to an array.
     * The top level will be a list of tables
     * Each table contains the keys Columns and Indexes.
     *
     * Each entry in the Columns array contains these keys: Field, Type, Null, Default, Extra
     * Each entry in the Indexes array contains these keys: Name, Columns(array), Unique
     *
     * @return array
     */
    private static function loadFromDb()
    {
        $db_name = Config::get('db_name');
        $output = array();

        foreach (dbFetchRows("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db_name' ORDER BY TABLE_NAME;") as $table) {
            $table = $table['TABLE_NAME'];
            foreach (dbFetchRows("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME='$table'") as $data) {
                $def = array(
                    'Field' => $data['COLUMN_NAME'],
                    'Type' => $data['COLUMN_TYPE'],
                    'Null' => $data['IS_NULLABLE'] === 'YES',
                    'Extra' => str_replace('current_timestamp()', 'CURRENT_TIMESTAMP', $data['EXTRA']),
                );

                if (isset($data['COLUMN_DEFAULT']) && $data['COLUMN_DEFAULT'] != 'NULL') {
                    $default = trim($data['COLUMN_DEFAULT'], "'");
                    $def['Default'] = str_replace('current_timestamp()', 'CURRENT_TIMESTAMP', $default);
                }

                $output[$table]['Columns'][] = $def;
            }

            foreach (dbFetchRows("SHOW INDEX FROM `$table`") as $key) {
                $key_name = $key['Key_name'];
                if (isset($output[$table]['Indexes'][$key_name])) {
                    $output[$table]['Indexes'][$key_name]['Columns'][] = $key['Column_name'];
                } else {
                    $output[$table]['Indexes'][$key_name] = array(
                        'Name' => $key['Key_name'],
                        'Columns' => array($key['Column_name']),
                        'Unique' => !$key['Non_unique'],
                        'Type' => $key['Index_type'],
                    );
                }
            }
        }
        return $output;
    }

}
