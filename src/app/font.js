import localFont from 'next/font/local';

export const roboto = localFont({
  src: [
    {
      path: '../../public/font/roboto/Roboto-Regular.woff2',
      weight: '400',
      style: 'normal',
    },
    {
      path: '../../public/font/roboto/Roboto-Medium.woff2',
      weight: '500',
      style: 'normal',
    },
    {
      path: '../../public/font/roboto/Roboto-Bold.woff2',
      weight: '700',
      style: 'normal',
    },
  ],
  display: 'swap',
});
