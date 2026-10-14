import TypedText from '@/components/TypedText';

export default function TaxPlanningPage() {
  const taxSections = [
    {
      title: 'Tax Saving Investments',
      items: ['ELSS Funds', 'PPF', 'NPS', 'Tax Saving FDs', 'Life Insurance']
    },
    {
      title: 'Deductions',
      items: ['Section 80C', 'Section 80D', 'HRA', 'Home Loan Interest', 'Education Loan']
    },
    {
      title: 'Tax Strategies',
      items: ['Capital Gains Planning', 'Tax Harvesting', 'Retirement Planning', 'Estate Planning']
    }
  ];

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            <TypedText text="Tax Planning" className="text-gray-900" />
          </h1>
          <p className="text-xl text-gray-600">Smart strategies to minimize tax liability and maximize savings</p>
        </div>
        
        <div className="grid md:grid-cols-3 gap-8 mb-8">
          {taxSections.map((section, index) => (
            <div key={index} className="bg-white rounded-xl shadow-lg p-6">
              <h3 className="text-xl font-bold text-gray-900 mb-4">{section.title}</h3>
              <ul className="space-y-3">
                {section.items.map((item, itemIndex) => (
                  <li key={itemIndex} className="flex items-center p-3 bg-gray-50 rounded-lg">
                    <span className="text-green-500 mr-3">✓</span>
                    <span className="text-gray-700">{item}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        
        <div className="bg-gradient-to-r from-green-500 to-teal-500 rounded-2xl p-8 text-white">
          <div className="max-w-3xl mx-auto text-center">
            <h2 className="text-3xl font-bold mb-4">Start Tax Planning Early</h2>
            <p className="text-green-100 mb-6">
              Don&apos;t wait until March! Strategic tax planning throughout the year can save you 
              significant amounts and help you achieve your financial goals faster.
            </p>
            <button className="px-8 py-3 bg-white text-green-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors">
              Get Personalized Tax Plan
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export const metadata = {
  title: 'Tax Planning - Minimize Taxes, Maximize Savings',
  description: 'Comprehensive tax planning strategies and investment solutions for optimal tax savings',
};