<?php
// db.php - PDO MySQL connection helper
session_start();

function getDB(){
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = '127.0.0.1';
    $db   = 'pos_db';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try{
        $pdo = new PDO($dsn, $user, $pass, $opt);
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS table_number VARCHAR(50) NULL AFTER total_price");
        } catch (PDOException $e) {
            // ignore migration warnings during normal operation; the app can continue with the existing schema.
        }
        $pdo->exec("UPDATE items SET name = CASE name
            WHEN 'Nasi Lemak Sambal Udang' THEN 'Chicken Rendang'
            WHEN 'Teh Tarik' THEN 'Barley Lime'
            WHEN 'Sirap Bandung' THEN 'Ais Limau'
            ELSE name END
            WHERE name IN ('Nasi Lemak Sambal Udang', 'Teh Tarik', 'Sirap Bandung')");
        $pdo->exec("UPDATE items SET name = 'Barley Lime'
            WHERE name = 'Ais Limau' AND category = 'drink'
                            AND (description LIKE 'Fresh lime cooler%' OR description LIKE '%barley%' OR price IN (5.20, 5.60))");
        return $pdo;
    }catch(PDOException $e){
        http_response_code(500);
        echo "Database connection failed: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

function is_logged_in(){
    return !empty($_SESSION['user_id']);
}

function menu_item_image($name){
    $images = [
        'Chicken Rendang' => 'assets/menu/chicken-rendang.jpg.jpg',
        'Char Kway Teow' => 'assets/menu/char-kway-teow.jpg.jpg',
        'Barley Lime' => 'assets/menu/barley-lime.jpg.jpg',
        'Ais Limau' => 'assets/menu/ais-limau.jpg.webp'
    ];
    return $images[$name] ?? 'https://images.unsplash.com/photo-1498837167922-ddd27525d352?auto=format&fit=crop&w=1000&q=85';
}

function require_role($roles){
    if(!is_logged_in()){ header('Location: login.php'); exit; }
    $role = $_SESSION['role'] ?? '';
    $normalized = ($role === 'manager') ? 'admin' : $role;
    $allowed = array_map(function($value){
        return $value === 'manager' ? 'admin' : $value;
    }, is_array($roles) ? $roles : [$roles]);
    if(!in_array($normalized, $allowed, true)){
        echo "<div class=\"container mt-5\"><div class=\"alert alert-danger\">Access denied.</div></div>";
        exit;
    }
}

function require_admin_only(){
    if(!is_logged_in()){ header('Location: login.php'); exit; }
    $role = $_SESSION['role'] ?? '';
    $normalized = ($role === 'manager') ? 'admin' : $role;
    $allowed = ['admin'];
    if(!in_array($normalized, $allowed, true)){
        echo "<div class=\"container mt-5\"><div class=\"alert alert-danger\">Admin access required.</div></div>";
        exit;
    }
}

// CSRF helpers
function get_csrf_token(){
    if(!isset($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token){
    if(empty($token)) return false;
    if(!isset($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

?>