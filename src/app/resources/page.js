/* eslint-disable @next/next/no-img-element */
'use client';

import { useState } from 'react';
import siteConfig from '@/config/siteConfig';
import TypedText from '@/components/TypedText';
import Link from 'next/link';

export default function SmartInvestmentAdvisory() {
  const [isResourcesOpen, setIsResourcesOpen] = useState(false);

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
      {/* Header with Navigation */}
      <header className="bg-white shadow-md sticky top-0 z-50">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            {/* Logo */}
            <div className="text-2xl font-bold text-blue-900">
              {siteConfig.CompanyName || 'WealthAdvisor'}
            </div>
            
            {/* Navigation Menu */}
            <nav className="hidden md:flex items-center space-x-8">
              <a href="/" className="text-gray-700 hover:text-blue-600 transition-colors">Home</a>
              <a href="#about" className="text-gray-700 hover:text-blue-600 transition-colors">About</a>
              <a href="#services" className="text-gray-700 hover:text-blue-600 transition-colors">Services</a>
              
              {/* Resources Dropdown */}
              <div className="relative">
                <button
                  onClick={() => setIsResourcesOpen(!isResourcesOpen)}
                  className="flex items-center gap-1 text-gray-700 hover:text-blue-600 transition-colors"
                >
                  Resources
                  <svg className={`w-4 h-4 transition-transform ${isResourcesOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                
                {/* Dropdown Menu */}
                {isResourcesOpen && (
                  <div className="absolute top-full left-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-100 py-2 z-50">
                    <Link
                      href="/blog"
                      className="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                      onClick={() => setIsResourcesOpen(false)}
                    >
                      <span className="text-xl">📝</span>
                      <span className="font-medium">Blog</span>
                    </Link>
                    <Link
                      href="/media"
                      className="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                      onClick={() => setIsResourcesOpen(false)}
                    >
                      <span className="text-xl">📰</span>
                      <span className="font-medium">Media</span>
                    </Link>
                  </div>
                )}
              </div>
              
              <a href="#contact" className="text-gray-700 hover:text-blue-600 transition-colors">Contact</a>
            </nav>
            
            {/* Mobile Menu Button */}
            <button className="md:hidden text-gray-700">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <div className="container mx-auto px-4 py-12">
        <div className="text-center mb-12">
          <h1 className="text-4xl md:text-5xl font-bold text-blue-900 mb-6">
            <TypedText text="Smart Investment Advisory" className="text-blue-900" />
          </h1>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Expert guidance on Mutual Funds, Asset Allocation, and goal-based investing aligned with SEBI regulations
          </p>
        </div>

        {/* What is Smart Investment Advisory */}
        <div className="bg-white rounded-2xl shadow-xl p-8 mb-12">
          <h2 className="text-3xl font-bold text-blue-800 mb-6">What is Smart Investment Advisory?</h2>
          <p className="text-gray-700 mb-6">
            Smart Investment Advisory goes beyond simple product recommendations. It involves understanding your financial goals, 
            risk appetite, and time horizon to create a personalized investment strategy. As a SEBI-registered mutual fund distributor, 
            we follow rigorous due diligence and disclosure standards to ensure your investments align with your life goals.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="bg-blue-50 p-6 rounded-xl">
              <h3 className="text-xl font-bold text-blue-700 mb-4">Key Benefits</h3>
              <ul className="space-y-3">
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Goal-based investment planning</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>SEBI-compliant advisory process</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Diversified portfolio construction</span>
                </li>
              </ul>
            </div>
            
            <div className="bg-emerald-50 p-6 rounded-xl">
              <h3 className="text-xl font-bold text-emerald-700 mb-4">Our Approach</h3>
              <ul className="space-y-3">
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Personalized risk profiling</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Regular portfolio reviews & rebalancing</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Transparent fee structure</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Investment Options Section */}
        <div className="mb-12">
          <div className="block lg:hidden xl:hidden md:block sm:block mx-2.5">
            <p className="text-justify">
              Investments can be packaged into different products that can enhance, protect or maximise risks and returns of a Portfolio. 
              At {siteConfig.CompanyName} we believe that we can help to get this balance as per needs of each customer. 
              Different asset classes behave in different manner across economic cycles. Our asset allocation model helps customers 
              navigate these economic cycles smoothly thus help them in optimizing returns of their portfolio and achieve their Goals.
            </p>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-12 gap-6 p-2.5">
            <div className="hidden lg:block md:hidden sm:hidden lg:col-span-2">
              &nbsp;
            </div>
            
            <div className="md:col-span-12 lg:col-span-8">
              <div className="flex flex-col md:flex-row gap-6">
                <div className="md:w-1/2">
                  <p className="text-sm md:text-base pt-5 text-justify">
                    With the help of our intelligent asset allocation model we help customers invest in:
                  </p>
                  
                  <ul className="text-sm md:text-base pt-5 text-justify list-none">
                    {[
                      'Mutual Funds (Active & Passive)',
                      'Debt Markets & Bonds',
                      'Domestic Equity Markets',
                      'International Equity Markets',
                      'Real Estate (REITs)',
                      'Gold (Sovereign Gold Bonds, ETFs)'
                    ].map((item, index) => (
                      <li key={index} className="mb-2">
                        <input 
                          type="checkbox" 
                          value={item}
                          className="mr-2"
                          id={`asset-${index}`}
                        />
                        <label htmlFor={`asset-${index}`}>{item}</label>
                      </li>
                    ))}
                  </ul>
                  
                  <span className="block font-bold mt-4">
                    Interested in Smart Investment Advisory? Please tick the above options and Submit
                  </span>
                  
                  <button className="btn btn-primary mt-2.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                    Get Your Investment Plan
                  </button>
                </div>
                
                <div className="md:w-1/2">
                  <div className="relative mt-6 md:mt-0">
                    <img 
                      src={siteConfig.assetAllocationImage} 
                      alt="Smart Investment Advisory - Asset Allocation"
                      className="w-full h-auto rounded-lg shadow-md"
                    />
                  </div>
                </div>
              </div>
            </div>
            
            <div className="hidden lg:block md:hidden sm:hidden lg:col-span-2">
              &nbsp;
            </div>
          </div>
        </div>

        {/* CTA Section */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-2xl p-8 text-center">
          <h2 className="text-3xl font-bold mb-4">Ready to Start Your Investment Journey?</h2>
          <p className="text-blue-100 mb-6 max-w-2xl mx-auto">
            Get a personalized investment plan based on your financial goals and risk profile
          </p>
          <button className="bg-white text-blue-900 px-8 py-3 rounded-lg font-bold hover:bg-blue-50 transition-colors">
            Get Your Investment Plan
          </button>
        </div>
      </div>
    </div>
  );
}