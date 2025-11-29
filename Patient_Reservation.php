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
if ($conn === false) { die(print_r(sqlsrv_errors(), true)); }

// Resolve patient from session
$patientUsername = $_SESSION['user_email'] ?? '';
$sqlPatient = "SELECT Patient_ID, Patient_FName, Patient_LName FROM Patients WHERE Patient_Username = ?";
$stmtPatient = sqlsrv_query($conn, $sqlPatient, [$patientUsername]);
$patient = sqlsrv_fetch_array($stmtPatient, SQLSRV_FETCH_ASSOC);
$patientId = $patient['Patient_ID'];


?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Reservation — Healthioneers</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Sticky footer */
        body { display:flex; flex-direction:column; min-height:100vh; margin:0; font-family:Arial, sans-serif; background:#f9f9f9; }
        main { flex:1; padding:1.5rem 2rem; }

        h2 { margin-bottom:1rem; color:#333; }

        /* Reservation list styling */
        .reservation-list { display:block; text-align:left; margin:0; padding:0; }

        .reservation-item { width:100%; margin:12px 0; }

        .reservation-link {
            display:flex;
            justify-content:space-between;
            align-items:center;
            width:100%;
            padding:16px 20px;
            border-radius:8px;
            background:#fff;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            text-align:left;
            text-decoration:none;
            color:#333;
            font-weight:500;
            transition:transform 0.2s ease, box-shadow 0.3s ease;
        }
        .reservation-link:hover {
            transform:translateY(-2px);
            box-shadow:0 6px 16px rgba(0,0,0,0.12);
        }

        .res-vaccine { font-weight:700; color:#009688; }
        .res-center { flex:1; margin:0 12px; color:#555; }
        .res-date { color:#444; }

        .res-status { display:flex; align-items:center; font-weight:600; }
        .status-dot { width:8px; height:8px; border-radius:50%; margin-right:6px; }
        .status-ongoing { color:#e53935; }
        .status-ongoing .status-dot { background:#e53935; }
        .status-finished { color:#43a047; }
        .status-finished .status-dot { background:#43a047; }

        .reservation-details { margin:10px 0; padding:12px; background:#e6e6e6; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }

        .reservation-table { width:100%; border-collapse:collapse; }
        .reservation-table th { text-align:left; padding:8px; background:var(--teal); width:200px; }
        .reservation-table td { padding:8px; }

        .confirm-btn {
            background:linear-gradient(to right,#009688,#26a69a);
            color:#fff;
            border:none;
            padding:8px 14px;
            border-radius:6px;
            cursor:pointer;
            transition:background-position 0.3s ease, transform 0.2s ease;
        }
        .confirm-btn:hover {
            background-position:right center;
            transform:translateY(-2px);
        }

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
    <h2>My Reservations</h2>

    <?php
    // Reservation list
    $sqlList = "SELECT r.Reservation_ID, r.Scheduled_Date, r.Reservation_Status,
                   r.First_Confirmation, r.First_Confirmation_Date,
                   r.Second_Confirmation, r.Second_Confirmation_Date,
                   v.Vaccine_Name, c.Center_Name, c.Center_Address, v.dose_gap_days
            FROM Reservations r
            JOIN Vaccines v ON r.Vaccine_ID = v.Vaccine_ID
            JOIN Vaccination_Centers c ON r.Center_ID = c.Center_ID
            WHERE r.Patient_ID = ?
            ORDER BY r.Reservation_ID DESC";
    $stmtList = sqlsrv_query($conn, $sqlList, [$patientId]);

    if ($stmtList) {
        echo "<div class='reservation-list'>";
        while ($row = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
            $date = $row['Scheduled_Date'] instanceof DateTime ? $row['Scheduled_Date']->format('Y-m-d') : $row['Scheduled_Date'];
            $statusClass = strtolower($row['Reservation_Status']) === 'ongoing' ? 'status-ongoing' : 'status-finished';
            $statusText = $row['Reservation_Status'];

            echo "
        <div class='reservation-item'>
          <button type='button' class='reservation-link' data-id='".$row['Reservation_ID']."'>
            <div class='res-info'>
              <span class='res-vaccine'>".$row['Vaccine_Name']."</span> —
              <span class='res-center'>".$row['Center_Name']."</span> —
              <span class='res-date'>".$date."</span>
            </div>
            <div class='res-status ".$statusClass."'>
              <span class='status-dot'></span> ".$statusText."
            </div>
          </button>
          <div class='reservation-details' id='details-".$row['Reservation_ID']."' style='display:none;'>
            <table class='reservation-table'>
              <tr><th>Patient</th><td>".htmlspecialchars($patient['Patient_FName']." ".$patient['Patient_LName'])."</td></tr>
              <tr><th>Vaccine</th><td>".htmlspecialchars($row['Vaccine_Name'])."</td></tr>
              <tr><th>Center</th><td>".htmlspecialchars($row['Center_Name'])."</td></tr>
              <tr><th>Location</th><td>".htmlspecialchars($row['Center_Address'])."</td></tr>
              <tr><th>First Dose Date</th><td>".$date."</td></tr>";

            if (!empty($row['dose_gap_days'])) {
                $secondDate = date("Y-m-d", strtotime($date." +".$row['dose_gap_days']." days"));
                echo "<tr><th>Second Dose Date</th><td>".$secondDate."</td></tr>
                        <tr><th>Dose Gap</th><td>".$row['dose_gap_days']." days</td></tr>";
            }

            echo "<tr><th>Status</th><td>".$row['Reservation_Status']."</td></tr>
              <tr><th>Action</th><td>";

            // First dose confirmation status
            if (empty($row['First_Confirmation'])) {
                echo "Waiting for center to confirm first dose ⏳";
            } else {
                $fd = $row['First_Confirmation_Date'] instanceof DateTime
                    ? $row['First_Confirmation_Date']->format('Y-m-d')
                    : $row['First_Confirmation_Date'];
                echo "First Dose Confirmed on ".$fd;
            }

            // Second dose confirmation status
            if (!empty($row['Second_Confirmation_Date'])) {
                $sd = $row['Second_Confirmation_Date'] instanceof DateTime
                    ? $row['Second_Confirmation_Date']->format('Y-m-d')
                    : $row['Second_Confirmation_Date'];
                echo "<br>Second Dose Confirmed on ".$sd;
            } else {
                echo "<br>Waiting for center to confirm second dose ⏳";
            }

            echo "</td></tr>
            </table>
          </div>
        </div>";
        }
        echo "</div>";
    } else {
        echo "<p>No reservations found.</p>";
    }
    ?>
</main>

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

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll(".reservation-link").forEach(btn => {
            btn.addEventListener("click", () => {
                const id = btn.dataset.id;
                const details = document.getElementById("details-" + id);

                // Close all others
                document.querySelectorAll(".reservation-details").forEach(d => {
                    if (d !== details) d.style.display = "none";
                });

                // Toggle this one
                if (details.style.display === "none" || details.style.display === "") {
                    details.style.display = "block";
                } else {
                    details.style.display = "none";
                }
            });
        });
    });
</script>
</body>
</html>