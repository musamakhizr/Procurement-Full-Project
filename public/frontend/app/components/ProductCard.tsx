import { ShoppingCart, Star, TrendingUp, Package } from 'lucide-react';

interface ProductCardProps {
  id: string;
  name: string;
  category: string;
  price: number;
  image: string;
  supplier: string;
  rating: number;
  reviews: number;
  moq: number;
  leadTime: string;
  verified: boolean;
  trending?: boolean;
}

export function ProductCard({
  name,
  category,
  price,
  image,
  supplier,
  rating,
  reviews,
  moq,
  leadTime,
  verified,
  trending,
}: ProductCardProps) {
  return (
    <div className="bg-white rounded-2xl border-2 border-slate-200 hover:border-[#4F6BFF]/30 hover:shadow-xl transition-all overflow-hidden group">
      {/* Image */}
      <div className="relative overflow-hidden bg-slate-50">
        <img
          src={image}
          alt={name}
          className="w-full h-52 object-cover group-hover:scale-105 transition-transform duration-300"
        />
        {trending && (
          <div className="absolute top-3 left-3 flex items-center gap-1 px-2.5 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-lg uppercase tracking-wide">
            <TrendingUp className="w-3 h-3" />
            Trending
          </div>
        )}
        {verified && (
          <div className="absolute top-3 right-3 px-2.5 py-1.5 bg-[#4F6BFF] text-white text-[11px] font-bold rounded-lg uppercase tracking-wide">
            Verified
          </div>
        )}
      </div>

      {/* Content */}
      <div className="p-5">
        <p className="text-[11px] text-slate-500 mb-2 uppercase tracking-wide font-semibold">{category}</p>
        <h3 className="font-bold text-slate-900 mb-3 line-clamp-2 min-h-[2.5rem] text-[15px] leading-tight">{name}</h3>

        <p className="text-sm text-slate-600 mb-4 font-medium">{supplier}</p>

        <div className="flex items-center gap-2 mb-4">
          <div className="flex items-center gap-1">
            <Star className="w-4 h-4 fill-amber-400 text-amber-400" />
            <span className="text-sm font-bold text-slate-900">{rating}</span>
          </div>
          <span className="text-xs text-slate-400">({reviews})</span>
        </div>

        <div className="flex items-center gap-4 mb-5 text-xs text-slate-500 font-medium">
          <div className="flex items-center gap-1.5">
            <Package className="w-3.5 h-3.5" />
            <span>MOQ {moq}</span>
          </div>
          <div className="w-1 h-1 bg-slate-300 rounded-full"></div>
          <div>{leadTime}</div>
        </div>

        <div className="flex items-center justify-between pt-5 border-t-2 border-slate-100">
          <div>
            <p className="text-[11px] text-slate-500 mb-1 uppercase tracking-wide font-semibold">Business pricing</p>
            <p className="text-2xl font-bold text-slate-900">¥{price}</p>
          </div>
          <button className="flex items-center gap-2 px-5 py-2.5 bg-[#4F6BFF] text-white rounded-xl font-semibold hover:bg-[#3F5AF5] transition-colors text-sm shadow-sm">
            Request Quote
          </button>
        </div>
      </div>
    </div>
  );
}
