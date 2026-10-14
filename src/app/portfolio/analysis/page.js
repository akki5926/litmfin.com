'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

export default function PortfolioAnalysisPage() {
  const router = useRouter();
  const [analysisData, setAnalysisData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const data = localStorage.getItem('portfolio_analysis');
    if (data) {
      setAnalysisData(JSON.parse(data));
    }
    setLoading(false);
  }, []);

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

  const formatPercentage = (value) => {
    if (!value && value !== 0) return '0.00%';
    return `${value >= 0 ? '+' : ''}${value.toFixed(2)}%`;
  };

  const getRiskColor = (score) => {
    if (score <= 3) return 'text-green-600 bg-green-100';
    if (score <= 6) return 'text-yellow-600 bg-yellow-100';
    return 'text-red-600 bg-red-100';
  };

  const getScoreColor = (score) => {
    if (score >= 7) return 'text-green-600 bg-green-100';
    if (score >= 4) return 'text-yellow-600 bg-yellow-100';
    return 'text-red-600 bg-red-100';
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading analysis...</p>
        </div>
      </div>
    );
  }

  if (!analysisData) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center max-w-md p-8 bg-white rounded-xl shadow-lg">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-xl font-bold text-gray-900 mb-2">No Analysis Data</h3>
          <p className="text-gray-600 mb-6">Please analyze your portfolio from the main portfolio page</p>
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

  const { riskAnalysis, diversification, performance, costAnalysis, recommendations } = analysisData;

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
                  <h1 className="text-3xl font-bold text-gray-900">Portfolio Analysis</h1>
                  <p className="text-gray-600 mt-2">Deep insights into your Indian mutual fund portfolio</p>
                </div>
              </div>
              <p className="text-sm text-gray-500 mt-2">
                Last analyzed: {new Date(analysisData.timestamp).toLocaleDateString('en-IN', {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit'
                })}
              </p>
            </div>
            <div className="mt-4 sm:mt-0">
              <button
                onClick={() => window.print()}
                className="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors flex items-center"
              >
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Print Report
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Executive Summary */}
        <div className="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl shadow-lg p-8 mb-8 text-white">
          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h2 className="text-2xl font-bold mb-4">Portfolio Health Summary</h2>
              <div className="space-y-2">
                <div className="flex items-center">
                  <div className={`w-3 h-3 rounded-full mr-3 ${getRiskColor(riskAnalysis.overallRiskScore).includes('green') ? 'bg-green-300' : getRiskColor(riskAnalysis.overallRiskScore).includes('yellow') ? 'bg-yellow-300' : 'bg-red-300'}`}></div>
                  <span className="font-medium">Risk Profile: </span>
                  <span className="ml-2">
                    {riskAnalysis.overallRiskScore <= 3 ? 'Conservative' : 
                     riskAnalysis.overallRiskScore <= 6 ? 'Moderate' : 'Aggressive'}
                  </span>
                </div>
                <div className="flex items-center">
                  <div className="w-3 h-3 rounded-full mr-3 bg-green-300"></div>
                  <span className="font-medium">Diversification: </span>
                  <span className="ml-2">
                    {diversification.fundHouseConcentration.concentrationScore < 50 ? 'Excellent' : 
                     diversification.fundHouseConcentration.concentrationScore < 70 ? 'Good' : 'Needs Improvement'}
                  </span>
                </div>
                <div className="flex items-center">
                  <div className="w-3 h-3 rounded-full mr-3 bg-blue-300"></div>
                  <span className="font-medium">Cost Efficiency: </span>
                  <span className="ml-2">
                    {costAnalysis.totalExpenseRatio < 1.5 ? 'Excellent' : 
                     costAnalysis.totalExpenseRatio < 2.0 ? 'Good' : 'High'}
                  </span>
                </div>
              </div>
            </div>
            <div className="mt-6 lg:mt-0 lg:text-right">
              <div className="text-5xl font-bold mb-2">
                {performance.portfolioXIRR >= 0 ? '+' : ''}{performance.portfolioXIRR.toFixed(1)}%
              </div>
              <p className="text-blue-100">Portfolio XIRR</p>
              <div className="mt-4 flex items-center justify-center lg:justify-end space-x-4">
                <div className="text-center">
                  <div className="text-2xl font-bold">{performance.sharpeRatio.toFixed(2)}</div>
                  <p className="text-sm text-blue-200">Sharpe Ratio</p>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold">{performance.sortinoRatio.toFixed(2)}</div>
                  <p className="text-sm text-blue-200">Sortino Ratio</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Risk Analysis Section */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Risk Analysis</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Overall Risk Score</h3>
                <span className={`px-3 py-1 rounded-full text-sm font-medium ${getRiskColor(riskAnalysis.overallRiskScore)}`}>
                  {riskAnalysis.overallRiskScore.toFixed(1)}/10
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${riskAnalysis.overallRiskScore <= 3 ? 'bg-green-500' : riskAnalysis.overallRiskScore <= 6 ? 'bg-yellow-500' : 'bg-red-500'}`}
                  style={{ width: `${riskAnalysis.overallRiskScore * 10}%` }}
                ></div>
              </div>
              <p className="text-sm text-gray-600 mt-2">
                {riskAnalysis.overallRiskScore <= 3 ? 'Low risk portfolio' : 
                 riskAnalysis.overallRiskScore <= 6 ? 'Moderate risk portfolio' : 
                 'High risk portfolio. Consider rebalancing.'}
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Volatility Score</h3>
                <span className={`px-3 py-1 rounded-full text-sm font-medium ${getRiskColor(riskAnalysis.volatilityScore)}`}>
                  {riskAnalysis.volatilityScore.toFixed(1)}/10
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${riskAnalysis.volatilityScore <= 3 ? 'bg-green-500' : riskAnalysis.volatilityScore <= 6 ? 'bg-yellow-500' : 'bg-red-500'}`}
                  style={{ width: `${riskAnalysis.volatilityScore * 10}%` }}
                ></div>
              </div>
              <p className="text-sm text-gray-600 mt-2">
                Expected fluctuation in portfolio value
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Concentration Risk</h3>
                <span className={`px-3 py-1 rounded-full text-sm font-medium ${getRiskColor(riskAnalysis.concentrationRisk)}`}>
                  {riskAnalysis.concentrationRisk.toFixed(1)}/10
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${riskAnalysis.concentrationRisk <= 3 ? 'bg-green-500' : riskAnalysis.concentrationRisk <= 6 ? 'bg-yellow-500' : 'bg-red-500'}`}
                  style={{ width: `${riskAnalysis.concentrationRisk * 10}%` }}
                ></div>
              </div>
              <p className="text-sm text-gray-600 mt-2">
                {riskAnalysis.concentrationRisk <= 3 ? 'Well diversified' : 
                 'Consider spreading investments'}
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Liquidity Score</h3>
                <span className={`px-3 py-1 rounded-full text-sm font-medium ${getScoreColor(riskAnalysis.liquidityScore)}`}>
                  {riskAnalysis.liquidityScore.toFixed(1)}/10
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className={`h-2 rounded-full ${riskAnalysis.liquidityScore >= 7 ? 'bg-green-500' : riskAnalysis.liquidityScore >= 4 ? 'bg-yellow-500' : 'bg-red-500'}`}
                  style={{ width: `${riskAnalysis.liquidityScore * 10}%` }}
                ></div>
              </div>
              <p className="text-sm text-gray-600 mt-2">
                {riskAnalysis.liquidityScore >= 7 ? 'High liquidity' : 
                 riskAnalysis.liquidityScore >= 4 ? 'Moderate liquidity' : 
                 'Low liquidity. Some funds may have lock-in.'}
              </p>
            </div>
          </div>

          <div className="bg-blue-50 rounded-lg p-6">
            <h3 className="font-medium text-gray-900 mb-4">Risk Profile Interpretation</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="text-center p-4 bg-white rounded-lg">
                <div className="text-2xl font-bold text-green-600 mb-2">1-3</div>
                <h4 className="font-medium text-gray-900 mb-1">Conservative</h4>
                <p className="text-sm text-gray-600">Suitable for short-term goals, low risk tolerance</p>
              </div>
              <div className="text-center p-4 bg-white rounded-lg">
                <div className="text-2xl font-bold text-yellow-600 mb-2">4-6</div>
                <h4 className="font-medium text-gray-900 mb-1">Moderate</h4>
                <p className="text-sm text-gray-600">Balanced approach for medium-term goals</p>
              </div>
              <div className="text-center p-4 bg-white rounded-lg">
                <div className="text-2xl font-bold text-red-600 mb-2">7-10</div>
                <h4 className="font-medium text-gray-900 mb-1">Aggressive</h4>
                <p className="text-sm text-gray-600">For long-term goals, high risk tolerance</p>
              </div>
            </div>
          </div>
        </div>

        {/* Diversification Analysis */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Diversification Analysis</h2>
          
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Asset Class Distribution */}
            <div>
              <h3 className="font-medium text-gray-900 mb-4">Asset Class Distribution</h3>
              <div className="space-y-4">
                {Object.entries(diversification.assetClassDistribution).map(([asset, data]) => (
                  <div key={asset} className="bg-gray-50 rounded-lg p-4">
                    <div className="flex justify-between items-center mb-2">
                      <span className="font-medium text-gray-900">{asset}</span>
                      <span className="text-lg font-bold text-gray-900">{data.percentage.toFixed(1)}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="h-2 rounded-full bg-blue-500"
                        style={{ width: `${data.percentage}%` }}
                      ></div>
                    </div>
                    <p className="text-sm text-gray-600 mt-2">
                      {formatCurrency(data.value)}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* Fund House Concentration */}
            <div>
              <h3 className="font-medium text-gray-900 mb-4">Fund House Concentration</h3>
              <div className="space-y-4">
                {diversification.fundHouseConcentration.topFundHouses.map((fundHouse, index) => (
                  <div key={index} className="bg-gray-50 rounded-lg p-4">
                    <div className="flex justify-between items-center mb-2">
                      <span className="font-medium text-gray-900 truncate">{fundHouse.name}</span>
                      <span className="text-lg font-bold text-gray-900">{fundHouse.percentage.toFixed(1)}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="h-2 rounded-full bg-green-500"
                        style={{ width: `${fundHouse.percentage}%` }}
                      ></div>
                    </div>
                    <p className="text-sm text-gray-600 mt-2">
                      {formatCurrency(fundHouse.value)}
                    </p>
                  </div>
                ))}
                <div className="bg-blue-50 rounded-lg p-4">
                  <div className="flex items-center">
                    <svg className="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                      <p className="text-sm font-medium text-blue-900">Concentration Score</p>
                      <p className="text-lg font-bold text-blue-900">
                        {diversification.fundHouseConcentration.concentrationScore.toFixed(1)}%
                      </p>
                      <p className="text-xs text-blue-700 mt-1">
                        {diversification.fundHouseConcentration.concentrationScore < 50 ? 'Well diversified' : 
                         diversification.fundHouseConcentration.concentrationScore < 70 ? 'Moderate concentration' : 
                         'High concentration risk'}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Market Cap Distribution */}
          {diversification.marketCapDistribution && (
            <div className="mt-8">
              <h3 className="font-medium text-gray-900 mb-4">Equity Market Cap Distribution</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {Object.entries(diversification.marketCapDistribution).map(([cap, percentage]) => (
                  <div key={cap} className="bg-gray-50 rounded-lg p-6 text-center">
                    <div className="text-3xl font-bold text-gray-900 mb-2">{percentage.toFixed(1)}%</div>
                    <p className="font-medium text-gray-900">{cap}</p>
                    <p className="text-sm text-gray-600 mt-1">
                      {cap === 'Large Cap' ? 'Established companies, stable returns' :
                       cap === 'Mid Cap' ? 'Growing companies, moderate risk' :
                       'Emerging companies, high growth potential'}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Performance Analysis */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Performance Analysis</h2>
          
          {/* Benchmark Comparison */}
          <div className="mb-8">
            <h3 className="font-medium text-gray-900 mb-4">Benchmark Comparison</h3>
            <div className="space-y-4">
              {performance.benchmarkComparison.map((comparison, index) => (
                <div key={index} className="bg-gray-50 rounded-lg p-4">
                  <div className="flex justify-between items-center mb-3">
                    <div>
                      <p className="font-medium text-gray-900">{comparison.assetClass}</p>
                      <p className="text-sm text-gray-600">vs {comparison.benchmarkName}</p>
                    </div>
                    <div className={`px-3 py-1 rounded-full text-sm font-medium ${comparison.outperformance >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                      {formatPercentage(comparison.outperformance)}
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="text-center p-3 bg-white rounded-lg">
                      <p className="text-sm text-gray-600">Your Return</p>
                      <p className={`text-lg font-bold ${comparison.portfolioReturn >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {formatPercentage(comparison.portfolioReturn)}
                      </p>
                    </div>
                    <div className="text-center p-3 bg-white rounded-lg">
                      <p className="text-sm text-gray-600">Benchmark</p>
                      <p className="text-lg font-bold text-gray-900">
                        {formatPercentage(comparison.benchmarkReturn)}
                      </p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Risk-Adjusted Returns */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-green-900">Sharpe Ratio</h3>
                <div className={`px-3 py-1 rounded-full text-sm font-medium ${
                  performance.sharpeRatio > 1 ? 'bg-green-200 text-green-800' :
                  performance.sharpeRatio > 0 ? 'bg-yellow-200 text-yellow-800' :
                  'bg-red-200 text-red-800'
                }`}>
                  {performance.sharpeRatio.toFixed(2)}
                </div>
              </div>
              <p className="text-sm text-green-800">
                {performance.sharpeRatio > 1 ? 'Excellent risk-adjusted returns' :
                 performance.sharpeRatio > 0 ? 'Acceptable risk-adjusted returns' :
                 'Poor risk-adjusted returns'}
              </p>
            </div>

            <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-blue-900">Sortino Ratio</h3>
                <div className={`px-3 py-1 rounded-full text-sm font-medium ${
                  performance.sortinoRatio > 1.5 ? 'bg-green-200 text-green-800' :
                  performance.sortinoRatio > 1 ? 'bg-yellow-200 text-yellow-800' :
                  'bg-red-200 text-red-800'
                }`}>
                  {performance.sortinoRatio.toFixed(2)}
                </div>
              </div>
              <p className="text-sm text-blue-800">
                Measures downside risk protection
              </p>
            </div>

            <div className="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-purple-900">Rolling Returns</h3>
                <div className="text-2xl font-bold text-purple-900">
                  {performance.portfolioXIRR.toFixed(1)}%
                </div>
              </div>
              <p className="text-sm text-purple-800">
                Annualized returns over different periods
              </p>
            </div>
          </div>
        </div>

        {/* Cost Analysis */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Cost Analysis</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Total Expense Ratio (TER)</h3>
                <span className={`text-2xl font-bold ${
                  costAnalysis.totalExpenseRatio < 1.5 ? 'text-green-600' :
                  costAnalysis.totalExpenseRatio < 2.0 ? 'text-yellow-600' :
                  'text-red-600'
                }`}>
                  {costAnalysis.totalExpenseRatio.toFixed(2)}%
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2 mb-3">
                <div 
                  className={`h-2 rounded-full ${
                    costAnalysis.totalExpenseRatio < 1.5 ? 'bg-green-500' :
                    costAnalysis.totalExpenseRatio < 2.0 ? 'bg-yellow-500' :
                    'bg-red-500'
                  }`}
                  style={{ width: `${Math.min(costAnalysis.totalExpenseRatio * 40, 100)}%` }}
                ></div>
              </div>
              <p className="text-sm text-gray-600">
                {costAnalysis.totalExpenseRatio < 1.5 ? 'Low-cost portfolio' :
                 costAnalysis.totalExpenseRatio < 2.0 ? 'Average costs' :
                 'High-cost portfolio. Consider direct plans.'}
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Exit Load Impact</h3>
                <span className="text-2xl font-bold text-gray-900">
                  {costAnalysis.exitLoadImpact.toFixed(2)}%
                </span>
              </div>
              <p className="text-sm text-gray-600 mb-3">
                Average exit load if you redeem all holdings today
              </p>
              <p className="text-xs text-gray-500">
                Lower exit loads indicate better liquidity
              </p>
            </div>

            <div className="bg-gray-50 rounded-lg p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-medium text-gray-900">Potential Tax Impact</h3>
                <span className="text-2xl font-bold text-red-600">
                  {formatCurrency(costAnalysis.taxImplications.potentialTax.total)}
                </span>
              </div>
              <p className="text-sm text-gray-600">
                Estimated tax on unrealized gains (LTCG)
              </p>
              <div className="mt-3 space-y-1">
                <div className="flex justify-between text-sm">
                  <span>Equity Funds:</span>
                  <span>{formatCurrency(costAnalysis.taxImplications.potentialTax.equity)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span>Debt Funds:</span>
                  <span>{formatCurrency(costAnalysis.taxImplications.potentialTax.debt)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span>Gold Funds:</span>
                  <span>{formatCurrency(costAnalysis.taxImplications.potentialTax.gold)}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Recommendations */}
        <div className="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-6">Recommendations</h2>
          
          {recommendations.length > 0 ? (
            <div className="space-y-4">
              {recommendations.map((rec, index) => (
                <div key={index} className={`border-l-4 ${
                  rec.priority === 'high' ? 'border-red-500 bg-red-50' :
                  rec.priority === 'medium' ? 'border-yellow-500 bg-yellow-50' :
                  'border-blue-500 bg-blue-50'
                } rounded-r-lg p-5`}>
                  <div className="flex items-start">
                    <div className={`mr-4 px-2 py-1 rounded text-xs font-medium ${
                      rec.priority === 'high' ? 'bg-red-100 text-red-800' :
                      rec.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-blue-100 text-blue-800'
                    }`}>
                      {rec.priority.toUpperCase()}
                    </div>
                    <div className="flex-1">
                      <div className="flex justify-between items-start mb-2">
                        <h3 className="font-bold text-gray-900">{rec.title}</h3>
                        <span className={`text-xs px-2 py-1 rounded-full ${
                          rec.type === 'risk' ? 'bg-purple-100 text-purple-800' :
                          rec.type === 'performance' ? 'bg-green-100 text-green-800' :
                          rec.type === 'cost' ? 'bg-red-100 text-red-800' :
                          'bg-blue-100 text-blue-800'
                        }`}>
                          {rec.type.charAt(0).toUpperCase() + rec.type.slice(1)}
                        </span>
                      </div>
                      <p className="text-gray-700 mb-3">{rec.description}</p>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="bg-white rounded-lg p-3">
                          <p className="text-sm font-medium text-gray-600 mb-1">Recommended Action</p>
                          <p className="text-gray-900">{rec.action}</p>
                        </div>
                        <div className="bg-white rounded-lg p-3">
                          <p className="text-sm font-medium text-gray-600 mb-1">Expected Impact</p>
                          <p className="text-gray-900">{rec.impact}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8">
              <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 className="text-lg font-bold text-gray-900 mb-2">Excellent Portfolio Health</h3>
              <p className="text-gray-600">Your portfolio is well-structured. Keep up the good work!</p>
            </div>
          )}
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap gap-4 justify-center">
          <button
            onClick={() => {
              const dataStr = JSON.stringify(analysisData, null, 2);
              const dataBlob = new Blob([dataStr], { type: 'application/json' });
              const url = URL.createObjectURL(dataBlob);
              const link = document.createElement('a');
              link.href = url;
              link.download = `portfolio-analysis-${new Date().toISOString().split('T')[0]}.json`;
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
            Export Analysis Data
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
          
          <button
            onClick={() => router.push('/portfolio/rebalance')}
            className="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 flex items-center"
            >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Rebalance Portfolio using AI
            </button>
        </div>
      </div>
    </div>
  );
}