import { Clock, Package, CheckCircle, TrendingUp } from 'lucide-react';

interface RecentItem {
  id: string;
  title: string;
  date: string;
  type: 'order' | 'ai-recommendation' | 'sourcing';
  status: string;
  amount?: number;
}

const recentItems: RecentItem[] = [
  {
    id: '1',
    title: 'Spring Festival Event Supplies',
    date: '2 days ago',
    type: 'order',
    status: 'Delivered',
    amount: 3245,
  },
  {
    id: '2',
    title: 'AI Recommendation: Science Lab Equipment',
    date: '1 week ago',
    type: 'ai-recommendation',
    status: 'Saved',
  },
  {
    id: '3',
    title: 'Custom Branded Merchandise',
    date: '2 weeks ago',
    type: 'sourcing',
    status: 'In Progress',
    amount: 8500,
  },
  {
    id: '4',
    title: 'Office Supplies Restock',
    date: '3 weeks ago',
    type: 'order',
    status: 'Delivered',
    amount: 1876,
  },
];

const popularTemplates = [
  { id: '1', name: 'Back to School Kit', uses: 234, icon: '🎒' },
  { id: '2', name: 'Quarterly Office Restock', uses: 189, icon: '🏢' },
  { id: '3', name: 'Workshop Starter Pack', uses: 156, icon: '🛠️' },
  { id: '4', name: 'Event Essentials', uses: 142, icon: '🎉' },
];

export function RecentProcurement() {
  return (
    <div className="max-w-[1400px] mx-auto px-6 py-20">
      <div className="grid md:grid-cols-3 gap-6">
        {/* Recent Activity */}
        <div className="md:col-span-2 bg-white rounded-2xl border-2 border-slate-200 p-7 shadow-sm">
          <div className="flex items-center justify-between mb-7">
            <div className="flex items-center gap-2.5">
              <Clock className="w-5 h-5 text-slate-600" />
              <h3 className="font-bold text-slate-900 text-lg">Recent Activity</h3>
            </div>
            <button className="text-sm text-[#4F6BFF] hover:text-[#3F5AF5] font-bold">
              View All
            </button>
          </div>

          <div className="space-y-4">
            {recentItems.map((item) => (
              <div
                key={item.id}
                className="flex items-center gap-4 p-4 border-2 border-slate-200 rounded-xl hover:border-[#4F6BFF]/30 hover:bg-slate-50 transition-all cursor-pointer"
              >
                <div className={`p-3.5 rounded-xl ${
                  item.type === 'order' ? 'bg-[#EEF2FF]' :
                  item.type === 'ai-recommendation' ? 'bg-[#F3E8FF]' :
                  'bg-[#EEF2FF]'
                } shadow-sm`}>
                  {item.type === 'order' && <Package className="w-5 h-5 text-[#4F6BFF]" />}
                  {item.type === 'ai-recommendation' && <TrendingUp className="w-5 h-5 text-[#7C3AED]" />}
                  {item.type === 'sourcing' && <CheckCircle className="w-5 h-5 text-[#4F6BFF]" />}
                </div>

                <div className="flex-1">
                  <h4 className="font-bold text-slate-900 mb-1 text-[15px]">{item.title}</h4>
                  <div className="flex items-center gap-3 text-sm text-slate-500 font-medium">
                    <span>{item.date}</span>
                    <span>•</span>
                    <span className={`${
                      item.status === 'Delivered' ? 'text-emerald-600 font-semibold' :
                      item.status === 'In Progress' ? 'text-[#4F6BFF] font-semibold' :
                      'text-slate-600 font-semibold'
                    }`}>
                      {item.status}
                    </span>
                  </div>
                </div>

                {item.amount && (
                  <div className="text-right">
                    <p className="font-bold text-slate-900 text-lg">${item.amount.toLocaleString()}</p>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Popular Templates */}
        <div className="bg-white rounded-2xl border-2 border-slate-200 p-7 shadow-sm">
          <h3 className="font-bold text-slate-900 mb-1.5 text-lg">Popular Templates</h3>
          <p className="text-sm text-slate-600 mb-7">Quick start with proven procurement lists</p>

          <div className="space-y-3">
            {popularTemplates.map((template) => (
              <button
                key={template.id}
                className="w-full flex items-center gap-3.5 p-4 bg-white rounded-xl hover:shadow-md transition-all text-left border-2 border-slate-200 hover:border-[#4F6BFF]/30"
              >
                <div className="text-3xl">{template.icon}</div>
                <div className="flex-1">
                  <p className="font-bold text-slate-900 text-sm">{template.name}</p>
                  <p className="text-xs text-slate-500 font-medium">{template.uses} uses</p>
                </div>
              </button>
            ))}
          </div>

          <button className="w-full mt-5 px-5 py-3 bg-[#4F6BFF] text-white rounded-xl font-bold hover:bg-[#3F5AF5] transition-colors text-sm shadow-sm">
            Browse All Templates
          </button>
        </div>
      </div>
    </div>
  );
}