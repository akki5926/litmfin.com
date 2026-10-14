/* eslint-disable @next/next/no-img-element */
'use client';

import siteConfig from '@/config/siteConfig';
import TypedText from '@/components/TypedText';
import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function AssetAllocation() {
  const router = useRouter();
  const [selectedAsset, setSelectedAsset] = useState('');

  const handleGetAllocationPlan = () => {
    if (!selectedAsset) {
      alert('Please select an asset class first');
      return;
    }
    // Navigate to contact page with the selected option as query parameter
    router.push(`/contact?subject=${encodeURIComponent(selectedAsset)}`);
  };

  const handleDirectContact = () => {
    // Navigate directly to contact page without any subject
    router.push('/contact');
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
      <div className="container mx-auto px-4 py-12">
        {/* Hero Section */}
        <div className="text-center mb-12">
          <h1 className="text-4xl md:text-5xl font-bold text-blue-900 mb-6">
            <TypedText text="Smart Asset Diversification" className="text-blue-900" />
          </h1>
          <p className="text-xl text-gray-600 max-w-3xl mx-auto">
            Build a diversified portfolio suiting your financial goals and risk tolerance.
          </p>
        </div>

        {/* What is Asset Diversification */}
        <div className="bg-white rounded-2xl shadow-xl p-8 mb-12">
          <h2 className="text-3xl font-bold text-blue-800 mb-6">What is Asset Diversification?</h2>
          <p className="text-gray-700 mb-6">
            Asset Diversification is the strategic distribution of your investments across different asset classes 
            like equity, debt, gold, and real estate. It&apos;s the most important factor determining your 
            portfolio&apos;s risk and return profile.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="bg-blue-50 p-6 rounded-xl">
              <h3 className="text-xl font-bold text-blue-700 mb-4">Key Benefits</h3>
              <ul className="space-y-3">
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Reduces portfolio volatility</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Maximizes returns for your risk level</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Aligns investments with financial goals</span>
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
                  <span>Personalized risk assessment</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Goal-based allocation strategy</span>
                </li>
                <li className="flex items-start">
                  <div className="w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center mr-3 mt-1">
                    <span className="text-white text-sm">✓</span>
                  </div>
                  <span>Regular portfolio rebalancing</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* New Content Section */}
      <div className="mb-12">
        <div className="block lg:hidden xl:hidden md:block sm:block mx-2.5">
          <p className="text-justify">
            Investments can be packaged into different products that can enhance, protect or maximise risks and returns of a Portfolio. At {siteConfig.CompanyName} we believe that we can help to get this balance as per needs of each customer. Different asset classes behave in different manner across economic cycles. Our Asset allocation model helps customers navigate these economic cycles smoothly thus help them in optimizing returns of their portfolio and achieve their Goals.
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
                  Our vast distribution capability helps client invest in:
                </p>
                
                <ul className="text-sm md:text-base pt-5 text-justify list-none">
                  {['Debt Markets', 'Domestic Equity Markets', 'International Equity Markets', 'Property', 'Gold'].map((item, index) => (
                    <li key={index} className="mb-2">
                      <input 
                        type="radio" 
                        name="assetClass"
                        value={item}
                        checked={selectedAsset === item}
                        onChange={(e) => setSelectedAsset(e.target.value)}
                        className="mr-2"
                        id={`asset-${index}`}
                      />
                      <label htmlFor={`asset-${index}`}>{item}</label>
                    </li>
                  ))}
                </ul>
                
                <span className="block font-bold mt-4">
                  Interested? Please select one option above:
                </span>
                
                <button 
                  onClick={handleGetAllocationPlan}
                  className="btn btn-primary mt-2.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
                >
                   Get Your Plan
                </button>
              </div>
              
              <div className="md:w-1/2">
                <div className="relative mt-6 md:mt-0">
                  <img 
                    src={siteConfig.branding.assetAllocationImage} 
                    alt="Asset Allocation"
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
          <h2 className="text-3xl font-bold mb-4">Ready to Optimize Your Portfolio?</h2>
          <p className="text-blue-100 mb-6 max-w-2xl mx-auto">
            Get a personalized asset allocation plan based on your financial goals and risk profile
          </p>
          <button 
            onClick={handleDirectContact}
            className="bg-white text-blue-900 px-8 py-3 rounded-lg font-bold hover:bg-blue-50 transition-colors"
          >
            Get Your Plan
          </button>
        </div>
      </div>
    </div>
  );
}