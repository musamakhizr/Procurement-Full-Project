import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { Trash2, Plus, Minus, Download, Send, ChevronRight, Loader2 } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { ProcurementListItem, submitQuoteRequest } from '../api';

function downloadCSV(items: ProcurementListItem[]) {
  const headers = ['SKU', 'Product Name', 'Category', 'Quantity', 'Unit Price (RMB)', 'Line Total (RMB)'];
  const rows = items.map((item) => [
    item.variant_sku_id || item.sku,
    `"${[item.name, item.variant_label].filter(Boolean).join(' - ').replace(/"/g, '""')}"`,
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
  const { items, isLoading, removeItem, updateQuantity, refreshItems } = useProcurementList();
  const [selectedItemIds, setSelectedItemIds] = useState<number[]>([]);
  const [quoteNotes, setQuoteNotes] = useState('');
  const [isSubmittingQuote, setIsSubmittingQuote] = useState(false);
  const didInitializeSelection = useRef(false);

  useEffect(() => {
    const availableIds = items.map((item) => item.id);

    if (availableIds.length === 0) {
      didInitializeSelection.current = false;
    }

    setSelectedItemIds((currentIds) => {
      const retainedIds = currentIds.filter((id) => availableIds.includes(id));

      if (!didInitializeSelection.current && availableIds.length > 0) {
        didInitializeSelection.current = true;

        return availableIds;
      }

      return retainedIds;
    });
  }, [items]);

  const selectedItems = useMemo(
    () => items.filter((item) => selectedItemIds.includes(item.id)),
    [items, selectedItemIds]
  );
  const selectedSubtotal = selectedItems.reduce((sum, item) => sum + item.line_total, 0);
  const selectedTotalItems = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
  const allSelected = items.length > 0 && selectedItemIds.length === items.length;

  const toggleSelection = (itemId: number) => {
    setSelectedItemIds((currentIds) =>
      currentIds.includes(itemId)
        ? currentIds.filter((id) => id !== itemId)
        : [...currentIds, itemId]
    );
  };

  const toggleSelectAll = () => {
    setSelectedItemIds(allSelected ? [] : items.map((item) => item.id));
  };

  const handleRequestQuote = async () => {
    if (selectedItemIds.length === 0 || isSubmittingQuote) {
      return;
    }

    setIsSubmittingQuote(true);

    try {
      await submitQuoteRequest({
        item_ids: selectedItemIds,
        notes: quoteNotes.trim() || undefined,
      });
      await refreshItems();
      navigate('/my-quote-requests');
    } finally {
      setIsSubmittingQuote(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-24 pb-16">
      <div className="max-w-[1400px] mx-auto px-6">
        <div className="mb-8 flex items-center gap-2 text-sm text-slate-600">
          <Link to="/" className="hover:text-[#4F6BFF]">{t('product.home')}</Link>
          <ChevronRight className="w-4 h-4" />
          <span className="font-medium text-slate-900">{t('procurementList.title')}</span>
        </div>

        <div className="mb-8">
          <h1 className="mb-3 text-4xl font-bold text-slate-900">{t('procurementList.title')}</h1>
          <p className="text-lg text-slate-600">{t('procurementList.subtitle')}</p>
        </div>

        {isLoading ? (
          <div className="rounded-2xl border-2 border-slate-200 bg-white p-12 text-center text-slate-500">Loading your procurement list...</div>
        ) : (
          <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div className="space-y-4 lg:col-span-2">
              {items.length === 0 ? (
                <div className="rounded-2xl border-2 border-slate-200 bg-white p-12 text-center">
                  <div className="mx-auto max-w-md">
                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                      <Plus className="h-8 w-8 text-slate-400" />
                    </div>
                    <h3 className="mb-2 text-xl font-bold text-slate-900">{t('procurementList.emptyTitle')}</h3>
                    <p className="mb-6 text-slate-600">{t('procurementList.emptySubtitle')}</p>
                    <Link to="/marketplace" className="inline-block rounded-xl bg-[#4F6BFF] px-6 py-3 font-semibold text-white transition-colors hover:bg-[#3D56E0]">
                      {t('procurementList.browseCatalog')}
                    </Link>
                  </div>
                </div>
              ) : (
                <>
                  <div className="flex flex-col gap-3 rounded-2xl border-2 border-[#4F6BFF]/20 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <h2 className="font-bold text-slate-900">Quote review selection</h2>
                      <p className="text-sm text-slate-600">Choose the products that should be saved into this quote request.</p>
                    </div>
                    <button
                      onClick={toggleSelectAll}
                      className="rounded-xl border-2 border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:bg-[#EEF2FF] hover:text-[#4F6BFF]"
                    >
                      {allSelected ? 'Clear selection' : 'Select all'}
                    </button>
                  </div>

                  {items.map((item) => (
                    <div
                      key={item.id}
                      className={`rounded-2xl border-2 bg-white p-6 transition-all hover:border-[#4F6BFF]/40 ${
                        selectedItemIds.includes(item.id) ? 'border-[#4F6BFF]/60 shadow-sm' : 'border-slate-200'
                      }`}
                    >
                      <div className="flex gap-6">
                        <input
                          type="checkbox"
                          checked={selectedItemIds.includes(item.id)}
                          onChange={() => toggleSelection(item.id)}
                          className="mt-9 h-6 w-6 flex-shrink-0 rounded-lg border-2 border-slate-300 accent-[#4F6BFF]"
                          aria-label={`Select ${item.name} for quote request`}
                        />

                        <Link to={`/marketplace/product/${item.product_id}`} className="flex-shrink-0">
                          <div className="h-24 w-24 overflow-hidden rounded-xl bg-slate-100">
                            <img src={item.image ?? 'https://placehold.co/200x200?text=Product'} alt={item.name} className="h-full w-full object-cover" />
                          </div>
                        </Link>

                        <div className="flex-1">
                          <div className="mb-2 flex items-start justify-between">
                            <div>
                              <div className="mb-1 text-xs font-semibold text-[#7C3AED]">{item.category}</div>
                              <Link to={`/marketplace/product/${item.product_id}`} className="font-bold text-slate-900 transition-colors hover:text-[#4F6BFF]">
                                {item.name}
                              </Link>
                              <div className="mt-1 text-sm text-slate-500">SKU: {item.variant_sku_id || item.sku}</div>
                              {item.variant_label && <div className="mt-2 text-sm font-semibold text-[#F97316]">{item.variant_label}</div>}
                              {item.variant_options && item.variant_options.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                  {item.variant_options.map((option) => (
                                    <span key={`${item.id}-${option.key}`} className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                      {option.group_name}: {option.value}
                                    </span>
                                  ))}
                                </div>
                              )}
                            </div>
                            <button onClick={() => void removeItem(item.id)} className="rounded-lg p-2 text-red-600 transition-colors hover:bg-red-50">
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </div>

                          <div className="mt-4 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <span className="text-sm font-semibold text-slate-700">{t('product.quantity')}:</span>
                              <div className="flex items-center gap-2">
                                <button onClick={() => void updateQuantity(item.id, Math.max(item.moq, item.quantity - 1))} className="h-8 w-8 rounded-lg border-2 border-slate-200 font-bold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:bg-[#EEF2FF]">
                                  <Minus className="mx-auto h-4 w-4" />
                                </button>
                                <span className="w-12 text-center font-bold text-slate-900">{item.quantity}</span>
                                <button onClick={() => void updateQuantity(item.id, item.quantity + 1)} className="h-8 w-8 rounded-lg border-2 border-slate-200 font-bold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:bg-[#EEF2FF]">
                                  <Plus className="mx-auto h-4 w-4" />
                                </button>
                              </div>
                            </div>

                            <div className="text-right">
                              <div className="text-sm text-slate-600">¥{item.unit_price.toFixed(2)} x {item.quantity}</div>
                              <div className="text-xl font-bold text-slate-900">¥{item.line_total.toFixed(2)}</div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}

                  <Link to="/marketplace" className="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 py-4 font-semibold text-slate-600 transition-colors hover:border-[#4F6BFF] hover:bg-[#EEF2FF] hover:text-[#4F6BFF]">
                    <Plus className="h-5 w-5" />
                    {t('procurementList.addMoreItems')}
                  </Link>
                </>
              )}
            </div>

            {items.length > 0 && (
              <div className="lg:col-span-1">
                <div className="sticky top-24 rounded-2xl border-2 border-slate-200 bg-white p-6">
                  <h3 className="mb-6 text-xl font-bold text-slate-900">{t('procurementList.summary')}</h3>

                  <div className="mb-6 space-y-3 border-b-2 border-slate-200 pb-6">
                    <div className="flex items-center justify-between text-slate-700">
                      <span>{t('procurementList.totalItems')}</span>
                      <span className="font-semibold">{selectedTotalItems} {t('marketplace.units')}</span>
                    </div>
                    <div className="flex items-center justify-between text-slate-700">
                      <span>{t('procurementList.subtotal')}</span>
                      <span className="font-semibold">¥{selectedSubtotal.toFixed(2)}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm text-slate-600">
                      <span>{t('procurementList.estimatedTax')}</span>
                      <span>{t('procurementList.calculatedAtCheckout')}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm text-slate-600">
                      <span>{t('procurementList.shipping')}</span>
                      <span>{t('procurementList.calculatedAtCheckout')}</span>
                    </div>
                  </div>

                  <div className="mb-6 flex items-center justify-between text-xl">
                    <span className="font-bold text-slate-900">{t('product.totalEstimate')}</span>
                    <span className="font-bold text-[#4F6BFF]">¥{selectedSubtotal.toFixed(2)}</span>
                  </div>

                  <div className="mb-6 rounded-2xl bg-slate-50 p-4">
                    <div className="mb-2 flex items-center justify-between text-sm font-semibold text-slate-700">
                      <span>Selected for quote</span>
                      <span>{selectedItems.length} / {items.length}</span>
                    </div>
                    <textarea
                      value={quoteNotes}
                      onChange={(event) => setQuoteNotes(event.target.value)}
                      rows={3}
                      placeholder="Optional notes for the procurement team"
                      className="mt-2 w-full resize-none rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-900 outline-none transition-colors focus:border-[#4F6BFF]"
                    />
                  </div>

                  <div className="space-y-3">
                    <button
                      onClick={() => void handleRequestQuote()}
                      disabled={selectedItemIds.length === 0 || isSubmittingQuote}
                      className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#4F6BFF] py-4 font-bold text-white transition-colors hover:bg-[#3D56E0] disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                      {isSubmittingQuote ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}
                      {isSubmittingQuote ? 'Saving quote request...' : t('procurementList.requestQuote')}
                    </button>
                    <button
                      onClick={() => downloadCSV(items)}
                      className="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 py-4 font-semibold text-slate-700 transition-colors hover:border-[#4F6BFF] hover:bg-[#EEF2FF] hover:text-[#4F6BFF]"
                    >
                      <Download className="h-5 w-5" />
                      {t('procurementList.downloadList')}
                    </button>
                  </div>

                  <div className="mt-6 border-t-2 border-slate-200 pt-6">
                    <p className="text-center text-sm text-slate-600">{t('procurementList.quoteNote')}</p>
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
