'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { PieChart, Pie, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, Cell, ResponsiveContainer, LineChart, Line } from 'recharts';
import TypedText from '@/components/TypedText';

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

const ASSET_COLORS = {
  Equity: '#3b82f6',
  Debt: '#10b981',
  Gold: '#f59e0b',
  Hybrid: '#8b5cf6'
};

const ASSET_LIGHT_COLORS = {
  Equity: '#dbeafe',
  Debt: '#d1fae5',
  Gold: '#fef3c7',
  Hybrid: '#ede9fe'
};

export default function AssetSplitsPage() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [assetData, setAssetData] = useState(null);
  const [chartType, setChartType] = useState('pie');
  const router = useRouter();
  const hasFetched = useRef(false);

  useEffect(() => {
    if (hasFetched.current) return;
    hasFetched.current = true;

    const fetchAssetData = async () => {
      try {
        const token = localStorage.getItem('site_token');
        if (!token) {
          router.push('/login');
          return;
        }

        const response = await fetch(`${API_BASE_URL}/grand_folio_api.php`, {
          method: 'GET',
          headers: { 
            'Authorization': `Bearer ${token}`, 
            'Content-Type': 'application/json' 
          }
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (data.Success) {
          const totalAssetInfo = data.totalAssetInfo?.[0] || {};
          
          const totalInvestment = parseFloat(totalAssetInfo.Total_Cost_Value?.replace(/,/g, '') || 0);
          const currentValue = parseFloat(totalAssetInfo.Total_Current_Value?.replace(/,/g, '') || 0);
          const totalGain = parseFloat(totalAssetInfo.Total_Unrealized?.replace(/,/g, '') || 0);
          const totalReturn = totalInvestment > 0 ? (totalGain / totalInvestment) * 100 : 0;
          
          const allocation = {
            Equity: {
              costValue: parseFloat(totalAssetInfo.Total_Equity_Cost_Value?.replace(/,/g, '') || 0),
              currentValue: parseFloat(totalAssetInfo.Total_Equity_Curr_Value?.replace(/,/g, '') || 0),
              return: parseFloat(totalAssetInfo.Total_Equity_Return || 0),
              unrealizedGain: parseFloat(totalAssetInfo.Total_Equity_UnR?.replace(/,/g, '') || 0)
            },
            Debt: {
              costValue: parseFloat(totalAssetInfo.Total_Debt_Cost_Value?.replace(/,/g, '') || 0),
              currentValue: parseFloat(totalAssetInfo.Total_Debt_Curr_Value?.replace(/,/g, '') || 0),
              return: parseFloat(totalAssetInfo.Total_Debt_Return || 0),
              unrealizedGain: parseFloat(totalAssetInfo.Total_Debt_UnR?.replace(/,/g, '') || 0)
            },
            Gold: {
              costValue: parseFloat(totalAssetInfo.Total_Gold_Cost_Value?.replace(/,/g, '') || 0),
              currentValue: parseFloat(totalAssetInfo.Total_Gold_Curr_Value?.replace(/,/g, '') || 0),
              return: parseFloat(totalAssetInfo.Total_Gold_Return || 0),
              unrealizedGain: parseFloat(totalAssetInfo.Total_Gold_UnR?.replace(/,/g, '') || 0)
            },
            Hybrid: {
              costValue: parseFloat(totalAssetInfo.Total_Hybrid_Cost_Value?.replace(/,/g, '') || 0),
              currentValue: parseFloat(totalAssetInfo.Total_Hybrid_Curr_Value?.replace(/,/g, '') || 0),
              return: parseFloat(totalAssetInfo.Total_Hybrid_Return || 0),
              unrealizedGain: parseFloat(totalAssetInfo.Total_Hybrid_UnR?.replace(/,/g, '') || 0)
            }
          };

          setAssetData({
            totalInvestment,
            currentValue,
            totalGain,
            totalReturn,
            allocation,
            rawData: totalAssetInfo
          });
        } else {
          setError(data.Message || 'Failed to load asset data');
        }
      } catch (err) {
        console.error('Error fetching asset data:', err);
        setError(err.message || 'Failed to load asset splits. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    fetchAssetData();
  }, [router]);

  // Format currency
  const formatCurrency = (amount) => {
    if (amount === undefined || amount === null) return '₹0';
    const num = typeof amount === 'string' ? parseFloat(amount.replace(/,/g, '')) : amount;
    
    if (num >= 10000000) {
      return `₹${(num / 10000000).toFixed(2)} Cr`;
    }
    if (num >= 100000) {
      return `₹${(num / 100000).toFixed(2)} L`;
    }
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(num);
  };

  // Format percentage
  const formatPercentage = (value) => {
    if (value === undefined || value === null) return '0.00%';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return `${num >= 0 ? '+' : ''}${num.toFixed(2)}%`;
  };

  // Get chart data for allocation
  const getAllocationChartData = () => {
    if (!assetData) return [];
    const { allocation } = assetData;
    return [
      { name: 'Equity', value: allocation.Equity.currentValue, fill: ASSET_COLORS.Equity },
      { name: 'Debt', value: allocation.Debt.currentValue, fill: ASSET_COLORS.Debt },
      { name: 'Gold', value: allocation.Gold.currentValue, fill: ASSET_COLORS.Gold },
      { name: 'Hybrid', value: allocation.Hybrid.currentValue, fill: ASSET_COLORS.Hybrid }
    ].filter(item => item.value > 0);
  };

  // Get chart data for returns
  const getReturnsChartData = () => {
    if (!assetData) return [];
    const { allocation } = assetData;
    return [
      { name: 'Equity', return: allocation.Equity.return || 0, fill: ASSET_COLORS.Equity },
      { name: 'Debt', return: allocation.Debt.return || 0, fill: ASSET_COLORS.Debt },
      { name: 'Gold', return: allocation.Gold.return || 0, fill: ASSET_COLORS.Gold },
      { name: 'Hybrid', return: allocation.Hybrid.return || 0, fill: ASSET_COLORS.Hybrid }
    ];
  };

  // Custom label for pie chart
  const renderCustomLabel = ({ name, value }) => {
    const total = assetData?.currentValue || 0;
    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
    return `${percentage}%`;
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading asset splits...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center max-w-md p-8 bg-white rounded-xl shadow-lg">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Asset Data</h3>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={() => window.location.reload()}
            className="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  if (!assetData) return null;

  const { allocation, totalInvestment, currentValue, totalGain, totalReturn } = assetData;
  const chartData = getAllocationChartData();
  const returnsData = getReturnsChartData();

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            <TypedText text="Asset Allocation & Splits" className="text-gray-900" />
          </h1>
          <p className="text-gray-600 mt-2">View and analyze your portfolio asset allocation across different categories</p>
        </div>

        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Total Investment</p>
            <p className="text-3xl font-bold text-gray-900">{formatCurrency(totalInvestment)}</p>
            <p className="text-xs text-gray-500 mt-2">Cost basis of all holdings</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Current Value</p>
            <p className="text-3xl font-bold text-gray-900">{formatCurrency(currentValue)}</p>
            <p className="text-xs text-gray-500 mt-2">Market value today</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Total Gains</p>
            <p className={`text-3xl font-bold ${totalGain >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {formatCurrency(totalGain)}
            </p>
            <p className="text-xs text-gray-500 mt-2">Unrealized gains</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Overall Returns</p>
            <p className={`text-3xl font-bold ${totalReturn >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {formatPercentage(totalReturn)}
            </p>
            <p className="text-xs text-gray-500 mt-2">Return on investment</p>
          </div>
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          {/* Asset Allocation Chart */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">Asset Allocation</h2>
            {chartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={chartData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={renderCustomLabel}
                    outerRadius={100}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {chartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.fill} />
                    ))}
                  </Pie>
                  <Tooltip 
                    formatter={(value) => formatCurrency(value)}
                    contentStyle={{ backgroundColor: '#f3f4f6', border: '1px solid #e5e7eb' }}
                  />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-80 flex items-center justify-center text-gray-400">
                No allocation data available
              </div>
            )}
          </div>

          {/* Returns Comparison Chart */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">Returns by Asset Class</h2>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={returnsData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip 
                  formatter={(value) => `${value.toFixed(2)}%`}
                  contentStyle={{ backgroundColor: '#f3f4f6', border: '1px solid #e5e7eb' }}
                />
                <Bar dataKey="return" fill="#3b82f6" radius={[8, 8, 0, 0]}>
                  {returnsData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.fill} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Detailed Asset Breakdown Table */}
        <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
          <h2 className="text-xl font-semibold text-gray-900 mb-6">Asset Class Details</h2>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Asset Class</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Invested</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Current Value</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Allocation %</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Gains/Loss</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Return %</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {['Equity', 'Debt', 'Gold', 'Hybrid'].map((assetClass) => {
                  const asset = allocation[assetClass];
                  const allocationPercent = currentValue > 0 ? (asset.currentValue / currentValue) * 100 : 0;
                  const isPositive = asset.unrealizedGain >= 0;

                  return (
                    <tr key={assetClass} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <div 
                            className="w-3 h-3 rounded-full mr-3"
                            style={{ backgroundColor: ASSET_COLORS[assetClass] }}
                          ></div>
                          <span className="font-medium text-gray-900">{assetClass}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-gray-600">
                        {formatCurrency(asset.costValue)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-gray-900 font-medium">
                        {formatCurrency(asset.currentValue)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-gray-600">
                        <div className="flex items-center">
                          <div className="w-32 bg-gray-200 rounded-full h-2 mr-3">
                            <div
                              className="h-2 rounded-full"
                              style={{
                                width: `${allocationPercent}%`,
                                backgroundColor: ASSET_COLORS[assetClass]
                              }}
                            ></div>
                          </div>
                          <span>{allocationPercent.toFixed(1)}%</span>
                        </div>
                      </td>
                      <td className={`px-6 py-4 whitespace-nowrap font-medium ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                        {isPositive ? '+' : ''}{formatCurrency(asset.unrealizedGain)}
                      </td>
                      <td className={`px-6 py-4 whitespace-nowrap font-medium ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                        {formatPercentage(asset.return)}
                      </td>
                    </tr>
                  );
                })}
                <tr className="bg-gray-50 font-semibold">
                  <td className="px-6 py-4 whitespace-nowrap text-gray-900">Total</td>
                  <td className="px-6 py-4 whitespace-nowrap text-gray-900">
                    {formatCurrency(totalInvestment)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-gray-900">
                    {formatCurrency(currentValue)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-gray-900">100%</td>
                  <td className={`px-6 py-4 whitespace-nowrap ${totalGain >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                    {totalGain >= 0 ? '+' : ''}{formatCurrency(totalGain)}
                  </td>
                  <td className={`px-6 py-4 whitespace-nowrap ${totalReturn >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                    {formatPercentage(totalReturn)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Asset Class Comparison */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Allocation Breakdown</h3>
            <div className="space-y-3">
              {['Equity', 'Debt', 'Gold', 'Hybrid'].map((assetClass) => {
                const asset = allocation[assetClass];
                const allocationPercent = currentValue > 0 ? (asset.currentValue / currentValue) * 100 : 0;

                return (
                  <div key={assetClass} className="flex items-center justify-between p-3 rounded-lg" style={{ backgroundColor: ASSET_LIGHT_COLORS[assetClass] }}>
                    <div className="flex items-center space-x-3">
                      <div 
                        className="w-4 h-4 rounded-full"
                        style={{ backgroundColor: ASSET_COLORS[assetClass] }}
                      ></div>
                      <div>
                        <p className="font-medium text-gray-900">{assetClass}</p>
                        <p className="text-xs text-gray-600">{formatCurrency(asset.currentValue)}</p>
                      </div>
                    </div>
                    <p className="text-lg font-bold text-gray-900">{allocationPercent.toFixed(1)}%</p>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Performance Summary */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Performance Summary</h3>
            <div className="space-y-3">
              {['Equity', 'Debt', 'Gold', 'Hybrid'].map((assetClass) => {
                const asset = allocation[assetClass];
                const isPositive = asset.return >= 0;

                return (
                  <div key={assetClass} className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                    <div className="flex items-center space-x-3">
                      <div 
                        className="w-4 h-4 rounded-full"
                        style={{ backgroundColor: ASSET_COLORS[assetClass] }}
                      ></div>
                      <p className="font-medium text-gray-900">{assetClass}</p>
                    </div>
                    <div className="text-right">
                      <p className={`text-sm font-semibold ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                        {formatPercentage(asset.return)}
                      </p>
                      <p className="text-xs text-gray-500">{formatCurrency(asset.unrealizedGain)}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}