# DotApp Database Operations Guide for AI

## Overview

**This guide is specifically for AI assistants working with the DotApp PHP Framework.** It explains how to properly handle database operations in DotApp without relying on external SQL files.

**⚠️ CRITICAL:** Always use DotApp's `DB::module()` syntax. Never create or reference external `.sql` files. All database operations must be done through PHP scripts using the framework's database modules.

---

## 1. Understanding DotApp Database Architecture

### 1.1 Database Configuration

In `app/config.php`, databases are configured with **internal names**:

```php
// app/config.php - Database configuration:

// Use addDatabase() method with separate parameters:
Config::addDatabase(
    "main",           // Internal name (used in selectDb())
    "127.0.0.1",      // Host/server
    "username",       // Database username
    "password",       // Database password
    "database_name",  // Actual database name on server
    "UTF8",           // Character set
    "mysql",          // Database type: mysql, postgres, sqlite, etc.
    "pdo"             // Driver type: 'pdo' or 'mysqli' (built-in), or custom driver name if registered
);
```

**Internal name**: `"main"` (what you use in `selectDb('main')`)
**Actual database**: `"database_name"` (actual database on server)
**Driver**: `"pdo"` or `"mysqli"` (PHP database extension to use)
**Type**: `"mysql"`, `"postgres"`, `"sqlite"`, etc. (database engine)

### 1.2 Database Modules

DotApp has two main database modules:

- **`DB::module("RAW")`** - Direct SQL queries with QueryBuilder
- **`DB::module("ORM")`** - Object-Relational Mapping

For database operations (create/drop tables, seeding), use **`DB::module("RAW")`**.

**Custom Database Drivers:** For special databases (MongoDB, Redis, etc.), register custom drivers using `Databaser::customDriver('driver_name', 'DriverClass')`. Custom drivers must extend the framework's driver pattern and implement the `create()` static method. Then use the custom driver name in the configuration:

```php
// Register custom driver
Databaser::customDriver('mongodb', 'MyNamespace\\MongoDbDriver');

// Configure database with custom driver
Config::addDatabase("mongo", "localhost", "user", "pass", "myapp", "UTF8", "mongodb", "mongodb");
```

### 1.3 Why app/config.php Instead of index.php?

**CRITICAL:** Always use `require_once 'app/config.php';` instead of `require_once 'index.php';`

**Why?**

- `index.php` sets production settings like `display_errors = 0`
- This would override your debugging preferences
- `app/config.php` contains only database configuration
- You maintain control over error reporting and display settings

**Example:**

```php
// ✅ GOOD - You control error settings
require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ❌ BAD - index.php overrides your settings
require_once 'index.php'; // display_errors gets set to 0 here!
error_reporting(E_ALL);   // Too late, already overridden
ini_set('display_errors', 1); // Too late, already overridden
```

---

## 2. Creating Database Operation Scripts

### 2.1 Basic Script Structure

Always create standalone PHP files in the project root for database operations:

**For RAW database operations (create/drop tables, seeding):**

```php
<?php
// database_setup.php - Example script for RAW database operations

// ⚠️ IMPORTANT: Include ONLY app/config.php, NOT index.php!
// index.php sets display_errors to 0 and other production settings
// that would override your debugging preferences

// Simulate HTTP request for proper framework initialization (CLI workaround)
$_SERVER['REQUEST_URI'] = '/'.md5(random_bytes(10))."/".md5(random_bytes(10))."/".md5(random_bytes(10));
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET'; // Use standard HTTP method
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Use statements for framework classes
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Response;
use \Dotsystems\App\Parts\Renderer;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DB;
use \Dotsystems\App\Parts\Translator;
use \Dotsystems\App\Parts\Request;
use \Dotsystems\App\Parts\Entity;
use \Dotsystems\App\Parts\Collection;

require_once 'app/config.php';

// Set error reporting for development/debugging (optional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Your database operations here
echo "Starting database operations...\n";

// Example: Create a table
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    })
    ->execute(
        function($result) {
            echo "✓ Table 'users' created successfully\n";
        },
        function($error) {
            echo "✗ Error creating table: $error\n";
        }
    );

// More operations...
echo "Database operations completed.\n";
```

**For ORM operations (Entity, Collection, Relations):**

```php
<?php
// orm_test.php - Example script for ORM testing with full framework initialization

// Set error reporting FIRST (before any includes)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define required constants for DotApp framework
define('__MAINTENANCE__', FALSE);
define('__DEBUG__', FALSE);
define('__RENDER_TO_FILE__', FALSE);
define('__ROOTDIR__', __DIR__);

// Use statements for all needed classes
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Response;
use \Dotsystems\App\Parts\Renderer;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DB;
use \Dotsystems\App\Parts\Translator;
use \Dotsystems\App\Parts\Request;
use \Dotsystems\App\Parts\Entity;
use \Dotsystems\App\Parts\Collection;

// Include framework config (this initializes the database connections)
require_once 'app/config.php';

echo "Framework config loaded, initializing DotApp...\n";

// PROPER FRAMEWORK INITIALIZATION FOR ORM
$dotApp = DotApp::dotApp();
// Note: config.php already calls load_modules(), so no need to call it again

echo "Framework fully initialized!\n";

// Now you can use ORM operations
$products = DB::module("ORM")
    ->selectDb('main')  // Use database 'main' as configured
    ->q(function($qb) {
        $qb->select(['id', 'name', 'code'])
           ->from('products')
           ->where('active', '=', 1)
           ->limit(5);
    })
    ->all();

echo "Loaded " . count($products) . " products via ORM\n";
```

### 2.2 Script Execution

**⚠️ CLI Limitations:** DotApp framework is designed for web environments. CLI scripts may show warnings related to sessions and HTTP headers. To avoid issues, always simulate HTTP request variables as shown in the examples above.

Run database scripts from command line:

```bash
cd /path/to/project
php database_setup.php
```

**Alternative: Web-based Testing**
For better compatibility, create web-accessible test scripts:

```php
// public/test_db.php
<?php
// Web-accessible test script (place in web root)
require_once '../app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Your database tests here
echo "<h1>Database Test Results</h1>";
// ... test code ...
```

Access via: `http://your-domain.com/test_db.php`

````

---

## 3. Reading Database Structure

### 3.1 Get All Tables

```php
<?php
// read_tables.php - Read all tables from database

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Reading database structure...\n";

DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SHOW TABLES");
    })
    ->all(function($tables) {
        echo "Found " . count($tables) . " tables:\n";
        foreach ($tables as $table) {
            $tableName = array_values($table)[0]; // First column contains table name
            echo "- $tableName\n";
        }
    });
````

### 3.2 Get Table Structure

```php
<?php
// read_table_structure.php - Read specific table structure

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tableName = 'users'; // Change this to the table you want to inspect

