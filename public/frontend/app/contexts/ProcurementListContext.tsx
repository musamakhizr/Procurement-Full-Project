import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import {
  addProcurementListItem,
  fetchProcurementList,
  ProcurementListItem,
  ProductDetail,
  ProductSummary,
  removeProcurementListItem,
  updateProcurementListItem,
} from '../api';
import { useAuth } from './AuthContext';

interface ProcurementListContextType {
  items: ProcurementListItem[];
  addItem: (item: Pick<ProductSummary, 'id' | 'moq'> | Pick<ProductDetail, 'id' | 'moq'>, quantity?: number) => Promise<void>;
  removeItem: (id: number) => Promise<void>;
  updateQuantity: (id: number, quantity: number) => Promise<void>;
  isInList: (productId: number) => boolean;
  itemCount: number;
  isLoading: boolean;
}

const ProcurementListContext = createContext<ProcurementListContextType | undefined>(undefined);

export function ProcurementListProvider({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading: authLoading } = useAuth();
  const [items, setItems] = useState<ProcurementListItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    const loadItems = async () => {
      if (!isAuthenticated) {
        setItems([]);
        return;
      }

      setIsLoading(true);

      try {
        const response = await fetchProcurementList();
        setItems(response);
      } finally {
        setIsLoading(false);
      }
    };

    if (!authLoading) {
      loadItems();
    }
  }, [authLoading, isAuthenticated]);

  const addItem = async (
    item: Pick<ProductSummary, 'id' | 'moq'> | Pick<ProductDetail, 'id' | 'moq'>,
    quantity?: number
  ) => {
    const savedItem = await addProcurementListItem(item.id, quantity ?? item.moq);

    setItems((prev) => {
      const existingIndex = prev.findIndex((currentItem) => currentItem.product_id === savedItem.product_id);

      if (existingIndex === -1) {
        return [...prev, savedItem];
      }

      return prev.map((currentItem, index) => (index === existingIndex ? savedItem : currentItem));
    });
  };

  const removeItem = async (id: number) => {
    await removeProcurementListItem(id);
    setItems((prev) => prev.filter((item) => item.id !== id));
  };

  const updateQuantity = async (id: number, quantity: number) => {
    const updatedItem = await updateProcurementListItem(id, quantity);
    setItems((prev) => prev.map((item) => (item.id === id ? updatedItem : item)));
  };

  const isInList = (productId: number) => {
    return items.some((item) => item.product_id === productId);
  };

  return (
    <ProcurementListContext.Provider value={{
      items,
      addItem,
      removeItem,
      updateQuantity,
      isInList,
      itemCount: items.length,
      isLoading,
    }}>
      {children}
    </ProcurementListContext.Provider>
  );
}

export function useProcurementList() {
  const context = useContext(ProcurementListContext);
  if (context === undefined) {
    throw new Error('useProcurementList must be used within a ProcurementListProvider');
  }
  return context;
}
