import { useEffect, useMemo, useState } from 'react';
import { Upload, Plus, X, CheckCircle2, FileText, ArrowRight } from 'lucide-react';
import { Link, useNavigate } from 'react-router';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchSourcingRequests, submitSourcingRequest, SourcingRequest } from '../api';

export function SourcingPage() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<'custom' | 'links'>('custom');
  const [productLinks, setProductLinks] = useState(['']);
  const [customForm, setCustomForm] = useState({
    title: '',
    details: '',
    quantity: '',
    budget: '',
    deliveryDate: '',
  });
  const [linksForm, setLinksForm] = useState({
    quantity: '',
    deliveryDate: '',
    notes: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submittedRef, setSubmittedRef] = useState('');
  const [error, setError] = useState('');
  const [recentRequests, setRecentRequests] = useState<SourcingRequest[]>([]);

  useEffect(() => {
    fetchSourcingRequests().then(setRecentRequests).catch(() => {});
  }, []);

  const addLinkField = () => setProductLinks([...productLinks, '']);
  const removeLinkField = (index: number) => setProductLinks(productLinks.filter((_, i) => i !== index));
  const updateLink = (index: number, value: string) => {
    const nextLinks = [...productLinks];
    nextLinks[index] = value;
    setProductLinks(nextLinks);
  };

  const customTitle = useMemo(() => customForm.title || 'Custom sourcing request', [customForm.title]);

  const handleCustomSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');
    setSubmittedRef('');
    setIsSubmitting(true);

    try {
      const response = await submitSourcingRequest({
        type: 'custom',
        title: customTitle,
        details: customForm.details,
        quantity: Number(customForm.quantity),
        budget_text: customForm.budget || undefined,
        delivery_date: customForm.deliveryDate || undefined,
      });
      setSubmittedRef(response.data.reference);
      setCustomForm({ title: '', details: '', quantity: '', budget: '', deliveryDate: '' });
      // Refresh recent requests list
      fetchSourcingRequests().then(setRecentRequests).catch(() => {});
    } catch (submitError: any) {
      setError(submitError?.response?.data?.message || 'Unable to submit your sourcing request.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleLinksSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');
    setSubmittedRef('');
    setIsSubmitting(true);

    try {
      const validLinks = productLinks.filter(Boolean);
      const response = await submitSourcingRequest({
        type: 'links',
        title: `Linked products request (${validLinks.length} items)`,
        details: linksForm.notes || 'Please review the attached product links.',
        quantity: Number(linksForm.quantity),
        delivery_date: linksForm.deliveryDate || undefined,
        notes: linksForm.notes || undefined,
        links: validLinks,
      });
      setSubmittedRef(response.data.reference);
      setLinksForm({ quantity: '', deliveryDate: '', notes: '' });
      setProductLinks(['']);
      // Refresh recent requests list
      fetchSourcingRequests().then(setRecentRequests).catch(() => {});
    } catch (submitError: any) {
      setError(submitError?.response?.data?.message || 'Unable to submit product links.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1000px] mx-auto px-6">
        <div className="text-center mb-12">
          <h1 className="text-5xl font-bold text-slate-900 mb-4">Custom Sourcing & Product Links</h1>
          <p className="text-xl text-slate-600 max-w-2xl mx-auto">Can't find what you need? Let our expert procurement team source it for you, or send us product links from any website.</p>
        </div>

        {submittedRef && (
          <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 flex items-start gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-semibold text-emerald-800">Request submitted successfully!</p>
              <p className="text-sm text-emerald-700 mt-0.5">
                Reference: <span className="font-mono font-bold">{submittedRef}</span>. Our team will review within 24 hours.
              </p>
              <Link to="/my-requests" className="inline-flex items-center gap-1 text-sm text-emerald-700 underline underline-offset-2 hover:text-emerald-900 mt-1">
                View all your requests <ArrowRight className="w-3.5 h-3.5" />
              </Link>
            </div>
          </div>
        )}
        {error && <div className="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}

        <div className="flex gap-4 mb-8 bg-white rounded-2xl border-2 border-slate-200 p-2">
          <button onClick={() => setActiveTab('custom')} className={`flex-1 py-4 px-6 rounded-xl font-bold transition-all ${activeTab === 'custom' ? 'bg-[#4F6BFF] text-white shadow-lg' : 'text-slate-600 hover:bg-slate-50'}`}>
            {t('sourcing.customTab')}
          </button>
          <button onClick={() => setActiveTab('links')} className={`flex-1 py-4 px-6 rounded-xl font-bold transition-all ${activeTab === 'links' ? 'bg-[#7C3AED] text-white shadow-lg' : 'text-slate-600 hover:bg-slate-50'}`}>
            {t('sourcing.linksTab')}
          </button>
        </div>

        {activeTab === 'custom' && (
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-8">
            <h2 className="text-2xl font-bold text-slate-900 mb-6">Describe Your Sourcing Request</h2>
            <form className="space-y-6" onSubmit={(event) => void handleCustomSubmit(event)}>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Product Name / Category</label>
                <input type="text" value={customForm.title} onChange={(event) => setCustomForm({ ...customForm, title: event.target.value })} placeholder="e.g., Custom branded notebooks, Event promotional items..." className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] text-slate-900" required />
              </div>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Detailed Requirements</label>
                <textarea rows={6} value={customForm.details} onChange={(event) => setCustomForm({ ...customForm, details: event.target.value })} placeholder="Describe specifications, materials, colors, sizes, customization needs, intended use..." className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] text-slate-900 resize-none" required />
              </div>
              <div className="grid grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Quantity Needed</label>
                  <input type="number" value={customForm.quantity} onChange={(event) => setCustomForm({ ...customForm, quantity: event.target.value })} placeholder="e.g., 500" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] text-slate-900" required />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Target Budget (Optional)</label>
                  <input type="text" value={customForm.budget} onChange={(event) => setCustomForm({ ...customForm, budget: event.target.value })} placeholder="e.g., $2000 - $3000" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] text-slate-900" />
                </div>
              </div>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Deadline / Delivery Date</label>
                <input type="date" value={customForm.deliveryDate} onChange={(event) => setCustomForm({ ...customForm, deliveryDate: event.target.value })} className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] text-slate-900" />
              </div>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Upload Reference Images (Optional)</label>
                <div className="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors cursor-not-allowed">
                  <Upload className="w-12 h-12 text-slate-400 mx-auto mb-3" />
                  <p className="text-slate-600 mb-1">Reference file upload can be added next.</p>
                  <p className="text-sm text-slate-500">PNG, JPG, PDF up to 10MB</p>
                </div>
              </div>
              <button type="submit" disabled={isSubmitting} className="w-full py-4 bg-[#4F6BFF] text-white font-bold text-lg rounded-xl hover:bg-[#3D56E0] transition-colors">
                {isSubmitting ? 'Submitting...' : 'Submit Sourcing Request'}
              </button>
            </form>
          </div>
        )}

        {activeTab === 'links' && (
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-8">
            <h2 className="text-2xl font-bold text-slate-900 mb-6">Submit Product Links</h2>
            <form className="space-y-6" onSubmit={(event) => void handleLinksSubmit(event)}>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Product Links</label>
                <p className="text-sm text-slate-600 mb-4">Paste product URLs from any website: 1688, Taobao, Amazon, AliExpress, supplier sites, etc.</p>
                <div className="space-y-3">
                  {productLinks.map((link, index) => (
                    <div key={index} className="flex gap-2">
                      <input type="url" value={link} onChange={(event) => updateLink(index, event.target.value)} placeholder="https://www.example.com/product/..." className="flex-1 px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#7C3AED] text-slate-900" required={index === 0} />
                      {productLinks.length > 1 && (
                        <button type="button" onClick={() => removeLinkField(index)} className="p-3 border-2 border-slate-200 rounded-xl hover:border-red-500 hover:bg-red-50 text-slate-600 hover:text-red-600 transition-colors">
                          <X className="w-5 h-5" />
                        </button>
                      )}
                    </div>
                  ))}
                </div>
                <button type="button" onClick={addLinkField} className="mt-3 flex items-center gap-2 px-4 py-2 text-[#7C3AED] hover:bg-[#F3E8FF] rounded-xl transition-colors font-semibold">
                  <Plus className="w-5 h-5" />
                  Add Another Link
                </button>
              </div>
              <div className="grid grid-cols-2 gap-6">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Total Quantity</label>
                  <input type="number" value={linksForm.quantity} onChange={(event) => setLinksForm({ ...linksForm, quantity: event.target.value })} placeholder="e.g., 100" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#7C3AED] text-slate-900" required />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Delivery Date</label>
                  <input type="date" value={linksForm.deliveryDate} onChange={(event) => setLinksForm({ ...linksForm, deliveryDate: event.target.value })} className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#7C3AED] text-slate-900" />
                </div>
              </div>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Additional Notes (Optional)</label>
                <textarea rows={4} value={linksForm.notes} onChange={(event) => setLinksForm({ ...linksForm, notes: event.target.value })} placeholder="Any specific requirements, customization needs, or questions..." className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#7C3AED] text-slate-900 resize-none" />
              </div>
              <button type="submit" disabled={isSubmitting} className="w-full py-4 bg-[#7C3AED] text-white font-bold text-lg rounded-xl hover:bg-[#6B2FD1] transition-colors">
                {isSubmitting ? 'Submitting...' : 'Submit Product Links'}
              </button>
            </form>
          </div>
        )}

        <div className="mt-8 bg-gradient-to-r from-[#EEF2FF] to-[#F3E8FF] rounded-2xl border-2 border-slate-200 p-6">
          <h3 className="font-bold text-slate-900 mb-2">What happens next?</h3>
          <ul className="space-y-2 text-slate-700">
            <li className="flex gap-2"><span className="text-[#4F6BFF] font-bold">1.</span><span>Our procurement team reviews your request within 24 hours</span></li>
            <li className="flex gap-2"><span className="text-[#4F6BFF] font-bold">2.</span><span>We source multiple supplier options and verify quality</span></li>
            <li className="flex gap-2"><span className="text-[#4F6BFF] font-bold">3.</span><span>You receive detailed quotes with supplier information</span></li>
            <li className="flex gap-2"><span className="text-[#4F6BFF] font-bold">4.</span><span>Approve your selection and we handle the rest</span></li>
          </ul>
        </div>

        {recentRequests.length > 0 && (
          <div className="mt-8">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-bold text-slate-900 flex items-center gap-2">
                <FileText className="w-5 h-5 text-[#4F6BFF]" />
                Recent Requests
              </h3>
              <Link to="/my-requests" className="text-sm text-[#4F6BFF] hover:text-[#3D56E0] font-semibold flex items-center gap-1">
                View all <ArrowRight className="w-3.5 h-3.5" />
              </Link>
            </div>
            <div className="space-y-3">
              {recentRequests.slice(0, 3).map((req) => (
                <div key={req.id} className="bg-white rounded-xl border border-slate-200 px-5 py-4 flex items-center justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-xs font-mono font-bold text-slate-400">{req.reference}</span>
                      <span className={`text-xs font-semibold px-1.5 py-0.5 rounded ${req.status === 'submitted' || req.status === 'under_review' ? 'bg-blue-100 text-blue-700' : req.status === 'quoted' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700'}`}>
                        {req.status.replace('_', ' ')}
                      </span>
                    </div>
                    <p className="text-sm font-medium text-slate-800 truncate">{req.title}</p>
                  </div>
                  <span className="text-xs text-slate-400 whitespace-nowrap">
                    {new Date(req.created_at).toLocaleDateString()}
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
