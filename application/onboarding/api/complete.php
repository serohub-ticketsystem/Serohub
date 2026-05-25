<?php
session_start();
require_once dirname(__DIR__, 2) . '/assets/config.php';

header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Prüfen ob alle Schritte abgeschlossen wurden
        $stmt = $pdo->prepare("
            SELECT letztes_pw_change, vorname, nachname, onboarding_abgeschlossen 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
            exit;
        }
        
        // Prüfen ob alle Schritte abgeschlossen wurden
        $step1Completed = !empty($user['letztes_pw_change']);
        $step2Completed = !empty($_SESSION['onboarding_profile_step_seen']);
        $step3Completed = !empty($_SESSION['onboarding_contact_step_seen']);
        $step4Completed = !empty($_SESSION['onboarding_avatar_step_seen']);

        if (!$step1Completed || !$step2Completed || !$step3Completed || !$step4Completed) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Bitte schließen Sie alle Pflichtschritte ab.',
                'step1' => $step1Completed,
                'step2' => $step2Completed,
                'step3' => $step3Completed,
                'step4' => $step4Completed,
            ]);
            exit;
        }
        
        // Onboarding als abgeschlossen markieren
        $updateStmt = $pdo->prepare("UPDATE users SET onboarding_abgeschlossen = 1, geaendert_datum = NOW() WHERE id = ?");
        $updateStmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Onboarding erfolgreich abgeschlossen'
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Onboarding Complete API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Onboarding Complete API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Serverfehler']);
}