echo "Reading structure of table '$tableName'...\n";

DB::module("RAW")
    ->q(function ($qb) use ($tableName) {
        $qb->raw("DESCRIBE `$tableName`");
    })
    ->all(function($columns) {
        echo "Table structure:\n";
        echo str_pad("Field", 20) . str_pad("Type", 15) . str_pad("Null", 8) . str_pad("Key", 8) . "Default\n";
        echo str_repeat("-", 70) . "\n";

        foreach ($columns as $column) {
            echo str_pad($column['Field'], 20) .
                 str_pad($column['Type'], 15) .
                 str_pad($column['Null'], 8) .
                 str_pad($column['Key'], 8) .
                 ($column['Default'] ?? 'NULL') . "\n";
        }
    });
```

### 3.3 Check If Table Exists

```php
<?php
// check_table_exists.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tableName = 'users';

DB::module("RAW")
    ->q(function ($qb) use ($tableName) {
        $qb->raw("SHOW TABLES LIKE ?", [$tableName]);
    })
    ->all(function($result) {
        if (count($result) > 0) {
            echo "✓ Table '$tableName' exists\n";
        } else {
            echo "✗ Table '$tableName' does not exist\n";
        }
    });
```

---

## 4. Creating Tables

### 4.1 Basic Table Creation

```php
<?php
// create_users_table.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Creating users table...\n";

DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('user', 'admin') DEFAULT 'user',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    })
    ->execute(
        function($result) {
            echo "✓ Users table created successfully\n";
        },
        function($error) {
            echo "✗ Error creating users table: $error\n";
        }
    );
```

### 4.2 Create Multiple Tables

```php
<?php
// create_all_tables.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tables = [
    'users' => "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'posts' => "
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            published TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_published (published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'categories' => "
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "
];

echo "Creating all tables...\n";

foreach ($tables as $tableName => $sql) {
    DB::module("RAW")
        ->q(function ($qb) use ($sql) {
            $qb->raw($sql);
        })
        ->execute(
            function($result) use ($tableName) {
                echo "✓ Table '$tableName' created\n";
            },
            function($error) use ($tableName) {
                echo "✗ Error creating table '$tableName': $error\n";
            }
        );
}

echo "All table creation operations completed.\n";
```

---

## 5. Dropping Tables

### 5.1 Drop Single Table

```php
<?php
// drop_table.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tableName = 'test_table'; // Change this to the table you want to drop

echo "Dropping table '$tableName'...\n";

// Check if table exists first
DB::module("RAW")
    ->q(function ($qb) use ($tableName) {
        $qb->raw("SHOW TABLES LIKE ?", [$tableName]);
    })
    ->all(function($result) use ($tableName) {
        if (count($result) == 0) {
            echo "✗ Table '$tableName' does not exist\n";
            return;
        }

        // Table exists, drop it
        DB::module("RAW")
            ->q(function ($qb) use ($tableName) {
                $qb->raw("DROP TABLE `$tableName`");
            })
            ->execute(
                function($result) use ($tableName) {
                    echo "✓ Table '$tableName' dropped successfully\n";
                },
                function($error) use ($tableName) {
                    echo "✗ Error dropping table '$tableName': $error\n";
                }
            );
    });
```

### 5.2 Drop All Tables (CAUTION!)

```php
<?php
// drop_all_tables.php - USE WITH EXTREME CAUTION!

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$confirm = getenv('CONFIRM_DROP_ALL') === 'yes';

if (!$confirm) {
    echo "❌ DANGER: This script will drop ALL tables in the database!\n";
    echo "To run this script, set environment variable: CONFIRM_DROP_ALL=yes\n";
    echo "Example: CONFIRM_DROP_ALL=yes php drop_all_tables.php\n";
    exit(1);
}

echo "🔥 DROPPING ALL TABLES - THIS CANNOT BE UNDONE!\n";

DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SHOW TABLES");
    })
    ->all(function($tables) {
        if (empty($tables)) {
            echo "No tables to drop.\n";
            return;
        }

        echo "Found " . count($tables) . " tables to drop...\n";

        foreach ($tables as $table) {
            $tableName = array_values($table)[0];

            DB::module("RAW")
                ->q(function ($qb) use ($tableName) {
                    $qb->raw("DROP TABLE `$tableName`");
                })
                ->execute(
                    function($result) use ($tableName) {
                        echo "✓ Dropped table: $tableName\n";
                    },
                    function($error) use ($tableName) {
                        echo "✗ Error dropping table '$tableName': $error\n";
                    }
                );
        }

        echo "All tables dropped.\n";
    });
```

---

## 6. Seeding Data

### 6.1 Basic Seeding

```php
<?php
// seed_users.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Seeding users table...\n";

$users = [
    ['name' => 'John Doe', 'email' => 'john@example.com'],
    ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ['name' => 'Bob Johnson', 'email' => 'bob@example.com'],
    ['name' => 'Alice Brown', 'email' => 'alice@example.com'],
    ['name' => 'Charlie Wilson', 'email' => 'charlie@example.com']
];

foreach ($users as $user) {
    DB::module("RAW")
        ->q(function ($qb) use ($user) {
            $qb->insert('users', [
                'name' => $user['name'],
                'email' => $user['email'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        })
        ->execute(
            function($result, $db, $execution_data) use ($user) {
                echo "✓ Created user: " . $user['name'] . " (ID: " . $execution_data['insert_id'] . ")\n";
            },
            function($error) use ($user) {
                echo "✗ Error creating user '" . $user['name'] . "': $error\n";
            }
        );
}

echo "User seeding completed.\n";
```

### 6.2 Bulk Seeding with Transactions

```php
<?php
// seed_bulk_data.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Seeding bulk data with transactions...\n";

$categories = [
    ['name' => 'Technology', 'slug' => 'technology'],
    ['name' => 'Sports', 'slug' => 'sports'],
    ['name' => 'Entertainment', 'slug' => 'entertainment'],
    ['name' => 'Science', 'slug' => 'science'],
    ['name' => 'Politics', 'slug' => 'politics']
];

DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("START TRANSACTION");
    })
    ->execute();

$successCount = 0;
$errorCount = 0;

foreach ($categories as $category) {
    DB::module("RAW")
        ->q(function ($qb) use ($category) {
            $qb->insert('categories', [
                'name' => $category['name'],
                'slug' => $category['slug'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        })
        ->execute(
            function($result, $db, $execution_data) use ($category, &$successCount) {
                $successCount++;
                echo "✓ Created category: " . $category['name'] . "\n";
            },
            function($error) use ($category, &$errorCount) {
                $errorCount++;
                echo "✗ Error creating category '" . $category['name'] . "': $error\n";
            }
        );
}

if ($errorCount == 0) {
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("COMMIT");
        })
        ->execute();
    echo "✓ All categories seeded successfully ($successCount created)\n";
} else {
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("ROLLBACK");
        })
        ->execute();
    echo "✗ Seeding failed, rolled back changes ($errorCount errors)\n";
}
```

### 6.3 Generate Fake Data

```php
<?php
// seed_fake_users.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Generating and seeding fake users...\n";

