<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

trait HandlesClubResources
{
    use InteractsWithClubResource;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $columnKeys
     */
    /**
     * @param  list<string>  $columnKeys
     * @param  list<string>  $fieldKeys  Extra keys needed for inline edit forms
     */
    /**
     * @param  list<string>  $columnKeys
     * @param  list<string>  $fieldKeys
     * @param  list<string>  $searchFields
     */
    protected function clubResourceIndex(
        Request $request,
        string $modelClass,
        array $columnKeys,
        int $perPage = 10_000,
        array $fieldKeys = [],
        string $q = '',
        string $status = '',
        array $searchFields = [],
        ?string $statusField = null,
    ): LengthAwarePaginator {
        $keys = array_values(array_unique(array_merge($columnKeys, $fieldKeys)));
        $builder = $this->clubQuery($modelClass, $request);

        if ($q !== '' && $searchFields !== []) {
            $like = '%'.$q.'%';
            $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $builder->where(function ($w) use ($searchFields, $like, $op) {
                foreach ($searchFields as $i => $field) {
                    $i === 0
                        ? $w->where($field, $op, $like)
                        : $w->orWhere($field, $op, $like);
                }
            });
        }

        if ($status !== '' && $statusField) {
            if ($statusField === 'is_active') {
                $builder->where('is_active', $status === '1' || $status === 'active');
            } else {
                $builder->where($statusField, $status);
            }
        }

        return $builder
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Model $item) use ($keys) {
                $row = ['id' => $item->getKey()];
                foreach ($keys as $key) {
                    $value = data_get($item, $key);
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('H:i:s') !== '00:00:00'
                            ? $value->format('Y-m-d\TH:i')
                            : $value->format('Y-m-d');
                    }
                    $row[$key] = $value;
                }

                return $row;
            });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $validated
     */
    protected function clubResourceStore(
        Request $request,
        string $modelClass,
        array $validated,
    ): Model {
        $validated['club_id'] = $this->clubId($request);

        /** @var Model $modelClass */
        return $modelClass::create($validated);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $validated
     */
    protected function clubResourceUpdate(
        Request $request,
        string $modelClass,
        string $id,
        array $validated,
    ): Model {
        $record = $this->clubQuery($modelClass, $request)->findOrFail($id);
        $record->fill(Arr::except($validated, ['club_id']));
        $record->save();

        return $record;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function clubResourceDestroy(
        Request $request,
        string $modelClass,
        string $id,
    ): void {
        $record = $this->clubQuery($modelClass, $request)->findOrFail($id);
        $record->delete();
    }

    protected function redirectToIndex(string $routePrefix, ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route("{$routePrefix}.index");

        if ($message) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }
}
