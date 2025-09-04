<?php
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

function formatTime($time, $format = 'H:i') {
    if (!$time) return '';
    return date($format, strtotime($time));
}

function formatDateTime($datetime, $format = 'd.m.Y H:i') {
    if (!$datetime) return '';
    return date($format, strtotime($datetime));
}

function getStatusText($status) {
    switch ($status) {
        case 'zakazana':
            return 'Zakazana';
        case 'u_toku':
            return 'U toku';
        case 'zavrsena':
            return 'Završena';
        case 'otkazana':
            return 'Otkazana';
        default:
            return ucfirst($status);
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'zakazana':
            return 'bg-blue-100 text-blue-800';
        case 'u_toku':
            return 'bg-yellow-100 text-yellow-800';
        case 'zavrsena':
            return 'bg-green-100 text-green-800';
        case 'otkazana':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function minutesToHours($minutes) {
    if (!$minutes) return '0:00';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%d:%02d', $hours, $mins);
}

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function showAlert($message, $type = 'info') {
    $alertClass = 'bg-blue-100 border-blue-400 text-blue-700';
    
    switch($type) {
        case 'success':
            $alertClass = 'bg-green-100 border-green-400 text-green-700';
            break;
        case 'error':
            $alertClass = 'bg-red-100 border-red-400 text-red-700';
            break;
        case 'warning':
            $alertClass = 'bg-yellow-100 border-yellow-400 text-yellow-700';
            break;
    }
    
    echo "<div class='border-l-4 p-4 mb-4 {$alertClass}' role='alert'>{$message}</div>";
}

function getDaysOfWeek() {
    return [
        1 => 'Ponedeljak',
        2 => 'Utorak',
        3 => 'Sreda',
        4 => 'Četvrtak',
        5 => 'Petak',
        6 => 'Subota',
        7 => 'Nedelja'
    ];
}

function getStrucnaSpremaSrbija() {
    return [
        'Osnovna škola',
        'Srednja stručna škola',
        'Gimnazija',
        'Viša škola',
        'Fakultet',
        'Master',
        'Doktorat'
    ];
}
?>