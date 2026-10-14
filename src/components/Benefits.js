import siteConfig from "@/config/siteConfig";

export default function Benefits() {
  return (
    <section className="py-16 bg-blue-50">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <div>
            <h2 className="text-3xl font-bold mb-6 text-blue-600">Distribution Model</h2>
            <p className="text-gray-700 text-lg">
              {`${siteConfig.SITENAME_CAP} distributes wide range to financial and investment products with constant support coming from our Relationship Managers and Dedicated Service support to help navigate your financial journey. Products starting from RBI Bonds, Mutual Funds, SIF, PMS, Bonds, GIFT CITY, Angel AIF etc…….`}
            </p>
          </div>
          <div>
            <h3 className="text-2xl font-bold mb-6 text-blue-600">{siteConfig.homepage.benefitsTitle}</h3>
            <ul className="space-y-3">
              {siteConfig.homepage.benefits.map((benefit, index) => (
                <li key={index} className="flex items-start">
                  <svg className="w-5 h-5 text-green-500 mt-1 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                  <span className="text-gray-700">{benefit}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}