<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$error = '';

if ($_POST) {
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error = 'Molimo unesite email i lozinku.';
    } else {
        if ($auth->login($email, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Neispravni podaci za prijavu.';
        }
    }
}

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prijava - CAP Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="../global_assets/favicon.png">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <img class="mx-auto h-20 w-auto" src="../global_assets/cap_logo.png" alt="CAP Logo">
                <h2 class="mt-6 text-3xl font-bold text-gray-900" style="font-family: 'Poppins', sans-serif;">
                    Caritas Assistant Portal
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Administracija sistema
                </p>
            </div>
            
            <form class="mt-8 space-y-6" method="POST" action="">
                <div class="card">
                    <?php if ($error): ?>
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email adresa
                            </label>
                            <input id="email" name="email" type="email" required 
                                   class="form-input" placeholder="Unesite email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                Lozinka
                            </label>
                            <input id="password" name="password" type="password" required 
                                   class="form-input" placeholder="Unesite lozinku">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="btn-primary w-full">
                            Prijavite se
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="text-center text-sm text-gray-600">
                <p>Default login podaci:</p>
                <p><strong>Email:</strong> milos@studiopresent.com</p>
                <p><strong>Lozinka:</strong> miki1818</p>
            </div>
        </div>
    </div>
</body>
</html>