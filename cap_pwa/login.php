<?php
require_once '../cap_admin/includes/config.php';
require_once '../cap_admin/includes/database.php';
require_once '../cap_admin/includes/auth.php';

$auth = new Auth();

// If already logged in, redirect
if ($auth->isLoggedIn()) {
    $currentUser = $auth->getCurrentUser();
    if ($currentUser['type'] === 'upravitelj') {
        header('Location: ../cap_admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Molimo unesite email i lozinku';
    } else {
        if ($auth->login($email, $password)) {
            $currentUser = $auth->getCurrentUser();
            if ($currentUser['type'] === 'upravitelj') {
                header('Location: ../cap_admin/index.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Pogrešan email ili lozinka';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prijava - CAP</title>
    <link rel="stylesheet" href="css/tailwind.css?v=<?php echo time(); ?>">
    <script src="js/offline-storage.js?v=<?php echo time(); ?>"></script>
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#E24135">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CAP">
    <link rel="apple-touch-icon" href="../global_assets/cap_logo.png">
    
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="../global_assets/cap_logo.png" alt="CAP Logo" class="h-20 w-auto mx-auto mb-4">
            <h1 class="text-2xl font-bold text-gray-900">CAP Mobile</h1>
            <p class="text-gray-600 mt-2">Prijavite se da biste pristupili aplikaciji</p>
        </div>
        
        <!-- Login Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if ($error): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email adresa
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Lozinka
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                
                <button type="submit" 
                        class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 font-medium">
                    Prijavite se
                </button>
            </form>
        </div>
        
        <div class="text-center mt-6 text-sm text-gray-600">
            <p>CAP - Caritas Assistant Portal</p>
            <p class="mt-1">Za tehničku podršku kontaktirajte administratora</p>
        </div>
    </div>

    <!-- Service Worker se registruje samo na glavnoj stranici -->
</body>
</html>