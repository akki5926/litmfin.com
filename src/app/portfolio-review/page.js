'use client';

import { useState } from 'react';
import TypedText from '@/components/TypedText';

export default function PortfolioReviewPage() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    currentPortfolio: '',
    investmentGoals: '',
    riskAppetite: 'medium'
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    alert('Portfolio review request submitted successfully! Our advisor will contact you within 24 hours.');
    setFormData({
      name: '',
      email: '',
      phone: '',
      currentPortfolio: '',
      investmentGoals: '',
      riskAppetite: 'medium'
    });
  };

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Portfolio Review" className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Get expert analysis of your investment portfolio</p>
        </div>
        
        <div className="grid md:grid-cols-2 gap-8">
          {/* Form */}
          <div className="bg-white rounded-2xl shadow-lg p-8">
            <h2 className="text-2xl font-bold text-gray-900 mb-6">Request Free Portfolio Review</h2>
            
            <form onSubmit={handleSubmit} className="space-y-6">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Full Name *
                </label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Enter your full name"
                />
              </div>
              
              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Email *
                  </label>
                  <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleChange}
                    required
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="your@email.com"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Phone Number *
                  </label>
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleChange}
                    required
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="+91 9876543210"
                  />
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Current Portfolio Value (₹)
                </label>
                <input
                  type="text"
                  name="currentPortfolio"
                  value={formData.currentPortfolio}
                  onChange={handleChange}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., 10,00,000"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Investment Goals
                </label>
                <textarea
                  name="investmentGoals"
                  value={formData.investmentGoals}
                  onChange={handleChange}
                  rows="3"
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g., Retirement planning, children's education, buying a house..."
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Risk Appetite
                </label>
                <select
                  name="riskAppetite"
                  value={formData.riskAppetite}
                  onChange={handleChange}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="low">Low (Conservative)</option>
                  <option value="medium">Medium (Balanced)</option>
                  <option value="high">High (Aggressive)</option>
                </select>
              </div>
              
              <button
                type="submit"
                className="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors"
              >
                Submit for Review
              </button>
            </form>
          </div>
          
          {/* Benefits */}
          <div className="bg-white rounded-2xl shadow-lg p-8">
            <h2 className="text-2xl font-bold text-gray-900 mb-6">What You Get</h2>
            
            <div className="space-y-6">
              <div className="flex items-start">
                <div className="bg-blue-100 p-3 rounded-lg mr-4">
                  <span className="text-blue-600 font-bold text-xl">1</span>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-1">Comprehensive Analysis</h3>
                  <p className="text-gray-600">Detailed review of your current asset allocation and performance</p>
                </div>
              </div>
              
              <div className="flex items-start">
                <div className="bg-green-100 p-3 rounded-lg mr-4">
                  <span className="text-green-600 font-bold text-xl">2</span>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-1">Personalized Recommendations</h3>
                  <p className="text-gray-600">Customized suggestions based on your goals and risk profile</p>
                </div>
              </div>
              
              <div className="flex items-start">
                <div className="bg-purple-100 p-3 rounded-lg mr-4">
                  <span className="text-purple-600 font-bold text-xl">3</span>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-1">Tax Optimization</h3>
                  <p className="text-gray-600">Strategies to minimize tax liability on your investments</p>
                </div>
              </div>
              
              <div className="flex items-start">
                <div className="bg-orange-100 p-3 rounded-lg mr-4">
                  <span className="text-orange-600 font-bold text-xl">4</span>
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-1">Rebalancing Plan</h3>
                  <p className="text-gray-600">Actionable steps to optimize your portfolio allocation</p>
                </div>
              </div>
              
              <div className="mt-8 bg-blue-50 p-6 rounded-lg">
                <h3 className="font-bold text-blue-800 mb-2">Free for First Time Users!</h3>
                <p className="text-blue-700">
                  Get your first portfolio review absolutely free. No hidden charges, no commitments.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Process Section */}
        <div className="mt-12 bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-8 text-center">Our Portfolio Review Process</h2>
          
          <div className="grid md:grid-cols-4 gap-6">
            <div className="text-center">
              <div className="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-blue-600 text-xl font-bold">1</span>
              </div>
              <h3 className="font-semibold text-gray-900 mb-2">Submit Request</h3>
              <p className="text-sm text-gray-600">Fill out the form with your portfolio details</p>
            </div>
            
            <div className="text-center">
              <div className="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-green-600 text-xl font-bold">2</span>
              </div>
              <h3 className="font-semibold text-gray-900 mb-2">Expert Analysis</h3>
              <p className="text-sm text-gray-600">Our certified analysts review your portfolio</p>
            </div>
            
            <div className="text-center">
              <div className="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-purple-600 text-xl font-bold">3</span>
              </div>
              <h3 className="font-semibold text-gray-900 mb-2">Personalized Report</h3>
              <p className="text-sm text-gray-600">Receive detailed recommendations report</p>
            </div>
            
            <div className="text-center">
              <div className="bg-orange-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-orange-600 text-xl font-bold">4</span>
              </div>
              <h3 className="font-semibold text-gray-900 mb-2">Follow-up Consultation</h3>
              <p className="text-sm text-gray-600">Discuss recommendations with our expert</p>
            </div>
          </div>
        </div>

        {/* FAQ Section */}
        <div className="mt-12 bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-6">Frequently Asked Questions</h2>
          
          <div className="space-y-6">
            <div className="border-b border-gray-200 pb-6">
              <h3 className="font-semibold text-gray-900 mb-2">How long does the portfolio review take?</h3>
              <p className="text-gray-600">Our team typically completes the analysis within 3-5 business days after receiving all necessary information.</p>
            </div>
            
            <div className="border-b border-gray-200 pb-6">
              <h3 className="font-semibold text-gray-900 mb-2">What information do I need to provide?</h3>
              <p className="text-gray-600">You&apos;ll need details about your current investments, financial goals, risk tolerance, and any specific concerns you have about your portfolio.</p>
            </div>
            
            <div className="border-b border-gray-200 pb-6">
              <h3 className="font-semibold text-gray-900 mb-2">Is the review really free?</h3>
              <p className="text-gray-600">Yes, your first portfolio review is absolutely free with no obligation to use our paid services.</p>
            </div>
            
            <div>
              <h3 className="font-semibold text-gray-900 mb-2">Who will review my portfolio?</h3>
              <p className="text-gray-600">Your portfolio will be reviewed by our team of certified financial planners and investment analysts with extensive experience.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}