function generateFakeUser() {
    $firstNames = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Edward', 'Fiona'];
    $lastNames = ['Doe', 'Smith', 'Johnson', 'Brown', 'Wilson', 'Davis', 'Miller', 'Garcia'];

    $firstName = $firstNames[array_rand($firstNames)];
    $lastName = $lastNames[array_rand($lastNames)];
    $email = strtolower($firstName . '.' . $lastName . rand(1, 100) . '@example.com');

    return [
        'name' => $firstName . ' ' . $lastName,
        'email' => $email,
        'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'))
    ];
}

$userCount = 50; // How many fake users to create

for ($i = 0; $i < $userCount; $i++) {
    $user = generateFakeUser();

    DB::module("RAW")
        ->q(function ($qb) use ($user) {
            $qb->insert('users', $user);
        })
        ->execute(
            function($result, $db, $execution_data) use ($user, $i, $userCount) {
                echo "✓ Created user " . ($i + 1) . "/$userCount: " . $user['name'] . "\n";
            },
            function($error) use ($user) {
                echo "✗ Error creating user '" . $user['name'] . "': $error\n";
            }
        );
}

echo "Fake user seeding completed.\n";
```

---

## 7. Advanced Operations

### 7.1 Create Database Backup Script

```php
<?php
// backup_database.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Creating database backup...\n";

$backupFile = 'database_backup_' . date('Y-m-d_H-i-s') . '.sql';

DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SHOW TABLES");
    })
    ->all(function($tables) use ($backupFile) {
        $backupContent = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $tableName = array_values($table)[0];

            // Get CREATE TABLE statement
            DB::module("RAW")
                ->q(function ($qb) use ($tableName) {
                    $qb->raw("SHOW CREATE TABLE `$tableName`");
                })
                ->first(function($result) use (&$backupContent, $tableName) {
                    if ($result && isset($result['Create Table'])) {
                        $backupContent .= "-- Table: $tableName\n";
                        $backupContent .= $result['Create Table'] . ";\n\n";
                    }
                });

            // Get table data
            DB::module("RAW")
                ->q(function ($qb) use ($tableName) {
                    $qb->raw("SELECT * FROM `$tableName`");
                })
                ->all(function($rows) use (&$backupContent, $tableName) {
                    if (!empty($rows)) {
                        $backupContent .= "-- Data for table: $tableName\n";

                        foreach ($rows as $row) {
                            $columns = array_keys($row);
                            $values = array_map(function($value) {
                                return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                            }, array_values($row));

                            $backupContent .= "INSERT INTO `$tableName` (" .
                                implode(', ', array_map(function($col) { return "`$col`"; }, $columns)) .
                                ") VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $backupContent .= "\n";
                    }
                });
        }

        // Write backup file
        if (file_put_contents($backupFile, $backupContent)) {
            echo "✓ Database backup saved to: $backupFile\n";
            echo "Backup size: " . strlen($backupContent) . " bytes\n";
        } else {
            echo "✗ Error saving backup file\n";
        }
    });
```

### 7.2 Database Migration Script

```php
<?php
// migrate_database.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Running database migrations...\n";

$migrations = [
    [
        'version' => '001',
        'description' => 'Create users table',
        'up' => function() {
            return "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
        },
        'down' => function() {
            return "DROP TABLE IF EXISTS users";
        }
    ],
    [
        'version' => '002',
        'description' => 'Add posts table',
        'up' => function() {
            return "
                CREATE TABLE IF NOT EXISTS posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    content TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
        },
        'down' => function() {
            return "DROP TABLE IF EXISTS posts";
        }
    ]
];

$direction = getenv('DIRECTION') ?: 'up'; // 'up' or 'down'

if ($direction === 'up') {
    echo "Migrating UP...\n";
    $migrationsToRun = $migrations;
} else {
    echo "Migrating DOWN...\n";
    $migrationsToRun = array_reverse($migrations);
}

foreach ($migrationsToRun as $migration) {
    echo "Running migration {$migration['version']}: {$migration['description']}\n";

    $sql = $migration[$direction]();

    DB::module("RAW")
        ->q(function ($qb) use ($sql) {
            $qb->raw($sql);
        })
        ->execute(
            function($result) use ($migration, $direction) {
                echo "✓ Migration {$migration['version']} {$direction} completed\n";
            },
            function($error) use ($migration, $direction) {
                echo "✗ Migration {$migration['version']} {$direction} failed: $error\n";
            }
        );
}

echo "Migration process completed.\n";
```

---

## 8. Best Practices for AI

### 8.1 Choose Correct Module and Initialization

**For RAW operations** (create/drop tables, seeding):

```php
<?php
require_once 'app/config.php';  // Only config needed
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use DB::module("RAW")
DB::module("RAW")->selectDb("main")->q(/* query */)->execute();
```

**For ORM operations** (Entity, Collection, Relations):

```php
<?php
// Full framework initialization required
define('__MAINTENANCE__', FALSE);
define('__DEBUG__', FALSE);
define('__RENDER_TO_FILE__', FALSE);
define('__ROOTDIR__', __DIR__);

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DB;
// ... other use statements ...

require_once 'app/config.php';
$dotApp = DotApp::dotApp();  // Initialize framework

// Now use DB::module("ORM")
DB::module("ORM")->selectDb("main")->q(/* query */)->all();
```

### 8.2 Always Check Database Configuration

```php
// ❌ BAD - Hardcoded database name
DB::module("RAW")->selectDb("myapp_db");

// ✅ GOOD - Use internal name from config
DB::module("RAW")->selectDb("main");
```

### 8.2 Use Transactions for Multiple Operations

```php
// ✅ GOOD - Use transactions for related operations
DB::module("RAW")->q(fn($qb) => $qb->raw("START TRANSACTION"))->execute();

