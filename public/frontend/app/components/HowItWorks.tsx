import { Send, Search as SearchIcon, FileCheck, Truck } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';

export function HowItWorks() {
  const { t } = useLanguage();
  
  const steps = [
    {
      number: '1',
      icon: <Send className="w-7 h-7" />,
      titleKey: 'howItWorks.step1.title',
      descKey: 'howItWorks.step1.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
    },
    {
      number: '2',
      icon: <SearchIcon className="w-7 h-7" />,
      titleKey: 'howItWorks.step2.title',
      descKey: 'howItWorks.step2.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
    },
    {
      number: '3',
      icon: <FileCheck className="w-7 h-7" />,
      titleKey: 'howItWorks.step3.title',
      descKey: 'howItWorks.step3.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
    },
    {
      number: '4',
      icon: <Truck className="w-7 h-7" />,
      titleKey: 'howItWorks.step4.title',
      descKey: 'howItWorks.step4.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
    },
  ];

  return (
    <div className="max-w-[1400px] mx-auto px-6 py-24">
      <div className="text-center mb-20">
        <h2 className="text-4xl font-bold text-slate-900 mb-4 tracking-tight">{t('howItWorks.title')}</h2>
        <p className="text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
          {t('howItWorks.subtitle')}
        </p>
      </div>

      <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-10">
        {steps.map((step, index) => (
          <div key={index} className="relative">
            {/* Connector Line */}
            {index < steps.length - 1 && (
              <div className="hidden lg:block absolute top-20 left-full w-full h-0.5 bg-gradient-to-r from-slate-200 to-transparent -z-10" />
            )}

            <div className="text-center">
              {/* Icon Circle */}
              <div className="relative inline-flex mb-8">
                <div className={`p-6 ${step.color} rounded-2xl shadow-sm`}>
                  {step.icon}
                </div>
                <div className="absolute -top-2 -right-2 w-9 h-9 bg-white border-2 border-slate-200 rounded-full flex items-center justify-center shadow-md">
                  <span className="text-sm font-bold text-slate-900">{step.number}</span>
                </div>
              </div>

              {/* Content */}
              <h3 className="text-lg font-bold text-slate-900 mb-3 leading-tight">
                {t(step.titleKey)}
              </h3>
              <p className="text-slate-600 leading-relaxed text-[15px]">
                {t(step.descKey)}
              </p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}