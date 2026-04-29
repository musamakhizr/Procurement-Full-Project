import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, CheckCircle2, Clock3, ExternalLink, FileText, Package, Search, UserRound, XCircle } from 'lucide-react';
import { fetchAdminSourcingRequests, SourcingRequest, updateAdminSourcingRequestStatus } from '../api';

const STATUS_STYLES: Record<string, { label: string; className: string; icon: React.ElementType }> = {
  submitted: { label: 'Pending', className: 'bg-amber-100 text-amber-700', icon: Clock3 },
  accepted: { label: 'Accepted', className: 'bg-emerald-100 text-emerald-700', icon: CheckCircle2 },
  rejected: { label: 'Rejected', className: 'bg-red-100 text-red-700', icon: XCircle },
  under_review: { label: 'Under Review', className: 'bg-indigo-100 text-indigo-700', icon: Clock3 },
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

export function AdminRequestsPage() {
  const [requests, setRequests] = useState<SourcingRequest[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [activeRequestId, setActiveRequestId] = useState<number | null>(null);

  useEffect(() => {
    fetchAdminSourcingRequests()
      .then(setRequests)
      .finally(() => setIsLoading(false));
  }, []);

  const filteredRequests = useMemo(() => {
    const normalizedSearch = searchQuery.trim().toLowerCase();

    const sorted = [...requests].sort((left, right) => {
      return new Date(right.created_at).getTime() - new Date(left.created_at).getTime();
    });

    if (!normalizedSearch) {
      return sorted;
    }

    return sorted.filter((request) => {
      const haystack = [
        request.reference,
        request.title,
        request.details,
        request.user?.name,
        request.user?.email,
        request.user?.organization_name,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      return haystack.includes(normalizedSearch);
    });
  }, [requests, searchQuery]);

  const summary = useMemo(() => {
    return requests.reduce(
      (accumulator, request) => {
        accumulator.total += 1;

        if (request.status === 'accepted') {
          accumulator.accepted += 1;
        } else if (request.status === 'rejected') {
          accumulator.rejected += 1;
        } else {
          accumulator.pending += 1;
        }

        return accumulator;
      },
      { total: 0, pending: 0, accepted: 0, rejected: 0 }
    );
  }, [requests]);

  const handleStatusChange = async (requestId: number, status: 'accepted' | 'rejected') => {
    setActiveRequestId(requestId);

    try {
      const updatedRequest = await updateAdminSourcingRequestStatus(requestId, status);
      setRequests((currentRequests) =>
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
              Admin Request Management
            </div>
            <h1 className="text-4xl font-bold text-slate-900">Customer Sourcing Requests</h1>
            <p className="mt-2 max-w-3xl text-slate-600">
              Review every client submission, see who sent it, and update each request to accepted or rejected.
            </p>
          </div>

          <div className="relative w-full max-w-md">
            <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              value={searchQuery}
              onChange={(event) => setSearchQuery(event.target.value)}
              placeholder="Search by customer, email, title, or reference"
              className="w-full rounded-2xl border border-slate-200 bg-white py-3 pl-12 pr-4 text-slate-900 outline-none transition-colors focus:border-[#4F6BFF]"
            />
          </div>
        </div>

        <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Total Requests</p>
            <p className="mt-2 text-3xl font-bold text-slate-900">{summary.total}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Pending Review</p>
            <p className="mt-2 text-3xl font-bold text-amber-600">{summary.pending}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Accepted</p>
            <p className="mt-2 text-3xl font-bold text-emerald-600">{summary.accepted}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <p className="text-sm font-medium text-slate-500">Rejected</p>
            <p className="mt-2 text-3xl font-bold text-red-600">{summary.rejected}</p>
          </div>
        </div>

        <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white">
          {isLoading ? (
            <div className="p-12 text-center text-slate-500">Loading sourcing requests...</div>
          ) : filteredRequests.length === 0 ? (
            <div className="p-12 text-center">
              <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
                <AlertCircle className="h-7 w-7 text-slate-400" />
              </div>
              <h2 className="text-xl font-bold text-slate-900">No matching requests</h2>
              <p className="mt-2 text-slate-500">Try a different search term or wait for new client submissions.</p>
            </div>
          ) : (
            <div className="divide-y divide-slate-200">
              {filteredRequests.map((request) => {
                const isUpdating = activeRequestId === request.id;
                const isAccepted = request.status === 'accepted';
                const isRejected = request.status === 'rejected';

                return (
                  <div key={request.id} className="p-6">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                      <div className="min-w-0 flex-1">
                        <div className="mb-3 flex flex-wrap items-center gap-3">
                          <span className="rounded-md bg-slate-100 px-2.5 py-1 font-mono text-xs font-bold text-slate-600">
                            {request.reference}
                          </span>
                          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${request.type === 'links' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'}`}>
                            {request.type === 'links' ? 'Product Links' : 'Custom Request'}
                          </span>
                          <StatusBadge status={request.status} statusLabel={request.status_label} />
                        </div>

                        <h2 className="text-xl font-bold text-slate-900">{request.title}</h2>
                        <p className="mt-2 max-w-4xl whitespace-pre-wrap text-sm leading-6 text-slate-600">{request.details}</p>

                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
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

                          <div className="rounded-2xl bg-slate-50 p-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Request Details</p>
                            <p className="text-sm text-slate-700">Quantity: {request.quantity.toLocaleString()}</p>
                            <p className="text-sm text-slate-700">
                              Submitted: {new Date(request.created_at).toLocaleDateString()}
                            </p>
                            <p className="text-sm text-slate-700">
                              Delivery: {request.delivery_date ? new Date(request.delivery_date).toLocaleDateString() : 'Not specified'}
                            </p>
                          </div>

                          <div className="rounded-2xl bg-slate-50 p-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Budget And Notes</p>
                            <p className="text-sm text-slate-700">Budget: {request.budget_text || 'Not specified'}</p>
                            <p className="text-sm text-slate-700">{request.notes || 'No extra notes provided'}</p>
                          </div>

                          <div className="rounded-2xl bg-slate-50 p-4">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Links</p>
                            {request.links.length > 0 ? (
                              <div className="flex flex-wrap gap-2">
                                {request.links.slice(0, 3).map((link, index) => (
                                  <a
                                    key={`${request.id}-${index}`}
                                    href={link}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-[#4F6BFF] ring-1 ring-slate-200 transition-colors hover:text-[#3D56E0]"
                                  >
                                    <ExternalLink className="h-3 w-3" />
                                    Link {index + 1}
                                  </a>
                                ))}
                                {request.links.length > 3 && (
                                  <span className="rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                                    +{request.links.length - 3} more
                                  </span>
                                )}
                              </div>
                            ) : (
                              <p className="text-sm text-slate-700">No external links attached.</p>
                            )}
                          </div>
                        </div>
                      </div>

                      <div className="flex w-full flex-col gap-3 xl:w-[190px]">
                        <button
                          onClick={() => void handleStatusChange(request.id, 'accepted')}
                          disabled={isUpdating || isAccepted}
                          className="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-200"
                        >
                          <CheckCircle2 className="h-4 w-4" />
                          {isAccepted ? 'Accepted' : isUpdating ? 'Updating...' : 'Accept'}
                        </button>
                        <button
                          onClick={() => void handleStatusChange(request.id, 'rejected')}
                          disabled={isUpdating || isRejected}
                          className="inline-flex items-center justify-center gap-2 rounded-2xl bg-red-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-200"
                        >
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
        </div>
      </div>
    </div>
  );
}