try {
    // Multiple operations
    DB::module("RAW")->q(fn($qb) => $qb->insert('users', $userData))->execute();
    DB::module("RAW")->q(fn($qb) => $qb->insert('user_profiles', $profileData))->execute();

    DB::module("RAW")->q(fn($qb) => $qb->raw("COMMIT"))->execute();
} catch (Exception $e) {
    DB::module("RAW")->q(fn($qb) => $qb->raw("ROLLBACK"))->execute();
}
```

### 8.3 Handle Errors Properly

```php
// ✅ GOOD - Always handle both success and error callbacks
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("CREATE TABLE test (id INT PRIMARY KEY)");
    })
    ->execute(
        function($result) {
            echo "✓ Operation successful\n";
        },
        function($error) {
            echo "✗ Operation failed: $error\n";
            // Handle error appropriately
        }
    );
```

### 8.4 Use Proper SQL Escaping

```php
// ❌ BAD - SQL injection risk
$name = $_POST['name'];
DB::module("RAW")->q(fn($qb) => $qb->raw("SELECT * FROM users WHERE name = '$name'"));

// ✅ GOOD - Use parameterized queries
$name = $_POST['name'];
DB::module("RAW")->q(fn($qb) => $qb->raw("SELECT * FROM users WHERE name = ?", [$name]));
```

### 8.5 Create Reusable Scripts

```php
// ✅ GOOD - Create reusable database utility functions

function createTableIfNotExists($tableName, $createSql) {
    return DB::module("RAW")
        ->q(function ($qb) use ($createSql) {
            $qb->raw($createSql);
        })
        ->execute(
            function($result) use ($tableName) {
                echo "✓ Table '$tableName' created or already exists\n";
            },
            function($error) use ($tableName) {
                echo "✗ Error creating table '$tableName': $error\n";
            }
        );
}

function seedTable($tableName, $data) {
    foreach ($data as $row) {
        DB::module("RAW")
            ->q(function ($qb) use ($tableName, $row) {
                $qb->insert($tableName, $row);
            })
            ->execute();
    }
}
```

---

## 9. Common Database Tasks

### 9.1 Reset Database (Drop All, Recreate, Seed)

```php
<?php
// reset_database.php - Complete database reset

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "⚠️  WARNING: This will completely reset the database!\n";
echo "All data will be lost!\n\n";

$confirm = getenv('CONFIRM_RESET') === 'yes';

if (!$confirm) {
    echo "To run this script, set: CONFIRM_RESET=yes\n";
    echo "Example: CONFIRM_RESET=yes php reset_database.php\n";
    exit(1);
}

// Step 1: Drop all tables
echo "Step 1: Dropping all tables...\n";
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SHOW TABLES");
    })
    ->all(function($tables) {
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            DB::module("RAW")
                ->q(function ($qb) use ($tableName) {
                    $qb->raw("DROP TABLE `$tableName`");
                })
                ->execute(
                    function($result) use ($tableName) {
                        echo "✓ Dropped: $tableName\n";
                    },
                    function($error) use ($tableName) {
                        echo "✗ Error dropping $tableName: $error\n";
                    }
                );
        }
    });

// Step 2: Recreate tables
echo "\nStep 2: Recreating tables...\n";
// Include your table creation scripts here
require_once 'create_all_tables.php';

// Step 3: Seed data
echo "\nStep 3: Seeding data...\n";
// Include your seeding scripts here
require_once 'seed_initial_data.php';

echo "\n✓ Database reset completed successfully!\n";
```

### 9.2 Check Database Health

```php
<?php
// check_database_health.php

require_once 'app/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Checking database health...\n";

$issues = [];

// Check connection
try {
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("SELECT 1 as test");
        })
        ->first(function($result) {
            if ($result && $result['test'] == 1) {
                echo "✓ Database connection: OK\n";
            } else {
                echo "✗ Database connection: FAILED\n";
            }
        });
} catch (Exception $e) {
    echo "✗ Database connection: ERROR - " . $e->getMessage() . "\n";
    exit(1);
}

// Check required tables
$requiredTables = ['users', 'posts', 'categories'];
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SHOW TABLES");
    })
    ->all(function($tables) use ($requiredTables, &$issues) {
        $existingTables = array_map(function($table) {
            return array_values($table)[0];
        }, $tables);

        foreach ($requiredTables as $requiredTable) {
            if (!in_array($requiredTable, $existingTables)) {
                $issues[] = "Missing table: $requiredTable";
            }
        }

        if (empty($issues)) {
            echo "✓ Required tables: All present\n";
        } else {
            echo "✗ Required tables: Missing " . count($issues) . " tables\n";
            foreach ($issues as $issue) {
                echo "  - $issue\n";
            }
        }
    });

// Check data integrity
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SELECT COUNT(*) as user_count FROM users");
    })
    ->first(function($result) {
        $userCount = $result['user_count'] ?? 0;
        echo "✓ Users in database: $userCount\n";
    });

if (empty($issues)) {
    echo "\n✓ Database health check: PASSED\n";
} else {
    echo "\n✗ Database health check: ISSUES FOUND\n";
    exit(1);
}
```

---

## 10. Quick Reference

### 10.1 Essential Script Templates

**Check if table exists:**

```php
DB::module("RAW")->q(fn($qb) => $qb->raw("SHOW TABLES LIKE ?", [$tableName]))->all(...)
```

**Create table:**

```php
DB::module("RAW")->q(fn($qb) => $qb->raw($createSql))->execute($success, $error)
```

**Drop table:**

```php
DB::module("RAW")->q(fn($qb) => $qb->raw("DROP TABLE `$tableName`"))->execute(...)
```

**Insert data:**

```php
DB::module("RAW")->q(fn($qb) => $qb->insert($tableName, $data))->execute(...)
```

**Select data:**

```php
DB::module("RAW")->q(fn($qb) => $qb->raw("SELECT * FROM `$tableName`"))->all(...)
```

**Transaction:**

```php
DB::module("RAW")->q(fn($qb) => $qb->raw("START TRANSACTION"))->execute();
// ... operations ...
DB::module("RAW")->q(fn($qb) => $qb->raw("COMMIT"))->execute();
```

**Always remember:**

- **RAW operations**: `require_once 'app/config.php'` + `DB::module("RAW")`
- **ORM operations**: Full framework init + `DB::module("ORM")`
- Use internal database names from `app/config.php`
- Handle both success and error callbacks
- Use parameterized queries to prevent SQL injection
- Use transactions for related operations
- Create standalone PHP files for database operations

---

## 11. ORM Testing and Operations

### 11.1 Full Framework Initialization for ORM

**⚠️ CRITICAL:** For ORM operations (Entity, Collection, Relations), you MUST initialize the full DotApp framework. This is different from RAW operations!

```php
<?php
// orm_test.php - Complete ORM testing script with full framework initialization

