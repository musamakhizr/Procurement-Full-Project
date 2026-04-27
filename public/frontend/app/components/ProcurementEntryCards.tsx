import { Search, Sparkles, Package, Link as LinkIcon, ArrowRight } from 'lucide-react';
import { Link } from 'react-router';
import { useLanguage } from '../contexts/LanguageContext';

interface EntryCard {
  id: string;
  icon: React.ReactNode;
  titleKey: string;
  descKey: string;
  color: string;
  highlight?: boolean;
  path: string;
}

export function ProcurementEntryCards() {
  const { t } = useLanguage();
  
  const entries: EntryCard[] = [
    {
      id: 'marketplace',
      icon: <Search className="w-8 h-8" />,
      titleKey: 'tabs.search',
      descKey: 'entry.search.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
      path: '/marketplace',
    },
    {
      id: 'ai',
      icon: <Sparkles className="w-8 h-8" />,
      titleKey: 'tabs.ai',
      descKey: 'entry.ai.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
      path: '/#ai',
    },
    {
      id: 'sourcing',
      icon: <Package className="w-8 h-8" />,
      titleKey: 'tabs.sourcing',
      descKey: 'entry.sourcing.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
      highlight: true,
      path: '/sourcing',
    },
    {
      id: 'links',
      icon: <LinkIcon className="w-8 h-8" />,
      titleKey: 'tabs.links',
      descKey: 'entry.links.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
      highlight: true,
      path: '/sourcing',
    },
  ];

  return (
    <div className="max-w-[1400px] mx-auto px-6 py-20">
      <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
        {entries.map((entry) => (
          <Link
            key={entry.id}
            to={entry.path}
            className={`group relative p-8 bg-white rounded-2xl border-2 transition-all text-left hover:shadow-xl hover:-translate-y-1 ${
              entry.highlight
                ? 'border-[#7C3AED]/20 bg-gradient-to-br from-[#F3E8FF]/30 to-white hover:border-[#7C3AED]/40 shadow-md'
                : 'border-slate-200 hover:border-[#4F6BFF]/40 shadow-sm'
            }`}
          >
            <div className={`inline-flex p-4 ${entry.color} rounded-xl mb-6 group-hover:scale-110 transition-transform shadow-sm`}>
              {entry.icon}
            </div>

            <h3 className="text-xl font-bold text-slate-900 mb-3 group-hover:text-[#4F6BFF] transition-colors leading-tight">
              {t(entry.titleKey)}
            </h3>
            
            <p className="text-slate-600 mb-6 leading-relaxed text-[15px]">
              {t(entry.descKey)}
            </p>

            <div className={`flex items-center gap-2 font-semibold text-sm ${
              entry.highlight ? 'text-[#7C3AED]' : 'text-[#4F6BFF]'
            }`}>
              {t('header.signIn')}
              <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}