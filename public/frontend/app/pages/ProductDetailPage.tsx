import { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router';
import { ChevronRight, Package, Clock, CheckCircle, MessageSquare, Download, ShoppingCart, Loader2 } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { fetchProduct, ProductDetail } from '../api';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { useAuth } from '../contexts/AuthContext';

export function ProductDetailPage() {
  const { id } = useParams();
  const { t } = useLanguage();
  const { addItem, addItems, isInList } = useProcurementList();
  const { isAuthenticated } = useAuth();
  const navigate = useNavigate();

  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [selectedImage, setSelectedImage] = useState(0);
  const [selectedOptions, setSelectedOptions] = useState<Record<string, string>>({});
  const [variantQuantities, setVariantQuantities] = useState<Record<number, number>>({});
  const [isAddingSelectedSkus, setIsAddingSelectedSkus] = useState(false);
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
    setVariantQuantities({});
  }, [product?.id]);

  useEffect(() => {
    if (!product) {
      return;
    }

    setSelectedImage((current) => {
      if (product.images.length === 0) {
        return 0;
      }

      return current >= product.images.length ? 0 : current;
    });
  }, [product?.images.length]);

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
  const galleryImages = selectedVariant?.image
    ? [selectedVariant.image, ...product.images.filter((image) => image !== selectedVariant.image)]
    : product.images;
  const currentUnitPrice = selectedVariant?.original_price
    ?? selectedVariant?.price
    ?? product.base_price
    ?? 0;
  const heroImage = galleryImages[selectedImage]
    ?? galleryImages[0]
    ?? product.image_source_url
    ?? 'https://placehold.co/800x800?text=Product';
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
      setSelectedImage(0);
      setSelectedOptions(nextSelection);
      return;
    }

    const compatibleVariant = product.variants.find((variant) =>
      variant.option_values.some((option) => option.group_name === groupName && option.key === optionKey)
    );

    if (!compatibleVariant) {
      return;
    }

    setSelectedImage(0);
    setSelectedOptions(
      Object.fromEntries(compatibleVariant.option_values.map((option) => [option.group_name, option.key]))
    );
  };

  const selectVariantForPreview = (variant: ProductDetail['variants'][number]) => {
    setSelectedImage(0);
    setSelectedOptions(
      Object.fromEntries(variant.option_values.map((option) => [option.group_name, option.key]))
    );
  };

  const normalizeVariantQuantity = (variant: ProductDetail['variants'][number], quantityValue: number) => {
    if (!Number.isFinite(quantityValue) || quantityValue <= 0) {
      return 0;
    }

    const maxStock = Math.max(0, variant.stock_quantity || 0);
    const minimumQuantity = Math.max(1, product.moq);
    const normalizedQuantity = Math.max(minimumQuantity, Math.floor(quantityValue));

    return maxStock > 0 ? Math.min(normalizedQuantity, maxStock) : normalizedQuantity;
  };

  const updateVariantQuantity = (variant: ProductDetail['variants'][number], quantityValue: number) => {
    const nextQuantity = normalizeVariantQuantity(variant, quantityValue);

    selectVariantForPreview(variant);
    setVariantQuantities((currentQuantities) => {
      if (nextQuantity === 0) {
        const { [variant.id]: _removed, ...remainingQuantities } = currentQuantities;
        return remainingQuantities;
      }

      return {
        ...currentQuantities,
        [variant.id]: nextQuantity,
      };
    });
  };

  const adjustVariantQuantity = (variant: ProductDetail['variants'][number], delta: number) => {
    const currentQuantity = variantQuantities[variant.id] ?? 0;
    const minimumQuantity = Math.max(1, product.moq);
    const nextQuantity = delta > 0 && currentQuantity === 0
      ? minimumQuantity
      : currentQuantity + delta;

    updateVariantQuantity(variant, nextQuantity < minimumQuantity ? 0 : nextQuantity);
  };

  const selectedSkuRows = product.variants
    .map((variant) => ({
      variant,
      quantity: variantQuantities[variant.id] ?? 0,
    }))
    .filter((row) => row.quantity > 0);
  const selectedSkuQuantity = selectedSkuRows.reduce((sum, row) => sum + row.quantity, 0);
  const selectedSkuSubtotal = selectedSkuRows.reduce((sum, row) => sum + (row.quantity * row.variant.price), 0);
  const currencySymbol = '\u00A5';

  const handleAddSelectedSkus = async (redirectToList = false) => {
    if (!isAuthenticated) {
      navigate('/sign-in');
      return;
    }

    if (selectedSkuRows.length === 0 || isAddingSelectedSkus) {
      return;
    }

    setIsAddingSelectedSkus(true);

    try {
      await addItems(selectedSkuRows.map((row) => ({
        product_id: product.id,
        product_variant_id: row.variant.id,
        quantity: row.quantity,
      })));

      if (redirectToList) {
        navigate('/procurement-list');
      }
    } finally {
      setIsAddingSelectedSkus(false);
    }
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

            {product.variants.length > 0 && (
              <>
                <div className="mb-6 overflow-hidden rounded-2xl border-2 border-slate-200 bg-white">
                  <div className="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-slate-100 px-5 py-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <h3 className="text-lg font-bold text-slate-900">Select SKUs</h3>
                        <p className="text-sm text-slate-600">Choose one or multiple variants from this product.</p>
                      </div>
                      <button
                        type="button"
                        disabled={selectedSkuRows.length === 0}
                        onClick={() => setVariantQuantities({})}
                        className="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 transition-colors hover:bg-white hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
                      >
                        Clear selection
                      </button>
                    </div>
                  </div>

                  <div className="max-h-[560px] overflow-y-auto">
                    <div className="divide-y divide-slate-100">
                      {product.variants.map((variant) => {
                        const rowQuantity = variantQuantities[variant.id] ?? 0;
                        const rowSubtotal = rowQuantity * variant.price;
                        const rowImage = variant.image ?? product.image_source_url ?? 'https://placehold.co/120x120?text=SKU';
                        const rowLabel = variant.label ?? variant.sku_id ?? variant.properties_name ?? product.name;
                        const maxStock = variant.stock_quantity || 0;
                        const isSelected = rowQuantity > 0;
                        const isCurrent = selectedVariant?.id === variant.id;
                        const optionLabel = variant.option_values
                          .map((option) => `${option.group_name}: ${option.value}`)
                          .join(' / ');

                        return (
                          <div
                            key={variant.id}
                            role="button"
                            tabIndex={0}
                            onClick={() => selectVariantForPreview(variant)}
                            onKeyDown={(event) => {
                              if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                selectVariantForPreview(variant);
                              }
                            }}
                            className={`grid cursor-pointer grid-cols-[56px_minmax(0,1fr)] gap-3 border-l-4 px-4 py-3 transition-all md:grid-cols-[56px_minmax(0,1fr)_90px_76px_132px_86px] md:items-center ${
                              isSelected
                                ? 'border-l-[#4F6BFF] bg-[#EEF2FF]/70'
                                : isCurrent
                                  ? 'border-l-[#F97316] bg-[#FFF7ED]/70'
                                  : 'border-l-transparent hover:bg-slate-50'
                            }`}
                          >
                            <img src={rowImage} alt={rowLabel} className="h-14 w-14 rounded-xl border border-slate-200 bg-slate-50 object-cover" />

                            <div className="min-w-0">
                              <div className="line-clamp-2 text-sm font-bold text-slate-900">{rowLabel}</div>
                              {optionLabel && <div className="mt-1 line-clamp-1 text-xs text-slate-500">{optionLabel}</div>}
                              <div className="mt-2 flex flex-wrap gap-1.5">
                                {isInList(product.id, variant.id) && (
                                  <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Already in list</span>
                                )}
                                {isCurrent && (
                                  <span className="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-700">Previewing</span>
                                )}
                              </div>
                            </div>

                            <div className="text-left md:text-right">
                              <div className="text-sm font-bold text-slate-900">{currencySymbol}{variant.price.toFixed(2)}</div>
                              <div className="text-[11px] font-medium text-slate-500">Unit price</div>
                            </div>

                            <div className="text-left md:text-right">
                              <div className={`text-sm font-bold ${maxStock <= product.moq ? 'text-orange-600' : 'text-slate-800'}`}>
                                {maxStock > 9999 ? '9999+' : maxStock.toLocaleString()}
                              </div>
                              <div className="text-[11px] font-medium text-slate-500">{maxStock <= product.moq ? 'Low stock' : 'Stock'}</div>
                            </div>

                            <div className="flex items-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                              <button
                                type="button"
                                onClick={(event) => {
                                  event.stopPropagation();
                                  adjustVariantQuantity(variant, -1);
                                }}
                                disabled={rowQuantity <= 0}
                                className="h-10 w-10 border-r border-slate-200 font-bold text-slate-600 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                              >
                                -
                              </button>
                              <input
                                type="number"
                                value={rowQuantity}
                                min={0}
                                max={maxStock || undefined}
                                onClick={(event) => event.stopPropagation()}
                                onChange={(event) => updateVariantQuantity(variant, parseInt(event.target.value, 10) || 0)}
                                className="h-10 min-w-0 flex-1 border-0 text-center text-sm font-bold text-slate-900 outline-none"
                              />
                              <button
                                type="button"
                                onClick={(event) => {
                                  event.stopPropagation();
                                  adjustVariantQuantity(variant, 1);
                                }}
                                disabled={maxStock > 0 && rowQuantity >= maxStock}
                                className="h-10 w-10 border-l border-slate-200 font-bold text-slate-600 transition-colors hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                              >
                                +
                              </button>
                            </div>

                            <div className="text-left md:text-right">
                              <div className={`text-sm font-bold ${isSelected ? 'text-[#4F6BFF]' : 'text-slate-300'}`}>
                                {isSelected ? `${currencySymbol}${rowSubtotal.toFixed(2)}` : '-'}
                              </div>
                              <div className="text-[11px] font-medium text-slate-500">Subtotal</div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </div>

                <div className="mb-6 rounded-2xl border-2 border-slate-200 bg-white p-6">
                  <div className="mb-4 flex items-center justify-between">
                    <div>
                      <h3 className="text-lg font-bold text-slate-900">Order Summary</h3>
                      <p className="text-sm text-slate-500">
                        {selectedSkuRows.length > 0
                          ? `${selectedSkuRows.length} SKU${selectedSkuRows.length === 1 ? '' : 's'} selected`
                          : 'Select at least one SKU to add this product.'}
                      </p>
                    </div>
                    <div className="rounded-full bg-[#EEF2FF] px-3 py-1 text-sm font-bold text-[#4F6BFF]">{selectedSkuRows.length}</div>
                  </div>

                  {selectedSkuRows.length > 0 && (
                    <div className="mb-5 max-h-64 space-y-2 overflow-y-auto pr-1">
                      {selectedSkuRows.map((row) => {
                        const rowImage = row.variant.image ?? product.image_source_url ?? 'https://placehold.co/120x120?text=SKU';
                        const rowLabel = row.variant.label ?? row.variant.sku_id ?? product.name;

                        return (
                          <div key={row.variant.id} className="flex gap-3 rounded-2xl border border-[#4F6BFF]/20 bg-[#EEF2FF]/60 p-3">
                            <img src={rowImage} alt={rowLabel} className="h-12 w-12 rounded-xl border border-white object-cover shadow-sm" />
                            <div className="min-w-0 flex-1">
                              <div className="line-clamp-1 text-sm font-bold text-slate-900">{rowLabel}</div>
                              <div className="mt-1 text-xs text-slate-600">{currencySymbol}{row.variant.price.toFixed(2)} x {row.quantity}</div>
                            </div>
                            <div className="text-right text-sm font-bold text-[#4F6BFF]">{currencySymbol}{(row.variant.price * row.quantity).toFixed(2)}</div>
                          </div>
                        );
                      })}
                    </div>
                  )}

                  <div className="mb-5 space-y-3 border-t border-slate-200 pt-4">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-semibold text-slate-600">Total quantity</span>
                      <span className="font-bold text-slate-900">{selectedSkuQuantity} {t('marketplace.units')}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-semibold text-slate-600">Subtotal</span>
                      <span className="font-bold text-slate-900">{currencySymbol}{selectedSkuSubtotal.toFixed(2)}</span>
                    </div>
                    <div className="flex items-center justify-between border-t border-slate-200 pt-3">
                      <span className="text-base font-bold text-slate-900">{t('product.totalEstimate')}</span>
                      <span className="text-2xl font-bold text-[#4F6BFF]">{currencySymbol}{selectedSkuSubtotal.toFixed(2)}</span>
                    </div>
                  </div>

                  <div className="space-y-3">
                    <button
                      type="button"
                      onClick={() => void handleAddSelectedSkus(false)}
                      disabled={selectedSkuRows.length === 0 || isAddingSelectedSkus}
                      className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#4F6BFF] py-4 font-bold text-white transition-colors hover:bg-[#3D56E0] disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                      {isAddingSelectedSkus ? <Loader2 className="h-5 w-5 animate-spin" /> : <ShoppingCart className="h-5 w-5" />}
                      {isAddingSelectedSkus ? 'Adding selected SKUs...' : 'Add Selected SKUs to List'}
                    </button>
                    <button
                      type="button"
                      onClick={() => void handleAddSelectedSkus(true)}
                      disabled={selectedSkuRows.length === 0 || isAddingSelectedSkus}
                      className="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-[#4F6BFF] bg-white py-3 font-bold text-[#4F6BFF] transition-colors hover:bg-[#EEF2FF] disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-300"
                    >
                      <MessageSquare className="h-5 w-5" />
                      Request Quote
                    </button>
                  </div>
                </div>
              </>
            )}

            {product.variants.length === 0 && product.option_groups.length > 0 && (
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

            {product.variants.length === 0 && (
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
            )}

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
