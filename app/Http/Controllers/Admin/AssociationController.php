<?php

namespace App\Http\Controllers\Admin;

use App\Models\Association;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssociationController extends ClubResourceController
{
    protected function resourceKey(): string
    {
        return 'associations';
    }

    public function index(Request $request): Response
    {
        $config = $this->config();
        $columnKeys = array_column($config['columns'], 'key');
        $fieldKeys = array_column($config['fields'], 'name');
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $paginator = $this->clubResourceIndex(
            $request,
            $config['model'],
            $columnKeys,
            30,
            $fieldKeys,
            $q,
            $status,
            $config['search'] ?? [],
            $config['status_field'] ?? null,
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(function (array $row) {
                $row['active'] = ! empty($row['active']) && $row['active'] !== '0' ? '1' : '0';

                return $row;
            }),
        );

        return Inertia::render($config['page'], [
            'title' => $config['title'],
            'subtitle' => $config['subtitle'] ?? null,
            'createLabel' => $config['create_label'] ?? null,
            'resourceKey' => $this->resourceKey(),
            'routes' => [
                'store' => "{$config['route']}.store",
                'update' => "{$config['route']}.update",
                'destroy' => "{$config['route']}.destroy",
                'index' => "{$config['route']}.index",
            ],
            'columns' => $config['columns'],
            'fields' => $config['fields'],
            'items' => $paginator,
            'filters' => [
                'q' => $q,
                'status' => $status,
            ],
            'filterOptions' => $config['filter_options'] ?? null,
            'filterLabel' => $config['filter_label'] ?? 'Κατάσταση',
            'toolbar' => $config['toolbar'] ?? [],
            'emptyMessage' => $config['empty_message'] ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $config = $this->config();
        $validated = $request->validate($config['rules']);
        $validated = $this->normalizeBooleans($validated, $config['fields']);
        $validated = $this->normalizeActive($validated);

        Association::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'club_id' => $this->clubId($request),
            'name' => $validated['name'],
            'meta' => $this->packMeta($validated),
        ]);

        return $this->redirectToIndex($config['route'], 'Το σωματείο προστέθηκε.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $config = $this->config();
        $validated = $request->validate($config['rules']);
        $validated = $this->normalizeBooleans($validated, $config['fields']);
        $validated = $this->normalizeActive($validated);

        $record = $this->clubQuery(Association::class, $request)->findOrFail($id);
        $record->fill([
            'name' => $validated['name'],
            'meta' => array_merge($record->meta ?? [], $this->packMeta($validated)),
        ])->save();

        return $this->redirectToIndex($config['route'], 'Το σωματείο ενημερώθηκε.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{city: string, phone: string, email: string, address: string, active: bool}
     */
    private function packMeta(array $validated): array
    {
        return [
            'city' => (string) ($validated['city'] ?? ''),
            'phone' => (string) ($validated['phone'] ?? ''),
            'email' => (string) ($validated['email'] ?? ''),
            'address' => (string) ($validated['address'] ?? ''),
            'active' => (bool) ($validated['active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeActive(array $validated): array
    {
        if (array_key_exists('active', $validated)) {
            $validated['active'] = filter_var($validated['active'], FILTER_VALIDATE_BOOLEAN);
        }

        return $validated;
    }
}
