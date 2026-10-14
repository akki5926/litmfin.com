import siteConfig from "@/config/siteConfig";

const fundCategories = siteConfig.fundCategories;
const fundCategoriesHeading = siteConfig.homepage.fundCategoriesHeading;

export default function FundCategories() {
  return (
    <section className="py-8"> {/* Reduced from py-16 to py-8 */}
      <div className="container mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-8 text-blue-600">{fundCategoriesHeading}</h2> {/* Reduced from mb-12 to mb-8 */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"> {/* Reduced gap from gap-8 to gap-6 */}
          {fundCategories.map((category, index) => (
            <div key={index} className="bg-white rounded-lg shadow-md p-5"> {/* Reduced from p-6 to p-5 */}
              <h3 className="text-xl font-bold mb-3 text-blue-900">{category.title}</h3> {/* Reduced from mb-4 to mb-3 */}
              <ul className="space-y-2"> {/* Reduced from space-y-3 to space-y-2 */}
                {category.funds.map((fund, fundIndex) => (
                  <li key={fundIndex} className="text-gray-700 border-b pb-2 last:border-0 text-sm"> {/* Added text-sm */}
                    {fund}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}