# DotApp PHP Framework - Database Module Guide

## Overview

**DotApp PHP Framework** is a modular PHP framework where database operations are handled through the **Database Module**. All database operations use the `DB::module()` method to specify which module to use.

**⚠️ CRITICAL:** All database operations must use `DB::module("RAW")` or `DB::module("ORM")`. DotApp is **modular by design**.

The framework supports two main approaches for database interactions:

- **`DB::module("RAW")`** - Direct SQL commands with parameters (bypasses ORM)
- **`DB::module("ORM")`** - Object-Relational Mapping with Entity and Collection classes

The framework supports these database systems:

- **MySQL/MariaDB** (`mysql`) - Most common choice for web applications
- **PostgreSQL** (`pgsql`) - Advanced open-source database with rich features
- **SQLite** (`sqlite`) - Lightweight file-based database, perfect for development/testing
- **Microsoft SQL Server** (`sqlsrv`) - Enterprise-grade database for Windows environments
- **Oracle Database** (`oci`) - High-performance enterprise database

---

## 1. Database Configuration

### 1.1 Why Modular Architecture? Understanding DB::module()

**DotApp PHP Framework uses a modular approach** because database operations are complex and require different strategies:

#### Why DB::module()?

- **`DB::module("RAW")`** - Direct SQL queries with QueryBuilder (performance, complex queries)
- **`DB::module("ORM")`** - Object-Relational Mapping (developer productivity, relationships)
- **Future modules** - Could add "CACHE", "ANALYTICS", etc.

#### Driver Binding

**Databases are bound to specific drivers:**

```php
// mysqli driver database (RAW module only)
Config::addDatabase("legacy", "localhost", "user", "pass", "myapp", "UTF8", "mysql", "mysqli");

// PDO driver database (ORM module)
Config::addDatabase("modern", "localhost", "user", "pass", "myapp", "UTF8", "mysql", "pdo");
```

#### Internal vs External Names

```php
Config::addDatabase("production", "prod-server.com", "user", "pass", "myapp_prod_db", "UTF8", "pgsql", "pdo");
// "production" = DotApp internal name
// "myapp_prod_db" = actual database name on server

// Usage:
DB::module("ORM")->selectDb("production");  // Uses "myapp_prod_db"
```

### 1.2 Basic Configuration

```php
// app/config.php - MySQL Example
Config::addDatabase("main", "localhost", "root", "", "myapp_db", "utf8mb4", "mysql", "pdo");
// "main" = DotApp internal name
// "myapp_db" = actual database name on server

// Usage: Reference by internal name
DB::module("ORM")->selectDb("main");    // Uses "myapp_db" database

// PostgreSQL Example
Config::addDatabase("pg_main", "localhost", "postgres", "password", "myapp_db", "UTF8", "pgsql", "pdo");

// SQLite Example (file-based, no host/username/password needed)
Config::addDatabase("sqlite_main", "", "", "", "/path/to/database.sqlite", "", "sqlite", "pdo");

// Microsoft SQL Server Example
Config::addDatabase("mssql_main", "localhost", "sa", "password", "myapp_db", "UTF8", "sqlsrv", "pdo");

// Oracle Database Example
Config::addDatabase("oracle_main", "localhost", "user", "password", "ORCL", "AL32UTF8", "oci", "pdo");
// SID or Service Name = "ORCL"
```

### 1.2 Database Selection

In DotApp, databases are identified by **internal names** (not actual database names on the server). These internal names are used throughout the framework:

```php
// Select active database by internal name
DB::select_db("main"); // Selects the database configured with name "main"

// Switch to different database
DB::select_db("reporting"); // Switch to reporting database
DB::select_db("archive");   // Switch to archive database

// Check current connection
$connection = DB::getConnection();
```

**Important:** The name `"main"` is an **internal identifier** within DotApp framework, not the actual database name on your MySQL/PostgreSQL server. The actual database name is specified in the configuration.

### 1.3 PHP Extensions Requirements

Each database type requires specific PHP extensions:

- **MySQL/MariaDB** (`mysql`): `pdo` and/or `mysqli` extensions
- **PostgreSQL** (`pgsql`): `pdo` extension
- **SQLite** (`sqlite`): `pdo` extension (usually included)
- **Microsoft SQL Server** (`sqlsrv`): `pdo` extension
- **Oracle Database** (`oci`): `pdo` extension

**Installation commands:**

```bash
# Ubuntu/Debian
sudo apt-get install php-mysql php-pgsql php-sqlite3 php-sqlsrv php-oci8

# CentOS/RHEL
sudo yum install php-mysql php-pgsql php-sqlite3 php-sqlsrv php-oci8

# macOS (with Homebrew)
brew install php

# Windows - download from https://windows.php.net/
# Or use XAMPP/WAMP which includes most extensions
```

### 1.4 Driver-Based Database Architecture

**Critical Concept:** Databases in DotApp are **bound to specific drivers**. This means:

- A database configured for `mysqli` driver uses MySQLi extension
- A database configured for `pdo` driver uses PDO extension
- **ORM is driver-agnostic** - it works with any configured driver (mysqli, PDO, or custom)
- Each driver maintains its own connection pool

