import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import {
  ArrowRight,
  Baby,
  BookOpen,
  Briefcase,
  Brush,
  Building2,
  FlaskConical,
  Laptop,
  LucideIcon,
  Music4,
  Package,
  PartyPopper,
  ShieldPlus,
  Sofa,
  Sparkles,
  Trophy,
  UtensilsCrossed,
} from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchCategories } from '../api';

type CategoryItem = {
  id: number;
  name: string;
  slug: string;
  children: unknown[];
};

const CATEGORY_ICONS: Record<string, LucideIcon> = {
  office: Briefcase,
  classroom: BookOpen,
  art: Brush,
  sports: Trophy,
  events: PartyPopper,
  technology: Laptop,
  'early-years': Baby,
  'science-lab': FlaskConical,
  'music-performing-arts': Music4,
  'furniture-storage': Sofa,
  'books-learning-resources': BookOpen,
  'sen-student-support': Sparkles,
  'facilities-campus-supplies': Building2,
  'cleaning-health-safety': ShieldPlus,
  'pantry-hospitality': UtensilsCrossed,
};

const CATEGORY_COLORS: Record<string, string> = {
  office: 'bg-[#EEF2FF] text-[#4F6BFF]',
  classroom: 'bg-[#EEF2FF] text-[#4F6BFF]',
  art: 'bg-[#F3E8FF] text-[#7C3AED]',
  sports: 'bg-[#F3E8FF] text-[#7C3AED]',
  events: 'bg-amber-50 text-amber-600',
  technology: 'bg-emerald-50 text-emerald-600',
  'early-years': 'bg-pink-50 text-pink-600',
  'science-lab': 'bg-cyan-50 text-cyan-600',
  'music-performing-arts': 'bg-rose-50 text-rose-600',
  'furniture-storage': 'bg-orange-50 text-orange-600',
  'books-learning-resources': 'bg-sky-50 text-sky-600',
  'sen-student-support': 'bg-violet-50 text-violet-600',
  'facilities-campus-supplies': 'bg-slate-100 text-slate-700',
  'cleaning-health-safety': 'bg-green-50 text-green-700',
  'pantry-hospitality': 'bg-yellow-50 text-yellow-700',
};

export function CategoryGrid() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [categories, setCategories] = useState<CategoryItem[]>([]);

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
          const Icon = CATEGORY_ICONS[category.slug] ?? Package;
          const color = CATEGORY_COLORS[category.slug] ?? 'bg-slate-50 text-slate-600';

          return (
            <button
              key={category.id}
              onClick={() => navigate(`/marketplace?category=${category.slug}`)}
              className="group p-6 bg-white border-2 border-slate-200 rounded-2xl hover:border-[#4F6BFF]/40 hover:shadow-lg transition-all text-left hover:-translate-y-0.5"
            >
              <div className={`w-12 h-12 ${color} rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform shadow-sm`}>
                <Icon className="w-6 h-6" />
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
