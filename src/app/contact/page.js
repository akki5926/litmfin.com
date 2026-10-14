'use client';

import { useState, useEffect, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import siteConfig from "@/config/siteConfig";
import TypedText from '@/components/TypedText';

// Create a separate component for the content that uses useSearchParams
function ContactPageContent() {
  const searchParams = useSearchParams();
  
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    subject: '', 
    message: '',
    inquiryType: 'general'
  });

  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Read subject from URL query parameter on component mount
  useEffect(() => {
    const subjectParam = searchParams.get('subject');
    if (subjectParam) {
      setFormData(prev => ({
        ...prev,
        subject: decodeURIComponent(subjectParam)
      }));
    }
  }, [searchParams]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    
    try {
      // Determine the correct API URL
      let apiUrl;
      
      // Check if we're in development (localhost)
      if (typeof window !== 'undefined' && window.location.hostname === 'localhost') {
        // For local WAMP setup - adjust path based on your setup
        // If your Next.js runs on a different port than PHP
        const basePath = 'http://localhost/litmfin.com';
        apiUrl = `${basePath}/api/contact.php`;
      } else {
        // Production - use relative path or env variable
        const apiBase = (process.env.NEXT_PUBLIC_API_BASE_URL || '').replace(/\/$/, '');
        apiUrl = apiBase ? `${apiBase}/contact.php` : '/api/contact.php';
      }
      
      // console.log('Sending to:', apiUrl);
      // console.log('Form data:', formData);
      
      const response = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });
      
      // console.log('Response status:', response.status);
      
      // Try to parse response as JSON
      let data;
      const responseText = await response.text();
      // console.log('Response text:', responseText);
      
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        console.error('Failed to parse JSON:', responseText);
        throw new Error('Server returned invalid response');
      }
      
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to send message');
      }
      
      setSubmitted(true);
      
      // Reset form after successful submission
      setTimeout(() => {
        setSubmitted(false);
        setFormData({
          name: '',
          email: '',
          phone: '',
          subject: '',
          message: '',
          inquiryType: 'general'
        });
      }, 3000);
      
    } catch (err) {
      setError(err.message || 'Something went wrong. Please try again.');
      console.error('Error submitting form:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  // Helper function to check if an object has all required fields
  const isValidAddress = (address) => {
    return address && 
           address.name && 
           address.company && 
           address.address && 
           address.address.length > 0 &&
           address.city && 
           address.state && 
           address.pincode;
  };

  // Check if registered office address is valid
  const hasRegisteredOffice = isValidAddress(siteConfig.offices.registered);
  
  // Check if corporate office address is valid
  const hasCorporateOffice = isValidAddress(siteConfig.offices.corporate);

  // DYNAMICALLY build contact info from siteConfig - NO HARDCODING
  const contactInfo = [
    // Privacy & Legal (if available)
    ...((siteConfig.contact.privacyEmail) ? [{
      title: 'Privacy & Legal',
      email: siteConfig.contact.privacyEmail,
      phone: siteConfig.contact.phone || '',
      hours: 'Mon-Fri, 10AM-5PM'
    }] : [])
  ];

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text={`Contact ${siteConfig.siteName}`} className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Get in touch with our team</p>
        </div>
        
        <div className="grid lg:grid-cols-3 gap-8">
          {/* Contact Form */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-2xl shadow-lg p-8">
              <h2 className="text-2xl font-bold text-gray-900 mb-6">Send us a Message</h2>
              
              {submitted ? (
                <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                  <div className="text-green-600 text-4xl mb-3">✓</div>
                  <h3 className="text-lg font-bold text-green-800 mb-2">Message Sent!</h3>
                  <p className="text-green-700">We&apos;ll get back to you within 24 hours.</p>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                  {error && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                      {error}
                    </div>
                  )}
                  
                  <div className="grid md:grid-cols-2 gap-6">
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
                        placeholder="Enter your name"
                      />
                    </div>
                    
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Email Address *
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
                  </div>
                  
                  <div className="grid md:grid-cols-2 gap-6">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number
                      </label>
                      <input
                        type="tel"
                        name="phone"
                        value={formData.phone}
                        onChange={handleChange}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="+91 9876543210"
                      />
                    </div>
                    
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Inquiry Type
                      </label>
                      <select
                        name="inquiryType"
                        value={formData.inquiryType}
                        onChange={handleChange}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      >
                        <option value="general">General Inquiry</option>
                        <option value="investment">Investment Related</option>
                        <option value="technical">Technical Support</option>
                        <option value="partnership">Partnership</option>
                        <option value="feedback">Feedback/Suggestion</option>
                      </select>
                    </div>
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Subject *
                    </label>
                    <input
                      type="text"
                      name="subject"
                      value={formData.subject}
                      onChange={handleChange}
                      required
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      placeholder="What is this regarding?"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Message *
                    </label>
                    <textarea
                      name="message"
                      value={formData.message}
                      onChange={handleChange}
                      required
                      rows="5"
                      className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      placeholder="Please provide details about your inquiry"
                    />
                  </div>
                  
                  <button
                    type="submit"
                    disabled={loading}
                    className="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors disabled:bg-blue-400 disabled:cursor-not-allowed"
                  >
                    {loading ? 'Sending...' : 'Send Message'}
                  </button>
                </form>
              )}
            </div>
          </div>
          
          {/* Contact Information - DYNAMIC from siteConfig */}
          <div className="space-y-6">
            {contactInfo.map((info, index) => (
              <div key={index} className="bg-white rounded-xl shadow-lg p-6">
                <h3 className="text-lg font-bold text-gray-900 mb-4">{info.title}</h3>
                <div className="space-y-3">
                  {info.email && (
                    <div className="flex items-start gap-3">
                      <span className="text-gray-500 text-lg mt-0.5 flex-shrink-0">📧</span>
                      <a 
                        href={`mailto:${info.email}`} 
                        className="text-gray-700 hover:text-blue-600 break-all"
                      >
                        {info.email}
                      </a>
                    </div>
                  )}
                  {info.phone && (
                    <div className="flex items-start gap-3">
                      <span className="text-gray-500 text-lg mt-0.5 flex-shrink-0">📞</span>
                      <a 
                        href={`tel:${info.phone.replace(/\s+/g, '')}`} 
                        className="text-gray-700 hover:text-blue-600"
                      >
                        {info.phone}
                      </a>
                    </div>
                  )}
                  {info.hours && (
                    <div className="flex items-start gap-3">
                      <span className="text-gray-500 text-lg mt-0.5 flex-shrink-0">⏰</span>
                      <span className="text-gray-700">{info.hours}</span>
                    </div>
                  )}
                </div>
              </div>
            ))}
            
            {/* Registered Office Address - DYNAMIC */}
            {hasRegisteredOffice && (
              <div className="bg-blue-50 border border-blue-100 rounded-xl p-6">
                <div className="flex items-start gap-3 mb-4">
                  <span className="text-blue-600 text-xl flex-shrink-0">🏠</span>
                  <h3 className="text-lg font-bold text-blue-800">
                    {siteConfig.offices.registered.name}
                  </h3>
                </div>
                <div className="space-y-3 ml-9">
                  <p className="font-medium text-blue-900">
                    {siteConfig.offices.registered.company}
                  </p>
                  <div className="text-blue-800 space-y-1">
                    {siteConfig.offices.registered.address.map((line, index) => (
                      <p key={index}>{line}</p>
                    ))}
                  </div>
                  <a 
                    href={siteConfig.offices.registered.mapsUrl || `https://maps.google.com/?q=${encodeURIComponent(
                      `${siteConfig.offices.registered.address.join(', ')}, ${siteConfig.offices.registered.city}, ${siteConfig.offices.registered.state} ${siteConfig.offices.registered.pincode}`
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 text-sm font-medium mt-2"
                  >
                    <span className="text-lg">📍</span>
                    View on Google Maps
                  </a>
                </div>
              </div>
            )}
            
            {/* Corporate Office Address - DYNAMIC */}
            {hasCorporateOffice && (
              <div className="bg-green-50 border border-green-100 rounded-xl p-6">
                <div className="flex items-start gap-3 mb-4">
                  <span className="text-green-600 text-xl flex-shrink-0">🏢</span>
                  <h3 className="text-lg font-bold text-green-800">
                    {siteConfig.offices.corporate.name}
                  </h3>
                </div>
                <div className="space-y-3 ml-9">
                  <p className="font-medium text-blue-900">
                    {siteConfig.offices.corporate.company}
                  </p>
                  <div className="text-blue-800 space-y-1">
                    {siteConfig.offices.corporate.address.map((line, index) => (
                      <p key={index}>{line}</p>
                    ))}
                  </div>
                  <a 
                    href={siteConfig.offices.corporate.mapsUrl || `https://maps.google.com/?q=${encodeURIComponent(
                      `${siteConfig.offices.corporate.address.join(', ')}, ${siteConfig.offices.corporate.city}, ${siteConfig.offices.corporate.state} ${siteConfig.offices.corporate.pincode}`
                    )}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 text-green-600 hover:text-green-800 text-sm font-medium mt-2"
                  >
                    <span className="text-lg">📍</span>
                    View on Google Maps
                  </a>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// Main page component with Suspense boundary
export default function ContactPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-gray-50 py-12 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading contact page...</p>
        </div>
      </div>
    }>
      <ContactPageContent />
    </Suspense>
  );
}