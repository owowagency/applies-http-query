<?php

namespace OwowAgency\AppliesHttpQuery;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait AppliesHttpQuery
{
    /**
     * Scope a query to add a search or order by when the specific http queries
     * are available.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHttpQuery(Builder $query): Builder
    {
        $request = request();

        if (is_null($request)) {
            return $query;
        }

        if ($request->has('search')) {
            $this->applySearch($query, $request->get('search'), $request->get('searchFields'));
        }

        if ($request->has('order_by')) {
            $this->applyOrderBy(
                $query,
                $request->get('order_by'),
                $request->get('sort_by') ?? 'asc'
            );
        }

        return $query;
    }

    /**
     * Applies the search where clauses to the query. It wraps all these wheres
     * so they will not interfere with other where claueses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @param  string  $searchFields
     * @return void
     */
    private function applySearch(Builder &$query, string $search, string $searchFields): void
    {
        $this->applyJoins($query);

        $columns = $this->filterColumns($this->getColumns(), $searchFields);

        // Add a scoped where clause that includes a search on all the
        // specified columns.
        $query->where(function (Builder $query) use ($search, $columns) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%$search%");
            }
        });
    }

    /**
     * Applies an order by on the specified column. It also accepts a direction
     * of sorting.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $orderBy
     * @param  string  $sortBy
     * @return void
     */
    private function applyOrderBy(Builder &$query, string $orderBy, string $sortBy = 'asc'): void
    {
        $this->applyJoins($query);

        $column = $this->resolveColumn($this, $orderBy);

        $query->orderBy($column, $sortBy);
    }

    /**
     * Applies the joins necessary to add the search or order by.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    private function applyJoins(Builder &$query): void
    {
        $joins = $this->getJoins();

        if (count($joins) == 0) {
            return;
        }

        // Get all the current joins so we can prevent duplicates.
        $exisingJoins = collect($query->getQuery()->joins);

        foreach ($joins as $table => $join) {
            // Check if the join has already been applied.
            $exists = $exisingJoins->contains(function ($value, $key) use ($table) {
                return $table == $value->table;
            });

            // Do not inlcude the join when it has already been applied.
            if (! $exists) {
                $query->join($table, ...$join);
            }
        }
    }

    /**
     * Resolves a column. The sort by can be written in dot notation. Each
     * piece can also be written as the relation name instead of the table, eg:
     * post.user.name. This example will result in users.name. There is no
     * limit in the amount of levels deep the relations can go as long as there
     * exists a method with the name of the specified relation.
     *
     * @param  \Illuminate\Datbase\Eloquent\Model  $model
     * @param  string  $column
     * @return string
     */
    private function resolveColumn(Model $model, string $column): string
    {
        $exploded = explode('.', $column);

        // Do not continue when only one column is present.
        if (count($exploded) == 1) {
            return $column;
        }

        // Try to call the method to verify it is a relationship.
        $method = Arr::pull($exploded, 0);

        if (! method_exists($model, $method)) {
            return $column;
        }

        $relation = $model->$method();

        if (! $relation instanceof Relation) {
            return $column;
        }

        // Retrieve the table of the related model.
        $related = $relation->getRelated();

        $table = $related->getTable();

        $rest = implode('.', $exploded);

        // Add the table to the rest when only one column is left. Resolve
        // column against the related model when more than one column is left.
        if (count($exploded) == 1) {
            $resolved = $table . '.' . $rest;
        } else {
            $resolved = $this->resolveColumn($related, $rest);
        }

        return $resolved;
    }

    /**
     * Filter all columns which aren't in the searchableFields get parameter.
     *
     * @param  array  $columns
     * @param  string  $searchableColumns
     * @return array
     */
    private function filterColumns($columns, $searchFields)
    {
        $searchFields = explode(',', $searchFields);

        // If no searchable fields are given we can presume to search in all
        // columns specified in the model.
        if (count($searchFields) === 0) {
            return $columns;
        }

        return array_intersect($columns, $searchFields);
    }

    /**
     * Gets the http queryable.
     *
     * @return array
     */
    private function getHttpQueryable(): array
    {
        return $this->httpQueryable ?? [];
    }

    /**
     * Gets the columns from http queryable.
     *
     * @return array
     */
    private function getColumns(): array
    {
        return $this->getHttpQueryable()['columns'] ?? [];
    }

    /**
     * Gets the joins from http queryable.
     *
     * @return array
     */
    private function getJoins(): array
    {
        return $this->getHttpQueryable()['joins'] ?? [];
    }
}
