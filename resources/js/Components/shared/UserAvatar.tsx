import { cn, initials } from '@/lib/utils';

interface UserAvatarProps {
    name: string;
    src?: string | null;
    size?: number;
    /** Varian gelap dipakai di kartu juara. */
    tone?: 'light' | 'dark';
    className?: string;
}

export default function UserAvatar({ name, src, size = 36, tone = 'light', className }: UserAvatarProps) {
    const style = { width: size, height: size, fontSize: Math.max(11, Math.round(size * 0.36)) };

    if (src) {
        return (
            <img
                src={src}
                alt={`Foto ${name}`}
                style={style}
                className={cn('shrink-0 rounded-full object-cover ring-1 ring-border', className)}
            />
        );
    }

    return (
        <span
            aria-hidden
            style={style}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full font-semibold select-none',
                tone === 'light' ? 'bg-pln-blue-light text-pln-blue-dark' : 'bg-ink text-white',
                className,
            )}
        >
            {initials(name)}
        </span>
    );
}
