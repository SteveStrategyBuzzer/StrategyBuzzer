@extends('layouts.app')

@section('content')
<style>
  body { 
    background: linear-gradient(135deg, #003DA5 0%, #001A52 100%);
    color: #fff;
    overflow: hidden;
    margin: 0;
    padding: 0;
  }

  .preparation-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #003DA5 0%, #001A52 100%);
  }

  .concentration-text {
    font-size: 3rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 60px;
    text-shadow: 0 4px 20px rgba(0,0,0,0.5);
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #FF6347 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: pulse 1.5s ease-in-out infinite;
  }

  .countdown-container {
    position: relative;
    width: 400px;
    height: 400px;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .countdown-number {
    position: absolute;
    font-size: 20rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 10px 40px rgba(0,0,0,0.7);
    opacity: 0;
    transform: scale(0);
  }

  .countdown-number.active {
    animation: countdownAnimation 1s ease-out forwards;
  }

  @keyframes countdownAnimation {
    0% {
      opacity: 0;
      transform: scale(3);
    }
    10% {
      opacity: 1;
      transform: scale(3);
    }
    90% {
      opacity: 1;
      transform: scale(0.8);
    }
    100% {
      opacity: 0;
      transform: scale(0.2);
    }
  }

  @keyframes pulse {
    0%, 100% {
      transform: scale(1);
    }
    50% {
      transform: scale(1.05);
    }
  }

  /* Responsive pour mobile portrait */
  @media (max-width: 767px) and (orientation: portrait) {
    .concentration-text {
      font-size: 2rem;
      margin-bottom: 40px;
    }
    
    .countdown-container {
      width: 300px;
      height: 300px;
    }
    
    .countdown-number {
      font-size: 12rem;
    }
  }

  /* Responsive pour mobile landscape */
  @media (max-width: 767px) and (orientation: landscape) {
    .concentration-text {
      font-size: 1.8rem;
      margin-bottom: 30px;
    }
    
    .countdown-container {
      width: 250px;
      height: 250px;
    }
    
    .countdown-number {
      font-size: 10rem;
    }
  }

  /* Responsive pour tablette portrait */
  @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
    .concentration-text {
      font-size: 2.5rem;
      margin-bottom: 50px;
    }
    
    .countdown-container {
      width: 350px;
      height: 350px;
    }
    
    .countdown-number {
      font-size: 15rem;
    }
  }
</style>

<div class="preparation-container">
  <div class="concentration-text">🎯 On se Concentre</div>
  
  <div class="countdown-container">
    <div class="countdown-number" id="count-3">3</div>
    <div class="countdown-number" id="count-2">2</div>
    <div class="countdown-number" id="count-1">1</div>
  </div>
</div>

<script>
  // Lancer le compte à rebours automatiquement
  let currentCount = 3;
  
  function showNumber(num) {
    const element = document.getElementById('count-' + num);
    if (element) {
      element.classList.add('active');
    }
  }
  
  // Démarrer le compte à rebours
  setTimeout(() => {
    showNumber(3);
    
    setTimeout(() => {
      showNumber(2);
      
      setTimeout(() => {
        showNumber(1);
        
        // Après le 1, rediriger vers la première question
        setTimeout(() => {
          window.location.href = "{{ route('solo.game') }}";
        }, 1000);
      }, 1000);
    }, 1000);
  }, 100);
</script>
@endsection
