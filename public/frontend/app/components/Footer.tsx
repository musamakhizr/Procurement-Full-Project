import { Link } from 'react-router';
import { useLanguage } from '../contexts/LanguageContext';

export function Footer() {
  const { t } = useLanguage();

  return (
    <footer className="bg-white border-t border-slate-200 mt-12">
      <div className="max-w-[1400px] mx-auto px-6 py-12">
        <div className="grid md:grid-cols-4 gap-8">
          <div>
            <Link to="/" className="flex items-center gap-2 mb-4">
              <div className="w-8 h-8 bg-[#4F6BFF] rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">P</span>
              </div>
              <span className="font-semibold text-slate-900 text-lg">ProcurePro</span>
            </Link>
            <p className="text-sm text-slate-600">
              {t('footer.description')}
            </p>
          </div>

          <div>
            <h4 className="font-semibold text-slate-900 mb-4">{t('footer.platform')}</h4>
            <ul className="space-y-2 text-sm text-slate-600">
              <li><Link to="/marketplace" className="hover:text-[#4F6BFF]">{t('header.marketplace')}</Link></li>
              <li><Link to="/#ai" className="hover:text-[#4F6BFF]">{t('header.aiAssistant')}</Link></li>
              <li><Link to="/sourcing" className="hover:text-[#4F6BFF]">{t('header.sourcing')}</Link></li>
              <li><Link to="/#suppliers" className="hover:text-[#4F6BFF]">{t('footer.supplierNetwork')}</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="font-semibold text-slate-900 mb-4">{t('footer.support')}</h4>
            <ul className="space-y-2 text-sm text-slate-600">
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.helpCenter')}</a></li>
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.contactUs')}</a></li>
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.buyerProtection')}</a></li>
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.termsOfService')}</a></li>
            </ul>
          </div>

          <div>
            <h4 className="font-semibold text-slate-900 mb-4">{t('footer.company')}</h4>
            <ul className="space-y-2 text-sm text-slate-600">
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.aboutUs')}</a></li>
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.careers')}</a></li>
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.blog')}</a></li>
              <li><a href="#" className="hover:text-[#4F6BFF]">{t('footer.pressKit')}</a></li>
            </ul>
          </div>
        </div>

        <div className="border-t border-slate-200 mt-8 pt-8 text-center text-sm text-slate-500">
          <p>{t('footer.copyright')}</p>
        </div>
      </div>
    </footer>
  );
}