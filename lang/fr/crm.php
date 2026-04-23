<?php

/**
 * Traductions françaises pour le marché suisse romand.
 *
 * Les clés sont alignées avec lang/en/crm.php et lang/de/crm.php — gardez-les
 * synchronisées lors de l'ajout de nouvelles chaînes.
 */
return [

    'common' => [
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'delete' => 'Supprimer',
        'edit' => 'Modifier',
        'create' => 'Créer',
        'update' => 'Mettre à jour',
        'search' => 'Rechercher',
        'close' => 'Fermer',
        'back' => 'Retour',
        'confirm' => 'Confirmer',
        'loading' => 'Chargement…',
        'no_results' => 'Aucun résultat.',
        'required' => 'Requis',
        'optional' => 'Facultatif',
        'view' => 'Voir',
        'status' => 'Statut',
        'actions' => 'Actions',
    ],

    'status' => [
        'pending' => 'En attente',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
        'scheduled' => 'Planifié',
        'busy' => 'Occupé',
        'available' => 'Disponible',
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'work_order' => [
        'title' => 'Ordre de travail',
        'status_created' => 'Créé',
        'status_scheduled' => 'Planifié',
        'status_in_progress' => 'En cours',
        'status_waiting_parts' => 'Attente de pièces',
        'status_completed' => 'Terminé',
        'status_invoiced' => 'Facturé',
        'status_cancelled' => 'Annulé',
        'technician' => 'Technicien',
        'technician_busy' => 'Technicien occupé',
        'technician_available' => 'Technicien disponible',
    ],

    'customer' => [
        'title' => 'Client',
        'name' => 'Nom',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        'address' => 'Adresse',
    ],

    'finance' => [
        'invoice' => 'Facture',
        'payment' => 'Paiement',
        'total' => 'Total',
        'paid' => 'Payé',
        'balance' => 'Solde',
        'overdue' => 'En retard',
        'currency' => 'CHF',
        'subtotal' => 'Sous-total',
        'tax' => 'TVA',
        'discount' => 'Remise',
        'due_date' => 'Échéance',
        'issue_date' => 'Date d\'émission',
        'balance_due' => 'Solde dû',
        'bill_to' => 'Adresser à',
        'print_save_pdf' => 'Imprimer / Enregistrer en PDF',
    ],

    'quote' => [
        'title' => 'Devis',
        'quotes' => 'Devis',
        'new' => 'Nouveau devis',
        'number' => 'Numéro',
        'customer' => 'Client',
        'issued' => 'Émis',
        'expires' => 'Valable jusqu\'au',
        'status_draft' => 'Brouillon',
        'status_sent' => 'Envoyé',
        'status_accepted' => 'Accepté',
        'status_rejected' => 'Refusé',
        'status_converted' => 'Converti',
        'convert_to_invoice' => 'Convertir en facture',
        'line_items' => 'Lignes',
        'add_item' => '+ Ajouter une ligne',
        'description' => 'Description',
        'quantity' => 'Qté',
        'unit_price' => 'Prix unitaire',
        'vat_rate' => 'TVA %',
        'line_total' => 'Total ligne',
        'empty_list' => 'Aucun devis pour le moment.',
        'all_statuses' => 'Tous les statuts',
        'search_placeholder' => 'Rechercher par numéro ou client',
        'save_changes' => 'Enregistrer les modifications',
        'filter' => 'Filtrer',
        'view_invoice' => 'Voir la facture :number',
    ],

    'errors' => [
        'access_denied' => 'Accès refusé',
        'not_found' => 'Introuvable',
        'server_error' => 'Une erreur est survenue de notre côté.',
        'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
    ],

];
