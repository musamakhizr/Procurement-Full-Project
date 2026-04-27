import { useEffect, useMemo, useState } from 'react';
import { Search, Plus, Trash2, Package, TrendingUp, AlertCircle, Filter, Download, Upload } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { createAdminProduct, deleteAdminProduct, fetchAdminProducts, fetchAdminStats, fetchCategories } from '../api';

export function AdminProductsPage() {
  const { t } = useLanguage();
  const [searchQuery, setSearchQuery] = useState('');
  const [showAddModal, setShowAddModal] = useState(false);
  const [stats, setStats] = useState({ total_products: 0, active_products: 0, low_stock: 0, categories: 0 });
  const [products, setProducts] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [form, setForm] = useState({
    name: '', sku: '', categoryId: '', description: '', moq: '1', stock: '0', leadMin: '3', leadMax: '5', basePrice: '1.00', imageUrl: '',
  });

  // Flatten to show parent > subcategory pairs for the product form
  const flatCategories = useMemo(() => {
    const flat: Array<{ id: number; label: string }> = [];
    for (const parent of categories) {
      if (parent.children && parent.children.length > 0) {
        for (const child of parent.children) {
          flat.push({ id: child.id, label: `${parent.name} › ${child.name}` });
        }
      } else {
        flat.push({ id: parent.id, label: parent.name });
      }
    }
    return flat;
  }, [categories]);

  const loadAdminData = async () => {
    const [statsResponse, productsResponse, categoriesResponse] = await Promise.all([
      fetchAdminStats(),
      fetchAdminProducts(searchQuery),
      fetchCategories(),
    ]);

    setStats(statsResponse);
    setProducts(productsResponse.data);
    setCategories(categoriesResponse);
  };

  useEffect(() => {
    void loadAdminData();
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => {
      void fetchAdminProducts(searchQuery).then((response) => setProducts(response.data));
    }, 250);

    return () => clearTimeout(timeout);
  }, [searchQuery]);

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
    await createAdminProduct({
      category_id: Number(form.categoryId),
      sku: form.sku,
      name: form.name,
      description: form.description,
      image_url: form.imageUrl || undefined,
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

    setShowAddModal(false);
    setForm({ name: '', sku: '', categoryId: '', description: '', moq: '1', stock: '0', leadMin: '3', leadMax: '5', basePrice: '1.00', imageUrl: '' });
    await loadAdminData();
  };

  const handleDeleteProduct = async (id: number) => {
    await deleteAdminProduct(id);
    await loadAdminData();
  };

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
              <button onClick={() => setShowAddModal(true)} className="flex items-center gap-2 px-5 py-3 bg-[#4F6BFF] text-white rounded-xl hover:bg-[#3D56E0] transition-colors font-semibold"><Plus className="w-4 h-4" /><span>{t('admin.addProduct')}</span></button>
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
                    <td className="px-6 py-4 whitespace-nowrap"><button onClick={() => void handleDeleteProduct(product.id)} className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"><Trash2 className="w-4 h-4" /></button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-6">
          <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-8">
            <h2 className="text-2xl font-bold text-slate-900 mb-6">{t('admin.addNewProduct')}</h2>
            <div className="space-y-4">
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
                <input type="number" value={form.moq} onChange={(event) => setForm({ ...form, moq: event.target.value })} placeholder="MOQ" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                <input type="number" value={form.stock} onChange={(event) => setForm({ ...form, stock: event.target.value })} placeholder="Stock" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <input type="number" value={form.leadMin} onChange={(event) => setForm({ ...form, leadMin: event.target.value })} placeholder="Lead time min" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                <input type="number" value={form.leadMax} onChange={(event) => setForm({ ...form, leadMax: event.target.value })} placeholder="Lead time max" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <input type="number" step="0.01" value={form.basePrice} onChange={(event) => setForm({ ...form, basePrice: event.target.value })} placeholder="Base price" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
                <input type="text" value={form.imageUrl} onChange={(event) => setForm({ ...form, imageUrl: event.target.value })} placeholder="Image URL" className="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:outline-none focus:border-[#4F6BFF]" />
              </div>
              <div className="flex gap-4 justify-end pt-4">
                <button onClick={() => setShowAddModal(false)} className="px-6 py-3 border-2 border-slate-200 text-slate-700 rounded-xl hover:border-slate-300 transition-colors font-semibold">{t('admin.cancel')}</button>
                <button onClick={() => void handleCreateProduct()} className="px-6 py-3 bg-[#4F6BFF] text-white rounded-xl hover:bg-[#3D56E0] transition-colors font-semibold">{t('admin.create')}</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
