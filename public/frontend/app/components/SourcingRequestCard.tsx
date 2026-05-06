import { Package, ArrowRight, CheckCircle, Globe, Boxes } from 'lucide-react';
import { useState } from 'react';

export function SourcingRequestCard() {
  const [showForm, setShowForm] = useState(false);

  const features = [
    { icon: <CheckCircle className="w-4 h-4" />, text: 'Custom & non-catalog items' },
    { icon: <Globe className="w-4 h-4" />, text: 'Cross-border procurement' },
    { icon: <Boxes className="w-4 h-4" />, text: 'Bulk & project-based orders' },
  ];

  return (
    <div className="max-w-[1400px] mx-auto px-6 py-12">
      <div className="bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 rounded-2xl overflow-hidden">
        <div className="grid md:grid-cols-2 gap-8 p-8 md:p-12">
          {/* Left Side - Info */}
          <div className="text-white">
            <div className="inline-flex items-center gap-2 px-3 py-1 bg-white/20 rounded-full text-sm mb-4">
              <Package className="w-4 h-4" />
              Sourcing Service
            </div>
            <h2 className="text-3xl font-bold mb-4">
              Can't find what you need?<br />Let our experts help.
            </h2>
            <p className="text-indigo-100 mb-6 text-lg">
              Submit a sourcing request for custom, complex, or hard-to-find items. Our procurement team will find the best suppliers for you.
            </p>

            <div className="space-y-3 mb-8">
              {features.map((feature, index) => (
                <div key={index} className="flex items-center gap-3 text-indigo-100">
                  <div className="text-white">{feature.icon}</div>
                  <span>{feature.text}</span>
                </div>
              ))}
            </div>

            {!showForm && (
              <button
                onClick={() => setShowForm(true)}
                className="flex items-center gap-2 px-6 py-3 bg-white text-indigo-600 rounded-xl font-semibold hover:bg-indigo-50 transition-colors shadow-lg"
              >
                Submit Sourcing Request
                <ArrowRight className="w-5 h-5" />
              </button>
            )}
          </div>

          {/* Right Side - Form */}
          <div className={`transition-all ${showForm ? 'opacity-100' : 'opacity-0 pointer-events-none md:opacity-100 md:pointer-events-auto'}`}>
            <div className="bg-white rounded-xl p-6 shadow-xl">
              <h3 className="font-semibold text-gray-900 mb-4">Quick Sourcing Request</h3>
              <form className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    What do you need?
                  </label>
                  <textarea
                    rows={3}
                    placeholder="Describe the items, specifications, quantity, and purpose..."
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-900 placeholder:text-gray-400"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Target Budget
                    </label>
                    <input
                      type="text"
                      placeholder="¥5,000"
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-900 placeholder:text-gray-400"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Deadline
                    </label>
                    <input
                      type="date"
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-900"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Contact Email
                  </label>
                  <input
                    type="email"
                    placeholder="sarah.chen@school.edu"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-gray-900 placeholder:text-gray-400"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-semibold hover:from-indigo-700 hover:to-purple-700 transition-all shadow-md"
                >
                  Submit Request
                </button>

                <p className="text-xs text-gray-500 text-center">
                  Our team will respond within 24 hours with options and quotes
                </p>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
