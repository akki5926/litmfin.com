'use client';

import siteConfig from '@/config/siteConfig';
import TypedText from '@/components/TypedText';
import { useRouter } from 'next/navigation';

export default function PmsPage() {
  const router = useRouter();

  const handleEnquire = () => {
    router.push(`/contact?subject=${encodeURIComponent('Portfolio Management Services (PMS)')}`);
  };

  const pmsHighlights = [
    {
      title: 'Minimum Investment',
      value: '₹50 Lacs',
      description: 'SEBI-mandated threshold for high-net-worth individual & institutional investors.',
      icon: (
        <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Lock-in Period',
      value: 'No Lock-ins',
      description: 'Flexibility to exit as needed, subject to defined exit load tiers.',
      icon: (
        <svg className="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
        </svg>
      ),
    },
    {
      title: 'Asset Ownership',
      value: 'In Investor Name',
      description: 'All shares, bonds, & units are directly registered in the investor’s Demat account.',
      icon: (
        <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
      ),
    },
    {
      title: 'Fund Execution',
      value: 'Direct Demat Trading',
      description: 'Fund Manager buys/sells Stocks, Bonds, & Mutual Funds from investor’s account.',
      icon: (
        <svg className="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
      ),
    },
    {
      title: 'Taxation Structure',
      value: 'Investor Level',
      description: 'Tax is computed directly on individual transactions at the investor level.',
      icon: (
        <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 14l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
    },
    {
      title: 'Regulatory Oversight',
      value: 'SEBI Regulated',
      description: 'Governed strictly by SEBI & APMI rules ensuring high governance & transparency.',
      icon: (
        <svg className="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      ),
    },
  ];

  const exitLoadStructure = [
    { year: 'Year 1', load: 'Maximum 3%', note: 'Applied on redemption within the 1st year of allocation.' },
    { year: 'Year 2', load: 'Maximum 2%', note: 'Applied on redemption within the 2nd year of allocation.' },
    { year: 'Year 3', load: 'Maximum 1%', note: 'Applied on redemption within the 3rd year of allocation.' },
    { year: 'After Year 3', load: '0% (Nil)', note: 'No exit load charged for redemptions after 3 years.' },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 via-white to-gray-50">
      <div className="container mx-auto px-4 py-12">
        {/* Back Navigation Button */}
        <div className="mb-6">
          <button
            onClick={() => router.back()}
            className="flex items-center text-gray-600 hover:text-blue-600 font-medium transition-colors"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </button>
        </div>

        {/* Hero Section */}
        <div className="text-center mb-14">
          <span className="inline-block px-4 py-1.5 bg-blue-100 text-blue-800 text-sm font-semibold rounded-full mb-4 shadow-sm">
            SEBI Regulated Specialized Wealth Solution
          </span>
          <h1 className="text-4xl md:text-5xl font-bold text-blue-950 mb-6 tracking-tight">
            <TypedText text="Portfolio Management Services (PMS)" className="text-blue-900" />
          </h1>
          <p className="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
            Portfolio Management Services (PMS) is a SEBI-regulated investment service in which a registered portfolio manager manages an investor’s funds in accordance with a clearly defined strategy.
          </p>

          <div className="mt-8 flex justify-center gap-4">
            <button
              onClick={handleEnquire}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5"
            >
              Enquire & Schedule Advisory
            </button>
          </div>
        </div>

        {/* Highlight Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
          {pmsHighlights.map((feature, idx) => (
            <div
              key={idx}
              className="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transition-all duration-300 border border-gray-100 flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center justify-between mb-4">
                  <span className="p-3 bg-blue-50 rounded-xl">{feature.icon}</span>
                  <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Feature #{idx + 1}</span>
                </div>
                <h3 className="text-sm font-medium text-gray-500 mb-1">{feature.title}</h3>
                <p className="text-2xl font-bold text-gray-900 mb-3">{feature.value}</p>
                <p className="text-sm text-gray-600">{feature.description}</p>
              </div>
            </div>
          ))}
        </div>

        {/* Detailed Core Principles Section */}
        <div className="bg-white rounded-3xl shadow-xl p-8 md:p-12 mb-16 border border-gray-100">
          <div className="max-w-3xl mb-8">
            <h2 className="text-3xl font-bold text-blue-900 mb-4">Key Operational Mechanics</h2>
            <p className="text-gray-600 text-base">
              PMS offers tailored, transparent, and direct ownership of assets managed by expert fund managers.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="bg-gradient-to-br from-blue-50 to-indigo-50/40 p-6 rounded-2xl border border-blue-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-blue-600 text-white rounded-xl flex items-center justify-center font-bold">
                  💼
                </div>
                <h3 className="text-xl font-bold text-blue-900">Direct Ownership & Execution</h3>
              </div>
              <ul className="space-y-3 text-gray-700 text-sm">
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Investments in Investor Name:</strong> All securities are directly held in your own Demat account, ensuring complete ownership.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Multi-Asset Capabilities:</strong> Fund Manager buys and sells <strong>Stocks, Bonds, and Mutual Funds</strong> directly in your account.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-600 font-bold mr-2">•</span>
                  <span><strong>Individual Capital Gains Tax:</strong> Taxation is calculated at the individual investor level, providing full tax visibility and optimization.</span>
                </li>
              </ul>
            </div>

            <div className="bg-gradient-to-br from-emerald-50 to-teal-50/40 p-6 rounded-2xl border border-emerald-100">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 bg-emerald-600 text-white rounded-xl flex items-center justify-center font-bold">
                  💰
                </div>
                <h3 className="text-xl font-bold text-emerald-900">Minimum Capital & Liquidity</h3>
              </div>
              <ul className="space-y-3 text-gray-700 text-sm">
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>Minimum Ticket Size:</strong> ₹50 Lacs as prescribed by SEBI guidelines for Portfolio Management Services.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>No Lock-ins:</strong> Investors enjoy flexibility with no mandatory lock-in period.</span>
                </li>
                <li className="flex items-start">
                  <span className="text-emerald-600 font-bold mr-2">•</span>
                  <span><strong>Transparent Tiers:</strong> Graduated exit load structure incentivizing long-term wealth creation.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Exit Load Schedule Grid */}
        <div className="bg-gradient-to-r from-slate-900 to-blue-950 text-white rounded-3xl p-8 md:p-12 mb-16 shadow-2xl">
          <div className="mb-8">
            <span className="px-3 py-1 bg-amber-400 text-gray-900 text-xs font-bold uppercase rounded-md tracking-wider">
              Exit Load Structure
            </span>
            <h2 className="text-3xl font-bold mt-2">No Lock-ins with Graduated Exit Loads</h2>
            <p className="text-blue-200 mt-1 max-w-2xl">
              While there are no compulsory lock-ins, standard SEBI exit loads apply during the initial 3 years of investment.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {exitLoadStructure.map((item, index) => (
              <div key={index} className="bg-white/10 backdrop-blur-md border border-white/15 p-6 rounded-2xl">
                <div className="text-amber-400 font-bold text-sm mb-1 uppercase tracking-wider">{item.year}</div>
                <div className="text-2xl font-bold text-white mb-2">{item.load}</div>
                <p className="text-xs text-gray-300 leading-relaxed">{item.note}</p>
              </div>
            ))}
          </div>
        </div>

        {/* CTA Banner */}
        <div className="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-3xl p-8 md:p-12 text-center shadow-xl">
          <h2 className="text-3xl font-bold mb-4">Interested in Professional Portfolio Management?</h2>
          <p className="text-blue-100 mb-8 max-w-2xl mx-auto text-base">
            Consult with our registered PMS advisors at {siteConfig.CompanyName} to build a high-conviction, custom-tailored investment strategy.
          </p>
          <button
            onClick={handleEnquire}
            className="bg-white text-blue-900 font-bold px-8 py-3.5 rounded-xl hover:bg-blue-50 transition-colors shadow-lg"
          >
            Schedule Consultation / Enquire
          </button>
        </div>
      </div>
    </div>
  );
}
