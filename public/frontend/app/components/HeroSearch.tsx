import { Search, Sparkles, Package, Link } from 'lucide-react';
import { useState } from 'react';
import { useLanguage } from '../contexts/LanguageContext';

interface HeroSearchProps {
  onSearchSubmit: (query: string, type: 'search' | 'ai' | 'sourcing' | 'links') => void;
}

export function HeroSearch({ onSearchSubmit }: HeroSearchProps) {
  const { t } = useLanguage();
  const [searchQuery, setSearchQuery] = useState('');
  const [activeMode, setActiveMode] = useState<'search' | 'ai' | 'sourcing' | 'links'>('search');

  const quickScenarios = [
    { icon: '🎨', label: 'Art workshop kit', type: 'ai' as const },
    { icon: '🏢', label: 'Office restock', type: 'search' as const },
    { icon: '🎓', label: 'Classroom supplies', type: 'search' as const },
    { icon: '⚽', label: 'Sports day setup', type: 'ai' as const },
    { icon: '🎉', label: 'Event materials', type: 'ai' as const },
    { icon: '✨', label: 'Custom printed items', type: 'sourcing' as const },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      onSearchSubmit(searchQuery, activeMode);
    }
  };

  const placeholders = {
    search: t('hero.searchPlaceholder'),
    ai: t('hero.aiPlaceholder'),
    sourcing: t('hero.sourcingPlaceholder'),
    links: t('hero.linksPlaceholder'),
  };

  return (
    <div className="bg-gradient-to-b from-slate-50 to-white py-24 px-6">
      <div className="max-w-5xl mx-auto">
        <div className="text-center mb-12">
          <h1 className="text-5xl md:text-6xl font-bold text-slate-900 mb-5 tracking-tight">
            {t('hero.title')}
          </h1>
          <p className="text-xl text-slate-600 max-w-3xl mx-auto mb-4 leading-relaxed">
            {t('hero.subtitle')}
          </p>
        </div>

        {/* Account CTAs */}
        <div className="flex items-center justify-center gap-4 mb-10">
          <button className="px-9 py-3.5 bg-[#4F6BFF] text-white rounded-xl font-semibold hover:bg-[#3F5AF5] transition-all shadow-sm text-[15px]">
            {t('header.signIn')}
          </button>
          <button className="px-9 py-3.5 bg-white text-slate-700 border-2 border-slate-200 rounded-xl font-semibold hover:border-slate-300 hover:bg-slate-50 transition-all text-[15px]">
            {t('header.getStarted')}
          </button>
        </div>

        {/* Mode Selector */}
        <div className="flex items-center justify-center gap-2 mb-6">
          <button
            onClick={() => setActiveMode('search')}
            className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold transition-all ${
              activeMode === 'search'
                ? 'bg-white text-[#4F6BFF] shadow-sm border border-slate-200'
                : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'
            }`}
          >
            <Search className="w-4 h-4" />
            {t('hero.searchMarketplace')}
          </button>
          <button
            onClick={() => setActiveMode('ai')}
            className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold transition-all ${
              activeMode === 'ai'
                ? 'bg-white text-[#4F6BFF] shadow-sm border border-slate-200'
                : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'
            }`}
          >
            <Sparkles className="w-4 h-4" />
            {t('hero.getRecommendations')}
          </button>
          <button
            onClick={() => setActiveMode('sourcing')}
            className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold transition-all ${
              activeMode === 'sourcing'
                ? 'bg-white text-[#4F6BFF] shadow-sm border border-slate-200'
                : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'
            }`}
          >
            <Package className="w-4 h-4" />
            {t('hero.submitRequest')}
          </button>
          <button
            onClick={() => setActiveMode('links')}
            className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold transition-all ${
              activeMode === 'links'
                ? 'bg-white text-[#4F6BFF] shadow-sm border border-slate-200'
                : 'text-slate-600 hover:bg-white/80 hover:text-slate-900'
            }`}
          >
            <Link className="w-4 h-4" />
            {t('hero.submitLinks')}
          </button>
        </div>

        {/* Main Search Input */}
        <form onSubmit={handleSubmit} className="mb-6">
          <div className="relative">
            <div className="absolute left-6 top-1/2 -translate-y-1/2">
              {activeMode === 'search' && <Search className="w-6 h-6 text-gray-400" />}
              {activeMode === 'ai' && <Sparkles className="w-6 h-6 text-blue-500" />}
              {activeMode === 'sourcing' && <Package className="w-6 h-6 text-indigo-500" />}
              {activeMode === 'links' && <Link className="w-6 h-6 text-purple-500" />}
            </div>
            {activeMode === 'links' ? (
              <textarea
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={placeholders[activeMode]}
                rows={3}
                className="w-full pl-16 pr-6 py-6 bg-white rounded-2xl shadow-xl border-2 border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder:text-gray-400 resize-none"
              />
            ) : (
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={placeholders[activeMode]}
                className="w-full pl-16 pr-40 py-6 bg-white rounded-2xl shadow-xl border-2 border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder:text-gray-400"
              />
            )}
            <button
              type="submit"
              className="absolute right-3 top-1/2 -translate-y-1/2 px-8 py-3 bg-[#4F6BFF] text-white rounded-xl font-semibold hover:bg-[#3F5AF5] transition-all shadow-sm"
            >
              {activeMode === 'search' ? t('hero.searchMarketplace') : 
               activeMode === 'ai' ? t('hero.getRecommendations') : 
               activeMode === 'sourcing' ? t('hero.submitRequest') : 
               t('hero.submitLinks')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}