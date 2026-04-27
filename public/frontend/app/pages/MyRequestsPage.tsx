import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { FileText, Clock, CheckCircle2, AlertCircle, ChevronRight, Plus, ExternalLink, Package } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchSourcingRequests, SourcingRequest } from '../api';

const STATUS_CONFIG: Record<string, { label: string; color: string; icon: React.ElementType }> = {
  submitted: { label: 'Submitted', color: 'bg-blue-100 text-blue-700', icon: Clock },
  under_review: { label: 'Under Review', color: 'bg-purple-100 text-purple-700', icon: Clock },
  needs_info: { label: 'Needs Info', color: 'bg-orange-100 text-orange-700', icon: AlertCircle },
  quoted: { label: 'Quote Ready', color: 'bg-emerald-100 text-emerald-700', icon: CheckCircle2 },
  approved: { label: 'Approved', color: 'bg-green-100 text-green-700', icon: CheckCircle2 },
  processing: { label: 'Processing', color: 'bg-indigo-100 text-indigo-700', icon: Package },
  completed: { label: 'Completed', color: 'bg-slate-100 text-slate-600', icon: CheckCircle2 },
};

function StatusBadge({ status }: { status: string }) {
  const config = STATUS_CONFIG[status] ?? { label: status, color: 'bg-slate-100 text-slate-600', icon: Clock };
  const Icon = config.icon;

  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${config.color}`}>
      <Icon className="w-3.5 h-3.5" />
      {config.label}
    </span>
  );
}

export function MyRequestsPage() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [requests, setRequests] = useState<SourcingRequest[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchSourcingRequests()
      .then(setRequests)
      .finally(() => setIsLoading(false));
  }, []);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1000px] mx-auto px-6">
        {/* Breadcrumb */}
        <div className="flex items-center gap-2 text-sm text-slate-500 mb-6">
          <Link to="/" className="hover:text-[#4F6BFF] transition-colors">Home</Link>
          <ChevronRight className="w-3.5 h-3.5" />
          <Link to="/dashboard" className="hover:text-[#4F6BFF] transition-colors">Dashboard</Link>
          <ChevronRight className="w-3.5 h-3.5" />
          <span className="text-slate-900 font-medium">My Requests</span>
        </div>

        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold text-slate-900 mb-1">My Sourcing Requests</h1>
            <p className="text-slate-600">Track the status of all your custom sourcing and product link requests.</p>
          </div>
          <button
            onClick={() => navigate('/sourcing')}
            className="flex items-center gap-2 px-5 py-2.5 bg-[#4F6BFF] text-white font-semibold rounded-xl hover:bg-[#3D56E0] transition-colors text-sm"
          >
            <Plus className="w-4 h-4" />
            New Request
          </button>
        </div>

        {/* Content */}
        {isLoading ? (
          <div className="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
            Loading your requests...
          </div>
        ) : requests.length === 0 ? (
          <div className="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-16 text-center">
            <div className="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <FileText className="w-8 h-8 text-slate-400" />
            </div>
            <h3 className="text-xl font-bold text-slate-900 mb-2">No requests yet</h3>
            <p className="text-slate-500 mb-6 max-w-sm mx-auto">
              Submit a custom sourcing request or share product links and our team will find the best options for you.
            </p>
            <button
              onClick={() => navigate('/sourcing')}
              className="px-6 py-3 bg-[#4F6BFF] text-white font-semibold rounded-xl hover:bg-[#3D56E0] transition-colors"
            >
              Create Your First Request
            </button>
          </div>
        ) : (
          <div className="space-y-4">
            {requests.map((request) => (
              <div
                key={request.id}
                className="bg-white rounded-2xl border border-slate-200 p-6 hover:border-[#4F6BFF]/40 hover:shadow-sm transition-all"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    {/* Reference & Type */}
                    <div className="flex items-center gap-3 mb-2">
                      <span className="text-xs font-mono font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                        {request.reference}
                      </span>
                      <span className={`text-xs font-semibold px-2 py-0.5 rounded ${request.type === 'links' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                        {request.type === 'links' ? 'Product Links' : 'Custom Request'}
                      </span>
                      <StatusBadge status={request.status} />
                    </div>

                    {/* Title */}
                    <h3 className="font-semibold text-slate-900 mb-2">{request.title}</h3>

                    {/* Meta */}
                    <div className="flex items-center gap-4 text-xs text-slate-500">
                      <span className="flex items-center gap-1">
                        <Package className="w-3.5 h-3.5" />
                        Qty: {request.quantity.toLocaleString()}
                      </span>
                      {request.delivery_date && (
                        <span className="flex items-center gap-1">
                          <Clock className="w-3.5 h-3.5" />
                          Due: {new Date(request.delivery_date).toLocaleDateString()}
                        </span>
                      )}
                      <span>
                        Submitted: {new Date(request.created_at).toLocaleDateString()}
                      </span>
                    </div>

                    {/* Links preview */}
                    {request.links.length > 0 && (
                      <div className="mt-3 flex flex-wrap gap-2">
                        {request.links.slice(0, 3).map((link, index) => (
                          <a
                            key={index}
                            href={link}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(e) => e.stopPropagation()}
                            className="inline-flex items-center gap-1 text-xs text-[#4F6BFF] hover:text-[#3D56E0] bg-[#EEF2FF] px-2 py-1 rounded-lg transition-colors"
                          >
                            <ExternalLink className="w-3 h-3" />
                            Link {index + 1}
                          </a>
                        ))}
                        {request.links.length > 3 && (
                          <span className="text-xs text-slate-500 px-2 py-1">
                            +{request.links.length - 3} more
                          </span>
                        )}
                      </div>
                    )}
                  </div>

                  {/* Action */}
                  <div className="flex-shrink-0">
                    {(request.status === 'needs_info' || request.status === 'quoted') ? (
                      <button
                        onClick={() => navigate('/sourcing')}
                        className={`px-4 py-2 rounded-xl font-semibold text-sm transition-colors ${
                          request.status === 'needs_info'
                            ? 'bg-orange-600 text-white hover:bg-orange-700'
                            : 'bg-emerald-600 text-white hover:bg-emerald-700'
                        }`}
                      >
                        {request.status === 'needs_info' ? 'Add Info' : 'Review Quote'}
                      </button>
                    ) : (
                      <span className="text-xs text-slate-400 mt-1 block text-right">
                        {request.status === 'submitted' || request.status === 'under_review'
                          ? 'Team reviewing...'
                          : ''}
                      </span>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Info box */}
        <div className="mt-8 bg-gradient-to-r from-[#EEF2FF] to-[#F3E8FF] rounded-2xl border border-slate-200 p-6">
          <h3 className="font-bold text-slate-900 mb-2 text-sm">Need a new request?</h3>
          <p className="text-sm text-slate-600 mb-4">
            Our procurement team typically responds within 24 hours with multiple supplier options and competitive quotes.
          </p>
          <div className="flex gap-3">
            <button onClick={() => navigate('/sourcing')} className="px-4 py-2 bg-[#4F6BFF] text-white font-semibold rounded-lg hover:bg-[#3D56E0] transition-colors text-sm">
              Custom Sourcing Request
            </button>
            <button onClick={() => navigate('/marketplace')} className="px-4 py-2 bg-white text-slate-700 font-semibold rounded-lg border border-slate-200 hover:border-[#4F6BFF] hover:text-[#4F6BFF] transition-colors text-sm">
              Browse Catalog
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
