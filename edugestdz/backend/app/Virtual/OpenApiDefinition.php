<?php

namespace App\Virtual;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="EduGest DZ API",
 *     description="API complète — plateforme SaaS de gestion scolaire pour l'Algérie.
Authentification via JWT Bearer (header Authorization: Bearer <token>).
Multi-tenant : chaque requête doit inclure le header X-Tenant-ID.",
 *     @OA\Contact(email="support@edugest.dz"),
 *     @OA\License(name="Proprietary")
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Serveur courant"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Parameter(
 *     parameter="TenantId",
 *     name="X-Tenant-ID",
 *     in="header",
 *     required=true,
 *     description="Identifiant unique du tenant (école/centre)",
 *     @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
 * )
 *
 * @OA\Tag(name="Auth",          description="Authentification JWT + 2FA")
 * @OA\Tag(name="Eleves",        description="Gestion des élèves")
 * @OA\Tag(name="Enseignants",   description="Gestion du corps enseignant")
 * @OA\Tag(name="Groupes",       description="Groupes / classes")
 * @OA\Tag(name="Planning",      description="Séances & planning")
 * @OA\Tag(name="Presences",     description="Présences en séance")
 * @OA\Tag(name="Evaluations",   description="Évaluations & notes")
 * @OA\Tag(name="Bulletins",     description="Bulletins trimestriels PDF")
 * @OA\Tag(name="Absences",      description="Absences journalières + SMS")
 * @OA\Tag(name="Billets",       description="Billets entrée / retard / sortie / convocation")
 * @OA\Tag(name="Finances",      description="Factures & paiements")
 * @OA\Tag(name="PaiementCIB",   description="Paiement CIB / Dahabia via Satim")
 * @OA\Tag(name="Transport",     description="Circuits, arrêts & pointage bus")
 * @OA\Tag(name="Cantine",       description="Menus, inscriptions & pointage repas")
 * @OA\Tag(name="Stock",         description="Inventaire, mouvements & bons de commande")
 * @OA\Tag(name="Budget",        description="Budget, dépenses & prévisionnel")
 * @OA\Tag(name="Personnel",     description="Personnel non-enseignant & paie")
 * @OA\Tag(name="Entretien",     description="Locaux, interventions & préventif")
 * @OA\Tag(name="Pointage",      description="Pointage enseignants & badges RFID")
 * @OA\Tag(name="Notifications", description="Notifications push & WhatsApp")
 * @OA\Tag(name="SuperAdmin",    description="Administration globale (super-admin uniquement)")
 */
class OpenApiDefinition {}
