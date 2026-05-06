import { CheckCircle, FileText, Link, Package } from 'lucide-react';
import { useState } from 'react';
import { useLanguage } from '../contexts/LanguageContext';

export function SourcingServiceSection() {
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState<'describe' | 'links'>('describe');

  return (
    <div className="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 py-24 px-6">
      <div className="max-w-[1400px] mx-auto">
        <div className="grid lg:grid-cols-2 gap-16 items-start">
          {/* Left Side - Info */}
          <div className="text-white">
            <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-xs font-bold mb-8 border border-white/10 uppercase tracking-wide">
              <Package className="w-3.5 h-3.5" />
              {t('header.sourcing')}
            </div>
            
            <h2 className="text-5xl font-bold mb-6 leading-tight tracking-tight">
              {t('sourcing.mainTitle')}
            </h2>
            
            <p className="text-slate-300 mb-10 text-lg leading-relaxed">
              {t('sourcing.mainSubtitle')}
            </p>

            <div className="grid grid-cols-2 gap-6 mb-10">
              <div className="bg-white/5 backdrop-blur-sm border-2 border-white/10 rounded-xl p-5">
                <CheckCircle className="w-6 h-6 mb-3" />
                <h4 className="font-bold mb-2">{t('sourcing.custom.feature1')}</h4>
                <p className="text-sm text-slate-300">{t('sourcing.custom.feature1Desc')}</p>
              </div>
              <div className="bg-white/5 backdrop-blur-sm border-2 border-white/10 rounded-xl p-5">
                <CheckCircle className="w-6 h-6 mb-3" />
                <h4 className="font-bold mb-2">{t('sourcing.custom.feature2')}</h4>
                <p className="text-sm text-slate-300">{t('sourcing.custom.feature2Desc')}</p>
              </div>
            </div>

            <div className="bg-white/5 backdrop-blur-sm border-2 border-white/10 rounded-2xl p-7">
              <h3 className="font-bold text-lg mb-3 flex items-center gap-2.5">
                <Link className="w-5 h-5" />
                {t('sourcing.links.title')}
              </h3>
              <p className="text-slate-300 leading-relaxed">
                {t('sourcing.links.subtitle')}
              </p>
            </div>
          </div>

          {/* Right Side - Form */}
          <div className="bg-white rounded-2xl shadow-2xl overflow-hidden">
            {/* Tab Header */}
            <div className="flex border-b border-slate-200">
              <button
                onClick={() => setActiveTab('describe')}
                className={`flex-1 flex items-center justify-center gap-2 px-6 py-4 font-semibold transition-all ${
                  activeTab === 'describe'
                    ? 'bg-[#EEF2FF] text-[#4F6BFF] border-b-2 border-[#4F6BFF]'
                    : 'text-slate-600 hover:bg-slate-50'
                }`}
              >
                <FileText className="w-5 h-5" />
                {t('sourcing.customTab')}
              </button>
              <button
                onClick={() => setActiveTab('links')}
                className={`flex-1 flex items-center justify-center gap-2 px-6 py-4 font-semibold transition-all ${
                  activeTab === 'links'
                    ? 'bg-[#F3E8FF] text-[#7C3AED] border-b-2 border-[#7C3AED]'
                    : 'text-slate-600 hover:bg-slate-50'
                }`}
              >
                <Link className="w-5 h-5" />
                {t('sourcing.linksTab')}
              </button>
            </div>

            {/* Form Content */}
            <div className="p-8">
              {activeTab === 'describe' ? (
                <form className="space-y-5">
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                      {t('sourcing.custom.step1')} *
                    </label>
                    <textarea
                      rows={4}
                      placeholder={t('sourcing.custom.step1Desc')}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder:text-gray-400"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        {t('sourcing.custom.feature3')}
                      </label>
                      <input
                        type="text"
                        placeholder="¥5,000"
                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 placeholder:text-gray-400"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        {t('featured.leadTime')}
                      </label>
                      <input
                        type="date"
                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full px-6 py-4 bg-[#4F6BFF] text-white rounded-xl font-semibold hover:bg-[#3F5AF5] transition-all shadow-sm"
                  >
                    {t('sourcing.custom.button')}
                  </button>
                </form>
              ) : (
                <form className="space-y-5">
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                      {t('sourcing.links.step1')} *
                    </label>
                    <textarea
                      rows={5}
                      placeholder={t('sourcing.links.step1Desc')}
                      className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 placeholder:text-gray-400 font-mono text-sm"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        {t('featured.moq')}
                      </label>
                      <input
                        type="text"
                        placeholder="50, 100, 25"
                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 placeholder:text-gray-400"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-gray-700 mb-2">
                        {t('sourcing.custom.feature3')}
                      </label>
                      <input
                        type="text"
                        placeholder="¥10,000"
                        className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 placeholder:text-gray-400"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 transition-all shadow-lg hover:shadow-xl"
                  >
                    {t('sourcing.links.button')}
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
