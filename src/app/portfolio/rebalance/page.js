'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export default function RebalancePortfolioPage() {
  const router = useRouter();
  const [portfolioData, setPortfolioData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [targetAllocation, setTargetAllocation] = useState({
    Equity: 60,
    Debt: 25,
    Gold: 10,
    Hybrid: 5
  });
  const [rebalancingPlan, setRebalancingPlan] = useState(null);
  const [suggestedSwitches, setSuggestedSwitches] = useState([]);

  useEffect(() => {
    const data = localStorage.getItem('portfolio_analysis');
    if (data) {
      const analysisData = JSON.parse(data);
      setPortfolioData(analysisData);
      generateRebalancingPlan(analysisData);
    } else {
      // Fallback: Redirect to portfolio if no data
      router.push('/portfolio');
    }
    setLoading(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const generateRebalancingPlan = (analysisData) => {
    const currentAllocation = analysisData.diversification.assetClassDistribution;
    const recommendations = analysisData.recommendations || [];
    
    // Calculate rebalancing needs
    const rebalancing = {};
    let totalValue = 0;
    
    // Calculate current values
    Object.entries(currentAllocation).forEach(([asset, data]) => {
      rebalancing[asset] = {
        current: data.percentage || 0,
        target: targetAllocation[asset] || 0,
        value: data.value || 0
      };
      totalValue += data.value || 0;
    });
    
    // Calculate adjustments needed
    Object.keys(rebalancing).forEach(asset => {
      const adjustment = targetAllocation[asset] - rebalancing[asset].current;
      const amount = (adjustment / 100) * totalValue;
      
      rebalancing[asset] = {
        ...rebalancing[asset],
        adjustment,
        amount,
        action: adjustment > 0 ? 'BUY' : adjustment < 0 ? 'SELL' : 'HOLD',
        amountAbs: Math.abs(amount)
      };
    });
    
    // Generate suggested fund switches based on recommendations
    const switches = [];
    if (recommendations.some(r => r.type === 'performance')) {
      switches.push({
        from: 'Underperforming Equity Fund',
        to: 'Nifty 50 Index Fund',
        reason: 'Higher benchmark alignment',
        impact: '+2% expected returns'
      });
    }
    
    if (recommendations.some(r => r.type === 'cost')) {
      switches.push({
        from: 'High TER Regular Plan',
        to: 'Low TER Direct Plan',
        reason: 'Reduce expense ratio',
        impact: 'Save 1% annually in fees'
      });
    }
    
    if (analysisData.riskAnalysis.overallRiskScore > 7) {
      switches.push({
        from: 'Small Cap Fund',
        to: 'Large Cap Fund',
        reason: 'Reduce portfolio risk',
        impact: 'Lower volatility by 20%'
      });
    }
    
    setRebalancingPlan(rebalancing);
    setSuggestedSwitches(switches);
  };

  const formatCurrency = (amount) => {
    if (!amount) return '₹0';
    if (amount >= 10000000) return `₹${(amount / 10000000).toFixed(2)} Cr`;
    if (amount >= 100000) return `₹${(amount / 100000).toFixed(2)} L`;
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  };

  const handleTargetChange = (asset, value) => {
    setTargetAllocation(prev => ({
      ...prev,
      [asset]: Math.min(100, Math.max(0, parseInt(value) || 0))
    }));
  };

  const calculateTotalAllocation = () => {
    return Object.values(targetAllocation).reduce((sum, val) => sum + val, 0);
  };

  const handleRebalance = () => {
    const plan = {
      rebalancingPlan,
      targetAllocation,
      timestamp: new Date().toISOString()
    };
    localStorage.setItem('rebalancing_plan', JSON.stringify(plan));
    alert('Rebalancing plan saved! You can now execute the suggested actions.');
  };

  const getRecommendedFunds = (assetClass) => {
    const recommendedFunds = {
      Equity: [
        { name: 'Parag Parikh Flexi Cap Fund', type: 'Flexi Cap', ter: 0.58 },
        { name: 'Mirae Asset Large Cap Fund', type: 'Large Cap', ter: 0.60 },
        { name: 'Axis Bluechip Fund', type: 'Large Cap', ter: 0.62 },
        { name: 'UTI Nifty 50 Index Fund', type: 'Index', ter: 0.20 }
      ],
      Debt: [
        { name: 'ICICI Prudential Short Term Fund', type: 'Short Term', ter: 0.55 },
        { name: 'SBI Magnum Income Fund', type: 'Income', ter: 0.60 },
        { name: 'HDFC Banking & PSU Debt Fund', type: 'Banking & PSU', ter: 0.50 }
      ],
      Gold: [
        { name: 'SBI Gold Fund', type: 'Gold ETF', ter: 0.50 },
        { name: 'HDFC Gold Fund', type: 'Gold FoF', ter: 0.55 }
      ],
      Hybrid: [
        { name: 'ICICI Prudential Equity & Debt Fund', type: 'Aggressive Hybrid', ter: 0.85 },
        { name: 'HDFC Balanced Advantage Fund', type: 'Dynamic Asset Allocation', ter: 0.90 }
      ]
    };
    
    return recommendedFunds[assetClass] || [];
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading rebalancing analysis...</p>
        </div>
      </div>
    );
  }

  if (!portfolioData) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center max-w-md p-8 bg-white rounded-xl shadow-lg">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-xl font-bold text-gray-900 mb-2">No Portfolio Data</h3>
          <p className="text-gray-600 mb-6">Please analyze your portfolio first</p>
          <Link
            href="/portfolio"
            className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors inline-block"
          >
            Back to Portfolio
          </Link>
        </div>
      </div>
    );
  }

  const totalAllocation = calculateTotalAllocation();
  const isBalanced = totalAllocation === 100;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="flex items-center">
                <Link
                  href="/portfolio"
                  className="mr-4 text-gray-600 hover:text-gray-900"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                  </svg>
                </Link>
                <div>
                  <h1 className="text-3xl font-bold text-gray-900">Portfolio Rebalancing</h1>
                  <p className="text-gray-600 mt-2">Optimize your asset allocation for better returns</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Allocation Editor */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <div className="flex justify-between items-center mb-6">
            <h2 className="text-xl font-bold text-gray-900">Target Asset Allocation</h2>
            <div className={`px-4 py-2 rounded-lg ${isBalanced ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
              Total: {totalAllocation}%
              {!isBalanced && <span className="ml-2 text-sm">(Should be 100%)</span>}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {Object.entries(targetAllocation).map(([asset, percentage]) => (
              <div key={asset} className="bg-gray-50 rounded-lg p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="font-medium text-gray-900">{asset}</h3>
                  <div className="flex items-center space-x-2">
                    <input
                      type="number"
                      min="0"
                      max="100"
                      value={percentage}
                      onChange={(e) => handleTargetChange(asset, e.target.value)}
                      className="w-20 px-3 py-1 border border-gray-300 rounded text-center"
                    />
                    <span className="text-gray-600">%</span>
                  </div>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    className="h-2 rounded-full bg-blue-500"
                    style={{ width: `${percentage}%` }}
                  ></div>
                </div>
                <p className="text-sm text-gray-600 mt-2">
                  Recommended for moderate risk: {asset === 'Equity' ? '50-70%' : 
                                                asset === 'Debt' ? '20-40%' :
                                                asset === 'Gold' ? '5-15%' : '5-10%'}
                </p>
              </div>
            ))}
          </div>

          {/* Preset Strategies */}
          <div className="mb-8">
            <h3 className="font-medium text-gray-900 mb-4">Quick Allocation Presets</h3>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <button
                onClick={() => setTargetAllocation({ Equity: 40, Debt: 40, Gold: 10, Hybrid: 10 })}
                className="px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-left"
              >
                <p className="font-medium text-gray-900">Conservative</p>
                <p className="text-sm text-gray-600">Low risk, stable returns</p>
              </button>
              <button
                onClick={() => setTargetAllocation({ Equity: 60, Debt: 25, Gold: 10, Hybrid: 5 })}
                className="px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-left"
              >
                <p className="font-medium text-gray-900">Moderate</p>
                <p className="text-sm text-gray-600">Balanced growth & stability</p>
              </button>
              <button
                onClick={() => setTargetAllocation({ Equity: 80, Debt: 15, Gold: 5, Hybrid: 0 })}
                className="px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 text-left"
              >
                <p className="font-medium text-gray-900">Aggressive</p>
                <p className="text-sm text-gray-600">High growth, high risk</p>
              </button>
            </div>
          </div>
        </div>

        {/* Rebalancing Plan */}
        {rebalancingPlan && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Rebalancing Action Plan</h2>
            
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Asset Class</th>
                    <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Current</th>
                    <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Target</th>
                    <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Adjustment</th>
                    <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Action</th>
                    <th className="py-3 px-4 text-left text-sm font-medium text-gray-600">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(rebalancingPlan).map(([asset, data]) => (
                    <tr key={asset} className="border-b border-gray-100 hover:bg-gray-50">
                      <td className="py-4 px-4">
                        <div className="flex items-center">
                          <div className="w-8 h-8 rounded-full mr-3" style={{
                            backgroundColor: asset === 'Equity' ? '#3b82f6' :
                                            asset === 'Debt' ? '#10b981' :
                                            asset === 'Gold' ? '#f59e0b' : '#8b5cf6'
                          }}></div>
                          <span className="font-medium text-gray-900">{asset}</span>
                        </div>
                      </td>
                      <td className="py-4 px-4">
                        <span className="text-gray-900">{data.current.toFixed(1)}%</span>
                      </td>
                      <td className="py-4 px-4">
                        <span className="text-gray-900">{data.target.toFixed(1)}%</span>
                      </td>
                      <td className="py-4 px-4">
                        <span className={`font-medium ${data.adjustment > 0 ? 'text-green-600' : data.adjustment < 0 ? 'text-red-600' : 'text-gray-600'}`}>
                          {data.adjustment > 0 ? '+' : ''}{data.adjustment.toFixed(1)}%
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <span className={`px-3 py-1 rounded-full text-sm font-medium ${
                          data.action === 'BUY' ? 'bg-green-100 text-green-800' :
                          data.action === 'SELL' ? 'bg-red-100 text-red-800' :
                          'bg-gray-100 text-gray-800'
                        }`}>
                          {data.action}
                        </span>
                      </td>
                      <td className="py-4 px-4">
                        <span className={`font-medium ${data.action === 'BUY' ? 'text-green-600' : 'text-red-600'}`}>
                          {data.action === 'BUY' ? '+' : '-'}{formatCurrency(data.amountAbs)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Summary */}
            <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-blue-50 rounded-lg p-6">
                <h3 className="font-medium text-blue-900 mb-2">Total Investment Needed</h3>
                <p className="text-2xl font-bold text-blue-900">
                  {formatCurrency(Object.values(rebalancingPlan)
                    .filter(a => a.action === 'BUY')
                    .reduce((sum, a) => sum + a.amountAbs, 0))}
                </p>
                <p className="text-sm text-blue-700 mt-2">For buying underweight assets</p>
              </div>
              
              <div className="bg-green-50 rounded-lg p-6">
                <h3 className="font-medium text-green-900 mb-2">Amount to Redeem</h3>
                <p className="text-2xl font-bold text-green-900">
                  {formatCurrency(Object.values(rebalancingPlan)
                    .filter(a => a.action === 'SELL')
                    .reduce((sum, a) => sum + a.amountAbs, 0))}
                </p>
                <p className="text-sm text-green-700 mt-2">From overweight assets</p>
              </div>
              
              <div className="bg-purple-50 rounded-lg p-6">
                <h3 className="font-medium text-purple-900 mb-2">Rebalancing Impact</h3>
                <p className="text-2xl font-bold text-purple-900">+2-4%</p>
                <p className="text-sm text-purple-700 mt-2">Expected improvement in risk-adjusted returns</p>
              </div>
            </div>
          </div>
        )}

        {/* Suggested Fund Switches */}
        {suggestedSwitches.length > 0 && (
          <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Suggested Fund Switches</h2>
            
            <div className="space-y-4">
              {suggestedSwitches.map((switchItem, index) => (
                <div key={index} className="border border-gray-200 rounded-lg p-5 hover:bg-gray-50">
                  <div className="flex items-start">
                    <div className="mr-4 px-3 py-1 bg-red-100 text-red-800 rounded text-sm font-medium">
                      SWITCH
                    </div>
                    <div className="flex-1">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div className="bg-red-50 rounded-lg p-4">
                          <p className="text-sm font-medium text-red-600 mb-1">From (Current)</p>
                          <p className="font-medium text-gray-900">{switchItem.from}</p>
                          <p className="text-xs text-red-500 mt-1">Consider switching</p>
                        </div>
                        <div className="bg-green-50 rounded-lg p-4">
                          <p className="text-sm font-medium text-green-600 mb-1">To (Recommended)</p>
                          <p className="font-medium text-gray-900">{switchItem.to}</p>
                          <p className="text-xs text-green-500 mt-1">Better alternative</p>
                        </div>
                      </div>
                      
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <p className="text-sm font-medium text-gray-600 mb-1">Reason</p>
                          <p className="text-gray-900">{switchItem.reason}</p>
                        </div>
                        <div>
                          <p className="text-sm font-medium text-gray-600 mb-1">Expected Impact</p>
                          <p className="text-green-600 font-medium">{switchItem.impact}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Recommended Funds */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Recommended Funds for Investment</h2>
          
          <div className="space-y-8">
            {Object.keys(targetAllocation).map(assetClass => {
              const funds = getRecommendedFunds(assetClass);
              if (funds.length === 0) return null;
              
              return (
                <div key={assetClass}>
                  <h3 className="font-medium text-gray-900 mb-4">{assetClass} Funds</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {funds.map((fund, index) => (
                      <div key={index} className="border border-gray-200 rounded-lg p-5 hover:border-blue-500 transition-colors">
                        <div className="flex justify-between items-start mb-3">
                          <div>
                            <p className="font-medium text-gray-900">{fund.name}</p>
                            <p className="text-sm text-gray-600">{fund.type}</p>
                          </div>
                          <span className="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded">
                            TER: {fund.ter}%
                          </span>
                        </div>
                        
                        <div className="space-y-2">
                          <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Risk Level:</span>
                            <span className="font-medium">{
                              assetClass === 'Equity' ? 'High' :
                              assetClass === 'Debt' ? 'Low' :
                              assetClass === 'Gold' ? 'Medium' : 'Moderate'
                            }</span>
                          </div>
                          <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Min. Investment:</span>
                            <span className="font-medium">₹500</span>
                          </div>
                          <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Exit Load:</span>
                            <span className="font-medium">{'1% (<1 year)'}</span>
                          </div>
                        </div>
                        
                        <button
                          onClick={() => {
                            localStorage.setItem('investment_data', JSON.stringify({
                              fund: fund.name,
                              type: fund.type,
                              assetClass: assetClass
                            }));
                            router.push('/dashboard/invest');
                          }}
                          className="w-full mt-4 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
                        >
                          Invest Now
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Tax Implications */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Tax Implications of Rebalancing</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 className="font-medium text-gray-900 mb-4">Capital Gains Tax</h3>
              <div className="space-y-4">
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm font-medium text-gray-600">Equity Funds</span>
                    <span className="text-sm font-medium text-gray-900">15% STCG / 10% LTCG*</span>
                  </div>
                  <p className="text-xs text-gray-500">*LTCG above ₹1 lakh taxable at 10%</p>
                </div>
                
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-sm font-medium text-gray-600">Debt Funds</span>
                    <span className="text-sm font-medium text-gray-900">20% with indexation</span>
                  </div>
                  <p className="text-xs text-gray-500">For holdings {'>'} 3 years</p>
                </div>
                
                <div className="bg-gray-50 rounded-lg p-4">
                  <div className="flex justify-between items-center">
                    <span className="text-sm font-medium text-gray-600">Tax Loss Harvesting</span>
                    <span className="text-sm font-medium text-green-600">Opportunity</span>
                  </div>
                  <p className="text-xs text-gray-500 mt-2">
                    Offset gains with losses to reduce tax liability
                  </p>
                </div>
              </div>
            </div>
            
            <div>
              <h3 className="font-medium text-gray-900 mb-4">Rebalancing Strategy</h3>
              <div className="space-y-4">
                <div className="bg-blue-50 rounded-lg p-4">
                  <div className="flex items-center mb-2">
                    <svg className="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="font-medium text-blue-900">Strategy 1: Gradual Rebalancing</span>
                  </div>
                  <p className="text-sm text-blue-700">
                    Rebalance over 3-6 months to minimize tax impact
                  </p>
                </div>
                
                <div className="bg-green-50 rounded-lg p-4">
                  <div className="flex items-center mb-2">
                    <svg className="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="font-medium text-green-900">Strategy 2: Use New Investments</span>
                  </div>
                  <p className="text-sm text-green-700">
                    Redirect new SIPs to underweight assets instead of selling
                  </p>
                </div>
                
                <div className="bg-purple-50 rounded-lg p-4">
                  <div className="flex items-center mb-2">
                    <svg className="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    <span className="font-medium text-purple-900">Strategy 3: Dividend Reinvestment</span>
                  </div>
                  <p className="text-sm text-purple-700">
                    Use dividends and payouts to rebalance without selling
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap gap-4 justify-center">
          <button
            onClick={() => {
              generateRebalancingPlan(portfolioData);
              alert('Rebalancing plan updated with new targets!');
            }}
            className="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 flex items-center"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Update Plan
          </button>
          
          <button
            onClick={handleRebalance}
            disabled={!isBalanced}
            className={`px-6 py-3 font-medium rounded-lg flex items-center ${
              isBalanced 
                ? 'bg-green-600 text-white hover:bg-green-700' 
                : 'bg-gray-300 text-gray-500 cursor-not-allowed'
            }`}
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Save Rebalancing Plan
          </button>
          
          <button
            onClick={() => {
              const plan = {
                rebalancingPlan,
                targetAllocation,
                timestamp: new Date().toISOString()
              };
              const dataStr = JSON.stringify(plan, null, 2);
              const dataBlob = new Blob([dataStr], { type: 'application/json' });
              const url = URL.createObjectURL(dataBlob);
              const link = document.createElement('a');
              link.href = url;
              link.download = `rebalancing-plan-${new Date().toISOString().split('T')[0]}.json`;
              document.body.appendChild(link);
              link.click();
              document.body.removeChild(link);
              URL.revokeObjectURL(url);
            }}
            className="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 flex items-center"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export Plan
          </button>
          
          <Link
            href="/portfolio"
            className="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 flex items-center"
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
            Back to Portfolio
          </Link>
        </div>

        {/* Disclaimer */}
        <div className="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div className="flex items-start">
            <svg className="w-5 h-5 text-yellow-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.226 16.5c-.77.833.192 2.5 1.732 2.5" />
            </svg>
            <div>
              <p className="text-sm font-medium text-yellow-800">Disclaimer</p>
              <p className="text-sm text-yellow-700">
                This is an automated rebalancing suggestion based on your current portfolio. 
                Please consult with a financial advisor before making investment decisions. 
                Past performance is not indicative of future results. Consider your risk tolerance, 
                investment horizon, and tax implications before rebalancing.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
