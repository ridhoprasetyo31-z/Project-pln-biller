import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster(props: ToasterProps) {
    return (
        <Sonner
            position="top-right"
            closeButton
            toastOptions={{
                classNames: {
                    toast: 'rounded-14! border-border! font-sans! text-sm! text-ink! shadow-lg!',
                    error: 'bg-error-tint! text-error-deep! border-error-line!',
                    success: 'bg-white!',
                    description: 'text-muted-ink!',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
