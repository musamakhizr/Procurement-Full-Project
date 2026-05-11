import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { AlertCircle, CheckCircle2, ChevronRight, Clock, FileText, Package, Plus } from 'lucide-react';
import { fetchQuoteRequests, QuoteRequest } from '../api';

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: React.ElementType }> = {
  submitted: { label: 'Submitted', color: 'bg-amber-100 text-amber-700', icon: Clock },
  accepted: { label: 'Accepted', color: 'bg-emerald-100 text-emerald-700', icon: CheckCircle2 },
  rejected: { label: 'Rejected', color: 'bg-red-100 text-red-700', icon: AlertCircle },
};

function StatusBadge({ status }: { status: string }) {
  const config = STATUS_CONFIG[status] ?? { label: status, color: 'bg-slate-100 text-slate-600', icon: Clock };
  const Icon = config.icon;

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${config.color}`}>
      <Icon className="h-3.5 w-3.5" />
      {config.label}
    </span>
  );
}

function PaginationControls({
  currentPage,
  lastPage,
  onPageChange,
}: {
  currentPage: number;
  lastPage: number;
  onPageChange: (page: number) => void;
}) {
  if (lastPage <= 1) {
    return null;
  }

  return (
    <div className="mt-6 flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
      <button
        onClick={() => onPageChange(Math.max(1, currentPage - 1))}
        disabled={currentPage <= 1}
        className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF] disabled:cursor-not-allowed disabled:text-slate-300"
      >
        Previous
      </button>
      <span className="text-sm font-semibold text-slate-600">Page {currentPage} of {lastPage}</span>
      <button
        onClick={() => onPageChange(Math.min(lastPage, currentPage + 1))}
        disabled={currentPage >= lastPage}
        className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF] disabled:cursor-not-allowed disabled:text-slate-300"
      >
        Next
      </button>
    </div>
  );
}

export function MyQuoteRequestsPage() {
  const navigate = useNavigate();
  const [quoteRequests, setQuoteRequests] = useState<QuoteRequest[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    setIsLoading(true);

    fetchQuoteRequests(page)
      .then((response) => {
        setQuoteRequests(response.data);
        setLastPage(response.meta.last_page);
      })
      .finally(() => setIsLoading(false));
  }, [page]);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="mx-auto max-w-[1100px] px-6">
        <div className="mb-6 flex items-center gap-2 text-sm text-slate-500">
          <Link to="/" className="transition-colors hover:text-[#4F6BFF]">Home</Link>
          <ChevronRight className="h-3.5 w-3.5" />
          <Link to="/dashboard" className="transition-colors hover:text-[#4F6BFF]">Dashboard</Link>
          <ChevronRight className="h-3.5 w-3.5" />
          <span className="font-medium text-slate-900">Catalog Quote Requests</span>
        </div>

        <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="mb-1 text-3xl font-bold text-slate-900">Catalog Quote Requests</h1>
            <p className="text-slate-600">Review product quote requests created from your procurement list.</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Link to="/my-requests" className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF]">
              Custom Requests
            </Link>
            <button
              onClick={() => navigate('/marketplace')}
              className="flex items-center gap-2 rounded-xl bg-[#4F6BFF] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#3D56E0]"
            >
              <Plus className="h-4 w-4" />
              New Quote
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">Loading catalog quote requests...</div>
        ) : quoteRequests.length === 0 ? (
          <div className="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-16 text-center">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
              <FileText className="h-8 w-8 text-slate-400" />
            </div>
            <h3 className="mb-2 text-xl font-bold text-slate-900">No catalog quotes yet</h3>
            <p className="mx-auto mb-6 max-w-sm text-slate-500">Browse the catalog, add products to your procurement list, then submit a fresh quote request.</p>
            <button onClick={() => navigate('/marketplace')} className="rounded-xl bg-[#4F6BFF] px-6 py-3 font-semibold text-white transition-colors hover:bg-[#3D56E0]">
              Browse Catalog
            </button>
          </div>
        ) : (
          <>
            <div className="space-y-4">
              {quoteRequests.map((request) => (
                <div key={request.id} className="rounded-2xl border border-slate-200 bg-white p-6 transition-all hover:border-[#4F6BFF]/40 hover:shadow-sm">
                  <div className="mb-4 flex flex-wrap items-center gap-3">
                    <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-500">{request.reference}</span>
                    <span className="rounded bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">Catalog Quote</span>
                    <StatusBadge status={request.status} />
                  </div>

                  <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <h3 className="font-semibold text-slate-900">{request.items.length} selected product{request.items.length === 1 ? '' : 's'}</h3>
                      <p className="text-sm text-slate-500">Submitted {new Date(request.created_at).toLocaleDateString()}</p>
                    </div>
                    <div className="text-left sm:text-right">
                      <p className="text-sm text-slate-500">Estimated subtotal</p>
                      <p className="text-xl font-bold text-[#4F6BFF]">¥{request.subtotal.toFixed(2)}</p>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {request.items.slice(0, 6).map((item) => (
                      <div key={item.id} className="flex gap-3 rounded-2xl bg-slate-50 p-3">
                        <div className="h-14 w-14 flex-shrink-0 overflow-hidden rounded-xl bg-white">
                          <img src={item.image ?? 'https://placehold.co/120x120?text=Product'} alt={item.product_name} className="h-full w-full object-cover" />
                        </div>
                        <div className="min-w-0">
                          <p className="truncate text-sm font-semibold text-slate-900">{item.product_name}</p>
                          <p className="text-xs text-slate-500">Qty {item.quantity.toLocaleString()} · ¥{item.unit_price.toFixed(2)}</p>
                          {item.variant_label && <p className="truncate text-xs font-semibold text-[#F97316]">{item.variant_label}</p>}
                        </div>
                      </div>
                    ))}
                  </div>

                  {request.items.length > 6 && <p className="mt-3 text-xs text-slate-500">+{request.items.length - 6} more item{request.items.length - 6 === 1 ? '' : 's'} saved in this quote.</p>}
                  {request.notes && <p className="mt-3 text-sm text-slate-600">Notes: {request.notes}</p>}
                </div>
              ))}
            </div>

            <PaginationControls currentPage={page} lastPage={lastPage} onPageChange={setPage} />
          </>
        )}
      </div>
    </div>
  );
}
