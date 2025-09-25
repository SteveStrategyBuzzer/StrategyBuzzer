import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import { getFirestore, collection, getDocs } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";
import firebaseConfig from './firebase-config.js';

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

console.log("Connexion Firebase OK :", db);

const avatarsRef = collection(db, "Players");
const snapshot = await getDocs(avatarsRef);
snapshot.forEach(doc => {
  console.log(doc.id, "=>", doc.data());
});
