// app/dashboard/risk-profile/page.js
'use client';

import { useState, useEffect } from 'react';
import TypedText from '@/components/TypedText';

export default function RiskProfilePage() {
  const [selectedRisk, setSelectedRisk] = useState(null);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [showSuccessMessage, setShowSuccessMessage] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [savedRiskProfile, setSavedRiskProfile] = useState(null);
  const [userId, setUserId] = useState(null);
  const [error, setError] = useState(null);
  const [debugInfo, setDebugInfo] = useState({});
  
  // API base URL - Use environment variable or default
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ;

  
  // Get user ID on component mount
  useEffect(() => {
    const fetchUserId = async () => {
      // console.log('🔍 Attempting to get user ID...');
      
      // Try multiple methods to get user ID
      let foundUserId = null;
      let source = '';
      
      // Method 1: Check localStorage for various keys
      const storageKeys = [
        'site_user',
        'site_user_id', 
        'userId',
        'user_id',
        'user',
        'site_token',
        'token',
        'auth_token'
      ];
      
      for (const key of storageKeys) {
        try {
          const value = localStorage.getItem(key);
          if (value) {
            // console.log(`Found ${key}:`, value.substring(0, 50) + '...');
            
            // Try to parse as JSON
            try {
              const parsed = JSON.parse(value);
              // console.log(`Parsed ${key}:`, parsed);
              
              // Look for ID in parsed object
              if (parsed.id) {
                foundUserId = parsed.id.toString();
                source = `localStorage.${key}.id`;
                break;
              } else if (parsed.userId) {
                foundUserId = parsed.userId.toString();
                source = `localStorage.${key}.userId`;
                break;
              } else if (parsed.user_id) {
                foundUserId = parsed.user_id.toString();
                source = `localStorage.${key}.user_id`;
                break;
              }
            } catch (e) {
              // Not JSON, might be a simple ID or token
              if (key.includes('token')) {
                // Try to decode JWT
                try {
                  const parts = value.split('.');
                  if (parts.length === 3) {
                    const payload = JSON.parse(atob(parts[1]));
                    // console.log('JWT payload:', payload);
                    if (payload.userId || payload.id || payload.sub) {
                      foundUserId = (payload.userId || payload.id || payload.sub).toString();
                      source = `localStorage.${key} (JWT)`;
                      break;
                    }
                  }
                } catch (e) {
                  console.log('Not a JWT token or cannot decode');
                }
              } else if (key.includes('id') || key.includes('user')) {
                // Might be a direct ID
                foundUserId = value;
                source = `localStorage.${key} (direct)`;
                break;
              }
            }
          }
        } catch (e) {
          console.error(`Error reading ${key}:`, e);
        }
      }
      
      // Method 2: Check sessionStorage
      if (!foundUserId) {
        for (const key of storageKeys) {
          try {
            const value = sessionStorage.getItem(key);
            if (value) {
              // console.log(`Found sessionStorage.${key}:`, value.substring(0, 50) + '...');
              
              try {
                const parsed = JSON.parse(value);
                if (parsed.id) {
                  foundUserId = parsed.id.toString();
                  source = `sessionStorage.${key}.id`;
                  break;
                }
              } catch (e) {
                if (key.includes('id') || key.includes('user')) {
                  foundUserId = value;
                  source = `sessionStorage.${key} (direct)`;
                  break;
                }
              }
            }
          } catch (e) {
            console.error(`Error reading sessionStorage.${key}:`, e);
          }
        }
      }
      
      // Method 3: Try to get from API endpoint (if you have one)
      if (!foundUserId) {
        try {
          // console.log('Trying to get user from API...');
          const response = await fetch(`${API_BASE_URL}/get_current_user.php`, {
            credentials: 'include'
          });
          
          if (response.ok) {
            const userData = await response.json();
            // console.log('API user data:', userData);
            if (userData.id) {
              foundUserId = userData.id.toString();
              source = 'API endpoint';
            }
          }
        } catch (e) {
          console.log('API endpoint not available or failed');
        }
      }
      
      if (foundUserId) {
        // console.log(`✅ User ID found: ${foundUserId} (source: ${source})`);
        setUserId(foundUserId);
        setDebugInfo(prev => ({ ...prev, userId: foundUserId, source }));
        
        // Store for future use
        localStorage.setItem('site_user_id', foundUserId);
      } else {
        console.log('❌ No user ID found');
        setError('Please log in to access risk profile selection.');
        setIsLoading(false);
        
        // For testing only - remove in production
        if (process.env.NODE_ENV === 'development') {
          console.log('⚠️ DEV MODE: Using test user ID');
          const testUserId = '1'; // Change this to match your test user
          setUserId(testUserId);
          setDebugInfo(prev => ({ ...prev, userId: testUserId, source: 'DEV MODE' }));
          localStorage.setItem('site_user_id', testUserId);
        }
      }
    };
    
    fetchUserId();
  }, [API_BASE_URL]);
  
  // Fetch user's risk profile when userId is available
  useEffect(() => {
    if (userId) {
      fetchRiskProfile();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);
  
  const fetchRiskProfile = async () => {
  try {
    setIsLoading(true);
    setError(null);
    
    // console.log('📡 Fetching risk profile...');
    // console.log('User ID:', userId);
    // console.log('API URL:', `${API_BASE_URL}/risk_profile_api.php`);
    
    // First try with query parameter (most reliable)
    let response;
    try {
      response = await fetch(`${API_BASE_URL}/risk_profile_api.php?userId=${userId}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include',
      });
      
      // console.log('Response (query method):', response.status, response.statusText);
    } catch (queryError) {
      console.log('Query method failed, trying header...');
      
      // Fallback to header method
      response = await fetch(`${API_BASE_URL}/risk_profile_api.php`, {
        method: 'GET',
        headers: {
          'X-User-ID': userId,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include',
      });
      
      // console.log('Response (header method):', response.status, response.statusText);
    }
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Response error:', errorText);
      throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
    }
    
    const result = await response.json();
    // console.log('API Result:', result);
    
    setDebugInfo(prev => ({ ...prev, apiResponse: result }));
    
    if (result.success) {
      if (result.data.hasRiskProfile) {
        // console.log('✅ Risk profile found:', result.data.riskProfile);
        setSavedRiskProfile(result.data.riskProfile);
        setSelectedRisk(result.data.riskProfile.riskId);
        setIsSubmitted(true);
      } else {
        console.log('ℹ️ No risk profile set yet');
        setSavedRiskProfile(null);
        setIsSubmitted(false);
      }
    } else {
      console.error('❌ API returned error:', result.message);
      setError(result.message || 'Failed to load risk profile');
    }
  } catch (error) {
    console.error('❌ Error fetching risk profile:', error);
    setError(`Unable to connect: ${error.message}`);
  } finally {
    setIsLoading(false);
  }
};
  
  // Risk options configuration
  const riskOptions = [
    {
      id: 'high',
      title: 'High Risk Tolerance',
      description: '30% Negative to capital invested',
      details: 'You are comfortable with high volatility and potential for significant capital erosion in exchange for potentially higher returns.',
      color: 'from-red-50 to-red-100',
      borderColor: 'border-red-200',
      textColor: 'text-red-800',
      icon: '🔥'
    },
    {
      id: 'medium',
      title: 'Medium Risk Tolerance',
      description: '10% Negative to capital invested',
      details: 'You accept moderate volatility with limited capital erosion while aiming for balanced growth.',
      color: 'from-yellow-50 to-yellow-100',
      borderColor: 'border-yellow-200',
      textColor: 'text-yellow-800',
      icon: '⚖️'
    },
    {
      id: 'low',
      title: 'Low Risk Tolerance',
      description: 'Not expecting capital erosion',
      details: 'You prefer stability and preservation of capital with minimal volatility.',
      color: 'from-green-50 to-green-100',
      borderColor: 'border-green-200',
      textColor: 'text-green-800',
      icon: '🛡️'
    },
    {
      id: 'conservative',
      title: 'Conservative',
      description: 'Minimum FD returns expected',
      details: 'You seek capital protection and stable returns similar to fixed deposit rates.',
      color: 'from-blue-50 to-blue-100',
      borderColor: 'border-blue-200',
      textColor: 'text-blue-800',
      icon: '💰'
    }
  ];

  // Portfolio recommendations
  const portfolioRecommendations = {
    high: {
      equity: '70-80%',
      debt: '10-15%',
      gold: '5-10%',
      cash: '0-5%',
      description: 'Aggressive growth portfolio focusing on equity with minimal debt exposure',
      color: 'bg-red-100 text-red-800'
    },
    medium: {
      equity: '50-60%',
      debt: '30-35%',
      gold: '5-10%',
      cash: '5-10%',
      description: 'Balanced portfolio with moderate equity and significant debt allocation',
      color: 'bg-yellow-100 text-yellow-800'
    },
    low: {
      equity: '0-10%',
      debt: '60-70%',
      gold: '5-10%',
      cash: '5-10%',
      description: 'Conservative portfolio emphasizing debt instruments and capital preservation',
      color: 'bg-green-100 text-green-800'
    },
    conservative: {
      equity: '0',
      debt: '80-90%',
      gold: '5%',
      cash: '5-10%',
      description: 'Very conservative portfolio focused on fixed income instruments',
      color: 'bg-blue-100 text-blue-800'
    }
  };

  // Handle risk selection
  const handleRiskSelection = (riskId) => {
    if (!isSubmitted) {
      setSelectedRisk(riskId);
      // console.log('Selected risk:', riskId);
    }
  };

  // Handle form submission
 // Handle form submission
const handleSubmit = async () => {
  if (!selectedRisk || !userId) {
    alert('Please select a risk profile and ensure you are logged in.');
    return;
  }
  
  if (isSubmitted) {
    alert('Risk profile already saved and cannot be changed.');
    return;
  }
  
  try {
    setIsLoading(true);
    setError(null);
    
    // console.log('💾 Saving risk profile...');
    // console.log('Selected:', selectedRisk);
    // console.log('User ID:', userId);
    
    const saveData = {
      riskProfileId: selectedRisk,
      userId: userId
    };
    
    // console.log('Sending data:', saveData);
    
    let response;
    try {
      // Try with userId in the POST body
      response = await fetch(`${API_BASE_URL}/risk_profile_api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(saveData)
      });
      
      // console.log('Save response (POST with body):', response.status);
    } catch (headerError) {
      console.log('First method failed, trying query parameter...');
      
      // Fallback: Send userId as query parameter
      response = await fetch(`${API_BASE_URL}/risk_profile_api.php?userId=${userId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ riskProfileId: selectedRisk })
      });
      
      // console.log('Save response (query param):', response.status);
    }
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Save error response:', errorText);
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    // console.log('Save result:', result);
    
    setDebugInfo(prev => ({ ...prev, saveResponse: result }));
    
    if (result.success) {
      // console.log('✅ Risk profile saved successfully!');
      
      setIsSubmitted(true);
      setShowSuccessMessage(true);
      setSavedRiskProfile(result.data);
      
      // Hide success message after 5 seconds
      setTimeout(() => {
        setShowSuccessMessage(false);
      }, 5000);
    } else {
      console.error('❌ Save failed:', result.message);
      alert(`Save failed: ${result.message}`);
    }
  } catch (error) {
    console.error('❌ Error saving risk profile:', error);
    alert(`Network error: ${error.message}. Please check your connection.`);
  } finally {
    setIsLoading(false);
  }
};

  // Handle clear selection
  const handleClearSelection = () => {
    if (!isSubmitted) {
      setSelectedRisk(null);
      // console.log('Selection cleared');
    }
  };

  // Loading state
  if (isLoading && !error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
          <p className="mt-4 text-gray-600 font-medium">Loading your risk profile...</p>
          <p className="mt-2 text-sm text-gray-500">User ID: {userId || 'Fetching...'}</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error && !userId) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-6 text-center">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-5V6m0 0h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-gray-900 mb-2">Authentication Required</h2>
          <p className="text-gray-600 mb-6">{error}</p>
          <button
            onClick={() => window.location.href = '/login'}
            className="w-full py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors"
          >
            Go to Login
          </button>
          <div className="mt-6 p-4 bg-gray-50 rounded-lg text-left">
            <p className="text-sm font-medium text-gray-700 mb-2">Debug Info:</p>
            <pre className="text-xs text-gray-600 overflow-auto">
              {JSON.stringify(debugInfo, null, 2)}
            </pre>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-4 md:p-6">
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">
            <TypedText text="Risk Profile Assessment" className="text-gray-900" />
          </h1>
          <p className="text-gray-600">
            Select your risk tolerance to get personalized investment recommendations. <br></br>
            <span className="font-semibold text-red-600 ml-1">Once selected, this cannot be changed.</span>
          </p>
        </div>

        {/* Debug Panel - Only in development */}
        {/* {process.env.NODE_ENV === 'development' && (
          <div className="mb-6 p-4 bg-gray-800 text-white rounded-lg">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-mono font-bold">🔧 Debug Information</h3>
              <button
                onClick={() => setDebugInfo({})}
                className="text-sm bg-gray-700 px-2 py-1 rounded"
              >
                Clear
              </button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <div>
                <span className="text-gray-400">User ID:</span> 
                <span className="ml-2 font-mono">{userId || 'Not found'}</span>
              </div>
              <div>
                <span className="text-gray-400">Status:</span>
                <span className={`ml-2 px-2 py-1 rounded ${isSubmitted ? 'bg-green-600' : 'bg-yellow-600'}`}>
                  {isSubmitted ? 'Profile Saved' : 'Not Saved'}
                </span>
              </div>
              <div>
                <span className="text-gray-400">Selected:</span>
                <span className="ml-2">{selectedRisk || 'None'}</span>
              </div>
              <div>
                <span className="text-gray-400">API Base:</span>
                <span className="ml-2 font-mono truncate">{API_BASE_URL}</span>
              </div>
            </div>
            {Object.keys(debugInfo).length > 0 && (
              <details className="mt-3">
                <summary className="cursor-pointer text-gray-300">Full Debug Data</summary>
                <pre className="mt-2 p-2 bg-gray-900 rounded text-xs overflow-auto max-h-40">
                  {JSON.stringify(debugInfo, null, 2)}
                </pre>
              </details>
            )}
          </div>
        )} */}

        {/* Error Display */}
        {error && (
          <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-red-600 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p className="text-red-800">{error}</p>
              <button
                onClick={fetchRiskProfile}
                className="ml-auto text-sm bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200"
              >
                Retry
              </button>
            </div>
          </div>
        )}

        {/* Saved Profile Notice */}
        {isSubmitted && savedRiskProfile && !showSuccessMessage && (
          <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-blue-600 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                
                <p className="text-blue-700 text-sm mt-1">
                  This selection is permanent and cannot be changed.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Success Message */}
        {showSuccessMessage && savedRiskProfile && (
          <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-pulse">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-green-600 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              <p className="text-green-800 font-medium">
                ✅ Your {savedRiskProfile.riskName} risk profile has been saved successfully!
              </p>
            </div>
          </div>
        )}

        {/* Risk Selection Section */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-2">
              What is your Risk Appetite over a 3-year time horizon?
            </h2>
            <p className="text-gray-600">
              Select the option that best describes your comfort level with investment risk and potential losses.
            </p>
          </div>

          <div className="space-y-4">
            {riskOptions.map((option) => {
              const isSavedOption = isSubmitted && savedRiskProfile?.riskId === option.id;
              const isSelected = selectedRisk === option.id;
              
              return (
                <div
                  key={option.id}
                  onClick={() => !isSubmitted && handleRiskSelection(option.id)}
                  className={`relative border-2 rounded-xl p-5 transition-all duration-200 ${
                    isSelected
                      ? `${option.borderColor} ${option.color} border-opacity-100 scale-[1.02] shadow-md`
                      : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                  } ${
                    isSubmitted 
                      ? 'cursor-not-allowed opacity-80' 
                      : 'cursor-pointer'
                  }`}
                >
                  {isSavedOption && (
                    <div className="absolute -top-2 -right-2 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow">
                      SELECTED
                    </div>
                  )}
                  
                  <div className="flex items-start">
                    <div className={`flex-shrink-0 w-6 h-6 mt-0.5 border-2 rounded-full mr-4 flex items-center justify-center ${
                      isSelected
                        ? 'border-blue-500 bg-blue-500' 
                        : 'border-gray-300'
                    }`}>
                      {isSelected && (
                        <div className="w-3 h-3 bg-white rounded-full"></div>
                      )}
                    </div>
                    
                    <div className="flex-1">
                      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                        <div className="flex items-center">
                          <span className="text-xl mr-3">{option.icon}</span>
                          <div>
                            <h3 className="font-bold text-gray-900 text-lg">{option.title}</h3>
                            {isSavedOption && (
                              <span className="inline-block mt-1 px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">
                                Your Current Selection
                              </span>
                            )}
                          </div>
                        </div>
                        
                        <span className={`inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium ${option.textColor} ${option.color.split(' ')[0].replace('from-', 'bg-')} bg-opacity-30`}>
                          {option.description}
                        </span>
                      </div>
                      
                      <p className="mt-3 text-gray-600 pl-10">{option.details}</p>
                      
                      {isSelected && !isSubmitted && (
                        <div className="mt-3 pl-10">
                          <span className="inline-flex items-center text-sm text-blue-600 font-medium">
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Currently selected
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Important Warning */}
          <div className="mt-8 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div className="flex items-start">
              <svg className="w-5 h-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.786 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <div>
                <p className="font-bold text-red-800">⚠️ IMPORTANT WARNING</p>
                <p className="text-red-700 mt-1">
                  Your risk profile selection is <strong>PERMANENT and CANNOT BE CHANGED</strong> once saved. 
                  All future portfolio recommendations will be based on this selection.
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Action Buttons */}
        {!isSubmitted ? (
          <div className="flex flex-col sm:flex-row gap-4">
            <button
              onClick={handleSubmit}
              disabled={!selectedRisk || isLoading}
              className={`flex-1 py-4 px-6 rounded-xl font-bold text-lg transition-all flex items-center justify-center ${
                selectedRisk && !isLoading
                  ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg hover:shadow-xl'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              }`}
            >
              {isLoading ? (
                <>
                  <div className="inline-block animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-3"></div>
                  Saving Profile...
                </>
              ) : (
                <>
                  <svg className="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  CONFIRM RISK PROFILE SELECTION
                </>
              )}
            </button>
            
            <button
              onClick={handleClearSelection}
              disabled={!selectedRisk || isLoading}
              className={`py-4 px-6 rounded-xl font-medium transition-colors ${
                selectedRisk && !isLoading
                  ? 'bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300'
                  : 'bg-gray-100 text-gray-400 cursor-not-allowed'
              }`}
            >
              Clear Selection
            </button>
          </div>
        ) : (
          <div className="text-center p-6 bg-gray-50 rounded-xl border border-gray-200">
            <p className="text-gray-700 font-medium">
              Your risk profile has been saved. You can view your selection above.
            </p>
            <p className="text-sm text-gray-500 mt-2">
              Need help? Contact our support team for assistance.
            </p>
          </div>
        )}

        {/* Information Section */}
        <div className="mt-10 bg-white rounded-xl border border-gray-200 p-6">
          <h3 className="text-xl font-bold text-gray-900 mb-4">Understanding Risk Profiles</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h4 className="font-semibold text-gray-800 mb-3">Why Risk Profiling Matters</h4>
              <ul className="space-y-3">
                <li className="flex items-start">
                  <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <span className="text-blue-600 text-sm">1</span>
                  </div>
                  <span className="text-gray-600">Helps align investments with your financial goals and time horizon</span>
                </li>
                <li className="flex items-start">
                  <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <span className="text-blue-600 text-sm">2</span>
                  </div>
                  <span className="text-gray-600">Prevents emotional decision-making during market volatility</span>
                </li>
                <li className="flex items-start">
                  <div className="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <span className="text-blue-600 text-sm">3</span>
                  </div>
                  <span className="text-gray-600">Ensures appropriate asset allocation for your comfort level</span>
                </li>
              </ul>
            </div>
            
            <div>
              <h4 className="font-semibold text-gray-800 mb-3">Key Considerations</h4>
              <ul className="space-y-3">
                <li className="flex items-start">
                  <div className="flex-shrink-0 w-6 h-6 bg-red-100 rounded-full flex items-center justify-center mr-3">
                    <svg className="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.786 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                  </div>
                  <span className="text-gray-600"><strong>Permanent Decision:</strong> Once saved, your risk profile cannot be changed</span>
                </li>
                <li className="flex items-start">
                  <div className="flex-shrink-0 w-6 h-6 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                    <svg className="w-3 h-3 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <span className="text-gray-600">Based on a 3-year investment horizon - consider your long-term goals</span>
                </li>
                <li className="flex items-start">
                  <div className="flex-shrink-0 w-6 h-6 bg-green-100 rounded-full flex items-center justify-center mr-3">
                    <svg className="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <span className="text-gray-600">Consult with a financial advisor if you need personalized guidance</span>
                </li>
              </ul>
            </div>
          </div>
          
          <div className="mt-8 pt-6 border-t border-gray-200">
            <p className="text-sm text-gray-500">
              <strong>Disclaimer:</strong> This risk profile assessment provides general guidelines only and should not be considered as financial advice. 
              Investment decisions should be based on your individual circumstances and consultation with qualified financial professionals. 
              Past performance is not indicative of future results. All investments carry risk, including the possible loss of principal.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}