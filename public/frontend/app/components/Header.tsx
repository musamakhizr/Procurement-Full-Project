import { Menu, User, ShoppingCart, Settings, LogOut, X } from 'lucide-react';
import { Link, useNavigate } from 'react-router';
import { useLanguage } from '../contexts/LanguageContext';
import { useAuth } from '../contexts/AuthContext';
import { useProcurementList } from '../contexts/ProcurementListContext';
import { useState } from 'react';

export function Header() {
  const { language, setLanguage, t } = useLanguage();
  const { isAuthenticated, user, signOut } = useAuth();
  const { itemCount } = useProcurementList();
  const navigate = useNavigate();
  const [showUserMenu, setShowUserMenu] = useState(false);
  const [showMobileMenu, setShowMobileMenu] = useState(false);

  const closeMenus = () => {
    setShowUserMenu(false);
    setShowMobileMenu(false);
  };

  const handleSignOut = async () => {
    await signOut();
    closeMenus();
    navigate('/');
  };

  return (
    <header className="bg-white border-b border-slate-200 sticky top-0 z-50">
      <div className="max-w-[1400px] mx-auto px-4 py-3 sm:px-6">
        <div className="flex items-center justify-between gap-3">
          {/* Logo */}
          <div className="flex min-w-0 flex-1 items-center gap-5">
            <Link to="/" className="flex shrink-0 items-center gap-2">
              <div className="w-8 h-8 bg-[#4F6BFF] rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">P</span>
              </div>
              <span className="font-semibold text-slate-900 text-lg">ProcurePro</span>
            </Link>

            {/* Main Navigation */}
            <nav className="hidden min-w-0 flex-1 items-center gap-3 overflow-x-auto whitespace-nowrap [-ms-overflow-style:none] [scrollbar-width:none] lg:flex xl:gap-4 [&::-webkit-scrollbar]:hidden">
              {isAuthenticated ? (
                <>
                  <Link to="/dashboard" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.dashboard')}
                  </Link>
                  <Link to="/marketplace" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.catalog')}
                  </Link>
                  <Link to="/procurement-list" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.myList')}
                  </Link>
                  <Link to="/my-requests" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.requests')}
                  </Link>
                  <Link to="/my-quote-requests" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    Quotes
                  </Link>
                  <Link to="/#ai" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.aiAssistant')}
                  </Link>
                  {user?.is_admin && (
                    <Link to="/admin/requests" className="text-sm text-slate-500 hover:text-[#7C3AED] transition-colors font-medium flex items-center gap-1">
                      <Settings className="w-3.5 h-3.5" />
                      Admin Requests
                    </Link>
                  )}
                  {user?.is_admin && (
                    <Link to="/admin/quote-requests" className="text-sm text-slate-500 hover:text-[#7C3AED] transition-colors font-medium">
                      Admin Quotes
                    </Link>
                  )}
                  {user?.is_admin && (
                    <Link to="/admin/products" className="text-sm text-slate-500 hover:text-[#7C3AED] transition-colors font-medium">
                      Admin Products
                    </Link>
                  )}
                </>
              ) : (
                <>
                  <Link to="/marketplace" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.marketplace')}
                  </Link>
                  <Link to="/#ai" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.aiAssistant')}
                  </Link>
                  <Link to="/sourcing" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.sourcing')}
                  </Link>
                  <Link to="/#how-it-works" className="text-sm text-slate-700 hover:text-[#4F6BFF] transition-colors font-medium">
                    {t('header.howItWorks')}
                  </Link>
                </>
              )}
            </nav>
          </div>

          {/* Right Side Actions */}
          <div className="flex shrink-0 items-center gap-2 sm:gap-3">
            {/* Language Switcher */}
            <div className="relative flex items-center gap-1 bg-slate-100 rounded-lg p-1">
              <button
                onClick={() => setLanguage('en')}
                className={`px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                  language === 'en'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                EN
              </button>
              <button
                onClick={() => setLanguage('zh')}
                className={`px-3 py-1.5 rounded-md text-[0px] font-medium transition-colors ${
                  language === 'zh'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <span className="text-sm">{'\u4E2D\u6587'}</span>
                中文
              </button>
            </div>

            {/* Procurement List - Only show when authenticated */}
            {isAuthenticated && (
              <Link to="/procurement-list" className="relative hidden items-center gap-2 whitespace-nowrap px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:text-[#4F6BFF] md:flex xl:px-4">
                <ShoppingCart className="w-4 h-4" />
                <span className="hidden lg:inline">{t('header.procurementList')}</span>
                <span className="absolute -top-1 -right-1 w-5 h-5 bg-[#4F6BFF] text-white text-xs font-bold rounded-full flex items-center justify-center">{itemCount}</span>
              </Link>
            )}

            {/* User Menu or Sign In */}
            {isAuthenticated ? (
              <div className="relative">
                <button
                  onClick={() => setShowUserMenu(!showUserMenu)}
                  className="hidden items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100 md:flex xl:px-4"
                >
                  <User className="w-4 h-4" />
                  <span>{user?.name || t('header.account')}</span>
                </button>

                {showUserMenu && (
                  <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl border border-slate-200 shadow-lg py-2">
                    <div className="px-4 py-2 border-b border-slate-200">
                      <p className="text-sm font-semibold text-slate-900">{user?.name}</p>
                      <p className="text-xs text-slate-500">{user?.email}</p>
                    </div>
                    <Link to="/dashboard" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      {t('header.dashboard')}
                    </Link>
                    <Link to="/procurement-list" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      {t('header.procurementList')}
                    </Link>
                    <Link to="/my-requests" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      Custom Requests
                    </Link>
                    <Link to="/my-quote-requests" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                      Catalog Quotes
                    </Link>
                    {user?.is_admin && (
                      <Link to="/admin/requests" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                        <Settings className="w-3.5 h-3.5" />
                        Admin Requests
                      </Link>
                    )}
                    {user?.is_admin && (
                      <Link to="/admin/quote-requests" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Admin Quotes
                      </Link>
                    )}
                    {user?.is_admin && (
                      <Link to="/admin/products" onClick={() => setShowUserMenu(false)} className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Admin Products
                      </Link>
                    )}
                    <div className="border-t border-slate-200 my-2" />
                    <button
                      onClick={handleSignOut}
                      className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2"
                    >
                      <LogOut className="w-3.5 h-3.5" />
                      {t('header.signOut')}
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <>
                <Link to="/sign-in" className="hidden items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100 md:flex xl:px-4">
                  {t('header.signIn')}
                </Link>
                <Link to="/get-started" className="whitespace-nowrap rounded-lg bg-[#4F6BFF] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#3F5AF5] xl:px-5">
                  {t('header.getStarted')}
                </Link>
              </>
            )}

            {/* Mobile Menu */}
            <button
              type="button"
              onClick={() => setShowMobileMenu((isOpen) => !isOpen)}
              className="lg:hidden p-2 hover:bg-slate-100 rounded-lg transition-colors"
              aria-label="Toggle navigation menu"
              aria-expanded={showMobileMenu}
            >
              {showMobileMenu ? <X className="w-5 h-5 text-slate-600" /> : <Menu className="w-5 h-5 text-slate-600" />}
            </button>
          </div>
        </div>

        {showMobileMenu && (
          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg lg:hidden">
            <nav className="grid gap-1 text-sm font-semibold text-slate-700">
              {isAuthenticated ? (
                <>
                  <Link to="/dashboard" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.dashboard')}</Link>
                  <Link to="/marketplace" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.catalog')}</Link>
                  <Link to="/procurement-list" onClick={closeMenus} className="flex items-center justify-between rounded-xl px-4 py-3 hover:bg-slate-50">
                    <span>{t('header.myList')}</span>
                    <span className="rounded-full bg-[#4F6BFF] px-2 py-0.5 text-xs text-white">{itemCount}</span>
                  </Link>
                  <Link to="/my-requests" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.requests')}</Link>
                  <Link to="/my-quote-requests" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">Quotes</Link>
                  {user?.is_admin && (
                    <>
                      <div className="my-1 border-t border-slate-100" />
                      <Link to="/admin/requests" onClick={closeMenus} className="flex items-center gap-2 rounded-xl px-4 py-3 text-slate-600 hover:bg-slate-50">
                        <Settings className="w-4 h-4" />
                        Admin Requests
                      </Link>
                      <Link to="/admin/quote-requests" onClick={closeMenus} className="rounded-xl px-4 py-3 text-slate-600 hover:bg-slate-50">Admin Quotes</Link>
                      <Link to="/admin/products" onClick={closeMenus} className="rounded-xl px-4 py-3 text-slate-600 hover:bg-slate-50">Admin Products</Link>
                    </>
                  )}
                  <div className="my-1 border-t border-slate-100" />
                  <button onClick={handleSignOut} className="flex items-center gap-2 rounded-xl px-4 py-3 text-left text-red-600 hover:bg-red-50">
                    <LogOut className="w-4 h-4" />
                    {t('header.signOut')}
                  </button>
                </>
              ) : (
                <>
                  <Link to="/marketplace" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.marketplace')}</Link>
                  <Link to="/sourcing" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.sourcing')}</Link>
                  <Link to="/#how-it-works" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.howItWorks')}</Link>
                  <div className="my-1 border-t border-slate-100" />
                  <Link to="/sign-in" onClick={closeMenus} className="rounded-xl px-4 py-3 hover:bg-slate-50">{t('header.signIn')}</Link>
                  <Link to="/get-started" onClick={closeMenus} className="rounded-xl bg-[#4F6BFF] px-4 py-3 text-center text-white hover:bg-[#3F5AF5]">{t('header.getStarted')}</Link>
                </>
              )}
            </nav>
          </div>
        )}
      </div>
    </header>
  );
}
