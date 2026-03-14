<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Get the PDO connection to the SQLite database.
     * Initializes the database file and tables if they don't exist.
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = __DIR__ . '/../database/database.sqlite';
            $dir = dirname($dbPath);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            try {
                self::$pdo = new PDO('sqlite:' . $dbPath);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::initTables();
            } catch (PDOException $e) {
                file_put_contents(__DIR__ . '/log/debug.log', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
                throw $e;
            }
        }

        return self::$pdo;
    }

    /**
     * Create required tables if they do not exist yet.
     */
    private static function initTables(): void
    {
        $db = self::$pdo;

        // Table for long-term memories (facts about the user)
        $db->exec("
            CREATE TABLE IF NOT EXISTS memories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fact TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Table for scheduled reminders
        $db->exec("
            CREATE TABLE IF NOT EXISTS reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trigger_time DATETIME NOT NULL,
                message TEXT NOT NULL,
                is_sent INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Save a new fact to the database
     */
    public static function addMemory(string $fact): void
    {
        $stmt = self::getConnection()->prepare("INSERT INTO memories (fact) VALUES (:fact)");
        $stmt->execute(['fact' => $fact]);
    }

    /**
     * Get all saved facts as a bulleted list
     */
    public static function getAllMemoriesFormatted(): string
    {
        $stmt = self::getConnection()->query("SELECT fact FROM memories ORDER BY created_at ASC");
        $facts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($facts)) {
            return "No memories saved yet.";
        }

        $formatted = "Things you must remember about the user:\n";
        foreach ($facts as $fact) {
            $formatted .= "- $fact\n";
        }
        return $formatted;
    }

    /**
     * Schedule a new reminder
     */
    public static function addReminder(string $triggerTime, string $message): void
    {
        $stmt = self::getConnection()->prepare("INSERT INTO reminders (trigger_time, message) VALUES (:time, :msg)");
        $stmt->execute([
            'time' => $triggerTime,
            'msg'  => $message
        ]);
    }
}
