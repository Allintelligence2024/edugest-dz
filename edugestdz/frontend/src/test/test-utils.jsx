import React from 'react';
import { render } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { I18nProvider } from '@context/I18nContext';

function AllTheProviders({ children }) {
  return (
    <BrowserRouter>
      <I18nProvider>
        {children}
      </I18nProvider>
    </BrowserRouter>
  );
}

function customRender(ui, options = {}) {
  return render(ui, { wrapper: AllTheProviders, ...options });
}

export * from '@testing-library/react';
export { customRender as render };
