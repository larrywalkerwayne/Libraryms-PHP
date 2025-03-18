<?php
require "../db_connect.php";
require "../message_display.php";
require "verify_librarian.php";
require "header_librarian.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Composer autoload for PHPMailer
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
// Function to send email using PHPMailer
function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your@gmail.com'; // Your Gmail address
        $mail->Password   = 'password'; // Use an App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your@gmail.com', 'Library Management System');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

// Fetch pending book requests
$query = $con->prepare("SELECT * FROM pending_book_requests;");
$query->execute();
$result = $query->get_result();
$requests_data = $result->fetch_all(MYSQLI_ASSOC);

if (empty($requests_data)) {
    echo "<h2 align='center'>No requests pending</h2>";
} else {
    echo "<form class='cd-form' method='POST'>";
    echo "<center><legend>Pending Book Requests</legend></center>";
    echo "<table width='100%' cellpadding='10' cellspacing='10'>";
    echo "<tr>
            <th>Select</th>
            <th>Username</th>
            <th>Book</th>
            <th>Time</th>
          </tr>";

    foreach ($requests_data as $row) {
		echo "<tr>
        <td>
            <label class='control control--checkbox'>
                <input type='checkbox' name='cb_{$row['request_id']}' value='{$row['request_id']}' />
                <div class='control__indicator'></div>
            </label>
        </td>
        <td>{$row['member']}</td>
        <td>{$row['book_isbn']}</td>
        <td>" . (isset($row['request_time']) ? $row['request_time'] : "N/A") . "</td>
      </tr>";
}

    echo "</table><br />";
    echo "<div style='float: right;'>
            <input type='submit' value='Reject Request' name='l_reject' />
            <input type='submit' value='Allow' name='l_grant'/>
          </div>";
    echo "</form>";
}

// Process Grant Requests
if (isset($_POST['l_grant'])) {
    processRequests($con, $requests_data, true);
}

// Process Reject Requests
if (isset($_POST['l_reject'])) {
    processRequests($con, $requests_data, false);
}

// Function to process requests (Grant/Reject)
function processRequests($con, $requests_data, $grant) {
    $requests_processed = 0;

    foreach ($requests_data as $row) {
        $request_id = $row['request_id'];
        if (isset($_POST['cb_' . $request_id])) {
            $member = $row['member'];
            $isbn = $row['book_isbn'];

            // Get Member Email
            $query = $con->prepare("SELECT email FROM member WHERE username = ?;");
            $query->bind_param("s", $member);
            $query->execute();
            $email = $query->get_result()->fetch_column();

            // Get Book Title
            $query = $con->prepare("SELECT title FROM book WHERE isbn = ?;");
            $query->bind_param("s", $isbn);
            $query->execute();
            $title = $query->get_result()->fetch_column();

            if ($grant) {
                // Insert into book issue log
                $query = $con->prepare("INSERT INTO book_issue_log(member, book_isbn) VALUES(?, ?);");
                $query->bind_param("ss", $member, $isbn);
                if ($query->execute()) {
                    $requests_processed++;

                    // Get Due Date
                    $query = $con->prepare("SELECT due_date FROM book_issue_log WHERE member = ? AND book_isbn = ?;");
                    $query->bind_param("ss", $member, $isbn);
                    $query->execute();
                    $due_date = $query->get_result()->fetch_column();

                    $message = "The book '{$title}' with ISBN {$isbn} has been issued to your account. The due date to return the book is {$due_date}.";
                    sendEmail($email, "Book Issued", $message);
                }
            } else {
                // Delete request from pending_book_requests
                $query = $con->prepare("DELETE FROM pending_book_requests WHERE request_id = ?;");
                $query->bind_param("d", $request_id);
                if ($query->execute()) {
                    $requests_processed++;

                    $message = "Your request for issuing the book '{$title}' with ISBN {$isbn} has been rejected.";
                    sendEmail($email, "Book Issue Rejected", $message);
                }
            }
        }
    }

    if ($requests_processed > 0) {
        echo success("Successfully processed {$requests_processed} requests");
    } else {
        echo error_without_field("No request selected");
    }
}
?>
</body>
