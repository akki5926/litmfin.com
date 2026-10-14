'use client';

import Link from 'next/link';
import { useState, useEffect } from 'react';
import Image from 'next/image';
import { usePathname } from 'next/navigation';
import siteConfig from "@/config/siteConfig";

const getAuthStatus = () => {
  try {
    const storedUser = localStorage.getItem('site_user');
    const authStatus = localStorage.getItem('site_auth');
    return !!(storedUser && authStatus === 'true');
  } catch {
    return false;
  }
};

export default function Footer() {
  const [email, setEmail] = useState('');
  // Lazy initializer reads localStorage synchronously — no flicker on first render
  const [isLoggedIn, setIsLoggedIn] = useState(() => {
    if (typeof window === 'undefined') return false;
    return getAuthStatus();
  });
  const pathname = usePathname();

  // Re-check on every route change so state is always fresh after navigation
  useEffect(() => {
    setIsLoggedIn(getAuthStatus());
  }, [pathname]);

  // Also listen for login/logout events
  useEffect(() => {
    const checkAuth = () => setIsLoggedIn(getAuthStatus());
    window.addEventListener('storage', checkAuth);
    window.addEventListener('authChange', checkAuth);
    return () => {
      window.removeEventListener('storage', checkAuth);
      window.removeEventListener('authChange', checkAuth);
    };
  }, []);

  const logoHref = isLoggedIn ? '/dashboard/home' : '/';

  const footerLinks = [
    {
      title: 'Investments',
      links: [
        { name: 'Mutual Funds', path: '/mutual-funds' },
        { name: 'SIP Calculator', path: '/sip-calculator' },
        { name: 'PMS', path: '/pms' },
        { name: 'AIF', path: '/aif' },
        // { name: 'Lumpsum Investment', path: '/lumpsum' },
      ],
    },
    {
      title: 'Services',
      links: [
        { name: 'Multi Asset', path: '/asset-allocation' },
        { name: 'RBI Bonds', path: '/rbi-bonds' },
        { name: 'Capital Gain Bonds', path: '/capital-gain-bonds' },
        // { name: 'Tax Planning', path: '/tax-planning' },
      ],
    },
    {
      title: 'Company',
      links: [
        { name: 'About Us', path: '/about-us' },
        { name: 'Careers', path: '/careers' },
        { name: 'Blog', path: '/blog' },
        { name: 'Media', path: '/media' },
      ],
    },
    {
      title: 'Support',
      links: [
        { name: 'Contact Us', path: '/contact' },
        { name: 'FAQ', path: '/faq' },
        { name: 'Privacy Policy', path: '/privacy-policy' },
        { name: 'Terms of Use', path: '/terms' },
      ],
    },
  ];

  const social = [
    { 
      name: 'LinkedIn', 
      url: siteConfig.social?.linkedin || '#',
      icon: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.92 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/>
        </svg>
      )
    },
    { 
      name: 'Twitter', 
      url: siteConfig.social?.twitter || '#',
      icon: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
        </svg>
      )
    },
    { 
      name: 'Facebook', 
      url: siteConfig.social?.facebook || '#',
      icon: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
        </svg>
      )
    },
     {
      name: 'Instagram',
      url: siteConfig.social?.instagram || '#',
      icon: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM12 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
        </svg>
      )
    },
    { 
      name: 'YouTube', 
      url: siteConfig.social?.youtube || '#',
      icon: (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.5 12 3.5 12 3.5s-7.505 0-9.377.55A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.552 9.376.552 9.376.552s7.505 0 9.377-.55a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
        </svg>
      )
    },
  ];

  const handleSubscribe = (e) => {
    e.preventDefault();
    alert(`Thank you for subscribing with: ${email}`);
    setEmail('');
  };

  return (
    <footer className="bg-gradient-to-b from-gray-900 to-gray-950 text-white">
      <div className="container-custom">
        <div className="section-padding py-8 px-6">
          {/* Main Footer Content - Reduced vertical spacing */}
          <div className="grid grid-cols-1 lg:grid-cols-5 gap-12 mb-6">
            {/* Brand */}
            <div className="lg:col-span-2">
              <div className="mb-6">
                <Link href={logoHref} className="inline-block" suppressHydrationWarning>
                  <Image
                    src={siteConfig.branding.footerLogo}
                    alt={`${siteConfig.siteName} - ${siteConfig.tagline}`}
                    width={280}
                    height={100}
                    className="w-auto h-20 object-contain"
                    priority
                  />
                </Link>
              </div>

              <p className="text-gray-400 mb-4 max-w-md">
                {siteConfig.footer.description}
              </p>

              {/* Newsletter Subscription */}
              {/* <div className="border border-gray-800 rounded-xl p-4 mb-4">

                <div className="w-full mb-3">
                  <div className="flex items-center gap-2">
                    <h3 className="font-bold text-blue-300 whitespace-nowrap">Stay Updated</h3>
                    <span className="text-gray-400">•</span>
                    <p className="text-gray-400 text-sm flex-1">
                      Get investment insights and market updates
                    </p>
                  </div>
                </div>
                
                
                <form onSubmit={handleSubscribe} className="flex gap-2 mb-2">
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Your email"
                    className="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent"
                    required
                  />
                  <button
                    type="submit"
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
                  >
                    Subscribe
                  </button>
                </form>
                <p className="text-gray-500 text-xs">
                  We respect your privacy. Unsubscribe anytime.
                </p>
              </div> */}
            </div>

            {/* Links Grid - Reduced vertical spacing */}
            <div className="lg:col-span-3">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-4">
                {footerLinks.map((section, idx) => (
                  <div key={idx}>
                    <h4 className="font-bold text-lg mb-4 pb-3 border-b" style={{color: '#E2E2E2', borderColor: '#E2E2E2'}}>{section.title}</h4>
                    <ul className="space-y-2">
                      {section.links.map((link) => (
                        <li key={link.name}>
                          <Link
                            href={link.path}
                            className="text-gray-400 hover:text-white transition-colors duration-300 flex items-center group text-sm"
                          >
                            <span className="w-1 h-1 bg-blue-500 rounded-full mr-2 opacity-0 group-hover:opacity-100 transition-opacity"></span>
                            {link.name}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>

              {/* Follow Us - Reduced vertical spacing */}
              <div className="border-t border-gray-800 pt-4">
                <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                  <div className="flex items-center space-x-4">
                    <span className="text-gray-400 font-medium">Follow us:</span>
                    <div className="flex space-x-2">
                      {social.map((item) => (
                        <a
                          key={item.name}
                          href={item.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center text-white hover:text-blue-400 transition-all duration-300 hover:bg-gray-700"
                          aria-label={item.name}
                        >
                          {item.icon}
                        </a>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Bottom Bar - Further reduced vertical spacing */}
          <div className="border-t border-gray-800 pt-4 pb-4 px-4" style={{backgroundColor: 'rgb(3 7 18 / var(--tw-bg-opacity, 1))'}}>
            {/* Disclaimer and Regulatory Info */}
            <p className="text-gray-400 text-xs text-center max-w-none mx-auto mb-3 py-3">
              Mutual fund investments are subject to market risks. Please read the scheme information and other related documents carefully before investing. Past performance is not indicative of future returns. Portfolio returns and allocation between equity and debt are estimated based on a number of factors including the user&apos;s risk profile, goal horizon and disclosed financial position. Performance of any investment portfolio can neither be predicted nor guaranteed.
            </p>
            <p className="text-gray-400 text-xs text-center max-w-none mx-auto py-4 border-t border-b border-gray-800">
              {siteConfig.footer.text}
            </p>
          </div>
            
          {/* Copyright - Single line and centered */}
          <div className="text-center pt-4">
            <div className="text-gray-500 text-sm" suppressHydrationWarning>
              © {new Date().getFullYear()} All rights reserved {siteConfig.domain}. 
              <span className="mx-2">•</span>
              Developed by <a href='https://www.zieers.com/' target='_blank' rel="noopener noreferrer" className="text-blue-400 hover:text-blue-300 transition-colors">Zieers Systems Pvt Ltd</a>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
}