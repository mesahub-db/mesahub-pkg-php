# MesaHub PHP SDK

PHP SDK for [MesaHub](https://mesahub.app) — access SQLite databases from PHP with raw SQL or a high-level table API.

## Requirements

- PHP 8.1+
- [Guzzle 7](https://docs.guzzlephp.org/)

## Installation

Add the VCS repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mesahub-db/mesahub-pkg-php.git"
        }
    ],
    "require": {
        "mesahub/client": "^0.1.0"
    }
}
```

Then run:

```bash
composer install
```

## Quick Start

```php
use Mesahub\MesahubClient;

$client = new MesahubClient(
    apiKey: 'shs_your_api_key',        // from mesahub.app → Settings → API Keys
    apiUrl: 'https://api.mesahub.app', // or your self-hosted core URL
);

$db    = $client->db('my-app-db');    // your database slug from the dashboard
$users = $db->table('users');

// High-level table API
$all    = $users->find(where: ['active' => 1], limit: 20);
$alice  = $users->findOne(where: ['email' => 'alice@example.com']);
$count  = $users->count(where: ['active' => 1]);
$newRow = $users->insert(['name' => 'Bob', 'email' => 'bob@example.com', 'active' => 1]);

$users->update(where: ['id' => $newRow['id']], set: ['name' => 'Robert']);
$users->delete(where: ['id' => $newRow['id']]);

// Raw SQL
$result = $db->query('SELECT * FROM users WHERE active = ?', [1]);
print_r($result->rows);

$db->exec('CREATE TABLE IF NOT EXISTS logs (msg TEXT, created_at TEXT)');
```

## Connection String

```php
$info   = MesahubClient::parseMesahubUrl('mh://shs_abc@mycore.railway.app/mydb');
$client = new MesahubClient(
    apiKey:      $info['api_key'],
    apiUrl:      $info['api_url'],
    routePrefix: $info['route_prefix'],
);
$db = $client->db($info['db_name']);
```

Format: `mh://apikey@host[:port]/dbname`

- Remote hosts (e.g. `railway.app`) → HTTPS, `/v1/` routes
- `localhost` / Docker service names / `*.internal` → HTTP, `/api/` routes

## WHERE Filters

```php
// Shorthand equality
$users->find(where: ['active' => 1]);

// Comparison operators
$users->find(where: ['age' => ['gte' => 18], 'score' => ['lt' => 100]]);

// LIKE
$users->find(where: ['name' => ['like' => '%alice%']]);

// IN / NOT IN
$users->find(where: ['role' => ['in' => ['admin', 'editor']]]);

// NULL checks
$users->find(where: ['deleted_at' => ['is_null' => true]]);
$users->find(where: ['verified_at' => ['is_not_null' => true]]);
```

All conditions in a single `where` array are combined with `AND`.

| Operator key  | SQL equivalent   |
|---------------|------------------|
| *(plain value)* | `= ?`          |
| `eq`          | `= ?`           |
| `ne`          | `!= ?`          |
| `gt`          | `> ?`           |
| `gte`         | `>= ?`          |
| `lt`          | `< ?`           |
| `lte`         | `<= ?`          |
| `like`        | `LIKE ?`        |
| `not_like`    | `NOT LIKE ?`    |
| `in`          | `IN (...)`      |
| `not_in`      | `NOT IN (...)`  |
| `is_null`     | `IS NULL`       |
| `is_not_null` | `IS NOT NULL`   |

## `find()` Options

```php
$users->find(
    where:   ['active' => 1],
    select:  ['id', 'name', 'email'],
    orderBy: [['column' => 'created_at', 'direction' => 'desc']],
    limit:   10,
    offset:  20,
);
```

## `insertMany()`

```php
$db->table('logs')->insertMany(
    rows:       [['msg' => 'started'], ['msg' => 'done']],
    onConflict: 'ignore', // 'ignore' | 'replace' | ''
);
```

## Files

```php
$files = $db->files;

// List
$listing = $files->list(limit: 10, folderPrefix: 'reports/');

// Upload
$record = $files->upload(file_get_contents('report.pdf'), 'report.pdf', 'application/pdf');
echo $record->url;

// Download
$data = $files->download($record->id);

// Delete
$files->delete($record->id);
```

## Error Handling

```php
use Mesahub\Errors\MesahubException;
use Mesahub\Errors\AuthenticationException;
use Mesahub\Errors\NotFoundException;

try {
    $db->query('SELECT * FROM users');
} catch (AuthenticationException $e) {
    echo 'Invalid API key';
} catch (MesahubException $e) {
    echo "[{$e->errorCode}] {$e->statusCode}: {$e->getMessage()}";
}
```

## Result Types

| Type | Properties |
|------|------------|
| `QueryResult` | `rows`, `columns`, `rowCount`, `queryDurationMs` |
| `ExecResult` | `rowsAffected`, `lastInsertRowid`, `queryDurationMs` |
| `FileRecord` | `id`, `filename`, `folderPath`, `sizeBytes`, `contentType`, `url`, `uploadedAt`, `expiresAt`, `metadata` |

## Self-Hosting

```php
$client = new MesahubClient(
    apiKey:      'your-admin-token',
    apiUrl:      'http://localhost:4004',
    routePrefix: 'api',
);
```
