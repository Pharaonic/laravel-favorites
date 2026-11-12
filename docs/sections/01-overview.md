:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Laravel Favorites

Laravel Favorites lets any Eloquent model favorite any other. Add `CanFavorite` to the model that favorites, such as a user, and `HasFavorites` to the model that is favorited, such as a post. Each favorite is one row in a `favorites` table, and a unique index on that table keeps every favoriter and favoritable pair to one row, even when two requests try to create it at the same moment. `favorite()` and `unfavorite()` tell you whether they changed anything, and the favorites are native Eloquent relations, so counts, filters and eager loading run in SQL.

:::features
### Results That Say What Changed {icon="check-circle"}
favorite and unfavorite return true only when the call created or removed the favorite.

### Unique by the Database {icon="lock"}
A unique index, not a read before the write, keeps each pair to one row.

### Any Model, Both Roles {icon="users"}
Users, bots or teams favorite posts, comments, products or each other, and one model can favorite and be favorited.

### Native Relations {icon="chart"}
Count, filter and eager load favorites with withCount, whereHas and with.

### Mixed Key Types {icon="tag"}
Integer, UUID and ULID models share one table, and morph map aliases are respected.

### Clean Deletes {icon="shield-check"}
Deleting a model deletes its favorites, while a soft delete keeps them for a restore.
:::

:::info Quick Tip
Send the state the user wants (`favorite()` or `unfavorite()`) from your UI instead of `toggleFavorite()`: a double click then can't undo itself.
:::
