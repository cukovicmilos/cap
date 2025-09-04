<header class="navbar">
    <div class="px-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <img src="../global_assets/cap_logo.png" alt="CAP Logo" class="h-10 w-auto">
            </div>
            
            <div class="flex items-center space-x-4">
                <span class="text-sm">
                    Dobrodošli, <?php echo htmlspecialchars($currentUser['name']); ?>
                </span>
                <a href="logout.php" class="text-sm bg-red-700 px-3 py-1 rounded hover:bg-red-800">
                    Odjava
                </a>
            </div>
        </div>
    </div>
</header>