<?php

namespace Neko\Database;
use Neko\Database\DB;
use Neko\Database\Schema\Grammars;
use Neko\Database\Schema\Magic;
use Neko\Framework\Util\File;
use Neko\Framework\Util\Str;

class Schema
{
    /**
     * Mulai operasi schema terhadap tabel.
     *
     * @param string   $table
     * @param \Closure $callback
     */
    public static function table($table, \Closure $callback)
    {
        call_user_func($callback, $table = new Schema\Table($table));
        return static::execute($table);
    }

    /**
     * Cek apakah tabel ada di database.
     *
     * @param string $table
     *
     * @return bool
     */
    public static function has_table($table, $connection = null)
    {
        global $app;
        if($connection!=null)
        {
            DB::connection($connection);
        }else{
            $connection = "mysql";
        }
        $driver = DB::getConnection()->getDriverName();
        $database = $app->config['db'][$connection]['database'];
        $database = DB::quote($database);
        $table = DB::quote($table);


        $query = '';

        switch ($driver) {
            case 'mysql':
                $query = "SELECT * FROM information_schema.tables WHERE table_schema = $database AND table_name = $table";
                break;

            case 'pgsql':
                $query = "SELECT * FROM information_schema.tables WHERE table_name = $table";
                break;

            case 'sqlite':
                $query = "SELECT * FROM sqlite_master WHERE type = 'table' AND name = $table";
                break;

            case 'sqlsrv':
                $query = "SELECT * FROM sysobjects WHERE type = \'U\' AND name = $table";
                break;

            default:
                throw new \Exception(sprintf(
                    'Unsupported schema operations for selected driver: %s', $driver
                ));
                break;
        }

        return null !== DB::first($query);
    }

