<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesClubResources;
use App\Http\Controllers\Controller;
use App\Support\AdminClubResources;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

abstract class ClubResourceController extends Controller
{
    use HandlesClubResources;

    abstract protected function resourceKey(): string;

    protected function config(): array
    {
        return AdminClubResources::get($this->resourceKey());
    }

    public function index(Request $request): Response
    {
        $config = $this->config();
        $columnKeys = array_column($config['columns'], 'key');
        $fieldKeys = array_values(array_unique(array_merge(
            array_column($config['fields'], 'name'),
            ['meta', 'starts_at', 'ends_at', 'academy_class_id', 'score_for', 'score_against', 'home_away', 'status', 'title', 'notes', 'stock_qty', 'sku', 'direction'],
        )));
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
            $paginator->getCollection()->map(fn (array $row) => $this->enrichIndexRow($row, $config['model'])),
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

    /**
     * @param  array<string, mixed>  $row
     * @param  class-string  $modelClass
     * @return array<string, mixed>
     */
    protected function enrichIndexRow(array $row, string $modelClass): array
    {
        $key = $this->resourceKey();

        if ($key === 'trainings') {
            $starts = $row['starts_at'] ?? null;
            $ends = $row['ends_at'] ?? null;
            $startsDt = is_string($starts) ? Carbon::parse($starts) : null;
            $endsDt = is_string($ends) ? Carbon::parse($ends) : null;
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $row['date_label'] = $startsDt?->locale('el')->translatedFormat('d M Y') ?? '—';
            $row['start_time'] = $startsDt?->format('H:i') ?? '—';
            $row['end_time'] = $endsDt?->format('H:i') ?? '—';
            $row['location'] = $row['title'] ?? ($meta['location'] ?? '—');
            $row['sport'] = $meta['sport'] ?? '—';
            $row['class_name'] = $meta['class_name'] ?? '—';
            $row['notes'] = $row['notes'] ?? '—';
        }

        if ($key === 'matches') {
            $starts = $row['starts_at'] ?? null;
            $startsDt = is_string($starts) ? Carbon::parse($starts) : null;
            $homeAway = [
                'home' => 'Εντός',
                'away' => 'Εκτός',
                'neutral' => 'Ουδέτερο',
            ];
            $statusLabels = [
                'scheduled' => 'Προγραμματισμένος',
                'played' => 'Ολοκληρώθηκε',
                'cancelled' => 'Ακυρωμένος',
                'postponed' => 'Αναβληθείς',
            ];
            $for = $row['score_for'] ?? null;
            $against = $row['score_against'] ?? null;
            $row['date_label'] = $startsDt
                ? $startsDt->locale('el')->translatedFormat('d/m/Y').($startsDt->format('H:i') !== '00:00' ? ' · '.$startsDt->format('H:i') : '')
                : '—';
            $row['class_name'] = $row['class_name'] ?? '—';
            $row['home_away_label'] = $homeAway[$row['home_away'] ?? ''] ?? ($row['home_away'] ?: '—');
            $row['status_label'] = $statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?: '—');
            $row['score'] = ($for !== null && $for !== '') || ($against !== null && $against !== '')
                ? trim(($for ?? '—').' – '.($against ?? '—'))
                : '—';
        }

        if ($key === 'warehouse') {
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $stock = (int) ($row['stock_qty'] ?? 0);
            $row['barcode'] = $meta['barcode'] ?? '—';
            $row['brand'] = $meta['brand'] ?? '—';
            $row['category'] = $meta['category'] ?? '—';
            $row['size_color'] = $meta['size_color'] ?? ($meta['size'] ?? '—');
            $row['imports'] = $meta['imports'] ?? 0;
            $row['exports'] = $meta['exports'] ?? 0;
            $row['available'] = $stock;
        }

        if ($key === 'protocol') {
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $row['direction_label'] = ($row['direction'] ?? '') === 'out' ? 'Εξερχόμενο' : (($row['direction'] ?? '') === 'in' ? 'Εισερχόμενο' : '—');
            $row['sport'] = $meta['sport'] ?? '—';
            $row['party'] = $meta['party'] ?? ($meta['sender'] ?? '—');
            $row['file_label'] = $meta['file'] ?? '—';
            $row['status_label'] = $meta['status'] ?? '—';
        }

        if (in_array($key, ['coaches', 'staff', 'members'], true)) {
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $row['photo_url'] = $row['photo_url'] ?? ($meta['photo_url'] ?? null);
        }

        return $row;
    }

    public function store(Request $request): RedirectResponse
    {
        $config = $this->config();
        $validated = $request->validate($config['rules']);
        $validated = $this->normalizeBooleans($validated, $config['fields']);

        if ($this->resourceKey() === 'matches' && array_key_exists('notes', $validated)) {
            $notes = $validated['notes'];
            unset($validated['notes']);
            $validated['meta'] = array_filter([
                'notes' => $notes,
            ], fn ($v) => $v !== null && $v !== '');
        }

        if ($this->resourceKey() === 'trainings' && empty($validated['title'])) {
            $validated['title'] = 'Προπόνηση';
        }

        $this->clubResourceStore($request, $config['model'], $validated);

        return $this->redirectToIndex($config['route'], 'Η εγγραφή δημιουργήθηκε.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $config = $this->config();
        $validated = $request->validate($config['rules']);
        $validated = $this->normalizeBooleans($validated, $config['fields']);

        if ($this->resourceKey() === 'matches' && array_key_exists('notes', $validated)) {
            $notes = $validated['notes'];
            unset($validated['notes']);
            $record = $this->clubQuery($config['model'], $request)->findOrFail($id);
            $meta = is_array($record->meta) ? $record->meta : [];
            $meta['notes'] = $notes;
            $validated['meta'] = $meta;
        }

        if ($this->resourceKey() === 'trainings' && empty($validated['title'])) {
            $validated['title'] = 'Προπόνηση';
        }

        $this->clubResourceUpdate($request, $config['model'], $id, $validated);

        return $this->redirectToIndex($config['route'], 'Η εγγραφή ενημερώθηκε.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $config = $this->config();

        $this->clubResourceDestroy($request, $config['model'], $id);

        return $this->redirectToIndex($config['route'], 'Η εγγραφή διαγράφηκε.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array{name: string, type?: string}>  $fields
     * @return array<string, mixed>
     */
    protected function normalizeBooleans(array $validated, array $fields): array
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? 'text') === 'checkbox') {
                $name = $field['name'];
                $validated[$name] = filter_var($validated[$name] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $validated;
    }
}
