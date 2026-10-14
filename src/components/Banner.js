import siteConfig from "@/config/siteConfig";
export default function Banner() {
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 text-white">
      <div className="absolute inset-0 opacity-10">
        <div className="absolute inset-0" style={{
          backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
          backgroundSize: '60px 60px'
        }}></div>
      </div>

      <div className="relative container mx-auto px-4">
        <div className="py-20 md:py-28">
          <div className="max-w-3xl">
            <div className="inline-flex items-center px-4 py-2 rounded-full bg-white/20 backdrop-blur-sm mb-6">
              <span className="text-sm font-medium">✨ Trusted by 500+ Investors</span>
            </div>
            
            <h1 className="text-4xl md:text-6xl font-bold mb-6 leading-tight">
              Smart Financial Planning Solutions
              <span className="block text-blue-200 mt-2">Tailored for You</span>
            </h1>
            
            <p className="text-xl text-blue-100 mb-8 max-w-2xl">
              Get <span className="font-bold text-white">customized financial planning advice</span> with focus on 
              asset allocation. 100% online, zero charges, zero fees.
            </p>
            
            {/* <div className="flex flex-col sm:flex-row gap-4 mb-12">
              <button className="bg-white text-blue-900 px-8 py-4 rounded-lg font-bold text-lg hover:bg-blue-50 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
                Start Free Trial
              </button>
              <button className="border-2 border-white text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white/10 transition-all duration-300">
                Watch Demo
              </button>
            </div> */}

            {/* Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-2xl">
              <div className="bg-gradient-to-br from-blue-500 to-blue-700 text-white p-6 rounded-2xl shadow-xl">
                <div className="text-3xl font-bold">₹500Cr+</div>
                <div className="text-blue-100 text-sm">Assets Managed</div>
              </div>
              <div className="bg-gradient-to-br from-blue-500 to-blue-700 text-white p-6 rounded-2xl shadow-xl">
                <div className="text-3xl font-bold">50K+</div>
                <div className="text-blue-100 text-sm">Happy Investors</div>
              </div>
              <div className="bg-gradient-to-br from-blue-500 to-blue-700 text-white p-6 rounded-2xl shadow-xl">
                <div className="text-3xl font-bold">9.8/10</div>
                <div className="text-blue-100 text-sm">Customer Rating</div>
              </div>
              <div className="bg-gradient-to-br from-blue-500 to-blue-700 text-white p-6 rounded-2xl shadow-xl">
                <div className="text-3xl font-bold">24/7</div>
                <div className="text-blue-100 text-sm">Support</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}