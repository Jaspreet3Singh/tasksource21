<?php
    /*
     * Use:
     * file to be required for database connection.
     */

    /*
     * Note:
     * require and include are only different in the way they handle errors. required file missing
     * will throw a fatal exception while include will simply continue running without using that file
     */
    $dbname = 'tasksource21';
    $port = 5432;
    $host = 'localhost';
    $pass = 'password';
    $user = 'postgres';

    // in php, variable names inside double quotes get interpreted, whereas in single quotes
    // the string is literal, just like bash
    $dsn = "host=$host port=$port dbname=$dbname user=$user password=$pass";

    /*This $dbh variable is available after this file is required_once */
    $dbh = pg_connect($dsn)
    or die('Could not connect to the database\n');


?>