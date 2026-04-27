import axios from 'axios';

const TOKEN_KEY = 'auth_token';

export interface User {
  id: number;
  name: string;
  email: string;
  organization_name: string | null;
  organization_type: string;
  role: string;
  is_admin: boolean;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  children: Category[];
}

export interface ProductSummary {
  id: number;
  sku: string;
  name: string;
  category: string;
  category_slug: string;
  subcategory_slug: string | null;
  image: string | null;
  moq: number;
  lead_time: string;
  verified: boolean;
  customizable: boolean;
  stock_quantity: number;
  status: string;
  last_updated: string | null;
  base_price_range: string;
  price_tier_1: { range: string; price: number } | null;
  price_tier_2: { range: string; price: number } | null;
}

export interface ProductDetail {
  id: number;
  sku: string;
  name: string;
  category: string;
  category_slug: string;
  description: string;
  images: string[];
  moq: number;
  lead_time: string;
  in_stock: boolean;
  stock_quantity: number;
  is_verified: boolean;
  is_customizable: boolean;
  pricing_tiers: Array<{
    id: number;
    min_qty: number;
    max_qty: number | null;
    price: number;
    label: string;
  }>;
  specifications: Array<{ label: string; value: string }>;
}

export interface ProcurementListItem {
  id: number;
  product_id: number;
  name: string;
  sku: string;
  category: string;
  quantity: number;
  unit_price: number;
  image: string | null;
  moq: number;
  line_total: number;
}

export interface DashboardData {
  summary: {
    pending_requests: number;
    active_orders: number;
    month_spend: number;
    savings_percentage: number;
  };
  action_items: Array<{
    id: string;
    title: string;
    status: string;
    status_text: string;
    next_step: string;
    action: string;
    urgent: boolean;
    date: string | null;
  }>;
  recent_activity: Array<{
    id: number;
    action: string;
    time: string;
  }>;
}

export interface SourcingRequestPayload {
  type: 'custom' | 'links';
  title: string;
  details: string;
  quantity: number;
  budget_text?: string;
  delivery_date?: string;
  notes?: string;
  links?: string[];
}

export interface AdminProductPayload {
  category_id: number;
  sku: string;
  name: string;
  description: string;
  image_url?: string;
  moq: number;
  lead_time_min_days: number;
  lead_time_max_days: number;
  stock_quantity: number;
  is_verified: boolean;
  is_customizable: boolean;
  is_active: boolean;
  base_price: number;
  price_tiers: Array<{
    min_quantity: number;
    max_quantity: number | null;
    price: number;
  }>;
}

interface AuthResponse {
  token: string;
  user: User;
}

interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api',
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export function persistToken(token: string) {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY);
}

export async function signIn(payload: { email: string; password: string }) {
  const { data } = await api.post<AuthResponse>('/auth/login', payload);
  return data;
}

export async function signUp(payload: {
  name: string;
  email: string;
  password: string;
  organization_name: string;
  organization_type: string;
}) {
  const { data } = await api.post<AuthResponse>('/auth/register', payload);
  return data;
}

export async function fetchCurrentUser() {
  const { data } = await api.get<User>('/auth/me');
  return data;
}

export async function signOutRequest() {
  await api.post('/auth/logout');
}

export async function fetchCategories() {
  const { data } = await api.get<Category[]>('/categories');
  return data;
}

export async function fetchProducts(params: Record<string, string | number | boolean | undefined>) {
  const { data } = await api.get<PaginatedResponse<ProductSummary>>('/products', { params });
  return data;
}

export async function fetchProduct(id: string | number) {
  const { data } = await api.get<ProductDetail>(`/products/${id}`);
  return data;
}

export async function fetchDashboard() {
  const { data } = await api.get<DashboardData>('/dashboard');
  return data;
}

export async function fetchProcurementList() {
  const { data } = await api.get<ProcurementListItem[]>('/procurement-list');
  return data;
}

export async function addProcurementListItem(productId: number, quantity?: number) {
  const { data } = await api.post<ProcurementListItem>('/procurement-list', {
    product_id: productId,
    quantity,
  });
  return data;
}

export async function updateProcurementListItem(id: number, quantity: number) {
  const { data } = await api.patch<ProcurementListItem>(`/procurement-list/${id}`, { quantity });
  return data;
}

export async function removeProcurementListItem(id: number) {
  await api.delete(`/procurement-list/${id}`);
}

export interface SourcingRequest {
  id: number;
  reference: string;
  title: string;
  type: 'custom' | 'links';
  status: string;
  status_label: string;
  quantity: number;
  delivery_date: string | null;
  created_at: string;
  links: string[];
}

export async function submitSourcingRequest(payload: SourcingRequestPayload) {
  const { data } = await api.post('/sourcing-requests', payload);
  return data;
}

export async function fetchSourcingRequests() {
  const { data } = await api.get<{ data: SourcingRequest[] }>('/sourcing-requests');
  return data.data;
}

export async function fetchAdminStats() {
  const { data } = await api.get<{
    total_products: number;
    active_products: number;
    low_stock: number;
    categories: number;
  }>('/admin/product-stats');
  return data;
}

export async function fetchAdminProducts(search = '') {
  const { data } = await api.get<PaginatedResponse<ProductSummary>>('/admin/products', {
    params: { search },
  });
  return data;
}

export async function createAdminProduct(payload: AdminProductPayload) {
  const { data } = await api.post<ProductDetail>('/admin/products', payload);
  return data;
}

export async function deleteAdminProduct(id: number) {
  await api.delete(`/admin/products/${id}`);
}
