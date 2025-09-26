import React from 'react';

function App() {
  const handleLogin = () => {
    window.location.href = 'https://a2836f7b-0195-4fff-b2d8-7d4368d26b55-00-3accm9exzmt5t.riker.replit.dev:8080/login';
  };

  return (
    <div style={{ textAlign: 'center', padding: '2rem' }}>
      <h1>Bienvenue dans StrategyBuzzer React</h1>
      <p>Interface React connectée avec Laravel</p>
      <button 
        onClick={handleLogin}
        style={{
          padding: '12px 24px',
          backgroundColor: '#007bff',
          color: 'white',
          border: 'none',
          borderRadius: '6px',
          fontSize: '16px',
          cursor: 'pointer',
          marginTop: '20px'
        }}
      >
        Connexion
      </button>
    </div>
  );
}

export default App;

