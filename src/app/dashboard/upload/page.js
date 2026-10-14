// app/dashboard/upload/page.js
'use client';

import { useState, useEffect } from 'react';
import TypedText from '@/components/TypedText';

export default function UploadPage() {
  const [fileName, setFileName] = useState('');
  const [fileBase64, setFileBase64] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [uploadStatus, setUploadStatus] = useState(null);
  const [uploadDetails, setUploadDetails] = useState(null);
  const [userBankDetails, setUserBankDetails] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  // API base URL - Use environment variable or default
  const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ;

  // Fetch user bank details on component mount
  useEffect(() => {
    fetchUserBankDetails();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Function to fetch user bank details using bank_api.php
  const fetchUserBankDetails = async () => {
    try {
      setIsLoading(true);
      
      // Get token from localStorage
      const token = localStorage.getItem('site_token');
      
      if (!token) {
        setUploadStatus({
          type: 'error',
          message: 'Authentication required. Please login first.'
        });
        setIsLoading(false);
        return;
      }
      
      const response = await fetch(`${API_BASE_URL}/bank_api.php`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        mode: 'cors',
        credentials: 'include'
      });
      
      // Get response text first
      const responseText = await response.text();
      
      // Check if response is empty
      if (!responseText.trim()) {
        throw new Error('Empty response from server');
      }
      
      // Check if response is HTML
      if (responseText.trim().startsWith('<!DOCTYPE') || responseText.includes('<html')) {
        throw new Error('Server returned HTML instead of JSON. Check PHP errors.');
      }
      
      // Parse JSON response
      const result = JSON.parse(responseText);
      
      if (result.success) {
        if (result.data) {
          // Format the data - handle different field name formats
          const formattedData = {
            client_code: result.data.CLIENT_CODE || 
                        result.data.client_code || 
                        result.data.ClientCode || 
                        '',
            account_no: result.data.accountNo || 
                       result.data.account_no || 
                       result.data.AccountNo || 
                       result.data.accountNumber ||
                       '',
            account_holder_name: result.data.accountHolderName || 
                               result.data.account_holder_name || 
                               result.data.AccountHolderName || 
                               result.data.accountHolder ||
                               result.data.fullName ||
                               '',
            ifsc: result.data.ifscCode || 
                 result.data.ifsc || 
                 result.data.IfscCode || 
                 result.data.IFSC ||
                 '',
            account_type: result.data.accountType || 
                         result.data.account_type || 
                         result.data.AccountType || 
                         ''
          };
          
          // Check if we have minimum required data
          const hasRequiredData = formattedData.account_no && formattedData.ifsc;
          
          if (hasRequiredData) {
            setUserBankDetails(formattedData);
          } else {
            setUserBankDetails(null);
            setUploadStatus({
              type: 'warning',
              message: 'Bank details incomplete. Please update your bank account information.'
            });
          }
        } else {
          setUserBankDetails(null);
          setUploadStatus({
            type: 'info',
            message: result.message || 'No bank account found. Please set up your bank account first.'
          });
        }
      } else {
        setUserBankDetails(null);
        setUploadStatus({
          type: 'error',
          message: result.message || 'Failed to load bank details'
        });
      }
      
    } catch (error) {
      console.error('Error fetching bank details:', error);
      
      setUserBankDetails(null);
      setUploadStatus({
        type: 'error',
        message: `Failed to load bank details: ${error.message}.`
      });
    } finally {
      setIsLoading(false);
    }
  };

  // Handle file upload and convert to Base64
  const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
      // Check file type - ONLY TIF/TIFF ALLOWED
      const allowedTypes = ['image/tiff', 'image/tif', 'application/tiff', 'application/tif'];
      const fileExtension = file.name.toLowerCase().split('.').pop();
      
      // Check both MIME type and extension
      const isAllowedType = allowedTypes.includes(file.type) || ['tif', 'tiff'].includes(fileExtension);
      
      if (!isAllowedType) {
        setUploadStatus({
          type: 'error',
          message: 'Only TIF/TIFF files are allowed. Please upload a .tif or .tiff file.'
        });
        event.target.value = '';
        return;
      }
      
      // Check file size (5MB = 5 * 1024 * 1024 bytes)
      const maxSize = 5 * 1024 * 1024;
      if (file.size > maxSize) {
        setUploadStatus({
          type: 'error',
          message: 'File size should be less than 5MB.'
        });
        event.target.value = '';
        return;
      }
      
      // Check file name length
      if (file.name.length > 40) {
        setUploadStatus({
          type: 'error',
          message: 'File name must be 40 characters or less.'
        });
        event.target.value = '';
        return;
      }
      
      // Convert file to Base64
      const reader = new FileReader();
      
      reader.onload = (e) => {
        const base64String = e.target.result.split(',')[1]; // Remove data URL prefix
        setFileBase64(base64String);
        setUploadStatus({
          type: 'success',
          message: `File "${file.name}" ready for upload`
        });
      };
      
      reader.onerror = (error) => {
        console.error('Error reading file:', error);
        setUploadStatus({
          type: 'error',
          message: 'Error reading file. Please try again.'
        });
      };
      
      reader.readAsDataURL(file);
      
      setFileName(file.name);
      setUploadDetails(null); // Clear previous upload details
    }
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Check if user has bank details
    if (!userBankDetails) {
      setUploadStatus({
        type: 'error',
        message: 'Bank account details not found. Please complete bank account setup first.'
      });
      return;
    }
    
    // Validation
    if (!fileName || !fileBase64) {
      setUploadStatus({
        type: 'error',
        message: 'Please upload a cancelled cheque file'
      });
      return;
    }
    
    if (fileName.length > 40) {
      setUploadStatus({
        type: 'error',
        message: 'File name must be 40 characters or less'
      });
      return;
    }
    
    setIsSubmitting(true);
    setUploadStatus(null);
    setUploadDetails(null);
    
    try {
      // Prepare payload - ONLY file data is sent
      const payload = {
        file_name: fileName,
        file_data: fileBase64
      };
      
      // Get token from localStorage
      const token = localStorage.getItem('site_token');
      
      // Call API
      const response = await fetch(`${API_BASE_URL}/cancelled-cheque-upload.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token || ''}`,
        },
        body: JSON.stringify(payload),
        mode: 'cors',
        credentials: 'include'
      });
      
      // Check if response is JSON
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Non-JSON response from upload API:', text.substring(0, 200));
        throw new Error('Server returned non-JSON response. Check PHP errors.');
      }
      
      const result = await response.json();
      
      // Set upload details for display (we still receive it but don't show it)
      setUploadDetails(result.data);
      
      if (result.success) {
        setUploadStatus({
          type: 'success',
          message: result.message || 'Cancelled cheque uploaded successfully!'
        });
        
        // Reset form on complete success
        if (result.data?.local_storage?.success) {
          setTimeout(() => {
            setFileName('');
            setFileBase64('');
            
            // Reset file input
            const fileInput = document.getElementById('fileUpload');
            if (fileInput) {
              fileInput.value = '';
            }
          }, 3000);
        }
        
        // Clear success message after 8 seconds
        setTimeout(() => {
          setUploadStatus(null);
          setUploadDetails(null);
        }, 8000);
        
      } else {
        setUploadStatus({
          type: 'error',
          message: result.message || 'Upload failed. Please try again.'
        });
      }
      
    } catch (error) {
      console.error('Upload error:', error);
      setUploadStatus({
        type: 'error',
        message: error.message.includes('non-JSON') 
          ? 'Server error. Please check if the upload API is working correctly.' 
          : 'Network error. Please try again.'
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading your bank details...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Upload Documents Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          <TypedText text="Upload Cancelled Cheque" className="text-gray-900" />
        </h1>
        <p className="text-gray-600">
          Upload cancelled cheque for bank verification.
        </p>
      </div>

      {/* Upload Status Messages */}
      {uploadStatus && (
        <div className={`p-4 rounded-lg ${
          uploadStatus.type === 'success' 
            ? 'bg-green-50 text-green-800 border border-green-200' 
            : uploadStatus.type === 'error'
            ? 'bg-red-50 text-red-800 border border-red-200'
            : uploadStatus.type === 'warning'
            ? 'bg-yellow-50 text-yellow-800 border border-yellow-200'
            : 'bg-blue-50 text-blue-800 border border-blue-200'
        }`}>
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              {uploadStatus.type === 'success' ? (
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
              ) : uploadStatus.type === 'error' ? (
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              ) : uploadStatus.type === 'warning' ? (
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.346 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              ) : (
                <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              )}
              <span className="font-medium">{uploadStatus.message}</span>
            </div>
            <button
              type="button"
              onClick={() => setUploadStatus(null)}
              className="text-gray-500 hover:text-gray-700"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* Upload Form - Only file upload */}
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* File Upload Field */}
        <div>
          <label htmlFor="fileUpload" className="block text-sm font-medium text-gray-700 mb-2">
            Cancelled Cheque Image <span className="text-red-500">*</span>
          </label>
          <div className="flex items-center">
            <label
              htmlFor="fileUpload"
              className={`cursor-pointer px-4 py-3 rounded-lg border transition-colors flex items-center ${
                isSubmitting || !userBankDetails
                  ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                  : 'bg-blue-50 hover:bg-blue-100 text-blue-700 border-blue-200'
              }`}
              onClick={(e) => {
                if (!userBankDetails) {
                  e.preventDefault();
                  setUploadStatus({
                    type: 'error',
                    message: 'Bank details not available. Please complete bank account setup first.'
                  });
                }
              }}
            >
              <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              {isSubmitting ? 'Uploading...' : 'Choose TIF/TIFF File'}
            </label>
            <input
              type="file"
              id="fileUpload"
              accept=".tif,.tiff,image/tiff,image/tif"
              onChange={handleFileChange}
              className="hidden"
              required
              disabled={isSubmitting || !userBankDetails}
            />
            <span className={`ml-4 ${fileName ? 'text-gray-900 font-medium' : 'text-gray-600'}`}>
              {fileName || 'No file chosen'}
            </span>
          </div>
          
          {/* Upload Note */}
          <div className="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div className="text-sm text-yellow-800">
              <p className="font-medium mb-2">Important:</p>
              <ul className="list-disc list-inside space-y-1">
                <li>File must be in TIF or TIFF format only</li>
                <li>Maximum file size: 5MB</li>
                <li>File name must be 40 characters or less</li>
                <li>Ensure cheque is clearly marked as &quot;CANCELLED&quot;</li>
                <li>All bank details must be clearly visible</li>
              </ul>
            </div>
          </div>
          
          {/* File Preview */}
          {fileName && !isSubmitting && userBankDetails && (
            <div className="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
              <div className="flex justify-between items-center">
                <div className="flex items-center">
                  <svg className="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p className="text-sm text-green-800 font-medium">TIF/TIFF file ready for upload:</p>
                    <p className="text-sm text-green-700">{fileName}</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setFileName('');
                    setFileBase64('');
                    const fileInput = document.getElementById('fileUpload');
                    if (fileInput) {
                      fileInput.value = '';
                    }
                  }}
                  className="text-red-600 hover:text-red-800 text-sm font-medium px-3 py-1 rounded hover:bg-red-50 transition-colors"
                >
                  Remove
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Submit Button */}
        <div className="pt-4">
          <button
            type="submit"
            disabled={isSubmitting || !userBankDetails || !fileName}
            className={`w-full font-medium py-3 px-4 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${
              isSubmitting || !userBankDetails || !fileName
                ? 'bg-blue-400 text-white cursor-not-allowed focus:ring-blue-300'
                : 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500'
            }`}
          >
            {isSubmitting ? (
              <div className="flex items-center justify-center">
                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Uploading Cancelled Cheque...
              </div>
            ) : !userBankDetails ? (
              'Complete Bank Setup First'
            ) : !fileName ? (
              'Select a File to Upload'
            ) : (
              'Upload Cancelled Cheque'
            )}
          </button>
        </div>
      </form>
    </div>
  );
}