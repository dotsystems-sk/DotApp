<?php

/**
 * DOTAPP DATABASE INITIALIZATION SCRIPT - initializedb.php
 * Version 1.8
 *
 * This script initializes the DotApp database with all required tables
 * for the users management system. It converts raw SQL to QueryBuilder format
 * and provides a safe, user-controlled way to set up your database.
 *
 * ════════════════════════════════════════════════════════════════════════════════
 * 📋 USAGE GUIDE - How to initialize DotApp database
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * STEP 1: Configure Database in config.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Before running this script, make sure you have configured the 'main' database
 * in your app/config.php file:
 *
 * <?php
 * // app/config.php
 * Config::addDatabase(
 *     "main",           // Internal name (used in scripts)
 *     "127.0.0.1",      // Database host
 *     "your_username",  // Database username
 *     "your_password",  // Database password
 *     "your_database",  // Database name
 *     "UTF8",          // Character set
 *     "mysql",         // Database type (mysql, pgsql, sqlite, etc.)
 *     "pdo"            // Driver (pdo or mysqli)
 * );
 *
 * STEP 2: Run the Initialization Script
 * ─────────────────────────────────────────────────────────────────────────────
 * Open terminal/command prompt and navigate to your project root directory,
 * then execute:
 *
 *     php initializedb.php
 *
 * STEP 3: Confirm Database Operations
 * ─────────────────────────────────────────────────────────────────────────────
 * The script will:
 * 1. Check if 'main' database is configured ✓
 * 2. Ask for user confirmation: "Continue? (type 'YES' to confirm): "
 * 3. Type 'YES' to proceed (case-sensitive)
 *
 * ⚠️  WARNING: This action creates tables in your database!
 *    Make sure you have backup of important data.
 *
 * STEP 4: Monitor Progress
 * ─────────────────────────────────────────────────────────────────────────────
 * Script will show progress:
 * - [TABLE 1/12] Creating dotapp_users table...
 * - [SUCCESS] dotapp_users table created
 * - [FK 1/10] Adding foreign key constraint...
 *
 * STEP 5: Optional Default Data
 * ─────────────────────────────────────────────────────────────────────────────
 * After tables are created, script asks:
 * "Create default roles and permissions? (YES/no): "
 *
 * Type 'YES' or press Enter to create:
 * - admin, moderator, editor, user roles
 * - System rights group
 *
 * ════════════════════════════════════════════════════════════════════════════════
 * 📊 WHAT GETS CREATED
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * CORE TABLES (12 total):
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. dotapp_users              - Main users table with 2FA support
 * 2. dotapp_users_firewall     - IP-based firewall rules
 * 3. dotapp_users_password_resets - Password reset tokens
 * 4. dotapp_users_rights_groups - Rights grouping
 * 5. dotapp_users_rights_list  - Available permissions list
 * 6. dotapp_users_rights       - User-specific permissions
 * 7. dotapp_users_roles_list   - Available roles
 * 8. dotapp_users_rmtokens     - Remember me tokens
 * 9. dotapp_users_roles        - User-role assignments
 * 10. dotapp_users_roles_rights - Role-permission mappings
 * 11. dotapp_users_sessions     - Session storage
 * 12. dotapp_users_url_firewall - URL-based access control
 *
 * FOREIGN KEY CONSTRAINTS (10 total):
 * ─────────────────────────────────────────────────────────────────────────────
 * - User relationships (firewall, passwords, rights, roles, tokens, sessions)
 * - Rights relationships (groups, role assignments)
 * - Role relationships (user assignments, permission mappings)
 *
 * ════════════════════════════════════════════════════════════════════════════════
 * 🔧 TROUBLESHOOTING
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * ERROR: "Main database not found in config.php"
 * → Add database configuration to app/config.php (see STEP 1)
 *
 * ERROR: "Failed to initialize framework"
 * → Check your config.php syntax and database credentials
 *
 * ERROR: "Table creation failed"
 * → Check database permissions and available disk space
 *
 * ERROR: "Foreign key constraint failed"
 * → Ensure all tables were created successfully first
 *
 * ════════════════════════════════════════════════════════════════════════════════
 * 🔄 RE-RUNNING THE SCRIPT
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * The script uses "CREATE TABLE IF NOT EXISTS" so it's safe to run multiple times.
 * Existing tables won't be affected, only missing ones will be created.
 *
 * ════════════════════════════════════════════════════════════════════════════════
 * 📝 NOTES
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * - All tables use InnoDB engine with utf8mb4 character set
 * - Foreign keys have proper CASCADE rules for data integrity
 * - Comments in Slovak language (as per original SQL)
 * - Indexes optimized for common query patterns
 * - Compatible with MySQL 5.7+ and MariaDB 10.0+
 *
 * ════════════════════════════════════════════════════════════════════════════════
 * 🎯 QUICK START EXAMPLE
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * 1. Edit app/config.php:
 *    Config::addDatabase("main", "localhost", "root", "", "dotapp_db", "UTF8", "mysql", "pdo");
 *
 * 2. Run: php initializedb.php
 *
 * 3. Type: YES (when prompted)
 *
 * 4. Type: YES (for default roles, optional)
 *
 * 5. Done! Your DotApp database is ready.
 *
 * ════════════════════════════════════════════════════════════════════════════════
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2026
 * @license   MIT License
 * @security  User confirmation required for all operations
 * @logging   All database operations are logged with timestamps
 *
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

// =================================================================
// SECURITY & CONFIGURATION SECTION
// =================================================================

// Error reporting for development
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 1);

// Define required constants
define('__MAINTENANCE__', FALSE);
define('__DEBUG__', TRUE);
define('__RENDER_TO_FILE__', FALSE);
define('__ROOTDIR__', __DIR__);

// Simulate HTTP environment for framework compatibility
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/init-db';
$_SERVER['HTTP_HOST'] = 'localhost';

// =================================================================
// REQUIRED INCLUDES
// =================================================================

// Framework includes
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Response;
use \Dotsystems\App\Parts\Renderer;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DB;
use \Dotsystems\App\Parts\Translator;
use \Dotsystems\App\Parts\Request;
use \Dotsystems\App\Parts\Config;

// Load framework configuration
require_once 'app/config.php';

// =================================================================
// SECURITY CONFIRMATION
// =================================================================

echo "=== DOTAPP DATABASE INITIALIZATION ===\n";
echo "Framework Version: 1.8\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Check if main database is configured
$mainDbConfigured = false;
$configDatabases = Config::get("databases", []);
if (isset($configDatabases['main'])) {
    $mainDbConfigured = true;
    echo "[INFO] Main database is configured in config.php\n";
} else {
    echo "[ERROR] Main database not found in config.php\n";
    echo "[ERROR] Please add database configuration first:\n";
    echo "[ERROR] Config::addDatabase('main', 'host', 'user', 'pass', 'database', 'charset', 'type', 'driver');\n";
    exit(1);
}

// CRITICAL: Always ask for user confirmation before database operations
$userConfirmed = readline("⚠️  This will CREATE TABLES in your 'main' database.\nThis action cannot be undone. Continue? (type 'YES' to confirm): ");

if ($userConfirmed !== 'YES') {
    echo "[CANCELLED] Operation cancelled by user.\n";
    exit(0);
}

echo "[CONFIRMED] User confirmed operation. Proceeding...\n\n";

// =================================================================
// INITIALIZE FRAMEWORK
// =================================================================

try {
    $dotApp = DotApp::dotApp();
    echo "[INIT] Framework initialized successfully\n\n";
} catch (Exception $e) {
    echo "[ERROR] Failed to initialize framework: " . $e->getMessage() . "\n";
    exit(1);
}

// =================================================================
// DATABASE TABLES CREATION
// =================================================================

$createdTables = 0;
$totalTables = 12; // Count of tables to create

echo "[START] Creating database tables...\n\n";

try {
    // =================================================================
    // 1. CREATE dotapp_users TABLE
    // =================================================================
    echo "[TABLE 1/12] Creating dotapp_users table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users (
                    id INT NOT NULL AUTO_INCREMENT,
                    username VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Uzivatelske meno',
                    email VARCHAR(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Email, moze sa pouzit na prihlasenie tiez. Moze sa pouzivat na emailove notifikacie',
                    password VARCHAR(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Heslo',
                    tfa_firewall INT NOT NULL COMMENT 'Pouzit alebo nepouzit firewall',
                    tfa_sms INT NOT NULL COMMENT 'Pouzvame 2faktor cez SMS?',
                    tfa_sms_number_prefix VARCHAR(8) COLLATE utf8mb4_general_ci NOT NULL,
                    tfa_sms_number VARCHAR(20) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Cislo pre zaslanie SMS',
                    tfa_sms_number_confirmed INT NOT NULL COMMENT 'Cislo potvrdene zadanim kodu',
                    tfa_auth INT NOT NULL COMMENT 'Pouzvame 2 faktor cez GOOGLE AUTH ?',
                    tfa_auth_secret VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Ak mame google auth, tak treba drzat ulozeny secret',
                    tfa_auth_secret_confirmed INT NOT NULL COMMENT 'Bolo potvrdene 2FA auth?',
                    tfa_email INT NOT NULL COMMENT 'Pouzvame 2 faktor cez e-mail?',
                    status INT NOT NULL COMMENT 'Status prihlasenia. 1 - Aktivny, 2-DLhsie neaktivny, 3 - Offline',
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NOT NULL,
                    last_logged_at TIMESTAMP NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY username (username),
                    UNIQUE KEY email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabulky s uzivatelmi modulu users'
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users table created\n";
    $createdTables++;

    // =================================================================
    // 2. CREATE dotapp_users_firewall TABLE
    // =================================================================
    echo "[TABLE 2/12] Creating dotapp_users_firewall table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_firewall (
                    id BIGINT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    rule VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Pravidlo pre firewall. CIDR tvar. Napriklad 192.168.1.0/24',
                    action INT NOT NULL COMMENT '0 - Block, 1 - Allow',
                    active INT NOT NULL COMMENT 'Rule is active or inactive',
                    ordering INT NOT NULL COMMENT 'Poradie pravidla',
                    PRIMARY KEY (id),
                    KEY ordering (ordering),
                    KEY user_id (user_id),
                    KEY user_id_2 (user_id, active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_firewall table created\n";
    $createdTables++;

    // =================================================================
    // 3. CREATE dotapp_users_password_resets TABLE
    // =================================================================
    echo "[TABLE 3/12] Creating dotapp_users_password_resets table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_password_resets (
                    id BIGINT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    PRIMARY KEY (id),
                    KEY user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_password_resets table created\n";
    $createdTables++;

    // =================================================================
    // 4. CREATE dotapp_users_rights_groups TABLE (must be first for FK)
    // =================================================================
    echo "[TABLE 4/12] Creating dotapp_users_rights_groups table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_rights_groups (
                    id INT NOT NULL AUTO_INCREMENT,
                    name MEDIUMTEXT COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Nazov grupy - Normalne textom',
                    ordering INT NOT NULL COMMENT 'Poradie',
                    creator VARCHAR(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Ktory modul to vytvoril pre odinstalaciu. Ak je prazdne tak je to vstavane defaultne do systemu',
                    editable INT NOT NULL COMMENT '0 - nesmie sa upravovat / 1 - moze sa upravovat',
                    PRIMARY KEY (id),
                    KEY ordering (ordering),
                    KEY creator (creator)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_rights_groups table created\n";
    $createdTables++;

    // =================================================================
    // 5. CREATE dotapp_users_rights_list TABLE
    // =================================================================
    echo "[TABLE 5/12] Creating dotapp_users_rights_list table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_rights_list (
                    id INT NOT NULL AUTO_INCREMENT,
                    group_id INT NOT NULL COMMENT 'Id zoskupenia opravneni kedze kazdym odzul moze mat vlastnu skupinu nech v tom nie je bordel',
                    name TEXT CHARACTER SET utf8mb3 NOT NULL COMMENT 'Nazov prava v dlhom formate',
                    description TEXT CHARACTER SET utf8mb3 NOT NULL COMMENT 'Popis opravnenia v detailoch',
                    module VARCHAR(100) CHARACTER SET utf8mb3 NOT NULL COMMENT 'Nazov modulu ktory pravo vytvoril',
                    rightname VARCHAR(100) CHARACTER SET utf8mb3 NOT NULL COMMENT 'Opravnenie',
                    active INT NOT NULL COMMENT '0 nie 1 ano',
                    ordering INT NOT NULL COMMENT 'Zoradenie',
                    creator VARCHAR(100) CHARACTER SET utf8mb3 NOT NULL COMMENT 'Ktory modul vytvoril zoznam aby bolo mozne pri odinstalacii ho zmazat',
                    custom INT NOT NULL COMMENT '0 - nie, 1 - ano',
                    PRIMARY KEY (id),
                    KEY module (module),
                    KEY rightname (rightname),
                    KEY module_2 (module, rightname),
                    KEY ordering (ordering),
                    KEY rightname_2 (rightname, active, ordering),
                    KEY group_id (group_id, module, rightname, ordering),
                    KEY id (id, active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Zoznam opravneni ktore je mozne uzivatelvi priradit'
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_rights_list table created\n";
    $createdTables++;

    // =================================================================
    // 6. CREATE dotapp_users_rights TABLE
    // =================================================================
    echo "[TABLE 6/12] Creating dotapp_users_rights table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_rights (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    right_id INT NOT NULL,
                    PRIMARY KEY (id),
                    KEY user_id (user_id, right_id),
                    KEY user_id_2 (user_id),
                    KEY right_id (right_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_rights table created\n";
    $createdTables++;

    // =================================================================
    // 7. CREATE dotapp_users_roles_list TABLE (must be before roles)
    // =================================================================
    echo "[TABLE 7/12] Creating dotapp_users_roles_list table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_roles_list (
                    id INT NOT NULL AUTO_INCREMENT,
                    name VARCHAR(50) CHARACTER SET utf16 NOT NULL,
                    description TEXT CHARACTER SET utf16,
                    PRIMARY KEY (id),
                    UNIQUE KEY name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_roles_list table created\n";
    $createdTables++;

    // =================================================================
    // 8. CREATE dotapp_users_rmtokens TABLE
    // =================================================================
    echo "[TABLE 8/12] Creating dotapp_users_rmtokens table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_rmtokens (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    token VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY token (token),
                    KEY dotapp_users_sessions_ibfk_1 (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_rmtokens table created\n";
    $createdTables++;

    // =================================================================
    // 9. CREATE dotapp_users_roles TABLE
    // =================================================================
    echo "[TABLE 9/12] Creating dotapp_users_roles table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_roles (
                    id INT NOT NULL AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    role_id INT NOT NULL,
                    assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_user_role (user_id, role_id),
                    KEY id_roly (role_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_roles table created\n";
    $createdTables++;

    // =================================================================
    // 10. CREATE dotapp_users_roles_rights TABLE
    // =================================================================
    echo "[TABLE 10/12] Creating dotapp_users_roles_rights table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_roles_rights (
                    id INT NOT NULL AUTO_INCREMENT,
                    right_id INT NOT NULL,
                    role_id INT NOT NULL,
                    assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_right_role (right_id, role_id),
                    KEY role_id (role_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_roles_rights table created\n";
    $createdTables++;

    // =================================================================
    // 11. CREATE dotapp_users_sessions TABLE
    // =================================================================
    echo "[TABLE 11/12] Creating dotapp_users_sessions table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_sessions (
                    session_id VARCHAR(64) COLLATE utf8mb4_general_ci NOT NULL,
                    sessname VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
                    sessvalues LONGTEXT COLLATE utf8mb4_general_ci NOT NULL,
                    variables LONGTEXT COLLATE utf8mb4_general_ci NOT NULL,
                    expiry BIGINT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (session_id, sessname),
                    KEY idx_expiry (expiry)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_sessions table created\n";
    $createdTables++;

    // =================================================================
    // 12. CREATE dotapp_users_url_firewall TABLE
    // =================================================================
    echo "[TABLE 12/12] Creating dotapp_users_url_firewall table...\n";

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("
                CREATE TABLE IF NOT EXISTS dotapp_users_url_firewall (
                    id INT NOT NULL,
                    user_id INT NOT NULL,
                    url VARCHAR(200) CHARACTER SET utf8mb3 NOT NULL COMMENT 'Url moze byt s * napriklad moze byt * - to znamena vsetky adresy blokneme. Alebo blokneme len */uzivatelia/* takze ak je v URL /uzivatelia/ tak blokneme alebo naopak povolime',
                    action INT NOT NULL COMMENT '0-Blokni / 1 - Povol',
                    active INT NOT NULL COMMENT 'Pravidlo je aktivovane alebo deaktivovane'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        })
        ->execute();

    echo "[SUCCESS] dotapp_users_url_firewall table created\n";
    $createdTables++;

    // =================================================================
    // ADD FOREIGN KEY CONSTRAINTS
    // =================================================================
    echo "\n[FOREIGN KEYS] Adding foreign key constraints...\n";

    $foreignKeys = [
        "ALTER TABLE dotapp_users_firewall ADD CONSTRAINT users_vs_firewall FOREIGN KEY (user_id) REFERENCES dotapp_users (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_password_resets ADD CONSTRAINT dotapp_users_password_resets_ibfk_1 FOREIGN KEY (user_id) REFERENCES dotapp_users (id) ON DELETE CASCADE",
        "ALTER TABLE dotapp_users_rights ADD CONSTRAINT pravo_id FOREIGN KEY (right_id) REFERENCES dotapp_users_rights_list (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_rights ADD CONSTRAINT uziv_id FOREIGN KEY (user_id) REFERENCES dotapp_users (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_rights_list ADD CONSTRAINT dotapp_users_rights_list_ibfk_1 FOREIGN KEY (group_id) REFERENCES dotapp_users_rights_groups (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_rmtokens ADD CONSTRAINT dotapp_users_rmtokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES dotapp_users (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_roles ADD CONSTRAINT id_roly FOREIGN KEY (role_id) REFERENCES dotapp_users_roles_list (id) ON DELETE CASCADE",
        "ALTER TABLE dotapp_users_roles ADD CONSTRAINT uzivatelove_id FOREIGN KEY (user_id) REFERENCES dotapp_users (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_roles_rights ADD CONSTRAINT dotapp_users_roles_rights_ibfk_1 FOREIGN KEY (role_id) REFERENCES dotapp_users_roles_list (id) ON DELETE CASCADE ON UPDATE CASCADE",
        "ALTER TABLE dotapp_users_roles_rights ADD CONSTRAINT dotapp_users_roles_rights_ibfk_2 FOREIGN KEY (right_id) REFERENCES dotapp_users_rights_list (id) ON DELETE CASCADE ON UPDATE CASCADE"
    ];

    foreach ($foreignKeys as $index => $fkSql) {
        echo "[FK " . ($index + 1) . "/" . count($foreignKeys) . "] Adding constraint...\n";
        DB::module("RAW")
            ->q(function ($qb) use ($fkSql) {
                $qb->raw($fkSql);
            })
            ->execute();
    }

    echo "[SUCCESS] All foreign key constraints added\n";
} catch (Exception $e) {
    echo "[ERROR] Database initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// =================================================================
// SUMMARY
// =================================================================

echo "\n" . str_repeat("=", 60) . "\n";
echo "DATABASE INITIALIZATION COMPLETED SUCCESSFULLY\n";
echo str_repeat("=", 60) . "\n";
echo "Tables created: $createdTables / $totalTables\n";
echo "Foreign keys: " . count($foreignKeys) . " added\n";
echo "Database: main (from config.php)\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

echo "✅ DotApp users management system tables ready!\n";
echo "You can now use the users module in your application.\n\n";

// =================================================================
// OPTIONAL: CREATE DEFAULT DATA
// =================================================================

$createDefaults = readline("Create default roles and permissions? (YES/no): ");
if ($createDefaults === 'YES' || $createDefaults === '') {
    echo "[BONUS] Creating default roles and permissions...\n";

    try {
        // Default roles
        DB::module("RAW")
            ->q(function ($qb) {
                $qb->raw("INSERT IGNORE INTO dotapp_users_roles_list (name, description) VALUES
                    ('admin', 'Administrator with full access'),
                    ('moderator', 'Content moderator'),
                    ('editor', 'Content editor'),
                    ('user', 'Regular user')");
            })
            ->execute();

        // Default rights group
        DB::module("RAW")
            ->q(function ($qb) {
                $qb->raw("INSERT IGNORE INTO dotapp_users_rights_groups (name, ordering, creator, editable) VALUES
                    ('System Rights', 1, '', 0)");
            })
            ->execute();

        echo "[SUCCESS] Default roles and permissions created\n";
    } catch (Exception $e) {
        echo "[WARNING] Failed to create defaults: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 DOTAPP DATABASE INITIALIZATION COMPLETE!\n";
echo str_repeat("=", 60) . "\n";
