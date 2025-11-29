<?php
session_start();

$serverName = "DESKTOP-OG4GIGD";
$connectionOptions = [
        "Database" => "Vaccination",
        "Authentication" => ""
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Fetch cities for dropdown
$cities = [];
$sql = "SELECT City_ID, City_Name FROM Cities ORDER BY City_Name ASC";
$stmt = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $cities[] = $row;
}

// Helper: keep old value only if error exists
function old($field) {
    global $error;
    return !empty($error) ? htmlspecialchars($_POST[$field] ?? '') : '';
}

// Handle registration
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname       = $_POST['fname'] ?? '';
    $lname       = $_POST['lname'] ?? '';
    $username    = $_POST['username'] ?? '';
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm-password'] ?? '';
    $city_id     = $_POST['city'] ?? '';
    $phone       = $_POST['phone'] ?? '';
    $national_id = $_POST['national_id'] ?? '';

    if ($password !== $confirmPass) {
        $error = "❌ Passwords do not match.";
    } elseif (!preg_match('/^\d{14}$/', $national_id)) {
        $error = "❌ National ID must be exactly 14 digits.";
    } else {
        // Check duplicate username
        $sql = "SELECT 1 FROM Patients WHERE Patient_Username = ?";
        $stmt = sqlsrv_query($conn, $sql, [$username]);
        if ($stmt && sqlsrv_fetch($stmt)) {
            $error = "❌ Username already registered.";
        }

        if (empty($error)) {
            $sql = "INSERT INTO Patients 
                    (Patient_FName, Patient_LName, Patient_Username, Patient_Password, Patient_City_ID, Patient_Phone, Patient_National_ID) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $params = [$fname, $lname, $username, $password, $city_id, $phone, $national_id];
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                header("Location: login.php");
                exit();
            } else {
                $error = "❌ Registration failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Register - Healthineers</title>

    <link rel="stylesheet" href="css/login&register.css"/>
    <link rel="stylesheet" href="css/style.css"/>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

    <style>
        #city {
            -webkit-appearance: none;  /* Chrome/Safari */
            -moz-appearance: none;     /* Firefox */
            appearance: none;          /* Standard */
            background: transparent;   /* remove default background */
            position: absolute;        /* take it out of flow */
            left: -9999px;             /* move it off-screen */
        }

        /* Clean up Select2 arrow box */
        .select2-container .select2-selection__arrow {
            background: none !important;   /* remove any default background */
            border: none !important;       /* remove border that can look like an arrow */
            top: 50% !important;
            transform: translateY(-50%) !important;
        }

        .select2-container .select2-selection__arrow {
            background: none !important;   /* remove any default background */
            border: none !important;       /* remove border that can look like an arrow */
            top: 50% !important;
            transform: translateY(-50%) !important;
        }

        .select2-container .select2-selection__arrow b {
            border-color: #333 transparent transparent transparent !important;
            border-style: solid;
            border-width: 5px 4px 0 4px;   /* triangle pointing down */
            display: inline-block;
            margin-left: -4px;
        }


        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 44px;                /* same height as inputs */
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 16px;
        padding: 0;                  /* remove default padding that offsets text */
        position: relative;          /* for arrow positioning */
        box-sizing: border-box;
        background-color: #fff;
        }
        .select2-container .select2-selection__rendered {
            display: block;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 44px !important; /* vertical centering via line-height */
            text-align: center !important; /* ✅ center text horizontally */
            color: #333;
        }
        .select2-container .select2-selection__placeholder {
            color: #888;                 /* placeholder style centered */
        }
        .select2-container .select2-selection__arrow {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            height: 24px;                /* keep arrow visible */
        }
        .select2-container .select2-selection__clear {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Dropdown and search field styling for consistency */

        .select2-dropdown {
            border-color: #ccc;
            border-radius: 4px;
            overflow: hidden;
        }
        .select2-search--dropdown .select2-search__field {
            font-size: 16px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            width: calc(100% - 16px);
            margin: 8px;
        }
        .select2-results__option {
            padding: 8px 12px;
            font-size: 15px;
        }

        /* Button spacing and centering */
        form button {
            display: block;
            margin: 20px auto 0;          /* space above, centered horizontally */
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
        }


    </style>

</head>
<body>
<section class="content" id="register">
    <div class="form-container">
        <h2>Register</h2>
        <form id="registerForm" method="POST" action="register.php">

            <label for="fname">First Name</label>
            <input type="text" id="fname" name="fname" value="<?= old('fname') ?>" required>

            <label for="lname">Last Name</label>
            <input type="text" id="lname" name="lname" value="<?= old('lname') ?>" required>

            <label for="username">Username (Email)</label>
            <input type="email" id="username" name="username" value="<?= old('username') ?>" required>

            <label for="city">City</label>
            <select id="city" name="city" required>
                <option value="">Select your city</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['City_ID'] ?>" <?= (!empty($error) && ($_POST['city'] ?? '') == $c['City_ID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['City_Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm-password">Confirm Password</label>
            <input type="password" id="confirm-password" name="confirm-password" required>

            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?= old('phone') ?>" required>

            <label for="national_id">National ID</label>
            <input type="text" id="national_id" name="national_id" maxlength="14"
                   pattern="\d{14}" title="National ID must be exactly 14 digits"
                   value="<?= old('national_id') ?>" required>

            <button type="submit">Register</button>
        </form>
        <p class="form-link">Already have an account? <a href="login.php">Login here</a></p>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
    </div>
</section>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#city').select2({
            placeholder: "Select your city",
            allowClear: true,
            width: '100%'
        });

        // Extra validation for National ID
        $('#registerForm').on('submit', function(e) {
            const nationalId = $('#national_id').val().trim();
            if (!/^\d{14}$/.test(nationalId)) {
                e.preventDefault();
                alert("⚠️ National ID must be exactly 14 digits.");
                $('#national_id').css('border', '1px solid red');
            }
        });
    });
</script>
</body>
</html>