<?php

namespace App\Swagger;

use OpenApi\Attributes as OA; // CORRECT pour les attributs #[]

#[OA\Info(
    title: "Wolplay API Documentation",
    version: "1.0.0",
    description: "Documentation de l'API Wolplay."
)]
#[OA\Server(url: "http://127.0.0.1:8000")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class OpenApi {}