// Set error reporting FIRST
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate HTTP request for proper framework initialization (CLI workaround)
$_SERVER['REQUEST_URI'] = '/'.md5(random_bytes(10))."/".md5(random_bytes(10))."/".md5(random_bytes(10));
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET'; // Use standard HTTP method
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Define required constants for DotApp framework
define('__MAINTENANCE__', FALSE);
define('__DEBUG__', FALSE);
define('__RENDER_TO_FILE__', FALSE);
define('__ROOTDIR__', __DIR__);

// Use statements for all needed classes
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Response;
use \Dotsystems\App\Parts\Renderer;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DB;
use \Dotsystems\App\Parts\Translator;
use \Dotsystems\App\Parts\Request;
use \Dotsystems\App\Parts\Entity;
use \Dotsystems\App\Parts\Collection;

// Include framework config (initializes database connections)
require_once 'app/config.php';

echo "Framework config loaded, initializing DotApp...\n";

// PROPER FRAMEWORK INITIALIZATION FOR ORM
$dotApp = DotApp::dotApp();
// Note: config.php already calls load_modules(), no need to call again

echo "Framework fully initialized!\n";

echo "\n=== ORM TESTING STARTED ===\n";

// 1. Test basic ORM SELECT
echo "1. Testing basic ORM SELECT...\n";

$products = DB::module("ORM")
    ->selectDb('main')  // Use database 'main' as configured
    ->q(function($qb) {
        $qb->select(['id', 'nazov', 'sukl_kod', 'atc_kod'])
           ->from('lieky')
           ->where('sukl_status', '=', 1)
           ->limit(5);
    })
    ->all();

echo "✅ Loaded " . count($products) . " products via ORM\n";
echo "Result type: " . gettype($products) . "\n";
echo "Is Collection? " . ($products instanceof Collection ? "YES" : "NO") . "\n";

if (count($products) > 0) {
    echo "First product: " . $products[0]->nazov . " (ID: " . $products[0]->id . ")\n";

    // Test Entity methods
    echo "Testing isDirty(): " . ($products[0]->isDirty() ? "YES" : "NO") . "\n";

    // Simulate change
    $oldNazov = $products[0]->nazov;
    $products[0]->nazov = $oldNazov . ' (test)';
    echo "After change isDirty('nazov'): " . ($products[0]->isDirty('nazov') ? "YES" : "NO") . "\n";
    echo "getDirty() fields: " . implode(', ', array_keys($products[0]->getDirty())) . "\n";

    // Reset
    $products[0]->nazov = $oldNazov;
}

// 2. Test WHERE conditions
echo "\n2. Testing ORM with WHERE conditions...\n";

$activeProducts = DB::module("ORM")
    ->selectDb('main')
    ->q(function($qb) {
        $qb->select(['id', 'nazov', 'sukl_kod'])
           ->from('lieky')
           ->where('sukl_status', '=', 1)
           ->limit(10);
    })
    ->all();

echo "✅ Loaded " . count($activeProducts) . " active products\n";

// 3. Test Collection methods
echo "\n3. Testing Collection methods...\n";

$sortedProducts = $activeProducts->sortBy('nazov');
echo "sortBy('nazov'): first = '" . $sortedProducts[0]->nazov . "'\n";

$withAtc = $activeProducts->filter(function($item) {
    return !empty($item->atc_kod);
});
echo "filter(atc_kod): " . count($withAtc) . " products with ATC code\n";

$namesOnly = $activeProducts->map(function($product) {
    return $product->nazov;
});
echo "map() to names: " . count($namesOnly) . " items\n";

$searched = $activeProducts->search('nazov', 'paralen');
echo "search('nazov', 'paralen'): " . count($searched) . " results\n";

// 4. Test single Entity
echo "\n4. Testing single Entity (first)...\n";

$singleProduct = DB::module("ORM")
    ->selectDb('main')
    ->q(function($qb) {
        $qb->select(['id', 'nazov', 'sukl_kod'])
           ->from('lieky')
           ->where('sukl_status', '=', 1)
           ->limit(1);
    })
    ->first();

if ($singleProduct) {
    echo "✅ Loaded single product: " . $singleProduct->nazov . "\n";
    echo "Type: " . gettype($singleProduct) . "\n";
    echo "Is Entity? " . ($singleProduct instanceof Entity ? "YES" : "NO") . "\n";
    echo "toArray() has " . count($singleProduct->toArray()) . " items\n";
} else {
    echo "❌ No product found\n";
}

// 5. Test with relations (if available)
echo "\n5. Testing with relations...\n";

$productsWithRelations = DB::module("ORM")
    ->selectDb('main')
    ->q(function($qb) {
        $qb->select('*')
           ->from('lieky')
           ->where('sukl_status', '=', 1)
           ->limit(3);
    })
    ->with(['categories']) // If relation exists
    ->all();

echo "✅ Loaded products with relations: " . count($productsWithRelations) . "\n";

echo "\n=== ORM TESTING COMPLETED ===\n";
echo "🎉 ORM model works perfectly with full framework!\n";
echo "Framework is properly initialized and DB::module('ORM') works!\n";
```

### 11.2 Why Full Framework Initialization?

**RAW operations** (create/drop tables, seeding) only need `app/config.php` because they directly use database drivers.

**ORM operations** need full DotApp initialization because:

- Entity and Collection classes need dependency injection
- Relations and eager loading need the full framework context
- Events and observers need the framework event system
- Validation rules need translator and other services

### 11.3 ORM vs RAW Usage Guidelines

| Operation Type            | Module              | Initialization                  | Use Case                    |
| ------------------------- | ------------------- | ------------------------------- | --------------------------- |
| Create/Drop Tables        | `DB::module("RAW")` | `require_once 'app/config.php'` | Schema management           |
| Seed Data                 | `DB::module("RAW")` | `require_once 'app/config.php'` | Data population             |
| SELECT/INSERT/UPDATE      | `DB::module("ORM")` | Full framework init             | Business logic with objects |
| Relations & Eager Loading | `DB::module("ORM")` | Full framework init             | Complex data relationships  |
| Entity Validation         | `DB::module("ORM")` | Full framework init             | Data validation with rules  |

### 11.4 Common ORM Testing Patterns

```php
// Pattern 1: Basic CRUD testing
$entity = DB::module("ORM")->selectDb('main')->q(/* query */)->first();
$entity->some_field = 'new value';
$entity->save();

// Pattern 2: Collection operations
$collection = DB::module("ORM")->selectDb('main')->q(/* query */)->all();
$filtered = $collection->filter(/* condition */);
$sorted = $filtered->sortBy('field');

// Pattern 3: Relations
$entity = DB::module("ORM")->selectDb('main')->q(/* query */)->with(['relation'])->first();
$related = $entity->relation; // Lazy loaded or eager loaded

