<?php
// Check if the session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start the session if it hasn't been started yet
}

// Check if the username is set in the session
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; // Default to 'Guest' if not set
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="http://fonts.googleapis.com/css?family=Open+Sans:400,300,700">
    <link rel="stylesheet" type="text/css" href="css/header_librarian_style.css" />
    <title>Library Management System</title>
</head>
<body>
    <header>
        <div id="cd-logo">
            <a href="../">
                <img src="img/ic_logo2.svg" alt="Library Logo" width="45" height="45" />
                <p>Library Management System</p>
            </a>
        </div>
        
        <div class="dropdown">
            <button class="dropbtn"> 
                <p id="librarian-name">@<?php echo $username; ?></p>
            </button>
            <div class="dropdown-content">
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </header>
</body>
</html>