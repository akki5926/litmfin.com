'use client';

import { usePathname } from 'next/navigation';
import Link from 'next/link';

export default function DashboardSidebar({ user, currentPath }) {
  const getInitials = () => {
    if (!user?.name) return 'U';
    const names = user.name.split(' ');
    if (names.length === 1) return names[0].charAt(0).toUpperCase();
    return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
  };

  const menuItems = [
    { label: 'Dashboard', href: '/dashboard/home', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'Portfolio', href: '/portfolio', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { label: 'Invest Now', href: '/dashboard/invest', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Current SIPs', href: '/sips', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Transaction History', href: '/transactions', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { label: 'Recommended Funds', href: '/recommended-funds', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Tax Saver Funds', href: '/tax-saver', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { label: 'FAQ', href: '/faq', icon: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Help Center', href: '/help', icon: 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z' },
  ];
// console.log('Dashboardsidebar.js user:', user);

  // Get UCC from user object
  // const getUCC = () => {
  //   return user?.UCC || user?.ucc || user?.clientCode || 'N/A';
  // };

  return (
    <aside className="h-[calc(100vh-4rem)] sticky top-16 border-r bg-white overflow-y-auto">
      {/* User Profile Card */}
      <div className="p-6 border-b">
        <div className="text-center">
          {/* Big Circle with Initials */}
          <div className="w-24 h-24 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center mx-auto mb-4">
            <span className="text-white text-3xl font-bold">{getInitials()}</span>
          </div>
          
          <h3 className="text-lg font-semibold text-gray-900">{user?.name || 'User'}</h3>
          {/* <div className="mt-2 inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-600 text-sm font-medium">
            UCC: {getUCC()}
          </div> */}
          
          <div className="mt-4 grid grid-cols-2 gap-3">
            <div className="bg-gray-50 p-3 rounded-lg">
              <p className="text-xs text-gray-500">Status</p>
              <p className="text-sm font-medium text-yellow-600">Activation Pending 3</p>
            </div>
            <div className="bg-gray-50 p-3 rounded-lg">
              <p className="text-xs text-gray-500">KYC</p>
              <p className="text-sm font-medium text-green-600">Verified</p>
            </div>
          </div>
        </div>
      </div>

      {/* Navigation Menu */}
      <nav className="p-4 space-y-1">
        {menuItems.map((item) => {
          const isActive = currentPath === item.href;
          return (
            <Link
              key={item.label}
              href={item.href}
              className={`
                flex items-center px-4 py-3 rounded-lg transition-colors
                ${isActive 
                  ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600' 
                  : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'
                }
              `}
            >
              <svg 
                className="w-5 h-5 mr-3 flex-shrink-0" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
              </svg>
              <span className="font-medium">{item.label}</span>
              {item.label === 'Recommended Funds' && (
                <span className="ml-auto bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full">Hot</span>
              )}
            </Link>
          );
        })}
      </nav>

      {/* Quick Stats */}
      <div className="p-4 mt-4 border-t">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Quick Stats</h4>
        <div className="space-y-3">
          <div>
            <p className="text-xs text-gray-500">Total Investment</p>
            <p className="text-lg font-bold text-gray-900">₹2,45,678</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Current Value</p>
            <p className="text-lg font-bold text-green-600">₹2,78,432</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Returns</p>
            <p className="text-lg font-bold text-green-600">+13.3%</p>
          </div>
        </div>
      </div>
    </aside>
  );
}