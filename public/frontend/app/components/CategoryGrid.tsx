import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { ArrowRight } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchCategories } from '../api';

const CATEGORY_ICONS: Record<string, string> = {
  office: '📎',
  classroom: '🎓',
  art: '🎨',
  sports: '⚽',
  events: '🎉',
  technology: '💻',
};

const CATEGORY_COLORS: Record<string, string> = {
  office: 'bg-[#EEF2FF] text-[#4F6BFF]',
  classroom: 'bg-[#EEF2FF] text-[#4F6BFF]',
  art: 'bg-[#F3E8FF] text-[#7C3AED]',
  sports: 'bg-[#F3E8FF] text-[#7C3AED]',
  events: 'bg-amber-50 text-amber-600',
  technology: 'bg-emerald-50 text-emerald-600',
};

export function CategoryGrid() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [categories, setCategories] = useState<Array<{ id: number; name: string; slug: string; children: any[] }>>([]);

  useEffect(() => {
    fetchCategories().then(setCategories).catch(() => {});
  }, []);

  return (
    <div className="max-w-[1400px] mx-auto px-6 py-16">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h2 className="text-3xl font-bold text-slate-900 mb-2 tracking-tight">{t('categories.browseTitle')}</h2>
          <p className="text-slate-600">{t('categories.description')}</p>
        </div>
        <button
          onClick={() => navigate('/marketplace')}
          className="flex items-center gap-2 px-5 py-2.5 text-[#4F6BFF] hover:bg-[#EEF2FF] rounded-xl transition-colors font-semibold text-sm"
        >
          {t('categories.viewAll')}
          <ArrowRight className="w-4 h-4" />
        </button>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5">
        {categories.map((category) => {
          const icon = CATEGORY_ICONS[category.slug] ?? '📦';
          const color = CATEGORY_COLORS[category.slug] ?? 'bg-slate-50 text-slate-600';

          return (
            <button
              key={category.id}
              onClick={() => navigate(`/marketplace?category=${category.slug}`)}
              className="group p-6 bg-white border-2 border-slate-200 rounded-2xl hover:border-[#4F6BFF]/40 hover:shadow-lg transition-all text-left hover:-translate-y-0.5"
            >
              <div className={`w-12 h-12 ${color} rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform shadow-sm`}>
                {icon}
              </div>
              <h3 className="font-bold text-slate-900 mb-1 group-hover:text-[#4F6BFF] transition-colors text-sm">
                {category.name}
              </h3>
              <p className="text-xs text-slate-500 font-medium">
                {category.children.length} subcategories
              </p>
            </button>
          );
        })}
      </div>
    </div>
  );
}
