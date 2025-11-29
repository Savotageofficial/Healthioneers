<?php
session_start();

// Guard: only patients
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// DB connect
$server = "DESKTOP-OG4GIGD";
$db = "Vaccination";
$conn = sqlsrv_connect($server, ["Database" => $db]);
if ($conn === false) {
    die("Database connection failed.");
}

$patientUsername = $_SESSION['user_email'] ?? '';
$patient = null;
$message = "";
$error = "";
$modalError = "";
$modalMessage = "";

// Fetch patient data
$sql = "SELECT Patient_ID, Patient_FName, Patient_LName, Patient_Username,
               Patient_Password, Patient_City_ID, Patient_Phone, Patient_National_ID
        FROM Patients WHERE Patient_Username = ?";
$stmt = sqlsrv_query($conn, $sql, [$patientUsername]);
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    $patient = $row;
} else {
    $error = "Patient record not found.";
}

// Fetch cities for dropdown
$cities = [];
$csql = "SELECT City_ID, City_Name FROM Cities ORDER BY City_Name";
$cstmt = sqlsrv_query($conn, $csql);
while ($cstmt && ($crow = sqlsrv_fetch_array($cstmt, SQLSRV_FETCH_ASSOC))) {
    $cities[] = $crow;
}

// Handle account update (excluding password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $username = $_POST['username'] ?? '';
    $cityId = (int)($_POST['city_id'] ?? 0);
    $phone = $_POST['phone'] ?? '';

    $updateSql = "UPDATE Patients
                  SET Patient_FName = ?, Patient_LName = ?, Patient_Username = ?,
                      Patient_City_ID = ?, Patient_Phone = ?
                  WHERE Patient_ID = ?";
    $params = [$fname, $lname, $username, $cityId, $phone, $patient['Patient_ID']];
    $ustmt = sqlsrv_query($conn, $updateSql, $params);

    if ($ustmt) {
        $message = "✅ Account updated successfully.";
        $_SESSION['user_email'] = $username;
        // Refresh patient data
        $stmt = sqlsrv_query($conn, $sql, [$username]);
        if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            $patient = $row;
        }
    } else {
        $error = "❌ Failed to update account.";
    }
}

