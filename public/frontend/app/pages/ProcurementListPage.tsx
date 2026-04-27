import { Link, useNavigate } from 'react-router';
import { Trash2, Plus, Minus, Download, Send, ChevronRight } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { ProcurementListItem } from '../api';

function downloadCSV(items: ProcurementListItem[]) {
  const headers = ['SKU', 'Product Name', 'Category', 'Quantity', 'Unit Price (USD)', 'Line Total (USD)'];
  const rows = items.map((item) => [
    item.sku,
    `"${item.name.replace(/"/g, '""')}"`,
    item.category,
    item.quantity,
    item.unit_price.toFixed(2),
    item.line_total.toFixed(2),
  ]);
  const grandTotal = items.reduce((sum, item) => sum + item.line_total, 0);
  rows.push(['', '"Grand Total"', '', '', '', grandTotal.toFixed(2)]);

  const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `procurement-list-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

export function ProcurementListPage() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const { items, isLoading, removeItem, updateQuantity } = useProcurementList();

  const getTotal = () => items.reduce((sum, item) => sum + item.line_total, 0);
  const getTotalItems = () => items.reduce((sum, item) => sum + item.quantity, 0);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1400px] mx-auto px-6">
        <div className="flex items-center gap-2 text-sm text-slate-600 mb-8">
          <Link to="/" className="hover:text-[#4F6BFF]">{t('product.home')}</Link>
          <ChevronRight className="w-4 h-4" />
          <span className="text-slate-900 font-medium">{t('procurementList.title')}</span>
        </div>

        <div className="mb-8">
          <h1 className="text-4xl font-bold text-slate-900 mb-3">{t('procurementList.title')}</h1>
          <p className="text-slate-600 text-lg">{t('procurementList.subtitle')}</p>
        </div>

        {isLoading ? (
          <div className="bg-white rounded-2xl border-2 border-slate-200 p-12 text-center text-slate-500">Loading your procurement list...</div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div className="lg:col-span-2 space-y-4">
              {items.length === 0 ? (
                <div className="bg-white rounded-2xl border-2 border-slate-200 p-12 text-center">
                  <div className="max-w-md mx-auto">
                    <div className="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                      <Plus className="w-8 h-8 text-slate-400" />
                    </div>
                    <h3 className="text-xl font-bold text-slate-900 mb-2">{t('procurementList.emptyTitle')}</h3>
                    <p className="text-slate-600 mb-6">{t('procurementList.emptySubtitle')}</p>
                    <Link to="/marketplace" className="inline-block px-6 py-3 bg-[#4F6BFF] text-white font-semibold rounded-xl hover:bg-[#3D56E0] transition-colors">
                      {t('procurementList.browseCatalog')}
                    </Link>
                  </div>
                </div>
              ) : (
                <>
                  {items.map((item) => (
                    <div key={item.id} className="bg-white rounded-2xl border-2 border-slate-200 p-6 hover:border-[#4F6BFF]/40 transition-all">
                      <div className="flex gap-6">
                        <Link to={`/marketplace/product/${item.product_id}`} className="flex-shrink-0">
                          <div className="w-24 h-24 bg-slate-100 rounded-xl overflow-hidden">
                            <img src={item.image ?? 'https://placehold.co/200x200?text=Product'} alt={item.name} className="w-full h-full object-cover" />
                          </div>
                        </Link>

                        <div className="flex-1">
                          <div className="flex items-start justify-between mb-2">
                            <div>
                              <div className="text-xs text-[#7C3AED] font-semibold mb-1">{item.category}</div>
                              <Link to={`/marketplace/product/${item.product_id}`} className="font-bold text-slate-900 hover:text-[#4F6BFF] transition-colors">
                                {item.name}
                              </Link>
                              <div className="text-sm text-slate-500 mt-1">SKU: {item.sku}</div>
                            </div>
                            <button onClick={() => void removeItem(item.id)} className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>

                          <div className="flex items-center justify-between mt-4">
                            <div className="flex items-center gap-3">
                              <span className="text-sm font-semibold text-slate-700">{t('product.quantity')}:</span>
                              <div className="flex items-center gap-2">
                                <button onClick={() => void updateQuantity(item.id, Math.max(item.moq, item.quantity - 1))} className="w-8 h-8 border-2 border-slate-200 rounded-lg hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors font-bold text-slate-700">
                                  <Minus className="w-4 h-4 mx-auto" />
                                </button>
                                <span className="w-12 text-center font-bold text-slate-900">{item.quantity}</span>
                                <button onClick={() => void updateQuantity(item.id, item.quantity + 1)} className="w-8 h-8 border-2 border-slate-200 rounded-lg hover:border-[#4F6BFF] hover:bg-[#EEF2FF] transition-colors font-bold text-slate-700">
                                  <Plus className="w-4 h-4 mx-auto" />
                                </button>
                              </div>
                            </div>

                            <div className="text-right">
                              <div className="text-sm text-slate-600">${item.unit_price.toFixed(2)} � {item.quantity}</div>
                              <div className="text-xl font-bold text-slate-900">${item.line_total.toFixed(2)}</div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}

                  <Link to="/marketplace" className="flex items-center justify-center gap-2 py-4 border-2 border-dashed border-slate-300 rounded-2xl text-slate-600 hover:border-[#4F6BFF] hover:bg-[#EEF2FF] hover:text-[#4F6BFF] transition-colors font-semibold">
                    <Plus className="w-5 h-5" />
                    {t('procurementList.addMoreItems')}
                  </Link>
                </>
              )}
            </div>

            {items.length > 0 && (
              <div className="lg:col-span-1">
                <div className="bg-white rounded-2xl border-2 border-slate-200 p-6 sticky top-24">
                  <h3 className="text-xl font-bold text-slate-900 mb-6">{t('procurementList.summary')}</h3>

                  <div className="space-y-3 mb-6 pb-6 border-b-2 border-slate-200">
                    <div className="flex items-center justify-between text-slate-700">
                      <span>{t('procurementList.totalItems')}</span>
                      <span className="font-semibold">{getTotalItems()} {t('marketplace.units')}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-700">
                      <span>{t('procurementList.subtotal')}</span>
                      <span className="font-semibold">${getTotal().toFixed(2)}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-600 text-sm">
                      <span>{t('procurementList.estimatedTax')}</span>
                      <span>{t('procurementList.calculatedAtCheckout')}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-600 text-sm">
                      <span>{t('procurementList.shipping')}</span>
                      <span>{t('procurementList.calculatedAtCheckout')}</span>
                    </div>
                  </div>

                  <div className="flex items-center justify-between mb-6 text-xl">
                    <span className="font-bold text-slate-900">{t('product.totalEstimate')}</span>
                    <span className="font-bold text-[#4F6BFF]">${getTotal().toFixed(2)}</span>
                  </div>

                  <div className="space-y-3">
                    <button onClick={() => navigate('/sourcing')} className="w-full py-4 bg-[#4F6BFF] text-white font-bold rounded-xl hover:bg-[#3D56E0] transition-colors flex items-center justify-center gap-2">
                      <Send className="w-5 h-5" />
                      {t('procurementList.requestQuote')}
                    </button>
                    <button
                      onClick={() => downloadCSV(items)}
                      className="w-full py-4 border-2 border-slate-200 text-slate-700 font-semibold rounded-xl hover:border-[#4F6BFF] hover:bg-[#EEF2FF] hover:text-[#4F6BFF] transition-colors flex items-center justify-center gap-2"
                    >
                      <Download className="w-5 h-5" />
                      {t('procurementList.downloadList')}
                    </button>
                  </div>

                  <div className="mt-6 pt-6 border-t-2 border-slate-200">
                    <p className="text-sm text-slate-600 text-center">{t('procurementList.quoteNote')}</p>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
