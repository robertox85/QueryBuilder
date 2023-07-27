# QueryBuilder
 The `QueryBuilder` class is a SQL query builder in PHP that provides a chainable interface for programmatically and safely creating queries.

## Installation
Just clone the repository and include the `QueryBuilder.php` file in your project.

## Usage

### Creating a new query
```php
$pdo = new PDO('mysql:host=' . $host . ';dbname=' . $dbname, $username, $password);
$qb = new QueryBuilder($pdo);
```

### Selecting columns
```php
// SELECT * FROM users
$result = $qb->setTable('users')->get();

// SELECT id, name FROM users
$result = $qb->setTable('users')->select(['id', 'name'])->get();

// SELECT * FROM users WHERE id = 1
$result = $qb->setTable('users')->where('id', 1)->get();

// SELECT * FROM users JOIN orders ON users.id = orders.user_id WHERE users.id = 1
$result = $qb->setTable('users')
             ->join('orders', 'users.id', 'orders.user_id')
             ->where('users.id', 1)
             ->get();

// SELECT * FROM users LEFT JOIN orders ON users.id = orders.user_id WHERE users.id = 1
$result = $qb->setTable('users')
             ->leftJoin('orders', 'users.id', 'orders.user_id')
             ->where('users.id', 1)
             ->get();

// SELECT * FROM users WHERE (name = 'John' OR name = 'Jane') AND age > 21
$result = $qb->setTable('users')
             ->beginWhereGroup()
                 ->where('name', 'John')
                 ->orWhere('name', 'Jane')
             ->endWhereGroup()
             ->where('age', '>', 21)
             ->get();

```

### Inserting rows
```php
// INSERT INTO users (name, age) VALUES ('John', 21)
$result = $qb->setTable('users')->insert(['name' => 'John', 'age' => 21]);

// INSERT INTO users (name, age) VALUES ('John', 21), ('Jane', 22)
$result = $qb->setTable('users')->insert([['name' => 'John', 'age' => 21], ['name' => 'Jane', 'age' => 22]]);
```

### Updating rows
```php
// UPDATE users SET name = 'John', age = 21 WHERE id = 1
$result = $qb->setTable('users')->where('id', 1)->update(['name' => 'John', 'age' => 21]);
```

### Deleting rows
```php

// DELETE FROM users WHERE id = 1

$result = $qb->setTable('users')->where('id', 1)->delete();
```

### Executing raw queries
```php
// SELECT * FROM users WHERE id = 1
$result = $qb->execute('SELECT * FROM users WHERE id = 1');

// INSERT INTO users (name, age) VALUES ('John', 21)
$result = $qb->execute('INSERT INTO users (name, age) VALUES (?, ?)', ['John', 21]);

// UPDATE users SET name = 'John', age = 21 WHERE id = 1
$result = $qb->execute('UPDATE users SET name = ?, age = ? WHERE id = ?', ['John', 21, 1]);

// DELETE FROM users WHERE id = 1
$result = $qb->execute('DELETE FROM users WHERE id = ?', [1]);
```

### Getting the last inserted ID
```php
// INSERT INTO users (name, age) VALUES ('John', 21)
$result = $qb->setTable('users')->insert(['name' => 'John', 'age' => 21]);
$lastInsertedId = $qb->getLastInsertedId();
```

