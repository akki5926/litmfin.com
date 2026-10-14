import siteConfig from "@/config/siteConfig";
import TypedText from '@/components/TypedText';

export default function TermsPage() {
  // Use values from siteConfig with fallbacks
  const companyName = siteConfig.CompanyName || '';
  const domain = siteConfig.domain || '';
  const email = siteConfig.contact?.supportEmail || '';
  const address = siteConfig.contact?.privacyAddress || '';
  const phone = siteConfig.contact.phone || '';
  const siteName = siteConfig.siteName || '';

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Terms of Use" className="text-gray-900" />
          </h1>
          <p className="text-gray-600">Effective Date: May 15, 2026</p>
        </div>
        
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <div className="prose prose-lg max-w-none">
            <div className="mb-8">
              <p className="text-gray-700">
                Please read these Terms of Use (&quot;Terms&quot;) carefully before using the {siteName} 
                website and services. By accessing or using our services, you agree to be bound 
                by these Terms.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">1. Acceptance of Terms</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                By creating an account or using our services, you acknowledge that you have read, 
                understood, and agree to be bound by these Terms and our Privacy Policy.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">2. Eligibility</h2>
            <div className="mb-8">
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>You must be at least 18 years old</li>
                <li>You must be a resident of India</li>
                <li>You must have a valid PAN number</li>
                <li>You must complete KYC verification</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">3. Account Registration</h2>
            <div className="mb-8">
              <p className="text-gray-700 mb-4">
                You are responsible for:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>Providing accurate and complete information</li>
                <li>Maintaining the confidentiality of your account credentials</li>
                <li>All activities that occur under your account</li>
                <li>Promptly notifying us of any unauthorized access</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">4. Investment Services</h2>
            <div className="mb-8">
              <p className="text-gray-700 mb-4">
                Our platform provides access to mutual fund investments. Please note:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>All mutual fund investments are subject to market risks</li>
                <li>Past performance is not indicative of future returns</li>
                <li>Read all scheme related documents carefully</li>
                <li>We are not providing any investment guarantees</li>
                <li>Consider consulting with a financial advisor before investing</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">5. Fees and Charges</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                We may charge fees for certain services as disclosed in our fee schedule. 
                Mutual fund investments may involve expense ratios and other charges levied 
                by Asset Management Companies.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">6. Prohibited Activities</h2>
            <div className="mb-8">
              <p className="text-gray-700 mb-4">
                You agree not to:
              </p>
              <ul className="list-disc pl-5 space-y-2 text-gray-700">
                <li>Use our services for illegal purposes</li>
                <li>Attempt to gain unauthorized access to our systems</li>
                <li>Interfere with or disrupt the services</li>
                <li>Provide false or misleading information</li>
                <li>Use automated tools to access our services</li>
                <li>Violate any applicable laws or regulations</li>
              </ul>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">7. Intellectual Property</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                All content on our platform, including text, graphics, logos, and software, 
                is our property or licensed to us and is protected by intellectual property laws.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">8. Limitation of Liability</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                To the maximum extent permitted by law, we shall not be liable for any indirect, 
                incidental, special, consequential, or punitive damages arising from your use of 
                our services.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">9. Termination</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                We may terminate or suspend your account at our sole discretion, without prior 
                notice, for conduct that we believe violates these Terms or is harmful to other 
                users, us, or third parties.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">10. Governing Law</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                These Terms shall be governed by and construed in accordance with the laws of 
                India. Any disputes shall be subject to the exclusive jurisdiction of the courts 
                in Mumbai.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">11. Changes to Terms</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                We reserve the right to modify these Terms at any time. We will notify you of 
                significant changes by posting the updated Terms on our website. Your continued 
                use of our services constitutes acceptance of the updated Terms.
              </p>
            </div>
            
            <h2 className="text-2xl font-bold text-gray-900 mb-4">12. Contact Information</h2>
            <div className="mb-8">
              <p className="text-gray-700">
                For any questions about these Terms, please contact us at:
              </p>
              <p className="text-gray-700 mt-2">
                Email: <a href={`mailto:${email}`} className="text-blue-600 hover:underline">{email}</a><br />
                Address: {address}<br />
                Phone: <a href={`tel:${phone.replace(/\s+/g, '')}`} className="text-blue-600 hover:underline">{phone}</a><br />
                Website: <a href={`https://${domain}`} className="text-blue-600 hover:underline">{domain}</a>
              </p>
            </div>

            <div className="mt-8 p-6 bg-gray-50 rounded-lg">
              <h3 className="font-bold text-gray-900 mb-2">Company Information:</h3>
              <p className="text-gray-700">
                This service is operated by {companyName}, a registered financial services provider. 
                All financial services are offered in compliance with relevant regulations and guidelines 
                issued by regulatory authorities in India.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}