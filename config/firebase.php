<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Service Account
    |--------------------------------------------------------------------------
    |
    | Le chemin vers votre fichier JSON de compte de service Firebase.
    | Assurez-vous qu’il est correctement référencé ici.
    |
    */

    'credentials' => base_path('firebase/strategybuzzer-firebase-adminsdk-fbsvc-w3724d2c0e.json'),

    /*
    |--------------------------------------------------------------------------
    | Autres options Firebase
    |--------------------------------------------------------------------------
    |
    | Vous pouvez ajouter ici d’autres clés comme le databaseURL, storageBucket…
    |
    */

    'default' => [
        'database' => env('FIREBASE_DATABASE_URL', null),
        'storage'  => env('FIREBASE_STORAGE_BUCKET', null),
    ],
];