#### Example:

```php
// This configures a MySQL database accessible via mysqli driver
Config::addDatabase("legacy_db", "localhost", "user", "pass", "myapp", "UTF8", "mysql", "mysqli");

// This configures a different MySQL database accessible via PDO
Config::addDatabase("modern_db", "localhost", "user", "pass", "myapp2", "UTF8", "mysql", "pdo");

// Usage - ORM works with any driver:
DB::module("ORM")->selectDb("legacy_db");  // Uses mysqli driver
DB::module("ORM")->selectDb("modern_db");  // Uses PDO driver
DB::module("RAW")->selectDb("legacy_db");  // Uses mysqli driver
DB::module("RAW")->selectDb("modern_db");  // Uses PDO driver
```

### 1.5 Database-Specific Features and Limitations

#### MySQL/MariaDB

- ✅ Full UTF8MB4 support
- ✅ Auto-increment primary keys
- ✅ Foreign key constraints
- ✅ Full-text search
- ✅ JSON columns (MySQL 5.7.8+)
- ✅ Spatial data types

#### PostgreSQL

- ✅ Advanced JSON/JSONB support
- ✅ Array data types
- ✅ Full-text search with tsvector
- ✅ Advanced indexing (GIN, GiST)
- ✅ Custom data types
- ✅ Table inheritance

#### SQLite

- ✅ Zero configuration
- ✅ Single file database
- ✅ ACID transactions
- ✅ Full-text search (FTS5)
- ✅ JSON support (SQLite 3.9.0+)
- ❌ No concurrent write access
- ❌ Limited ALTER TABLE

#### Microsoft SQL Server

- ✅ Advanced security features
- ✅ Full-text search
- ✅ Spatial data types
- ✅ Always Encrypted
- ✅ In-memory OLTP
- ✅ Columnstore indexes

#### Oracle Database

- ✅ Enterprise-grade performance
- ✅ Advanced partitioning
- ✅ Real Application Clusters (RAC)
- ✅ Advanced security (Oracle Advanced Security)
- ✅ Spatial and Graph data
- ✅ In-memory column store

### 1.5 SQL Syntax Compatibility

DotApp QueryBuilder automatically handles SQL syntax differences between databases:

#### Auto-increment Columns

```php
// MySQL/PostgreSQL/SQL Server
INSERT INTO users (name) VALUES ('John'); -- Auto ID: 1, 2, 3...

// Oracle (requires sequence)
-- QueryBuilder handles this automatically
```

#### LIMIT/OFFSET

```php
// MySQL/PostgreSQL/SQLite
SELECT * FROM users LIMIT 10 OFFSET 20;

// SQL Server
SELECT * FROM users ORDER BY id OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY;

// Oracle
SELECT * FROM users OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY;

// QueryBuilder handles these differences automatically
$query->limit(10)->offset(20);
```

#### String Concatenation

```php
// MySQL/PostgreSQL/SQLite
SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users;

// SQL Server
SELECT first_name + ' ' + last_name as full_name FROM users;

// Oracle
SELECT first_name || ' ' || last_name as full_name FROM users;

// QueryBuilder provides cross-database CONCAT function
$query->selectRaw("CONCAT(first_name, ' ', last_name) as full_name");
```

#### Boolean Values

```php
// MySQL/PostgreSQL
WHERE active = true;

// SQL Server/SQLite
WHERE active = 1;

// Oracle
WHERE active = 'Y';

// QueryBuilder handles boolean conversion automatically
$query->where('active', '=', true);
```

#### Date Functions

```php
// MySQL
SELECT NOW(), DATE_FORMAT(created_at, '%Y-%m-%d') FROM users;

// PostgreSQL
SELECT NOW(), TO_CHAR(created_at, 'YYYY-MM-DD') FROM users;

// SQL Server
SELECT GETDATE(), CONVERT(VARCHAR, created_at, 23) FROM users;

// Oracle
SELECT SYSDATE, TO_CHAR(created_at, 'YYYY-MM-DD') FROM users;

// Use PHP date functions for cross-database compatibility
$query->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as date");
```

---

## 2. RAW Queries

RAW queries provide direct access to SQL with automatic parameter escaping.

### 2.1 Basic RAW Query

```php
$result = DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SELECT * FROM users WHERE active = ?", [1]);
    })
    ->all(); // Returns array of results

// Or with callback
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SELECT * FROM users WHERE id = ?", [$userId]);
    })
    ->execute(
        function($result) {
            // Success callback - results are in $result
            echo "Found " . count($result) . " users";
        },
        function($error) {
            // Error callback - error in $error
            echo "Error: " . $error;
        }
    );
```

### 2.2 CRUD Operations with RAW

#### INSERT

```php
DB::module("RAW")
    ->q(function ($qb) {
        $qb->insert('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    })
    ->execute(
        function($result, $db, $execution_data) {
            $newId = $execution_data['insert_id'];
            echo "Created user with ID: $newId";
        }
    );
```

#### UPDATE

