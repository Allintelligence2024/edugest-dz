<?php

namespace App\Virtual;

/**
 * ── Réponse générique ──────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string",  example="Opération réussie"),
 *     @OA\Property(property="data",    type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string",  example="Ressource non trouvée"),
 *     @OA\Property(property="errors",  type="object",  nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     @OA\Property(property="current_page",  type="integer", example=1),
 *     @OA\Property(property="last_page",     type="integer", example=5),
 *     @OA\Property(property="per_page",      type="integer", example=20),
 *     @OA\Property(property="total",         type="integer", example=97)
 * )
 *
 * ── Élève ─────────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Eleve",
 *     required={"id","nom","prenom","niveau_scolaire","statut"},
 *     @OA\Property(property="id",              type="string",  format="uuid"),
 *     @OA\Property(property="nom",             type="string",  example="Benali"),
 *     @OA\Property(property="prenom",          type="string",  example="Amira"),
 *     @OA\Property(property="date_naissance",  type="string",  format="date", example="2010-03-15"),
 *     @OA\Property(property="sexe",            type="string",  enum={"M","F"}),
 *     @OA\Property(property="niveau_scolaire", type="string",  example="3AS"),
 *     @OA\Property(property="statut",          type="string",  enum={"actif","inactif","suspendu"}),
 *     @OA\Property(property="photo_url",       type="string",  nullable=true),
 *     @OA\Property(property="tenant_id",       type="string",  format="uuid"),
 *     @OA\Property(property="created_at",      type="string",  format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="EleveInput",
 *     required={"nom","prenom","niveau_scolaire","date_naissance","sexe"},
 *     @OA\Property(property="nom",             type="string",  example="Benali"),
 *     @OA\Property(property="prenom",          type="string",  example="Amira"),
 *     @OA\Property(property="date_naissance",  type="string",  format="date", example="2010-03-15"),
 *     @OA\Property(property="sexe",            type="string",  enum={"M","F"}),
 *     @OA\Property(property="niveau_scolaire", type="string",  example="3AS"),
 *     @OA\Property(property="wilaya_id",       type="integer", example=31),
 *     @OA\Property(property="commune_id",      type="integer", example=310),
 *     @OA\Property(property="adresse",         type="string",  nullable=true)
 * )
 *
 * ── Facture ───────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Facture",
 *     @OA\Property(property="id",            type="string",  format="uuid"),
 *     @OA\Property(property="numero",        type="string",  example="FAC-2026-0042"),
 *     @OA\Property(property="eleve_id",      type="string",  format="uuid"),
 *     @OA\Property(property="total_ht",      type="number",  format="float", example=4500.00),
 *     @OA\Property(property="tva",           type="number",  format="float", example=0.00),
 *     @OA\Property(property="total_ttc",     type="number",  format="float", example=4500.00),
 *     @OA\Property(property="statut",        type="string",  enum={"émise","payée","en_retard","partiellement_payée","annulée"}),
 *     @OA\Property(property="date_emission", type="string",  format="date"),
 *     @OA\Property(property="date_echeance", type="string",  format="date"),
 *     @OA\Property(property="mois",          type="integer", example=7),
 *     @OA\Property(property="annee",         type="integer", example=2026)
 * )
 *
 * ── Paiement ──────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Paiement",
 *     @OA\Property(property="id",             type="string", format="uuid"),
 *     @OA\Property(property="facture_id",     type="string", format="uuid"),
 *     @OA\Property(property="montant",        type="number", format="float", example=4500.00),
 *     @OA\Property(property="mode_paiement",  type="string", enum={"espèces","chèque","virement","cib","dahabia"}),
 *     @OA\Property(property="statut",         type="string", enum={"en_attente","confirmé","échoué","remboursé"}),
 *     @OA\Property(property="date_paiement",  type="string", format="date-time"),
 *     @OA\Property(property="reference_satim",type="string", nullable=true)
 * )
 *
 * ── Circuit Transport ─────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="CircuitTransport",
 *     @OA\Property(property="id",               type="string",  format="uuid"),
 *     @OA\Property(property="nom",              type="string",  example="Circuit Nord"),
 *     @OA\Property(property="capacite",         type="integer", example=25),
 *     @OA\Property(property="nb_eleves_actifs", type="integer", example=18),
 *     @OA\Property(property="taux_remplissage", type="number",  format="float", example=72.0),
 *     @OA\Property(property="actif",            type="boolean", example=true)
 * )
 *
 * ── Personnel ────────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="Personnel",
 *     @OA\Property(property="id",       type="string", format="uuid"),
 *     @OA\Property(property="nom",      type="string", example="Cherif"),
 *     @OA\Property(property="prenom",   type="string", example="Karim"),
 *     @OA\Property(property="poste",    type="string", example="Agent de sécurité"),
 *     @OA\Property(property="statut",   type="string", enum={"actif","inactif","congé"}),
 *     @OA\Property(property="salaire_base", type="number", format="float", example=28000.00)
 * )
 *
 * ── Article Stock ─────────────────────────────────────────────────────────
 *
 * @OA\Schema(
 *     schema="ArticleStock",
 *     @OA\Property(property="id",              type="string",  format="uuid"),
 *     @OA\Property(property="nom",             type="string",  example="Cahier 96 pages"),
 *     @OA\Property(property="reference",       type="string",  example="CAH-96"),
 *     @OA\Property(property="categorie",       type="string",  example="fournitures"),
 *     @OA\Property(property="quantite_stock",  type="integer", example=150),
 *     @OA\Property(property="seuil_alerte",    type="integer", example=20),
 *     @OA\Property(property="prix_unitaire",   type="number",  format="float", example=35.00),
 *     @OA\Property(property="actif",           type="boolean", example=true)
 * )
 */
class Schemas {}
