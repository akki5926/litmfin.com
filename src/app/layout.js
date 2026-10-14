import { roboto } from './font';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import ScrollbarStyles from '@/components/ScrollbarStyles';
import './globals.css';
import siteConfig from "@/config/siteConfig";

export const metadata = {
  title: `${siteConfig.siteName} - ${siteConfig.tagline}`,
  description: 'Get customized investment advice with focus on asset allocation. 100% online, zero charges, zero fees. Start your financial journey with {siteConfig.siteName}.',
  keywords: 'mutual funds, investment, asset allocation, financial planning, forex, loans, SIP',
};

export default function RootLayout({ children }) {
  return (
    <html lang="en" className={roboto.className}>
      <head>
        <link rel="icon" href={siteConfig.branding?.favicon || '/favicon.ico'} />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
      </head>
      <body className="antialiased bg-gradient-to-br from-blue-50 via-white to-blue-100/30">
        <ScrollbarStyles />
        <div className="fixed inset-0 -z-10 h-full w-full bg-white bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] opacity-50"></div>
        <Header />
        <main className="min-h-screen">
          {children}
        </main>
        <Footer />
      </body>
    </html>
  );
}