import { cn } from '@/lib/utils';

interface SegmentedTabsProps<V extends string> {
    value: V;
    onChange: (value: V) => void;
    items: { value: V; label: string; count?: number }[];
    label: string;
    className?: string;
}

/** Wadah canvas rounded-10, tab aktif putih + bayangan tipis. */
export default function SegmentedTabs<V extends string>({ value, onChange, items, label, className }: SegmentedTabsProps<V>) {
    return (
        <div role="tablist" aria-label={label} className={cn('inline-flex max-w-full gap-1 overflow-x-auto rounded-10 bg-canvas p-1', className)}>
            {items.map((item) => {
                const active = item.value === value;
                return (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => onChange(item.value)}
                        className={cn(
                            'inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md px-3 text-[13px] whitespace-nowrap transition-colors',
                            active
                                ? 'bg-white font-medium text-ink shadow-[0_1px_2px_rgba(15,27,42,0.1)]'
                                : 'text-muted-ink hover:text-ink',
                        )}
                    >
                        {item.label}
                        {item.count !== undefined && (
                            <span className={cn('text-xs', active ? 'text-muted-ink' : 'text-muted-ink/80')}>{item.count}</span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
