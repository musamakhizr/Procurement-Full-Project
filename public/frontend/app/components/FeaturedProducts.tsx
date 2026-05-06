import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { Package, Clock, ArrowRight, Check } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchProducts, ProductSummary } from '../api';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { useAuth } from '../contexts/AuthContext';

export function FeaturedProducts() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const { addItem, isInList } = useProcurementList();
  const { isAuthenticated } = useAuth();
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchProducts({ sort: 'moq', per_page: 8 })
      .then((res) => setProducts(res.data.slice(0, 8)))
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, []);

  const handleAddToList = async (product: ProductSummary, event: React.MouseEvent) => {
    event.preventDefault();
    if (!isAuthenticated) {
      navigate('/sign-in');
      return;
    }
    await addItem(product);
  };

  return (
    <div className="max-w-[1400px] mx-auto px-6 py-20">
      <div className="flex items-center justify-between mb-10">
        <div>
          <h2 className="text-3xl font-bold text-slate-900 mb-2 tracking-tight">{t('featured.title')}</h2>
          <p className="text-lg text-slate-600 mb-1">{t('featured.subtitle')}</p>
          <p className="text-sm text-slate-500">{t('featured.note')}</p>
        </div>
        <Link
          to="/marketplace"
          className="flex items-center gap-2 px-6 py-3 text-[#4F6BFF] hover:bg-[#EEF2FF] rounded-xl transition-colors font-bold text-sm"
        >
          {t('featured.viewCatalog')}
          <ArrowRight className="w-4 h-4" />
        </Link>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="bg-white rounded-2xl border border-slate-200 overflow-hidden animate-pulse">
              <div className="aspect-video bg-slate-100" />
              <div className="p-5 space-y-3">
                <div className="h-3 bg-slate-100 rounded w-1/3" />
                <div className="h-4 bg-slate-100 rounded w-4/5" />
                <div className="h-3 bg-slate-100 rounded w-2/3" />
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {products.map((product) => (
            <Link
              key={product.id}
              to={`/marketplace/product/${product.id}`}
              className="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:border-[#4F6BFF]/40 hover:shadow-md transition-all group block"
            >
              <div className="aspect-video bg-slate-100 relative overflow-hidden">
                <img
                  src={product.image ?? `https://placehold.co/800x600?text=${encodeURIComponent(product.name.split(' ')[0])}`}
                  alt={product.name}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                />
                {product.verified && (
                  <span className="absolute top-2 right-2 bg-emerald-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                    Verified
                  </span>
                )}
              </div>
              <div className="p-5">
                <div className="text-xs text-[#7C3AED] font-semibold mb-1">{product.category}</div>
                <h3 className="font-bold text-slate-900 mb-3 group-hover:text-[#4F6BFF] transition-colors line-clamp-2 text-sm">
                  {product.name}
                </h3>

                <div className="space-y-1.5 mb-3 pb-3 border-b border-slate-100">
                  <div className="flex items-center gap-2 text-xs text-slate-600">
                    <Package className="w-3.5 h-3.5 text-slate-400" />
                    <span className="font-medium">MOQ:</span>
                    <span>{product.moq} units</span>
                  </div>
                  <div className="flex items-center gap-2 text-xs text-slate-600">
                    <Clock className="w-3.5 h-3.5 text-slate-400" />
                    <span className="font-medium">Lead time:</span>
                    <span>{product.lead_time}</span>
                  </div>
                </div>

                <div className="mb-4">
                  {product.price_tier_1 && (
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-slate-500 text-xs">{product.price_tier_1.range}</span>
                      <span className="font-bold text-slate-900">¥{product.price_tier_1.price.toFixed(2)}</span>
                    </div>
                  )}
                  {product.price_tier_2 && product.price_tier_2.price !== product.price_tier_1?.price && (
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-slate-500 text-xs">{product.price_tier_2.range}</span>
                      <span className="font-bold text-[#4F6BFF]">¥{product.price_tier_2.price.toFixed(2)}</span>
                    </div>
                  )}
                </div>

                <button
                  onClick={(event) => void handleAddToList(product, event)}
                  className={`w-full py-2.5 text-sm font-semibold rounded-xl transition-all flex items-center justify-center gap-1.5 ${
                    isInList(product.id)
                      ? 'bg-emerald-500 text-white hover:bg-emerald-600'
                      : 'bg-[#4F6BFF] text-white hover:bg-[#3D56E0]'
                  }`}
                >
                  {isInList(product.id) && <Check className="w-4 h-4" />}
                  {isInList(product.id) ? 'In List' : 'Add to List'}
                </button>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
