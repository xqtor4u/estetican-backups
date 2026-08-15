import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import App from './App.tsx';
import { bootTheme } from './hooks/useUserPrefs';
import './index.css';

bootTheme();

// Evita que se vea el texto crudo de los íconos ("pets", "content_cut") mientras
// Material Symbols todavía no cargó — ver el guard en index.css. Timeout de respaldo
// para navegadores sin Font Loading API o si la fuente nunca resuelve: mejor mostrar
// el texto crudo tarde que dejar los íconos escondidos para siempre.
function revealIconsWhenReady() {
  document.documentElement.classList.add('icon-fonts-ready');
}
if ('fonts' in document) {
  document.fonts.ready.then(revealIconsWhenReady).catch(revealIconsWhenReady);
  setTimeout(revealIconsWhenReady, 2000);
} else {
  revealIconsWhenReady();
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
