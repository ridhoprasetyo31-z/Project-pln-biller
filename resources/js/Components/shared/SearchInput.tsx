import { Search, X } from 'lucide-react';
import type { ComponentProps } from 'react';

import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

interface SearchInputProps extends Omit<ComponentProps<'input'>, 'onChange' | 'value'> {
    value: string;
    onChange: (value: string) => void;
}

export default function SearchInput({ value, onChange, className, placeholder = 'Cari', ...props }: SearchInputProps) {
    return (
        <div className={cn('relative', className)}>
            <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-ink" />
            <Input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                className="pr-10 pl-9 [&::-webkit-search-cancel-button]:hidden"
                {...props}
            />
            {value && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    className="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center text-muted-ink hover:text-ink"
                >
                    <X className="size-4" />
                    <span className="sr-only">Hapus pencarian</span>
                </button>
            )}
        </div>
    );
}
