import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { AlertCircle, CheckCircle2, ChevronRight, Clock, ExternalLink, FileText, Package, Plus } from 'lucide-react';
import { fetchSourcingRequests, SourcingRequest } from '../api';

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: React.ElementType }> = {
  submitted: { label: 'Pending', color: 'bg-amber-100 text-amber-700', icon: Clock },
  accepted: { label: 'Accepted', color: 'bg-emerald-100 text-emerald-700', icon: CheckCircle2 },
  rejected: { label: 'Rejected', color: 'bg-red-100 text-red-700', icon: AlertCircle },
  under_review: { label: 'Under Review', color: 'bg-indigo-100 text-indigo-700', icon: Clock },
  needs_info: { label: 'Needs Info', color: 'bg-orange-100 text-orange-700', icon: AlertCircle },
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

export function MyRequestsPage() {
  const navigate = useNavigate();
  const [requests, setRequests] = useState<SourcingRequest[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    setIsLoading(true);

    fetchSourcingRequests(page)
      .then((response) => {
        setRequests(response.data);
        setLastPage(response.meta.last_page);
      })
      .finally(() => setIsLoading(false));
  }, [page]);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="mx-auto max-w-[1000px] px-6">
        <div className="mb-6 flex items-center gap-2 text-sm text-slate-500">
          <Link to="/" className="transition-colors hover:text-[#4F6BFF]">Home</Link>
          <ChevronRight className="h-3.5 w-3.5" />
          <Link to="/dashboard" className="transition-colors hover:text-[#4F6BFF]">Dashboard</Link>
          <ChevronRight className="h-3.5 w-3.5" />
          <span className="font-medium text-slate-900">Custom Sourcing Requests</span>
        </div>

        <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="mb-1 text-3xl font-bold text-slate-900">Custom Sourcing Requests</h1>
            <p className="text-slate-600">Track custom sourcing and product-link requests separately from catalog quote requests.</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Link to="/my-quote-requests" className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF]">
              Catalog Quotes
            </Link>
            <button
              onClick={() => navigate('/sourcing')}
              className="flex items-center gap-2 rounded-xl bg-[#4F6BFF] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#3D56E0]"
            >
              <Plus className="h-4 w-4" />
              New Request
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">Loading custom requests...</div>
        ) : requests.length === 0 ? (
          <div className="rounded-2xl border-2 border-dashed border-slate-200 bg-white p-16 text-center">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
              <FileText className="h-8 w-8 text-slate-400" />
            </div>
            <h3 className="mb-2 text-xl font-bold text-slate-900">No custom requests yet</h3>
            <p className="mx-auto mb-6 max-w-sm text-slate-500">Submit a custom sourcing request or product link and our team will review it.</p>
            <button onClick={() => navigate('/sourcing')} className="rounded-xl bg-[#4F6BFF] px-6 py-3 font-semibold text-white transition-colors hover:bg-[#3D56E0]">
              Create Your First Request
            </button>
          </div>
        ) : (
          <>
            <div className="space-y-4">
              {requests.map((request) => (
                <div key={request.id} className="rounded-2xl border border-slate-200 bg-white p-6 transition-all hover:border-[#4F6BFF]/40 hover:shadow-sm">
                  <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                      <div className="mb-2 flex flex-wrap items-center gap-3">
                        <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-500">{request.reference}</span>
                        <span className={`rounded px-2 py-0.5 text-xs font-semibold ${request.type === 'links' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                          {request.type === 'links' ? 'Product Links' : 'Custom Request'}
                        </span>
                        <StatusBadge status={request.status} />
                      </div>

                      <h3 className="mb-2 font-semibold text-slate-900">{request.title}</h3>
                      <p className="line-clamp-2 text-sm text-slate-600">{request.details}</p>

                      <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                        <span className="flex items-center gap-1"><Package className="h-3.5 w-3.5" />Qty: {request.quantity.toLocaleString()}</span>
                        {request.delivery_date && <span className="flex items-center gap-1"><Clock className="h-3.5 w-3.5" />Due: {new Date(request.delivery_date).toLocaleDateString()}</span>}
                        <span>Submitted: {new Date(request.created_at).toLocaleDateString()}</span>
                      </div>

                      {request.links.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                          {request.links.slice(0, 3).map((link, index) => (
                            <a key={index} href={link} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 rounded-lg bg-[#EEF2FF] px-2 py-1 text-xs text-[#4F6BFF] transition-colors hover:text-[#3D56E0]">
                              <ExternalLink className="h-3 w-3" />
                              Link {index + 1}
                            </a>
                          ))}
                          {request.links.length > 3 && <span className="px-2 py-1 text-xs text-slate-500">+{request.links.length - 3} more</span>}
                        </div>
                      )}
                    </div>
                  </div>
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
