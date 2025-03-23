<?php
// Définir une constante pour autoriser l'accès aux fichiers de configuration
define('SECURE_ACCESS', true);

// Charger les fichiers nécessaires
require_once 'client/init.php';
require_once 'client/functions.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: qualification.html');
    exit;
}

// Initialiser le tableau de réponse pour AJAX
$response = [
    'success' => false,
    'message' => '',
    'redirect' => '',
    'data' => []
];

// Vérifier le jeton CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $response['message'] = 'Erreur de sécurité, veuillez rafraîchir la page et réessayer.';
    echo json_encode($response);
    exit;
}

// Récupérer et nettoyer les données du formulaire
$nom = sanitize_input($_POST['nom'] ?? '');
$prenom = sanitize_input($_POST['prenom'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
$telephone = sanitize_input($_POST['telephone'] ?? '');
$code_postal = sanitize_input($_POST['code_postal'] ?? '');
$situation = sanitize_input($_POST['situation'] ?? '');
$type_habitation = sanitize_input($_POST['type_habitation'] ?? '');
$surface = filter_var($_POST['surface'] ?? 0, FILTER_VALIDATE_INT);
$annee_construction = filter_var($_POST['annee_construction'] ?? 0, FILTER_VALIDATE_INT);
$chauffage_actuel = sanitize_input($_POST['chauffage_actuel'] ?? '');
$type_chauffage = sanitize_input($_POST['type_chauffage'] ?? '');
$budget = filter_var($_POST['budget'] ?? 0, FILTER_VALIDATE_INT);
$delai_projet = sanitize_input($_POST['delai_projet'] ?? '');
$commentaire = sanitize_input($_POST['commentaire'] ?? '');

// Valider les données
$errors = [];

// Validation des champs obligatoires
if (empty($nom)) $errors[] = 'Le nom est obligatoire';
if (empty($prenom)) $errors[] = 'Le prénom est obligatoire';
if (empty($email)) $errors[] = 'L\'email est obligatoire';
if (empty($telephone)) $errors[] = 'Le téléphone est obligatoire';
if (empty($code_postal)) $errors[] = 'Le code postal est obligatoire';
if (empty($type_habitation)) $errors[] = 'Le type d\'habitation est obligatoire';
if (empty($surface)) $errors[] = 'La surface est obligatoire';
if (empty($chauffage_actuel)) $errors[] = 'Le type de chauffage actuel est obligatoire';
if (empty($type_chauffage)) $errors[] = 'Le type de chauffage souhaité est obligatoire';

// Validation du format de l'email
if (!empty($email) && !is_valid_email($email)) {
    $errors[] = 'Format d\'email invalide';
}

// Validation du format du téléphone
if (!empty($telephone) && !is_valid_phone($telephone)) {
    $errors[] = 'Format de téléphone invalide';
}

// Validation du code postal français
if (!empty($code_postal) && !preg_match('/^[0-9]{5}$/', $code_postal)) {
    $errors[] = 'Format de code postal invalide';
}

// En cas d'erreurs, renvoyer les erreurs
if (!empty($errors)) {
    $response['message'] = implode('<br>', $errors);
    echo json_encode($response);
    exit;
}

// Vérifier si l'utilisateur existe déjà
try {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    
    if ($client) {
        $client_id = $client['id'];
    } else {
        // Créer un nouveau client
        $stmt = $pdo->prepare("INSERT INTO clients (nom, prenom, email, telephone, code_postal, date_inscription) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nom, $prenom, $email, $telephone, $code_postal]);
        $client_id = $pdo->lastInsertId();
    }
    
    // Ajouter le projet
    $stmt = $pdo->prepare("INSERT INTO projets (client_id, type_habitation, surface, annee_construction, chauffage_actuel, type_chauffage, budget, delai_projet, commentaire, statut, date_creation, date_modification)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Nouveau', NOW(), NOW())");
    $stmt->execute([
        $client_id,
        $type_habitation,
        $surface,
        $annee_construction,
        $chauffage_actuel,
        $type_chauffage,
        $budget,
        $delai_projet,
        $commentaire
    ]);
    $projet_id = $pdo->lastInsertId();
    
    // Calculer l'éligibilité et les aides potentielles (exemple simplifié)
    $aides = [];
    $montant_total_aides = 0;
    
    // MaPrimeRénov' - Simplifié pour l'exemple
    if ($surface > 0 && $type_chauffage == 'Poêle à granulés') {
        $aide_montant = min(3000, $surface * 30);
        $aides[] = [
            'nom' => 'MaPrimeRénov\'',
            'montant' => $aide_montant,
            'description' => 'Aide de l\'État pour le remplacement de votre système de chauffage'
        ];
        $montant_total_aides += $aide_montant;
    }
    
    // CEE (Certificats d'Économie d'Énergie) - Simplifié pour l'exemple
    if ($surface > 0) {
        $aide_montant = min(2000, $surface * 20);
        $aides[] = [
            'nom' => 'Prime CEE',
            'montant' => $aide_montant,
            'description' => 'Prime liée aux Certificats d\'Économie d\'Énergie'
        ];
        $montant_total_aides += $aide_montant;
    }
    
    // TVA réduite
    if ($budget > 0) {
        $tva_pleine = $budget * 0.2;
        $tva_reduite = $budget * 0.055;
        $aide_montant = round($tva_pleine - $tva_reduite);
        $aides[] = [
            'nom' => 'TVA réduite 5.5%',
            'montant' => $aide_montant,
            'description' => 'Économie réalisée grâce à la TVA à taux réduit'
        ];
        $montant_total_aides += $aide_montant;
    }
    
    // Enregistrer les résultats d'éligibilité
    $stmt = $pdo->prepare("UPDATE projets SET estimation_aides = ? WHERE id = ?");
    $stmt->execute([$montant_total_aides, $projet_id]);
    
    // Préparer la réponse
    $response['success'] = true;
    $response['message'] = 'Votre demande a été enregistrée avec succès. Un conseiller vous contactera prochainement.';
    $response['data'] = [
        'projet_id' => $projet_id,
        'aides' => $aides,
        'montant_total' => $montant_total_aides
    ];
    
    // Envoyer un email de confirmation
    $sujet = 'Confirmation de votre demande de devis - Quali\'Poêle';
    $message = "Bonjour $prenom $nom,\n\n";
    $message .= "Nous avons bien reçu votre demande de devis pour un $type_chauffage.\n\n";
    $message .= "Selon une première estimation, vous pourriez bénéficier de $montant_total_aides € d'aides financières.\n\n";
    $message .= "Un conseiller va étudier votre dossier et vous contactera dans les plus brefs délais.\n\n";
    $message .= "Cordialement,\n";
    $message .= "L'équipe Quali'Poêle";
    
    $headers = "From: contact@qualipoele.fr\r\n";
    $headers .= "Reply-To: contact@qualipoele.fr\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    mail($email, $sujet, $message, $headers);
    
} catch (PDOException $e) {
    error_log("Erreur lors du traitement du formulaire de qualification: " . $e->getMessage());
    $response['message'] = 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer ultérieurement.';
}

// Renvoyer la réponse JSON
header('Content-Type: application/json');
echo json_encode($response); 