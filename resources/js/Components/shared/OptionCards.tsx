import { cn } from '@/lib/utils';

interface OptionCardsProps<V extends string> {
    name: string;
    label: string;
    value: V;
    onChange: (value: V) => void;
    options: { value: V; label: string; hint?: string }[];
    disabled?: boolean;
    columns?: 2 | 4;
}

/** Pilihan tombol besar (radio): terpilih = tint biru + ring biru. */
export default function OptionCards<V extends string>({ name, label, value, onChange, options, disabled, columns = 4 }: OptionCardsProps<V>) {
    return (
        <fieldset className="grid gap-2" disabled={disabled}>
            <legend className="mb-2 text-[13px] font-medium text-ink">{label}</legend>
            <div className={cn('grid grid-cols-2 gap-2', columns === 4 && 'sm:grid-cols-4')}>
                {options.map((o) => {
                    const aktif = o.value === value;
                    return (
                        <label
                            key={o.value}
                            className={cn(
                                'flex min-h-12 cursor-pointer flex-col justify-center rounded-10 border px-3 py-2 text-sm transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pln-blue/25',
                                aktif
                                    ? 'border-pln-blue bg-pln-blue-light font-medium text-pln-blue-dark ring-1 ring-pln-blue'
                                    : 'border-border bg-white text-ink hover:bg-table-head',
                                disabled && 'cursor-not-allowed opacity-60',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={o.value}
                                checked={aktif}
                                onChange={() => onChange(o.value)}
                                className="sr-only"
                            />
                            {o.label}
                            {o.hint && <span className="text-xs font-normal text-muted-ink">{o.hint}</span>}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
