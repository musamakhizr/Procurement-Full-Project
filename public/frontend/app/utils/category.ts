import type { ProductDetail, ProductSummary } from '../api';

type ApiCategory = ProductSummary['cat_from_api'] | ProductDetail['cat_from_api'];

export function formatApiCategoryPath(category: ApiCategory, language: 'en' | 'zh', fallback?: string | null) {
  const keys = language === 'zh'
    ? ['L1_ZH', 'L2_ZH', 'L3_ZH'] as const
    : ['L1_EN', 'L2_EN', 'L3_EN'] as const;

  const path = keys
    .map((key) => category?.[key])
    .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
    .join(' -> ');

  return path || fallback || '';
}

export function formatApiCategoryL1(category: ApiCategory, language: 'en' | 'zh', fallback?: string | null) {
  const key = language === 'zh' ? 'L1_ZH' : 'L1_EN';
  const value = category?.[key];

  return typeof value === 'string' && value.trim() !== '' ? value : fallback || '';
}
