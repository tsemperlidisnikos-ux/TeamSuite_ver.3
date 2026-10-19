import { useForm } from '@inertiajs/react';

const emptyFromFields = (fields) => {
    const data = {};
    fields.forEach((f) => {
        if (f.type === 'checkbox') {
            data[f.name] = false;
        } else if (f.default !== undefined) {
            data[f.name] = f.default;
        } else {
            data[f.name] = '';
        }
    });
    return data;
};

function FieldInput({ field, value, onChange, error }) {
    if (field.type === 'textarea') {
        return (
            <textarea
                className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-ts-fg"
                rows={3}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
            />
        );
    }
    if (field.type === 'checkbox') {
        return (
            <input
                type="checkbox"
                className="mt-2 rounded border-slate-600"
                checked={!!value}
                onChange={(e) => onChange(e.target.checked)}
            />
        );
    }
    if (field.type === 'select') {
        return (
            <select
                className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-ts-fg"
                value={value ?? ''}
                required={!!field.required}
                onChange={(e) => onChange(e.target.value)}
            >
                <option value="">—</option>
                {(field.options || []).map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <input
            type={field.type || 'text'}
            required={!!field.required}
            className="mt-1 w-full rounded-md border border-ts-border bg-ts-surface px-2 py-1.5 text-ts-fg"
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
        />
    );
}

export default function ResourceForm({
    fields,
    storeRoute,
    submitLabel = 'Προσθήκη',
    formTitle = null,
}) {
    const { data, setData, post, processing, reset, errors } = useForm(
        emptyFromFields(fields),
    );

    const submit = (e) => {
        e.preventDefault();
        post(route(storeRoute), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mb-6 rounded-lg border border-ts-border bg-ts-surface p-4"
        >
            {!formTitle && (
                <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-ts-muted">
                    Νέα εγγραφή
                </p>
            )}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {fields.map((field) => (
                    <label key={field.name} className="block text-sm">
                        <span className="text-ts-muted">
                            {field.label}
                            {field.required ? ' *' : ''}
                        </span>
                        <FieldInput
                            field={field}
                            value={data[field.name]}
                            onChange={(v) => setData(field.name, v)}
                            error={errors[field.name]}
                        />
                        {errors[field.name] && (
                            <span className="mt-0.5 block text-xs text-rose-400">
                                {errors[field.name]}
                            </span>
                        )}
                    </label>
                ))}
            </div>
            <button
                type="submit"
                disabled={processing}
                className="mt-4 rounded-md bg-ts-accent px-4 py-2 text-sm font-medium text-ts-accent-fg hover:bg-ts-accent-hover disabled:opacity-50"
            >
                {submitLabel}
            </button>
        </form>
    );
}

export { FieldInput };
