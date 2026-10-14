'use client';

import { useState, useEffect } from 'react';
import TypedText from '@/components/TypedText';
import { PieChart, Pie, Cell, ResponsiveContainer, Legend, Tooltip } from 'recharts';

// Function to format numbers in Indian style (1,50,50,500)
const formatIndianNumber = (num) => {
  if (isNaN(num)) return '0';
  
  // Convert to integer first
  const number = Math.round(num);
  
  // Handle negative numbers
  const sign = number < 0 ? '-' : '';
  const absNumber = Math.abs(number);
  
  if (absNumber === 0) return '0';
  
  // Convert to string and split into integer and decimal parts
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

export default function SIPCalculatorPage() {
  const [monthlyInvestment, setMonthlyInvestment] = useState(15000);
  const [investmentPeriod, setInvestmentPeriod] = useState(15);
  const [expectedReturn, setExpectedReturn] = useState(15);
  const [result, setResult] = useState(null);
  const [chartData, setChartData] = useState([]);

  // Calculate SIP on component mount and when values change
  useEffect(() => {
    const calculateSIP = () => {
      const monthlyRate = expectedReturn / 100 / 12;
      const months = investmentPeriod * 12;
      
      // SIP formula: FV = P × [ (1 + i)^n - 1 ] / i × (1 + i)
      const futureValue = monthlyInvestment * 
        ((Math.pow(1 + monthlyRate, months) - 1) / monthlyRate) * 
        (1 + monthlyRate);
      
      const totalInvestment = monthlyInvestment * months;
      const estimatedReturns = futureValue - totalInvestment;
      
      const newResult = {
        investedAmount: totalInvestment,
        estimatedReturns: estimatedReturns,
        totalValue: futureValue
      };
      
      setResult(newResult);
      
      // Prepare chart data
      setChartData([
        { name: 'Invested Amount', value: totalInvestment, color: '#3b82f6' }, // blue-500
        { name: 'Estimated Returns', value: estimatedReturns, color: '#10b981' }, // green-500
      ]);
    };

    calculateSIP();
  }, [monthlyInvestment, investmentPeriod, expectedReturn]);

  // Format with ₹ symbol and Indian numbering
  const formatCurrency = (num) => {
    return `₹${formatIndianNumber(num)}`;
  };

  // Custom tooltip for pie chart
  const CustomTooltip = ({ active, payload }) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-white p-3 border border-gray-200 rounded-lg shadow-lg">
          <p className="font-semibold text-gray-900">{payload[0].name}</p>
          <p className="text-sm text-gray-600">{formatCurrency(payload[0].value)}</p>
          <p className="text-xs text-gray-500">
            {((payload[0].value / result.totalValue) * 100).toFixed(1)}% of total
          </p>
        </div>
      );
    }
    return null;
  };

  // Custom legend for pie chart
  const renderCustomizedLabel = ({ cx, cy, midAngle, innerRadius, outerRadius, percent, index }) => {
    const RADIAN = Math.PI / 180;
    const radius = innerRadius + (outerRadius - innerRadius) * 0.2;
    const x = cx + radius * Math.cos(-midAngle * RADIAN);
    const y = cy + radius * Math.sin(-midAngle * RADIAN);

    return (
      <text
        x={x}
        y={y}
        fill="white"
        textAnchor={x > cx ? 'start' : 'end'}
        dominantBaseline="central"
        className="text-sm font-semibold"
      >
        {`${(percent * 100).toFixed(0)}%`}
      </text>
    );
  };

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="SIP Calculator" className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Calculate your potential returns from Systematic Investment Plans</p>
        </div>
        
        <div className="grid md:grid-cols-2 gap-8">
          {/* Calculate Your SIP - LEFT SIDE */}
          <div className="bg-white rounded-2xl shadow-lg p-8">
            <h2 className="text-2xl font-bold text-gray-900 mb-6">Calculate Your SIP</h2>
            
            <div className="space-y-8">
              {/* Monthly Investment */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <label className="block text-sm font-medium text-gray-700">
                    Monthly Investment
                  </label>
                  <div className="text-lg font-semibold text-blue-600">{formatCurrency(monthlyInvestment)}</div>
                </div>
                <input
                  type="range"
                  min="500"
                  max="100000"
                  step="500"
                  value={monthlyInvestment}
                  onChange={(e) => setMonthlyInvestment(Number(e.target.value))}
                  className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-600 [&::-webkit-slider-thumb]:cursor-pointer"
                />
                <div className="flex justify-between text-xs text-gray-500 mt-2">
                  <span>₹500</span>
                  <span>₹1,00,000</span>
                </div>
              </div>
              
              {/* Investment Period */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <label className="block text-sm font-medium text-gray-700">
                    Investment Period
                  </label>
                  <div className="text-lg font-semibold text-blue-600">{investmentPeriod} Years</div>
                </div>
                <input
                  type="range"
                  min="1"
                  max="30"
                  value={investmentPeriod}
                  onChange={(e) => setInvestmentPeriod(Number(e.target.value))}
                  className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-600 [&::-webkit-slider-thumb]:cursor-pointer"
                />
                <div className="flex justify-between text-xs text-gray-500 mt-2">
                  <span>1 Year</span>
                  <span>30 Years</span>
                </div>
              </div>
              
              {/* Expected Return */}
              <div>
                <div className="flex justify-between items-center mb-4">
                  <label className="block text-sm font-medium text-gray-700">
                    Expected Return (annual)
                  </label>
                  <div className="text-lg font-semibold text-blue-600">{expectedReturn}%</div>
                </div>
                <input
                  type="range"
                  min="1"
                  max="20"
                  value={expectedReturn}
                  onChange={(e) => setExpectedReturn(Number(e.target.value))}
                  className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-600 [&::-webkit-slider-thumb]:cursor-pointer"
                />
                <div className="flex justify-between text-xs text-gray-500 mt-2">
                  <span>1%</span>
                  <span>20%</span>
                </div>
              </div>
              
              {/* Quick Presets */}
              <div className="pt-6 border-t border-gray-200">
                <h3 className="font-medium text-gray-900 mb-3">Quick Presets:</h3>
                <div className="grid grid-cols-2 gap-2">
                  {[
                    { amount: 5000, period: 5, return: 12, label: 'Conservative' },
                    { amount: 10000, period: 10, return: 14, label: 'Moderate' },
                    { amount: 15000, period: 15, return: 15, label: 'Balanced' },
                    { amount: 25000, period: 20, return: 16, label: 'Aggressive' }
                  ].map((preset, index) => (
                    <button
                      key={index}
                      onClick={() => {
                        setMonthlyInvestment(preset.amount);
                        setInvestmentPeriod(preset.period);
                        setExpectedReturn(preset.return);
                      }}
                      className={`px-4 py-3 rounded-lg text-sm transition-colors ${
                        monthlyInvestment === preset.amount && 
                        investmentPeriod === preset.period && 
                        expectedReturn === preset.return
                          ? 'bg-blue-600 text-white'
                          : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                      }`}
                    >
                      {preset.label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
          
          {/* Projected Returns with Pie Chart - RIGHT SIDE */}
          <div className="bg-white rounded-2xl shadow-lg p-8">
            <h2 className="text-2xl font-bold text-gray-900 mb-6">Projected Returns</h2>
            
            {result ? (
              <div className="space-y-8">
                {/* Total Value */}
                <div className="text-center mb-8">
                  <div className="text-sm text-gray-500 mb-2">
                    The total value of your investment after {investmentPeriod} {investmentPeriod === 1 ? 'Year' : 'Years'} will be
                  </div>
                  <div className="text-4xl font-bold text-blue-700 mb-1">
                    {formatCurrency(result.totalValue)}
                  </div>
                </div>
                
                {/* Pie Chart */}
                <div className="h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={chartData}
                        cx="50%"
                        cy="50%"
                        innerRadius={40}
                        outerRadius={80}
                        paddingAngle={0}
                        dataKey="value"
                        label={renderCustomizedLabel}
                        labelLine={false}
                      >
                        {chartData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip content={<CustomTooltip />} />
                      <Legend 
                        verticalAlign="bottom" 
                        height={36}
                        formatter={(value, entry) => (
                          <span className="text-sm text-gray-700">{value}</span>
                        )}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                
                {/* Invested Amount and Returns side by side */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="bg-blue-50 p-6 rounded-lg text-center">
                    <div className="text-sm text-blue-600 mb-1">Invested Amount</div>
                    <div className="text-2xl font-bold text-blue-700">
                      {formatCurrency(result.investedAmount)}
                    </div>
                    <div className="text-xs text-blue-500 mt-2">
                      {investmentPeriod} {investmentPeriod === 1 ? 'year' : 'years'} × {formatCurrency(monthlyInvestment)}/month
                    </div>
                  </div>
                  
                  <div className="bg-green-50 p-6 rounded-lg text-center">
                    <div className="text-sm text-green-600 mb-1">Est. Returns</div>
                    <div className="text-2xl font-bold text-green-700">
                      {formatCurrency(result.estimatedReturns)}
                    </div>
                    <div className="text-xs text-green-500 mt-2">
                      {((result.estimatedReturns / result.investedAmount) * 100).toFixed(1)}% of investment
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="text-center py-12">
                <div className="text-gray-400 mb-4">
                  <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                </div>
                <p className="text-gray-500">Adjust sliders to see projected returns</p>
              </div>
            )}
          </div>
        </div>
        
        {/* Information Section */}
        <div className="mt-12 bg-white rounded-2xl shadow-lg p-8">
          <div className="grid md:grid-cols-2 gap-8">
            <div>
              <h2 className="text-2xl font-bold text-gray-900 mb-6">How SIP Calculator Works</h2>
              <div className="space-y-4">
                <div className="flex items-start">
                  <div className="bg-blue-100 text-blue-600 rounded-full p-2 mr-3">
                    <span className="font-bold">1</span>
                  </div>
                  <div>
                    <h3 className="font-medium text-gray-900">Monthly Investment</h3>
                    <p className="text-gray-600 text-sm mt-1">Enter the amount you plan to invest every month in your SIP.</p>
                  </div>
                </div>
                
                <div className="flex items-start">
                  <div className="bg-green-100 text-green-600 rounded-full p-2 mr-3">
                    <span className="font-bold">2</span>
                  </div>
                  <div>
                    <h3 className="font-medium text-gray-900">Investment Period</h3>
                    <p className="text-gray-600 text-sm mt-1">Select the duration for which you plan to continue your SIP.</p>
                  </div>
                </div>
                
                <div className="flex items-start">
                  <div className="bg-purple-100 text-purple-600 rounded-full p-2 mr-3">
                    <span className="font-bold">3</span>
                  </div>
                  <div>
                    <h3 className="font-medium text-gray-900">Expected Returns</h3>
                    <p className="text-gray-600 text-sm mt-1">Enter the annual rate of return you expect from your investment.</p>
                  </div>
                </div>
              </div>
            </div>
            
            <div>
              <h2 className="text-2xl font-bold text-gray-900 mb-6">Benefits of SIP</h2>
              <ul className="space-y-3">
                <li className="flex items-center">
                  <span className="text-green-500 mr-2">✓</span>
                  <span className="text-gray-700">Disciplined approach to investing</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-2">✓</span>
                  <span className="text-gray-700">Rupee cost averaging reduces market timing risk</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-2">✓</span>
                  <span className="text-gray-700">Power of compounding over long term</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-2">✓</span>
                  <span className="text-gray-700">Flexibility to increase/decrease SIP amount</span>
                </li>
                <li className="flex items-center">
                  <span className="text-green-500 mr-2">✓</span>
                  <span className="text-gray-700">Affordable with low minimum investment</span>
                </li>
              </ul>
            </div>
          </div>
          
          <div className="mt-8 p-6 bg-blue-50 rounded-lg">
            <div className="flex items-start">
              <div className="text-blue-500 mr-3">ℹ️</div>
              <div>
                <h3 className="font-semibold text-blue-800 mb-2">About the Pie Chart:</h3>
                <ul className="text-blue-700 text-sm space-y-1">
                  <li>• <span className="font-semibold text-blue-600">Blue section</span> represents your total invested amount</li>
                  <li>• <span className="font-semibold text-green-600">Green section</span> represents your estimated returns</li>
                  <li>• The chart shows the proportion of your original investment vs. returns</li>
                </ul>
                <p className="text-blue-700 text-sm mt-3">
                  The calculations assume that returns are compounded monthly. Actual returns may vary due to market conditions.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}