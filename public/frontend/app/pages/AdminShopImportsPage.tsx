import { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, RefreshCcw, Store } from 'lucide-react';
import { fetchAdminShopImports, MarketplaceShopImport, PaginatedResponse } from '../api';

export function AdminShopImportsPage() {
  const [shopImports, setShopImports] = useState<MarketplaceShopImport[]>([]);
  const [pagination, setPagination] = useState<PaginatedResponse<MarketplaceShopImport>['meta']>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  });
  const [isLoading, setIsLoading] = useState(false);

  const loadShopImports = async (page = pagination.current_page) => {
    setIsLoading(true);

    try {
      const response = await fetchAdminShopImports(page, 10);
      setShopImports(response.data);
      setPagination(response.meta);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadShopImports(1);
  }, []);

  const pageNumbers = useMemo(() => {
    return Array.from({ length: pagination.last_page }, (_, index) => index + 1);
  }, [pagination.last_page]);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="mx-auto max-w-[1600px] px-6">
        <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <div className="mb-3 flex items-center gap-3">
              <Store className="h-8 w-8 text-[#7C3AED]" />
              <h1 className="text-4xl font-bold text-slate-900">Recent shop imports</h1>
            </div>
            <p className="text-lg text-slate-600">Seed URL resolution, shop discovery, and product import status.</p>
          </div>
          <button
            onClick={() => void loadShopImports(pagination.current_page)}
            disabled={isLoading}
            className="inline-flex items-center justify-center gap-2 rounded-xl border-2 border-slate-200 px-5 py-3 font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:bg-[#EEF2FF] disabled:cursor-not-allowed disabled:opacity-60"
          >
            <RefreshCcw className="h-4 w-4" />
            {isLoading ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>

        <div className="overflow-hidden rounded-2xl border-2 border-slate-200 bg-white">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="border-b-2 border-slate-200 bg-slate-50">
                <tr>
                  <th className="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Seed</th>
                  <th className="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">IDs</th>
                  <th className="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Status</th>
                  <th className="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Products</th>
                  <th className="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Error</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {shopImports.map((shopImport) => (
                  <tr key={shopImport.id} className="hover:bg-slate-50">
                    <td className="max-w-md px-6 py-4">
                      <p className="truncate text-sm font-semibold text-slate-900" title={shopImport.seed_url}>{shopImport.seed_url}</p>
                      <p className="mt-1 text-xs text-slate-500">{shopImport.seed_platform ?? 'platform pending'} / {shopImport.seed_num_iid ?? 'item pending'}</p>
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-600">
                      <p>Seller: {shopImport.seller_id ?? '-'}</p>
                      <p>Shop: {shopImport.shop_id ?? '-'}</p>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize ${
                        shopImport.status === 'completed'
                          ? 'bg-emerald-50 text-emerald-700'
                          : shopImport.status === 'failed'
                            ? 'bg-red-50 text-red-700'
                            : 'bg-blue-50 text-blue-700'
                      }`}>
                        {shopImport.status.replaceAll('_', ' ')}
                      </span>
                      <p className="mt-1 text-xs text-slate-500">
                        Stage: {shopImport.metadata?.failed_stage ?? shopImport.metadata?.current_stage ?? shopImport.status}
                      </p>
                    </td>
                    <td className="px-6 py-4 text-sm font-semibold text-slate-700">
                      {shopImport.imported_product_links}/{shopImport.total_product_links}
                    </td>
                    <td className="max-w-sm px-6 py-4">
                      {shopImport.error ? (
                        <p className="line-clamp-3 text-xs text-red-700" title={shopImport.error}>{shopImport.error}</p>
                      ) : (
                        <span className="text-xs text-slate-400">-</span>
                      )}
                    </td>
                  </tr>
                ))}
                {shopImports.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-6 py-12 text-center text-sm font-semibold text-slate-500">
                      No shop imports found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
            <p className="text-sm text-slate-500">
              {pagination.total > 0 ? (
                <>
                  Showing {(pagination.current_page - 1) * pagination.per_page + 1} to {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of {pagination.total} imports
                </>
              ) : 'Showing 0 imports'}
            </p>
            <div className="flex flex-wrap items-center gap-2">
              <button
                onClick={() => void loadShopImports(Math.max(pagination.current_page - 1, 1))}
                disabled={pagination.current_page === 1 || isLoading}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF] disabled:cursor-not-allowed disabled:opacity-50"
              >
                <ChevronLeft className="h-4 w-4" />
                Previous
              </button>
              {pageNumbers.map((pageNumber) => (
                <button
                  key={pageNumber}
                  onClick={() => void loadShopImports(pageNumber)}
                  disabled={isLoading}
                  className={`h-10 min-w-10 rounded-xl px-3 text-sm font-semibold transition-colors ${
                    pageNumber === pagination.current_page
                      ? 'bg-[#4F6BFF] text-white'
                      : 'border border-slate-200 text-slate-700 hover:border-[#4F6BFF] hover:text-[#4F6BFF]'
                  }`}
                >
                  {pageNumber}
                </button>
              ))}
              <button
                onClick={() => void loadShopImports(Math.min(pagination.current_page + 1, pagination.last_page))}
                disabled={pagination.current_page === pagination.last_page || isLoading}
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF] disabled:cursor-not-allowed disabled:opacity-50"
              >
                Next
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
