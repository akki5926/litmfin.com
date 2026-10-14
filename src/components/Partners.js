import siteConfig from "@/config/siteConfig";

export default function Partners() {
  // Separate AMFI, NSE, Ticker from the list
  const topPartners = siteConfig.partners.top;
  const mutualFundPartners = siteConfig.partners.mutualFunds;
  
  // Split mutual fund partners into 4 columns
  const columnCount = 4;
  const columnSize = Math.ceil(mutualFundPartners.length / columnCount);
  const columns = [];
  
  for (let i = 0; i < columnCount; i++) {
    columns.push(mutualFundPartners.slice(i * columnSize, (i + 1) * columnSize));
  }

  return (
    <section className="py-8">
      <div className="container mx-auto px-4">
        {/* Top Partners Section (AMFI, NSE, Ticker) */}
        <div className="mb-12">
          <h2 className="text-3xl font-bold text-center mb-8 text-blue-600">{siteConfig.SITENAME_CAP} Partners</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 px-4 md:px-24">
            {topPartners.map((partner, index) => (
              <div key={index} className="text-center">
                <span className="text-gray-800 font-semibold text-lg">{partner}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Mutual Fund Houses Section */}
        <div className="bg-gray-50 rounded-lg py-8">
          <h3 className="text-2xl font-bold text-center mb-8 text-blue-600">Mutual Fund Houses</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 px-4 md:px-8">
            {columns.map((column, colIndex) => (
              <div key={colIndex} className="space-y-2">
                {column.map((partner, index) => (
                  <div 
                    key={`${colIndex}-${index}`} 
                    className="text-gray-700 font-medium uppercase text-sm"
                  >
                    {partner}
                  </div>
                ))}
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}