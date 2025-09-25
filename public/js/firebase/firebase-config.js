import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";

// 🔐 Config tirée directement de Firebase Console :
const firebaseConfig = {
 apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bagWp_dHw",
  authDomain: "strategybuzzergame.firebaseapp.com",
  projectId: "strategybuzzergame",
  storageBucket: "strategybuzzergame.firebasestorage.app",
  messagingSenderId: "680474817391",
  appId: "1:680474817391:web:ba6b3bc148ef187bfeae9a"
};

// ⚙️ Initialisation de l’app
const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

export { db };