// Pattern 4: Bulk operations
$entities = DB::module("ORM")->selectDb('main')->q(/* query */)->all();
$entities->each(function($entity) {
    $entity->status = 'updated';
    $entity->save();
});
```

---

## ⚠️ CRITICAL: User Consent for Data and File Operations

**AI assistants MUST follow strict rules about when to ask for user permission and when NOT to auto-delete anything.**

### 📊 Database Data Deletion Rules

#### ✅ ALWAYS Ask User Before Deleting:

- **Test data created during testing** (sample records, dummy data)
- **Test tables created for demonstration** (unless user explicitly requested them)
- **Any data modifications** made during testing/development

#### ❌ NEVER Auto-Delete:

- **Tables created at user's explicit request** - Let user decide when to delete
- **Production data** - Never touch without explicit confirmation
- **User's existing data** - Always ask first

### 📁 PHP Script File Deletion Rules

#### ✅ ALWAYS Ask User Before Deleting PHP Scripts:

**AI MUST ALWAYS ask if user wants to delete ANY PHP files created for database communication, even test files!**

```php
// ❌ BAD - Never auto-delete PHP files
unlink('test_db_script.php'); // NEVER DO THIS!

// ✅ GOOD - Always ask first
echo "🗑️  Delete the test PHP script file 'test_db_script.php'? (y/n): ";
$answer = trim(fgets(STDIN));
if (strtolower($answer) === 'y') {
    unlink('test_db_script.php');
    echo "✓ Test script deleted\n";
} else {
    echo "ℹ️  Test script kept for reference\n";
}
```

### 🏷️ Specific Scenarios and Rules

#### Scenario 1: User asks for table creation + testing

```
User: "Create a users table for me"
AI: Creates table → Tests it → Asks: "Delete test data? (y/n)"
```

**→ AI asks about test data cleanup**

#### Scenario 2: User asks for table creation only

```
User: "Create a users table for my app"
AI: Creates table → User uses it → Later user asks to delete
```

**→ AI does NOT auto-delete, waits for user's explicit delete request**

#### Scenario 3: AI suggests table creation during conversation

```
AI: "I can create a test table to show you how it works"
AI: Creates table → Tests it → Asks: "Delete this test table? (y/n)"
```

**→ AI asks about test table cleanup**

### 💡 Best Practices for AI Behavior

#### Database Operations:

```php
// ✅ GOOD - Ask about test data
echo "✓ Test table created successfully\n";
echo "🗑️  Delete this test table? (y/n): ";
$answer = askUser();
if ($answer === 'y') {
    dropTable();
}

// ✅ GOOD - Don't touch user-requested tables
echo "✓ Table 'users' created as requested\n";
// DON'T ask to delete - user will ask when ready
```

#### File Operations:

```php
// ✅ GOOD - Always ask about PHP files
echo "✓ Created script 'migrate_users.php'\n";
echo "🗑️  Delete this PHP script file? (y/n): ";
$answer = askUser();
if ($answer === 'y') {
    unlink('migrate_users.php');
}
```

### 🚨 Critical Safety Rules

1. **Never auto-delete PHP files** - Always ask user first
2. **Never auto-delete user-requested tables** - Wait for explicit delete request
3. **Always ask about test data cleanup** - Give user control
4. **Preserve user's work** - Don't delete what they might want to keep
5. **Document cleanup commands** - Show user how to manually delete if they prefer

### 📝 Example User Interaction

```php
// GOOD AI behavior:
echo "✓ Created test table 'demo_users' with sample data\n";
echo "📊 Contains 10 sample user records\n";
echo "🗑️  Delete this test table and data? (y/n): ";

$answer = getUserInput();
if ($answer === 'y') {
    dropTable('demo_users');
    echo "✓ Test table deleted\n";
} else {
    echo "ℹ️  Test table kept. You can delete it later with: DROP TABLE demo_users;\n";
}

// For PHP files:
echo "✓ Created migration script 'create_users_table.php'\n";
echo "🗑️  Delete this PHP script file? (y/n): ";

$answer = getUserInput();
if ($answer === 'y') {
    unlink('create_users_table.php');
    echo "✓ Script file deleted\n";
} else {
    echo "ℹ️  Script file kept for your reference\n";
}
```

**Remember: User control and safety first! Always ask, never assume!**

---

_This guide ensures AI assistants can properly work with DotApp databases using the correct framework patterns and best practices._

---

## 11. Creating Safe PHP Scripts for Database Interaction

### 🎯 Purpose: AI-Generated Scripts for Programming Tasks

**This section teaches AI assistants how to create PHP scripts when they need database data for programming tasks**, such as:

- **Analyzing database structure** (table schemas, relationships, constraints)
- **Extracting sample data** for testing and development
- **Validating data integrity** and consistency
- **Generating code based on real database content**
- **Testing ORM functionality** with actual data
- **Debugging database issues** during development
- **Creating data fixtures** for testing environments
- **Performing data migrations** or transformations

**AI assistants should use these safe script templates whenever they need to interact with the database during programming or debugging tasks.**

### 📊 Common Use Cases for AI-Generated Database Scripts:

#### **1. Database Schema Analysis:**

```php
// AI needs to understand table structure for code generation
$tables = DB::module("RAW")
    ->q(function($qb) {
        $qb->raw("SHOW TABLES");
    })
    ->all();

foreach ($tables as $table) {
    $columns = DB::module("RAW")
        ->q(function($qb) use ($table) {
            $qb->raw("DESCRIBE " . $table->Tables_in_database);
        })
        ->all();
    // Analyze columns, types, constraints for code generation
}
```

#### **2. Sample Data Extraction:**

```php
// AI needs sample data to understand data patterns
$sampleUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->limit(5);
    })
    ->all();
// Use this data to understand field formats, relationships, etc.
```

#### **3. Relationship Mapping:**

```php
// AI needs to understand how tables relate for ORM code
$userPosts = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->with(['posts', 'profile'])
    ->first();
// Analyze relationship structure for generating proper ORM code
```

#### **4. Data Validation Testing:**

```php
// AI needs to test validation rules with real data
$invalidUsers = DB::module("RAW")
    ->q(function($qb) {
        $qb->raw("SELECT * FROM users WHERE email NOT LIKE '%@%' LIMIT 10");
    })
    ->all();
