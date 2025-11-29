<?php
session_start();

require 'C:/PHP-MAIL/PHPMailer-master/src/Exception.php';
require 'C:/PHP-MAIL/PHPMailer-master/src/PHPMailer.php';
require 'C:/PHP-MAIL/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CONFIG
$senderEmail = 'firepanda976@gmail.com';
$senderName = 'OTP System';
$senderPassword = 'zuezmonnzpunkjte'; // Gmail App Password
$otpLength = 6;
$expiryMinutes = 5;

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}
function sendOTP($email, $otp) {
    global $senderEmail, $senderName, $senderPassword, $expiryMinutes;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $senderEmail;
        $mail->Password = $senderPassword;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = [
                'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                ]
        ];

        $mail->setFrom($senderEmail, $senderName);
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your OTP is: $otp\nIt expires in $expiryMinutes minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

$error = "";
$message = "";
$showOtpBox = false; // controls which box is shown

// Tables to check
$tables = [
        "Admin" => ["Admin_Username", "Admin_Password", "admin_home.php", "admin"],
        "Vaccination_Centers" => ["Center_Username", "Center_Password", "center_home.php", "center"],
        "Patients" => ["Patient_Username", "Patient_Password", "Patient_home.php", "patient"]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Back to login
    if (isset($_POST['back_to_login'])) {
        $showOtpBox = false;
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['redirect'], $_SESSION['user_email'], $_SESSION['role'], $_SESSION['fname']);
    }

    // Verify OTP
    elseif (isset($_POST['verify_otp'])) {
        $userOTP = $_POST['otp'] ?? '';
        $validOTP = $_SESSION['otp'] ?? '';
        $expiry = $_SESSION['otp_expiry'] ?? 0;

        if (!$validOTP) {
            $error = "❌ No OTP session found. Please login again.";
            $showOtpBox = false;
        } elseif (time() > $expiry) {
            $error = "⏰ OTP expired. Please resend OTP.";
            $showOtpBox = true;
        } elseif ($userOTP === $validOTP) {
            $redirect = $_SESSION['redirect'] ?? '';
            unset($_SESSION['otp'], $_SESSION['otp_expiry']);
            header("Location: $redirect");
            exit();
        } else {
            $error = "❌ Invalid OTP.";
            $showOtpBox = true;
        }
    }

    // Resend OTP
    elseif (isset($_POST['resend_otp'])) {
        $email = $_SESSION['user_email'] ?? '';
        if (!$email) {
            $error = "❌ No user session. Please login again.";
            $showOtpBox = false;
        } else {
            $otp = generateOTP($otpLength);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expiry'] = time() + ($expiryMinutes * 60);
            $message = sendOTP($email, $otp) ? "✅ OTP resent to $email" : "❌ Failed to resend OTP.";
            $showOtpBox = true;
        }
    }

    // Login credentials
    else {
        $conn = sqlsrv_connect("DESKTOP-OG4GIGD", ["Database" => "Vaccination"]);
        if ($conn === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $authenticated = false;
        foreach ($tables as $table => $info) {
            list($emailCol, $passCol, $redirect, $role) = $info;
            $sql = "SELECT * FROM $table WHERE $emailCol = ? AND $passCol = ?";
            $stmt = sqlsrv_query($conn, $sql, [$email, $password]);

            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                // ✅ Assign session variables from DB + role
                $_SESSION['user_email'] = $row[$emailCol];
                $_SESSION['role'] = $role;
                $_SESSION['redirect'] = $redirect;

                // Store extra info for greeting
                if ($role === 'admin' && isset($row['Admin_Fname'])) {
                    $_SESSION['fname'] = $row['Admin_Fname'];
                } elseif ($role === 'center' && isset($row['Center_Name'])) {
                    $_SESSION['fname'] = $row['Center_Name'];
                } elseif ($role === 'patient' && isset($row['Patient_FName'])) {
                    $_SESSION['fname'] = $row['Patient_FName'];
                }

                // Generate OTP
                $otp = generateOTP($otpLength);
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_expiry'] = time() + ($expiryMinutes * 60);

                $message = sendOTP($row[$emailCol], $otp)
                        ? "✅ OTP sent to {$row[$emailCol]}"
                        : "❌ Failed to send OTP.";

                $showOtpBox = true;
                $authenticated = true;
                break;
            }
        }



        if (!$authenticated) {
            $error = "❌ Invalid login credentials.";
            $showOtpBox = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Healthineers</title>
    <link rel="stylesheet" href="css/login&register.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<section class="content" id="login">
    <?php if (!$showOtpBox): ?>
        <!-- Login Box -->
        <div class="form-container">
            <h2>Login</h2>

            <?php if ($message) echo "<p class='message' style='font-weight:bold;'>".htmlspecialchars($message)."</p>"; ?>
            <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>

            <form method="POST" action="login.php">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit" name="login">Login</button>
            </form>

            <p class="form-link">Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    <?php else: ?>
        <!-- OTP Box replaces Login -->
        <div class="form-container">
            <h2>OTP Verification</h2>
            <?php if ($message) echo "<p class='message' style='font-weight:bold;'>".htmlspecialchars($message)."</p>"; ?>
            <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>

            <form method="POST" action="login.php">
                <label for="otp">Enter OTP</label>
                <input type="text" id="otp" name="otp" placeholder="Enter OTP" required>
                <button type="submit" name="verify_otp" value="1">Verify OTP</button>
            </form>

            <form method="POST" action="login.php" style="margin-top:10px;">
                <button type="submit" name="resend_otp" value="1">Resend OTP</button>
            </form>

            <form method="POST" action="login.php" style="margin-top:10px;">
                <button type="submit" name="back_to_login" value="1">Back to Login</button>
            </form>
        </div>
    <?php endif; ?>
</section>
</body>
</html>