```php
DB::module("RAW")
    ->q(function ($qb) {
        $qb->update('users')
           ->set(['name' => 'John Smith'])
           ->where('id', '=', 1);
    })
    ->execute(
        function($result, $db, $execution_data) {
            echo "Updated rows: " . $execution_data['affected_rows'];
        }
    );
```

#### DELETE

```php
DB::module("RAW")
    ->q(function ($qb) {
        $qb->delete('users')->where('id', '=', 1);
    })
    ->execute(
        function($result, $db, $execution_data) {
            echo "Deleted rows: " . $execution_data['affected_rows'];
        }
    );
```

### 2.3 Pokročilé RAW Queries

#### JOIN Queries

```php
$users = DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("
            SELECT u.name, p.title, p.created_at
            FROM users u
            LEFT JOIN posts p ON u.id = p.user_id
            WHERE u.active = ?
            ORDER BY p.created_at DESC
        ", [1]);
    })
    ->all();
```

#### Transactions

```php
DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("START TRANSACTION");
    })
    ->execute();

try {
    // Multiple operations in transaction
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->insert('orders', ['user_id' => 1, 'total' => 100]);
        })
        ->execute();

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("UPDATE users SET balance = balance - ? WHERE id = ?", [100, 1]);
        })
        ->execute();

    // Record transaction
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->insert('transactions', [
                'from_user_id' => 1,
                'to_user_id' => 2,
                'amount' => 100,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        })
        ->execute();

    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("COMMIT");
        })
        ->execute();

} catch (Exception $e) {
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("ROLLBACK");
        })
        ->execute();
}
```

---

## 3. QueryBuilder

QueryBuilder provides a fluent API for building SQL queries without writing raw SQL.

### 3.1 Basic SELECT Queries

```php
// Simple SELECT
$users = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select(['id', 'name', 'email'])
           ->from('users')
           ->where('active', '=', 1);
    })
    ->all();

// SELECT with JOIN
$posts = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select(['p.title', 'u.name as author'])
           ->from('posts', 'p')
           ->join('users', 'u', 'p.user_id', '=', 'u.id')
           ->where('p.published', '=', 1);
    })
    ->all();

// First result
$user = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')->orderBy('created_at', 'DESC');
    })
    ->first();
```

### 3.2 WHERE Conditions

```php
// Basic WHERE
$users = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->where('age', '>', 18)
           ->where('active', '=', 1);
    })
    ->all();

// WHERE IN
$admins = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->whereIn('role', ['admin', 'moderator']);
    })
    ->all();

// WHERE BETWEEN
$users = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->whereBetween('age', [20, 30]);
    })
    ->all();

// NULL values
$users = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->whereNotNull('email')
           ->whereNull('deleted_at');
    })
    ->all();
```

### 3.3 ORDER BY, LIMIT, OFFSET

```php
$users = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->orderBy('created_at', 'DESC')
           ->orderBy('name', 'ASC')
           ->limit(10)
           ->offset(20); // page 3 with limit 10
    })
    ->all();
```

### 3.4 GROUP BY and HAVING

```php
$stats = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select(['category_id', 'COUNT(*) as count'])
           ->from('posts')
           ->where('published', '=', 1)
           ->groupBy('category_id')
           ->having('COUNT(*)', '>', 5)
           ->orderBy('count', 'DESC');
    })
    ->all();
```

### 3.5 UNION Queries

```php
$result = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select(['name', "'user' as type"])
           ->from('users')
           ->union(function ($subQuery) {
               $subQuery->select(['title', "'post' as type"])
                        ->from('posts');
           })
           ->orderBy('name');
    })
    ->all();
```

### 3.6 Subqueries

```php
// WHERE IN with subquery
$activeUsers = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->whereIn('id', function ($subQuery) {
               $subQuery->select('user_id')
                        ->from('posts')
                        ->where('published', '=', 1);
           });
    })
    ->all();

// EXISTS subquery
$usersWithPosts = DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->whereExists(function ($subQuery) {
               $subQuery->select('1')
                        ->from('posts')
                        ->whereColumn('posts.user_id', 'users.id');
           });
    })
    ->all();
```

---

## 4. ORM (Object-Relational Mapping)

ORM provides object-oriented access to the database with Entity and Collection classes.

### 4.1 Basic ORM Queries

```php
// SELECT all users
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all(); // Returns Collection<Entity>

// SELECT first user
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->orderBy('created_at', 'DESC');
    })
    ->first(); // Returns Entity or null

// Find by ID
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123); // Returns Entity or null

// Check existence
$hasUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('COUNT(*)')->from('users')->where('active', '=', 1);
    })
    ->exists(); // true/false

$noInactiveUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('COUNT(*)')->from('users')->where('active', '=', 0);
    })
    ->doesntExist(); // true/false
```

### 4.2 Entity CRUD Operations

#### Create New Entity

```php
// Method 1: Set table first, then create
$user = new Entity([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
    'active' => 1
], DB::module("ORM")->selectDb('main'));

$user->table('users');
$user->save();

// Method 2: Use query builder to set table context
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->from('users'); // Sets table context
    })
    ->newEntity([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com'
    ]);

$user->save();
```

#### Update Entity

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

