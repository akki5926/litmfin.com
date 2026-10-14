'use client';

import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { LineChart, Line, AreaChart, Area, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, Cell, ResponsiveContainer, PieChart, Pie } from 'recharts';
import TypedText from '@/components/TypedText';

// API base URL from environment
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/{siteConfig.domain}/api';
//const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost/litmfin.com/api';
const STATUS_COLORS = {
  'ACTIVE': '#10b981',
  'PAUSE': '#f59e0b',
  'MATURED': '#8b5cf6',
  'CXL': '#ef4444',
  'CANCELLED': '#ef4444',
  'CEASED': '#6b7280',
  'AUTHREJECT': '#ec4899',
  'COMPLETED': '#3b82f6'
};

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316', '#6366f1'];

const FREQUENCY_DISPLAY = {
  'WEEKLY': 'Weekly',
  'FORTNIGHTLY': 'Fortnightly',
  'MONTHLY': 'Monthly',
  'QUARTERLY': 'Quarterly',
  'HALF-YEARLY': 'Half-Yearly',
  'YEARLY': 'Yearly'
};

export default function SIPReportPage() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [sipData, setSipData] = useState(null);
  const [filteredData, setFilteredData] = useState([]);
  const router = useRouter();
  const hasFetched = useRef(false);

  useEffect(() => {
    if (hasFetched.current) return;
    hasFetched.current = true;

    const fetchSIPData = async () => {
      try {
        const token = localStorage.getItem('site_token');
        if (!token) {
          router.push('/login');
          return;
        }

        // First fetch portfolio data which includes SIP information
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
        
        // console.log('API Response:', data);
        // console.log('runningSIPInfo:', data.runningSIPInfo);
        
        if (data.Success) {
          // Check if API has SIP data in response - prioritize runningSIPInfo
          if (data.runningSIPInfo && Array.isArray(data.runningSIPInfo)) {
            // console.log('Processing runningSIPInfo data:', data.runningSIPInfo);
            const processedData = processSIPData(data.runningSIPInfo);
            setSipData(processedData);
            setFilteredData(processedData.activeSIPs);
          } else if (data.sipInfo && Array.isArray(data.sipInfo)) {
            // console.log('Processing sipInfo data:', data.sipInfo);
            const processedData = processSIPData(data.sipInfo);
            setSipData(processedData);
            setFilteredData(processedData.activeSIPs);
          } else {
            // If no SIP data in API, show message and use empty state
            console.log('No SIP data found in API response');
            setSipData({ activeSIPs: [], totalStats: { activeSIPCount: 0, totalInstallmentAmount: 0, uniqueSchemesCount: 0, uniqueAMCsCount: 0 } });
            setFilteredData([]);
          }
        } else {
          setError('Failed to load SIP data');
        }
      } catch (err) {
        console.error('Error fetching SIP data:', err);
        setError(err.message || 'Failed to load SIP data. Please try again.');
      } finally {
        setLoading(false);
      }
    };

    fetchSIPData();
  }, [router]);

  // Process SIP data from API
  const processSIPData = (rawSipData) => {
    // console.log('Raw SIP Data received:', rawSipData);
    
    if (!Array.isArray(rawSipData) || rawSipData.length === 0) {
      console.log('No SIP data or not an array');
      return { activeSIPs: [], totalStats: {} };
    }

    // console.log('Processing', rawSipData.length, 'SIP records');
    
    const activeSIPs = [];
    
    let totalInstallmentAmount = 0;
    let uniqueSchemes = new Set();
    let uniqueAMCs = new Set();

    rawSipData.forEach((sip, index) => {
      // console.log(`Processing SIP ${index}:`, sip);
      
      // Map API field names to display field names
      const sip_amount = parseFloat(sip.Amount || 0);
      const from_date = sip.From_Date || '-';
      const to_date = sip.To_Date || '-';
      
      // Check if SIP is active (To_Date should be in future)
      const toDate = new Date(to_date);
      const today = new Date();
      const isActive = toDate > today;
      
      const processedSip = {
        id: `${sip.Fund_Code}-${index}`,
        scheme_name: sip.Fund_Name || 'Unknown Scheme',
        fund_house: sip.AMC_Name || 'Unknown AMC',
        fund_code: sip.Fund_Code || '-',
        isin_code: sip.ISIN_Code || '-',
        sip_type: sip.Sip_Type || 'SIP',
        sip_amount: sip_amount,
        frequency: sip.Frequency || 'MONTHLY',
        status: isActive ? 'ACTIVE' : 'COMPLETED',
        start_date: from_date,
        end_date: to_date
      };

      // console.log('Processed SIP object:', processedSip);
      
      if (isActive) {
        activeSIPs.push(processedSip);
        totalInstallmentAmount += sip_amount;
        uniqueSchemes.add(processedSip.fund_code);
        uniqueAMCs.add(processedSip.fund_house);
      }
    });

    const result = {
      activeSIPs: activeSIPs.sort((a, b) => b.sip_amount - a.sip_amount),
      totalStats: {
        activeSIPCount: activeSIPs.length,
        totalInstallmentAmount,
        uniqueSchemesCount: uniqueSchemes.size,
        uniqueAMCsCount: uniqueAMCs.size
      }
    };
    
    // console.log('Final processed SIP data:', result);
    return result;
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

  // Get frequency display text
  const getFrequencyDisplay = (frequency) => {
    return FREQUENCY_DISPLAY[frequency?.toUpperCase()] || frequency || '-';
  };

  // Get status badge color
  const getStatusColor = (status) => {
    return STATUS_COLORS[status?.toUpperCase()] || '#6b7280';
  };

  // Prepare data for AMC Allocation Pie Chart
  const getAmcChartData = () => {
    if (!filteredData || filteredData.length === 0) return [];
    const amcMap = {};
    filteredData.forEach(sip => {
      amcMap[sip.fund_house] = (amcMap[sip.fund_house] || 0) + sip.sip_amount;
    });
    return Object.entries(amcMap)
      .map(([name, value]) => ({ name, value }))
      .sort((a, b) => b.value - a.value);
  };

  // Prepare data for Top SIP Schemes Bar Chart
  const getSchemeChartData = () => {
    if (!filteredData || filteredData.length === 0) return [];
    return filteredData.map(sip => ({
      name: sip.scheme_name,
      shortName: sip.scheme_name.length > 20 ? sip.scheme_name.substring(0, 20) + '...' : sip.scheme_name,
      value: sip.sip_amount
    })).sort((a, b) => b.value - a.value).slice(0, 5); // top 5 highest SIPs
  };

  const amcData = getAmcChartData();
  const schemeData = getSchemeChartData();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading SIP data...</p>
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
          <h3 className="text-xl font-bold text-gray-900 mb-2">Unable to Load SIP Data</h3>
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

  if (!sipData) return null;

  const { totalStats } = sipData;

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            <TypedText text="SIP Report" className="text-gray-900" />
          </h1>
          <p className="text-gray-600 mt-2">Track and manage your Systematic Investment Plans</p>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Active SIPs</p>
            <p className="text-3xl font-bold text-gray-900">{totalStats.activeSIPCount}</p>
            <p className="text-xs text-gray-500 mt-2">Running systematically</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Total Installment Amount</p>
            <p className="text-3xl font-bold text-gray-900">{formatCurrency(totalStats.totalInstallmentAmount)}</p>
            <p className="text-xs text-gray-500 mt-2">Sum of active SIP amounts</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Unique Schemes</p>
            <p className="text-3xl font-bold text-gray-900">{totalStats.uniqueSchemesCount}</p>
            <p className="text-xs text-gray-500 mt-2">Number of distinct funds</p>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
            <p className="text-sm font-medium text-gray-600 mb-2">Unique AMCs</p>
            <p className="text-3xl font-bold text-gray-900">{totalStats.uniqueAMCsCount}</p>
            <p className="text-xs text-gray-500 mt-2">Asset Management Companies</p>
          </div>
        </div>

        {/* Graphics Section */}
        {filteredData.length > 0 && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {/* Allocation by AMC Chart */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-6">Installment Amount by AMC</h2>
              <div className="h-[380px]">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={amcData}
                      cx="50%"
                      cy="50%"
                      innerRadius={70}
                      outerRadius={100}
                      paddingAngle={5}
                      dataKey="value"
                      label={({ percent }) => percent > 0.05 ? `${(percent * 100).toFixed(0)}%` : null}
                      labelLine={false}
                    >
                      {amcData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip 
                      formatter={(value) => formatCurrency(value)}
                      contentStyle={{ backgroundColor: '#f9fafb', borderRadius: '0.5rem', border: '1px solid #e5e7eb' }}
                    />
                    <Legend 
                      layout="vertical" 
                      verticalAlign="bottom" 
                      align="left" 
                      iconType="circle" 
                      wrapperStyle={{ fontSize: '11px', paddingTop: '10px' }} 
                    />
                  </PieChart>
                </ResponsiveContainer>
              </div>
            </div>

            {/* Top SIP Schemes Chart */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-6">Top SIPs by Installment Amount</h2>
              <div className="h-[380px]">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={schemeData} margin={{ top: 20, right: 30, left: 20, bottom: 40 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="shortName" angle={-45} textAnchor="end" height={60} tick={{ fontSize: 11, fill: '#6b7280' }} interval={0} />
                    <YAxis tickFormatter={(val) => `₹${val}`} tick={{ fontSize: 12, fill: '#6b7280' }} />
                    <Tooltip 
                      formatter={(value) => formatCurrency(value)}
                      labelFormatter={(label) => schemeData.find(d => d.shortName === label)?.name || label}
                      contentStyle={{ backgroundColor: '#f9fafb', borderRadius: '0.5rem', border: '1px solid #e5e7eb' }}
                    />
                    <Bar dataKey="value" radius={[6, 6, 0, 0]} maxBarSize={50}>
                      {schemeData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[(index + 4) % COLORS.length]} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>
          </div>
        )}

        {/* SIP List Section */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
          <div className="p-6 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Active SIPs ({totalStats.activeSIPCount})</h2>
          </div>

          <div className="p-6">
            {filteredData.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Scheme Name</th>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Fund Code / ISIN</th>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Amount</th>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Frequency</th>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Start Date</th>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">End Date</th>
                      <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {filteredData.map((sip) => (
                      <tr key={sip.id} className="hover:bg-gray-50 transition-colors">
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="flex flex-col">
                            <p className="text-sm font-medium text-gray-900">{sip.scheme_name}</p>
                            <p className="text-xs text-gray-500">{sip.fund_house}</p>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                          <div className="flex flex-col">
                            <p>{sip.fund_code}</p>
                            <p className="text-xs text-gray-400">{sip.isin_code}</p>
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          {formatCurrency(sip.sip_amount)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                          {getFrequencyDisplay(sip.frequency)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                          {sip.start_date}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                          {sip.end_date}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span 
                            className="px-3 py-1 text-xs font-medium rounded-full text-white"
                            style={{ backgroundColor: getStatusColor(sip.status) }}
                          >
                            {sip.status}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="text-center py-12">
                <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p className="text-gray-500 text-lg">No active SIPs found</p>
              </div>
            )}
          </div>
        </div>

        {/* Additional Information */}
        {filteredData.length > 0 && (
          <div className="bg-blue-50 border border-blue-200 rounded-xl p-6">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0z" clipRule="evenodd" />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-blue-800">SIP Benefits</h3>
                <div className="mt-2 text-sm text-blue-700">
                  <p>Systematic Investment Plans help you invest regularly regardless of market conditions, following the principle of rupee-cost averaging.</p>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}