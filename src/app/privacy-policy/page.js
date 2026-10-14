import siteConfig from "@/config/siteConfig";
import TypedText from '@/components/TypedText';

export default function PrivacyPolicyPage() {
  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Privacy Policy" className="text-gray-900" />
          </h1>
          <p className="text-gray-600">Last updated: July 29, 2026</p>
        </div>
        
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <div className="prose prose-lg max-w-none">
            <div className="mb-8">
              <p className="text-gray-700">
                This Privacy Policy describes how {siteConfig.siteName} (&quot;we&quot;, &quot;us&quot;, or &quot;our&quot;) collects, uses, 
                and discloses your personal information when you use our website and services.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">1. Information We Collect</h2>
            <div className="mb-8">
              <h3 className="text-xl font-semibold text-gray-800 mb-3">Personal Information</h3>
              <ul className="list-disc pl-5 space-y-2 text-gray-700 mb-4">
                <li>Name, email address, phone number</li>
                <li>Date of birth, gender, marital status</li>
                <li>PAN number, Aadhaar number, and other KYC details</li>
                <li>Bank account and payment information</li>
                <li>Employment and income details</li>
              </ul>
              
              <h3 className="text-xl font-semibold text-gray-800 mb-3">Technical Information</h3>
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>IP address, browser type, device information</li>
                <li>Cookies and usage data</li>
                <li>Location information (with consent)</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">2. How We Use Your Information</h2>
            <div className="mb-8">
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>To provide and maintain our services</li>
                <li>To process your transactions and investments</li>
                <li>To verify your identity and complete KYC requirements</li>
                <li>To communicate with you about your account</li>
                <li>To improve our services and user experience</li>
                <li>To comply with legal obligations and regulations</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">3. Data Security</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                We implement appropriate technical and organizational security measures to protect 
                your personal information. These include encryption, access controls, secure servers, 
                and regular security assessments.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">4. Data Sharing</h2>
            <div className="mb-8">
              <p className="text-gray-700 mb-4">
                We may share your information with:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>Asset Management Companies for investment processing</li>
                <li>Regulatory authorities as required by law</li>
                <li>Payment processors and banking partners</li>
                <li>Service providers who assist in our operations</li>
              </ul>
              <p className="text-gray-700 mt-4">
                We do not sell your personal information to third parties.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">5. Your Rights</h2>
            <div className="mb-8">
              <p className="text-gray-700 mb-4">
                You have the right to:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>Access your personal information</li>
                <li>Correct inaccurate information</li>
                <li>Request deletion of your information</li>
                <li>Object to processing of your information</li>
                <li>Data portability</li>
                <li>Withdraw consent at any time</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">6. Cookies</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                We use cookies and similar tracking technologies to enhance your experience. 
                You can control cookies through your browser settings.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">7. Contact Us</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                For any questions about this Privacy Policy or to exercise your rights, 
                please contact our Data Protection Officer at:
              </p>
              <p className="text-gray-700 mt-2">
                Email: {siteConfig.contact?.privacyEmail || ''}<br />
                Address: {siteConfig.contact?.privacyAddress || ''}
              </p>
            </div>
            
            <div className="bg-gray-50 p-6 rounded-lg">
              <p className="text-gray-700">
                <strong>Note:</strong> This privacy policy may be updated from time to time. 
                We will notify you of any material changes by posting the new policy on this page.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export const metadata = {
  title: 'Privacy Policy - Data Protection & Privacy',
  description: 'Learn how we collect, use, and protect your personal information',
};