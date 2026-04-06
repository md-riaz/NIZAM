import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import api from '@/lib/api';
import type { AuthResponse, LoginRequest, User } from '@/types/auth';

// ─── Context Shape ───────────────────────────────────────────

interface AuthContextValue {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    login: (credentials: LoginRequest) => Promise<void>;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

// ─── Provider ────────────────────────────────────────────────

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [token, setToken] = useState<string | null>(
        () => localStorage.getItem('nizam_token'),
    );
    const [isLoading, setIsLoading] = useState(true);

    // Hydrate session on mount
    useEffect(() => {
        if (!token) {
            setIsLoading(false);
            return;
        }

        api.get<{ user: User }>('auth/me')
            .then((res) => setUser(res.data.user))
            .catch(() => {
                localStorage.removeItem('nizam_token');
                setToken(null);
            })
            .finally(() => setIsLoading(false));
    }, [token]);

    const login = useCallback(async (credentials: LoginRequest) => {
        const { data } = await api.post<AuthResponse>('auth/login', credentials);
        localStorage.setItem('nizam_token', data.token);
        setToken(data.token);
        setUser(data.user);
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('auth/logout');
        } finally {
            localStorage.removeItem('nizam_token');
            setToken(null);
            setUser(null);
        }
    }, []);

    const value = useMemo<AuthContextValue>(
        () => ({
            user,
            token,
            isAuthenticated: !!user,
            isLoading,
            login,
            logout,
        }),
        [user, token, isLoading, login, logout],
    );

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

// ─── Hook ────────────────────────────────────────────────────

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}
