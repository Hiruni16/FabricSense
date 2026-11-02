<?php

class DB
{
    private static $host = "127.0.0.1";  // MySQL Workbench host
    private static $port = "3306";        //  CORRECT PORT
    private static $db_name = "fabric";   // Database name
    private static $username = "root";
    private static $password = "@Hiruni1116*";
    private static $conn = null;

    public static function connect()
    {
        if (self::$conn === null) {
            try {
                self::$conn = new PDO(
                    "mysql:host=" . self::$host . ";port=" . self::$port . ";dbname=" . self::$db_name . ";charset=utf8mb4",
                    self::$username,
                    self::$password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                // Surface connection issues to callers instead of exiting.
                throw $e;
            }
        }
        return self::$conn;
    }
}




// class DB
// {
//     private static $host = "localhost:3307";       // usually localhost
//     private static $db_name = "fabric_sense"; // EXACT database name in phpMyAdmin
//     private static $username = "root";        // default XAMPP username
//     private static $password = "";            // default XAMPP password is empty
//     private static $conn = null;

//     public static function connect()
//     {
//         if (self::$conn === null) {
//             try {
//                 self::$conn = new PDO(
//                     "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4",
//                     self::$username,
//                     self::$password
//                 );
//                 self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//             } catch (PDOException $e) {
//                 // Show real error while debugging
//                 die(json_encode([
//                     "status" => "error",
//                     "message" => "Database connection failed: " . $e->getMessage()
//                 ]));
//             }
//         }
//         return self::$conn;
//     }
// }