if ($user) {
    $user->name = 'John Smith';
    $user->email = 'johnsmith@example.com';
    $user->save();

    // Or bulk update
    $user->update([
        'last_login' => date('Y-m-d H:i:s'),
        'login_count' => $user->login_count + 1
    ]);
}
```

#### Delete Entity

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

if ($user) {
    $user->delete();
}
```

#### Static Methods

```php
// Find by ID (static method)
Entity::find(DB::module("ORM")->selectDb('main'), 123,
    function($user) {
        if ($user) {
            echo "Found user: " . $user->name;
        } else {
            echo "User not found";
        }
    },
    function($error) {
        echo "Error finding: " . $error;
    }
);

// Note: Entity::create() requires table to be set first
// Use new Entity() + table() + save() instead
```

### 4.3 Entity Properties and Methods

#### Dirty Checking

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

$user->name = 'New Name'; // Change attribute

if ($user->isDirty()) {
    echo "Entity has unsaved changes";
}

if ($user->isDirty('name')) {
    echo "Name was changed";
}

$changes = $user->getDirty(); // ['name' => 'New Name']
```

#### Validation

```php
$user = new Entity(['email' => 'invalid-email'], DB::module("ORM")->selectDb('main'));
$user->table('users'); // Set table for validation context
$user->setRules([
    'name' => 'required|min:2|max:50',
    'email' => 'required|email',
    'age' => 'integer|min:18|max:120'
]);

$user->name = 'John';
$user->email = 'john@example.com';
$user->age = 25;

try {
    $user->validate();
    echo "Validation successful";
} catch (Exception $e) {
    echo "Validation error: " . $e->getMessage();
}
```

#### Timestamps and Soft Deletes

```php
$user = new Entity(['name' => 'John'], DB::module("ORM")->selectDb('main'));
$user->table('users'); // Set table before configuring features

// Soft deletes
$user->setSoftDeletes(true, 'deleted_at');

// Timestamps are set automatically on save()
// created_at and updated_at

$user->save(); // Automatically sets created_at and updated_at

$user->touch(); // Updates only updated_at
$user->touch('last_activity'); // Updates custom timestamp column

// Soft delete
$user->delete(); // Sets deleted_at instead of hard delete

// Restore
$user->restore(); // Cancels soft delete

// Force delete
$user->forceDelete(); // Hard delete even with soft deletes enabled
```

#### Utility Methods

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

// Get primary key
$id = $user->getKey(); // or $user->id
$keyName = $user->getKeyName(); // 'id'

// Bulk set attributes
$user->fill([
    'name' => 'New Name',
    'email' => 'new@example.com'
]);

// Replicate (copy without ID)
$newUser = $user->replicate();
$newUser->email = 'copy@example.com';
$newUser->save();

// Refresh from database
$user->name = 'Changed Name';
$user->fresh(); // Cancels local changes and loads from DB

// Convert to array
$userArray = $user->toArray();
```

### 4.4 Relations (Relationships)

#### hasOne

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

// Load profile (1:1 relationship)
$profile = $user->hasOne('profiles', 'user_id', 'id');

// With callback filter
$activeProfile = $user->hasOne('profiles', 'user_id', 'id', function($query) {
    $query->where('active', '=', 1);
});
```

#### hasMany

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

// Load all user posts (1:N relationship)
$posts = $user->hasMany('posts', 'user_id', 'id');

// With callback filter
$publishedPosts = $user->hasMany('posts', 'user_id', 'id', function($query) {
    $query->where('published', '=', 1)->orderBy('created_at', 'DESC');
});
```

#### belongsTo

```php
$post = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('posts');
    })
    ->find(456);

// Load post author (N:1 relationship)
$author = $post->belongsTo('users', 'user_id', 'id');
```

#### belongsToMany

```php
$user = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->find(123);

// Load user roles (N:N relationship through pivot table)
$roles = $user->belongsToMany('roles', 'user_roles', 'user_id', 'role_id');
```

#### hasManyThrough

```php
$country = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('countries');
    })
    ->find(1);

// Load all posts from country through users (1:N:N relationship)
$posts = $country->hasManyThrough('posts', 'users', 'country_id', 'user_id');
```

#### Polymorphic Relationships

```php
// morphOne - comment on post or video
$post = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('posts');
    })
    ->find(123);

$image = $post->morphOne('images', 'imageable_type', 'imageable_id', 'Post');

// morphMany - all comments on post
$comments = $post->morphMany('comments', 'commentable_type', 'commentable_id', 'Post');

// morphTo - reverse direction (from comment to post/video)
$comment = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('comments');
    })
    ->find(456);

$parent = $comment->morphTo('commentable'); // Returns Post or Video
```

### 4.5 Eager Loading

```php
// Load users with their posts
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->with(['posts']) // Eager loading
    ->all();

// Access to relations (already loaded)
foreach ($users as $user) {
    $posts = $user->posts; // No additional query
}

// Multiple relations
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->with(['posts', 'profile']) // Loading multiple relations
    ->all();

// Load missing (additional loading)
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Additional relation loading
$users->loadMissing(['posts', 'roles']);
```

### 4.6 Events a Callbacks

