import TypedText from '@/components/TypedText';
import Link from 'next/link';

export default function CareersPage() {
  const jobOpenings = [
    {
      title: 'MBA Finance Intern',
      department: 'Wealth Management',
      location: 'Bengaluru',
      type: 'Internship',
      duration: '8–12 weeks (flexible based on institute requirement)',
      mode: 'Hybrid / Onsite (preferred)',
      reportingTo: 'Founder / Principal Advisor – Mutual Fund Distribution & Advisory'
    },
    {
      title: 'Digital Marketing Intern',
      department: 'Marketing & Growth',
      location: 'Bengaluru',
      type: 'Internship',
      duration: '8–12 weeks (flexible based on institute requirement)',
      mode: 'Hybrid / Onsite (preferred)',
      reportingTo: 'Head of Marketing'
    }
  ];

  const financeInternDetails = {
    roles: [
      'Mutual Fund Research & Analysis – Analyze mutual fund schemes, compare funds based on risk, return, expense ratio',
      'Client Support & Advisory Assistance – Support in client onboarding, KYC, and investment proposals',
      'Market & Economic Research – Track market trends, prepare research notes and presentations',
      'Operations & Back Office Exposure – Understand transaction processes, work with MF platforms and CRMs',
      'Investor Awareness & Content Support – Assist in creating investor education material'
    ],
    learningOutcomes: [
      'Practical exposure to wealth management & mutual fund advisory',
      'Understanding of client psychology and financial goal planning',
      'Hands-on experience in portfolio construction & SIP modeling',
      'Exposure to regulatory and compliance frameworks (AMFI/SEBI)',
      'Strong foundation for careers in Wealth Management, Asset Management, Banking, or RIA roles'
    ],
    eligibility: [
      'MBA (Finance) students – 1st or 2nd year',
      'Strong interest in investments, capital markets, and personal finance',
      'Basic knowledge of mutual funds and financial statements',
      'Proficiency in MS Excel (mandatory)',
      'AMFI Mutual Fund Certification (preferred but not mandatory)'
    ],
    skills: [
      'Analytical thinking',
      'Attention to detail',
      'Communication & presentation skills',
      'Willingness to learn and take ownership',
      'Ethical and compliance-oriented mindset'
    ],
    futureOpportunities: [
      'Pre-placement offer (PPO) for high-performing interns',
      'Long-term association as Relationship Manager / Research Associate',
      'Guidance for AMFI / NISM certifications'
    ]
  };

  const digitalMarketingDetails = {
    roles: [
      'Social Media Management – Create and schedule content across platforms',
      'Content Creation – Develop blog posts, newsletters, and marketing materials',
      'SEO & Analytics – Assist in optimizing website content and analyzing campaign performance',
      'Lead Generation – Support digital campaigns for client acquisition',
      'Market Research – Analyze competitor strategies and industry trends'
    ],
    learningOutcomes: [
      'Hands-on experience in digital marketing for financial services',
      'Understanding of financial content marketing strategies',
      'Experience with social media analytics and reporting tools',
      'Knowledge of SEO best practices for financial websites',
      'Exposure to lead generation and conversion optimization'
    ],
    eligibility: [
      'MBA (Marketing) / BBA / Mass Communication students',
      'Strong interest in digital marketing and financial services',
      'Basic understanding of social media platforms and content creation',
      'Creative thinking and good writing skills',
      'Familiarity with marketing tools (preferred)'
    ],
    skills: [
      'Creative content creation',
      'Social media savvy',
      'Analytical thinking',
      'Communication skills',
      'Adaptability and willingness to learn'
    ],
    futureOpportunities: [
      'Pre-placement offer (PPO) for high-performing interns',
      'Long-term association as Digital Marketing Executive',
      'Opportunity to specialize in financial services marketing'
    ]
  };

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Careers" className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Internship opportunities in Bengaluru</p>
        </div>
        
        <div className="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-8 text-white mb-8">
          <div className="max-w-3xl mx-auto text-center">
            <h2 className="text-3xl font-bold mb-4">Internship Program</h2>
            <p className="text-blue-100">
              We offer structured internship programs for students to gain practical experience 
              in finance and marketing within the financial services industry.
            </p>
            <div className="mt-6 grid grid-cols-2 gap-4 max-w-md mx-auto">
              <div className="text-center">
                <div className="text-2xl mb-1">📍</div>
                <div className="text-sm">Location: Bengaluru</div>
              </div>
              <div className="text-center">
                <div className="text-2xl mb-1">⏱️</div>
                <div className="text-sm">Duration: 8–12 weeks</div>
              </div>
            </div>
          </div>
        </div>
        
        <div className="bg-white rounded-2xl shadow-lg p-8 mb-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-6">Current Internship Openings</h2>
          
          <div className="grid md:grid-cols-2 gap-6">
            {jobOpenings.map((job, index) => (
              <div key={index} className="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-xl font-bold text-gray-900">{job.title}</h3>
                    <div className="flex items-center gap-2 mt-2">
                      <span className="text-sm text-gray-600">📍 {job.location}</span>
                      <span className="text-sm text-gray-600">•</span>
                      <span className="text-sm text-gray-600">{job.type}</span>
                    </div>
                  </div>
                  <Link href="/contact">
                    <button className="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors cursor-pointer">
                      Apply Now
                    </button>
                  </Link>
                </div>
                
                <div className="space-y-3">
                  <div>
                    <span className="text-sm font-medium text-gray-700">Duration:</span>
                    <span className="text-sm text-gray-600 ml-2">{job.duration}</span>
                  </div>
                  <div>
                    <span className="text-sm font-medium text-gray-700">Mode:</span>
                    <span className="text-sm text-gray-600 ml-2">{job.mode}</span>
                  </div>
                  <div>
                    <span className="text-sm font-medium text-gray-700">Reporting To:</span>
                    <span className="text-sm text-gray-600 ml-2">{job.reportingTo}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
        
        {/* MBA Finance Intern Details */}
        <div className="bg-white rounded-2xl shadow-lg p-8 mb-8">
          <div className="flex items-center mb-6">
            <div className="bg-blue-100 p-3 rounded-lg mr-4">
              <span className="text-blue-600 font-bold">Finance</span>
            </div>
            <h2 className="text-2xl font-bold text-gray-900">MBA Finance Intern - Role Details</h2>
          </div>
          
          <div className="grid md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Roles & Responsibilities</h3>
              <ul className="space-y-3">
                {financeInternDetails.roles.map((role, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-blue-500 mr-2">•</span>
                    <span className="text-gray-700">{role}</span>
                  </li>
                ))}
              </ul>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-6 mb-4">Learning Outcomes</h3>
              <ul className="space-y-2">
                {financeInternDetails.learningOutcomes.map((outcome, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-green-500 mr-2">✓</span>
                    <span className="text-gray-700">{outcome}</span>
                  </li>
                ))}
              </ul>
            </div>
            
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Eligibility Criteria</h3>
              <ul className="space-y-3">
                {financeInternDetails.eligibility.map((item, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-blue-500 mr-2">•</span>
                    <span className="text-gray-700">{item}</span>
                  </li>
                ))}
              </ul>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-6 mb-4">Desired Skills</h3>
              <div className="flex flex-wrap gap-2">
                {financeInternDetails.skills.map((skill, index) => (
                  <span key={index} className="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
                    {skill}
                  </span>
                ))}
              </div>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-6 mb-4">Future Opportunities</h3>
              <ul className="space-y-2">
                {financeInternDetails.futureOpportunities.map((opportunity, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-purple-500 mr-2">→</span>
                    <span className="text-gray-700">{opportunity}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
        
        {/* Digital Marketing Intern Details */}
        <div className="bg-white rounded-2xl shadow-lg p-8 mb-8">
          <div className="flex items-center mb-6">
            <div className="bg-purple-100 p-3 rounded-lg mr-4">
              <span className="text-purple-600 font-bold">Marketing</span>
            </div>
            <h2 className="text-2xl font-bold text-gray-900">Digital Marketing Intern - Role Details</h2>
          </div>
          
          <div className="grid md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Roles & Responsibilities</h3>
              <ul className="space-y-3">
                {digitalMarketingDetails.roles.map((role, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-purple-500 mr-2">•</span>
                    <span className="text-gray-700">{role}</span>
                  </li>
                ))}
              </ul>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-6 mb-4">Learning Outcomes</h3>
              <ul className="space-y-2">
                {digitalMarketingDetails.learningOutcomes.map((outcome, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-green-500 mr-2">✓</span>
                    <span className="text-gray-700">{outcome}</span>
                  </li>
                ))}
              </ul>
            </div>
            
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Eligibility Criteria</h3>
              <ul className="space-y-3">
                {digitalMarketingDetails.eligibility.map((item, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-purple-500 mr-2">•</span>
                    <span className="text-gray-700">{item}</span>
                  </li>
                ))}
              </ul>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-6 mb-4">Desired Skills</h3>
              <div className="flex flex-wrap gap-2">
                {digitalMarketingDetails.skills.map((skill, index) => (
                  <span key={index} className="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
                    {skill}
                  </span>
                ))}
              </div>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-6 mb-4">Future Opportunities</h3>
              <ul className="space-y-2">
                {digitalMarketingDetails.futureOpportunities.map((opportunity, index) => (
                  <li key={index} className="flex items-start">
                    <span className="text-purple-500 mr-2">→</span>
                    <span className="text-gray-700">{opportunity}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>
        
        {/* General Information */}
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-2xl font-bold text-gray-900 mb-6">Internship Details</h2>
          
          <div className="grid md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Internship Type</h3>
              <div className="space-y-4">
                <div>
                  <span className="block text-sm font-medium text-gray-700">Duration:</span>
                  <span className="text-gray-600">8–12 weeks (flexible based on institute requirement)</span>
                </div>
                <div>
                  <span className="block text-sm font-medium text-gray-700">Mode:</span>
                  <span className="text-gray-600">Hybrid / Onsite (preferred)</span>
                </div>
              </div>
              
              <h3 className="text-lg font-semibold text-gray-900 mt-8 mb-4">Certification</h3>
              <ul className="space-y-2">
                <li className="flex items-start">
                  <span className="text-blue-500 mr-2">✓</span>
                  <span className="text-gray-700">Internship Certificate</span>
                </li>
                <li className="flex items-start">
                  <span className="text-blue-500 mr-2">✓</span>
                  <span className="text-gray-700">Letter of Recommendation (based on performance)</span>
                </li>
              </ul>
            </div>
            
            <div>
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Application Process</h3>
              <div className="space-y-4">
                <div className="flex items-start">
                  <div className="bg-blue-100 text-blue-600 rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">
                    1
                  </div>
                  <div>
                    <h4 className="font-medium text-gray-900">Submit Application</h4>
                    <p className="text-gray-600 text-sm mt-1">Fill the online form with your details and upload resume</p>
                  </div>
                </div>
                
                <div className="flex items-start">
                  <div className="bg-green-100 text-green-600 rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">
                    2
                  </div>
                  <div>
                    <h4 className="font-medium text-gray-900">Screening</h4>
                    <p className="text-gray-600 text-sm mt-1">Profile review and shortlisting based on eligibility</p>
                  </div>
                </div>
                
                <div className="flex items-start">
                  <div className="bg-purple-100 text-purple-600 rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">
                    3
                  </div>
                  <div>
                    <h4 className="font-medium text-gray-900">Interview</h4>
                    <p className="text-gray-600 text-sm mt-1">Virtual/In-person discussion with department head</p>
                  </div>
                </div>
                
                <div className="flex items-start">
                  <div className="bg-orange-100 text-orange-600 rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">
                    4
                  </div>
                  <div>
                    <h4 className="font-medium text-gray-900">Selection</h4>
                    <p className="text-gray-600 text-sm mt-1">Offer letter and onboarding process</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}