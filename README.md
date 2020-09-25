# owowagency/applies-http-query

This package contains a trait that can be used on Eloquent models. The trait adds a `httpQuery` scope that can apply a "search" and "order by". It will only apply these two when certain url query parameters are present.

## Install

To install this package for Laravel version 5, 6 or 7, run the following command:

```
composer require owowagency/applies-http-query "^1.2"
```

## Usage

Add the trait to the model:

```
use OwowAgency\AppliesHttpQuery\AppliesHttpQuery;

class Post extends Model
{
    use AppliesHttpQuery
}
```

Specify the columns on which it can search:

```
/**
 * Http queryable rules.
 *
 * @var array
 */
protected $httpQueryable = [
    'columns' => [
        'posts.title'
        'users.name',
    ],
    'joins' => [
        'users' => ['posts.user_id','users.id'],
        'countries' => ['users.country_id', 'countries.id']
    ],
];
```

Call the scope:

```
Post::httpQuery()->paginate();

// Like all other scopes it can be combined with other clauses.
Post::whereNull('deleted_at')->httpQuery()->get();
```

In order for the scope to work, certain query parameters should be present in the url:
 - `search`, the value that will be searched for.
 - `order_by`, the column that will be ordered on.
 - `sort_by`, the direction of the ordering. By default this is `asc`.

### Search

```
https://mysite.com/posts?search=test
```

Will result in the following query:

```
SELECT * FROM posts INNER JOIN users ON posts.user_id = users.id WHERE (posts.title LIKE "%test%" OR users.name LIKE "%test%")
```

### Order by


```
https://mysite.com/posts?order_by=user.name
```

Will result in the following query:

```
SELECT * FROM posts INNER JOIN users ON posts.user_id = users.id ORDER BY users.name ASC
```

```
https://mysite.com/posts?order_by=user.country.name&sort_by=desc
```

Will result in the following query:

```
SELECT * FROM posts INNER JOIN users ON posts.user_id = users.id INNER JOIN countries ON users.country_id = country.id ORDER BY countries.name desc
```
