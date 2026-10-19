<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: text/html; charset=Shift-JIS');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cloudvmu_password_configured()) {
        $error = 'No admin password is configured on this server.';
    } elseif (isset($_POST['password']) && password_verify($_POST['password'], CLOUDVMU_ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['cloudvmu_admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        sleep(1); // slow down guessing
        $error = 'Wrong password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cloud VMU - Admin</title>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift-JIS">
</head>
<body bgcolor="#f0ffff">
    <h1><font size="6">Cloud VMU Admin</font></h1>
    <?php if ($error !== '') echo '<p><i>' . h($error) . '</i></p>'; ?>
    <form action="login.php" method="post">
        <input type="password" name="password">
        <input type="submit" value="Log in">
    </form>
    <p><a href="index.php">Back</a></p>
</body>
</html>
