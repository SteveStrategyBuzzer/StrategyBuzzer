document.addEventListener("DOMContentLoaded", () => {
  const audioWin = new Audio("/js/victory.mp3");
  const score = 85;
  if (score >= 80) {
    audioWin.play();
  }

  document.querySelectorAll(".player").forEach(div => {
    div.style.opacity = 0;
    setTimeout(() => {
      div.style.transition = "all 0.6s ease";
      div.style.opacity = 1;
      div.style.transform = "translateY(-10px)";
    }, 500);
  });
});
