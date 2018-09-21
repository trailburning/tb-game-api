<?php
class mysqliSingleton
{
    private static $instance;
    private $connection;

    private function __construct()
    {
        $this->connection = new mysqli($GLOBALS['db_server'], $GLOBALS['db_user'], $GLOBALS['db_pass'], $GLOBALS['db_name']);
    }

    public static function init()
    {
        if(is_null(self::$instance))
        {
            self::$instance = new mysqliSingleton();
        }

        return self::$instance;
    }

    public function __call($name, $args)
    {
        if(method_exists($this->connection, $name))
        {
             return call_user_func_array(array($this->connection, $name), $args);
        } else {
             trigger_error('Unknown Method ' . $name . '()', E_USER_WARNING);
             return false;
        }
    }
}
