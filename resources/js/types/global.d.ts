import type { route as routeFn } from 'ziggy-js';

declare global {
    // Disediakan oleh direktif Blade @routes (tightenco/ziggy).
    var route: typeof routeFn;
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
}

export {};
