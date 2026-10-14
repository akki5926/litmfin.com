'use client';

const ScrollbarStyles = () => {
  return (
    <style jsx global>{`
      /* Always show scrollbar on html/body */
      html {
        overflow-y: scroll;
        scrollbar-gutter: stable;
      }

      /* Width */
      ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
      }

      /* Track */
      ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
      }

      /* Handle */
      ::-webkit-scrollbar-thumb {
        background: #93c5fd;
        border-radius: 10px;
        border: 2px solid #f1f5f9;
        transition: background 0.2s ease;
      }

      /* Handle on hover */
      ::-webkit-scrollbar-thumb:hover {
        background: #3b82f6;
      }

      /* Corner */
      ::-webkit-scrollbar-corner {
        background: #f1f5f9;
      }

      /* Firefox */
      * {
        scrollbar-width: thin;
        scrollbar-color: #93c5fd #f1f5f9;
      }
    `}</style>
  );
};

export default ScrollbarStyles;