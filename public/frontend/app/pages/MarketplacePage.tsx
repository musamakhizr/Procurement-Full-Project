import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { Search, SlidersHorizontal, Grid3x3, List, Package, Clock, ChevronRight, Home, Check } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { fetchCategories, fetchProducts, ProductSummary } from '../api';
import { useAuth } from '../contexts/AuthContext';

type ViewMode = 'grid' | 'list';

type CategoryNode = {
  id: number;
  name: string;
  slug: string;
  children: Array<{
    id: number;
    name: string;
    slug: string;
  }>;
};

export function MarketplacePage() {
  const { t } = useLanguage();
  const { addItem, isInList } = useProcurementList();
  const { isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const [categories, setCategories] = useState<CategoryNode[]>([]);
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [showFilters, setShowFilters] = useState(false);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('search') ?? '');
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') ?? 'all');
  const [selectedSubcategory, setSelectedSubcategory] = useState<string | null>(searchParams.get('subcategory'));
  const [moqMax, setMoqMax] = useState(searchParams.get('moq_max') ?? '');
  const [leadTimeMax, setLeadTimeMax] = useState(searchParams.get('lead_time_max') ?? '');
  const [verifiedOnly, setVerifiedOnly] = useState(searchParams.get('verified_only') === '1');
  const [customizableOnly, setCustomizableOnly] = useState(searchParams.get('customizable_only') === '1');
  const [sort, setSort] = useState(searchParams.get('sort') ?? '');

  useEffect(() => {
    fetchCategories().then(setCategories);
  }, []);

  useEffect(() => {
    const loadProducts = async () => {
      setIsLoading(true);

      try {
        const response = await fetchProducts({
          category: selectedCategory !== 'all' ? selectedCategory : undefined,
          subcategory: selectedSubcategory ?? undefined,
          search: searchQuery || undefined,
          moq_max: moqMax || undefined,
          lead_time_max: leadTimeMax || undefined,
          verified_only: verifiedOnly || undefined,
          customizable_only: customizableOnly || undefined,
          sort: sort || undefined,
        });

        setProducts(response.data);
      } finally {
        setIsLoading(false);
      }
    };

    const nextParams = new URLSearchParams();

    if (selectedCategory !== 'all') nextParams.set('category', selectedCategory);
    if (selectedSubcategory) nextParams.set('subcategory', selectedSubcategory);
    if (searchQuery) nextParams.set('search', searchQuery);
    if (moqMax) nextParams.set('moq_max', moqMax);
    if (leadTimeMax) nextParams.set('lead_time_max', leadTimeMax);
    if (verifiedOnly) nextParams.set('verified_only', '1');
    if (customizableOnly) nextParams.set('customizable_only', '1');
    if (sort) nextParams.set('sort', sort);

    setSearchParams(nextParams, { replace: true });
    void loadProducts();
  }, [customizableOnly, leadTimeMax, moqMax, searchQuery, selectedCategory, selectedSubcategory, setSearchParams, sort, verifiedOnly]);

  const normalizedCategories = useMemo(
    () => [{ id: 0, name: t('marketplace.categoryAll'), slug: 'all', children: [] }, ...categories],
    [categories, t],
  );

  const currentCategory = normalizedCategories.find((category) => category.slug === selectedCategory);

  const handleAddToList = async (product: ProductSummary, event: React.MouseEvent) => {
    event.preventDefault();

    if (!isAuthenticated) {
      navigate('/sign-in');
      return;
    }

    await addItem(product);
  };

  const handleRequestQuote = (productId: number, event: React.MouseEvent) => {
    event.preventDefault();

    if (!isAuthenticated) {
      navigate('/sign-in');
      return;
    }

    navigate(`/sourcing?product=${productId}`);
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1400px] mx-auto px-6">
        {selectedCategory !== 'all' && (
          <div className="flex items-center gap-1.5 text-xs text-slate-500 mb-3">
            <Link to="/" className="hover:text-[#4F6BFF] transition-colors flex items-center gap-1">
              <Home className="w-3 h-3" />
              <span>{t('product.home')}</span>
            </Link>
            <ChevronRight className="w-3 h-3 text-slate-300" />
            <Link to="/marketplace" className="hover:text-[#4F6BFF] transition-colors">
              {t('product.catalog')}
            </Link>
            <ChevronRight className="w-3 h-3 text-slate-300" />
            <span className="font-semibold text-slate-700">{currentCategory?.name}</span>
          </div>
        )}

        <div className="mb-6">
          <h1 className="text-2xl font-bold text-slate-900 mb-1.5">
            {selectedCategory === 'all' ? t('marketplace.title') : currentCategory?.name}
          </h1>
          <div className="flex items-center gap-2.5 text-sm">
            <p className="text-slate-600">
              {selectedCategory === 'all' ? t('marketplace.subtitle') : 'Browse business-ready products with volume pricing and procurement support'}
            </p>
            {selectedCategory !== 'all' && (
              <>
                <span className="text-slate-300">•</span>
                <p className="font-semibold text-[#4F6BFF]">{products.length} products</p>
              </>
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-3.5 mb-5">
          <div className="flex gap-2.5 items-center">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                placeholder={t('marketplace.searchPlaceholder')}
                value={searchQuery}
                onChange={(event) => setSearchQuery(event.target.value)}
                className="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:border-[#4F6BFF] focus:ring-2 focus:ring-[#4F6BFF]/10 focus:bg-white text-sm text-slate-900 placeholder:text-slate-400"
              />
            </div>

            <button onClick={() => setShowFilters(!showFilters)} className={`flex items-center gap-1.5 px-3 py-2 border rounded-lg font-medium text-sm transition-colors ${showFilters ? 'bg-[#EEF2FF] border-[#4F6BFF] text-[#4F6BFF]' : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'}`}>
              <SlidersHorizontal className="w-4 h-4" />
              <span className="hidden sm:inline">{t('marketplace.filters')}</span>
            </button>

            <div className="flex gap-0.5 bg-slate-100 rounded-lg p-0.5">
              <button onClick={() => setViewMode('grid')} className={`p-1.5 rounded-md transition-colors ${viewMode === 'grid' ? 'bg-white text-[#4F6BFF] shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}>
                <Grid3x3 className="w-4 h-4" />
              </button>
              <button onClick={() => setViewMode('list')} className={`p-1.5 rounded-md transition-colors ${viewMode === 'list' ? 'bg-white text-[#4F6BFF] shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}>
                <List className="w-4 h-4" />
              </button>
            </div>
          </div>

          {showFilters && (
            <div className="pt-4 mt-4 border-t border-slate-200 grid grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <label className="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">{t('marketplace.filterMOQ')}</label>
                <select value={moqMax} onChange={(event) => setMoqMax(event.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#4F6BFF] text-sm text-slate-700 bg-white">
                  <option value="">{t('marketplace.filterAny')}</option>
                  <option value="10">1-10 {t('marketplace.units')}</option>
                  <option value="50">11-50 {t('marketplace.units')}</option>
                  <option value="999999">50+ {t('marketplace.units')}</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">{t('marketplace.filterLeadTime')}</label>
                <select value={leadTimeMax} onChange={(event) => setLeadTimeMax(event.target.value)} className="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-[#4F6BFF] text-sm text-slate-700 bg-white">
                  <option value="">{t('marketplace.filterAny')}</option>
                  <option value="3">1-3 days</option>
                  <option value="7">4-7 days</option>
                  <option value="999">7+ days</option>
                </select>
              </div>
              <div className="col-span-2">
                <label className="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">{t('marketplace.filterOptions')}</label>
                <div className="flex gap-4">
                  <label className="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" checked={verifiedOnly} onChange={(event) => setVerifiedOnly(event.target.checked)} className="w-4 h-4 rounded border-slate-300 text-[#4F6BFF] focus:ring-[#4F6BFF]" />
                    <span className="text-sm text-slate-700 group-hover:text-slate-900">{t('marketplace.filterVerifiedOnly')}</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" checked={customizableOnly} onChange={(event) => setCustomizableOnly(event.target.checked)} className="w-4 h-4 rounded border-slate-300 text-[#4F6BFF] focus:ring-[#4F6BFF]" />
                    <span className="text-sm text-slate-700 group-hover:text-slate-900">{t('marketplace.filterCustomizable')}</span>
                  </label>
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="mb-5">
          <div className="flex items-center gap-2 mb-3.5 overflow-x-auto pb-2">
            {normalizedCategories.map((category) => (
              <button
                key={category.slug}
                onClick={() => {
                  setSelectedCategory(category.slug);
                  setSelectedSubcategory(null);
                }}
                className={`px-4 py-2 rounded-lg font-semibold text-sm whitespace-nowrap transition-all ${selectedCategory === category.slug ? 'bg-[#4F6BFF] text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-white border border-slate-200 hover:border-slate-300'}`}
              >
                {category.name}
              </button>
            ))}
          </div>

          {!!currentCategory?.children?.length && (
            <div className="flex items-center gap-2 pl-2">
              <span className="text-slate-300 text-sm mr-0.5">?</span>
              {currentCategory.children.map((subcategory) => (
                <button
                  key={subcategory.slug}
                  onClick={() => setSelectedSubcategory(selectedSubcategory === subcategory.slug ? null : subcategory.slug)}
                  className={`px-3 py-1.5 rounded-full text-xs font-semibold transition-all ${selectedSubcategory === subcategory.slug ? 'bg-[#7C3AED] text-white' : 'bg-white text-slate-600 hover:text-[#7C3AED] hover:bg-[#F3E8FF] border border-slate-200 hover:border-[#7C3AED]/30'}`}
                >
                  {subcategory.name}
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="flex items-center justify-between mb-4">
          <p className="text-sm text-slate-600">
            <span className="font-bold text-slate-900">{products.length}</span> {selectedCategory === 'all' ? t('marketplace.productsAvailable') : `products in ${currentCategory?.name}`}
          </p>
          <select value={sort} onChange={(event) => setSort(event.target.value)} className="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none focus:border-[#4F6BFF] text-slate-700 bg-white">
            <option value="">{t('marketplace.sortRelevance')}</option>
            <option value="price_low">{t('marketplace.sortPriceLow')}</option>
            <option value="price_high">{t('marketplace.sortPriceHigh')}</option>
            <option value="lead_time">{t('marketplace.sortLeadTime')}</option>
            <option value="moq">{t('marketplace.sortMOQ')}</option>
          </select>
        </div>

        {isLoading ? (
          <div className="bg-white rounded-xl border border-slate-200 p-10 text-center text-slate-500">Loading catalog...</div>
        ) : (
          <div className={viewMode === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5' : 'space-y-4'}>
            {products.map((product) => (
              <Link key={product.id} to={`/marketplace/product/${product.id}`} className="bg-white rounded-xl border border-slate-200 overflow-hidden hover:border-[#4F6BFF]/40 hover:shadow-md transition-all group block">
                <div className="aspect-video bg-slate-100 relative overflow-hidden">
                  <img src={product.image ?? 'https://placehold.co/800x600?text=Product'} alt={product.name} className="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                </div>
                <div className="p-5">
                  <div className="text-xs text-[#7C3AED] font-semibold mb-2">{product.category}</div>
                  <h3 className="font-bold text-slate-900 mb-3 group-hover:text-[#4F6BFF] transition-colors line-clamp-2">{product.name}</h3>

                  <div className="space-y-1.5 mb-3 pb-3 border-b border-slate-100">
                    <div className="flex items-center gap-2 text-xs text-slate-600">
                      <Package className="w-3.5 h-3.5 text-slate-400" />
                      <span className="font-medium">{t('marketplace.moq')}</span>
                      <span>{product.moq} {t('marketplace.units')}</span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-slate-600">
                      <Clock className="w-3.5 h-3.5 text-slate-400" />
                      <span className="font-medium">{t('marketplace.leadTime')}</span>
                      <span>{product.lead_time}</span>
                    </div>
                  </div>

                  <div className="mb-4">
                    <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{t('marketplace.businessPricing')}</div>
                    <div className="space-y-1">
                      {product.price_tier_1 && (
                        <div className="flex items-center justify-between">
                          <span className="text-xs text-slate-600">{product.price_tier_1.range}</span>
                          <span className="font-bold text-slate-900">¥{product.price_tier_1.price.toFixed(2)}</span>
                        </div>
                      )}
                      {product.price_tier_2 && (
                        <div className="flex items-center justify-between">
                          <span className="text-xs text-slate-600">{product.price_tier_2.range}</span>
                          <span className="font-bold text-[#4F6BFF]">¥{product.price_tier_2.price.toFixed(2)}</span>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="flex gap-2">
                    <button onClick={(event) => void handleAddToList(product, event)} className={`flex-1 py-2.5 text-sm font-semibold rounded-lg transition-all flex items-center justify-center gap-1.5 ${isInList(product.id) ? 'bg-emerald-500 text-white hover:bg-emerald-600' : 'bg-[#4F6BFF] text-white hover:bg-[#3D56E0]'}`}>
                      {isInList(product.id) && <Check className="w-4 h-4" />}
                      <span>{isInList(product.id) ? t('marketplace.addedToList') : t('marketplace.addToList')}</span>
                    </button>
                    <button onClick={(event) => handleRequestQuote(product.id, event)} className="px-4 py-2.5 text-sm border border-slate-200 text-slate-700 font-semibold rounded-lg hover:border-[#4F6BFF] hover:text-[#4F6BFF] transition-colors">
                      {t('marketplace.quote')}
                    </button>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}

        <div className="mt-12 bg-gradient-to-r from-[#EEF2FF] to-[#F3E8FF] rounded-xl border border-slate-200 p-8">
          <div className="max-w-2xl mx-auto text-center">
            <h3 className="text-xl font-bold text-slate-900 mb-2">{t('marketplace.supportTitle')}</h3>
            <p className="text-slate-700 text-sm mb-5">{t('marketplace.supportSubtitle')}</p>
            <div className="flex gap-3 justify-center">
              <Link to="/sourcing" className="px-5 py-2.5 bg-[#4F6BFF] text-white font-semibold rounded-lg hover:bg-[#3D56E0] transition-colors text-sm">
                {t('marketplace.customSourcingBtn')}
              </Link>
              <button onClick={() => navigate('/sourcing')} className="px-5 py-2.5 bg-white text-slate-700 font-semibold rounded-lg border border-slate-200 hover:border-[#4F6BFF] hover:text-[#4F6BFF] transition-colors text-sm">
                {t('marketplace.contactTeamBtn')}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
