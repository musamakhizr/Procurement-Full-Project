import { createContext, useContext, useState, ReactNode, useEffect } from 'react';
import axios from 'axios';
import {
  clearToken,
  fetchCurrentUser,
  persistToken,
  signIn as signInRequest,
  signOutRequest,
  signUp as signUpRequest,
  User,
} from '../api';

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, name: string, organizationName: string, organizationType: string) => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);
const AUTH_TOKEN_KEY = 'auth_token';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [hasToken, setHasToken] = useState(() => Boolean(localStorage.getItem(AUTH_TOKEN_KEY)));

  useEffect(() => {
    const initializeSession = async () => {
      const token = localStorage.getItem(AUTH_TOKEN_KEY);

      if (!token) {
        setHasToken(false);
        setIsLoading(false);
        return;
      }

      setHasToken(true);

      try {
        const currentUser = await fetchCurrentUser();
        setUser(currentUser);
      } catch (error) {
        if (axios.isAxiosError(error) && error.response && [401, 403, 419].includes(error.response.status)) {
          clearToken();
          setHasToken(false);
          setUser(null);
        }
      } finally {
        setIsLoading(false);
      }
    };

    initializeSession();
  }, []);

  const signIn = async (email: string, password: string) => {
    const response = await signInRequest({ email, password });
    persistToken(response.token);
    setHasToken(true);
    setUser(response.user);
  };

  const signUp = async (
    email: string,
    password: string,
    name: string,
    organizationName: string,
    organizationType: string
  ) => {
    const response = await signUpRequest({
      email,
      password,
      name,
      organization_name: organizationName,
      organization_type: organizationType,
    });
    persistToken(response.token);
    setHasToken(true);
    setUser(response.user);
  };

  const signOut = async () => {
    try {
      await signOutRequest();
    } catch (error) {
      // Ignore logout failures and still clear local state.
    }

    clearToken();
    setHasToken(false);
    setUser(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: hasToken,
        isLoading,
        signIn,
        signUp,
        signOut,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