// Identify data quality issues for improvement suggestions
```

### 🤖 When AI Should Create Database Scripts:

#### **✅ Use Scripts When:**

- **Analyzing unknown database structure** for the first time
- **Understanding data relationships** and foreign keys
- **Extracting sample data** for code examples or testing
- **Validating ORM functionality** with real data
- **Debugging database-related issues** in applications
- **Generating reports** about database content or structure
- **Creating data fixtures** for development/testing
- **Performing one-time data analysis** tasks

#### **❌ Don't Use Scripts For:**

- **Production data manipulation** (use application code instead)
- **Regular application features** (implement in controllers/models)
- **Automated tasks** (use proper job queues/cron jobs)
- **API endpoints** (create proper REST APIs instead)
- **Real-time user interactions** (handle through application UI)

#### **💡 AI Workflow:**

1. **Analyze the task** - Determine if database script is needed
2. **Choose safe template** - Use the provided secure templates
3. **Get user confirmation** - Always ask before executing
4. **Execute safely** - Run with proper error handling
5. **Provide results** - Use extracted data for programming tasks
6. **Clean up** - Ensure script is safe for reuse

### 🔧 Practical Example - AI Analyzing Database for Code Generation:

**Scenario:** AI needs to understand user table structure to generate proper Entity code.

**AI Thought Process:**

> "I need to see the actual database structure to generate correct Entity code with proper relationships and validation rules."

**Generated Safe Script:**

```php
<?php
// AI-GENERATED: Database Analysis for Code Generation
// Purpose: Analyze users table structure for Entity code generation

// [FULL SAFE TEMPLATE HEADER...]

// Analyze table structure
echo "[ANALYSIS] Analyzing users table structure...\n";

$columns = DB::module("RAW")
    ->q(function($qb) {
        $qb->raw("DESCRIBE users");
    })
    ->all();

echo "Found " . count($columns) . " columns:\n";
foreach ($columns as $col) {
    echo "  - {$col->Field}: {$col->Type} " . ($col->Key ? "[{$col->Key}]" : "") . "\n";
}

// Check for relationships
$foreignKeys = DB::module("RAW")
    ->q(function($qb) {
        $qb->raw("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'users' AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
    })
    ->all();

if (count($foreignKeys) > 0) {
    echo "\nForeign Key Relationships:\n";
    foreach ($foreignKeys as $fk) {
        echo "  - {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}({$fk->REFERENCED_COLUMN_NAME})\n";
    }
}

// Sample data for validation rules
$sampleData = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->limit(3);
    })
    ->all();

echo "\nSample Data Analysis:\n";
echo "Email formats: " . implode(', ', array_column($sampleData, 'email')) . "\n";
echo "Age range: " . min(array_column($sampleData, 'age')) . " - " . max(array_column($sampleData, 'age')) . "\n";

// [TEMPLATE FOOTER...]
?>
```

**AI Uses Results For:**

- Generating proper Entity classes with correct field types
- Creating appropriate validation rules based on data patterns
- Setting up correct relationships in ORM models
- Writing proper database migration scripts

### ⚠️ CRITICAL SECURITY REQUIREMENTS

When creating PHP scripts that interact with databases, **ALWAYS follow these mandatory security protocols**:

### **MANDATORY: User Confirmation Required**

```php
// ❌ NEVER execute database operations without user confirmation
$userConfirmed = readline("Are you sure you want to DELETE all users? (type 'YES' to confirm): ");
if ($userConfirmed !== 'YES') {
    die("Operation cancelled by user.\n");
}
```

### **MANDATORY: Operation Logging**

```php
// ❌ NEVER perform operations without logging
echo "[INFO] Starting database operation: " . date('Y-m-d H:i:s') . "\n";
// ... perform operation ...
echo "[SUCCESS] Operation completed successfully\n";
```

### **MANDATORY: Error Handling**

```php
try {
    // database operations
} catch (Exception $e) {
    echo "[ERROR] Operation failed: " . $e->getMessage() . "\n";
    exit(1);
}
```

---

### 11.1 Basic PHP Script Template

**ALWAYS use this exact template structure:**

```php
<?php
/**
 * SAFE DATABASE INTERACTION SCRIPT
 * Generated by AI Assistant for DotApp Framework
 *
 * @version   1.8
 * @security  User confirmation required
 * @logging   All operations logged
 */

// =================================================================
// SECURITY & CONFIGURATION SECTION - DO NOT MODIFY
// =================================================================

// Error reporting for development
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 1);

// Define required constants
define('__MAINTENANCE__', FALSE);
define('__DEBUG__', TRUE);           // Enable for debugging
define('__RENDER_TO_FILE__', FALSE);
define('__ROOTDIR__', __DIR__);

// Simulate HTTP environment for framework compatibility
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/script';
$_SERVER['HTTP_HOST'] = 'localhost';

// =================================================================
// REQUIRED INCLUDES - DO NOT MODIFY
// =================================================================

// Framework includes
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Response;
use \Dotsystems\App\Parts\Renderer;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DB;
use \Dotsystems\App\Parts\Translator;
use \Dotsystems\App\Parts\Request;

// Load framework configuration
require_once 'app/config.php';

// =================================================================
// SECURITY CONFIRMATION - MANDATORY
// =================================================================

echo "=== DOTAPP DATABASE SCRIPT ===\n";
echo "Framework Version: 1.8\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// CRITICAL: Always ask for user confirmation before any database operations
$userConfirmed = readline("⚠️  This script will perform database operations.\nContinue? (type 'YES' to confirm): ");

if ($userConfirmed !== 'YES') {
    echo "[CANCELLED] Operation cancelled by user.\n";
    exit(0);
}

echo "[CONFIRMED] User confirmed operation. Proceeding...\n\n";

// =================================================================
// YOUR SCRIPT CODE GOES HERE
// =================================================================

// Initialize framework
$dotApp = DotApp::dotApp();
echo "[INIT] Framework initialized successfully\n";

