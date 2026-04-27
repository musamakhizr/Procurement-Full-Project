import { createContext, useContext, useState, ReactNode, useEffect } from 'react';
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

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const initializeSession = async () => {
      try {
        const currentUser = await fetchCurrentUser();
        setUser(currentUser);
      } catch (error) {
        clearToken();
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    };

    initializeSession();
  }, []);

  const signIn = async (email: string, password: string) => {
    const response = await signInRequest({ email, password });
    persistToken(response.token);
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
    setUser(response.user);
  };

  const signOut = async () => {
    try {
      await signOutRequest();
    } catch (error) {
      // Ignore logout failures and still clear local state.
    }

    clearToken();
    setUser(null);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user,
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
