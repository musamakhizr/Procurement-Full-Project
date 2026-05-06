import { useEffect, useMemo, useState } from 'react';
import { Search, Plus, Trash2, Package, TrendingUp, AlertCircle, Filter, Download, Upload, Pencil, ChevronLeft, ChevronRight } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { createAdminProduct, deleteAdminProduct, fetchAdminProductFromLink, fetchAdminProducts, fetchAdminStats, fetchCategories, fetchProduct, ImportedMarketplaceProduct, PaginatedResponse, ProductSummary, updateAdminProduct } from '../api';

const EMPTY_FORM = {
  name: '',
  sku: '',
  categoryId: '',
  description: '',
  moq: '1',
  stock: '0',
  leadMin: '3',
  leadMax: '5',
  basePrice: '1.00',
  imageUrl: '',
};

export function AdminProductsPage() {
  const { t } = useLanguage();
  const [searchQuery, setSearchQuery] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingProductId, setEditingProductId] = useState<number | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [stats, setStats] = useState({ total_products: 0, active_products: 0, low_stock: 0, categories: 0 });
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [pagination, setPagination] = useState<PaginatedResponse<ProductSummary>['meta']>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  });
  const [form, setForm] = useState(EMPTY_FORM);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState('');
  const [productLink, setProductLink] = useState('');
  const [isImporting, setIsImporting] = useState(false);
  const [importError, setImportError] = useState('');
  const [previewProduct, setPreviewProduct] = useState<ImportedMarketplaceProduct | null>(null);
  const [importedProduct, setImportedProduct] = useState<ImportedMarketplaceProduct | null>(null);

  // Flatten to show parent > subcategory pairs for the product form
  const flatCategories = useMemo(() => {
    const flat: Array<{ id: number; label: string; slug: string }> = [];
    for (const parent of categories) {
      if (parent.children && parent.children.length > 0) {
        for (const child of parent.children) {
          flat.push({ id: child.id, label: `${parent.name} > ${child.name}`, slug: child.slug });
        }
      } else {
        flat.push({ id: parent.id, label: parent.name, slug: parent.slug });
      }
    }
    return flat;
  }, [categories]);

  const loadAdminData = async (page = currentPage, search = searchQuery) => {
    const [statsResponse, productsResponse, categoriesResponse] = await Promise.all([
      fetchAdminStats(),
      fetchAdminProducts(search, page),
      fetchCategories(),
    ]);

    setStats(statsResponse);
    setProducts(productsResponse.data);
    setPagination(productsResponse.meta);
    setCategories(categoriesResponse);
  };

  const closeModal = () => {
    setShowAddModal(false);
    setEditingProductId(null);
    setForm(EMPTY_FORM);
    setFormError('');
    setIsSubmitting(false);
    setProductLink('');
    setIsImporting(false);
    setImportError('');
    setPreviewProduct(null);
    setImportedProduct(null);
  };

  const resolveCategoryId = (categoryName: string) => {
    const matchingCategory = flatCategories.find((category) => category.label.endsWith(categoryName) || category.label === categoryName);
    return matchingCategory ? String(matchingCategory.id) : '';
  };

  const buildProductPayload = (mode: 'create' | 'update') => ({
    ...(form.categoryId ? { category_id: Number(form.categoryId) } : {}),
    sku: form.sku,
    name: form.name,
    ...(form.description.trim() ? { description: form.description } : {}),
    ...(form.imageUrl.trim() ? { image_url: form.imageUrl.trim() } : mode === 'update' ? {} : { image_url: undefined }),
    ...(importedProduct ? {
      import_source: {
        platform: importedProduct.platform,
        num_iid: importedProduct.num_iid,
        detail_url: importedProduct.detail_url,
        image_url: importedProduct.image_url,
        main_image_url: importedProduct.main_image_url ?? importedProduct.image_url,
        classified_category: importedProduct.classified_category,
        description: importedProduct.description,
        description_html: importedProduct.description_html,
        images: importedProduct.images,
        description_images: importedProduct.description_images,
        variants: importedProduct.variants,
      },
    } : {}),
    moq: Number(form.moq),
    lead_time_min_days: Number(form.leadMin),
    lead_time_max_days: Number(form.leadMax),
    stock_quantity: Number(form.stock),
    is_verified: true,
    is_customizable: false,
    is_active: true,
    base_price: Number(form.basePrice),
    price_tiers: [
      { min_quantity: 1, max_quantity: Math.max(Number(form.moq) - 1, 1), price: Number(form.basePrice) },
      { min_quantity: Number(form.moq), max_quantity: null, price: Math.max(Number(form.basePrice) - 1, 0.5) },
    ],
  });

  const createSkuFromImportedProduct = (product: ImportedMarketplaceProduct) => {
    const prefix = product.platform.slice(0, 2).toUpperCase();
    return `${prefix}-${product.num_iid}`.slice(0, 100);
  };

  const buildImportedDescription = (product: ImportedMarketplaceProduct) => {
    return product.description?.trim() ?? '';
  };

  const parseLeadTime = (leadTime: string) => {
    const matched = leadTime.match(/(\d+)\s*-\s*(\d+)/);
    if (!matched) {
      return { leadMin: '3', leadMax: '5' };
    }

    return {
      leadMin: matched[1],
      leadMax: matched[2],
    };
  };

  const resolveImportedDisplayImage = (product: ImportedMarketplaceProduct) => {
    return product.processed_main_image?.preview_url || product.display_image_url || product.image_url;
  };

  const findCategoryIdByText = (value: string | null | undefined) => {
    if (!value) {
      return '';
    }

    const normalizedValue = value.trim().toLowerCase();

    if (!normalizedValue) {
      return '';
    }

    const exactMatch = flatCategories.find((item) => item.label.toLowerCase() === normalizedValue || item.slug.toLowerCase() === normalizedValue);

    if (exactMatch) {
      return String(exactMatch.id);
    }

    const partialMatch = flatCategories.find((item) => item.label.toLowerCase().includes(normalizedValue) || normalizedValue.includes(item.slug.toLowerCase()));

    return partialMatch ? String(partialMatch.id) : '';
  };

  const detectImportedCategoryId = (product: ImportedMarketplaceProduct) => {
    const classifiedCategoryId = findCategoryIdByText(product.classified_category);

    if (classifiedCategoryId) {
      return classifiedCategoryId;
    }

    const haystack = `${product.title} ${product.description ?? ''}`.toLowerCase();

    const keywordMappings: Array<{ categorySlug: string; keywords: string[] }> = [
      { categorySlug: 'early-years', keywords: ['toy', 'toys', 'plush', 'doll', 'keychain', 'stuffed', '娃娃', '毛绒', '玩具', '公仔'] },
      { categorySlug: 'student-supplies', keywords: ['bag', 'backpack', 'pencil', 'notebook', 'stationery'] },
      { categorySlug: 'decorations', keywords: ['decoration', 'banner', 'balloon', 'gift', 'event'] },
      { categorySlug: 'accessories', keywords: ['mouse', 'keyboard', 'usb', 'charger', 'cable'] },
      { categorySlug: 'tableware', keywords: ['cup', 'plate', 'tableware', 'fork', 'spoon'] },
    ];

    for (const mapping of keywordMappings) {
      if (mapping.keywords.some((keyword) => haystack.includes(keyword))) {
        const category = flatCategories.find((item) => item.slug === mapping.categorySlug);

        if (category) {
          return String(category.id);
        }
      }
    }

    return flatCategories.find((item) => item.slug === 'early-years')?.id?.toString() ?? '';
  };

  useEffect(() => {
    void loadAdminData(1, searchQuery);
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => {
      setCurrentPage(1);
      void fetchAdminProducts(searchQuery, 1).then((response) => {
        setProducts(response.data);
        setPagination(response.meta);
      });
    }, 250);

    return () => clearTimeout(timeout);
  }, [searchQuery]);

  useEffect(() => {
    void fetchAdminProducts(searchQuery, currentPage).then((response) => {
      setProducts(response.data);
      setPagination(response.meta);
    });
  }, [currentPage]);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return <span className="px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">{t('admin.statusActive')}</span>;
      case 'low-stock':
        return <span className="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full">{t('admin.statusLowStock')}</span>;
      default:
        return <span className="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full">{status}</span>;
    }
  };

  const handleCreateProduct = async () => {
    setIsSubmitting(true);
    setFormError('');

    try {
      await createAdminProduct(buildProductPayload('create'));
      closeModal();
      await loadAdminData(currentPage, searchQuery);
    } catch (error: any) {
      setFormError(error?.response?.data?.message ?? 'Unable to create product.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleFetchProductFromLink = async () => {
    if (!productLink.trim()) {
      setImportError('Please paste a Taobao, 1688, or JD product link.');
      return;
    }

    setIsImporting(true);
    setImportError('');
    setPreviewProduct(null);

    try {
      const response = await fetchAdminProductFromLink(productLink.trim());
      setPreviewProduct(response.product);
    } catch (error: any) {
      setImportError(error?.response?.data?.message ?? 'Unable to fetch product details from the provided link.');
    } finally {
      setIsImporting(false);
    }
  };

  const handleInsertImportedProduct = () => {
    if (!previewProduct) {
      return;
    }

    setImportedProduct(previewProduct);
    setForm((currentForm) => ({
      ...currentForm,
      name: previewProduct.title || currentForm.name,
      sku: currentForm.sku || createSkuFromImportedProduct(previewProduct),
      description: buildImportedDescription(previewProduct),
      basePrice: previewProduct.original_price || currentForm.basePrice,
      imageUrl: (previewProduct.main_image_url ?? previewProduct.image_url) || currentForm.imageUrl,
      categoryId: currentForm.categoryId || detectImportedCategoryId(previewProduct),
    }));
    setPreviewProduct(null);
    setProductLink('');
    setImportError('');
  };

  const handleCancelImportedPreview = () => {
    setPreviewProduct(null);
    setImportError('');
    setProductLink('');
  };

  const handleEditProduct = async (product: ProductSummary) => {
    setEditingProductId(product.id);
    setShowAddModal(true);
    setFormError('');
    const leadTime = parseLeadTime(product.lead_time);

    setForm({
      name: product.name,
      sku: product.sku,
      categoryId: resolveCategoryId(product.category),
      description: '',
      moq: String(product.moq),
      stock: String(product.stock_quantity),
      leadMin: leadTime.leadMin,
      leadMax: leadTime.leadMax,
      basePrice: product.price_tier_1?.price ? String(product.price_tier_1.price) : '1.00',
      imageUrl: product.image_source_url || product.image || '',
    });

    try {
      const productDetail = await fetchProduct(product.id);
      const detailLeadTime = parseLeadTime(productDetail.lead_time);

      setForm((currentForm) => ({
        ...currentForm,
        description: productDetail.description ?? currentForm.description,
        leadMin: detailLeadTime.leadMin,
        leadMax: detailLeadTime.leadMax,
        basePrice: productDetail.pricing_tiers[0]?.price ? String(productDetail.pricing_tiers[0].price) : currentForm.basePrice,
        imageUrl: productDetail.image_source_url || currentForm.imageUrl,
        categoryId: currentForm.categoryId || resolveCategoryId(productDetail.category),
      }));
    } catch (error) {
      // Keep the lightweight row data in the form if the detail fetch fails.
    }
  };

  const handleUpdateProduct = async () => {
    if (!editingProductId) {
      return;
    }

    setIsSubmitting(true);
    setFormError('');

    try {
      await updateAdminProduct(editingProductId, buildProductPayload('update'));
      closeModal();
      await loadAdminData(currentPage, searchQuery);
    } catch (error: any) {
      setFormError(error?.response?.data?.message ?? 'Unable to update product.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteProduct = async (id: number) => {
    await deleteAdminProduct(id);
    const nextPage = products.length === 1 && currentPage > 1 ? currentPage - 1 : currentPage;
    setCurrentPage(nextPage);
    await loadAdminData(nextPage, searchQuery);
  };

  const pageNumbers = useMemo(() => {
    return Array.from({ length: pagination.last_page }, (_, index) => index + 1);
  }, [pagination.last_page]);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1600px] mx-auto px-6">
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-3"><Package className="w-8 h-8 text-[#7C3AED]" /><h1 className="text-4xl font-bold text-slate-900">{t('admin.productManagement')}</h1></div>
          <p className="text-slate-600 text-lg">{t('admin.productManagementDesc')}</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-6"><div className="flex items-center justify-between mb-2"><span className="text-slate-600 font-medium">{t('admin.totalProducts')}</span><Package className="w-5 h-5 text-[#4F6BFF]" /></div><div className="text-3xl font-bold text-slate-900">{stats.total_products}</div><div className="text-sm text-emerald-600 font-semibold mt-1">Live catalog</div></div>
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-6"><div className="flex items-center justify-between mb-2"><span className="text-slate-600 font-medium">{t('admin.activeProducts')}</span><TrendingUp className="w-5 h-5 text-emerald-500" /></div><div className="text-3xl font-bold text-slate-900">{stats.active_products}</div><div className="text-sm text-slate-500 font-semibold mt-1">Currently purchasable</div></div>
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-6"><div className="flex items-center justify-between mb-2"><span className="text-slate-600 font-medium">{t('admin.lowStock')}</span><AlertCircle className="w-5 h-5 text-amber-500" /></div><div className="text-3xl font-bold text-slate-900">{stats.low_stock}</div><div className="text-sm text-amber-600 font-semibold mt-1">{t('admin.needsAttention')}</div></div>
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-6"><div className="flex items-center justify-between mb-2"><span className="text-slate-600 font-medium">{t('admin.categories')}</span><Filter className="w-5 h-5 text-[#7C3AED]" /></div><div className="text-3xl font-bold text-slate-900">{stats.categories}</div><div className="text-sm text-slate-500 font-semibold mt-1">{t('admin.inCatalog')}</div></div>
        </div>

        <div className="bg-white rounded-2xl border-2 border-slate-200 p-6 mb-8">
          <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
            <div className="flex-1 w-full md:w-auto relative"><Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" /><input type="text" placeholder={t('admin.searchProducts')} value={searchQuery} onChange={(event) => setSearchQuery(event.target.value)} className="w-full pl-12 pr-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] text-slate-900" /></div>
            <div className="flex gap-3 w-full md:w-auto">
              <button className="flex items-center gap-2 px-5 py-3 border-2 border-slate-200 rounded-xl hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors font-semibold text-slate-700"><Upload className="w-4 h-4" /><span className="hidden md:inline">{t('admin.import')}</span></button>
              <button className="flex items-center gap-2 px-5 py-3 border-2 border-slate-200 rounded-xl hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors font-semibold text-slate-700"><Download className="w-4 h-4" /><span className="hidden md:inline">{t('admin.export')}</span></button>
              <button onClick={() => { setEditingProductId(null); setForm(EMPTY_FORM); setProductLink(''); setImportError(''); setPreviewProduct(null); setImportedProduct(null); setShowAddModal(true); }} className="flex items-center gap-2 px-5 py-3 bg-[#4F6BFF] text-white rounded-xl hover:bg-[#3D56E0] transition-colors font-semibold"><Plus className="w-4 h-4" /><span>{t('admin.addProduct')}</span></button>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-2xl border-2 border-slate-200 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-slate-50 border-b-2 border-slate-200">
                <tr>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">SKU</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">{t('admin.productName')}</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">{t('admin.category')}</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">{t('admin.stock')}</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">MOQ</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">{t('admin.priceRange')}</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">{t('admin.status')}</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">{t('admin.actions')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {products.map((product) => (
                  <tr key={product.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap"><span className="text-sm font-mono font-semibold text-slate-700">{product.sku}</span></td>
                    <td className="px-6 py-4"><div className="text-sm font-semibold text-slate-900">{product.name}</div></td>
                    <td className="px-6 py-4 whitespace-nowrap"><span className="text-sm text-slate-600">{product.category}</span></td>
                    <td className="px-6 py-4 whitespace-nowrap"><span className={`text-sm font-semibold ${product.status === 'low-stock' ? 'text-amber-600' : 'text-slate-900'}`}>{product.stock_quantity.toLocaleString()}</span></td>
                    <td className="px-6 py-4 whitespace-nowrap"><span className="text-sm text-slate-600">{product.moq}</span></td>
                    <td className="px-6 py-4 whitespace-nowrap"><span className="text-sm font-semibold text-slate-900">{product.base_price_range}</span></td>
                    <td className="px-6 py-4 whitespace-nowrap">{getStatusBadge(product.status)}</td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center gap-2">
                        <button onClick={() => void handleEditProduct(product)} className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Edit product">
                          <Pencil className="w-4 h-4" />
                        </button>
                        <button onClick={() => void handleDeleteProduct(product.id)} className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete product">
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {pagination.last_page > 1 && (
            <div className="flex flex-col gap-4 border-t border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
              <p className="text-sm text-slate-500">
                Showing {(pagination.current_page - 1) * pagination.per_page + 1}
                {' '}to{' '}
                {Math.min(pagination.current_page * pagination.per_page, pagination.total)}
                {' '}of {pagination.total} products
              </p>
              <div className="flex flex-wrap items-center gap-2">
                <button
                  onClick={() => setCurrentPage((page) => Math.max(page - 1, 1))}
                  disabled={pagination.current_page === 1}
                  className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <ChevronLeft className="h-4 w-4" />
                  Previous
                </button>
                {pageNumbers.map((pageNumber) => (
                  <button
                    key={pageNumber}
                    onClick={() => setCurrentPage(pageNumber)}
                    className={`h-10 min-w-10 rounded-xl px-3 text-sm font-semibold transition-colors ${
                      pageNumber === pagination.current_page
                        ? 'bg-[#4F6BFF] text-white'
                        : 'border border-slate-200 text-slate-700 hover:border-[#4F6BFF] hover:text-[#4F6BFF]'
                    }`}
                  >
                    {pageNumber}
                  </button>
                ))}
                <button
                  onClick={() => setCurrentPage((page) => Math.min(page + 1, pagination.last_page))}
                  disabled={pagination.current_page === pagination.last_page}
                  className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:text-[#4F6BFF] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Next
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6">
          <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-8">
            <h2 className="text-2xl font-bold text-slate-900 mb-6">{editingProductId ? 'Edit Product' : t('admin.addNewProduct')}</h2>
            <div className="space-y-4">
              {formError && (
                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                  {formError}
                </div>
              )}
              {!editingProductId && (
                <div className="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                  <div className="mb-3">
                    <h3 className="text-sm font-bold text-slate-900">Import from Taobao, 1688, or JD</h3>
                    <p className="text-sm text-slate-600">Paste a product link to fetch the image, name, and price before adding it to your catalog.</p>
                  </div>
                  <div className="flex flex-col gap-3 md:flex-row">
                    <div className="flex-1">
                      <label className="mb-2 block text-sm font-semibold text-slate-700">Product Link</label>
                      <input
                        type="url"
                        value={productLink}
                        onChange={(event) => setProductLink(event.target.value)}
                        placeholder="https://item.taobao.com/item.htm?id=..."
                        className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]"
                      />
                    </div>
                    <div className="md:self-end">
                      <button
                        onClick={() => void handleFetchProductFromLink()}
                        disabled={isImporting}
                        className="px-5 py-3 bg-slate-900 text-white rounded-xl hover:bg-slate-800 transition-colors font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                      >
                        {isImporting ? 'Fetching...' : 'Fetch Item'}
                      </button>
                    </div>
                  </div>
                  {importError && (
                    <div className="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                      {importError}
                    </div>
                  )}
                  {importedProduct && (
                    <div className="mt-4 flex items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                      <div className="h-20 w-20 overflow-hidden rounded-xl bg-white border border-emerald-100 flex-shrink-0">
                        {importedProduct.image_url ? (
                          <img src={resolveImportedDisplayImage(importedProduct) ?? undefined} alt={importedProduct.title} className="h-full w-full object-cover" />
                        ) : (
                          <div className="flex h-full items-center justify-center text-xs text-slate-400">No image</div>
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Imported item</p>
                        <p className="mt-1 text-sm font-semibold text-slate-900 line-clamp-2">{importedProduct.title}</p>
                        <div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">
                          <span>Price: {importedProduct.original_price ?? 'N/A'}</span>
                          <span>Platform: {importedProduct.platform.toUpperCase()}</span>
                          {importedProduct.classified_category && <span>Category: {importedProduct.classified_category}</span>}
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">{t('admin.productName')}</label>
                <input type="text" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">SKU</label>
                  <input type="text" value={form.sku} onChange={(event) => setForm({ ...form, sku: event.target.value })} className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">{t('admin.category')}</label>
                  <select value={form.categoryId} onChange={(event) => setForm({ ...form, categoryId: event.target.value })} className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]">
                    <option value="">Select category</option>
                    {flatCategories.map((cat) => <option key={cat.id} value={cat.id}>{cat.label}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-sm font-semibold text-slate-700 mb-2">Description</label>
                <textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF] resize-none" rows={4} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">MOQ</label>
                  <input type="number" value={form.moq} onChange={(event) => setForm({ ...form, moq: event.target.value })} placeholder="MOQ" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">{t('admin.stock')}</label>
                  <input type="number" value={form.stock} onChange={(event) => setForm({ ...form, stock: event.target.value })} placeholder="Stock" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Lead Time Min (Days)</label>
                  <input type="number" value={form.leadMin} onChange={(event) => setForm({ ...form, leadMin: event.target.value })} placeholder="Lead time min" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Lead Time Max (Days)</label>
                  <input type="number" value={form.leadMax} onChange={(event) => setForm({ ...form, leadMax: event.target.value })} placeholder="Lead time max" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Base Price</label>
                  <input type="number" step="0.01" value={form.basePrice} onChange={(event) => setForm({ ...form, basePrice: event.target.value })} placeholder="Base price" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-slate-700 mb-2">Image URL</label>
                  <input type="text" value={form.imageUrl} onChange={(event) => setForm({ ...form, imageUrl: event.target.value })} placeholder="Image URL" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                </div>
              </div>
              <div className="flex gap-4 justify-end pt-4">
                <button onClick={closeModal} disabled={isSubmitting} className="px-6 py-3 border-2 border-slate-200 text-slate-700 rounded-xl hover:border-slate-300 transition-colors font-semibold disabled:cursor-not-allowed disabled:opacity-60">{t('admin.cancel')}</button>
                <button onClick={() => void (editingProductId ? handleUpdateProduct() : handleCreateProduct())} disabled={isSubmitting} className="px-6 py-3 bg-[#4F6BFF] text-white rounded-xl hover:bg-[#3D56E0] transition-colors font-semibold disabled:cursor-not-allowed disabled:opacity-60">
                  {isSubmitting ? 'Saving...' : editingProductId ? 'Update Product' : t('admin.create')}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {previewProduct && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-6">
          <div className="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white p-8 shadow-2xl">
            <div className="mb-6 flex items-start gap-6">
              <div className="h-40 w-40 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 flex-shrink-0">
                {previewProduct.image_url ? (
                  <img src={resolveImportedDisplayImage(previewProduct) ?? undefined} alt={previewProduct.title} className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-sm text-slate-400">No image</div>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#4F6BFF]">{previewProduct.platform.toUpperCase()} Product</p>
                <h3 className="mt-2 text-2xl font-bold text-slate-900">{previewProduct.title}</h3>
                <div className="mt-4 space-y-2 text-sm text-slate-600">
                  <p><span className="font-semibold text-slate-900">Original Price:</span> {previewProduct.original_price ?? 'N/A'}</p>
                  <p className="break-all"><span className="font-semibold text-slate-900">Detail URL:</span> {previewProduct.detail_url ?? 'N/A'}</p>
                  {previewProduct.classified_category && <p><span className="font-semibold text-slate-900">Detected Category:</span> {previewProduct.classified_category}</p>}
                </div>
              </div>
            </div>
            {previewProduct.description && (
              <div className="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p className="mb-2 text-sm font-semibold text-slate-900">Imported Description</p>
                <div className="max-h-64 overflow-y-auto pr-2">
                  <p className="whitespace-pre-line text-sm text-slate-700">{previewProduct.description}</p>
                </div>
              </div>
            )}
            <div className="flex justify-end gap-3">
              <button onClick={handleCancelImportedPreview} className="px-6 py-3 border-2 border-slate-200 text-slate-700 rounded-xl hover:border-slate-300 transition-colors font-semibold">
                Cancel
              </button>
              <button onClick={handleInsertImportedProduct} className="px-6 py-3 bg-[#4F6BFF] text-white rounded-xl hover:bg-[#3D56E0] transition-colors font-semibold">
                Insert
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
