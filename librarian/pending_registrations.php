<?php
require "../db_connect.php";
require "../message_display.php";
require "verify_librarian.php";
require "header_librarian.php";

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Use Composer's autoloader

?>

<html>
<head>
    <title>LMS</title>
    <link rel="stylesheet" type="text/css" href="../css/global_styles.css">
    <link rel="stylesheet" type="text/css" href="../css/custom_checkbox_style.css">
    <link rel="stylesheet" type="text/css" href="css/pending_registrations_style.css">
</head>
<body>
    <?php
    $query = $con->prepare("SELECT username, name, email, balance FROM pending_registrations");
    $query->execute();
    $result = $query->get_result();
    $rows = $result->num_rows;

    if ($rows == 0) {
        echo "<h2 align='center'>None at the moment!</h2>";
    } else {
        echo "<form class='cd-form' method='POST' action='#'>";
        echo "<center><legend>Pending Membership Registration</legend></center>";
        echo "<div class='error-message' id='error-message'><p id='error'></p></div>";
        echo "<table width='100%' cellpadding=10 cellspacing=10>
                <tr>
                    <th></th>
                    <th>Username<hr></th>
                    <th>Name<hr></th>
                    <th>Email<hr></th>
                    <th>Balance<hr></th>
                </tr>";

        $i = 0;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>
                    <label class='control control--checkbox'>
                        <input type='checkbox' name='cb_$i' value='" . $row['username'] . "' />
                        <div class='control__indicator'></div>
                    </label>
                  </td>";
            echo "<td>{$row['username']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['email']}</td>";
            echo "<td>Rs.{$row['balance']}</td>";
            echo "</tr>";
            $i++;
        }

        echo "</table><br /><br />";
        echo "<div style='float: right;'>";
        echo "<input type='submit' value='Confirm Verification' name='l_confirm' />&nbsp;&nbsp;&nbsp;";
        echo "<input type='submit' value='Reject' name='l_delete' />";
        echo "</div>";
        echo "</form>";
    }

    // Set up PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your@gmail.com'; // Replace with your email
        $mail->Password   = 'password'; // Use an App Password, not a real password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('your@gmail.com', 'Library Management System');
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['l_confirm'])) {
            $members = 0;
            for ($i = 0; $i < $rows; $i++) {
                if (isset($_POST["cb_$i"])) {
                    $username = $_POST["cb_$i"];

                    // Fetch user data from pending registrations
                    $query = $con->prepare("SELECT username, password, name, email, balance FROM pending_registrations WHERE username = ?");
                    $query->bind_param("s", $username);
                    $query->execute();
                    $result = $query->get_result();
                    $row = $result->fetch_assoc();

                    if (!$row || empty($row['password'])) {
                        echo error_without_field("ERROR: Password is missing for user $username.");
                        continue;
                    }

                    // Insert into member table
                    $query = $con->prepare("INSERT INTO member (username, password, name, email, balance) VALUES (?, ?, ?, ?, ?)");
                    $query->bind_param("ssssd", $row['username'], $row['password'], $row['name'], $row['email'], $row['balance']);
                    if (!$query->execute()) {
                        echo error_without_field("ERROR: Couldn't insert values for user $username.");
                        continue;
                    }
                    $members++;

                    // Send confirmation email
                    $mail->clearAddresses();
                    $mail->addAddress($row['email']);
                    $mail->isHTML(true);
                    $mail->Subject = "Library Membership Accepted";
                    $mail->Body = "Your membership has been accepted by the library. You can now issue books using your account.";

                    try {
                        $mail->send();
                    } catch (Exception $e) {
                        echo "Email Error: " . $mail->ErrorInfo;
                    }
                }
            }
            if ($members > 0) {
                echo success("Successfully added $members members.");
            } else {
                echo error_without_field("No registration selected.");
            }
        }

        if (isset($_POST['l_delete'])) {
            $requests = 0;
            for ($i = 0; $i < $rows; $i++) {
                if (isset($_POST["cb_$i"])) {
                    $username = $_POST["cb_$i"];

                    // Fetch email before deleting
                    $query = $con->prepare("SELECT email FROM pending_registrations WHERE username = ?");
                    $query->bind_param("s", $username);
                    $query->execute();
                    $result = $query->get_result();
                    $row = $result->fetch_assoc();
                    $email = $row['email'] ?? null;

                    // Delete from pending registrations
                    $query = $con->prepare("DELETE FROM pending_registrations WHERE username = ?");
                    $query->bind_param("s", $username);
                    if (!$query->execute()) {
                        echo error_without_field("ERROR: Couldn't delete values for user $username.");
                        continue;
                    }
                    $requests++;

                    // Send rejection email
                    if ($email) {
                        $mail->clearAddresses();
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = "Library Membership Rejected";
                        $mail->Body = "Your membership has been rejected by the library. Please contact a librarian for further information.";

                        try {
                            $mail->send();
                        } catch (Exception $e) {
                            echo "Email Error: " . $mail->ErrorInfo;
                        }
                    }
                }
            }
            if ($requests > 0) {
                echo success("Successfully deleted $requests requests.");
            } else {
                echo error_without_field("No registration selected.");
            }
        }
    }
    ?>
</body>
</html>
