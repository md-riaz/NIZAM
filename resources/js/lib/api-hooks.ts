import { useMutation, useQueryClient, type UseMutationOptions } from '@tanstack/react-query';
import { toast } from 'sonner';
import type { AxiosError } from 'axios';

/**
 * Standardize API error messages
 */
export const getErrorMessage = (error: unknown): string => {
    if (error && typeof error === 'object' && 'isAxiosError' in error) {
        const axiosError = error as AxiosError<{ message?: string; error?: string }>;
        return axiosError.response?.data?.message || axiosError.response?.data?.error || axiosError.message;
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
