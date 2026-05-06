import { Sparkles, CheckCircle, TrendingUp, ShoppingCart, X, Info } from 'lucide-react';

interface RecommendedProduct {
  id: string;
  name: string;
  category: string;
  price: number;
  quantity: number;
  supplier: string;
  image: string;
  reason: string;
  inStock: boolean;
  moq: number;
  leadTime: string;
}

interface AIRecommendationPanelProps {
  query: string;
  recommendations: RecommendedProduct[];
  onClose: () => void;
  onAddToCart: (productId: string) => void;
}

export function AIRecommendationPanel({ query, recommendations, onClose, onAddToCart }: AIRecommendationPanelProps) {
  const totalCost = recommendations.reduce((sum, p) => sum + p.price * p.quantity, 0);

  return (
    <div className="bg-white rounded-2xl shadow-xl border border-blue-100 overflow-hidden">
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 text-white">
        <div className="flex items-start justify-between">
          <div className="flex items-start gap-3">
            <div className="p-2 bg-white/20 rounded-lg mt-0.5">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-semibold text-lg mb-1">AI Procurement Recommendation</h3>
              <p className="text-blue-100 text-sm">Based on: "{query}"</p>
            </div>
          </div>
          <button onClick={onClose} className="p-1 hover:bg-white/20 rounded-lg transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Summary */}
      <div className="px-6 py-4 bg-blue-50 border-b border-blue-100">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-6">
            <div>
              <p className="text-xs text-gray-500 mb-1">Recommended Items</p>
              <p className="text-xl font-semibold text-gray-900">{recommendations.length}</p>
            </div>
            <div>
              <p className="text-xs text-gray-500 mb-1">Total Estimate</p>
              <p className="text-xl font-semibold text-gray-900">¥{totalCost.toLocaleString()}</p>
            </div>
            <div>
              <p className="text-xs text-gray-500 mb-1">Budget Match</p>
              <div className="flex items-center gap-1">
                <TrendingUp className="w-4 h-4 text-green-600" />
                <p className="text-xl font-semibold text-green-600">95%</p>
              </div>
            </div>
          </div>
          <button className="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg font-medium hover:from-blue-700 hover:to-indigo-700 transition-all shadow-md">
            Add All to Cart
          </button>
        </div>
      </div>

      {/* Recommended Products */}
      <div className="p-6">
        <div className="space-y-4">
          {recommendations.map((product) => (
            <div
              key={product.id}
              className="flex gap-4 p-4 border border-gray-200 rounded-xl hover:border-blue-300 hover:shadow-md transition-all"
            >
              {/* Product Image */}
              <img
                src={product.image}
                alt={product.name}
                className="w-24 h-24 object-cover rounded-lg bg-gray-100"
              />

              {/* Product Info */}
              <div className="flex-1">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <h4 className="font-semibold text-gray-900 mb-1">{product.name}</h4>
                    <p className="text-sm text-gray-500">{product.category} • {product.supplier}</p>
                  </div>
                  {product.inStock && (
                    <span className="flex items-center gap-1 px-2 py-1 bg-green-50 text-green-700 text-xs rounded-full">
                      <CheckCircle className="w-3 h-3" />
                      In Stock
                    </span>
                  )}
                </div>

                <div className="flex items-center gap-1 mb-3 text-sm text-blue-700 bg-blue-50 px-3 py-1.5 rounded-lg inline-flex">
                  <Info className="w-4 h-4" />
                  <span>{product.reason}</span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-6 text-sm">
                    <div>
                      <span className="text-gray-500">Qty: </span>
                      <span className="font-semibold text-gray-900">{product.quantity}</span>
                    </div>
                    <div>
                      <span className="text-gray-500">Unit Price: </span>
                      <span className="font-semibold text-gray-900">¥{product.price}</span>
                    </div>
                    <div>
                      <span className="text-gray-500">MOQ: </span>
                      <span className="text-gray-700">{product.moq}</span>
                    </div>
                    <div>
                      <span className="text-gray-500">Lead Time: </span>
                      <span className="text-gray-700">{product.leadTime}</span>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <span className="text-lg font-bold text-gray-900">
                      ¥{(product.price * product.quantity).toLocaleString()}
                    </span>
                    <button
                      onClick={() => onAddToCart(product.id)}
                      className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
                    >
                      <ShoppingCart className="w-4 h-4" />
                      Add to Cart
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Alternative Actions */}
        <div className="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
          <p className="text-sm text-gray-700 mb-3">
            Not quite what you're looking for?
          </p>
          <div className="flex gap-3">
            <button className="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
              Refine Requirements
            </button>
            <button className="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
              View Alternatives
            </button>
            <button className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
              Submit Sourcing Request
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
