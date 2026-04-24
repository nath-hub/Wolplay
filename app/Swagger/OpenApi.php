<?php

namespace App\Swagger;

use OpenApi\Attributes as OA; // CORRECT pour les attributs #[]

#[OA\Info(
    title: "Wolplay API Documentation",
    version: "1.0.0",
    description: "Documentation de l'API Wolplay."
)]
// #[OA\Server(url: env('APP_URL'), description: "Serveur de production")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class OpenApi {}
