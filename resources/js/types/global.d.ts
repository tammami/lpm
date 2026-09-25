import '@inertiajs/core';
import type { route as routeFn } from 'ziggy-js';
import type { SharedProps } from './index';

declare global {
    const route: typeof routeFn;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
        flashDataType: {
            toast?: { type: 'success' | 'error' | 'info' | 'warning'; message: string };
        };
    }
}
