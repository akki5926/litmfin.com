'use client';

import { useState, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import ReCAPTCHA from 'react-google-recaptcha';
import TypedText from '@/components/TypedText';
import siteConfig from "@/config/siteConfig";

export default function SignupPage() {
  const [formData, setFormData] = useState({
    fullName: '',
    email: '',
    mobile: '',
    pan: '',
    password: '',
    confirmPassword: '',
    address: '',
    city: '',
    state: '',
    pincode: ''
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showPAN, setShowPAN] = useState(false);
  const [captchaToken, setCaptchaToken] = useState(null);
  const [clientCode, setClientCode] = useState('');
  const recaptchaRef = useRef(null);
  const router = useRouter();

  const RECAPTCHA_SITE_KEY = process.env.NEXT_PUBLIC_RECAPTCHA_SITE_KEY;

  // API Base URL from environment
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL;

  // Validation functions
  const validateEmail = (email) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  const validateMobile = (mobile) => {
    return /^[6-9]\d{9}$/.test(mobile);
  };

  const validatePAN = (pan) => {
    return /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.toUpperCase());
  };

  const validatePassword = (password) => {
    return password.length >= 8;
  };

  const validateForm = () => {
    const errors = {};
    
    // Full Name validation
    if (!formData.fullName.trim()) {
      errors.fullName = 'Full name is required';
    } else if (formData.fullName.trim().length < 3) {
      errors.fullName = 'Full name must be at least 3 characters';
    }
    
    // Email validation
    if (!formData.email.trim()) {
      errors.email = 'Email is required';
    } else if (!validateEmail(formData.email)) {
      errors.email = 'Please enter a valid email address';
    }
    
    // Mobile validation
    if (!formData.mobile.trim()) {
      errors.mobile = 'Mobile number is required';
    } else if (!validateMobile(formData.mobile)) {
      errors.mobile = 'Please enter a valid 10-digit mobile number (starting with 6-9)';
    }
    
    // PAN validation
    if (!formData.pan.trim()) {
      errors.pan = 'PAN number is required';
    } else if (!validatePAN(formData.pan)) {
      errors.pan = 'Please enter a valid PAN number (format: ABCDE1234F)';
    }
    
    // Password validation
    if (!formData.password) {
      errors.password = 'Password is required';
    } else if (!validatePassword(formData.password)) {
      errors.password = 'Password must be at least 8 characters';
    }
    
    // Confirm Password validation
    if (!formData.confirmPassword) {
      errors.confirmPassword = 'Please confirm your password';
    } else if (formData.password !== formData.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match';
    }
    
    // Address validation
    if (formData.address && formData.address.length > 120) {
      errors.address = 'Address must be less than 120 characters';
    }
    
    // City validation
    if (formData.city && formData.city.length > 35) {
      errors.city = 'City must be less than 35 characters';
    }
    
    // State validation
    if (formData.state && formData.state.length > 5) {
      errors.state = 'State code must be less than 5 characters';
    }
    
    // Pincode validation
    if (formData.pincode && (!/^\d{6}$/.test(formData.pincode) || parseInt(formData.pincode) < 100000)) {
      errors.pincode = 'Please enter a valid 6-digit pincode';
    }
    
    // Captcha validation
    const isLocal = typeof window !== 'undefined' && 
      (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
    if (!captchaToken && !isLocal) {
      errors.captcha = 'Please verify that you are not a robot';
    }
    
    // Terms validation
    if (!termsAccepted) {
      errors.terms = 'Please accept the Terms & Conditions';
    }
    
    return errors;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setSuccess('');

    // Validate form
    const errors = validateForm();
    if (Object.keys(errors).length > 0) {
      setError(Object.values(errors)[0]);
      setLoading(false);
      return;
    }

    try {
      // Call PHP API for registration
      // console.log('Calling registration API...');
      const response = await fetch(`${API_BASE_URL}/users.php?action=signup`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          fullName: formData.fullName.trim(),
          email: formData.email.trim(),
          mobile: formData.mobile.trim(),
          pan: formData.pan.toUpperCase().trim(),
          password: formData.password,
          address: formData.address.trim(),
          city: formData.city.trim(),
          state: formData.state.trim().toUpperCase(),
          pincode: formData.pincode.trim(),
          captchaToken: captchaToken || 'local_bypass'
        })
      });

      // console.log('Registration response status:', response.status);
      const result = await response.json();
      // console.log('Registration result:', result);

      if (result.success) {
        setClientCode(result.data?.clientCode || '');
        
        // Store user data temporarily for verification
        localStorage.setItem('temp_user', JSON.stringify({
          email: formData.email,
          mobile: formData.mobile,
          clientCode: result.data?.clientCode,
          name: formData.fullName
        }));
        
        setSuccess('Registration successful! Check your email for verification link.');
        
        // Redirect to verification page after 5 seconds
        setTimeout(() => {
          router.push('/verify');
        }, 5000);
      } else {
        setError(result.message || 'Registration failed. Please try again.');
        // Reset captcha on failed submission so user can try again
        recaptchaRef.current?.reset();
        setCaptchaToken(null);
      }
    } catch (err) {
      console.error('Signup error:', err);
      setError('Network error. Please check your connection and try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    
    // Auto-uppercase for PAN
    if (name === 'pan') {
      setFormData(prev => ({ ...prev, [name]: value.toUpperCase() }));
    } 
    // Only numbers for mobile
    else if (name === 'mobile') {
      const numericValue = value.replace(/\D/g, '').slice(0, 10);
      setFormData(prev => ({ ...prev, [name]: numericValue }));
    }
    // Only numbers for pincode
    else if (name === 'pincode') {
      const numericValue = value.replace(/\D/g, '').slice(0, 6);
      setFormData(prev => ({ ...prev, [name]: numericValue }));
    }
    // Auto-uppercase for state (2-letter code)
    else if (name === 'state') {
      setFormData(prev => ({ ...prev, [name]: value.toUpperCase() }));
    }
    else {
      setFormData(prev => ({ ...prev, [name]: value }));
    }
    
    // Clear error when user starts typing
    if (error) setError('');
  };

  const getPasswordStrength = () => {
    if (!formData.password) return 0;
    
    let strength = 0;
    if (formData.password.length >= 8) strength++;
    if (/[a-z]/.test(formData.password)) strength++;
    if (/[A-Z]/.test(formData.password)) strength++;
    if (/\d/.test(formData.password)) strength++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(formData.password)) strength++;
    
    return strength;
  };

  const getStrengthColor = () => {
    const strength = getPasswordStrength();
    if (strength <= 2) return 'bg-red-500';
    if (strength <= 3) return 'bg-yellow-500';
    return 'bg-green-500';
  };

  const getStrengthText = () => {
    const strength = getPasswordStrength();
    if (strength <= 2) return 'Weak';
    if (strength <= 3) return 'Medium';
    return 'Strong';
  };

  const getStrengthTextColor = () => {
    const strength = getPasswordStrength();
    if (strength <= 2) return 'text-red-600';
    if (strength <= 3) return 'text-yellow-600';
    return 'text-green-600';
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
      <div className="py-8 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto">
          {/* Header */}
          <div className="text-center mb-8">
            <h1 className="text-3xl font-bold text-gray-900 mb-3">
              <TypedText text={`Create Your ${siteConfig.siteName} Account`} className="text-gray-900" />
            </h1>
            <p className="text-gray-600">
              Join thousands of investors and start your financial journey
            </p>
          </div>

          {success && (
            <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-fadeIn">
              <div className="flex items-center">
                <svg className="w-5 h-5 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                <div className="flex-1">
                  <p className="text-green-800 font-medium">{success}</p>
                  {clientCode && (
                    <p className="text-green-700 text-sm mt-1">
                      Your Client Code: <span className="font-bold">{clientCode}</span>
                    </p>
                  )}
                  <div className="mt-3">
                    <p className="text-green-600 text-sm">
                      Please check your email inbox (and spam folder) for the verification link.
                    </p>
                    <div className="flex items-center mt-2">
                      <Link
                        href="/verify"
                        className="text-sm text-blue-600 hover:text-blue-800 font-medium hover:underline"
                      >
                        Go to verification page
                      </Link>
                    </div>
                  </div>
                  <p className="text-green-600 text-sm mt-2">
                    Redirecting to verification page...
                  </p>
                </div>
              </div>
            </div>
          )}

          <div className="grid lg:grid-cols-2 gap-8">
            {/* Left Column - Form */}
            <div>
              <div className="bg-white rounded-2xl shadow-xl p-6">
                <h2 className="text-xl font-bold text-gray-900 mb-6">
                  Registration Form
                </h2>

                <form onSubmit={handleSubmit} className="space-y-5">
                  {/* Full Name */}
                  <div className="grid grid-cols-4 gap-4 items-center">
                    <label className="col-span-1 text-gray-700 font-medium">
                      Full Name *
                    </label>
                    <div className="col-span-3">
                      <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                          <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                          </svg>
                        </div>
                        <input
                          type="text"
                          name="fullName"
                          value={formData.fullName}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                          placeholder="Enter your full name"
                          required
                          maxLength="70"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Email */}
                  <div className="grid grid-cols-4 gap-4 items-center">
                    <label className="col-span-1 text-gray-700 font-medium">
                      Email *
                    </label>
                    <div className="col-span-3">
                      <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                          <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                          </svg>
                        </div>
                        <input
                          type="email"
                          name="email"
                          value={formData.email}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                          placeholder="Enter your email address"
                          required
                          maxLength="50"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Mobile */}
                  <div className="grid grid-cols-4 gap-4 items-center">
                    <label className="col-span-1 text-gray-700 font-medium">
                      Mobile *
                    </label>
                    <div className="col-span-3">
                      <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                          <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                          </svg>
                        </div>
                        <input
                          type="tel"
                          name="mobile"
                          value={formData.mobile}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                          placeholder="10-digit mobile number"
                          required
                          maxLength="10"
                          pattern="[6-9]{1}[0-9]{9}"
                        />
                      </div>
                      <p className="text-xs text-gray-500 mt-1">
                        Must start with 6, 7, 8, or 9
                      </p>
                    </div>
                  </div>

                  {/* PAN */}
                  <div className="grid grid-cols-4 gap-4 items-center">
                    <label className="col-span-1 text-gray-700 font-medium">
                      PAN *
                    </label>
                    <div className="col-span-3">
                      <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                          <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                        </div>
                        <input
                          type={showPAN ? "text" : "password"}
                          name="pan"
                          value={formData.pan}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50 uppercase"
                          placeholder="PAN (e.g., ABCDE1234F)"
                          required
                          maxLength="10"
                          pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPAN(!showPAN)}
                          disabled={loading}
                          className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 focus:outline-none"
                        >
                          {showPAN ? (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.815 7.815L21 21m-3.96-3.96-3.011-3.011m0 0a3 3 0 0 1-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                          ) : (
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.543 8.356 7.244 4.5 12 4.5c4.756 0 8.757 3.856 9.965 7.178.07.207.07.421 0 .644-1.351 3.222-5.351 7.178-9.965 7.178-4.756 0-8.757-3.856-9.965-7.178Z" />
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                          )}
                        </button>
                      </div>
                      <p className="text-xs text-gray-500 mt-1">
                        Format: 5 letters, 4 numbers, 1 letter
                      </p>
                    </div>
                  </div>

                  {/* Password */}
                  <div className="grid grid-cols-4 gap-4 items-start">
                    <label className="col-span-1 text-gray-700 font-medium mt-3">
                      Password *
                    </label>
                    <div className="col-span-3">
                      <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                          <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                          </svg>
                        </div>
                        <input
                          type={showPassword ? "text" : "password"}
                          name="password"
                          value={formData.password}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                          placeholder="Create a strong password"
                          required
                          minLength="8"
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(!showPassword)}
                          disabled={loading}
                          className="absolute inset-y-0 right-0 pr-3 flex items-center"
                        >
                          {showPassword ? (
                            <svg className="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.59 6.59m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                          ) : (
                            <svg className="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                          )}
                        </button>
                      </div>
                      
                      {/* Password Requirements Info */}
                      <div className="mt-2 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                        <p className="text-sm font-medium text-blue-800 mb-2">Password must contain:</p>
                        <ul className="text-xs text-blue-700 space-y-1">
                          <li className="flex items-center">
                            <span className={`mr-2 ${formData.password.length >= 8 ? 'text-green-600' : 'text-gray-400'}`}>
                              {formData.password.length >= 8 ? '✓' : '○'}
                            </span>
                            Minimum 8 characters
                          </li>
                          <li className="flex items-center">
                            <span className={`mr-2 ${/[a-z]/.test(formData.password) ? 'text-green-600' : 'text-gray-400'}`}>
                              {/[a-z]/.test(formData.password) ? '✓' : '○'}
                            </span>
                            At least one lowercase letter
                          </li>
                          <li className="flex items-center">
                            <span className={`mr-2 ${/[A-Z]/.test(formData.password) ? 'text-green-600' : 'text-gray-400'}`}>
                              {/[A-Z]/.test(formData.password) ? '✓' : '○'}
                            </span>
                            At least one uppercase letter
                          </li>
                          <li className="flex items-center">
                            <span className={`mr-2 ${/\d/.test(formData.password) ? 'text-green-600' : 'text-gray-400'}`}>
                              {/\d/.test(formData.password) ? '✓' : '○'}
                            </span>
                            At least one number
                          </li>
                          <li className="flex items-center">
                            <span className={`mr-2 ${/[!@#$%^&*(),.?":{}|<>]/.test(formData.password) ? 'text-green-600' : 'text-gray-400'}`}>
                              {/[!@#$%^&*(),.?":{}|<>]/.test(formData.password) ? '✓' : '○'}
                            </span>
                            At least one special character
                          </li>
                        </ul>
                      </div>
                      
                      {/* Password Strength */}
                      {formData.password && (
                        <div className="mt-2">
                          <div className="flex justify-between text-sm mb-1">
                            <span className="text-gray-600">Password strength:</span>
                            <span className={`font-medium ${getStrengthTextColor()}`}>
                              {getStrengthText()}
                            </span>
                          </div>
                          <div className="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                              className={`h-full ${getStrengthColor()} transition-all duration-300`}
                              style={{ width: `${(getPasswordStrength() / 5) * 100}%` }}
                            ></div>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Confirm Password */}
                  <div className="grid grid-cols-4 gap-4 items-center">
                    <label className="col-span-1 text-gray-700 font-medium">
                      Confirm Password *
                    </label>
                    <div className="col-span-3">
                      <input
                        type="password"
                        name="confirmPassword"
                        value={formData.confirmPassword}
                        onChange={handleChange}
                        disabled={loading}
                        className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                        placeholder="Confirm your password"
                        required
                      />
                    </div>
                  </div>

                  {/* Address (Optional) */}
                  <div className="grid grid-cols-4 gap-4 items-center">
                    <label className="col-span-1 text-gray-700 font-medium">
                      Address
                    </label>
                    <div className="col-span-3">
                      <input
                        type="text"
                        name="address"
                        value={formData.address}
                        onChange={handleChange}
                        disabled={loading}
                        className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                        placeholder="Enter your address"
                        maxLength="120"
                      />
                    </div>
                  </div>

                  {/* City, State, Pincode in one row */}
                  <div className="grid grid-cols-4 gap-4">
                    <div className="col-span-1">
                      <label className="block text-gray-700 mb-2 font-medium">
                        City
                      </label>
                    </div>
                    <div className="col-span-3 grid grid-cols-3 gap-4">
                      <div>
                        <input
                          type="text"
                          name="city"
                          value={formData.city}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                          placeholder="City"
                          maxLength="35"
                        />
                      </div>

                      <div>
                        <input
                          type="text"
                          name="state"
                          value={formData.state}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50 uppercase"
                          placeholder="State"
                          maxLength="5"
                        />
                      </div>

                      <div>
                        <input
                          type="text"
                          name="pincode"
                          value={formData.pincode}
                          onChange={handleChange}
                          disabled={loading}
                          className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-colors disabled:bg-gray-50"
                          placeholder="Pincode"
                          maxLength="6"
                          pattern="\d{6}"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Google reCAPTCHA */}
                  <div className="mt-4">
                    <ReCAPTCHA
                      ref={recaptchaRef}
                      sitekey={RECAPTCHA_SITE_KEY}
                      onChange={(token) => {
                        setCaptchaToken(token);
                        if (error) setError('');
                      }}
                      onExpired={() => {
                        setCaptchaToken(null);
                        setError('CAPTCHA expired. Please verify again.');
                      }}
                      onErrored={() => {
                        setCaptchaToken(null);
                        setError('CAPTCHA failed to load. Please refresh the page.');
                      }}
                    />
                  </div>

                  {/* Terms & Conditions */}
                  <div className="flex items-start mt-4">
                    <input
                      type="checkbox"
                      id="terms"
                      checked={termsAccepted}
                      onChange={(e) => setTermsAccepted(e.target.checked)}
                      disabled={loading}
                      className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1"
                    />
                    <label htmlFor="terms" className="ml-2 text-sm text-gray-700">
                      I agree to the{' '}
                      <Link href="/terms" className="text-blue-600 hover:underline">
                        Terms of Service
                      </Link>
                      {' '}and{' '}
                      <Link href="/privacy-policy" className="text-blue-600 hover:underline">
                        Privacy Policy
                      </Link>
                      . I understand that {siteConfig.siteName} will use my PAN for KYC verification and send verification emails to my registered email address.
                    </label>
                  </div>

                  {/* Error Message */}
                  {error && (
                    <div className="p-3 bg-red-50 border border-red-200 rounded-lg mb-4">
                      <div className="flex items-center">
                        <svg className="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                        <span className="text-red-700">{error}</span>
                      </div>
                    </div>
                  )}

                  {/* Success Message */}
                  {success && (
                    <div className="p-3 bg-green-50 border border-green-200 rounded-lg mb-4">
                      <div className="flex items-center">
                        <svg className="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                        <span className="text-green-700">{success}</span>
                      </div>
                    </div>
                  )}

                  {/* Submit Button */}
                  <button
                    type="submit"
                    disabled={loading || !captchaToken || !termsAccepted}
                    className={`w-full py-3 px-4 rounded-lg font-semibold text-white transition-colors ${
                      loading || !captchaToken || !termsAccepted
                        ? 'bg-gray-400 cursor-not-allowed'
                        : 'bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900'
                    }`}
                  >
                    {loading ? (
                      <div className="flex items-center justify-center">
                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating Account...
                      </div>
                    ) : (
                      'Create Account'
                    )}
                  </button>

                  {/* Already have account */}
                  <div className="text-center pt-4 border-t border-gray-200">
                    <p className="text-gray-600">
                      Already have an account?{' '}
                      <Link
                        href="/login"
                        className="text-blue-600 font-semibold hover:text-blue-800 hover:underline transition-colors"
                      >
                        Sign In
                      </Link>
                    </p>
                  </div>
                </form>
              </div>

              {/* Security Note */}
              {/* <div className="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <div className="flex items-start">
                  <svg className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
                  </svg>
                  <div>
                    <p className="text-sm text-blue-800 font-medium">Email Verification Required</p>
                    <p className="text-xs text-blue-600 mt-1">
                      After registration, you&apos;ll receive a verification email from the backend. Please check your inbox and spam folder. You must verify your email to access your account.
                    </p>
                  </div>
                </div>
              </div> */}
            </div>

            {/* Right Column - Benefits & Info */}
            <div className="space-y-6">
              {/* Benefits Card */}
              <div className="bg-white rounded-2xl shadow-xl p-6">
                <h2 className="text-xl font-bold text-gray-900 mb-6">
                  Why Join {siteConfig.siteName}?
                </h2>
                
                <div className="space-y-5">
                  <div className="flex items-start space-x-3">
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                      </div>
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">Bank-Level Security</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        256-bit SSL encryption, ISO 27001 certified, and regular security audits.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start space-x-3">
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                      </div>
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">Real-time Analytics</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        Track your investments with live market data and intelligent insights.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start space-x-3">
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </div>
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">Smart Investments</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        AI-powered recommendations for optimal returns and portfolio growth.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start space-x-3">
                    <div className="flex-shrink-0">
                      <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg className="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                      </div>
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">Dedicated Support</h3>
                      <p className="text-sm text-gray-600 mt-1">
                        24/7 customer support and personalized financial advisory services.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Email Verification Info */}
              <div className="bg-indigo-50 border border-indigo-200 rounded-2xl p-6">
                <div className="flex items-start">
                  <svg className="w-6 h-6 text-indigo-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                  </svg>
                  <div>
                    <h3 className="font-semibold text-indigo-900 mb-3">Email Verification Process</h3>
                    <ol className="text-sm text-indigo-800 space-y-2">
                      <li className="flex items-start">
                        <span className="mr-2 font-bold">1.</span>
                        <span>Complete registration form</span>
                      </li>
                      <li className="flex items-start">
                        <span className="mr-2 font-bold">2.</span>
                        <span>Check your email for verification link (sent automatically)</span>
                      </li>
                      <li className="flex items-start">
                        <span className="mr-2 font-bold">3.</span>
                        <span>Click the verification link in email</span>
                      </li>
                      {/* <li className="flex items-start">
                        <span className="mr-2 font-bold">4.</span>
                        <span>Complete mobile OTP verification</span>
                      </li> */}
                      <li className="flex items-start">
                        <span className="mr-2 font-bold">4.</span>
                        <span>Start your investment journey!</span>
                      </li>
                    </ol>
                    <p className="text-xs text-indigo-600 mt-3">
                      <strong>Note:</strong> The verification email is sent automatically from the backend. If you don&apos;t see it, please check your spam folder.
                    </p>
                  </div>
                </div>
              </div>

              {/* Requirements Card */}
              <div className="bg-yellow-50 border border-yellow-200 rounded-2xl p-6">
                <div className="flex items-start">
                  <svg className="w-6 h-6 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                  </svg>
                  <div>
                    <h3 className="font-semibold text-yellow-900 mb-3">Requirements & Information</h3>
                    <ul className="text-sm text-yellow-800 space-y-2">
                      <li className="flex items-start">
                        <span className="mr-2">•</span>
                        <span>PAN card is mandatory for KYC verification</span>
                      </li>
                      {/* <li className="flex items-start">
                        <span className="mr-2">•</span>
                        <span>Valid mobile number for OTP verification</span>
                      </li> */}
                      <li className="flex items-start">
                        <span className="mr-2">•</span>
                        <span>Email address for account communication</span>
                      </li>
                      {/* <li className="flex items-start">
                        <span className="mr-2">•</span>
                        <span>Minimum investment: ₹500 for mutual funds</span>
                      </li> */}
                      <li className="flex items-start">
                        <span className="mr-2">•</span>
                        <span>Email verification required for account activation</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              {/* Trust Badges */}
              {/* <div className="grid grid-cols-2 gap-4">
                <div className="bg-white p-4 rounded-xl shadow-sm border text-center">
                  <div className="text-lg font-bold text-blue-600">SEBI</div>
                  <div className="text-xs text-gray-600 mt-1">Registered</div>
                </div>
                <div className="bg-white p-4 rounded-xl shadow-sm border text-center">
                  <div className="text-lg font-bold text-green-600">AMFI</div>
                  <div className="text-xs text-gray-600 mt-1">Certified</div>
                </div>
                <div className="bg-white p-4 rounded-xl shadow-sm border text-center">
                  <div className="text-lg font-bold text-purple-600">ISO</div>
                  <div className="text-xs text-gray-600 mt-1">27001:2013</div>
                </div>
                <div className="bg-white p-4 rounded-xl shadow-sm border text-center">
                  <div className="text-lg font-bold text-orange-600">KYC</div>
                  <div className="text-xs text-gray-600 mt-1">Compliant</div>
                </div>
              </div> */}

              {/* Disclaimer */}
              <div className="bg-red-50 border border-red-100 rounded-lg p-4">
                <p className="text-xs text-red-800 text-center">
                  <span className="font-semibold">Investment Disclaimer:</span> Mutual fund investments are subject to market risks. Please read all scheme related documents carefully before investing. Past performance is not indicative of future returns.
                </p>
              </div>
            </div>
          </div>

          {/* Footer - Only copyright */}
          {/* <div className="mt-8 text-center">
            <p className="text-xs text-gray-400">
              © {new Date().getFullYear()} {siteConfig.siteName} Technologies Pvt. Ltd. All rights reserved.<br/>
              Registration No.: INH200007123 | SEBI Registration No.: INZ000200937
            </p>
          </div> */}
        </div>
      </div>
    </div>
  );
}