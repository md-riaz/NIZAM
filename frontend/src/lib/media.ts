import api from '@/lib/api';

/**
 * Helpers for fetching authenticated binary responses.
 *
 * Recording downloads and CDR exports both sit behind Sanctum bearer auth and
 * are served as attachments, so neither can be pointed at directly from an
 * `<audio src>` or an `<a href>` — the browser would send no Authorization
 * header. Everything here goes through the shared axios client (which attaches
 * the token) and turns the response into an object URL or a save prompt.
 */

/** Map a stored recording format to a MIME type a browser will play. */
function audioMimeType(format?: string | null): string {
    switch ((format ?? '').toLowerCase()) {
        case 'mp3':
            return 'audio/mpeg';
        case 'ogg':
        case 'opus':
            return 'audio/ogg';
        case 'm4a':
        case 'mp4':
            return 'audio/mp4';
        case 'wav':
        default:
            // FreeSWITCH records WAV by default. Guessing wav for unknown
            // formats is safer than passing through an octet-stream type, which
            // some browsers refuse to decode.
            return 'audio/wav';
    }
}

/**
 * Fetch a recording and return a playable object URL.
 *
 * The whole file is pulled into memory rather than streamed. Call recordings are
 * small enough that this is a fair trade for not needing a separate unauthenticated
 * streaming endpoint, and it makes seeking work without range requests.
 *
 * The caller owns the returned URL and must revoke it (see `revokeObjectUrl`).
 */
export async function fetchRecordingObjectUrl(
    url: string,
    format?: string | null,
): Promise<string> {
    const response = await api.get<Blob>(url, { responseType: 'blob' });

    return URL.createObjectURL(
        new Blob([response.data], { type: audioMimeType(format) }),
    );
}

export function revokeObjectUrl(url: string | null): void {
    if (url) {
        URL.revokeObjectURL(url);
    }
}

/**
 * Fetch a binary endpoint and prompt the browser to save it.
 *
 * Used for recording downloads and CSV exports. Any filename the server sent in
 * Content-Disposition is ignored in favour of the caller's, since the export
 * endpoint's generated name is not meaningful to a user.
 */
export async function downloadAuthenticatedFile(
    url: string,
    filename: string,
    params?: Record<string, unknown>,
): Promise<void> {
    const response = await api.get<Blob>(url, { responseType: 'blob', params });
    const objectUrl = URL.createObjectURL(response.data);

    try {
        const anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

/** Format a byte count for display, e.g. 2.4 MB. */
export function formatFileSize(bytes?: number | null): string {
    if (bytes === null || bytes === undefined) return '—';
    if (bytes < 1024) return `${bytes} B`;

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(1)} ${units[unitIndex]}`;
}

/** Format a duration in seconds as m:ss, or h:mm:ss past an hour. */
export function formatDuration(seconds?: number | null): string {
    if (seconds === null || seconds === undefined) return '—';

    const total = Math.max(0, Math.round(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    return `${minutes}:${String(secs).padStart(2, '0')}`;
}