```php
$user = new Entity(['name' => 'John'], DB::module("ORM")->selectDb('main'));
$user->table('users'); // Set table before configuring events

// Event registration
$user->observe('creating', function($entity) {
    echo "Creating entity...";
    $entity->created_by = $_SESSION['user_id'];
});

$user->observe('created', function($entity) {
    echo "Entity created with ID: " . $entity->getKey();
});

$user->observe('updating', function($entity) {
    echo "Updating entity...";
    $entity->updated_by = $_SESSION['user_id'];
});

$user->observe('saving', function($entity) {
    echo "Saving entity...";
    // Common logic for create/update
});

$user->observe('saved', function($entity) {
    echo "Entity saved successfully";
});

$user->observe('deleting', function($entity) {
    echo "Deleting entity...";
    // Log delete action
});

$user->observe('deleted', function($entity) {
    echo "Entity deleted";
});

// Save triggers events
$user->table('users');
$user->save(); // -> creating -> saving -> created -> saved
```

---

## 5. Collection Methods

Collection class provides Laravel-like API for working with entity collections.

### 5.1 Basic Methods

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all(); // Collection

// Count items
$count = $users->count();

// First item
$firstUser = $users->first();

// Last item (if exists)
$lastUser = $users->last();

// Convert to array
$userArray = $users->toArray();

// Push new item
$users->push($newUser);

// Iteration
foreach ($users as $user) {
    echo $user->name;
}
```

### 5.2 Filtrovanie a Transformácia

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Filter - active users
$activeUsers = $users->filter(function($user) {
    return $user->active == 1;
});

// Map - only names
$names = $users->map(function($user) {
    return $user->name;
});

// Pluck - values of specific field
$emails = $users->pluck('email');

// Reject - opposite of filter
$inactiveUsers = $users->reject(function($user) {
    return $user->active == 1;
});
```

### 5.3 Triedenie

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Sort by field
$sortedByName = $users->sortBy('name');
$sortedByAgeDesc = $users->sortBy('age', 'desc');
$sortedByAgeDesc2 = $users->sortByDesc('age');

// Custom sorting
$sortedCustom = $users->sort(function($a, $b) {
    return strcmp($a->name, $b->name);
});
```

### 5.4 Agregácie

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Statistics
$averageAge = $users->avg('age');
$totalBalance = $users->sum('balance');
$minAge = $users->min('age');
$maxAge = $users->max('age');
```

### 5.5 Hľadanie a Kontrola

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Find first by criteria
$admin = $users->find(function($user) {
    return $user->role === 'admin';
});

// Existence check
$hasAdmins = $users->contains(function($user) {
    return $user->role === 'admin';
});

$hasJohn = $users->contains(function($user) {
    return $user->name === 'John';
});

// Some/Every
$someAreActive = $users->some(function($user) {
    return $user->active == 1;
});

$allAreActive = $users->every(function($user) {
    return $user->active == 1;
});
```

### 5.6 Rozdelenie a Zlúčenie

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Split into two collections
list($activeUsers, $inactiveUsers) = $users->partition(function($user) {
    return $user->active == 1;
});

// Merge collections
$allUsers = $activeUsers->merge($inactiveUsers);

// Concatenation
$moreUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->where('created_at', '>', '2023-01-01');
    })
    ->all();

$combined = $users->concat($moreUsers);
```

### 5.7 Chunk a Nth

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Processing in chunks
$users->chunk(10, function($chunk) {
    foreach ($chunk as $user) {
        // Process 10 users at once
        sendEmail($user);
    }
});

// Every nth element
$everyThirdUser = $users->nth(3); // 1., 4., 7., 10., ...
$everyFifthStartingFromSecond = $users->nth(5, 1); // 2., 7., 12., ...
```

### 5.8 Podmienené Operácie

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// When - conditional operation
$processedUsers = $users->when($request->has('active'), function($collection) {
    return $collection->filter(function($user) {
        return $user->active == 1;
    });
}, function($collection) {
    return $collection; // fallback
});

// Unless - opposite of when
$processedUsers = $users->unless(empty($filters), function($collection) use ($filters) {
    return $collection->filter($filters);
});

// Tap - debugging without changing collection
$users->tap(function($collection) {
    Log::info("Processing " . $collection->count() . " users");
})->filter(...)->map(...);
```

### 5.9 Group By a Unique

```php
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

// Group by role
$usersByRole = $users->groupBy('role');
// ['admin' => Collection, 'user' => Collection, 'moderator' => Collection]

// Unique values
$uniqueRoles = $users->pluck('role')->unique();

// Unique with custom callback
$uniqueUsers = $users->unique(function($user) {
    return $user->email; // Unique by email
});
```

---

## 6. Pagination

Framework podporuje efektívnu SQL LIMIT/OFFSET pagination.

### 6.1 Základná Pagination

```php
// Pagination cez ORM
$paginatedUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->orderBy('id');
    })
    ->paginate(20, 1); // 20 per page, page 1

// Result structure
[
    'data' => [...], // 20 entities
    'current_page' => 1,
    'per_page' => 20,
    'total' => 150, // total count
    'last_page' => 8, // 150/20 = 7.5 -> 8
    'from' => 1, // first record on page
    'to' => 20, // last record on page
    'has_more_pages' => true,
    'prev_page' => null, // previous page
    'next_page' => 2 // next page
]
```

