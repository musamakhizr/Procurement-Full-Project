import { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router';
import { ChevronRight, Package, Clock, CheckCircle, MessageSquare, Download } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchProduct, ProductDetail } from '../api';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { useAuth } from '../contexts/AuthContext';

export function ProductDetailPage() {
  const { id } = useParams();
  const { t } = useLanguage();
  const { addItem, isInList } = useProcurementList();
  const { isAuthenticated } = useAuth();
  const navigate = useNavigate();

  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [selectedImage, setSelectedImage] = useState(0);
  const [selectedOptions, setSelectedOptions] = useState<Record<string, string>>({});
  const [hasUserSelectedVariant, setHasUserSelectedVariant] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let isCancelled = false;

    const loadProduct = async (showLoader = false) => {
      if (!id) {
        return;
      }

      if (showLoader) {
        setIsLoading(true);
      }

      try {
        const response = await fetchProduct(id);
        if (isCancelled) {
          return;
        }

        setProduct((currentProduct) => {
          if (currentProduct === null) {
            setQuantity(response.moq);
            setHasUserSelectedVariant(false);
          }

          return response;
        });
      } finally {
        if (!isCancelled && showLoader) {
          setIsLoading(false);
        }
      }
    };

    void loadProduct(true);

    return () => {
      isCancelled = true;
    };
  }, [id]);

  useEffect(() => {
    if (!id || product?.import_status !== 'processing') {
      return;
    }

    let isCancelled = false;

    const pollProduct = async () => {
      try {
        const response = await fetchProduct(id);

        if (!isCancelled) {
          setProduct(response);
        }
      } catch {
        // Keep the existing UI state and try again on the next poll.
      }
    };

    const intervalId = window.setInterval(() => {
      void pollProduct();
    }, 4000);

    return () => {
      isCancelled = true;
      window.clearInterval(intervalId);
    };
  }, [id, product?.import_status]);

  useEffect(() => {
    if (!product) {
      return;
    }

    const defaultVariant = product.variants.find((variant) => variant.id === product.default_variant_id)
      ?? product.variants.find((variant) => variant.is_default)
      ?? product.variants[0];

    if (!defaultVariant) {
      setSelectedOptions((currentOptions) => (Object.keys(currentOptions).length === 0 ? {} : currentOptions));
      return;
    }

    const defaultOptions = Object.fromEntries(defaultVariant.option_values.map((option) => [option.group_name, option.key]));

    setSelectedOptions((currentOptions) => {
      if (Object.keys(currentOptions).length === 0) {
        return defaultOptions;
      }

      const hasCompatibleVariant = product.variants.some((variant) =>
        variant.option_values.every((option) => currentOptions[option.group_name] === option.key)
      );

      return hasCompatibleVariant ? currentOptions : defaultOptions;
    });
  }, [product]);

  useEffect(() => {
    if (!product) {
      return;
    }

    setSelectedImage(0);
  }, [product?.id]);

  if (isLoading) {
    return <div className="min-h-screen bg-[#F8FAFC] pt-24 px-6 text-slate-600">Loading product...</div>;
  }

  if (!product) {
    return <div className="min-h-screen bg-[#F8FAFC] pt-24 px-6 text-slate-600">Product not found.</div>;
  }

  const selectedVariant = (() => {
    if (product.variants.length === 0) {
      return null;
    }

    return product.variants.find((variant) =>
      variant.option_values.every((option) => selectedOptions[option.group_name] === option.key)
    ) ?? null;
  })();
  const galleryImages = product.images;
  const currentUnitPrice = selectedVariant?.original_price
    ?? selectedVariant?.price
    ?? product.base_price
    ?? 0;
  const heroImage = (
    hasUserSelectedVariant && selectedVariant?.image
      ? selectedVariant.image
      : galleryImages[selectedImage]
  ) ?? product.image_source_url ?? 'https://placehold.co/800x800?text=Product';
  const currentPrice = currentUnitPrice;

  const isOptionAvailable = (groupName: string, optionKey: string) => {
    if (product.variants.length === 0) {
      return true;
    }

    return product.variants.some((variant) =>
      variant.option_values.some((option) => option.group_name === groupName && option.key === optionKey)
      && variant.option_values.every((option) => option.group_name === groupName || selectedOptions[option.group_name] === option.key)
    );
  };

  const handleOptionSelect = (groupName: string, optionKey: string) => {
    const nextSelection = {
      ...selectedOptions,
      [groupName]: optionKey,
    };

    const exactMatch = product.variants.find((variant) =>
      variant.option_values.every((option) => nextSelection[option.group_name] === option.key)
    );

    if (exactMatch) {
      setHasUserSelectedVariant(true);
      setSelectedOptions(nextSelection);
      return;
    }

    const compatibleVariant = product.variants.find((variant) =>
      variant.option_values.some((option) => option.group_name === groupName && option.key === optionKey)
    );

    if (!compatibleVariant) {
      return;
    }

    setHasUserSelectedVariant(true);
    setSelectedOptions(
      Object.fromEntries(compatibleVariant.option_values.map((option) => [option.group_name, option.key]))
    );
  };

  const handleAddToList = async () => {
    if (!isAuthenticated) {
      navigate('/sign-in');
      return;
    }

    await addItem(product, quantity, selectedVariant?.id ?? null);
  };

  const handleQuote = () => {
    if (!isAuthenticated) {
      navigate('/sign-in');
      return;
    }

    navigate(`/sourcing?product=${product.id}`);
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1400px] mx-auto px-6">
        <div className="flex items-center gap-2 text-sm text-slate-600 mb-8">
          <Link to="/" className="hover:text-[#4F6BFF]">{t('product.home')}</Link>
          <ChevronRight className="w-4 h-4" />
          <Link to="/marketplace" className="hover:text-[#4F6BFF]">{t('product.catalog')}</Link>
          <ChevronRight className="w-4 h-4" />
          <span className="text-slate-900 font-medium">{product.name}</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
          <div>
            <div className="bg-white rounded-2xl border-2 border-slate-200 overflow-hidden mb-4">
              <div className="aspect-square bg-slate-100">
                <img src={heroImage} alt={product.name} className="w-full h-full object-cover" />
              </div>
            </div>
            <div className="grid grid-cols-4 gap-3">
              {galleryImages.map((image, index) => (
                <button
                  key={image}
                  onClick={() => setSelectedImage(index)}
                  className={`aspect-square rounded-xl border-2 overflow-hidden transition-all ${selectedImage === index ? 'border-[#4F6BFF] ring-2 ring-[#4F6BFF]/20' : 'border-slate-200 hover:border-slate-300'}`}
                >
                  <img src={image} alt="" className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          </div>

          <div>
            <div className="mb-6">
              <div className="text-sm text-[#7C3AED] font-semibold mb-2">{product.category}</div>
              <h1 className="text-3xl font-bold text-slate-900 mb-2">{product.name}</h1>
              <div className="text-sm text-slate-500">SKU: {product.sku}</div>
              {selectedVariant && (
                <div className="mt-3 inline-flex rounded-full bg-[#FFF4E8] px-4 py-2 text-sm font-semibold text-[#B45309]">
                  Selected: {selectedVariant.label ?? selectedVariant.sku_id}
                </div>
              )}
            </div>

            <div className="bg-[#EEF2FF] rounded-xl p-4 mb-6">
              <div className="flex items-center gap-3 mb-3">
                <CheckCircle className="w-5 h-5 text-[#4F6BFF]" />
                <span className="font-semibold text-slate-900">{product.in_stock ? t('product.inStock') : 'Out of Stock'}</span>
                <span className="text-slate-600">({product.stock_quantity} {t('marketplace.units')} {t('product.available')})</span>
              </div>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div className="flex items-center gap-2 text-slate-700">
                  <Package className="w-4 h-4 text-slate-400" />
                  <span className="font-medium">{t('marketplace.moq')}</span>
                  <span>{product.moq} {t('marketplace.units')}</span>
                </div>
                <div className="flex items-center gap-2 text-slate-700">
                  <Clock className="w-4 h-4 text-slate-400" />
                  <span className="font-medium">{t('marketplace.leadTime')}</span>
                  <span>{product.lead_time}</span>
                </div>
              </div>
            </div>

            {product.option_groups.length > 0 && (
              <div className="bg-white rounded-2xl border-2 border-slate-200 p-6 mb-6">
                <h3 className="text-lg font-bold text-slate-900 mb-4">Choose Options</h3>
                <div className="space-y-5">
                  {product.option_groups.map((group) => (
                    <div key={group.name}>
                      <p className="mb-3 text-sm font-semibold text-slate-700">{group.name}</p>
                      <div className="flex flex-wrap gap-3">
                        {group.values.map((option) => {
                          const isSelected = selectedOptions[group.name] === option.key;
                          const isAvailable = isOptionAvailable(group.name, option.key);

                          return (
                            <button
                              key={option.key}
                              type="button"
                              disabled={!isAvailable}
                              onClick={() => handleOptionSelect(group.name, option.key)}
                              className={`min-w-[120px] rounded-2xl border-2 px-4 py-3 text-left transition-all ${
                                isSelected
                                  ? 'border-[#F97316] bg-[#FFF7ED] shadow-sm'
                                  : 'border-slate-200 bg-white hover:border-[#F97316]/60'
                              } ${!isAvailable ? 'cursor-not-allowed opacity-40' : ''}`}
                            >
                              {option.image && (
                                <div className="mb-2 h-14 w-14 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                  <img src={option.image} alt={option.value} className="h-full w-full object-cover" />
                                </div>
                              )}
                              <div className="text-sm font-semibold text-slate-900">{option.value}</div>
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="bg-white rounded-2xl border-2 border-slate-200 p-6 mb-6">
              <label className="block text-sm font-semibold text-slate-700 mb-3">{t('product.quantity')}</label>
              <div className="flex items-center gap-4 mb-4">
                <button onClick={() => setQuantity(Math.max(product.moq, quantity - 1))} className="w-12 h-12 border-2 border-slate-200 rounded-lg hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors font-bold text-slate-700">-</button>
                <input
                  type="number"
                  value={quantity}
                  onChange={(event) => setQuantity(Math.max(product.moq, parseInt(event.target.value, 10) || product.moq))}
                  min={product.moq}
                  className="flex-1 h-12 px-4 border-2 border-slate-200 rounded-lg text-center text-lg font-bold text-slate-900 focus:outline-none focus:border-[#4F6BFF]"
                />
                <button onClick={() => setQuantity(quantity + 1)} className="w-12 h-12 border-2 border-slate-200 rounded-lg hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors font-bold text-slate-700">+</button>
              </div>
              <div className="text-sm text-slate-600 mb-4">{t('product.minimumOrder')}: {product.moq} {t('marketplace.units')}</div>

              <div className="bg-slate-50 rounded-lg p-4 mb-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-slate-600">{t('product.unitPrice')}:</span>
                  <span className="text-xl font-bold text-slate-900">¥{currentPrice.toFixed(2)}</span>
                </div>
                {selectedVariant?.price !== null && selectedVariant?.original_price && selectedVariant.original_price !== selectedVariant.price && (
                  <div className="mb-2 flex items-center justify-between text-sm">
                    <span className="text-slate-500">Translated Price:</span>
                    <span className="font-semibold text-slate-700">¥{selectedVariant.price.toFixed(2)}</span>
                  </div>
                )}
                <div className="flex items-center justify-between pt-2 border-t-2 border-slate-200">
                  <span className="font-semibold text-slate-900">{t('product.totalEstimate')}:</span>
                  <span className="text-2xl font-bold text-[#4F6BFF]">¥{(currentPrice * quantity).toFixed(2)}</span>
                </div>
              </div>

              <div className="flex gap-3">
                <button onClick={() => void handleAddToList()} className="flex-1 py-4 bg-[#4F6BFF] text-white font-bold rounded-xl hover:bg-[#3D56E0] transition-colors">
                  {isInList(product.id, selectedVariant?.id ?? null) ? t('marketplace.addedToList') : t('marketplace.addToList')}
                </button>
                <button onClick={handleQuote} className="px-6 py-4 border-2 border-slate-200 text-slate-700 font-bold rounded-xl hover:border-[#4F6BFF] hover:bg-[#EEF2FF] hover:text-[#4F6BFF] transition-colors">
                  {t('marketplace.quote')}
                </button>
              </div>
            </div>

            <div className="flex gap-3">
              <button onClick={handleQuote} className="flex-1 flex items-center justify-center gap-2 py-3 border-2 border-slate-200 text-slate-700 font-semibold rounded-xl hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors">
                <MessageSquare className="w-4 h-4" />
                {t('product.contactSupport')}
              </button>
              <button className="flex-1 flex items-center justify-center gap-2 py-3 border-2 border-slate-200 text-slate-700 font-semibold rounded-xl hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors">
                <Download className="w-4 h-4" />
                {t('product.downloadSpec')}
              </button>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-2xl border-2 border-slate-200 p-8">
          {product.import_status && product.import_status !== 'completed' && (
            <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              Product media is still being processed in the background. Some translated images may appear shortly.
            </div>
          )}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="space-y-8">
              <div>
                <h2 className="text-xl font-bold text-slate-900 mb-4">{t('product.description')}</h2>
                <p className="whitespace-pre-line text-slate-700 leading-relaxed">{product.description}</p>
              </div>

              {product.description_images.length > 0 && (
                <div>
                  <h3 className="text-lg font-bold text-slate-900 mb-4">Product Detail Images</h3>
                  <div className="space-y-4">
                    {product.description_images.map((image, index) => (
                      <div key={`${image}-${index}`} className="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <img src={image} alt={`${product.name} detail ${index + 1}`} className="w-full object-cover" />
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <div>
              <h2 className="text-xl font-bold text-slate-900 mb-4">{t('product.specifications')}</h2>
              <div className="space-y-3">
                {product.specifications.map((specification) => (
                  <div key={`${specification.label}-${specification.value}`} className="flex items-center justify-between py-2 border-b border-slate-200 last:border-0">
                    <span className="text-slate-600 font-medium">{specification.label}</span>
                    <span className="text-slate-900">{specification.value}</span>
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
