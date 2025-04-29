"use client"

import { useEffect, useState, useRef } from "react"
import { useRouter, usePathname } from "next/navigation"
import Link from "next/link"
import { getUserBusinesses, getBusinessId, setBusinessId } from "@/lib/api-client"
import { Menu, X, ChevronRight } from "lucide-react"

// Business Selector Component
function BusinessSelector() {
  const [businesses, setBusinesses] = useState<any[]>([])
  const [currentBusinessId, setCurrentBusinessId] = useState<string | null>(null)
  const router = useRouter()
  
  useEffect(() => {
    const userBusinesses = getUserBusinesses()
    setBusinesses(userBusinesses || [])
    setCurrentBusinessId(getBusinessId())
  }, [])
  
  if (businesses.length <= 1) return null
  
  const handleBusinessChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newBusinessId = e.target.value
    setCurrentBusinessId(newBusinessId)
    setBusinessId(newBusinessId)
    
    // Refresh the page to apply new business context
    router.refresh()
  }
  
  return (
    <div className="px-4 my-4">
      <label htmlFor="business-selector" className="block text-sm text-gray-300 mb-1">
        Doanh nghiệp
      </label>
      <select 
        id="business-selector"
        value={currentBusinessId || ''}
        onChange={handleBusinessChange}
        className="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 text-sm"
      >
        {businesses.map((business: any) => (
          <option key={business.id} value={business.id}>
            {business.name}
          </option>
        ))}
      </select>
    </div>
  )
}

interface AppShellProps {
  children: React.ReactNode
}

export function AppShell({ children }: AppShellProps) {
  const router = useRouter()
  const pathname = usePathname()
  const [token, setToken] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [isMounted, setIsMounted] = useState(false)
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const sidebarRef = useRef<HTMLDivElement>(null)

  // Close sidebar when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (sidebarRef.current && !sidebarRef.current.contains(event.target as Node) && isSidebarOpen) {
        setIsSidebarOpen(false);
      }
    }
    
    document.addEventListener("mousedown", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, [isSidebarOpen]);

  // Close sidebar when route changes (mobile)
  useEffect(() => {
    setIsSidebarOpen(false);
  }, [pathname]);

  // This effect runs once on component mount
  useEffect(() => {
    setIsMounted(true)
  }, [])

  // This effect handles auth check only on client-side
  useEffect(() => {
    if (!isMounted) return // Don't run if not mounted yet
    
    try {
      // Check if user is logged in
      const savedToken = localStorage.getItem('auth_token')
      console.log("AppShell: Token from localStorage:", savedToken ? "exists" : "missing")
      setToken(savedToken)
      
      if (!savedToken && pathname !== '/login') {
        console.log("AppShell: No token, redirecting to login")
        router.push('/login')
      }
    } catch (error) {
      console.error("AppShell: Error checking auth:", error)
    } finally {
      // Always set loading to false to prevent UI from being stuck
      setIsLoading(false)
    }
  }, [isMounted, pathname, router])
  
  // Don't use AppShell layout for login page
  if (pathname === '/login') {
    return <>{children}</>
  }
  
  // Show loading spinner while checking auth
  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
      </div>
    )
  }

  const toggleSidebar = () => {
    setIsSidebarOpen(!isSidebarOpen);
  };

  // Always render content if not loading, even if no token
  // This helps prevent the UI from being stuck
  return (
    <div className="flex flex-col h-screen bg-gray-100">
      {/* Mobile Header */}
      <header className="lg:hidden bg-gray-800 text-white p-4 flex items-center justify-between">
        <button 
          onClick={toggleSidebar}
          className="text-white focus:outline-none"
          aria-label="Open menu"
        >
          <Menu size={24} />
        </button>
        <div className="font-bold text-xl">Quản lý bán hàng</div>
        <div className="w-6"></div> {/* Spacer for flex alignment */}
      </header>

      <div className="flex flex-1 overflow-hidden">
        {/* Sidebar - Desktop (fixed) and Mobile (overlay) */}
        <aside 
          ref={sidebarRef}
          className={`
            ${isSidebarOpen ? 'translate-x-0' : '-translate-x-full'} 
            lg:translate-x-0
            fixed lg:relative z-20 h-full lg:h-[calc(100vh-0px)] w-[80%] sm:w-[300px] lg:w-64 
            bg-gray-800 text-white shadow-lg
            transition-transform duration-300 ease-in-out
          `}
        >
          {/* Close button for mobile */}
          <div className="lg:hidden flex justify-end p-4">
            <button 
              onClick={() => setIsSidebarOpen(false)}
              className="text-gray-300 hover:text-white"
              aria-label="Close menu"
            >
              <X size={24} />
            </button>
          </div>

          <div className="p-4 font-bold text-xl lg:pt-4">Quản lý bán hàng</div>
          
          {/* Add Business Selector here */}
          <BusinessSelector />
          
          <nav className="mt-4">
            <ul className="space-y-2 px-4">
              <li>
                <Link 
                  href="/dashboard"
                  className={`flex items-center p-2 rounded ${pathname === '/dashboard' ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>Tổng quan</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li>
                <Link 
                  href="/products"
                  className={`flex items-center p-2 rounded ${pathname === '/products' ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>Sản phẩm</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li>
                <Link 
                  href="/orders"
                  className={`flex items-center p-2 rounded ${pathname === '/orders' ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>Đơn hàng</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li>
                <Link 
                  href="/data"
                  className={`flex items-center p-2 rounded ${pathname === '/data' ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>Dữ liệu</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li>
                <Link 
                  href="/ai"
                  className={`flex items-center p-2 rounded ${pathname?.startsWith('/ai') && pathname !== '/ai/conversations' ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>AI Agent</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li>
                <Link 
                  href="/ai/conversations"
                  className={`flex items-center p-2 rounded ${pathname?.startsWith('/ai/conversations') ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>Chatbot</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li>
                <Link 
                  href="/users"
                  className={`flex items-center p-2 rounded ${pathname === '/users' ? 'bg-blue-600' : 'hover:bg-gray-700'}`}
                >
                  <span>Người dùng</span>
                  <ChevronRight size={16} className="ml-auto" />
                </Link>
              </li>
              <li className="mt-8">
                <button 
                  onClick={() => {
                    try {
                      localStorage.removeItem('auth_token')
                    } catch (e) {
                      console.error("Error removing token:", e)
                    }
                    router.push('/login')
                  }}
                  className="flex items-center w-full p-2 rounded text-left hover:bg-red-600"
                >
                  <span>Đăng xuất</span>
                </button>
              </li>
            </ul>
          </nav>
        </aside>

        {/* Backdrop for mobile sidebar */}
        {isSidebarOpen && (
          <div 
            className="fixed inset-0 bg-black bg-opacity-50 z-10 lg:hidden"
            onClick={() => setIsSidebarOpen(false)}
          />
        )}

        {/* Main content */}
        <main className="flex-1 overflow-auto h-[calc(100vh-56px)] lg:h-screen w-full">
          {children}
        </main>
      </div>
    </div>
  )
} 