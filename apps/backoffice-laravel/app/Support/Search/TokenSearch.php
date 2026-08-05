<?php

namespace App\Support\Search;

use Illuminate\Contracts\Database\Eloquent\Builder;

class TokenSearch
{
    /**
     * Búsqueda por tokens: AND entre palabras del texto buscado, OR entre campos
     * por cada palabra. Campos con punto ('client.first_name', 'pet.client.email')
     * se resuelven como whereHas anidado, con la profundidad que haga falta.
     */
    public static function apply(Builder $query, string $search, array $fields): Builder
    {
        $tokens = array_values(array_filter(
            preg_split('/\s+/', trim($search)),
            fn (string $token) => $token !== ''
        ));

        foreach ($tokens as $token) {
            $query->where(function (Builder $tokenQuery) use ($token, $fields) {
                foreach ($fields as $field) {
                    static::applyField($tokenQuery, $field, $token, or: true);
                }
            });
        }

        return $query;
    }

    protected static function applyField(Builder $query, string $field, string $token, bool $or): void
    {
        if (! str_contains($field, '.')) {
            $or ? $query->orWhere($field, 'like', "%{$token}%") : $query->where($field, 'like', "%{$token}%");

            return;
        }

        [$relation, $rest] = explode('.', $field, 2);
        $callback = fn (Builder $relationQuery) => static::applyField($relationQuery, $rest, $token, or: false);

        $or ? $query->orWhereHas($relation, $callback) : $query->whereHas($relation, $callback);
    }
}
