import { Shield, DollarSign, Users, Workflow, Clock, Lock } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';

export function WhyChooseUs() {
  const { t } = useLanguage();
  
  const features = [
    {
      icon: <Shield className="w-7 h-7" />,
      titleKey: 'whyUs.verified.title',
      descKey: 'whyUs.verified.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
    },
    {
      icon: <DollarSign className="w-7 h-7" />,
      titleKey: 'whyUs.pricing.title',
      descKey: 'whyUs.pricing.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
    },
    {
      icon: <Users className="w-7 h-7" />,
      titleKey: 'whyUs.team.title',
      descKey: 'whyUs.team.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
    },
    {
      icon: <Workflow className="w-7 h-7" />,
      titleKey: 'whyUs.approval.title',
      descKey: 'whyUs.approval.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
    },
    {
      icon: <Clock className="w-7 h-7" />,
      titleKey: 'whyUs.tracking.title',
      descKey: 'whyUs.tracking.desc',
      color: 'bg-[#EEF2FF] text-[#4F6BFF]',
    },
    {
      icon: <Lock className="w-7 h-7" />,
      titleKey: 'whyUs.secure.title',
      descKey: 'whyUs.secure.desc',
      color: 'bg-[#F3E8FF] text-[#7C3AED]',
    },
  ];

  return (
    <div className="bg-slate-50 py-24 px-6">
      <div className="max-w-[1400px] mx-auto">
        <div className="text-center mb-20">
          <h2 className="text-4xl font-bold text-slate-900 mb-4 tracking-tight">{t('whyUs.title')}</h2>
          <p className="text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
            {t('whyUs.subtitle')}
          </p>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          {features.map((feature, index) => (
            <div
              key={index}
              className="bg-white p-8 rounded-2xl shadow-sm hover:shadow-lg transition-all hover:-translate-y-1 border-2 border-slate-100 hover:border-slate-200"
            >
              <div className={`inline-flex p-4 ${feature.color} rounded-xl mb-6 shadow-sm`}>
                {feature.icon}
              </div>
              
              <h3 className="text-lg font-bold text-slate-900 mb-3 leading-tight">
                {t(feature.titleKey)}
              </h3>
              
              <p className="text-slate-600 leading-relaxed text-[15px]">
                {t(feature.descKey)}
              </p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}