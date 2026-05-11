import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router';
import { AlertCircle, CheckCircle2, Clock3, FileText, Package, Search, UserRound, XCircle } from 'lucide-react';
import { fetchAdminQuoteRequests, QuoteRequest, updateAdminQuoteRequestStatus } from '../api';

const STATUS_STYLES: Record<string, { label: string; className: string; icon: React.ElementType }> = {
  submitted: { label: 'Submitted', className: 'bg-amber-100 text-amber-700', icon: Clock3 },
  accepted: { label: 'Accepted', className: 'bg-emerald-100 text-emerald-700', icon: CheckCircle2 },
  rejected: { label: 'Rejected', className: 'bg-red-100 text-red-700', icon: XCircle },
};

function StatusBadge({ status, statusLabel }: { status: string; statusLabel: string }) {
  const config = STATUS_STYLES[status] ?? {
    label: statusLabel,
    className: 'bg-slate-100 text-slate-700',
    icon: FileText,
  };
  const Icon = config.icon;

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${config.className}`}>
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
    <div className="flex items-center justify-between border-t border-slate-200 bg-white p-4">
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

export function AdminQuoteRequestsPage() {
  const [quoteRequests, setQuoteRequests] = useState<QuoteRequest[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [activeRequestId, setActiveRequestId] = useState<number | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [totalRequests, setTotalRequests] = useState(0);

  useEffect(() => {
    setIsLoading(true);

    fetchAdminQuoteRequests(page)
      .then((response) => {
        setQuoteRequests(response.data);
        setLastPage(response.meta.last_page);
        setTotalRequests(response.meta.total);
      })
      .finally(() => setIsLoading(false));
  }, [page]);

  const filteredQuoteRequests = useMemo(() => {
    const normalizedSearch = searchQuery.trim().toLowerCase();

    if (!normalizedSearch) {
      return quoteRequests;
    }

    return quoteRequests.filter((request) => {
      const haystack = [
        request.reference,
        request.notes,
        request.user?.name,
        request.user?.email,
        request.user?.organization_name,
        ...request.items.flatMap((item) => [item.product_name, item.product_sku, item.variant_label]),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      return haystack.includes(normalizedSearch);
    });
  }, [quoteRequests, searchQuery]);

  const summary = useMemo(() => {
    return quoteRequests.reduce(
      (accumulator, request) => {
        if (request.status === 'accepted') {
          accumulator.accepted += 1;
        } else if (request.status === 'rejected') {
          accumulator.rejected += 1;
        } else {
          accumulator.pending += 1;
        }

        return accumulator;
      },
      { pending: 0, accepted: 0, rejected: 0 }
    );
  }, [quoteRequests]);

  const handleStatusChange = async (requestId: number, status: 'accepted' | 'rejected') => {
    setActiveRequestId(requestId);

    try {
      const updatedRequest = await updateAdminQuoteRequestStatus(requestId, status);
      setQuoteRequests((currentRequests) =>
        currentRequests.map((request) => (request.id === requestId ? updatedRequest : request))
      );
    } finally {
      setActiveRequestId(null);
    }
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="mx-auto max-w-[1500px] px-6">
        <div className="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full bg-[#EEF2FF] px-4 py-1.5 text-sm font-semibold text-[#4F6BFF]">
              <Package className="h-4 w-4" />
              Admin Quote Management
            </div>
            <h1 className="text-4xl font-bold text-slate-900">Catalog Quote Requests</h1>
            <p className="mt-2 max-w-3xl text-slate-600">Accept or reject product quote requests submitted from procurement lists.</p>
          </div>

          <div className="flex w-full flex-col gap-3 lg:w-auto lg:flex-row">
            <Link to="/admin/requests" className="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-center text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF]">
              Custom Sourcing Requests
            </Link>
            <div className="relative w-full max-w-md">
              <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(event) => setSearchQuery(event.target.value)}
                placeholder="Search current page"
                className="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-12 pr-4 text-slate-900 outline-none transition-colors focus:border-[#4F6BFF]"
              />
            </div>
          </div>
        </div>

        <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Total Quotes</p>
            <p className="mt-2 text-3xl font-bold text-slate-900">{totalRequests}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Pending This Page</p>
            <p className="mt-2 text-3xl font-bold text-amber-600">{summary.pending}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Accepted This Page</p>
            <p className="mt-2 text-3xl font-bold text-emerald-600">{summary.accepted}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Rejected This Page</p>
            <p className="mt-2 text-3xl font-bold text-red-600">{summary.rejected}</p>
          </div>
        </div>

        <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
          {isLoading ? (
            <div className="p-12 text-center text-slate-500">Loading catalog quote requests...</div>
          ) : filteredQuoteRequests.length === 0 ? (
            <div className="p-12 text-center">
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                <AlertCircle className="h-7 w-7 text-slate-400" />
              </div>
              <h2 className="text-xl font-bold text-slate-900">No matching quotes</h2>
              <p className="mt-2 text-slate-500">Try a different search term or page.</p>
            </div>
          ) : (
            <div className="divide-y divide-slate-200">
              {filteredQuoteRequests.map((request) => {
                const isUpdating = activeRequestId === request.id;
                const isAccepted = request.status === 'accepted';
                const isRejected = request.status === 'rejected';

                return (
                  <div key={request.id} className="p-6">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                      <div className="min-w-0 flex-1">
                        <div className="mb-3 flex flex-wrap items-center gap-3">
                          <span className="rounded-md bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-600">{request.reference}</span>
                          <span className="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">Catalog Quote</span>
                          <StatusBadge status={request.status} statusLabel={request.status_label} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_260px]">
                          <div>
                            <h3 className="text-xl font-bold text-slate-900">{request.items.length} product{request.items.length === 1 ? '' : 's'} · ¥{request.subtotal.toFixed(2)}</h3>
                            <p className="mt-2 text-sm text-slate-600">Total quantity: {request.total_items.toLocaleString()} · Submitted {new Date(request.created_at).toLocaleDateString()}</p>
                            {request.notes && <p className="mt-2 text-sm text-slate-600">Notes: {request.notes}</p>}
                          </div>

                          <div className="rounded-2xl bg-slate-50 p-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</p>
                            <div className="flex items-start gap-2 text-sm text-slate-700">
                              <UserRound className="mt-0.5 h-4 w-4 flex-shrink-0 text-slate-400" />
                              <div>
                                <p className="font-semibold text-slate-900">{request.user?.name ?? 'Unknown user'}</p>
                                <p>{request.user?.email ?? 'No email available'}</p>
                                {request.user?.organization_name && <p>{request.user.organization_name}</p>}
                              </div>
                            </div>
                          </div>
                        </div>

                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                          {request.items.map((item) => (
                            <div key={item.id} className="flex gap-3 rounded-2xl bg-slate-50 p-3">
                              <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl bg-white">
                                <img src={item.image ?? 'https://placehold.co/120x120?text=Product'} alt={item.product_name} className="h-full w-full object-cover" />
                              </div>
                              <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-slate-900">{item.product_name}</p>
                                <p className="text-xs text-slate-500">SKU: {item.variant_sku_id || item.product_sku || 'N/A'}</p>
                                {item.variant_label && <p className="truncate text-xs font-semibold text-[#F97316]">{item.variant_label}</p>}
                                <p className="mt-1 text-xs text-slate-600">Qty {item.quantity.toLocaleString()} · ¥{item.line_total.toFixed(2)}</p>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>

                      <div className="flex w-full flex-col gap-3 xl:w-[190px]">
                        <button onClick={() => void handleStatusChange(request.id, 'accepted')} disabled={isUpdating || isAccepted} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-200">
                          <CheckCircle2 className="h-4 w-4" />
                          {isAccepted ? 'Accepted' : isUpdating ? 'Updating...' : 'Accept'}
                        </button>
                        <button onClick={() => void handleStatusChange(request.id, 'rejected')} disabled={isUpdating || isRejected} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-red-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-200">
                          <XCircle className="h-4 w-4" />
                          {isRejected ? 'Rejected' : isUpdating ? 'Updating...' : 'Reject'}
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          <PaginationControls currentPage={page} lastPage={lastPage} onPageChange={setPage} />
        </div>
      </div>
    </div>
  );
}
