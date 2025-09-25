// transitions.js - Gestion simple des transitions d’écrans

export function fadeTransition(element, callback) {
    element.style.opacity = 0;
    setTimeout(() => {
        callback();
        element.style.opacity = 1;
    }, 300);
}