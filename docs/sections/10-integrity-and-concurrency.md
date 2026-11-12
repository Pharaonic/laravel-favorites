## Integrity and Concurrency

### The unique index

The `favorites_unique_pair` index on (`favoriter_type`, `favoriter_id`, `favoritable_type`, `favoritable_id`) is what keeps a pair to one row. The package never reads first and writes second: `favorite()` inserts the row, and when the index refuses it because the pair already has one, `favorite()` returns false. Two requests that favorite the same post at the same moment therefore get `true` and `false`, and the table holds one row.

Only a refusal by a unique index counts as "already favorited", and only when the pair's row can then be read. Every other database error is thrown: a missing table, a lost connection, a `NOT NULL` failure, or a unique index of your own that refused the row.

Inside a transaction the insert runs in a savepoint, so a refused insert doesn't abort your transaction on PostgreSQL. The pair's row is then read with a locking read, so a favorite another request committed after your transaction started is still found: on MySQL, whose transactions read from a snapshot, a plain read would miss it.

### Deadlocks

On MySQL, a favorite and an unfavorite (or two toggles) of the same pair at the same moment can make InnoDB roll back one of the statements to break a deadlock. Outside a transaction, the package runs that statement again, up to three times, so the call still returns its normal result. Inside your own transaction, the database has already rolled the whole transaction back, so the deadlock reaches you; run the transaction with retries:

```php
DB::transaction(function () use ($user, $post) {
    $user->favorite($post);
    // ...
}, 3);
```

### Removing

`unfavorite()` reads the pair's favorite and deletes it by id. The call that deleted the row returns true and dispatches `FavoriteRemoved`; a simultaneous `unfavorite()` deletes nothing and returns false.

### Toggling under concurrency

`toggleFavorite()` inserts first: when the insert works, the pair is now favorited and the call returns true. When the pair already has a favorite, the call deletes it and returns false. Nothing serializes the two steps.

What is guaranteed:

- the table never holds two rows for a pair;
- every call returns the state it left the pair in;
- events are dispatched only for changes the call made.

What is not: two toggles of the same pair at the same moment are not serialized. Starting from "favorited", both may delete the same favorite, so the pair ends up not favorited instead of favorited again. When a user's intent matters, as in a favorite button, send the state the user wants and call `favorite()` or `unfavorite()`.

### Tested databases

The test suite runs on SQLite, MySQL 8.4 and PostgreSQL 16, including tests that start several PHP processes, each with its own connection, favoriting, unfavoriting or toggling the same pair at the same moment. On SQLite the database file serializes those writes; MySQL and PostgreSQL run them in parallel.