    /**
     * Cek apakah kolom ada di suatu tabel.
     *
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    public static function has_column($table, $column)
    {
        global $app;

        $driver = DB::getConnection()->getDriverName();
        $database = $app->config['db'][$driver]['database'];
        $database = DB::quote($database);
        $table = DB::quote($table);
        $column = DB::quote($column);

        $query = '';

        switch ($driver) {
            case 'mysql':
                $query = 'SELECT column_name FROM information_schema.columns '.
                    'WHERE table_schema = '.$database.' AND table_name = '.$table.' AND column_name = '.$column;
                break;

            case 'pgsql':
                $query = 'SELECT column_name FROM information_schema.columns '.
                    'WHERE table_name = '.$table.' AND column_name = '.$column;
                break;

            case 'sqlite':
                try {
                    $query = 'PRAGMA table_info('.str_replace('.', '__', $table).')';
                    $statement = DB::getConnection()->pdo->prepare($query);
                    $statement->execute();

                    // Listing semua kolom di dalam tabel
                    $columns = $statement->fetchAll(\PDO::FETCH_ASSOC);
                    $columns = array_values(array_map(function ($col) {
                        return isset($col['name']) ? $col['name'] : [];
                    }, $columns));

                    return in_array($column, $columns);
                } catch (\PDOException $e) {
                    return false;
                }
                break;

            case 'sqlsrv':
                $query = 'SELECT col.name FROM sys.columns as col '.
                    'JOIN sys.objects AS obj ON col.object_id = obj.object_id '.
                    'WHERE obj.type = \'U\' AND obj.name = '.$table.' AND col.name = '.$column;
                break;

            default:
                throw new \Exception(sprintf(
                    'Unsupported schema operations for selected driver: %s', $driver
                ));
                break;
        }

        return (null !== DB::first($query));
    }

    /**
     * Hidupkan foreign key constraint checking.
     *
     * @param string $table
     *
     * @return bool
     */
    public static function enable_fk_checks($table)
    {
        $table = DB::quote($table);
        $driver = DB::getConnection()->getDriverName();

        switch ($driver) {
            case 'mysql':  $query = 'SET FOREIGN_KEY_CHECKS=1;'; break;
            case 'pqsql':  $query = 'SET CONSTRAINTS ALL IMMEDIATE;'; break;
            case 'sqlite': $query = 'PRAGMA foreign_keys = ON;'; break;
            case 'sqlsrv':
                $query = 'EXEC sp_msforeachtable @command1="print \''.$table.'\'", '.
                    '@command2="ALTER TABLE '.$table.' WITH CHECK CHECK CONSTRAINT all";';
                break;

            default:
                throw new \Exception(sprintf(
                    'Unsupported schema operations for selected driver: %s', $driver
                ));
                break;
        }

        try {
            return false !== DB::getConnection()->pdo->exec($query);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Matikan foreign key constraint checking.
     *
     * @param string $table
     *
     * @return bool
     */
    public static function disable_fk_checks($table)
    {
        $table = DB::quote($table);
        $driver = DB::getConnection()->getDriverName();

        switch ($driver) {
            case 'mysql':  $query = 'SET FOREIGN_KEY_CHECKS=0;'; break;
            case 'pqsql':  $query = 'SET CONSTRAINTS ALL DEFERRED;'; break;
            case 'sqlite': $query = 'PRAGMA foreign_keys = OFF;'; break;
            case 'sqlsrv':
                $query = 'EXEC sp_msforeachtable "ALTER TABLE '.$table.' NOCHECK CONSTRAINT all";';
                break;

            default:
                throw new \Exception(sprintf(
                    'Unsupported schema operations for selected driver: %s', $driver
                ));
                break;
        }

        try {
            return false !== DB::getConnection()->pdo->exec($query);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Buat skema tabel baru.
     *
     * @param string   $table
     * @param \Closure $callback
     */
    public static function create($table, \Closure $callback)
    {
        $table = new Schema\Table($table);
        $table->create();

        call_user_func($callback, $table);

        return static::execute($table);
    }

    /**
     * Buat skema tabel baru jika tabel belum ada.
     *
     * @param string   $table
     * @param \Closure $callback
     */
    public static function create_if_not_exists($table, \Closure $callback)
    {
        if (! static::has_table($table)) {
            static::create($table, $callback);
        }
    }

    /**
     * Ganti nama tabel.
     *
     * @param string $table
     * @param string $new_name
     */
    public static function rename($table, $new_name)
    {
        $table = new Schema\Table($table);
        $table->rename($new_name);

        return static::execute($table);
    }

    /**
     * Hapus tabel dari skema.
     *
     * @param string $table
     * @param string $connection
     */
    public static function drop($table, $connection = null)
    {
        $table = new Schema\Table($table);
        $table->on($connection);
        $table->drop();

        return static::execute($table);
    }

    /**
     * Hapus tabel dari skema (hanya jika tabelnya ada).
     *
     * @param string $table
     * @param string $connection
     */
    public static function drop_if_exists($table, $connection = null)
    {
        if (static::has_table($table, $connection)) {
            static::drop($table, $connection);
        }
    }

    /**
     * Jalankan operasi skema terhadap database.
     *
     * @param Schema\Table $table
     */
    public static function execute($table)
    {
        static::implications($table);

        foreach ($table->commands as $command) {
            $connection = DB::getConnection();
            $grammar = static::grammar($connection);

            if (method_exists($grammar, $method = $command->type)) {
                $statements = $grammar->{$method}($table, $command);
                $statements = (array) $statements;

                foreach ($statements as $statement) {
                    $connection->query($statement);
                }
            }
        }
    }

    /**
     * Tambahkan perintah implisit apapun ke operasi skema.
     *
     * @param Schema\Table $table
     */
    protected static function implications($table)
    {
        if (count($table->columns) > 0 && ! $table->creating()) {
            $command = new Magic(['type' => 'add']);
            array_unshift($table->commands, $command);
        }

        foreach ($table->columns as $column) {
            $indexes = ['primary', 'unique', 'fulltext', 'index'];

            foreach ($indexes as $index) {
                if (isset($column->{$index})) {
                    if (true === $column->{$index}) {
                        $table->{$index}($column->name);
                    } else {
                        $table->{$index}($column->name, $column->{$index});
                    }
                }
            }
        }
    }

    /**
     * Mereturn query grammar yang sesuai untuk driver database saat ini.
     *
     * @param Connection $connection
     *
     * @return Grammar
     */
    public static function grammar(Connection $connection)
    {
        global $app;
        $connection = DB::getConnection();
        $driver = $app->config['db'][$app->config['db_connection']]['driver'];

        switch ($driver) {
            case 'mysql':  return new Grammars\MySQL($connection);
            case 'pgsql':  return new Grammars\Postgres($connection);
            case 'sqlsrv': return new Grammars\SQLServer($connection);
            case 'sqlite': return new Grammars\SQLite($connection);
        }

        throw new \Exception(sprintf('Unsupported schema operations for selected driver: %s', $driver));
    }

/**
     * Jalankan operasi migration.
     *
     * @param Schema\Table $table
     */
    public static function migrate($path, $method, $with_seed=false, $with_refresh=false)
    {
        require $path;
        $file_name = basename($path);
        echo "Processing : ".$file_name."\n";
        $file_name_table = explode("create_",str_replace('_table.php', '', $file_name))[1];
        $name_only = explode("_",$file_name);
        $name = [];
        foreach ($name_only as $key => $val) {
            if($key>4)
            {
                $name[] = $val;
            }
        }
        $file_name = implode("",$name);

        $class_name = sprintf("Create%sTable", str_replace('.php', '', str_replace('table', '', ucwords($file_name))));
        $table = new $class_name;

        if($method=="up")
        {
            if($with_refresh==true)
            {
                self::drop_if_exists($file_name_table);
            }
            $table->up();
            if($with_seed==true)
            {
                $table->seed();
            }
        }
    }

    /**
     * Jalankan operasi skema terhadap database.
     *
     * @param Schema\Table $table
     */
    public static function generate($console = false, $with_seed=false, $with_fresh=false,$include = [], $exclude = [])
    {
        global $app;

        $timezone = 'Asia/Singapore';
        date_default_timezone_set($timezone);

        $datetime_prefix_format = 'Y_m_d_His';
        $datetime_prefix = date($datetime_prefix_format);

        $folder = $app->path."/app/storage/database/migration/";//. $datetime_prefix;

        if(!is_dir($folder)){
            mkdir($folder, 0777, true);
        }

        $debug = false;

        $exclude_tables = [];
        $only_include_tables = [];
        $connection = DB::getConnection();
        
        $driver = $app->config['db'][$app->config['db_connection']]['driver'];
        $database = $app->config['db'][$driver]['database'];

        switch ($driver) {
            case 'mysql':
                $query = (sprintf("select TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE, TABLE_COMMENT from `information_schema`.`tables` where TABLE_SCHEMA = '%s';", ($database)));
                break;

            case 'pgsql':
                $query = 'SELECT t.table_schema as "TABLE_SCHEMA", t.table_name as "TABLE_NAME", t.table_type as "TABLE_TYPE", pg_catalog.obj_description(pgc.oid, \'pg_class\') AS "TABLE_COMMENT"
                FROM information_schema.tables t
                INNER JOIN pg_catalog.pg_class pgc
                ON t.table_name = pgc.relname 
                WHERE t.table_type=\'BASE TABLE\'
                AND t.table_schema=\'public\';';
                break;

            case 'sqlite':
                $query = "SELECT * FROM sqlite_master WHERE type = 'table' AND name!='sqlite_sequence'";
                break;

            case 'sqlsrv':
                $query = 'SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE, TABLE_COMMENT FROM sysobjects WHERE type = \'U\'';
                break;

            default:
                throw new \Exception(sprintf(
                    'Unsupported schema operations for selected driver: %s', $driver
                ));
                break;
        }
         //$query = (sprintf("select TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE, TABLE_COMMENT from `information_schema`.`tables` where TABLE_SCHEMA = '%s';", ($database)));

        
        $result = $connection->query($query);
        $tables = [];

        
        while($row = $result->fetch()){
            $tables []= $row;
        };

        $table_names = array_map(function($table){return $table[1];}, $tables);

        if(count($only_include_tables) > 0){
            $table_names = array_filter($table_names, function($table_name) use ($table_names, $only_include_tables) { return in_array($table_name, $only_include_tables); });
        } else {
            $table_names = array_filter($table_names, function($table_name) use ($table_names, $exclude_tables) { return !in_array($table_name, $exclude_tables); });
        }

        $field_type_name_mappings = [
            'tinyint' => 'tinyInteger',
            'int' => 'integer',
            'int8' => 'integer',
            'varchar' => 'string',
            'bigint' => 'integer',
            'timestamp' => 'datetime',
            'numeric' => 'decimal',
            'float' => 'decimal'
        ];

        $filter_field_type_params = [
            'tinyInteger' => function($x) { return []; },
            'integer' => function($x) { return []; },
            'increments' => function($x) { return []; }
        ];

        $nullable_field_types = [
            'varchar',
            'string',
            'datetime',
            'text',
            'integer'
        ];

        

        if($with_fresh==true)
        {
            File::cleandir($folder);
        }

        foreach ($table_names as $table_name){
            //echo sprintf("Table: %s", $table_name);

            switch ($driver) {
                case 'mysql':
                    $query = sprintf("show full columns from %s", $table_name);
                    break;
    
                case 'pgsql':
                    $query = 'SELECT column_name as "Field", udt_name as "Type", "collation_name" as "Collation", is_nullable as "Null", null as "Key", column_default as "Default", \'\' as "Extra",\'select,insert,update,references\' as "Privileges", \'\' as "Comment" , 
                    character_maximum_length as "max_char_length", numeric_precision, numeric_scale
                    FROM information_schema.columns WHERE table_schema = \'public\' AND table_name = \''.$table_name.'\';';
                    break;
    
                case 'sqlite':
                    $query = "PRAGMA table_info(".$table_name.")";
                    break;
    
                case 'sqlsrv':
                    $query = sprintf("show full columns from %s", $table_name);
                    break;
    
                default:
                    throw new \Exception(sprintf(
                        'Unsupported schema operations for selected driver: %s', $driver
                    ));
                    break;
            }

            $table_schema_codes = [];
            $exclude_fields = ['created_at', 'updated_at'];

            $fields = [];
            if($result = $connection->query($query)){
                while($row = $result->fetch()){
                    $row = array_intersect_key($row, array_flip(array_filter(array_keys($row), 'is_numeric')));
                    switch ($driver) {
                        case 'mysql': 
                            $field = $row[0];
                            $field_type = $row[1];
                            $collation = $row[2];
                            $null = $row[3];
                            $key = $row[4];
                            $default = $row[5];
                            $extra = $row[6];
                            $comment = $row[8];
                            break;
            
                        case 'pgsql':
                            $field = $row[0];
                            $field_type = $row[1];
                            if($field_type=="varchar")
                            {
                                $field_type = "varchar(".$row[9].")";
                            }
                            if($field_type=="numeric")
                            {
                                $field_type = "numeric(".$row[10].",".$row[11].")";
                            }
                            $collation = $row[2];
                            $null = $row[3];
                            $key = $row[4];
                            $default = $row[5];
                            if((Str::contains($default, 'nextval')))
                            {
                                $default = null;
                                $extra = "auto_increment";
                            }else if((Str::contains($default, '::'))){
                                $default = str_replace("'","",explode("::",$default)[0]);
                                $extra = "";
                            }else{
                                $extra = $row[6];
                            }
                            $comment = $row[8];
                            break;
            
                        case 'sqlite':
                            $field = $row[1];
                            $field_type = $row[2];
                            if($field_type=="FLOAT")
                            {
                                $field_type = "decimal(10,0)";
                            }
                            $collation = "";
                            $null = $row[3];
                            $key = $row[5];
                            if(($row[4]!==NULL)){
                                $default = str_replace("`","",$row[4]);
                            }else{
                                $default = null;
                            }
                            $extra = "";
                            $comment = "";
                            break;
            
                        case 'sqlsrv':
                            $query = 'SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE, TABLE_COMMENT FROM sysobjects WHERE type = \'U\'';
                            break;
            
                        default:
                            throw new \Exception(sprintf(
                                'Unsupported schema operations for selected driver: %s', $driver
                            ));
                            break;
                    }

                   

                    if(in_array($field, $exclude_fields)){
                        continue;
                    }

                    $field_type_split = explode('(', $field_type);

                    $field_type_name = $field_type_split[0];
                    $field_type_name = strtolower($field_type_name);
                    $field_type_name = array_key_exists($field_type_name, $field_type_name_mappings) ? $field_type_name_mappings[$field_type_name] : $field_type_name;

                    $field_type_settings = count($field_type_split) > 1 ? explode(' ', $field_type_split[1]) : [];

                    $field_type_params_string = count($field_type_split) > 1 ? explode(')', $field_type_split[1])[0] : '';
                    $field_type_params = $field_type_params_string != '' ? explode(',', $field_type_params_string) : [];
                    $field_type_params = array_key_exists($field_type_name, $filter_field_type_params) ? $filter_field_type_params[$field_type_name]($field_type_params) : $field_type_params;

                    if($extra == 'auto_increment' && $field == 'id' || $extra == 'auto_increment' && $field == 'no' || $extra == 'auto_increment' && $field == 'num'){
                        $field_type_name = 'increments';
                    }

                    $appends = [];
                    if($null == 'YES' && in_array($field_type_name, $nullable_field_types)){
                        $appends []= '->nullable()';
                    }
                    if(in_array('unsigned', $field_type_settings) && $field_type_name != 'increments'){
                        $appends []= '->unsigned()';
                    }
                    if($key == 'PRI' &&  $field_type_name != 'increments'){
                        $appends []= '->primary()';
                    }
                    if(!is_null($default)){
                        if($default == 'CURRENT_TIMESTAMP'){
                            $appends []= sprintf("->default(\DB::raw('%s'))", $default);
                        }else{
                            $appends []= sprintf("->default('%s')", $default);
                        }
                    }
                    if($comment) {
                        $appends []= "->comment('{$comment}')";
                    }

                    $migration_params = array_merge([sprintf("'%s'", $field)], $field_type_params);
                    $migration_params = array_filter($migration_params, function($param){
                    return trim($param) != "";
                    });

                    $table_schema_code = sprintf("    \$table->%s(%s)%s;", $field_type_name, implode(", ", $migration_params), implode("", $appends));

                    $debug and $table_schema_codes []= "    // " . json_encode($row);
                    $table_schema_codes []= $table_schema_code;
                };
            }

            $table_schema_code = '';//'    $table->timestamps();';
            $table_schema_codes []= ($table_schema_code);


            switch ($driver) {
                case 'mysql':
                    $query = "SHOW INDEX FROM {$table_name};";
                    break;
    
                case 'pgsql':
                    $query = 'select kcu.table_name AS "Table", \'0\' as "Non_unique", tco.constraint_type as "Key_name",kcu.ordinal_position as "Seq_in_index",kcu.column_name as key_column from information_schema.table_constraints tco join information_schema.key_column_usage kcu on kcu.constraint_name = tco.constraint_name and kcu.constraint_schema = tco.constraint_schema and kcu.constraint_name = tco.constraint_name where tco.constraint_type = \'PRIMARY KEY\' AND kcu."table_name" = \''.$table_name.'\' order by kcu.table_schema, kcu.table_name, "Seq_in_index";';
                    break;
    
                case 'sqlite':
                    $query = "SELECT * FROM sqlite_master  WHERE type = 'index' AND tbl_name='".$table_name."';";
                    break;
    
                case 'sqlsrv':
                    $query = "SHOW INDEX FROM {$table_name};";
                    break;
    
                default:
                    throw new \Exception(sprintf(
                        'Unsupported schema operations for selected driver: %s', $driver
                    ));
                    break;
            }


            $indexes = [];
            $results = $connection->query($query);
            while($row = $results->fetch()){
                if ($row[2] == 'PRIMARY' || $row[2] == 'PRIMARY KEY') {
                    $indexes[$row[2]]['is_unique'] = $row[1] ? false : true;
                    $indexes[$row[2]]['keys'][] = $row[4];
                }
            }

            if (!empty($indexes)) {
                foreach ($indexes as $indexName => $index) {
                    $table_schema_codes[] = '    $table->' . ($index['is_unique'] ? 'unique' : 'index') . '(["' . implode('", "', $index['keys']) .'"]);';
                }
            }

            switch ($driver) {
                case 'mysql':
                    $query = ("SHOW CREATE TABLE {$table_name};");
                    break;
    
                case 'pgsql':
                    $query = ("SELECT table_name, column_name, data_type 
                    FROM information_schema.columns 
                    WHERE table_name = '".$table_name."'; ");
                    break;
    
                case 'sqlite':
                    $query = "SELECT name, sql FROM sqlite_master WHERE name = '".$table_name."'";
                    break;
    
                case 'sqlsrv':
                    $query = ("SHOW CREATE TABLE {$table_name};");
                    break;
    
                default:
                    throw new \Exception(sprintf(
                        'Unsupported schema operations for selected driver: %s', $driver
                    ));
                    break;
            }

            

            $sql = [];
            $results = $connection->query($query);
            while($row = $results->fetch()){
                $sql []= $row[1];
            }
            $sql = implode("\n", $sql);
            $sql = explode("\n",$sql);
            $sql_new = [];
            foreach ($sql as $key => $val) {
                $sql_new[] = ($key!=0) ? "    * ".$val : "".$val;
            }
            $sql_query = implode("\n", $sql_new);
            $classname = sprintf("Create%sTable", str_replace(' ', '', str_replace('_', ' ', ucwords($table_name))));

            $table_schema_codes = implode("\n        ", $table_schema_codes);
            $table_schema_seed = "";
            if($with_seed==true)
            {
                $table_data = db::table($table_name)->get();
                $table_data_query = [];
                foreach ($table_data as $key => $val) {
                    $val = json_encode($val);
                    $table_query = "db::table('$table_name')->insert(json_decode('$val',true));";
                    $table_data_query[] =  ($key!=0) ? "        ".$table_query : "".$table_query;
                }
                $table_schema_seed = implode("\n",$table_data_query);
            }

            $code = File::get(__dir__."/Schema/Stubs/migration.stub");
            $code = str_replace(array('$classname','$table_schema_codes','$table_schema_seed','$sql_query','$table_name'), array($classname, $table_schema_codes, $table_schema_seed, $sql_query, $table_name), $code);
            $output_file_name = sprintf("%s/%s_create_%s_table.php", $folder, $datetime_prefix, $table_name);
            if($console==true)
            {
                echo "Generated file: ".basename($output_file_name)."\n";
            }else{
                echo "Generated file: ".basename($output_file_name)."<br>";
            }
            file_put_contents ( $output_file_name , $code );
        }

    }
}
