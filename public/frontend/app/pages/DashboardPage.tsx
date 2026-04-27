import { useEffect, useState } from 'react';
import { Search, Sparkles, FileText, Link as LinkIcon, ShoppingCart, Clock, CheckCircle2, TrendingUp, Package, ChevronRight, FileSpreadsheet, Briefcase, GraduationCap, ArrowRight, ClipboardCheck, Palette, Trophy, PartyPopper, Laptop } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { Link, useNavigate } from 'react-router';
import { fetchDashboard } from '../api';

type WorkflowMode = 'search' | 'ai' | 'sourcing' | 'links';

export function DashboardPage() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [workflowMode, setWorkflowMode] = useState<WorkflowMode>('search');
  const [inputValue, setInputValue] = useState('');
  const [dashboardData, setDashboardData] = useState<any>(null);

  useEffect(() => {
    fetchDashboard().then(setDashboardData);
  }, []);

  const workflowModes = [
    { id: 'search' as WorkflowMode, name: t('dashboard.modeSearch'), icon: Search, placeholder: t('dashboard.searchPlaceholder'), buttonText: t('dashboard.searchButton') },
    { id: 'ai' as WorkflowMode, name: t('dashboard.modeAI'), icon: Sparkles, placeholder: t('dashboard.aiPlaceholder'), buttonText: t('dashboard.aiButton') },
    { id: 'sourcing' as WorkflowMode, name: t('dashboard.modeSourcing'), icon: FileText, placeholder: t('dashboard.sourcingPlaceholder'), buttonText: t('dashboard.sourcingButton') },
    { id: 'links' as WorkflowMode, name: t('dashboard.modeLinks'), icon: LinkIcon, placeholder: t('dashboard.linksPlaceholder'), buttonText: t('dashboard.linksButton') },
  ];

  const quickTemplates = [
    { id: 1, name: t('dashboard.template1'), icon: GraduationCap, color: 'bg-blue-50 text-blue-600', desc: t('dashboard.template1Desc') },
    { id: 2, name: t('dashboard.template2'), icon: Briefcase, color: 'bg-purple-50 text-purple-600', desc: t('dashboard.template2Desc') },
    { id: 3, name: t('dashboard.template3'), icon: FileSpreadsheet, color: 'bg-green-50 text-green-600', desc: t('dashboard.template3Desc') },
  ];

  const productCategories = [
    { id: 'office', name: t('dashboard.categoryOffice'), icon: FileText },
    { id: 'classroom', name: t('dashboard.categoryClassroom'), icon: GraduationCap },
    { id: 'art', name: t('dashboard.categoryArt'), icon: Palette },
    { id: 'sports', name: t('dashboard.categorySports'), icon: Trophy },
    { id: 'events', name: t('dashboard.categoryEvents'), icon: PartyPopper },
    { id: 'technology', name: t('dashboard.categoryTechnology'), icon: Laptop },
  ];

  const currentMode = workflowModes.find((mode) => mode.id === workflowMode)!;
  const ModeIcon = currentMode.icon;

  const handlePrimaryAction = () => {
    if (workflowMode === 'search' || workflowMode === 'ai') {
      navigate(`/marketplace${inputValue ? `?search=${encodeURIComponent(inputValue)}` : ''}`);
      return;
    }

    navigate('/sourcing');
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1400px] mx-auto px-6">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-slate-900 mb-1">{t('dashboard.welcome')}</h1>
          <p className="text-sm text-slate-600">{t('dashboard.welcomeSubtitle')}</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between mb-3"><div className="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center"><Clock className="w-4.5 h-4.5 text-amber-600" /></div><span className="text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded">{t('dashboard.pending')}</span></div>
            <div className="text-2xl font-bold text-slate-900 mb-0.5">{dashboardData?.summary.pending_requests ?? 0}</div>
            <div className="text-xs font-medium text-slate-600">{t('dashboard.pendingRequests')}</div>
          </div>
          <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between mb-3"><div className="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center"><Package className="w-4.5 h-4.5 text-blue-600" /></div><span className="text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded">{t('dashboard.active')}</span></div>
            <div className="text-2xl font-bold text-slate-900 mb-0.5">{dashboardData?.summary.active_orders ?? 0}</div>
            <div className="text-xs font-medium text-slate-600">{t('dashboard.activeOrders')}</div>
          </div>
          <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between mb-3"><div className="w-9 h-9 rounded-lg bg-slate-50 flex items-center justify-center"><TrendingUp className="w-4.5 h-4.5 text-slate-600" /></div></div>
            <div className="text-2xl font-bold text-slate-900 mb-0.5">${(dashboardData?.summary.month_spend ?? 0).toFixed(2)}</div>
            <div className="text-xs font-medium text-slate-600">{t('dashboard.monthSpend')}</div>
          </div>
          <div className="bg-white rounded-xl border border-slate-200 p-5">
            <div className="flex items-center justify-between mb-3"><div className="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center"><CheckCircle2 className="w-4.5 h-4.5 text-emerald-600" /></div></div>
            <div className="text-2xl font-bold text-slate-900 mb-0.5">{dashboardData?.summary.savings_percentage ?? 0}%</div>
            <div className="text-xs font-medium text-slate-600">{t('dashboard.savings')}</div>
          </div>
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-7 mb-6">
          <div className="max-w-4xl mx-auto">
            <div className="mb-5">
              <h2 className="text-lg font-bold text-slate-900 mb-1">{t('dashboard.startProcurement')}</h2>
              <p className="text-xs text-slate-500">{t('dashboard.startProcurementDesc')}</p>
            </div>

            <div className="flex gap-1.5 mb-4">
              {workflowModes.map((mode) => {
                const Icon = mode.icon;
                const isActive = workflowMode === mode.id;
                return (
                  <button key={mode.id} onClick={() => setWorkflowMode(mode.id)} className={`flex-1 flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg font-semibold text-xs transition-all ${isActive ? 'bg-[#4F6BFF] text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'}`}>
                    <Icon className="w-4 h-4" />
                    <span className="hidden sm:inline">{mode.name}</span>
                  </button>
                );
              })}
            </div>

            <div className="relative mb-4">
              <ModeIcon className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-slate-400" />
              <input type="text" placeholder={currentMode.placeholder} value={inputValue} onChange={(event) => setInputValue(event.target.value)} className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-[#4F6BFF] focus:ring-2 focus:ring-[#4F6BFF]/10 focus:bg-white text-sm text-slate-900 placeholder:text-slate-400" />
            </div>

            <button onClick={handlePrimaryAction} className="w-full py-3.5 bg-[#4F6BFF] text-white font-semibold rounded-lg hover:bg-[#3D56E0] transition-all shadow-sm hover:shadow-md text-sm flex items-center justify-center gap-2 group mb-5">
              <span>{currentMode.buttonText}</span>
              <ArrowRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
            </button>

            <div className="flex items-center justify-center gap-3 pt-5 border-t border-slate-200 text-xs">
              <Link to="/procurement-list" className="flex items-center gap-1.5 text-slate-600 hover:text-[#4F6BFF] font-medium transition-colors"><ShoppingCart className="w-3.5 h-3.5" /><span>{t('dashboard.myList')}</span></Link>
              <span className="text-slate-300">•</span>
              <Link to="/sourcing" className="flex items-center gap-1.5 text-slate-600 hover:text-[#4F6BFF] font-medium transition-colors"><FileText className="w-3.5 h-3.5" /><span>{t('dashboard.customRequest')}</span></Link>
              <span className="text-slate-300">•</span>
              <button className="flex items-center gap-1.5 text-slate-600 hover:text-[#4F6BFF] font-medium transition-colors"><Package className="w-3.5 h-3.5" /><span>{t('dashboard.trackOrders')}</span></button>
            </div>
          </div>
        </div>

        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-bold text-slate-700">{t('dashboard.popularCategories')}</h3>
            <Link to="/marketplace" className="text-xs text-[#4F6BFF] hover:text-[#3D56E0] font-semibold flex items-center gap-1">{t('dashboard.viewAllCategories')}<ChevronRight className="w-3.5 h-3.5" /></Link>
          </div>
          <div className="flex flex-wrap gap-2">
            {productCategories.map((category) => {
              const Icon = category.icon;
              return (
                <Link key={category.id} to={`/marketplace?category=${category.id}`} className="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-200 hover:border-[#4F6BFF] rounded-lg text-xs font-medium text-slate-700 hover:text-[#4F6BFF] transition-all shadow-sm hover:shadow">
                  <Icon className="w-3.5 h-3.5" />
                  <span>{category.name}</span>
                </Link>
              );
            })}
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
            <div className="p-6 border-b border-slate-200">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-slate-900">{t('dashboard.actionCenter')}</h3>
                  <p className="text-xs text-slate-500 mt-1">{t('dashboard.actionCenterDesc')}</p>
                </div>
                <button className="text-xs text-[#4F6BFF] hover:text-[#3D56E0] font-semibold">{t('dashboard.viewAll')}</button>
              </div>
            </div>

            <div className="p-6 space-y-3">
              {(dashboardData?.action_items ?? []).map((item: any) => (
                <div key={item.id} className={`p-4 rounded-lg border transition-all hover:shadow-sm ${item.urgent ? 'bg-orange-50/30 border-orange-200' : 'bg-slate-50/50 border-slate-200 hover:border-slate-300'}`}>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-xs font-mono text-slate-500">{item.id}</span>
                        {item.urgent && <span className="px-1.5 py-0.5 bg-orange-600 text-white text-xs font-bold rounded">{t('dashboard.urgent')}</span>}
                        <span className="text-xs font-medium px-2 py-0.5 rounded bg-blue-100 text-blue-700">{item.status_text}</span>
                      </div>
                      <p className="text-sm font-semibold text-slate-900 mb-2">{item.title}</p>
                      <div className="flex items-start gap-1.5 text-xs text-slate-600"><ClipboardCheck className="w-3.5 h-3.5 mt-0.5 flex-shrink-0" /><span><span className="font-medium">{t('dashboard.nextStep')}:</span> {item.next_step}</span></div>
                    </div>
                    <button onClick={() => navigate('/sourcing')} className={`px-4 py-2 rounded-lg font-semibold text-xs whitespace-nowrap transition-all flex-shrink-0 ${item.urgent ? 'bg-orange-600 text-white hover:bg-orange-700 shadow-sm' : 'bg-[#4F6BFF] text-white hover:bg-[#3D56E0] shadow-sm'}`}>{item.action}</button>
                  </div>
                </div>
              ))}

              <button onClick={() => navigate('/sourcing')} className="w-full py-3 text-sm border border-dashed border-slate-300 text-slate-600 font-medium rounded-lg hover:border-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-colors">{t('dashboard.viewAllRequests')}</button>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white rounded-xl border border-slate-200 p-5">
              <h3 className="text-sm font-bold text-slate-900 mb-1">{t('dashboard.quickTemplates')}</h3>
              <p className="text-xs text-slate-500 mb-4">{t('dashboard.quickTemplatesDesc')}</p>
              <div className="space-y-2">
                {quickTemplates.map((template) => {
                  const Icon = template.icon;
                  return (
                    <button key={template.id} onClick={() => navigate('/sourcing')} className="w-full flex items-center gap-3 p-3 border border-slate-200 rounded-lg hover:border-[#4F6BFF] hover:bg-slate-50 transition-all group text-left">
                      <div className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${template.color}`}><Icon className="w-4 h-4" /></div>
                      <div className="flex-1 min-w-0"><p className="text-xs font-semibold text-slate-900 mb-0.5">{template.name}</p><p className="text-xs text-slate-500 line-clamp-1">{template.desc}</p></div>
                      <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-[#4F6BFF] flex-shrink-0" />
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 p-5">
              <h3 className="text-xs font-bold text-slate-900 mb-3">{t('dashboard.recentActivity')}</h3>
              <div className="space-y-3">
                {(dashboardData?.recent_activity ?? []).map((activity: any) => (
                  <div key={activity.id} className="flex items-start gap-2.5 text-xs">
                    <div className="w-1.5 h-1.5 rounded-full bg-blue-400 mt-1.5 flex-shrink-0" />
                    <div className="flex-1 min-w-0"><p className="text-slate-700 leading-relaxed">{activity.action}</p><p className="text-slate-400 mt-0.5">{activity.time}</p></div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