// Handle password change (plain text check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $checkSql = "SELECT Patient_Password FROM Patients WHERE Patient_ID = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, [$patient['Patient_ID']]);
    $checkRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;

    if (!$checkRow || $checkRow['Patient_Password'] !== $oldPassword) {
        $modalError = "❌ Old password is incorrect.";
    } elseif ($newPassword !== $confirmPassword) {
        $modalError = "❌ New passwords do not match.";
    } else {
        $updatePassSql = "UPDATE Patients SET Patient_Password = ? WHERE Patient_ID = ?";
        $params = [$newPassword, $patient['Patient_ID']];
        $upStmt = sqlsrv_query($conn, $updatePassSql, $params);

        if ($upStmt) {
            $modalMessage = "✅ Password changed successfully.";
            // Refresh patient data
            $stmt = sqlsrv_query($conn, $sql, [$patientUsername]);
            if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                $patient = $row;
            }
        } else {
            $modalError = "❌ Failed to change password.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Account — Healthioneers</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .account-card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 2rem auto;
        }
        .account-card h2 {
            margin-bottom: 1rem;
            color: var(--teal);
            border-bottom: 2px solid #f0f2f8;
            padding-bottom: .5rem;
        }
        .account-card form {
            display: grid;
            gap: 1rem;
        }
        .account-card label {
            font-weight: 600;
            color: #333;
        }
        .account-card input, .account-card select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
        }
        .account-card input:focus, .account-card select:focus {
            border-color: var(--teal);
            outline: none;
            box-shadow: 0 0 0 2px rgba(47,125,246,0.2);
        }
        .readonly {
            background: #f9f9f9;
            color: #555;
        }
        .btn {
            background: linear-gradient(135deg, var(--teal), var(--teal-lighter));
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-position 0.4s ease, transform 0.2s ease;
            background-size: 200% 200%;
            background-position: left center;
        }
        .btn:hover {
            background-position: right center;
            transform: translateY(-2px);
        }
        /* Modal styling */
        /* Overlay */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        /* Modal card */
        .modal-content {
            background: #fff;
            margin: auto;
            padding: 2.5rem;              /* more padding inside */
            border-radius: 18px;
            width: 95%;
            max-width: 520px;             /* wider card */
            box-shadow: 0 12px 28px rgba(0,0,0,0.25);
            position: relative;
            top: 50%;
            transform: translateY(-50%);
            animation: slideUp 0.35s ease;
        }

        /* Title */
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.6rem;
            color: var(--teal);
            border-bottom: 2px solid #f0f2f8;
            padding-bottom: 0.5rem;
        }

        /* Close button */
        .close {
            position: absolute;
            top: 16px;
            right: 20px;
            font-size: 1.6rem;
            cursor: pointer;
            color: #888;
            transition: color 0.2s ease;
        }
        .close:hover { color: var(--teal); }

        /* Form layout */
        .modal-content form {
            display: grid;
            gap: 1.5rem;                  /* more spacing between fields */
        }

        /* Labels */
        .modal-content label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.4rem;
            display: block;
        }

        /* Inputs */
        .password-field {
            display: flex;
            align-items: center;
            position: relative;
        }
        .password-field input {
            flex: 1;
            padding: 14px 16px;           /* taller input boxes */
            border: 1px solid #ccc;
            border-radius: 10px;
            font-size: 1rem;
            background: #fafafa;
        }
        .password-field input:focus {
            border-color: var(--teal);
            outline: none;
            box-shadow: 0 0 0 3px rgba(47,125,246,0.25);
            background: #fff;
        }

        /* Eye icon */
        .toggle-eye {
            margin-left: -40px;           /* sits inside input box */
            cursor: pointer;
            font-size: 1.3rem;
            color: #666;
            transition: color 0.2s ease;
        }
        .toggle-eye:hover { color: var(--teal); }

        /* Button */
        .modal-content button {
            background: linear-gradient(135deg, var(--teal), var(--teal-lighter));
            color: #fff;
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: background-position 0.4s ease, transform 0.2s ease, box-shadow 0.3s ease;
            background-size: 200% 200%;
            background-position: left center;
        }
        .modal-content button:hover {
            background-position: right center;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .modal-content button:active { transform: scale(0.97); }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(-40%); opacity: 0; } to { transform: translateY(-50%); opacity: 1; } }

        /* Error & success messages inside modal */
        .error { color:#d9534f; font-weight:bold; margin-bottom:1rem; }
        .message { color:#28a745; font-weight:bold; margin-bottom:1rem; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container nav-row">
        <div class="logo">
            <img src="Imgs/WhatsApp Image 2025-09-11 at 15.19.37_d6b5bce8.jpg" alt="Healthineers Logo">
            <a href="Patient_home.php">Healthioneers</a>
        </div>
        <nav class="main-nav">
            <a href="Patient_home.php">Home</a>
            <a href="Patient_Vaccines.php" data-link>Vaccines</a>
            <a href="Patient_reservation.php" data-link class="active">My Reservation</a>
            <a href="Patient_about.php" data-link>About</a>

            <!-- Dropdown Menu -->
            <div class="dropdown">
                <button class="dropbtn">Account ▾</button>
                <div class="dropdown-content">
                    <a href="Patient_Account.php">My Account</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </nav>
        <button class="nav-toggle">☰</button>
    </div>
</header>
<main>
    <div class="account-card">
        <h2>My Account</h2>
        <?php if ($message): ?><p class="message"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <?php if ($patient): ?>
            <form method="POST" action="Patient_account.php">
                <input type="hidden" name="update" value="1">

                <label>First Name</label>
                <input type="text" name="fname" value="<?php echo htmlspecialchars($patient['Patient_FName']); ?>" required>

                <label>Last Name</label>
                <input type="text" name="lname" value="<?php echo htmlspecialchars($patient['Patient_LName']); ?>" required>

                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($patient['Patient_Username']); ?>" required>

                <label>Password</label>
                <input type="password" value="********" readonly class="readonly">
                <button type="button" class="btn" id="openPasswordModal">Change Password</button>

                <label>City</label>
                <select name="city_id" required>
                    <option value="">Select City</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo (int)$c['City_ID']; ?>"
                            <?php if ($c['City_ID'] == $patient['Patient_City_ID']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['City_Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($patient['Patient_Phone']); ?>" required>

                <label>National ID</label>
                <input type="text" value="<?php echo htmlspecialchars($patient['Patient_National_ID']); ?>" readonly class="readonly">

                <button type="submit" class="btn">Update Account</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<!-- Password Modal -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Change Password</h3>

        <?php if (!empty($modalError)): ?>
            <p class="error"><?php echo htmlspecialchars($modalError); ?></p>
        <?php endif; ?>
        <?php if (!empty($modalMessage)): ?>
            <p class="message"><?php echo htmlspecialchars($modalMessage); ?></p>
        <?php endif; ?>

        <form method="POST" action="Patient_account.php">
            <input type="hidden" name="change_password" value="1">

            <div class="password-field">
                <label>Old Password</label>
                <input type="password" name="old_password" id="oldPassword" required>
                <span class="toggle-eye" onclick="togglePassword('oldPassword')">👁</span>
            </div>

            <div class="password-field">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPassword" required>
                <span class="toggle-eye" onclick="togglePassword('newPassword')">👁</span>
            </div>

            <div class="password-field">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPassword" required>
                <span class="toggle-eye" onclick="togglePassword('confirmPassword')">👁</span>
            </div>

            <button type="submit" class="btn">Update Password</button>
        </form>
    </div>
</div>

<?php if (!empty($modalError) || !empty($modalMessage)): ?>
    <script>
        // Reopen modal automatically if there was an error or success message
        document.getElementById("passwordModal").style.display = "block";
    </script>
<?php endif; ?>

<script>
    // Modal logic
    const passModal = document.getElementById("passwordModal");
    const openBtn = document.getElementById("openPasswordModal");
    const closeBtn = passModal.querySelector(".close");

    openBtn.onclick = () => passModal.style.display = "block";
    closeBtn.onclick = () => passModal.style.display = "none";
    window.onclick = (event) => { if (event.target == passModal) passModal.style.display = "none"; };

    // Toggle eye function
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        field.type = field.type === "password" ? "text" : "password";
    }
</script>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-section">
            <strong>Healthineers</strong>
            <div class="muted">Free Vaccination & Wellness</div>
            <p>Your trusted partner in community health and preventive care.</p>
        </div>
        <div class="footer-section">
            <h4>Quick Links</h4>
            <nav class="footer-nav">
                <a href="index.html">Home</a>
                <a href="about.html" data-link>About Us</a>
                <a href="services.html" data-link>Services</a>
                <a href="store.html" data-link>Health Products</a>
            </nav>
        </div>
        <div class="footer-section">
            <h4>Medical Info</h4>
            <nav class="footer-nav">
                <a href="#">Vaccination Guide</a>
                <a href="#">FAQs</a>
                <a href="#">Medical Disclaimer</a>
            </nav>
        </div>
        <div class="footer-section">

            <div class="social-links">

            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 Healthineers. Providing free vaccination services for all. All rights reserved.</p>
    </div>
</footer>
</body>
</html>