### 6.2 Pagination s WHERE filtrom

```php
$paginatedPosts = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('posts')
           ->where('published', '=', 1)
           ->where('category_id', '=', $categoryId)
           ->orderBy('created_at', 'DESC');
    })
    ->paginate(10, $page);
```

### 6.3 Použitie v Templates

```php
// Controller
public static function index($request) {
    $page = $request->get('page', 1);
    $perPage = 12;

    $products = DB::module("ORM")
        ->selectDb('main')
        ->q(function ($qb) {
            $qb->select('*')->from('products')
               ->where('active', '=', 1)
               ->orderBy('created_at', 'DESC');
        })
        ->paginate($perPage, $page);

    $renderer = self::initRenderer($request, 'sk');
    // ... template setup
    $renderer->setLayoutVar('products', $products);
    return $renderer->renderView();
}
```

```php
<!-- Template -->
{{ foreach $products.data as $product }}
    <!-- zobraz produkt -->
{{ endforeach }}

<!-- Pagination links -->
{{ if $products.has_more_pages }}
    <div class="pagination">
        {{ if $products.prev_page }}
            <a href="?page={{ $products.prev_page }}">Predchádzajúca</a>
        {{ endif }}

        <span>Stránka {{ $products.current_page }} z {{ $products.last_page }}</span>

        {{ if $products.next_page }}
            <a href="?page={{ $products.next_page }}">Nasledujúca</a>
        {{ endif }}
    </div>
{{ endif }}
```

---

## 7. Pokročilé Funkcie

### 7.1 WhereHas a WhereDoesntHave

```php
// Users who have published posts
$usersWithPosts = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->whereHas('posts', function($query) {
        $query->where('published', '=', 1);
    })
    ->all();

// Users without posts
$usersWithoutPosts = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->whereDoesntHave('posts')
    ->all();
```

### 7.2 WithCount - Eager Loading s počtom

```php
// Load categories with post count
$categories = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('categories');
    })
    ->withCount('posts') // Adds posts_count column
    ->all();

// Access to count
foreach ($categories as $category) {
    echo $category->name . ': ' . $category->posts_count . ' článkov';
}
```

### 7.3 Utility Functions

```php
// Check result existence (optimized - SELECT 1 LIMIT 1)
$hasUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->where('active', '=', 1);
    })
    ->exists(); // true/false

$noInactiveUsers = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->where('active', '=', 0);
    })
    ->doesntExist(); // true/false

// Check connection (for debugging)
$connection = DB::getConnection();

// Disconnect (rarely needed due to connection pooling)
DB::disconnect();
```

---

## 8. Error Handling a Callbacks

### 8.1 Success a Error Callbacks

```php
DB::module("RAW")
    ->q(function ($qb) {
        $qb->select('*')->from('users')->where('id', '=', $userId);
    })
    ->execute(
        function($result, $db, $execution_data) {
            // Success callback
            // $result - query results
            // $db - databaser instance
            // $execution_data - metadata (insert_id, affected_rows, etc.)
            echo "Našlo sa " . count($result) . " záznamov";
        },
        function($error, $db, $execution_data) {
            // Error callback
            // $error - error message
            // $db - databaser instance
            // $execution_data - metadata
            Log::error("Database error: " . $error);
            echo "Nastala chyba pri načítaní dát";
        }
    );
```

### 8.2 Exception Handling

```php
try {
    $user = DB::module("ORM")
        ->selectDb('main')
        ->q(function ($qb) {
            $qb->select('*')->from('users')->where('id', '=', $userId);
        })
        ->first();

    if (!$user) {
        throw new Exception("Používateľ nenájdený");
    }

    // User processing
    $user->name = 'New Name';
    $user->save();

} catch (Exception $e) {
    // Error logging
    Log::error("User update failed: " . $e->getMessage());

    // User error
    echo "Nepodarilo sa aktualizovať používateľa";
}
```

### 8.3 Transakcie s Error Handling

```php
function transferMoney($fromUserId, $toUserId, $amount) {
    DB::module("RAW")
        ->q(function ($qb) {
            $qb->raw("START TRANSACTION");
        })
        ->execute();

    try {
        // Check balance
        $fromUser = DB::module("ORM")
            ->selectDb('main')
            ->q(function ($qb) use ($fromUserId) {
                $qb->select('*')->from('users')->where('id', '=', $fromUserId);
            })
            ->first();

        if (!$fromUser || $fromUser->balance < $amount) {
            throw new Exception("Nedostatočný zostatok");
        }

        // Transfer
        DB::module("RAW")
            ->q(function ($qb) use ($fromUserId, $amount) {
                $qb->raw("UPDATE users SET balance = balance - ? WHERE id = ?", [$amount, $fromUserId]);
            })
            ->execute();

        DB::module("RAW")
            ->q(function ($qb) use ($toUserId, $amount) {
                $qb->raw("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $toUserId]);
            })
            ->execute();

        // Transaction record
        DB::module("RAW")
            ->q(function ($qb) use ($fromUserId, $toUserId, $amount) {
                $qb->insert('transactions', [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'amount' => $amount,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            })
            ->execute();

        DB::module("RAW")
            ->q(function ($qb) {
                $qb->raw("COMMIT");
            })
            ->execute();

        return ['success' => true, 'message' => 'Transfer úspešný'];

    } catch (Exception $e) {
        DB::module("RAW")
            ->q(function ($qb) {
                $qb->raw("ROLLBACK");
            })
            ->execute();

        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

---

## 9. Performance Tips

### 9.1 Používajte správny prístup

```php
// ✅ GOOD - ORM for complex operations
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')
           ->where('active', '=', 1)
           ->with(['posts', 'profile']);
    })
    ->all();

