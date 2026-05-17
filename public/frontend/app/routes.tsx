import { createBrowserRouter } from 'react-router';
import { RootLayout } from './layouts/RootLayout';
import { HomePage } from './pages/HomePage';
import { DashboardPage } from './pages/DashboardPage';
import { MarketplacePage } from './pages/MarketplacePage';
import { ProductDetailPage } from './pages/ProductDetailPage';
import { SignInPage } from './pages/SignInPage';
import { GetStartedPage } from './pages/GetStartedPage';
import { SourcingPage } from './pages/SourcingPage';
import { MyRequestsPage } from './pages/MyRequestsPage';
import { MyQuoteRequestsPage } from './pages/MyQuoteRequestsPage';
import { AdminProductsPage } from './pages/AdminProductsPage';
import { AdminShopImportsPage } from './pages/AdminShopImportsPage';
import { AdminRequestsPage } from './pages/AdminRequestsPage';
import { AdminQuoteRequestsPage } from './pages/AdminQuoteRequestsPage';
import { ProcurementListPage } from './pages/ProcurementListPage';
import { LanguageProvider } from './contexts/LanguageContext';
import { AuthProvider } from './contexts/AuthContext';
import { ProcurementListProvider } from './contexts/ProcurementListContext';
import { ProtectedRoute } from './components/ProtectedRoute';

// Wrapper component to provide context to RootLayout
function RootWithProviders() {
  return (
    <LanguageProvider>
      <AuthProvider>
        <ProcurementListProvider>
          <RootLayout />
        </ProcurementListProvider>
      </AuthProvider>
    </LanguageProvider>
  );
}

export const router = createBrowserRouter([
  {
    path: '/',
    element: <RootWithProviders />,
    children: [
      {
        index: true,
        Component: HomePage,
      },
      {
        path: 'dashboard',
        element: (
          <ProtectedRoute>
            <DashboardPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'marketplace',
        Component: MarketplacePage,
      },
      {
        path: 'marketplace/product/:id',
        Component: ProductDetailPage,
      },
      {
        path: 'procurement-list',
        element: (
          <ProtectedRoute>
            <ProcurementListPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'sourcing',
        element: (
          <ProtectedRoute>
            <SourcingPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'my-requests',
        element: (
          <ProtectedRoute>
            <MyRequestsPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'my-quote-requests',
        element: (
          <ProtectedRoute>
            <MyQuoteRequestsPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'sign-in',
        Component: SignInPage,
      },
      {
        path: 'get-started',
        Component: GetStartedPage,
      },
      {
        path: 'admin/requests',
        element: (
          <ProtectedRoute adminOnly>
            <AdminRequestsPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'admin/quote-requests',
        element: (
          <ProtectedRoute adminOnly>
            <AdminQuoteRequestsPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'admin/products',
        element: (
          <ProtectedRoute adminOnly>
            <AdminProductsPage />
          </ProtectedRoute>
        ),
      },
      {
        path: 'admin/shop-imports',
        element: (
          <ProtectedRoute adminOnly>
            <AdminShopImportsPage />
          </ProtectedRoute>
        ),
      },
    ],
  },
]);