// Example: Safe database operation
try {
    echo "[START] Performing database operation...\n";

    // Your database code here
    $users = DB::module("ORM")
        ->selectDb('main')
        ->q(function ($qb) {
            $qb->select('COUNT(*) as count')->from('users');
        })
        ->first();

    echo "[SUCCESS] Found " . $users->count . " users in database\n";

} catch (Exception $e) {
    echo "[ERROR] Database operation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// =================================================================
// CLEANUP & LOGGING - MANDATORY
// =================================================================

echo "\n[COMPLETE] Script execution finished successfully\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

?>
```

---

### 11.2 Script Creation Rules for AI

#### **MANDATORY RULES:**

1. **ALWAYS ask for user confirmation** before ANY database operation
2. **ALWAYS log ALL operations** with timestamps
3. **ALWAYS include proper error handling** with try/catch
4. **NEVER execute destructive operations** without explicit confirmation
5. **ALWAYS show what will be affected** before asking confirmation
6. **ALWAYS provide operation summary** after completion

#### **PROHIBITED PATTERNS:**

```php
// ❌ NEVER - No user confirmation
DB::module("RAW")->q(function($qb) {
    $qb->raw("DELETE FROM users WHERE 1=1");
})->execute();

// ❌ NEVER - No error handling
$users = DB::module("ORM")->selectDb('main')->q(function($qb) {
    $qb->select('*')->from('users');
})->all();

// ❌ NEVER - No logging
$user->delete();
```

#### **REQUIRED PATTERNS:**

```php
// ✅ ALWAYS - Safe pattern with confirmation
echo "This will delete " . $userCount . " inactive users.\n";
$confirm = readline("Continue? (type 'YES'): ");
if ($confirm !== 'YES') die("Cancelled\n");

try {
    echo "[START] Deleting inactive users...\n";
    // ... operation ...
    echo "[SUCCESS] Deleted " . $deletedCount . " users\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
```

---

### 11.3 Example Scripts for Common Operations

#### **READ Operation (Safe):**

```php
<?php
// ... [FULL TEMPLATE HEADER FROM ABOVE] ...

try {
    echo "[START] Reading user statistics...\n";

    $stats = DB::module("ORM")
        ->selectDb('main')
        ->q(function ($qb) {
            $qb->select('COUNT(*) as total, AVG(age) as avg_age')
               ->from('users')
               ->where('active', '=', 1);
        })
        ->first();

    echo "[SUCCESS] Total active users: " . $stats->total . "\n";
    echo "[SUCCESS] Average age: " . round($stats->avg_age, 1) . " years\n";

} catch (Exception $e) {
    echo "[ERROR] Failed to read statistics: " . $e->getMessage() . "\n";
    exit(1);
}

// ... [TEMPLATE FOOTER] ...
?>
```

#### **WRITE Operation (Requires Confirmation):**

```php
<?php
// ... [FULL TEMPLATE HEADER FROM ABOVE] ...

// Check what will be affected
$inactiveCount = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('COUNT(*) as count')->from('users')
           ->where('last_login', '<', date('Y-m-d H:i:s', strtotime('-1 year')));
    })
    ->first()->count;

echo "⚠️  This will deactivate " . $inactiveCount . " users inactive for 1+ year.\n";
$confirm = readline("Continue? (type 'YES' to confirm): ");

if ($confirm !== 'YES') {
    echo "[CANCELLED] Operation cancelled by user.\n";
    exit(0);
}

try {
    echo "[START] Deactivating inactive users...\n";

    $affected = DB::module("RAW")
        ->q(function($qb) {
            $qb->raw("UPDATE users SET active = 0 WHERE last_login < ?",
                    [date('Y-m-d H:i:s', strtotime('-1 year'))]);
        })
        ->execute();

    echo "[SUCCESS] Deactivated " . $affected['affected_rows'] . " users\n";

} catch (Exception $e) {
    echo "[ERROR] Failed to deactivate users: " . $e->getMessage() . "\n";
    exit(1);
}

// ... [TEMPLATE FOOTER] ...
?>
```

#### **DELETE Operation (Maximum Caution):**

```php
<?php
// ... [FULL TEMPLATE HEADER FROM ABOVE] ...

// Multiple confirmations for destructive operations
$userId = readline("Enter user ID to delete: ");
$userId = trim($userId);

if (!is_numeric($userId)) {
    echo "[ERROR] Invalid user ID\n";
    exit(1);
}

// Verify user exists
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) use ($userId) {
        $qb->select('username, email, created_at')->from('users')
           ->where('id', '=', $userId);
    })
    ->first();

if (!$user) {
    echo "[ERROR] User with ID $userId not found\n";
    exit(1);
}

echo "\n⚠️  DESTRUCTIVE OPERATION WARNING ⚠️\n";
echo "Will permanently delete user:\n";
echo "  ID: $userId\n";
echo "  Username: " . $user->username . "\n";
echo "  Email: " . $user->email . "\n";
echo "  Created: " . $user->created_at . "\n\n";

$confirm1 = readline("Type 'DELETE' to confirm: ");
$confirm2 = readline("Type the username '" . $user->username . "' to confirm: ");

if ($confirm1 !== 'DELETE' || $confirm2 !== $user->username) {
    echo "[CANCELLED] Operation cancelled - invalid confirmation\n";
    exit(0);
}

try {
    echo "[START] Deleting user $userId...\n";

    $result = DB::module("RAW")
        ->q(function($qb) use ($userId) {
            $qb->raw("DELETE FROM users WHERE id = ?", [$userId]);
        })
        ->execute();

    if ($result['affected_rows'] > 0) {
        echo "[SUCCESS] User $userId deleted successfully\n";
    } else {
        echo "[WARNING] No users were deleted (user may not exist)\n";
    }

} catch (Exception $e) {
    echo "[ERROR] Failed to delete user: " . $e->getMessage() . "\n";
    exit(1);
}

// ... [TEMPLATE FOOTER] ...
?>
```

---

### 11.4 AI Assistant Guidelines

#### **When creating database scripts, ALWAYS:**

1. **Start with full template** - Copy the complete safe template
2. **Identify operation type** - READ, WRITE, or DELETE
3. **Add appropriate confirmations** - More confirmations for destructive operations
4. **Show impact preview** - Display what will be affected
5. **Log everything** - Timestamp all operations and results
6. **Handle errors gracefully** - Never crash without explanation
7. **Clean up** - Ensure script ends cleanly

#### **Security Checklist:**

- [ ] User confirmation required for ALL operations
- [ ] Multiple confirmations for DELETE operations
- [ ] Impact preview shown before confirmation
- [ ] Comprehensive error handling with try/catch
- [ ] All operations logged with timestamps
- [ ] Script provides clear success/failure feedback
- [ ] No hardcoded sensitive data
- [ ] Input validation for user-provided data

#### **Example AI Response:**

> I'll create a safe PHP script to update user profiles. Here's the script with full security measures:
>
> ```php
> <?php
> /**
>  * SAFE DATABASE SCRIPT - Update User Profiles
>  * Generated by AI Assistant
>  * @security User confirmation required
>  */
>
> // [FULL TEMPLATE CODE]
>
> // Check what will be affected
> $count = DB::module("ORM")->selectDb('main')->q(function($qb) {
>     $qb->select('COUNT(*) as count')->from('users')
>        ->where('profile_updated', '=', 0);
> })->first()->count;
>
> echo "This will update $count user profiles.\n";
> $confirm = readline("Continue? (YES): ");
> if ($confirm !== 'YES') die("Cancelled\n");
>
> // [SCRIPT CODE]
> ?>
> ```

---

_This ensures AI-generated database scripts are always safe, logged, and user-controlled._
