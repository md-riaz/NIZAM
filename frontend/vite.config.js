import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { defineConfig, loadEnv } from 'vite';

const brandingDefaults = {
    VITE_APP_NAME: 'Communications Platform',
    VITE_APP_TAGLINE: 'Communications Control Plane',
    VITE_APP_DESCRIPTION: 'Manage tenants, extensions, call flows, and communications infrastructure.',
};

function trimTrailingSlash(value) {
    return value.replace(/\/+$/, '');
}

function resolveApiUrl(env) {
    const explicitApiUrl = env.VITE_API_URL?.trim();
    if (explicitApiUrl) {
        return trimTrailingSlash(explicitApiUrl);
    }

    const appUrl = env.APP_URL?.trim();
    if (appUrl) {
        return `${trimTrailingSlash(appUrl)}/api/v1`;
    }

    return '';
}

export default defineConfig(({ mode }) => {
    const envDir = path.resolve(__dirname, '..');
    const env = loadEnv(mode, envDir, '');
    const htmlEnv = {
        ...brandingDefaults,
        ...env,
        VITE_APP_NAME: env.VITE_APP_NAME || env.APP_NAME || brandingDefaults.VITE_APP_NAME,
        VITE_APP_TAGLINE: env.VITE_APP_TAGLINE || brandingDefaults.VITE_APP_TAGLINE,
        VITE_APP_DESCRIPTION: env.VITE_APP_DESCRIPTION || brandingDefaults.VITE_APP_DESCRIPTION,
        VITE_API_URL: resolveApiUrl(env),
    };
    const htmlTitle = `${htmlEnv.VITE_APP_NAME} — ${htmlEnv.VITE_APP_TAGLINE}`;

    return {
        envDir,
        plugins: [
            {
                name: 'html-branding-fallbacks',
                transformIndexHtml(html) {
                    return html
                        .replace(/%APP_HTML_TITLE%/g, htmlTitle)
                        .replace(/%APP_HTML_DESCRIPTION%/g, htmlEnv.VITE_APP_DESCRIPTION);
                },
            },
            tailwindcss(),
            react({
                babel: {
                    plugins: [
                        ["babel-plugin-react-compiler", { target: "19" }],
                    ],
                },
            }),
        ],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'src'),
            },
        },
        server: {
            host: '0.0.0.0',
            port: 5173,
            watch: {
                usePolling: true,
            },
        },
    };
});
