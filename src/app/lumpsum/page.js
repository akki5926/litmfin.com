'use client';

import { useState, useEffect } from 'react';
import TypedText from '@/components/TypedText';

// Function to format numbers in Indian style (1,50,50,500)
const formatIndianNumber = (num) => {
  if (isNaN(num)) return '0';
  
  const number = Math.round(num);
  const sign = number < 0 ? '-' : '';
  const absNumber = Math.abs(number);
  
  if (absNumber === 0) return '0';
  
  const numStr = absNumber.toString();
  const lastThree = numStr.substring(numStr.length - 3);
  const otherNumbers = numStr.substring(0, numStr.length - 3);
  
  if (otherNumbers !== '') {
    const formatted = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + "," + lastThree;
    return sign + formatted;
  } else {
    return sign + lastThree;
  }
};

export default function LumpsumPage() {
  const [lumpsumAmount, setLumpsumAmount] = useState(1000000);
  const [stpAmount, setStpAmount] = useState(50000);
  const [stpMonths, setStpMonths] = useState(12);
  const [expectedReturn, setExpectedReturn] = useState(12);
  const [showSTPCalculator, setShowSTPCalculator] = useState(false);

  const formatCurrency = (num) => {
    return `₹${formatIndianNumber(num)}`;
  };

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Lumpsum Investment" className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Make one-time investments with Systematic Transfer Plan (STP) option</p>
        </div>
        
        <div className="bg-white rounded-2xl shadow-lg p-8 mb-8">
          <div className="grid md:grid-cols-2 gap-8">
            <div>
              <h2 className="text-2xl font-bold text-gray-900 mb-4">What is Lumpsum Investment?</h2>
              <p className="text-gray-700 mb-4">
                A lumpsum investment involves investing a significant amount of money at once, 
                as opposed to spreading it over time through SIPs. This approach can be beneficial 
                when you have a large corpus available for investment.
              </p>
              
              <div className="mb-6">
                <h3 className="text-xl font-semibold text-gray-900 mb-3">When to Choose Lumpsum:</h3>
                <ul className="space-y-2 text-gray-700">
                  <li className="flex items-start">
                    <span className="text-green-500 mr-2">•</span>
                    <span>When you receive a bonus or windfall</span>
                  </li>
                  <li className="flex items-start">
                    <span className="text-green-500 mr-2">•</span>
                    <span>During market corrections</span>
                  </li>
                  <li className="flex items-start">
                    <span className="text-green-500 mr-2">•</span>
                    <span>For short-term financial goals</span>
                  </li>
                  <li className="flex items-start">
                    <span className="text-green-500 mr-2">•</span>
                    <span>When you have idle funds</span>
                  </li>
                </ul>
              </div>

              {/* STP Toggle */}
              <div className="mt-6 p-4 bg-blue-50 rounded-lg">
                <div className="flex items-center justify-between mb-2">
                  <h3 className="font-semibold text-blue-800">Avoid Market Volatility with STP</h3>
                  <button
                    onClick={() => setShowSTPCalculator(!showSTPCalculator)}
                    className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                  >
                    {showSTPCalculator ? 'Hide STP Calculator' : 'Show STP Calculator'}
                  </button>
                </div>
                <p className="text-blue-700 text-sm">
                  Convert your lumpsum investment into Systematic Transfer Plan (STP) to reduce 
                  market timing risk and benefit from rupee cost averaging.
                </p>
              </div>
            </div>
            
            <div>
              <h2 className="text-2xl font-bold text-gray-900 mb-4">Benefits</h2>
              <div className="space-y-4">
                <div className="bg-blue-50 p-4 rounded-lg">
                  <h3 className="font-semibold text-blue-800 mb-1">Higher Potential Returns</h3>
                  <p className="text-blue-700 text-sm">Get the benefit of compounding on the entire amount from day one</p>
                </div>
                
                <div className="bg-green-50 p-4 rounded-lg">
                  <h3 className="font-semibold text-green-800 mb-1">Time in Market</h3>
                  <p className="text-green-700 text-sm">Your entire investment starts working for you immediately</p>
                </div>
                
                <div className="bg-purple-50 p-4 rounded-lg">
                  <h3 className="font-semibold text-purple-800 mb-1">Simplicity</h3>
                  <p className="text-purple-700 text-sm">One-time transaction, no recurring commitments</p>
                </div>
              </div>
              
              <div className="mt-8">
                <button className="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                  Start Lumpsum Investment
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* STP Calculator Section */}
        {showSTPCalculator && (
          <div className="bg-white rounded-2xl shadow-lg p-8 mb-8 border-2 border-blue-200">
            <div className="flex items-center mb-6">
              <div className="bg-blue-100 p-2 rounded-lg mr-3">
                <span className="text-blue-600 font-bold">STP</span>
              </div>
              <h2 className="text-2xl font-bold text-gray-900">Systematic Transfer Plan Calculator</h2>
            </div>
            
            <p className="text-gray-600 mb-6">
              STP allows you to transfer a fixed amount from one mutual fund scheme to another at regular intervals. 
              This helps in reducing market timing risk while deploying a large corpus.
            </p>
            
            <div className="grid md:grid-cols-2 gap-8">
              {/* STP Input Form */}
              <div className="space-y-6">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Lumpsum Amount
                  </label>
                  <div className="flex items-center">
                    <span className="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-4 py-3 text-gray-700">
                      ₹
                    </span>
                    <input
                      type="text"
                      value={formatIndianNumber(lumpsumAmount)}
                      onChange={(e) => {
                        const value = e.target.value.replace(/,/g, '');
                        if (!isNaN(value)) setLumpsumAmount(Number(value));
                      }}
                      className="w-full px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      placeholder="Enter lumpsum amount"
                    />
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Monthly Transfer Amount
                  </label>
                  <div className="flex items-center space-x-4">
                    <input
                      type="range"
                      min="5000"
                      max="500000"
                      step="5000"
                      value={stpAmount}
                      onChange={(e) => setStpAmount(Number(e.target.value))}
                      className="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                    />
                    <span className="text-lg font-semibold w-24 text-right">{formatCurrency(stpAmount)}</span>
                  </div>
                  <div className="flex justify-between text-xs text-gray-500 mt-2">
                    <span>₹5,000</span>
                    <span>₹5,00,000</span>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Transfer Period (Months)
                  </label>
                  <div className="flex items-center space-x-4">
                    <input
                      type="range"
                      min="3"
                      max="36"
                      value={stpMonths}
                      onChange={(e) => setStpMonths(Number(e.target.value))}
                      className="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                    />
                    <span className="text-lg font-semibold w-16 text-right">{stpMonths} months</span>
                  </div>
                  <div className="flex justify-between text-xs text-gray-500 mt-2">
                    <span>3 months</span>
                    <span>36 months</span>
                  </div>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Expected Annual Return (%)
                  </label>
                  <div className="flex items-center space-x-4">
                    <input
                      type="range"
                      min="1"
                      max="20"
                      value={expectedReturn}
                      onChange={(e) => setExpectedReturn(Number(e.target.value))}
                      className="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                    />
                    <span className="text-lg font-semibold w-16 text-right">{expectedReturn}%</span>
                  </div>
                  <div className="flex justify-between text-xs text-gray-500 mt-2">
                    <span>1%</span>
                    <span>20%</span>
                  </div>
                </div>
              </div>
              
              {/* STP Results */}
              <div className="bg-gray-50 rounded-xl p-6">
                <h3 className="font-bold text-gray-900 mb-4">STP Plan Details</h3>
                
                <div className="space-y-4">
                  <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span className="text-gray-600">Lumpsum Amount</span>
                    <span className="font-semibold text-gray-900">{formatCurrency(lumpsumAmount)}</span>
                  </div>
                  
                  <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span className="text-gray-600">Monthly Transfer</span>
                    <span className="font-semibold text-gray-900">{formatCurrency(stpAmount)}</span>
                  </div>
                  
                  <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span className="text-gray-600">Transfer Period</span>
                    <span className="font-semibold text-gray-900">{stpMonths} months</span>
                  </div>
                  
                  <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                    <span className="text-gray-600">Total Transfers</span>
                    <span className="font-semibold text-gray-900">{formatCurrency(stpAmount * stpMonths)}</span>
                  </div>
                  
                  <div className="flex justify-between items-center">
                    <span className="text-gray-600">Remaining after STP</span>
                    <span className="font-semibold text-gray-900">
                      {formatCurrency(Math.max(0, lumpsumAmount - (stpAmount * stpMonths)))}
                    </span>
                  </div>
                </div>
                
                <div className="mt-6 p-4 bg-blue-50 rounded-lg">
                  <h4 className="font-semibold text-blue-800 mb-2">How STP Works:</h4>
                  <ul className="text-sm text-blue-700 space-y-1">
                    <li>• Invest lumpsum in a debt/liquid fund</li>
                    <li>• Set up automatic monthly transfers to equity fund</li>
                    <li>• Reduces market timing risk</li>
                    <li>• Benefit from rupee cost averaging</li>
                  </ul>
                </div>
                
                <button className="w-full mt-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                  Set Up STP Plan
                </button>
              </div>
            </div>
            
            {/* STP Benefits Section */}
            <div className="mt-8 pt-6 border-t border-gray-200">
              <h3 className="text-xl font-bold text-gray-900 mb-4">Benefits of STP over Lumpsum</h3>
              <div className="grid md:grid-cols-3 gap-4">
                <div className="bg-green-50 p-4 rounded-lg">
                  <div className="text-green-600 text-2xl mb-2">🛡️</div>
                  <h4 className="font-semibold text-green-800 mb-1">Risk Reduction</h4>
                  <p className="text-green-700 text-sm">Reduces market timing risk by spreading investment over time</p>
                </div>
                
                <div className="bg-blue-50 p-4 rounded-lg">
                  <div className="text-blue-600 text-2xl mb-2">📊</div>
                  <h4 className="font-semibold text-blue-800 mb-1">Rupee Cost Averaging</h4>
                  <p className="text-blue-700 text-sm">Buys more units when prices are low, fewer when high</p>
                </div>
                
                <div className="bg-purple-50 p-4 rounded-lg">
                  <div className="text-purple-600 text-2xl mb-2">⚡</div>
                  <h4 className="font-semibold text-purple-800 mb-1">Flexibility</h4>
                  <p className="text-purple-700 text-sm">Choose transfer frequency and amount as per your needs</p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Investment Options */}
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-6">Popular Lumpsum Investment Options</h2>
          
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="bg-gray-50">
                  <th className="py-3 px-4 text-left text-sm font-semibold text-gray-900">Investment Option</th>
                  <th className="py-3 px-4 text-left text-sm font-semibold text-gray-900">Minimum Investment</th>
                  <th className="py-3 px-4 text-left text-sm font-semibold text-gray-900">Lock-in Period</th>
                  <th className="py-3 px-4 text-left text-sm font-semibold text-gray-900">Risk Level</th>
                  <th className="py-3 px-4 text-left text-sm font-semibold text-gray-900">STP Available</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="py-3 px-4">
                    <div className="font-medium text-gray-900">Equity Mutual Funds</div>
                    <div className="text-sm text-gray-500">For long-term wealth creation</div>
                  </td>
                  <td className="py-3 px-4">₹5,000</td>
                  <td className="py-3 px-4">None (ELSS: 3 years)</td>
                  <td className="py-3 px-4">
                    <span className="inline-block px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded">High</span>
                  </td>
                  <td className="py-3 px-4 text-green-600 font-medium">Yes</td>
                </tr>
                <tr>
                  <td className="py-3 px-4">
                    <div className="font-medium text-gray-900">Debt Mutual Funds</div>
                    <div className="text-sm text-gray-500">For stable returns</div>
                  </td>
                  <td className="py-3 px-4">₹5,000</td>
                  <td className="py-3 px-4">None</td>
                  <td className="py-3 px-4">
                    <span className="inline-block px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">Medium</span>
                  </td>
                  <td className="py-3 px-4 text-green-600 font-medium">Yes</td>
                </tr>
                <tr>
                  <td className="py-3 px-4">
                    <div className="font-medium text-gray-900">Hybrid Funds</div>
                    <div className="text-sm text-gray-500">Balance of equity & debt</div>
                  </td>
                  <td className="py-3 px-4">₹5,000</td>
                  <td className="py-3 px-4">None</td>
                  <td className="py-3 px-4">
                    <span className="inline-block px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">Medium</span>
                  </td>
                  <td className="py-3 px-4 text-green-600 font-medium">Yes</td>
                </tr>
                <tr>
                  <td className="py-3 px-4">
                    <div className="font-medium text-gray-900">Liquid Funds</div>
                    <div className="text-sm text-gray-500">For short-term parking</div>
                  </td>
                  <td className="py-3 px-4">₹5,000</td>
                  <td className="py-3 px-4">None</td>
                  <td className="py-3 px-4">
                    <span className="inline-block px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Low</span>
                  </td>
                  <td className="py-3 px-4 text-green-600 font-medium">Yes</td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div className="mt-8 p-6 bg-blue-50 rounded-lg">
            <div className="flex items-start">
              <div className="text-blue-500 mr-3">💡</div>
              <div>
                <h3 className="font-semibold text-blue-800 mb-2">Professional Advice:</h3>
                <p className="text-blue-700">
                  If you have a large lumpsum amount to invest, consider starting with a debt/liquid fund 
                  and setting up an STP to equity funds. This strategy helps reduce market timing risk 
                  while ensuring your money remains invested and earns returns during the transfer period.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}