// ✅ GOOD - RAW for simple queries
$count = DB::module("RAW")
    ->q(function ($qb) {
        $qb->raw("SELECT COUNT(*) as count FROM users WHERE active = ?", [1]);
    })
    ->first()['count'];
```

### 9.2 Eager Loading

```php
// ❌ BAD - N+1 problem
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all();

foreach ($users as $user) {
    $posts = $user->hasMany('posts', 'user_id', 'id'); // Separate query for each user
}

// ✅ GOOD - Eager loading
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->with(['posts']) // One query for all relations
    ->all();

foreach ($users as $user) {
    $posts = $user->posts; // Already loaded
}
```

### 9.3 Select iba potrebné stĺpce

```php
// ✅ GOOD - select only needed columns
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select(['id', 'name', 'email'])->from('users');
    })
    ->all();

// ❌ BAD - select everything
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users'); // Loads all columns even if not needed
    })
    ->all();
```

### 9.4 Pagination pre veľké dáta

```php
// ✅ GOOD - pagination for large result sets
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users')->orderBy('id');
    })
    ->paginate(100, $page); // Loads only 100 records at once

// ❌ BAD - loading all at once
$users = DB::module("ORM")
    ->selectDb('main')
    ->q(function ($qb) {
        $qb->select('*')->from('users');
    })
    ->all(); // Loads all records at once - problem with 100k+ records
```

---

## 10. Best Practices

### 10.1 Štruktúra kódu

```php
// ✅ GOOD - separation of logic
class UserController extends BaseController {
    public static function index($request) {
        $users = self::getUsers($request->get('page', 1));
        $renderer = self::initRenderer($request, 'sk');
        // ... template setup
        return $renderer->renderView();
    }

    private static function getUsers($page = 1) {
        return DB::module("ORM")
            ->selectDb('main')
            ->q(function ($qb) {
                $qb->select('*')->from('users')
                   ->where('active', '=', 1)
                   ->orderBy('created_at', 'DESC');
            })
            ->paginate(20, $page);
    }
}
```

### 9.2 Validation

```php
// ✅ GOOD - validate before save
$user = new Entity($request->all(), DB::module("ORM")->selectDb('main'));
$user->setRules([
    'name' => 'required|min:2|max:100',
    'email' => 'required|email|unique:users,email',
    'age' => 'integer|min:18|max:120'
]);

