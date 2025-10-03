# Laravel MultiChain Package
A simple Laravel package to interact with MultiChain using RPC. Supports basic CRUD-like operations on MultiChain Streams (acting like blockchain-based tables).

---

## 🚀 Features
- Connect to a MultiChain node via RPC
- Publish data into streams
- Retrieve data by key or transaction ID
- Update data (append a new version)
- Soft delete data (mark as deleted)
- Simple, clean Laravel-style API

---

## 📦 Installation
```bash
composer require khalidmaquilang/multichain-model
```
Publish config:
```bash
php artisan vendor:publish --tag=multichain-config
```

---

## ⚙️ Configuration
Set your MultiChain connection in .env:
```bash
MULTICHAIN_HOST=127.0.0.1
MULTICHAIN_PORT=9538
MULTICHAIN_USER=multichainrpc
MULTICHAIN_PASS=yourpassword
MULTICHAIN_CHAIN=yourchain
```

---

## 📖 Usage
### Initialize Service

In your multichain server (lets assume that we will create users table)
```bash
multichain-cli yourchain create stream users true
multichain-cli yourchain subscribe users
```

```php
use EskieGwapo\Multichain\Models\BlockchainModel;

class User extends BlockchainModel
{
    protected static $stream = 'users'; // this is optional, if you want to specify the stream name
}
```

### Create (Insert Data)
```php
// Create user
$user = User::create([
    'name' => 'Eskie',
    'email' => 'eskie@example.com',
]);
```

### Find (Find Data)
```php
// Create user

// Find user
User::find($user->id);
```

### Get all data
```php
User::all();
```

Update (Append Data)
```php
// Create user

// Update user
$user->update(['email' => 'eskie@newmail.com']);
```

Updates are stored as new blockchain entries under the same key.

Delete (Soft Delete)
```php
// Create user

// Delete user
$user->delete();
```

This marks the record as deleted (soft delete), since blockchain data cannot be physically removed.

History (including old + deleted versions)
```php
$history = $user->history();

foreach ($history as $version) {
    dump($version);
}
```

## 📌 Notes
- Blockchain data is immutable. Updates and deletes are logical operations by appending new entries.

---

### ✅ Roadmap
- Add query helpers (list by publisher, time, etc.)
- Add artisan commands for creating model, migration, migrate
- Add encryption support for sensitive data

---

### 📜 License

MIT