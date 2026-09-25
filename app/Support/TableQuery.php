<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Conditionable;

/**
 * Pencarian, pengurutan (allow-list), dan paginasi standar untuk halaman indeks.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class TableQuery
{
    use Conditionable;

    /**
     * @param  Builder<TModel>  $query
     */
    public function __construct(private Builder $query, private Request $request) {}

    /**
     * @param  Builder<TModel>  $query
     * @return self<TModel>
     */
    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /**
     * @param  list<string>  $columns  Kolom yang dicari; gunakan "relasi.kolom" untuk relasi.
     * @return self<TModel>
     */
    public function search(array $columns): self
    {
        $term = trim((string) $this->request->string('search'));

        if ($term === '') {
            return $this;
        }

        $this->query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);
                    $query->orWhereHas($relation, fn (Builder $related) => $related->where($field, 'like', "%{$term}%"));
                } else {
                    $query->orWhere($query->qualifyColumn($column), 'like', "%{$term}%");
                }
            }
        });

        return $this;
    }

    /**
     * Terapkan filter persamaan bila parameter request terisi.
     *
     * @param  array<string, string>  $filters  parameter request => kolom
     * @return self<TModel>
     */
    public function filter(array $filters): self
    {
        foreach ($filters as $parameter => $column) {
            $value = $this->request->input($parameter);

            if ($value !== null && $value !== '' && $value !== 'all') {
                $this->query->where($column, $value);
            }
        }

        return $this;
    }

    /**
     * @param  list<string>  $allowed
     * @return self<TModel>
     */
    public function sort(array $allowed, string $default): self
    {
        $sort = (string) $this->request->string('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
            $column = ltrim($default, '-');
        }

        $this->query->orderBy($this->query->qualifyColumn($column), $direction)
            ->orderBy($this->query->qualifyColumn('id'), $direction);

        return $this;
    }

    /**
     * @return Builder<TModel>
     */
    public function builder(): Builder
    {
        return $this->query;
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max((int) $this->request->integer('per_page', $perPage), 5), 100);

        return $this->query->paginate($perPage)->withQueryString();
    }
}
