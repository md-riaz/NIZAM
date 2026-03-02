import './bootstrap';

const root = document.documentElement;
const themeKey = 'nizam-theme';

const applyTheme = (theme) => {
    root.classList.toggle('dark', theme === 'dark');
};

const storedTheme = localStorage.getItem(themeKey);
applyTheme(storedTheme ?? 'light');

window.toggleTheme = () => {
    const nextTheme = root.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem(themeKey, nextTheme);
    applyTheme(nextTheme);
};

document.addEventListener('htmx:configRequest', (event) => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrf) {
        event.detail.headers['X-CSRF-TOKEN'] = csrf;
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const dashboard = document.querySelector('[data-dashboard-stream]');
    if (!dashboard) {
        return;
    }

    const MAX_SEEN_EVENTS = 500;
    const RECONNECT_DELAY_MS = 1500;
    let socket;
    let reconnectTimer;
    const seen = new Set();
    const seenOrder = [];

    const applyMetric = (name, value) => {
        const target = document.querySelector(`[data-metric="${name}"]`);
        if (target) {
            target.textContent = value;
        }
    };

    const connect = () => {
        const url = dashboard.dataset.dashboardStream;
        const jwt = dashboard.dataset.jwt;

        if (!url || !jwt) {
            return;
        }

        socket = new WebSocket(url);

        socket.addEventListener('open', () => {
            socket.send(JSON.stringify({ type: 'auth', token: jwt }));
        });

        socket.addEventListener('message', (event) => {
            const payload = JSON.parse(event.data);
            if (!payload || typeof payload !== 'object') {
                return;
            }

            const fallbackId = payload.type && payload.tenant_id && payload.timestamp
                ? `${payload.type}-${payload.tenant_id}-${payload.timestamp}`
                : null;
            const id = payload.id ?? fallbackId;
            if (!id) {
                return;
            }
            if (seen.has(id)) {
                return;
            }
            seen.add(id);
            seenOrder.push(id);
            if (seen.size > MAX_SEEN_EVENTS) {
                const oldest = seenOrder.shift();
                if (oldest) {
                    seen.delete(oldest);
                }
            }

            if (payload.metrics) {
                Object.entries(payload.metrics).forEach(([key, value]) => applyMetric(key, value));
            }
        });

        socket.addEventListener('close', () => {
            reconnectTimer = window.setTimeout(connect, RECONNECT_DELAY_MS);
        });
    };

    connect();

    window.addEventListener('beforeunload', () => {
        window.clearTimeout(reconnectTimer);
        socket?.close();
    });
});
