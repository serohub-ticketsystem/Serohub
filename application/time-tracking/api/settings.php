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
$method = $_SERVER['REQUEST_METHOD'];

// Benutzerdaten und Rolle abrufen
try {
    $stmt = $pdo->prepare("SELECT id, rolle FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || ($user['rolle'] !== 'Admin' && $user['rolle'] !== 'Techniker')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // Einstellungen abrufen
            $stmt = $pdo->prepare("
                SELECT setting_key, setting_value 
                FROM user_settings 
                WHERE user_id = :user_id 
                AND setting_key IN ('weekly_hours', 'vacation_days', 'work_start_time', 'work_end_time', 'employment_start_date')
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [
                'weekly_hours' => 40, // Standard
                'vacation_days' => 25, // Standard
                'work_start_time' => '08:00', // Standard
                'work_end_time' => '17:00', // Standard
                'employment_start_date' => '' // Standard (leer)
            ];
            
            foreach ($settings as $setting) {
                $result[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Berechne Stunden pro Tag basierend auf Wochenstunden und Arbeitszeiten
            $weeklyHours = (float)$result['weekly_hours'];
            $startTime = $result['work_start_time'];
            $endTime = $result['work_end_time'];
            
            // Berechne Arbeitszeit pro Tag in Stunden
            $startParts = explode(':', $startTime);
            $endParts = explode(':', $endTime);
            $startHour = (int)$startParts[0] + ((int)$startParts[1] / 60);
            $endHour = (int)$endParts[0] + ((int)$endParts[1] / 60);
            $workHoursPerDay = $endHour - $startHour;
            
            // Stunden pro Tag basierend auf Wochenstunden (5 Arbeitstage)
            $calculatedHoursPerDay = $weeklyHours / 5;
            
            // Verwende das Minimum (falls Arbeitszeit kürzer als benötigt)
            $hoursPerDay = min($workHoursPerDay, $calculatedHoursPerDay);
            
            $result['hours_per_day'] = round($hoursPerDay, 2);
            $result['work_hours_per_day'] = $workHoursPerDay;
            
            echo json_encode(['success' => true, 'settings' => $result]);
            break;
            
        case 'POST':
            // Einstellungen speichern
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
                exit;
            }
            
            $weeklyHours = $data['weekly_hours'] ?? null;
            $vacationDays = $data['vacation_days'] ?? null;
            $workStartTime = $data['work_start_time'] ?? null;
            $workEndTime = $data['work_end_time'] ?? null;
            $employmentStartDate = $data['employment_start_date'] ?? null;
            
            $errors = [];
            
            if ($weeklyHours !== null && $weeklyHours !== '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value)
                        VALUES (:user_id, 'weekly_hours', :value)
                        ON DUPLICATE KEY UPDATE setting_value = :value2
                    ");
                    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':value', (float)$weeklyHours);
                    $stmt->bindValue(':value2', (float)$weeklyHours);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $errors[] = 'Wochenstunden: ' . $e->getMessage();
                }
            }
            
            if ($vacationDays !== null && $vacationDays !== '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value)
                        VALUES (:user_id, 'vacation_days', :value)
                        ON DUPLICATE KEY UPDATE setting_value = :value2
                    ");
                    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':value', (int)$vacationDays);
                    $stmt->bindValue(':value2', (int)$vacationDays);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $errors[] = 'Urlaubstage: ' . $e->getMessage();
                }
            }
            
            if ($workStartTime !== null && $workStartTime !== '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value)
                        VALUES (:user_id, 'work_start_time', :value)
                        ON DUPLICATE KEY UPDATE setting_value = :value2
                    ");
                    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':value', $workStartTime);
                    $stmt->bindValue(':value2', $workStartTime);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $errors[] = 'Arbeitsbeginn: ' . $e->getMessage();
                }
            }
            
            if ($workEndTime !== null && $workEndTime !== '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value)
                        VALUES (:user_id, 'work_end_time', :value)
                        ON DUPLICATE KEY UPDATE setting_value = :value2
                    ");
                    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':value', $workEndTime);
                    $stmt->bindValue(':value2', $workEndTime);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $errors[] = 'Arbeitsende: ' . $e->getMessage();
                }
            }
            
            if ($employmentStartDate !== null && $employmentStartDate !== '') {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, setting_key, setting_value)
                        VALUES (:user_id, 'employment_start_date', :value)
                        ON DUPLICATE KEY UPDATE setting_value = :value2
                    ");
                    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->bindValue(':value', $employmentStartDate);
                    $stmt->bindValue(':value2', $employmentStartDate);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $errors[] = 'Anstellungsdatum: ' . $e->getMessage();
                }
            }
            
            if (!empty($errors)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Einstellungen gespeichert']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
