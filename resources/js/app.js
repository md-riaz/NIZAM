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

const dashboard = document.querySelector('[data-dashboard-stream]');
if (dashboard) {
    let socket;
    let reconnectTimer;
    const seen = new Set();

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

        socket = new WebSocket(`${url}${url.includes('?') ? '&' : '?'}token=${encodeURIComponent(jwt)}`);

        socket.addEventListener('message', (event) => {
            const payload = JSON.parse(event.data);
            const id = payload?.id ?? `${payload?.type}-${payload?.tenant_id}-${payload?.timestamp}`;
            if (seen.has(id)) {
                return;
            }
            seen.add(id);
            if (seen.size > 500) {
                seen.clear();
            }

            if (payload.metrics) {
                Object.entries(payload.metrics).forEach(([key, value]) => applyMetric(key, value));
            }
        });

        socket.addEventListener('close', () => {
            reconnectTimer = window.setTimeout(connect, 1500);
        });
    };

    connect();

    window.addEventListener('beforeunload', () => {
        window.clearTimeout(reconnectTimer);
        socket?.close();
    });
}
