import { Outlet } from 'react-router';
import { Header } from '../components/Header';
import { Footer } from '../components/Footer';
import { ScrollToTop } from '../components/ScrollToTop';

export function RootLayout() {
  return (
    <div className="min-h-screen bg-[#F8FAFC]">
      <ScrollToTop />
      <Header />
      <Outlet />
      <Footer />
    </div>
  );
}
