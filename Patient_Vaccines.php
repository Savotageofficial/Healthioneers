<?php
session_start();

// Guard: only patients
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') {
    echo "<h2 style='color:red; text-align:center;'>Unauthorized Access</h2>";
    header("Refresh:2; url=login.php");
    exit();
}

// DB connect
$server = "DESKTOP-OG4GIGD";
$db = "Vaccination";
$conn = sqlsrv_connect($server, ["Database" => $db]);
if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }

// Resolve patient from session
$patientUsername = $_SESSION['user_email'] ?? '';
$patientId = null;
$patientFName = "Patient";

$sql = "SELECT Patient_ID, Patient_FName, Status 
        FROM Patients WHERE Patient_Username = ?";
$stmt = sqlsrv_query($conn, $sql, [$patientUsername]);
if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    if (strtolower($row['Status'] ?? 'active') !== 'active') {
        header("Location: login.php");
        exit();
    }
    $patientId = (int)$row['Patient_ID'];
    $patientFName = $row['Patient_FName'] ?? $patientFName;
} else {
    header("Location: login.php");
    exit();
}

// Fetch active vaccines
$vaccines = [];
$vsql = "SELECT Vaccine_ID, Vaccine_Name, dose_gap_days, Precautions, Status 
         FROM Vaccines WHERE Status = 'Active' ORDER BY Vaccine_Name";
$vstmt = sqlsrv_query($conn, $vsql);
while ($vstmt && ($vrow = sqlsrv_fetch_array($vstmt, SQLSRV_FETCH_ASSOC))) {
    $vaccines[] = $vrow;
}

// Fetch active centers
$centers = [];
$csql = "SELECT Center_ID, Center_Name, Center_Address 
         FROM Vaccination_Centers WHERE Status = 'Active' ORDER BY Center_Name";
$cstmt = sqlsrv_query($conn, $csql);
while ($cstmt && ($crow = sqlsrv_fetch_array($cstmt, SQLSRV_FETCH_ASSOC))) {
    $centers[] = $crow;
}

$message = "";
$error = "";

// Handle reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $centerId = (int)($_POST['center_id'] ?? 0);
    $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
    $firstDoseDate = $_POST['first_dose_date'] ?? '';

    if (!$centerId || !$vaccineId || !$firstDoseDate) {
        $error = "Please complete all fields.";
    } else {
        // Prevent duplicate active reservation
        $checkSql = "SELECT Reservation_ID FROM Reservations WHERE Patient_ID=? AND Reservation_Status='Ongoing'";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$patientId]);
        if ($checkStmt && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            $error = "❌ You already have an active reservation.";
        } else {
            $firstDoseDateSql = date('Y-m-d', strtotime($firstDoseDate));

            // Insert reservation
            $insert = "INSERT INTO Reservations
                       (Patient_ID, Vaccine_ID, Center_ID, Dose_Number, Scheduled_Date,
                        First_Confirmation, Second_Confirmation, Reservation_Status)
                       OUTPUT INSERTED.Reservation_ID
                       VALUES (?, ?, ?, 1, ?, 0, 0, 'Ongoing')";
            $params = [$patientId, $vaccineId, $centerId, $firstDoseDateSql];
            $istmt = sqlsrv_query($conn, $insert, $params);

            if ($istmt && ($row = sqlsrv_fetch_array($istmt, SQLSRV_FETCH_ASSOC))) {
                $reservationId = $row['Reservation_ID'];
                header("Location: Patient_reservation.php?reservation_id=".$reservationId);
                exit();
            } else {
                $error = "❌ Failed to create reservation.";
            }
        }
    }
}

// Vaccine list for display
$list = $vaccines;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Patient Reservation</title>
    <link rel="stylesheet" href="css/style.css">
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


<main class="container" style="padding:1.5rem 0;">
    <h2>Choose Your Vaccination Carefully,
        <span class="accent"><?= htmlspecialchars($patientFName) ?></span>!</h2>
    <?php if ($message): ?><p class="message"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- Search -->
    <input type="text" id="vaccineSearch" placeholder="Enter Vaccine name..."
           style="margin:20px 0; padding:10px; width:100%; max-width:400px;">

    <!-- Vaccine Cards -->
    <div class="grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem;">
        <?php foreach ($list as $v): ?>
            <div class="card-item vaccine-card">
                <h4><?= htmlspecialchars($v['Vaccine_Name']) ?></h4>
                <p><strong>Gap days:</strong> <?= (int)$v['dose_gap_days'] ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($v['Status']) ?></p>
                <details><summary>Precautions</summary>
                    <p><?= nl2br(htmlspecialchars($v['Precautions'] ?? '')) ?></p>
                </details>
                <button class="reserve-btn"
                        data-vaccine="<?= $v['Vaccine_ID'] ?>"
                        data-vaccine-name="<?= htmlspecialchars($v['Vaccine_Name']) ?>">
                    Reserve Vaccine
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Modal -->
<div id="reservationModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3 id="modalTitle">Reserve Vaccine</h3>
        <form method="POST" action="Patient_Vaccines.php">
            <input type="hidden" name="reserve" value="1">
            <input type="hidden" name="vaccine_id" id="modalVaccineId">

            <label>Vaccination center</label>
            <select name="center_id" required>
                <option value="">Select center</option>
                <?php foreach ($centers as $c): ?>
                    <option value="<?= (int)$c['Center_ID'] ?>">
                        <?= htmlspecialchars($c['Center_Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>First dose date</label>
            <input type="date" name="first_dose_date" required>

            <p class="muted">Your Reservation ID will be shown after confirmation.</p>
            <button type="submit" class="btn">Confirm Reservation</button>
        </form>
    </div>
</div>

<script>
    // Search filter
    document.getElementById("vaccineSearch").addEventListener("keyup", function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll(".card-item").forEach(function(card) {
            let name = card.querySelector("h4").textContent.toLowerCase();
            card.style.display = name.includes(filter) ? "block" : "none";
        });
    });

    // Modal logic
    const modal = document.getElementById("reservationModal");
    const closeBtn = document.querySelector(".close");

    document.querySelectorAll(".reserve-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("modalTitle").textContent = "Reserve " + btn.dataset.vaccineName;
            document.getElementById("modalVaccineId").value = btn.dataset.vaccine;
            modal.style.display = "block";
        });
    });

    closeBtn.onclick = () => modal.style.display = "none";
    window.onclick = (event) => { if (event.target == modal) modal.style.display = "none"; };
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