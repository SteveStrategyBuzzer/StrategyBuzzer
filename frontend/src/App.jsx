import React, { useEffect } from 'react';

function App() {
  useEffect(() => {
    // Redirection automatique vers Laravel
    window.location.replace('https://a2836f7b-0195-4fff-b2d8-7d4368d26b55-00-3accm9exzmt5t.riker.replit.dev:8080/');
  }, []);

  return (
    <div style={{ textAlign: 'center', padding: '2rem' }}>
      <h1>Redirection en cours...</h1>
      <p>Vous êtes redirigé vers StrategyBuzzer Laravel</p>
    </div>
  );
}

export default App;

