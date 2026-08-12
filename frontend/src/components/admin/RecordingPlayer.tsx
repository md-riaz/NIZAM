import { AlertCircle, Loader2, Play, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { fetchRecordingObjectUrl, revokeObjectUrl } from '@/lib/media';
import { getErrorMessage } from '@/lib/api-hooks';

interface RecordingPlayerProps {
    /** Organization-scoped download URL, e.g. `organizations/x/recordings/y/download`. */
    downloadUrl: string;
    /** Stored format, used to pick a MIME type the browser will decode. */
    format?: string | null;
    /** Rendered compactly for use inside a table row. */
    compact?: boolean;
}

/**
 * Plays a call recording inline.
 *
 * Audio is fetched lazily on first play rather than on mount: a call-history or
 * recordings page can show dozens of rows, and eagerly pulling every file would
 * be wasteful. Until then the control is just a play button.
 */
export function RecordingPlayer({ downloadUrl, format, compact = false }: RecordingPlayerProps) {
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const isMountedRef = useRef(true);

    useEffect(() => {
        isMountedRef.current = true;
        return () => {
            isMountedRef.current = false;
        };
    }, []);

    // Revoke the blob when this row unmounts or the target changes, otherwise
    // paging through a long list leaks every file that was played.
    useEffect(() => {
        return () => revokeObjectUrl(objectUrl);
    }, [objectUrl]);

    useEffect(() => {
        setObjectUrl(null);
        setError(null);
    }, [downloadUrl]);

    const load = async () => {
        if (objectUrl || isLoading) return;

        setIsLoading(true);
        setError(null);

        try {
            const url = await fetchRecordingObjectUrl(downloadUrl, format);

            // A row can be unmounted while its audio is still in flight — paging
            // away mid-download. The blob is already allocated at this point, so
            // it has to be released here; the revoke effect above will never run
            // for a URL that was never committed to state.
            if (!isMountedRef.current) {
                revokeObjectUrl(url);
                return;
            }

            setObjectUrl(url);
        } catch (caught) {
            if (isMountedRef.current) {
                setError(getErrorMessage(caught));
            }
        } finally {
            if (isMountedRef.current) {
                setIsLoading(false);
            }
        }
    };

    // Autoplay once the blob resolves, so one click means "play" rather than
    // "load, then click again".
    useEffect(() => {
        if (objectUrl && audioRef.current) {
            void audioRef.current.play().catch(() => {
                // Autoplay can be blocked by the browser; the visible controls
                // still work, so there is nothing to recover from here.
            });
        }
    }, [objectUrl]);

    if (error) {
        // A failed fetch is usually transient (expired token, network blip), so
        // the control stays actionable instead of becoming dead text.
        return (
            <div className="inline-flex items-center gap-1.5">
                <span
                    className="inline-flex items-center gap-1.5 text-xs text-destructive"
                    title={error}
                >
                    <AlertCircle className="size-3.5 shrink-0" />
                    {compact ? 'Unavailable' : error}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={() => {
                        setError(null);
                        void load();
                    }}
                    aria-label="Retry loading recording"
                >
                    <RotateCcw className="size-3.5" />
                </Button>
            </div>
        );
    }

    if (objectUrl) {
        return (
            <audio
                ref={audioRef}
                src={objectUrl}
                controls
                preload="auto"
                className={compact ? 'h-8 w-[220px]' : 'h-9 w-full'}
            />
        );
    }

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={() => void load()}
            disabled={isLoading}
            className="h-8"
            aria-label="Play recording"
        >
            {isLoading ? (
                <Loader2 className="size-3.5 animate-spin" />
            ) : (
                <Play className="size-3.5" />
            )}
            {compact ? null : <span className="ml-1.5">Play</span>}
        </Button>
    );
}
