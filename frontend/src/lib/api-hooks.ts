import { useMutation, useQueryClient, type UseMutationOptions } from '@tanstack/react-query';
import { toast } from 'sonner';
import type { AxiosError } from 'axios';

/**
 * Humanize a Laravel dot-notation field name.
 * e.g. "settings.26.value" → "Setting #27 value"
 */
function humanizeFieldName(field: string): string {
    return field
        .replace(/^settings\.(\d+)\.(\w+)$/, (_, index, prop) => `Setting #${Number(index) + 1} ${prop}`)
        .replace(/\./g, ' ')
        .replace(/_/g, ' ')
        .replace(/^\w/, (c) => c.toUpperCase());
}

/**
 * Standardize API error messages.
 * When the response contains a Laravel validation `errors` object,
 * each field error is extracted and humanized instead of showing
 * the raw summary like "The settings.26.value field is required. (and 2 more errors)".
 */
export const getErrorMessage = (error: unknown): string => {
    if (error && typeof error === 'object' && 'isAxiosError' in error) {
        const axiosError = error as AxiosError<{
            message?: string;
            error?: string;
            errors?: Record<string, string[]>;
        }>;
        const data = axiosError.response?.data;

        if (data?.errors && typeof data.errors === 'object') {
            const lines: string[] = [];
            for (const [field, messages] of Object.entries(data.errors)) {
                const label = humanizeFieldName(field);
                for (const msg of messages) {
                    // Replace the raw dot-notation field in the message itself
                    const cleaned = msg.replace(field, label.toLowerCase());
                    lines.push(cleaned);
                }
            }
            if (lines.length) {
                return lines.join('\n');
            }
        }

        return data?.message || data?.error || axiosError.message;
    }
    return error instanceof Error ? error.message : 'An unexpected error occurred';
};

/**
 * Enhanced useMutation wrapper with toast integration
 */
export function useApiMutation<TData = unknown, TVariables = unknown>(
    options: UseMutationOptions<TData, Error, TVariables> & {
        successMessage?: string | ((data: TData) => string);
        errorMessage?: string | ((error: Error) => string);
        invalidateQueries?: string[][]; // Array of query keys to invalidate on success
    }
) {
    const queryClient = useQueryClient();

    return useMutation<TData, Error, TVariables>({
        ...options,
        onSuccess: (data, variables, context) => {
            // Show toast
            if (options.successMessage) {
                const message = typeof options.successMessage === 'function' 
                    ? options.successMessage(data) 
                    : options.successMessage;
                toast.success(message);
            }

            // Invalidate queries
            if (options.invalidateQueries?.length) {
                options.invalidateQueries.forEach((queryKey) => {
                    queryClient.invalidateQueries({ queryKey });
                });
            }

            // Call original onSuccess if provided
            if (options.onSuccess) {
                options.onSuccess(data, variables, context);
            }
        },
        onError: (error, variables, context) => {
            // Show toast
            if (options.errorMessage) {
                const message = typeof options.errorMessage === 'function'
                    ? options.errorMessage(error)
                    : options.errorMessage;
                toast.error(message);
            } else {
                toast.error(getErrorMessage(error));
            }

            // Call original onError if provided
            if (options.onError) {
                options.onError(error, variables, context);
            }
        },
    });
}
