<?php
require_once __DIR__.'/db.php';
// log logout event if possible
if(!empty($_SESSION['user_id'])){
    try{
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ins = $db->prepare('INSERT INTO audit_log (user_id, action, ip) VALUES (?, ?, ?)');
        $ins->execute([$_SESSION['user_id'], 'logout', $ip]);
    }catch(Exception $e){
        // ignore
    }
}
session_unset(); session_destroy();
header('Location: index.php'); exit;
?>