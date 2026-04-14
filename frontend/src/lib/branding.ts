const env = (import.meta as ImportMeta & {
    env?: Record<string, string | undefined>;
}).env ?? {};

const storagePrefix = env.VITE_STORAGE_PREFIX?.trim() || 'platform';

export const branding = {
    appName: env.VITE_APP_NAME?.trim() || 'Communications Platform',
    appTagline: env.VITE_APP_TAGLINE?.trim() || 'Communications Control Plane',
    appDescription:
        env.VITE_APP_DESCRIPTION?.trim()
        || 'Manage tenants, extensions, call flows, and communications infrastructure.',
    loginEmailPlaceholder:
        env.VITE_LOGIN_EMAIL_PLACEHOLDER?.trim() || 'admin@example.com',
    storagePrefix,
    storageKeys: {
        token: `${storagePrefix}.token`,
        activeTenant: `${storagePrefix}.activeTenant`,
        theme: `${storagePrefix}.theme`,
    },
} as const;

export function getStoredValue(
    key: keyof typeof branding.storageKeys,
): string | null {
    return localStorage.getItem(branding.storageKeys[key]);
}

export function setStoredValue(
    key: keyof typeof branding.storageKeys,
    value: string,
): void {
    localStorage.setItem(branding.storageKeys[key], value);
}

export function removeStoredValue(
    key: keyof typeof branding.storageKeys,
): void {
    localStorage.removeItem(branding.storageKeys[key]);
}
