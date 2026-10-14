'use client';

import Image from 'next/image';
import Link from 'next/link';
import { useState, useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import siteConfig from "@/config/siteConfig";

export default function Header() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const [resourcesDropdownOpen, setResourcesDropdownOpen] = useState(false);
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [user, setUser] = useState(null);
  const [isMounted, setIsMounted] = useState(false);
  const router = useRouter();
  const pathname = usePathname();

  // Set mounted state
  useEffect(() => {
    setIsMounted(true);
  }, []);

  // Redirect logged-in users away from auth pages
  useEffect(() => {
    if (!isMounted || !isLoggedIn) return;

    const authPages = ['/login', '/signup', '/forgot-password', '/verify', '/verify-email', '/reset-password'];
    const isOnAuthPage = authPages.some(authPage => pathname.startsWith(authPage));

    if (isOnAuthPage) {
      router.replace('/dashboard/home');
    }
  }, [pathname, isLoggedIn, isMounted, router]);

  // Close dropdowns on scroll
  useEffect(() => {
    const handleScroll = () => {
      setUserDropdownOpen(false);
      setResourcesDropdownOpen(false);
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Prevent back button navigation to auth pages when logged in
  useEffect(() => {
    if (!isLoggedIn) return;

    // Define protected auth pages that logged-in users shouldn't navigate to
    const authPages = ['/login', '/signup', '/forgot-password', '/verify', '/verify-email', '/reset-password'];
    
    const handlePopState = () => {
      const currentPath = window.location.pathname;
      
      // Check if current path is an auth page
      const isAuthPage = authPages.some(authPage => currentPath.startsWith(authPage));
      
      if (isAuthPage) {
        // Prevent navigation to auth pages by replacing history
        window.history.forward();
        // Redirect to dashboard instead
        router.push('/dashboard/home');
      }
    };

    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, [isLoggedIn, router]);

  // Helper function to check if a path is active
  const isPathActive = (href) => {
    if (!isMounted) return false;
    if (!pathname) return false;
    
    const normalizedPathname = pathname.endsWith('/') && pathname !== '/' ? pathname.slice(0, -1) : pathname;
    const normalizedHref = href.endsWith('/') && href !== '/' ? href.slice(0, -1) : href;
    
    return normalizedPathname === normalizedHref;
  };

  const handleLogout = () => {
    localStorage.removeItem('site_user');
    localStorage.removeItem('site_auth');
    localStorage.removeItem('site_token');
    localStorage.removeItem('site_remember');
    localStorage.removeItem('site_last_activity');
    
    setIsLoggedIn(false);
    setUser(null);
    setUserDropdownOpen(false);
    
    window.dispatchEvent(new Event('authChange'));
    router.push('/');
  };

  // Global fetch interceptor to handle 401 (Unauthorized) responses
  useEffect(() => {
    if (typeof window !== 'undefined' && !window.fetchInterceptorAttached) {
      window.fetchInterceptorAttached = true;
      const originalFetch = window.fetch;
      window.fetch = async (...args) => {
        try {
          const response = await originalFetch(...args);
          if (response.status === 401) {
            console.log('Intercepted 401 response, logging out...');
            localStorage.removeItem('site_user');
            localStorage.removeItem('site_auth');
            localStorage.removeItem('site_token');
            localStorage.removeItem('site_last_activity');
            window.dispatchEvent(new Event('authChange'));
            
            // Check if we are already on the login page to avoid redirect loops
            if (!window.location.pathname.startsWith('/login')) {
              window.location.href = '/login?expired=true';
            }
          }
          return response;
        } catch (error) {
          throw error;
        }
      };
    }
  }, []);

  // Check login status
  useEffect(() => {
    const checkAuth = () => {
      try {
        const storedUser = localStorage.getItem('site_user');
        const authStatus = localStorage.getItem('site_auth');
        const token = localStorage.getItem('site_token');
        
        if (storedUser && authStatus === 'true' && token) {
          // Client-side JWT token expiry check
          try {
            const parts = token.split('.');
            if (parts.length === 3) {
              const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')));
              if (payload.exp && Date.now() / 1000 > payload.exp) {
                console.log('Token expired client-side');
                handleLogout();
                return;
              }
            }
          } catch (e) {
            console.error('Error parsing token:', e);
          }

          const userData = JSON.parse(storedUser);
          setIsLoggedIn(true);
          setUser(userData);
        } else {
          setIsLoggedIn(false);
          setUser(null);
        }
      } catch (error) {
        console.error('Error checking auth:', error);
        setIsLoggedIn(false);
        setUser(null);
      }
    };

    checkAuth();
    window.addEventListener('storage', checkAuth);
    window.addEventListener('authChange', checkAuth);

    return () => {
      window.removeEventListener('storage', checkAuth);
      window.removeEventListener('authChange', checkAuth);
    };
  }, [pathname]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (userDropdownOpen && !event.target.closest('.user-dropdown-container')) {
        setUserDropdownOpen(false);
      }
      if (resourcesDropdownOpen && !event.target.closest('.resources-dropdown-container')) {
        setResourcesDropdownOpen(false);
      }
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, [userDropdownOpen, resourcesDropdownOpen]);

  // Navigation items for non-logged in users
  const publicNavItems = [
    { name: 'Multi Asset', href: '/asset-allocation' },
    { name: 'Offerings', href: '/offerings' },
    { name: 'About Us', href: '/about-us' },
  ];

  // Navigation items for logged-in users
  const loggedInNavItems = [
    { name: 'Asset Splits', href: '/asset-splits' },
    { name: 'Market Capitalization', href: '/market-capitalization' },
    { name: 'SIP Report', href: '/sip-report' },
    { name: 'CAS Report', href: '/cas-report' },
  ];

  // Resources dropdown items - Horizontal layout (only for non-logged in users)
  const resourcesItems = [
    { name: 'Blogs', href: '/blog', icon: '📝' },
    { name: 'Media', href: '/media', icon: '📰' },
  ];

  // User dropdown menu items (only for logged-in users)
  const userMenuItems = [
    { name: 'Profile', href: '/dashboard/home', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Invest Now', href: '/invest', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Current SIPs', href: '/sips', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Portfolio', href: '/portfolio', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { name: 'Transaction History', href: '/transactions', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { name: 'Recommended Funds', href: '/recommended-funds', icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z' },
    { name: 'Tax Saver Funds', href: '/tax-saver', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Other Investment', href: '/other-investment', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
    { name: 'Mandate Registration', href: '/dashboard/mandate-registration', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { name: 'FAQ', href: '/faq', icon: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  ];

  const getInitials = (name) => {
    if (!name) return 'U';
    const names = name.split(' ');
    if (names.length === 1) return names[0].charAt(0).toUpperCase();
    return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
  };

  // Resources dropdown handler - only on click
  const handleResourcesClick = () => {
    setResourcesDropdownOpen(!resourcesDropdownOpen);
  };

  // Function to render navigation items
  const renderNavItems = (items, isMobile = false) => {
    return items.map((item) => {
      const isActive = isPathActive(item.href);
      
      if (isMobile) {
        return (
          <Link
            key={item.name}
            href={item.href}
            className={`flex items-center justify-between px-4 py-3.5 rounded-xl font-medium transition-all duration-300 border-l-4 mt-1 ${
              isActive
                ? 'bg-blue-50 border-blue-600 text-blue-700'
                : 'border-transparent text-gray-700 hover:bg-blue-50 hover:text-blue-600'
            }`}
            onClick={() => setIsMenuOpen(false)}
          >
            <span>{item.name}</span>
          </Link>
        );
      }
      
      return (
        <Link
          key={item.name}
          href={item.href}
          className={`relative px-4 py-2 rounded-lg font-medium transition-all duration-300 ${
            isActive ? 'text-blue-700' : 'text-gray-700 hover:text-blue-600'
          }`}
        >
          {item.name}
          <span className={`absolute bottom-0 left-4 right-4 h-0.5 bg-gradient-to-r from-blue-600 to-blue-700 rounded-full transition-all duration-300 ${
            isActive ? 'opacity-100 scale-x-100' : 'opacity-0 scale-x-0'
          }`}></span>
        </Link>
      );
    });
  };

  return (
    <header className="bg-white shadow-sm border-b sticky top-0 z-50">
      <div className="w-full px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Logo */}
          <div className="flex justify-start items-center lg:flex-1">
            <Link href={isLoggedIn ? "/dashboard/home" : "/"} className="flex items-center space-x-3">
              <Image
                src={siteConfig.branding.logo}
                alt={`${siteConfig.siteName} - ${siteConfig.tagline}`}
                width={150}
                height={50}
                className="h-14 w-auto object-contain"
                priority
              />
            </Link>
          </div>

          {/* Desktop Navigation - Different menus based on login status */}
          <nav className="hidden lg:flex items-center justify-center space-x-1">
            {!isLoggedIn ? (
              // Public menu (when NOT logged in)
              <>
                {renderNavItems(publicNavItems.slice(0, 1))} {/* Asset Allocation */}
                {renderNavItems(publicNavItems.slice(1, 2))} {/* Offerings */}
                
                {/* Resources Dropdown */}
                <div className="relative resources-dropdown-container">
                  <button
                    onClick={handleResourcesClick}
                    className={`relative px-4 py-2 rounded-lg font-medium transition-all duration-300 flex items-center gap-1.5 ${
                      resourcesDropdownOpen
                        ? 'text-blue-700 bg-blue-50'
                        : 'text-gray-700 hover:text-blue-600 hover:bg-gray-50'
                    }`}
                  >
                    <span>Resources</span>
                    <svg 
                      className={`w-4 h-4 transition-transform duration-200 ${resourcesDropdownOpen ? 'rotate-180' : ''}`}
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 16 16"
                    >
                      <path 
                        fillRule="evenodd" 
                        clipRule="evenodd" 
                        d="M2.55806 6.29544C2.46043 6.19781 2.46043 6.03952 2.55806 5.94189L3.44195 5.058C3.53958 4.96037 3.69787 4.96037 3.7955 5.058L8.00001 9.26251L12.2045 5.058C12.3021 4.96037 12.4604 4.96037 12.5581 5.058L13.4419 5.94189C13.5396 6.03952 13.5396 6.19781 13.4419 6.29544L8.17678 11.5606C8.07915 11.6582 7.92086 11.6582 7.82323 11.5606L2.55806 6.29544Z" 
                        fill="currentColor"
                      />
                    </svg>
                    <span className={`absolute bottom-0 left-4 right-4 h-0.5 bg-gradient-to-r from-blue-600 to-blue-700 rounded-full transition-all duration-300 ${
                      resourcesDropdownOpen ? 'opacity-100 scale-x-100' : 'opacity-0 scale-x-0'
                    }`}></span>
                  </button>
                  
                  {resourcesDropdownOpen && (
                    <div className="absolute left-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-100 py-2 z-30 flex flex-row min-w-[200px]">
                      {resourcesItems.map((item) => {
                        const isActive = isPathActive(item.href);
                        return (
                          <Link
                            key={item.name}
                            href={item.href}
                            className={`flex items-center px-4 py-2.5 text-sm transition-all duration-200 whitespace-nowrap ${
                              isActive
                                ? 'text-blue-700 bg-blue-50'
                                : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'
                            }`}
                            onClick={() => {
                              setResourcesDropdownOpen(false);
                              setIsMenuOpen(false);
                            }}
                          >
                            <span className="mr-2 text-base">{item.icon}</span>
                            <span>{item.name}</span>
                          </Link>
                        );
                      })}
                    </div>
                  )}
                </div>
                
                {renderNavItems(publicNavItems.slice(2, 3))} {/* About Us */}
              </>
            ) : (
              // Logged-in menu
              renderNavItems(loggedInNavItems)
            )}
          </nav>

          {/* Right auth area */}
          <div className="hidden lg:flex items-center justify-end lg:flex-1">
            {isLoggedIn && user ? (
              <div className="relative user-dropdown-container">
                <button
                  onClick={() => setUserDropdownOpen(!userDropdownOpen)}
                  className="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                >
                  <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center">
                    <span className="text-white font-bold">{getInitials(user.name)}</span>
                  </div>
                  <div className="hidden md:block text-left">
                    <p className="text-sm font-medium text-gray-900">{user.name || 'User'}</p>
                  </div>
                  <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                {userDropdownOpen && (
                  <>
                    <div className="fixed inset-0 z-20" onClick={() => setUserDropdownOpen(false)} />
                    <div className="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl py-1 z-30">
                      <div className="px-4 py-2 border-b">
                        <p className="text-sm font-medium text-gray-900">{user.name}</p>
                      </div>
                      
                      <div className="py-1 max-h-96 overflow-y-auto">
                        {userMenuItems.map((item) => {
                          const isActive = isPathActive(item.href);
                          return (
                            <Link
                              key={item.name}
                              href={item.href}
                              className={`flex items-center px-4 py-1.5 text-sm transition-all duration-200 group rounded-lg mx-2 ${
                                isActive
                                  ? 'text-blue-700 font-semibold bg-blue-100'
                                  : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'
                              }`}
                              onClick={() => setUserDropdownOpen(false)}
                            >
                              <svg className={`w-5 h-5 mr-3 transition-colors ${isActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                              </svg>
                              <span>{item.name}</span>
                            </Link>
                          );
                        })}
                      </div>
                      
                      <div className="pt-1">
                        <button onClick={handleLogout} className="w-full flex items-center px-4 py-1.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                          <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                          </svg>
                          Logout
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>
            ) : (
              <Link href="/login" className="px-5 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 text-white font-medium shadow-sm hover:opacity-95 transition">
                Login
              </Link>
            )}
          </div>

          {/* Mobile menu button */}
          <button
            className="lg:hidden text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors"
            onClick={() => setIsMenuOpen(!isMenuOpen)}
            aria-label="Toggle menu"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isMenuOpen ? "M6 18L18 6M6 6l12 12" : "M4 6h16M4 12h16M4 18h16"} />
            </svg>
          </button>
        </div>

        {/* Mobile menu */}
        {isMenuOpen && (
          <div className="lg:hidden mt-2 pb-4">
            <div className="bg-white rounded-2xl shadow-lg p-4 border border-gray-200">
              {!isLoggedIn ? (
                // Mobile Public Menu
                <>
                  {renderNavItems(publicNavItems.slice(0, 1), true)} {/* Asset Allocation */}
                  {renderNavItems(publicNavItems.slice(1, 2), true)} {/* Offerings */}
                  
                  {/* Mobile Resources Section */}
                  <div className="mt-2 pt-2">
                    <div className="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                      </svg>
                      Resources
                    </div>
                    {resourcesItems.map((item) => {
                      const isActive = isPathActive(item.href);
                      return (
                        <Link
                          key={item.name}
                          href={item.href}
                          className={`flex items-center px-4 py-3 rounded-xl transition-all duration-300 ${
                            isActive
                              ? 'bg-blue-50 text-blue-700'
                              : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'
                          }`}
                          onClick={() => setIsMenuOpen(false)}
                        >
                          <span className="mr-3 text-xl">{item.icon}</span>
                          <span className="font-medium">{item.name}</span>
                        </Link>
                      );
                    })}
                  </div>
                  
                  {renderNavItems(publicNavItems.slice(2, 3), true)} {/* About Us */}
                  
                  <div className="border-t border-gray-100 pt-4 mt-2">
                    <Link href="/login" className="block w-full text-center py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all" onClick={() => setIsMenuOpen(false)}>
                      Login / Sign Up
                    </Link>
                  </div>
                </>
              ) : (
                // Mobile Logged-in Menu
                <>
                  {renderNavItems(loggedInNavItems, true)}
                  
                  <div className="border-t border-gray-100 pt-4 mt-4">
                    <div className="flex items-center space-x-3 mb-4 px-2">
                      <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center">
                        <span className="text-white font-bold text-sm">{getInitials(user?.name || 'User')}</span>
                      </div>
                      <div>
                        <p className="font-medium text-gray-700">{user?.name || 'User'}</p>
                        <p className="text-xs text-gray-500">Activation Pending</p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-2">
                      {userMenuItems.slice(0, 4).map((item) => (
                        <Link
                          key={item.name}
                          href={item.href}
                          className="flex flex-col items-center justify-center p-3 rounded-lg bg-gray-50 hover:bg-blue-50 transition-colors text-center"
                          onClick={() => setIsMenuOpen(false)}
                        >
                          <svg className="w-5 h-5 mb-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                          </svg>
                          <span className="text-xs font-medium text-gray-700">{item.name}</span>
                        </Link>
                      ))}
                    </div>
                    
                    <button onClick={handleLogout} className="w-full mt-4 flex items-center justify-center py-3 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                      <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                      </svg>
                      Logout
                    </button>
                  </div>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </header>
  );
}