try {
    $user->table('users');
    $user->save();
    return ['success' => true];
} catch (Exception $e) {
    return ['success' => false, 'error' => $e->getMessage()];
}
```

### 9.3 Error Handling

```php
// ✅ GOOD - comprehensive error handling
function createPost($data) {
    try {
        // Validation
        if (empty($data['title']) || empty($data['content'])) {
            throw new Exception("Title and content are required");
        }

        // Author check
        $author = DB::module("ORM")
            ->selectDb('main')
            ->q(function ($qb) use ($data) {
                $qb->select('*')->from('users')->where('id', '=', $data['user_id']);
            })
            ->first();

        if (!$author) {
            throw new Exception("Author not found");
        }

        // Create post
        $post = new Entity([
            'title' => $data['title'],
            'content' => $data['content'],
            'user_id' => $data['user_id'],
            'published' => $data['published'] ?? 0
        ], DB::module("ORM")->selectDb('main'));

        $post->table('posts');
        $post->save();

        return ['success' => true, 'post' => $post];

    } catch (Exception $e) {
        Log::error("Post creation failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

---

_This guide provides a complete overview of **DotApp PHP Framework's Database Module**. Always use `DB::module("RAW")` or `DB::module("ORM")` for database operations. For specific use cases, see the existing code in the project or ask about specific scenarios._

---

## 11. Complete Database Support Reference

**DotApp PHP Framework** supports **5 major database systems** through its **modular Database Module architecture** with full SQL syntax compatibility:

### Why Modular Database Architecture?

**DotApp uses DB::module()** because:

1. **Abstraction Level**: `RAW` for direct SQL, `ORM` for object-oriented data handling
2. **Performance Optimization**: Each module can be tuned for specific workloads
3. **Future-Proofing**: New modules (CACHE, ANALYTICS) can be added
4. **Clean Separation**: Business logic vs database implementation

#### Module Examples:

```php
// RAW module - Direct SQL control, maximum performance
DB::module("RAW")->selectDb("analytics")->q(fn($qb) => $qb->raw("SELECT * FROM logs"));

// ORM module - Object-oriented data handling, relationships
DB::module("ORM")->selectDb("main")->q(fn($qb) => $qb->select('*')->from('users'));
```

### Database Types & Configuration

| Database          | Type Code | Driver Options  | Module Access | Best Use Case                       |
| ----------------- | --------- | --------------- | ------------- | ----------------------------------- |
| **MySQL/MariaDB** | `mysql`   | `mysqli`, `pdo` | RAW, ORM      | Web applications, LAMP stack        |
| **PostgreSQL**    | `pgsql`   | `pdo`           | RAW, ORM      | Complex queries, JSON operations    |
| **SQLite**        | `sqlite`  | `pdo`           | RAW, ORM      | Development, testing, embedded apps |
| **SQL Server**    | `sqlsrv`  | `pdo`           | RAW, ORM      | Enterprise Windows environments     |
| **Oracle**        | `oci`     | `pdo`           | RAW, ORM      | High-reliability enterprise systems |

### Configuration Examples

#### MySQL/MariaDB

```php
Config::addDatabase("main", "localhost", "root", "", "myapp", "utf8mb4", "mysql", "pdo");
```

#### PostgreSQL

```php
Config::addDatabase("main", "localhost", "postgres", "password", "myapp", "UTF8", "pgsql", "pdo");
```

#### SQLite

```php
Config::addDatabase("main", "", "", "", "/path/to/database.sqlite", "", "sqlite", "pdo");
```

#### Microsoft SQL Server

```php
Config::addDatabase("main", "localhost", "sa", "password", "myapp", "UTF8", "sqlsrv", "pdo");
```

#### Oracle Database

```php
Config::addDatabase("main", "localhost", "user", "password", "ORCL", "AL32UTF8", "oci", "pdo");
// SID or Service Name = "ORCL"
```

### Automatic SQL Compatibility

DotApp QueryBuilder automatically translates SQL syntax:

```php
// LIMIT/OFFSET - identical syntax across all databases
$query->limit(10)->offset(20);

// Boolean handling - auto-converted
$query->where('active', '=', true); // Becomes 1, 't', etc. per database

// String concatenation - handled per database
$query->selectRaw("CONCAT(first_name, ' ', last_name) as full_name");

// Date operations - database-appropriate functions
$query->where('created_at', '>', '2024-01-01');
```

### Performance & Scalability

| Database   | Read Performance | Write Performance | Concurrent Users | Best For                    |
| ---------- | ---------------- | ----------------- | ---------------- | --------------------------- |
| MySQL      | ⭐⭐⭐⭐⭐       | ⭐⭐⭐⭐⭐        | ⭐⭐⭐⭐⭐       | General web apps            |
| PostgreSQL | ⭐⭐⭐⭐         | ⭐⭐⭐⭐          | ⭐⭐⭐⭐⭐       | Analytical queries          |
| SQLite     | ⭐⭐⭐⭐⭐       | ⭐⭐⭐⭐⭐        | ⭐⭐             | Development/testing         |
| SQL Server | ⭐⭐⭐⭐         | ⭐⭐⭐            | ⭐⭐⭐⭐⭐       | Enterprise Windows          |
| Oracle     | ⭐⭐⭐           | ⭐⭐⭐            | ⭐⭐⭐⭐⭐       | Mission-critical enterprise |

_Choose based on your application requirements, existing infrastructure, and scalability needs._

### 11.3 Driver Binding Architecture

**Critical Understanding:** Databases are **permanently bound to specific drivers**:

#### Driver Types in DotApp:

- **`mysqli`** - MySQLi extension (works with RAW and ORM modules, MySQL only)
- **`pdo`** - PDO extension (works with RAW and ORM modules, supports all databases)

#### Binding Rules:

```php
// ✅ CORRECT - ORM works with any driver
Config::addDatabase("legacy", "localhost", "user", "pass", "myapp", "UTF8", "mysql", "mysqli");
Config::addDatabase("modern", "localhost", "user", "pass", "myapp2", "UTF8", "mysql", "pdo");

DB::module("ORM")->selectDb("legacy");   // Works with mysqli driver
DB::module("ORM")->selectDb("modern");   // Works with PDO driver
DB::module("RAW")->selectDb("legacy");   // Works with mysqli driver
DB::module("RAW")->selectDb("modern");   // Works with PDO driver
```

#### Why This Architecture?

1. **Performance**: Each driver optimized for specific use cases
2. **Compatibility**: Legacy systems can use mysqli, modern use PDO
3. **Flexibility**: ORM works with any configured driver
4. **Resource Management**: Separate connection pools per driver
5. **Security**: Driver-specific security implementations

#### Common Pitfalls:

```php
// ✅ ORM works with any configured driver
Config::addDatabase("legacy", "localhost", "user", "pass", "myapp", "UTF8", "mysql", "mysqli");
Config::addDatabase("modern", "localhost", "user", "pass", "myapp2", "UTF8", "mysql", "pdo");

// Both work perfectly:
DB::module("ORM")->selectDb("legacy");   // Uses mysqli driver
DB::module("ORM")->selectDb("modern");   // Uses PDO driver
```
