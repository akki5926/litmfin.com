'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { PieChart, Pie, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, Cell, ResponsiveContainer } from 'recharts';
import TypedText from '@/components/TypedText';

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';

const SEGMENT_COLORS = {
  'Large Cap': '#059669',
  'Mid Cap': '#f59e0b',
  'Small Cap': '#8b5cf6',
  'Multi Cap': '#3b82f6'
};

const SEGMENT_LIGHT_COLORS = {
  'Large Cap': '#d1fae5',
  'Mid Cap': '#fef3c7',
  'Small Cap': '#ede9fe',
  'Multi Cap': '#dbeafe'
};

// Fund category mapping to market cap segments
const FUND_CATEGORY_MAP = {
  'largecap': 'Large Cap',
  'large cap': 'Large Cap',
  'mid cap': 'Mid Cap',
  'midcap': 'Mid Cap',
  'small cap': 'Small Cap',
  'smallcap': 'Small Cap',
  'multicap': 'Multi Cap',
  'multi cap': 'Multi Cap',
  'large & midcap': 'Large Cap',
  'elss': 'Multi Cap'
};

export default function MarketCapitalizationPage() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [marketData, setMarketData] = useState(null);
  const [selectedSegment, setSelectedSegment] = useState('all');
  const router = useRouter();
  const hasFetched = useRef(false);

  useEffect(() => {
    if (hasFetched.current) return;
    hasFetched.current = true;

    const fetchMarketCapData = async () => {
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
          const processedData = processMarketCapData(data);
          setMarketData(processedData);
        } else {
          setError(data.Message || 'Failed to load market cap data');
        }
      } catch (err) {
        console.error('Error fetching market cap data:', err);
        setError(err.message || 'Failed to load market cap data. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    fetchMarketCapData();
  }, [router]);

  // Process and categorize holdings by market cap segment
  const processMarketCapData = (apiData) => {
    const holdings = [];
    
    // Extract mutual fund holdings
    if (apiData.portfolioInfo) {
      Object.entries(apiData.portfolioInfo).forEach(([fundHouseCode, funds]) => {
        if (Array.isArray(funds)) {
          funds.forEach(fund => {
            Object.entries(fund).forEach(([fundCode, fundDetails]) => {
              if (fundDetails.Asset_Class === 'EQUITY' || fundDetails.Asset_Class?.toLowerCase() === 'equity') {
                const fundName = fundDetails.Fund_Name || 'Unknown Scheme';
                const category = extractMarketCapCategory(fundName, fundDetails);
                
                holdings.push({
                  id: fundCode,
                  scheme_name: fundName,
                  fund_house: fundDetails.Fund_House_Name || 'Unknown AMC',
                  segment: category,
                  cost_value: parseFloat(fundDetails.Cost_Value?.replace(/,/g, '') || 0),
                  current_value: parseFloat(fundDetails.Curr_Value?.replace(/,/g, '') || 0),
                  units: parseFloat(fundDetails.Unit_Holdings?.replace(/,/g, '') || 0),
                  unrealized_profit: parseFloat(fundDetails.UnRealizedProfit?.replace(/,/g, '') || 0),
                  xirr: parseFloat(fundDetails.XIRR || 0),
                  nav: parseFloat(fundDetails.Current_NAV || 0)
                });
              }
            });
          });
        }
      });
    }

    // Calculate segment-wise statistics
    const segmentStats = {};
    const segments = ['Large Cap', 'Mid Cap', 'Small Cap', 'Multi Cap'];
    
    segments.forEach(seg => {
      const segmentHoldings = holdings.filter(h => h.segment === seg);
      if (segmentHoldings.length > 0) {
        const costValue = segmentHoldings.reduce((sum, h) => sum + h.cost_value, 0);
        const currentValue = segmentHoldings.reduce((sum, h) => sum + h.current_value, 0);
        const unrealizedGain = segmentHoldings.reduce((sum, h) => sum + h.unrealized_profit, 0);
        const avgReturn = segmentHoldings.length > 0 
          ? segmentHoldings.reduce((sum, h) => sum + h.xirr, 0) / segmentHoldings.length 
          : 0;

        segmentStats[seg] = {
          holdings: segmentHoldings,
          count: segmentHoldings.length,
          costValue,
          currentValue,
          unrealizedGain,
          return: avgReturn,
          allocation: 0 // Will calculate after total
        };
      }
    });

    // Calculate total and allocations
    const totalCostValue = Object.values(segmentStats).reduce((sum, s) => sum + s.costValue, 0);
    const totalCurrentValue = Object.values(segmentStats).reduce((sum, s) => sum + s.currentValue, 0);
    const totalGain = Object.values(segmentStats).reduce((sum, s) => sum + s.unrealizedGain, 0);
    const totalReturn = totalCostValue > 0 ? (totalGain / totalCostValue) * 100 : 0;

    Object.keys(segmentStats).forEach(seg => {
      segmentStats[seg].allocation = totalCurrentValue > 0 
        ? (segmentStats[seg].currentValue / totalCurrentValue) * 100 
        : 0;
    });

    return {
      holdings,
      segments: segmentStats,
      totalCostValue,
      totalCurrentValue,
      totalGain,
      totalReturn,
      totalHoldings: holdings.length
    };
  };

  // Extract market cap category from fund name and details
  const extractMarketCapCategory = (fundName, fundDetails) => {
    const name = fundName.toLowerCase();
    
    // Check direct fund name indicators
    for (const [key, value] of Object.entries(FUND_CATEGORY_MAP)) {
      if (name.includes(key)) {
        return value;
      }
    }

    // If no specific category found, default to Multi Cap
    return 'Multi Cap';
  };

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
    if (!marketData) return [];
    return Object.entries(marketData.segments)
      .map(([segment, data]) => ({
        name: segment,
        value: data.currentValue,
        fill: SEGMENT_COLORS[segment]
      }))
      .filter(item => item.value > 0);
  };

  // Get chart data for returns
  const getReturnsChartData = () => {
    if (!marketData) return [];
    return Object.entries(marketData.segments)
      .map(([segment, data]) => ({
        name: segment,
        return: data.return || 0,
        fill: SEGMENT_COLORS[segment]
      }))
      .filter(item => item.return !== 0);
  };

  // Custom label for pie chart
  const renderCustomLabel = ({ name, value }) => {
    const total = marketData?.totalCurrentValue || 0;
    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
    return `${percentage}%`;
  };

  // Get filtered holdings
  const getFilteredHoldings = () => {
    if (!marketData) return [];
    if (selectedSegment === 'all') {
      return marketData.holdings.sort((a, b) => b.current_value - a.current_value);
    }
    return marketData.holdings
      .filter(h => h.segment === selectedSegment)
      .sort((a, b) => b.current_value - a.current_value);
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading market cap data...</p>
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
          <h3 className="text-xl font-bold text-gray-900 mb-2">Unable to Load Data</h3>
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

  if (!marketData) return null;

  const filteredHoldings = getFilteredHoldings();
  const chartData = getAllocationChartData();
  const returnsData = getReturnsChartData();

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            <TypedText text="Market Capitalization" className="text-gray-900" />
          </h1>
          <p className="text-gray-600 mt-2">Track your equity portfolio distribution across market cap segments</p>
        </div>

        {/* KPI Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Total Equity Investment</p>
            <p className="text-3xl font-bold text-gray-900">{formatCurrency(marketData.totalCostValue)}</p>
            <p className="text-xs text-gray-500 mt-2">Cost basis</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Current Value</p>
            <p className="text-3xl font-bold text-gray-900">{formatCurrency(marketData.totalCurrentValue)}</p>
            <p className="text-xs text-gray-500 mt-2">Market value today</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Total Gains</p>
            <p className={`text-3xl font-bold ${marketData.totalGain >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {formatCurrency(marketData.totalGain)}
            </p>
            <p className="text-xs text-gray-500 mt-2">Unrealized gains</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Portfolio Return</p>
            <p className={`text-3xl font-bold ${marketData.totalReturn >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {formatPercentage(marketData.totalReturn)}
            </p>
            <p className="text-xs text-gray-500 mt-2">Absolute return</p>
          </div>
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          {/* Allocation Chart */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">Market Cap Allocation</h2>
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

          {/* Performance Chart */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">Returns by Segment</h2>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={returnsData.length > 0 ? returnsData : Object.entries(marketData.segments).map(([name, data]) => ({ name, return: data.return, fill: SEGMENT_COLORS[name] }))}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip 
                  formatter={(value) => `${value.toFixed(2)}%`}
                  contentStyle={{ backgroundColor: '#f3f4f6', border: '1px solid #e5e7eb' }}
                />
                <Bar dataKey="return" radius={[8, 8, 0, 0]}>
                  {Object.values(marketData.segments).map((seg, index) => (
                    <Cell key={`cell-${index}`} fill={Object.values(SEGMENT_COLORS)[index]} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Segment Filter Buttons */}
        <div className="bg-white rounded-xl shadow-sm p-6 mb-8">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Filter by Market Cap Segment</h3>
          <div className="flex flex-wrap gap-3">
            <button
              onClick={() => setSelectedSegment('all')}
              className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 ${
                selectedSegment === 'all'
                  ? 'bg-blue-600 text-white shadow-md'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              All Segments
            </button>
            {['Large Cap', 'Mid Cap', 'Small Cap', 'Multi Cap'].map((segment) => (
              marketData.segments[segment] && (
                <button
                  key={segment}
                  onClick={() => setSelectedSegment(segment)}
                  className={`px-4 py-2 rounded-lg font-medium transition-all duration-200 ${
                    selectedSegment === segment
                      ? 'text-white shadow-md'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                  style={{
                    backgroundColor: selectedSegment === segment ? SEGMENT_COLORS[segment] : undefined
                  }}
                >
                  {segment}
                </button>
              )
            ))}
          </div>
        </div>

        {/* Segment Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {Object.entries(marketData.segments).map(([segment, data]) => (
            <div 
              key={segment}
              className="rounded-xl shadow-sm p-6"
              style={{ backgroundColor: SEGMENT_LIGHT_COLORS[segment] }}
            >
              <div className="flex items-start justify-between mb-4">
                <div>
                  <p className="text-sm font-medium text-gray-700">{segment}</p>
                  <p className="text-2xl font-bold text-gray-900 mt-1">{data.allocation.toFixed(1)}%</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-gray-600">{data.count} fund{data.count !== 1 ? 's' : ''}</p>
                </div>
              </div>
              <div className="space-y-2">
                <div className="flex justify-between text-xs">
                  <span className="text-gray-600">Current Value:</span>
                  <span className="font-semibold text-gray-900">{formatCurrency(data.currentValue)}</span>
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-gray-600">Avg Return:</span>
                  <span className={`font-semibold ${data.return >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                    {formatPercentage(data.return)}
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Holdings Table */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            {selectedSegment === 'all' ? 'All Holdings' : `${selectedSegment} Holdings`}
          </h2>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Scheme Name</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Segment</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Invested</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Current Value</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Allocation %</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Return %</th>
                  <th className="px-6 py-4 text-left text-sm font-semibold text-gray-900">Gains</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {filteredHoldings.length > 0 ? (
                  filteredHoldings.map((holding) => {
                    const allocation = marketData.totalCurrentValue > 0 
                      ? (holding.current_value / marketData.totalCurrentValue) * 100 
                      : 0;
                    const isPositive = holding.unrealized_profit >= 0;

                    return (
                      <tr key={holding.id} className="hover:bg-gray-50 transition-colors">
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex flex-col">
                            <p className="text-sm font-medium text-gray-900">{holding.scheme_name}</p>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span 
                            className="px-3 py-1 text-xs font-medium rounded-full text-white"
                            style={{ backgroundColor: SEGMENT_COLORS[holding.segment] }}
                          >
                            {holding.segment}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                          {formatCurrency(holding.cost_value)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          {formatCurrency(holding.current_value)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                          {allocation.toFixed(2)}%
                        </td>
                        <td className={`px-6 py-4 whitespace-nowrap text-sm font-medium ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                          {formatPercentage(holding.xirr)}
                        </td>
                        <td className={`px-6 py-4 whitespace-nowrap text-sm font-medium ${isPositive ? 'text-green-600' : 'text-red-600'}`}>
                          {isPositive ? '+' : ''}{formatCurrency(holding.unrealized_profit)}
                        </td>
                      </tr>
                    );
                  })
                ) : (
                  <tr>
                    <td colSpan="7" className="px-6 py-8 text-center text-gray-500">
                      No holdings found for this segment
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}