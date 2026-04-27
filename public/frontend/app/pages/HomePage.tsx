import { useState } from 'react';
import { useNavigate } from 'react-router';
import { HeroSearch } from '../components/HeroSearch';
import { ProcurementEntryCards } from '../components/ProcurementEntryCards';
import { AIRecommendationPanel } from '../components/AIRecommendationPanel';
import { CategoryGrid } from '../components/CategoryGrid';
import { FeaturedProducts } from '../components/FeaturedProducts';
import { SourcingServiceSection } from '../components/SourcingServiceSection';
import { HowItWorks } from '../components/HowItWorks';
import { WhyChooseUs } from '../components/WhyChooseUs';

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

export function HomePage() {
  const navigate = useNavigate();
  const [showRecommendations, setShowRecommendations] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Mock AI recommendations
  const mockRecommendations: RecommendedProduct[] = [
    {
      id: '1',
      name: 'Acrylic Paint Set - 24 Colors',
      category: 'Art Supplies',
      price: 28.50,
      quantity: 15,
      supplier: 'ArtMaster Pro',
      image: 'https://images.unsplash.com/photo-1765484253358-70f69979d307?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxhcnQlMjBjcmFmdCUyMHN1cHBsaWVzJTIwY29sb3JmdWx8ZW58MXx8fHwxNzc1MjcxNzI4fDA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral',
      reason: 'Perfect for art workshop with 20 participants',
      inStock: true,
      moq: 10,
      leadTime: '3-5 days',
    },
    {
      id: '2',
      name: 'Canvas Boards 11x14" (Pack of 12)',
      category: 'Art Supplies',
      price: 32.00,
      quantity: 2,
      supplier: 'CreativeHub',
      image: 'https://images.unsplash.com/photo-1586958060273-f1ddaafbd396?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxzdGF0aW9uZXJ5JTIwcGVucyUyMG5vdGVib29rc3xlbnwxfHx8fDE3NzUyNzE3Mjl8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral',
      reason: 'Matches workshop participant count',
      inStock: true,
      moq: 1,
      leadTime: '2-4 days',
    },
    {
      id: '3',
      name: 'Premium Brush Set - Assorted Sizes',
      category: 'Art Supplies',
      price: 18.75,
      quantity: 20,
      supplier: 'BrushMasters',
      image: 'https://images.unsplash.com/photo-1768875820800-1c2a6f2e8280?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxvZmZpY2UlMjBzdXBwbGllcyUyMGRlc2slMjBvcmdhbml6ZWR8ZW58MXx8fHwxNzc1MjcxNzI3fDA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral',
      reason: 'One set per participant recommended',
      inStock: true,
      moq: 15,
      leadTime: '5-7 days',
    },
  ];

  const handleSearchSubmit = (query: string, type: 'search' | 'ai' | 'sourcing' | 'links') => {
    setSearchQuery(query);
    if (type === 'ai') {
      setShowRecommendations(true);
    } else if (type === 'sourcing' || type === 'links') {
      navigate('/sourcing');
    } else {
      setShowRecommendations(false);
      navigate(`/marketplace${query ? `?search=${encodeURIComponent(query)}` : ''}`);
    }
  };

  const handleAddToCart = (_productId: string) => {
    navigate('/marketplace');
  };

  return (
    <>
      {/* Hero Section */}
      <HeroSearch onSearchSubmit={handleSearchSubmit} />

      {/* AI Recommendations Panel - Conditional */}
      {showRecommendations && (
        <div className="max-w-[1400px] mx-auto px-6 py-8">
          <AIRecommendationPanel
            query={searchQuery}
            recommendations={mockRecommendations}
            onClose={() => setShowRecommendations(false)}
            onAddToCart={handleAddToCart}
          />
        </div>
      )}

      {/* Core Procurement Entry Cards */}
      <ProcurementEntryCards />

      {/* Category Grid */}
      <CategoryGrid />

      {/* Featured Products */}
      <FeaturedProducts />

      {/* Sourcing Service Section */}
      <SourcingServiceSection />

      {/* How It Works */}
      <HowItWorks />

      {/* Why Choose Us */}
      <WhyChooseUs />
    </>
  );
}
