"use client";

import siteConfig from "@/config/siteConfig";
import TypedText from "@/components/TypedText";
import { useRouter } from 'next/navigation';

export default function WealthInvestmentAdvisory() {
  const router = useRouter();

  // Offerings data with explanations
  const offerings = [
    {
      name: "Mutual Funds",
      explanation: "Professionally managed investment portfolios that pool money from multiple investors to invest in diversified assets like stocks, bonds, and other securities. Suitable for retail investors seeking diversification and expert management."
    },
    {
      name: "Corporate FD",
      explanation: "Fixed deposits offered by companies (non-banking financial institutions) that typically provide higher interest rates than bank FDs. Ideal for conservative investors looking for better returns with moderate risk."
    },
    {
      name: "SIF (Special Investment Fund)",
      explanation: "A specialized investment vehicle designed for informed investors seeking exposure to unique asset classes or strategies. Offers tailored portfolio management with specific investment mandates."
    },
    {
      name: "AIF (Alternative Investment Fund)",
      explanation: "Sophisticated investment funds for high-net-worth individuals and institutions, covering private equity, hedge funds, real estate, and venture capital. Regulated by SEBI with minimum investment requirements."
    },
    {
      name: "PMS (Portfolio Management Services)",
      explanation: "Customized investment solutions where professional managers create and manage a personalized portfolio of stocks, ETFs, and other securities based on your risk profile and financial goals."
    },
    {
      name: "Bonds",
      explanation: "Fixed-income instruments where you lend money to governments or corporations in exchange for periodic interest payments and principal repayment at maturity. Offers predictable income with lower volatility than equities."
    },
    {
      name: "International Investments",
      explanation: "Opportunities to invest in global markets including US stocks, emerging markets, international ETFs, and foreign currencies. Helps diversify geographical risk and tap into growth stories beyond India."
    }
  ];

  const handleConsultationClick = () => {
    router.push('/contact');
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white">
      <div className="container mx-auto px-4 py-12">
        {/* Hero Section */}
        <div className="text-center mb-12">
          <h1 className="text-4xl md:text-5xl font-bold text-blue-900 mb-6">
            <TypedText text={siteConfig.offerings.hero.heading} className="text-blue-900" />
          </h1>
          <p className="text-xl text-gray-600 max-w-4xl mx-auto">
            {siteConfig.offerings.hero.subtitle}
          </p>
        </div>

        {/* Services Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
          {siteConfig.offerings.services.map((service, index) => (
            <div key={index} className="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
              <div className="text-4xl mb-4">{service.icon}</div>
              <h3 className="text-xl font-bold text-blue-800 mb-4">{service.title}</h3>
              <ul className="space-y-2">
                {service.features.map((feature, idx) => (
                  <li key={idx} className="flex items-center">
                    <div className="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                    <span className="text-gray-700">{feature}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* New Content: Investment Market Description */}
        <div className="block lg:hidden xl:hidden md:block sm:block mx-2.5 mb-8">
          <p className="text-justify text-gray-700">
            {siteConfig.offerings.description}
          </p>
        </div>

        {/* Our Offerings Section - Updated with 7 products and explanations */}
        <div className="bg-white rounded-2xl shadow-xl p-8 mb-12">
          <div className="grid grid-cols-1 md:grid-cols-12 gap-6">
            <div className="hidden lg:block md:hidden sm:hidden lg:col-span-2">
              &nbsp;
            </div>
            
            <div className="md:col-span-12 lg:col-span-8">
              <h5 className="text-3xl md:text-4xl font-bold text-[#04047e] text-center mb-8">
                {siteConfig.offerings.ourOfferingsHeading}
              </h5>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {offerings.map((offering, index) => (
                  <div key={index} className="border border-blue-100 rounded-xl p-5 hover:shadow-md transition-shadow">
                    <h6 className="text-xl font-bold text-blue-800 mb-2 flex items-center">
                      <span className="text-blue-500 mr-2">◆</span>
                      {offering.name}
                    </h6>
                    <p className="text-gray-600 text-sm leading-relaxed">
                      {offering.explanation}
                    </p>
                  </div>
                ))}
              </div>
            </div>
            
            <div className="hidden lg:block md:hidden sm:hidden lg:col-span-2">
              &nbsp;
            </div>
          </div>
        </div>

        {/* Why Choose Us */}
        <div className="bg-gradient-to-r from-blue-100 to-blue-50 rounded-2xl p-8 mb-12">
          <h2 className="text-3xl font-bold text-blue-900 mb-6">{siteConfig.offerings.whyChooseHeading.replace('{siteName}', siteConfig.siteName)}</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-xl font-bold text-blue-700 mb-4">For Individuals</h3>
              <ul className="space-y-3">
                <li className="flex items-center">
                  <span className="text-green-500 mr-3">✓</span>
                  <span>Purpose Planning</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-3">✓</span>
                  <span>Access to diversified investment options</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-3">✓</span>
                  <span>Regular portfolio reviews & rebalancing</span>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="text-xl font-bold text-blue-700 mb-4">For Businesses</h3>
              <ul className="space-y-3">
                <li className="flex items-center">
                  <span className="text-green-500 mr-3">✓</span>
                  <span>Cash Management Options</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-3">✓</span>
                  <span>Regular Market insights</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-3">✓</span>
                  <span>Presentation to Board.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* CTA Section */}
        <div className="text-center">
          <h2 className="text-3xl font-bold text-blue-900 mb-4">Ready to Grow Your Wealth?</h2>
          <p className="text-gray-600 mb-8 max-w-2xl mx-auto">
            Get personalized investment solutions tailored to your financial goals
          </p>
          <button 
            onClick={handleConsultationClick}
            className="bg-blue-900 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-800 transition-colors"
          >
            Get started with LITM
          </button>
        </div>
      </div>
    </div>
  );
}