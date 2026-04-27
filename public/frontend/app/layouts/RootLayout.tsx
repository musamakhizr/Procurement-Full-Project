import { Outlet } from 'react-router';
import { Header } from '../components/Header';
import { Footer } from '../components/Footer';

export function RootLayout() {
  return (
    <div className="min-h-screen bg-[#F8FAFC]">
      <Header />
      <Outlet />
      <Footer />
    </div>
  );
}