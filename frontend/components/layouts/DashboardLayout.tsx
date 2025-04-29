import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import {
  LayoutDashboard,
  User,
  LogOut,
  Menu,
  X,
} from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';

export function DashboardLayout({ children }: { children: React.ReactNode }) {
  const [isSidebarOpen, setIsSidebarOpen] = useState(true);
  const { logout } = useAuth();
  const router = useRouter();

  const handleLogout = async () => {
    try {
      await logout();
      router.push('/login');
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <div className="flex h-screen bg-gray-100">
      {/* Sidebar */}
      <div
        className={`${
          isSidebarOpen ? 'w-64' : 'w-20'
        } bg-white shadow-lg transition-all duration-300`}
      >
        <div className="flex items-center justify-between p-4">
          {isSidebarOpen && <h1 className="text-xl font-bold">Dashboard</h1>}
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setIsSidebarOpen(!isSidebarOpen)}
          >
            {isSidebarOpen ? <X size={24} /> : <Menu size={24} />}
          </Button>
        </div>
        <nav className="mt-4">
          <Link
            href="/dashboard"
            className="flex items-center p-4 text-gray-700 hover:bg-gray-100"
          >
            <LayoutDashboard size={20} />
            {isSidebarOpen && <span className="ml-4">Dashboard</span>}
          </Link>
          <Link
            href="/profile"
            className="flex items-center p-4 text-gray-700 hover:bg-gray-100"
          >
            <User size={20} />
            {isSidebarOpen && <span className="ml-4">Profile</span>}
          </Link>
          <button
            onClick={handleLogout}
            className="flex items-center w-full p-4 text-gray-700 hover:bg-gray-100"
          >
            <LogOut size={20} />
            {isSidebarOpen && <span className="ml-4">Logout</span>}
          </button>
        </nav>
      </div>

      {/* Main Content */}
      <div className="flex-1 overflow-auto">
        <main className="p-6">{children}</main>
      </div>
    </div>
  );
} 